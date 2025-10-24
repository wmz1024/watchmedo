// 媒体播放状态监控模块
use serde::{Deserialize, Serialize};
use std::path::PathBuf;
use std::fs;
use base64::{Engine as _, engine::general_purpose};

/// 媒体信息结构
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct MediaInfo {
    pub title: String,           // 媒体标题
    pub artist: Option<String>,  // 艺术家
    pub album: Option<String>,   // 专辑
    pub duration: Option<u64>,   // 总时长（秒）
    pub position: Option<u64>,   // 当前播放位置（秒）
    pub playback_status: String, // 播放状态: Playing, Paused, Stopped
    pub media_type: String,      // 媒体类型: Music, Video
    pub thumbnail: Option<String>, // Base64编码的缩略图
}

/// 媒体配置
#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct MediaSettings {
    pub enabled: bool,               // 是否启用媒体监控
    pub send_thumbnail: bool,        // 是否发送缩略图
    pub compress_thumbnail: bool,    // 是否压缩缩略图
    pub thumbnail_max_size_kb: u32,  // 缩略图最大大小（KB）
}

impl Default for MediaSettings {
    fn default() -> Self {
        Self {
            enabled: true,
            send_thumbnail: false,
            compress_thumbnail: true,
            thumbnail_max_size_kb: 16,
        }
    }
}

/// 获取媒体配置文件路径
fn get_media_config_path() -> Result<PathBuf, String> {
    let config_dir = tauri::api::path::config_dir()
        .ok_or_else(|| "无法获取配置目录".to_string())?;
    
    let app_config_dir = config_dir.join("watchmedo");
    
    if !app_config_dir.exists() {
        fs::create_dir_all(&app_config_dir)
            .map_err(|e| format!("无法创建配置目录: {}", e))?;
    }
    
    Ok(app_config_dir.join("media_settings.json"))
}

/// 保存媒体配置
pub fn save_media_settings(settings: &MediaSettings) -> Result<(), String> {
    let path = get_media_config_path()?;
    let json = serde_json::to_string_pretty(settings)
        .map_err(|e| format!("序列化配置失败: {}", e))?;
    
    fs::write(&path, json)
        .map_err(|e| format!("写入配置文件失败: {}", e))?;
    
    println!("媒体配置已保存到: {:?}", path);
    Ok(())
}

/// 加载媒体配置
pub fn load_media_settings() -> MediaSettings {
    let path = match get_media_config_path() {
        Ok(p) => p,
        Err(e) => {
            eprintln!("获取配置文件路径失败: {}", e);
            return MediaSettings::default();
        }
    };
    
    if !path.exists() {
        println!("配置文件不存在，使用默认设置");
        return MediaSettings::default();
    }
    
    match fs::read_to_string(&path) {
        Ok(content) => {
            match serde_json::from_str::<MediaSettings>(&content) {
                Ok(settings) => {
                    println!("从文件加载媒体配置成功");
                    settings
                }
                Err(e) => {
                    eprintln!("解析配置文件失败: {}", e);
                    MediaSettings::default()
                }
            }
        }
        Err(e) => {
            eprintln!("读取配置文件失败: {}", e);
            MediaSettings::default()
        }
    }
}

/// 获取当前播放的媒体信息
#[cfg(target_os = "windows")]
pub async fn get_current_media() -> Option<MediaInfo> {
    use windows::Media::Control::{
        GlobalSystemMediaTransportControlsSessionManager,
        GlobalSystemMediaTransportControlsSessionPlaybackStatus,
    };
    use windows::Foundation::IAsyncOperation;
    
    // 获取媒体会话管理器
    let manager = match GlobalSystemMediaTransportControlsSessionManager::RequestAsync() {
        Ok(async_op) => {
            // 使用 await 等待异步操作完成
            match async_op.await {
                Ok(mgr) => mgr,
                Err(_) => return None,
            }
        }
        Err(_) => return None,
    };
    
    // 获取当前会话
    let session = manager.GetCurrentSession().ok()?;
    
    // 获取播放信息
    let playback_info = session.GetPlaybackInfo().ok()?;
    let playback_status = playback_info.PlaybackStatus().ok()?;
    
    let status_str = match playback_status {
        GlobalSystemMediaTransportControlsSessionPlaybackStatus::Playing => "Playing",
        GlobalSystemMediaTransportControlsSessionPlaybackStatus::Paused => "Paused",
        GlobalSystemMediaTransportControlsSessionPlaybackStatus::Stopped => "Stopped",
        _ => "Unknown",
    };
    
    // 如果未播放，返回None
    if status_str == "Stopped" || status_str == "Unknown" {
        return None;
    }
    
    // 获取媒体属性
    let media_properties = match session.TryGetMediaPropertiesAsync() {
        Ok(async_op) => match async_op.await {
            Ok(props) => props,
            Err(_) => return None,
        },
        Err(_) => return None,
    };
    
    let title = media_properties.Title().ok()?
        .to_string_lossy();
    
    let artist = media_properties.Artist().ok()
        .map(|s| s.to_string_lossy());
    
    let album = media_properties.AlbumTitle().ok()
        .map(|s| s.to_string_lossy());
    
    // 获取时间轴信息
    let timeline = session.GetTimelineProperties().ok()?;
    
    let duration = timeline.EndTime().ok()
        .map(|d| d.Duration as u64 / 10_000_000); // 转换为秒
    
    let position = timeline.Position().ok()
        .map(|d| d.Duration as u64 / 10_000_000);
    
    // 判断媒体类型（简单判断）
    let media_type = if artist.is_some() || album.is_some() {
        "Music"
    } else {
        "Video"
    }.to_string();
    
    // 获取缩略图（如果配置允许）
    let thumbnail = get_media_thumbnail(&media_properties).await;
    
    Some(MediaInfo {
        title,
        artist,
        album,
        duration,
        position,
        playback_status: status_str.to_string(),
        media_type,
        thumbnail,
    })
}

#[cfg(target_os = "windows")]
async fn get_media_thumbnail(media_properties: &windows::Media::Control::GlobalSystemMediaTransportControlsSessionMediaProperties) -> Option<String> {
    use windows::Storage::Streams::DataReader;
    
    // 加载配置
    let settings = load_media_settings();
    
    // 如果不发送缩略图，直接返回
    if !settings.send_thumbnail {
        return None;
    }
    
    // 获取缩略图流
    let thumbnail_ref = media_properties.Thumbnail().ok()?;
    let stream = match thumbnail_ref.OpenReadAsync() {
        Ok(async_op) => match async_op.await {
            Ok(s) => s,
            Err(_) => return None,
        },
        Err(_) => return None,
    };
    
    let size = stream.Size().ok()? as u32;
    
    // 读取数据
    let reader = DataReader::CreateDataReader(&stream).ok()?;
    match reader.LoadAsync(size) {
        Ok(async_op) => match async_op.await {
            Ok(_) => {},
            Err(_) => return None,
        },
        Err(_) => return None,
    }
    
    let mut buffer = vec![0u8; size as usize];
    reader.ReadBytes(&mut buffer).ok()?;
    
    // 如果需要压缩
    if settings.compress_thumbnail {
        match compress_image(&buffer, settings.thumbnail_max_size_kb) {
            Ok(compressed) => Some(general_purpose::STANDARD.encode(&compressed)),
            Err(e) => {
                eprintln!("压缩图片失败: {}", e);
                // 如果压缩失败，检查原图大小
                if buffer.len() <= (settings.thumbnail_max_size_kb as usize * 1024) {
                    Some(general_purpose::STANDARD.encode(&buffer))
                } else {
                    None
                }
            }
        }
    } else {
        Some(general_purpose::STANDARD.encode(&buffer))
    }
}

/// 压缩图片到指定大小
fn compress_image(data: &[u8], max_size_kb: u32) -> Result<Vec<u8>, String> {
    use image::GenericImageView;
    use std::io::Cursor;
    
    // 加载图片
    let img = image::load_from_memory(data)
        .map_err(|e| format!("加载图片失败: {}", e))?;
    
    // 获取原始尺寸
    let (width, height) = img.dimensions();
    
    // 尝试不同的缩放比例
    let scales = [1.0, 0.8, 0.6, 0.5, 0.4, 0.3, 0.25, 0.2];
    
    for &scale in &scales {
        let new_width = (width as f32 * scale) as u32;
        let new_height = (height as f32 * scale) as u32;
        
        // 调整大小
        let resized = img.resize(new_width, new_height, image::imageops::FilterType::Lanczos3);
        
        // 尝试不同的质量
        let qualities = [85, 75, 65, 55, 45, 35, 25];
        
        for &quality in &qualities {
            let mut buffer = Vec::new();
            let mut cursor = Cursor::new(&mut buffer);
            
            // 使用JPEG格式和指定质量
            let encoder = image::codecs::jpeg::JpegEncoder::new_with_quality(&mut cursor, quality);
            resized.write_with_encoder(encoder)
                .map_err(|e| format!("编码图片失败: {}", e))?;
            
            // 检查大小
            if buffer.len() <= (max_size_kb as usize * 1024) {
                return Ok(buffer);
            }
        }
    }
    
    Err("无法将图片压缩到指定大小".to_string())
}

#[cfg(not(target_os = "windows"))]
pub async fn get_current_media() -> Option<MediaInfo> {
    // 其他平台暂不支持
    None
}

// Tauri Commands
#[tauri::command]
pub fn get_media_settings() -> MediaSettings {
    load_media_settings()
}

#[tauri::command]
pub fn set_media_settings(settings: MediaSettings) -> Result<(), String> {
    save_media_settings(&settings)
}

#[tauri::command]
pub async fn get_current_media_info() -> Option<MediaInfo> {
    get_current_media().await
}


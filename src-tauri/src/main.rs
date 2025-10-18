// Prevents additional console window on Windows in release, DO NOT REMOVE!!
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use std::sync::{Arc, Mutex};
use std::net::TcpListener;
use std::path::PathBuf;
use std::fs;
use axum::{
    extract::State,
    routing::get,
    Router, Json,
};
use serde::{Deserialize, Serialize};
use sysinfo::{System, Networks, Disks, Components};
use tauri::{
    Manager, SystemTray, SystemTrayEvent, SystemTrayMenu, SystemTrayMenuItem, 
    CustomMenuItem, AppHandle, WindowEvent
};
use auto_launch::AutoLaunch;
use std::time::SystemTime;

#[cfg(windows)]
mod windows_helper {
    use std::collections::HashMap;
    use windows::Win32::Foundation::{HWND, LPARAM, BOOL};
    use windows::Win32::UI::WindowsAndMessaging::{
        GetForegroundWindow, GetWindowThreadProcessId, EnumWindows, GetWindowTextW, IsWindowVisible,
    };

    pub fn get_focused_pid() -> Option<u32> {
        unsafe {
            let hwnd = GetForegroundWindow();
            if hwnd.0 == 0 {
                return None;
            }

            let mut pid: u32 = 0;
            GetWindowThreadProcessId(hwnd, Some(&mut pid));
            
            if pid == 0 {
                None
            } else {
                Some(pid)
            }
        }
    }

    pub fn get_window_titles() -> HashMap<u32, String> {
        unsafe {
            let mut titles: HashMap<u32, String> = HashMap::new();
            
            extern "system" fn enum_callback(hwnd: HWND, lparam: LPARAM) -> BOOL {
                unsafe {
                    let titles = &mut *(lparam.0 as *mut HashMap<u32, String>);
                    
                    // Only process visible windows
                    if !IsWindowVisible(hwnd).as_bool() {
                        return BOOL(1);
                    }

                    let mut pid: u32 = 0;
                    GetWindowThreadProcessId(hwnd, Some(&mut pid));

                    let mut text: [u16; 512] = [0; 512];
                    let len = GetWindowTextW(hwnd, &mut text);
                    
                    if len > 0 {
                        let title = String::from_utf16_lossy(&text[..len as usize]);
                        if !title.is_empty() && pid != 0 {
                            // Store the first non-empty title for each PID
                            titles.entry(pid).or_insert(title);
                        }
                    }

                    BOOL(1) // Continue enumeration
                }
            }

            let _ = EnumWindows(
                Some(enum_callback),
                LPARAM(&mut titles as *mut _ as isize),
            );

            titles
        }
    }
}

#[cfg(not(windows))]
mod windows_helper {
    use std::collections::HashMap;

    pub fn get_focused_pid() -> Option<u32> {
        None
    }

    pub fn get_window_titles() -> HashMap<u32, String> {
        HashMap::new()
    }
}

#[derive(Debug, Clone, Serialize, Deserialize)]
struct HttpSettings {
    port: u16,
    is_running: bool,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
struct ShareSettings {
    share_computer_name: bool,
    share_uptime: bool,
    share_cpu_usage: bool,
    share_memory_usage: bool,
    share_processes: bool,
    share_disks: bool,
    share_network: bool,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
struct AppSettings {
    auto_start_http: bool,
    auto_launch: bool,
    silent_launch: bool,
    process_limit: u32,
}

#[derive(Debug, Clone, Serialize, Deserialize)]
struct RemoteSettings {
    enabled: bool,
    url: String,
    token: String,
    interval_seconds: u64,
}

#[derive(Clone)]
struct AppState {
    http_settings: Arc<Mutex<HttpSettings>>,
    share_settings: Arc<Mutex<ShareSettings>>,
    app_settings: Arc<Mutex<AppSettings>>,
    remote_settings: Arc<Mutex<RemoteSettings>>,
    server_handle: Arc<Mutex<Option<tokio::task::JoinHandle<()>>>>,
    remote_push_handle: Arc<Mutex<Option<tokio::task::JoinHandle<()>>>>,
    last_push_time: Arc<Mutex<Option<SystemTime>>>,
}

#[derive(Serialize)]
struct SystemInfo {
    computer_name: Option<String>,
    uptime: Option<u64>,
    cpu_usage: Option<Vec<f32>>,
    memory_usage: Option<MemoryInfo>,
    processes: Option<Vec<ProcessInfo>>,
    disks: Option<Vec<DiskInfo>>,
    network: Option<Vec<NetworkInfo>>,
    battery: Option<BatteryInfo>,
}

#[derive(Serialize, Clone)]
struct BatteryInfo {
    percentage: f32,      // 电量百分比 0-100
    is_charging: bool,    // 是否正在充电
    status: String,       // 状态文本
}

#[derive(Serialize)]
struct MemoryInfo {
    total: u64,
    used: u64,
    percent: f64,
}

#[derive(Serialize, Clone)]
struct ProcessInfo {
    memory: u64,
    is_focused: bool,
    window_title: String,
    executable_name: String,
    pid: u32,
    cpu_usage: f32,
}

#[derive(Serialize)]
struct DiskInfo {
    name: String,
    mount_point: String,
    total_space: u64,
    available_space: u64,
}

#[derive(Serialize)]
struct NetworkInfo {
    name: String,
    received: u64,
    transmitted: u64,
}

// Dashboard specific structures
#[derive(Serialize)]
struct DashboardSystemInfo {
    computer_name: String,
    uptime: String,
    cpu_usage: f32,
    memory_usage: f32,
    memory_total: u64,
    memory_used: u64,
}

#[derive(Serialize)]
struct DashboardProcessInfo {
    name: String,
    window_title: String,
    pid: u32,
    cpu_usage: f32,
    memory_usage: u64,
    is_focused: bool,
}

#[derive(Serialize)]
struct DashboardDiskInfo {
    name: String,
    mount_point: String,
    total: u64,
    used: u64,
    usage_percent: f32,
}

#[derive(Serialize)]
struct DashboardNetworkInterfaceInfo {
    name: String,
    received: u64,
    transmitted: u64,
    received_rate: u64,
    transmitted_rate: u64,
}

#[derive(Serialize)]
struct DashboardNetworkInfo {
    interfaces: Vec<DashboardNetworkInterfaceInfo>,
    total_received: u64,
    total_transmitted: u64,
}

impl AppState {
    fn new() -> Self {
        Self {
            http_settings: Arc::new(Mutex::new(HttpSettings {
                port: 21536,
                is_running: false,
            })),
            share_settings: Arc::new(Mutex::new(ShareSettings {
                share_computer_name: true,
                share_uptime: true,
                share_cpu_usage: true,
                share_memory_usage: true,
                share_processes: true,
                share_disks: true,
                share_network: true,
            })),
            app_settings: Arc::new(Mutex::new(AppSettings {
                auto_start_http: true,
                auto_launch: false,
                silent_launch: false,
                process_limit: 20,
            })),
            remote_settings: Arc::new(Mutex::new(RemoteSettings {
                enabled: false,
                url: String::new(),
                token: String::new(),
                interval_seconds: 60,
            })),
            server_handle: Arc::new(Mutex::new(None)),
            remote_push_handle: Arc::new(Mutex::new(None)),
            last_push_time: Arc::new(Mutex::new(None)),
        }
    }
}

// 获取配置文件路径
fn get_config_dir() -> Result<PathBuf, String> {
    let config_dir = tauri::api::path::config_dir()
        .ok_or_else(|| "无法获取配置目录".to_string())?;
    
    let app_config_dir = config_dir.join("watchmedo");
    
    // 确保配置目录存在
    if !app_config_dir.exists() {
        fs::create_dir_all(&app_config_dir)
            .map_err(|e| format!("无法创建配置目录: {}", e))?;
    }
    
    Ok(app_config_dir)
}

// 获取远程设置文件路径
fn get_remote_settings_path() -> Result<PathBuf, String> {
    let config_dir = get_config_dir()?;
    Ok(config_dir.join("remote_settings.json"))
}

// 保存远程设置到文件
fn save_remote_settings(settings: &RemoteSettings) -> Result<(), String> {
    let path = get_remote_settings_path()?;
    let json = serde_json::to_string_pretty(settings)
        .map_err(|e| format!("序列化设置失败: {}", e))?;
    
    fs::write(&path, json)
        .map_err(|e| format!("写入配置文件失败: {}", e))?;
    
    println!("远程设置已保存到: {:?}", path);
    Ok(())
}

// 从文件加载远程设置
fn load_remote_settings() -> Option<RemoteSettings> {
    let path = match get_remote_settings_path() {
        Ok(p) => p,
        Err(e) => {
            eprintln!("获取配置文件路径失败: {}", e);
            return None;
        }
    };
    
    if !path.exists() {
        println!("配置文件不存在，使用默认设置");
        return None;
    }
    
    match fs::read_to_string(&path) {
        Ok(content) => {
            match serde_json::from_str::<RemoteSettings>(&content) {
                Ok(settings) => {
                    println!("从文件加载远程设置成功: {:?}", path);
                    Some(settings)
                }
                Err(e) => {
                    eprintln!("解析配置文件失败: {}", e);
                    None
                }
            }
        }
        Err(e) => {
            eprintln!("读取配置文件失败: {}", e);
            None
        }
    }
}

#[tauri::command]
fn get_http_settings(state: tauri::State<AppState>) -> HttpSettings {
    state.http_settings.lock().unwrap().clone()
}

#[tauri::command]
fn get_share_settings(state: tauri::State<AppState>) -> ShareSettings {
    state.share_settings.lock().unwrap().clone()
}

#[tauri::command]
fn get_app_settings(state: tauri::State<AppState>) -> AppSettings {
    state.app_settings.lock().unwrap().clone()
}

#[tauri::command]
fn get_remote_settings(state: tauri::State<AppState>) -> RemoteSettings {
    state.remote_settings.lock().unwrap().clone()
}

#[tauri::command]
fn set_http_port(port: u16, state: tauri::State<AppState>) {
    state.http_settings.lock().unwrap().port = port;
}

#[tauri::command]
fn set_share_settings(settings: ShareSettings, state: tauri::State<AppState>) {
    *state.share_settings.lock().unwrap() = settings;
}

#[tauri::command]
fn set_app_settings(settings: AppSettings, state: tauri::State<AppState>) {
    *state.app_settings.lock().unwrap() = settings;
}

#[tauri::command]
fn set_remote_settings(settings: RemoteSettings, state: tauri::State<AppState>) -> Result<(), String> {
    // 保存到状态
    *state.remote_settings.lock().unwrap() = settings.clone();
    
    // 持久化到文件
    save_remote_settings(&settings)?;
    
    Ok(())
}

#[tauri::command]
async fn set_auto_launch(enable: bool, state: tauri::State<'_, AppState>) -> Result<(), String> {
    let app_path = std::env::current_exe().map_err(|e| e.to_string())?;
    let app_name = "WatchMeDo";
    
    // Check if silent launch is enabled
    let silent_launch = state.app_settings.lock().unwrap().silent_launch;
    let args = if silent_launch {
        vec!["--silent"]
    } else {
        vec![]
    };
    
    let auto = AutoLaunch::new(
        app_name,
        &app_path.to_string_lossy(),
        &args,
    );

    if enable {
        auto.enable().map_err(|e| e.to_string())?;
    } else {
        auto.disable().map_err(|e| e.to_string())?;
    }

    state.app_settings.lock().unwrap().auto_launch = enable;
    Ok(())
}

async fn get_system_info(State(state): State<Arc<AppState>>) -> Json<SystemInfo> {
    let share_settings = state.share_settings.lock().unwrap().clone();
    let app_settings = state.app_settings.lock().unwrap().clone();
    let mut sys = System::new_all();
    sys.refresh_all();

    let computer_name = if share_settings.share_computer_name {
        hostname::get().ok().and_then(|h| h.into_string().ok())
    } else {
        None
    };

    let uptime = if share_settings.share_uptime {
        Some(System::uptime())
    } else {
        None
    };

    let cpu_usage = if share_settings.share_cpu_usage {
        Some(sys.cpus().iter().map(|cpu| cpu.cpu_usage()).collect())
    } else {
        None
    };

    let memory_usage = if share_settings.share_memory_usage {
        let total = sys.total_memory();
        let used = sys.used_memory();
        Some(MemoryInfo {
            total,
            used,
            percent: (used as f64 / total as f64) * 100.0,
        })
    } else {
        None
    };

    let processes = if share_settings.share_processes {
        // Get focused window PID
        let focused_pid = windows_helper::get_focused_pid();
        
        // Get all window titles
        let window_titles = windows_helper::get_window_titles();

        let mut all_processes: Vec<ProcessInfo> = sys.processes()
            .iter()
            .map(|(pid, process)| {
                let pid_u32 = pid.as_u32();
                let is_focused = focused_pid.map_or(false, |fp| fp == pid_u32);
                
                // Use window title if available, otherwise use process name
                let window_title = window_titles.get(&pid_u32)
                    .map(|s| s.clone())
                    .unwrap_or_else(|| process.name().to_string());

                ProcessInfo {
                    memory: process.memory(),
                    is_focused,
                    window_title,
                    executable_name: process.name().to_string(),
                    pid: pid_u32,
                    cpu_usage: process.cpu_usage(),
                }
            })
            .collect();

        // Sort by CPU usage descending
        all_processes.sort_by(|a, b| b.cpu_usage.partial_cmp(&a.cpu_usage).unwrap());
        
        // Get the focused process if any
        let focused_process = all_processes.iter()
            .find(|p| p.is_focused)
            .cloned();
        
        // Take top N processes
        let limit = app_settings.process_limit as usize;
        let mut result: Vec<ProcessInfo> = all_processes.into_iter().take(limit).collect();
        
        // Add focused process if it's not already in the list
        if let Some(focused) = focused_process {
            if !result.iter().any(|p| p.pid == focused.pid) {
                result.insert(0, focused);
            }
        }

        Some(result)
    } else {
        None
    };

    let disks = if share_settings.share_disks {
        let disks_list = Disks::new_with_refreshed_list();
        Some(
            disks_list
                .iter()
                .map(|disk| DiskInfo {
                    name: disk.name().to_string_lossy().to_string(),
                    mount_point: disk.mount_point().to_string_lossy().to_string(),
                    total_space: disk.total_space(),
                    available_space: disk.available_space(),
                })
                .collect(),
        )
    } else {
        None
    };

    let network = if share_settings.share_network {
        let networks = Networks::new_with_refreshed_list();
        Some(
            networks
                .iter()
                .map(|(name, network)| NetworkInfo {
                    name: name.to_string(),
                    received: network.total_received(),
                    transmitted: network.total_transmitted(),
                })
                .collect(),
        )
    } else {
        None
    };

    // 获取电池信息（如果是笔记本）
    let battery = get_battery_info();

    Json(SystemInfo {
        computer_name,
        uptime,
        cpu_usage,
        memory_usage,
        processes,
        disks,
        network,
        battery,
    })
}

// 获取电池信息
fn get_battery_info() -> Option<BatteryInfo> {
    // 尝试使用sysinfo获取电池信息
    // 注意：sysinfo 0.30版本对电池支持有限，可能需要使用其他方法
    
    #[cfg(target_os = "windows")]
    {
        // Windows系统：通过WMI或其他方式获取
        return get_battery_info_windows();
    }
    
    #[cfg(target_os = "linux")]
    {
        // Linux系统：从/sys/class/power_supply读取
        return get_battery_info_linux();
    }
    
    #[cfg(target_os = "macos")]
    {
        // macOS系统：使用IOKit
        return get_battery_info_macos();
    }
    
    #[allow(unreachable_code)]
    None
}

#[cfg(target_os = "windows")]
fn get_battery_info_windows() -> Option<BatteryInfo> {
    use std::process::Command;
    use std::os::windows::process::CommandExt;
    
    // CREATE_NO_WINDOW = 0x08000000
    // 使用此标志防止创建新的控制台窗口
    const CREATE_NO_WINDOW: u32 = 0x08000000;
    
    // 使用WMIC命令获取电池信息，但不显示窗口
    let output = Command::new("WMIC")
        .args(&["Path", "Win32_Battery", "Get", "EstimatedChargeRemaining,BatteryStatus"])
        .creation_flags(CREATE_NO_WINDOW)
        .output()
        .ok()?;
    
    let output_str = String::from_utf8_lossy(&output.stdout);
    let lines: Vec<&str> = output_str.lines().collect();
    
    if lines.len() < 2 {
        return None; // 没有电池
    }
    
    // 解析输出
    for line in lines.iter().skip(1) {
        let parts: Vec<&str> = line.split_whitespace().collect();
        if parts.len() >= 2 {
            if let (Ok(status), Ok(percentage)) = (parts[0].parse::<u32>(), parts[1].parse::<f32>()) {
                let is_charging = status == 2; // 2表示正在充电
                let status_text = if is_charging {
                    "充电中".to_string()
                } else if percentage > 20.0 {
                    "使用电池".to_string()
                } else {
                    "电量低".to_string()
                };
                
                return Some(BatteryInfo {
                    percentage,
                    is_charging,
                    status: status_text,
                });
            }
        }
    }
    
    None
}

#[cfg(target_os = "linux")]
fn get_battery_info_linux() -> Option<BatteryInfo> {
    use std::fs;
    
    // 尝试从 /sys/class/power_supply/BAT0 或 BAT1 读取
    for bat in &["BAT0", "BAT1"] {
        let base_path = format!("/sys/class/power_supply/{}", bat);
        
        if let (Ok(capacity), Ok(status)) = (
            fs::read_to_string(format!("{}/capacity", base_path)),
            fs::read_to_string(format!("{}/status", base_path))
        ) {
            if let Ok(percentage) = capacity.trim().parse::<f32>() {
                let status = status.trim();
                let is_charging = status == "Charging" || status == "Full";
                let status_text = match status {
                    "Charging" => "充电中",
                    "Discharging" => "使用电池",
                    "Full" => "已充满",
                    _ => "未知"
                }.to_string();
                
                return Some(BatteryInfo {
                    percentage,
                    is_charging,
                    status: status_text,
                });
            }
        }
    }
    
    None
}

#[cfg(target_os = "macos")]
fn get_battery_info_macos() -> Option<BatteryInfo> {
    use std::process::Command;
    
    // 使用pmset命令获取电池信息
    let output = Command::new("pmset")
        .args(&["-g", "batt"])
        .output()
        .ok()?;
    
    let output_str = String::from_utf8_lossy(&output.stdout);
    
    // 解析输出，格式类似：Now drawing from 'Battery Power'
    //  -InternalBattery-0 (id=1234567)	85%; discharging; 5:23 remaining
    for line in output_str.lines() {
        if line.contains("InternalBattery") {
            // 解析百分比
            if let Some(percent_pos) = line.find('%') {
                let before_percent = &line[..percent_pos];
                if let Some(tab_pos) = before_percent.rfind('\t') {
                    let percent_str = &before_percent[tab_pos+1..].trim();
                    if let Ok(percentage) = percent_str.parse::<f32>() {
                        let is_charging = line.contains("charging") && !line.contains("discharging");
                        let status_text = if line.contains("charged") || line.contains("charged") {
                            "已充满"
                        } else if is_charging {
                            "充电中"
                        } else {
                            "使用电池"
                        }.to_string();
                        
                        return Some(BatteryInfo {
                            percentage,
                            is_charging,
                            status: status_text,
                        });
                    }
                }
            }
        }
    }
    
    None
}

async fn start_http_server_internal(state: AppState) -> Result<(), String> {
    let port = state.http_settings.lock().unwrap().port;
    
    // Check if port is available
    if TcpListener::bind(format!("0.0.0.0:{}", port)).is_err() {
        return Err(format!("Port {} is already in use", port));
    }

    let app_state = Arc::new(state.clone());
    
    let app = Router::new()
        .route("/api/system", get(get_system_info))
        .with_state(app_state.clone())
        .layer(
            tower_http::cors::CorsLayer::new()
                .allow_origin(tower_http::cors::Any)
                .allow_methods(tower_http::cors::Any)
                .allow_headers(tower_http::cors::Any),
        );

    let addr = format!("0.0.0.0:{}", port);
    let listener = tokio::net::TcpListener::bind(&addr)
        .await
        .map_err(|e| e.to_string())?;

    let handle = tokio::spawn(async move {
        axum::serve(listener, app)
            .await
            .expect("Failed to start server");
    });

    *state.server_handle.lock().unwrap() = Some(handle);
    state.http_settings.lock().unwrap().is_running = true;

    Ok(())
}

#[tauri::command]
async fn start_http_server(state: tauri::State<'_, AppState>) -> Result<(), String> {
    start_http_server_internal((*state).clone()).await
}

#[tauri::command]
async fn stop_http_server(state: tauri::State<'_, AppState>) -> Result<(), String> {
    if let Some(handle) = state.server_handle.lock().unwrap().take() {
        handle.abort();
    }
    state.http_settings.lock().unwrap().is_running = false;
    Ok(())
}

async fn start_remote_push_internal(state: AppState) -> Result<(), String> {
    let remote_settings = state.remote_settings.lock().unwrap().clone();
    let http_port = state.http_settings.lock().unwrap().port;
    
    if remote_settings.url.is_empty() {
        return Err("远程URL未配置".to_string());
    }

    // Stop existing task if any
    if let Some(handle) = state.remote_push_handle.lock().unwrap().take() {
        handle.abort();
    }

    let state_clone = state.clone();
    let handle = tokio::spawn(async move {
        let client = reqwest::Client::new();
        let local_url = format!("http://localhost:{}/api/system", http_port);
        
        loop {
            let remote_settings = state_clone.remote_settings.lock().unwrap().clone();
            
            // Check if still enabled
            if !remote_settings.enabled {
                break;
            }

            // Fetch data from local API
            match reqwest::get(&local_url).await {
                Ok(response) => {
                    if let Ok(json_data) = response.text().await {
                        // POST to remote server
                        let mut request = client
                            .post(&remote_settings.url)
                            .header("Content-Type", "application/json");
                        
                        // Add token header if provided
                        if !remote_settings.token.is_empty() {
                            request = request.header("X-Device-Token", &remote_settings.token);
                        }
                        
                        match request
                            .body(json_data)
                            .send()
                            .await
                        {
                            Ok(resp) => {
                                if resp.status().is_success() {
                                    println!("Remote push successful");
                                    // Update last push time
                                    *state_clone.last_push_time.lock().unwrap() = Some(SystemTime::now());
                                } else {
                                    eprintln!("Remote push failed: {}", resp.status());
                                }
                            }
                            Err(e) => {
                                eprintln!("Failed to push to remote: {}", e);
                            }
                        }
                    }
                }
                Err(e) => {
                    eprintln!("Failed to fetch local API: {}", e);
                }
            }

            // Wait for next interval
            tokio::time::sleep(tokio::time::Duration::from_secs(remote_settings.interval_seconds)).await;
        }
    });

    *state.remote_push_handle.lock().unwrap() = Some(handle);
    state.remote_settings.lock().unwrap().enabled = true;
    
    // 保存设置到文件
    let settings = state.remote_settings.lock().unwrap().clone();
    save_remote_settings(&settings)?;
    
    Ok(())
}

#[tauri::command]
async fn start_remote_push(state: tauri::State<'_, AppState>) -> Result<(), String> {
    start_remote_push_internal((*state).clone()).await
}

#[tauri::command]
async fn stop_remote_push(state: tauri::State<'_, AppState>) -> Result<(), String> {
    if let Some(handle) = state.remote_push_handle.lock().unwrap().take() {
        handle.abort();
    }
    state.remote_settings.lock().unwrap().enabled = false;
    
    // 保存设置到文件
    let settings = state.remote_settings.lock().unwrap().clone();
    save_remote_settings(&settings)?;
    
    Ok(())
}

#[tauri::command]
fn get_last_push_time(state: tauri::State<AppState>) -> Result<String, String> {
    let last_time = state.last_push_time.lock().unwrap();
    
    if let Some(time) = *last_time {
        match time.duration_since(SystemTime::UNIX_EPOCH) {
            Ok(duration) => {
                let timestamp = duration.as_secs();
                // Convert to readable format
                let now = SystemTime::now()
                    .duration_since(SystemTime::UNIX_EPOCH)
                    .unwrap()
                    .as_secs();
                let elapsed = now.saturating_sub(timestamp);
                
                let time_str = if elapsed < 60 {
                    format!("{}秒前", elapsed)
                } else if elapsed < 3600 {
                    format!("{}分钟前", elapsed / 60)
                } else if elapsed < 86400 {
                    format!("{}小时前", elapsed / 3600)
                } else {
                    format!("{}天前", elapsed / 86400)
                };
                
                Ok(time_str)
            }
            Err(_) => Err("无法获取时间".to_string()),
        }
    } else {
        Err("暂无推送记录".to_string())
    }
}

#[tauri::command]
async fn test_remote_push(state: tauri::State<'_, AppState>) -> Result<(), String> {
    let remote_settings = state.remote_settings.lock().unwrap().clone();
    let http_port = state.http_settings.lock().unwrap().port;
    
    if remote_settings.url.is_empty() {
        return Err("远程URL未配置".to_string());
    }

    // Fetch data from local API
    let local_url = format!("http://localhost:{}/api/system", http_port);
    let response = reqwest::get(&local_url)
        .await
        .map_err(|e| format!("获取本地数据失败: {}", e))?;
    
    let json_data = response.text()
        .await
        .map_err(|e| format!("读取响应数据失败: {}", e))?;

    // POST to remote server
    let client = reqwest::Client::new();
    let mut request = client
        .post(&remote_settings.url)
        .header("Content-Type", "application/json");
    
    // Add token header if provided
    if !remote_settings.token.is_empty() {
        request = request.header("X-Device-Token", &remote_settings.token);
    }
    
    let resp = request
        .body(json_data)
        .send()
        .await
        .map_err(|e| format!("推送失败: {}", e))?;
    
    if resp.status().is_success() {
        // Update last push time
        *state.last_push_time.lock().unwrap() = Some(SystemTime::now());
        Ok(())
    } else {
        Err(format!("推送失败，服务器返回状态码: {}", resp.status()))
    }
}

#[tauri::command]
fn get_system_info_dashboard() -> DashboardSystemInfo {
    let mut sys = System::new_all();
    sys.refresh_all();

    let computer_name = hostname::get()
        .ok()
        .and_then(|h| h.into_string().ok())
        .unwrap_or_else(|| "Unknown".to_string());

    let uptime_secs = System::uptime();
    let hours = uptime_secs / 3600;
    let minutes = (uptime_secs % 3600) / 60;
    let uptime = format!("{}h {}m", hours, minutes);

    let cpu_usage = sys.cpus().iter().map(|cpu| cpu.cpu_usage()).sum::<f32>() / sys.cpus().len() as f32;

    let total = sys.total_memory();
    let used = sys.used_memory();
    let memory_usage = (used as f32 / total as f32) * 100.0;

    DashboardSystemInfo {
        computer_name,
        uptime,
        cpu_usage,
        memory_usage,
        memory_total: total,
        memory_used: used,
    }
}

#[tauri::command]
fn get_processes() -> Vec<DashboardProcessInfo> {
    let mut sys = System::new_all();
    sys.refresh_all();

    // Get focused window PID
    let focused_pid = windows_helper::get_focused_pid();
    
    // Get all window titles
    let window_titles = windows_helper::get_window_titles();

    let mut processes: Vec<DashboardProcessInfo> = sys
        .processes()
        .iter()
        .map(|(pid, process)| {
            let pid_u32 = pid.as_u32();
            let is_focused = focused_pid.map_or(false, |fp| fp == pid_u32);
            
            // Use window title if available, otherwise use process name
            let window_title = window_titles.get(&pid_u32)
                .map(|s| s.clone())
                .unwrap_or_else(|| process.name().to_string());

            DashboardProcessInfo {
                name: process.name().to_string(),
                window_title,
                pid: pid_u32,
                cpu_usage: process.cpu_usage(),
                memory_usage: process.memory(),
                is_focused,
            }
        })
        .collect();

    // Sort: focused first, then by CPU usage descending
    processes.sort_by(|a, b| {
        match (a.is_focused, b.is_focused) {
            (true, false) => std::cmp::Ordering::Less,
            (false, true) => std::cmp::Ordering::Greater,
            _ => b.cpu_usage.partial_cmp(&a.cpu_usage).unwrap(),
        }
    });
    
    // Return top 20
    processes.truncate(20);
    processes
}

#[tauri::command]
fn get_disks() -> Vec<DashboardDiskInfo> {
    let disks = Disks::new_with_refreshed_list();
    
    disks
        .iter()
        .map(|disk| {
            let total = disk.total_space();
            let available = disk.available_space();
            let used = total - available;
            let usage_percent = if total > 0 {
                (used as f32 / total as f32) * 100.0
            } else {
                0.0
            };

            DashboardDiskInfo {
                name: disk.name().to_string_lossy().to_string(),
                mount_point: disk.mount_point().to_string_lossy().to_string(),
                total,
                used,
                usage_percent,
            }
        })
        .collect()
}

#[tauri::command]
fn get_network_info_dashboard() -> DashboardNetworkInfo {
    use std::thread;
    use std::time::Duration;
    
    // First sample
    let mut networks = Networks::new_with_refreshed_list();
    let first_samples: std::collections::HashMap<String, (u64, u64)> = networks
        .iter()
        .map(|(name, network)| {
            (name.to_string(), (network.total_received(), network.total_transmitted()))
        })
        .collect();
    
    // Wait a short time
    thread::sleep(Duration::from_millis(500));
    
    // Second sample
    networks.refresh();
    
    let mut total_received = 0u64;
    let mut total_transmitted = 0u64;

    let interfaces: Vec<DashboardNetworkInterfaceInfo> = networks
        .iter()
        .map(|(name, network)| {
            let received = network.total_received();
            let transmitted = network.total_transmitted();
            
            total_received += received;
            total_transmitted += transmitted;

            // Calculate rate based on the difference
            let (prev_received, prev_transmitted) = first_samples.get(name.as_str())
                .copied()
                .unwrap_or((0, 0));
            
            // Rate is bytes per 0.5 second, so multiply by 2 to get bytes/second
            let received_rate = ((received.saturating_sub(prev_received)) * 2).min(u64::MAX);
            let transmitted_rate = ((transmitted.saturating_sub(prev_transmitted)) * 2).min(u64::MAX);

            DashboardNetworkInterfaceInfo {
                name: name.to_string(),
                received,
                transmitted,
                received_rate,
                transmitted_rate,
            }
        })
        .collect();

    DashboardNetworkInfo {
        interfaces,
        total_received,
        total_transmitted,
    }
}

fn create_tray() -> SystemTray {
    let quit = CustomMenuItem::new("quit".to_string(), "退出");
    let show = CustomMenuItem::new("show".to_string(), "显示");
    let hide = CustomMenuItem::new("hide".to_string(), "隐藏");
    
    let tray_menu = SystemTrayMenu::new()
        .add_item(show)
        .add_item(hide)
        .add_native_item(SystemTrayMenuItem::Separator)
        .add_item(quit);

    SystemTray::new().with_menu(tray_menu)
}

fn handle_tray_event(app: &AppHandle, event: SystemTrayEvent) {
    match event {
        SystemTrayEvent::LeftClick { .. } => {
            let window = app.get_window("main").unwrap();
            if window.is_visible().unwrap() {
                window.hide().unwrap();
            } else {
                window.show().unwrap();
                window.set_focus().unwrap();
            }
        }
        SystemTrayEvent::MenuItemClick { id, .. } => {
            match id.as_str() {
                "quit" => {
                    std::process::exit(0);
                }
                "show" => {
                    let window = app.get_window("main").unwrap();
                    window.show().unwrap();
                    window.set_focus().unwrap();
                }
                "hide" => {
                    let window = app.get_window("main").unwrap();
                    window.hide().unwrap();
                }
                _ => {}
            }
        }
        _ => {}
    }
}

fn main() {
    let mut app_state = AppState::new();
    
    // 从文件加载远程设置
    if let Some(saved_settings) = load_remote_settings() {
        println!("加载保存的远程设置: enabled={}, url={}", saved_settings.enabled, saved_settings.url);
        // 加载设置但不自动启动，因为需要HTTP服务器先运行
        *app_state.remote_settings.lock().unwrap() = saved_settings;
    }

    tauri::Builder::default()
        .manage(app_state)
        .setup(|app| {
            let window = app.get_window("main").unwrap();
            
            // Check if launched with silent mode
            let args: Vec<String> = std::env::args().collect();
            let is_silent = args.iter().any(|arg| arg == "--silent");
            
            if is_silent {
                window.hide().unwrap();
            }

            // Handle window close event
            let window_clone = window.clone();
            window.on_window_event(move |event| {
                if let WindowEvent::CloseRequested { api, .. } = event {
                    // Prevent closing, hide instead
                    window_clone.hide().unwrap();
                    api.prevent_close();
                }
            });

            // Auto start HTTP server if enabled
            let app_state = app.state::<AppState>();
            let app_settings = app_state.app_settings.lock().unwrap().clone();
            if app_settings.auto_start_http {
                let state = app_state.inner().clone();
                tauri::async_runtime::spawn(async move {
                    let _ = start_http_server_internal(state.clone()).await;
                    
                    // 等待HTTP服务器启动
                    tokio::time::sleep(tokio::time::Duration::from_secs(1)).await;
                    
                    // 如果远程推送在上次是启用的，自动重启
                    let remote_settings = state.remote_settings.lock().unwrap().clone();
                    if remote_settings.enabled && !remote_settings.url.is_empty() {
                        println!("自动启动远程推送服务...");
                        let state_clone = state.clone();
                        tokio::spawn(async move {
                            match start_remote_push_internal(state_clone).await {
                                Ok(_) => println!("远程推送服务已自动启动"),
                                Err(e) => eprintln!("自动启动远程推送失败: {}", e),
                            }
                        });
                    }
                });
            }

            Ok(())
        })
        .system_tray(create_tray())
        .on_system_tray_event(handle_tray_event)
        .invoke_handler(tauri::generate_handler![
            get_http_settings,
            get_share_settings,
            get_app_settings,
            get_remote_settings,
            set_http_port,
            set_share_settings,
            set_app_settings,
            set_remote_settings,
            set_auto_launch,
            start_http_server,
            stop_http_server,
            start_remote_push,
            stop_remote_push,
            get_last_push_time,
            test_remote_push,
            get_system_info_dashboard,
            get_processes,
            get_disks,
            get_network_info_dashboard,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}

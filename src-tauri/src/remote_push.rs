use std::sync::{Arc, Mutex};
use std::time::{Duration, SystemTime, UNIX_EPOCH};
use reqwest::Client;
use serde::{Deserialize, Serialize};
use tokio::time;

#[derive(Debug, Clone, Serialize, Deserialize)]
pub struct RemoteSettings {
    pub enabled: bool,
    pub url: String,
    pub interval_seconds: u64,
}

#[derive(Clone)]
pub struct RemotePushState {
    settings: Arc<Mutex<RemoteSettings>>,
    last_push_time: Arc<Mutex<Option<String>>>,
    push_handle: Arc<Mutex<Option<tokio::task::JoinHandle<()>>>>,
    http_port: Arc<Mutex<u16>>,
}

impl RemotePushState {
    pub fn new() -> Self {
        Self {
            settings: Arc::new(Mutex::new(RemoteSettings {
                enabled: false,
                url: String::new(),
                interval_seconds: 60,
            })),
            last_push_time: Arc::new(Mutex::new(None)),
            push_handle: Arc::new(Mutex::new(None)),
            http_port: Arc::new(Mutex::new(21536)), // 默认端口
        }
    }

    pub fn get_settings(&self) -> RemoteSettings {
        self.settings.lock().unwrap().clone()
    }

    pub fn set_settings(&self, settings: RemoteSettings) {
        *self.settings.lock().unwrap() = settings;
    }

    pub fn get_last_push_time(&self) -> Option<String> {
        self.last_push_time.lock().unwrap().clone()
    }

    pub fn update_http_port(&self, port: u16) {
        *self.http_port.lock().unwrap() = port;
    }

    pub async fn start_push(&self) -> Result<(), String> {
        let settings = self.get_settings();
        if !settings.enabled || settings.url.is_empty() {
            return Err("Remote push is not properly configured".to_string());
        }

        let client = Client::new();
        let state = self.clone();

        let handle = tokio::spawn(async move {
            let mut interval = time::interval(Duration::from_secs(state.settings.lock().unwrap().interval_seconds));

            loop {
                interval.tick().await;
                
                let port = *state.http_port.lock().unwrap();
                let local_url = format!("http://localhost:{}/api/system", port);
                
                // 从本地 API 获取数据
                match client.get(&local_url).send().await {
                    Ok(response) => {
                        if let Ok(data) = response.json::<serde_json::Value>().await {
                            // 推送到远程服务器
                            let settings = state.settings.lock().unwrap();
                            if let Err(e) = client.post(&settings.url)
                                .json(&data)
                                .send()
                                .await
                            {
                                eprintln!("Failed to push data: {}", e);
                                continue;
                            }

                            // 更新最后推送时间
                            let now = SystemTime::now()
                                .duration_since(UNIX_EPOCH)
                                .unwrap()
                                .as_secs();
                            let datetime = chrono::DateTime::from_timestamp(now as i64, 0)
                                .map(|dt| dt.format("%Y-%m-%d %H:%M:%S").to_string())
                                .unwrap_or_else(|| "Unknown".to_string());
                            *state.last_push_time.lock().unwrap() = Some(datetime);
                        }
                    }
                    Err(e) => {
                        eprintln!("Failed to fetch local data: {}", e);
                    }
                }
            }
        });

        *self.push_handle.lock().unwrap() = Some(handle);
        Ok(())
    }

    pub fn stop_push(&self) -> Result<(), String> {
        if let Some(handle) = self.push_handle.lock().unwrap().take() {
            handle.abort();
            Ok(())
        } else {
            Err("No push task running".to_string())
        }
    }

    pub async fn test_push(&self) -> Result<(), String> {
        let settings = self.get_settings();
        if settings.url.is_empty() {
            return Err("Remote URL is not configured".to_string());
        }

        let client = Client::new();
        let port = *self.http_port.lock().unwrap();
        let local_url = format!("http://localhost:{}/api/system", port);

        // 从本地 API 获取数据
        let response = client.get(&local_url)
            .send()
            .await
            .map_err(|e| e.to_string())?;
        
        let data = response.json::<serde_json::Value>()
            .await
            .map_err(|e| e.to_string())?;

        // 推送到远程服务器
        client.post(&settings.url)
            .json(&data)
            .send()
            .await
            .map_err(|e| e.to_string())?;

        // 更新最后推送时间
        let now = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap()
            .as_secs();
        let datetime = chrono::DateTime::from_timestamp(now as i64, 0)
            .map(|dt| dt.format("%Y-%m-%d %H:%M:%S").to_string())
            .unwrap_or_else(|| "Unknown".to_string());
        *self.last_push_time.lock().unwrap() = Some(datetime);

        Ok(())
    }
}
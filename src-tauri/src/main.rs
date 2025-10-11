// Prevents additional console window on Windows in release, DO NOT REMOVE!!
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use std::sync::{Arc, Mutex};
use std::net::TcpListener;
use axum::{
    extract::State,
    routing::get,
    Router, Json,
};
use serde::{Deserialize, Serialize};
use sysinfo::{System, Networks, Disks};
use tauri::{
    Manager, SystemTray, SystemTrayEvent, SystemTrayMenu, SystemTrayMenuItem, 
    CustomMenuItem, AppHandle, WindowEvent
};
use auto_launch::AutoLaunch;

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

#[derive(Clone)]
struct AppState {
    http_settings: Arc<Mutex<HttpSettings>>,
    share_settings: Arc<Mutex<ShareSettings>>,
    app_settings: Arc<Mutex<AppSettings>>,
    server_handle: Arc<Mutex<Option<tokio::task::JoinHandle<()>>>>,
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
            server_handle: Arc::new(Mutex::new(None)),
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

    Json(SystemInfo {
        computer_name,
        uptime,
        cpu_usage,
        memory_usage,
        processes,
        disks,
        network,
    })
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
    let app_state = AppState::new();

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
                    let _ = start_http_server_internal(state).await;
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
            set_http_port,
            set_share_settings,
            set_app_settings,
            set_auto_launch,
            start_http_server,
            stop_http_server,
            get_system_info_dashboard,
            get_processes,
            get_disks,
            get_network_info_dashboard,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}

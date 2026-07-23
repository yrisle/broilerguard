// src/api/types.ts

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
}

export interface LoginResponse {
  success: boolean;
  token: string;
  user: {
    username: string;
    role: string;
  };
}

export interface SensorData {
  temperature: number;
  humidity: number;
  feed_level: number;
  water_level: number;
  fan_status: "ON" | "OFF";
  water_pump: "ON" | "OFF";
  timestamp: number;
}

export interface DashboardStats {
  healthy_chicks: number;
  weak_chicks: number;
  unhealthy_chicks: number;
  total_chicks: number;
  active_alerts: number;
  temperature: number;
  humidity: number;
  feed_level: number;
  water_level: number;
  fan_status: "ON" | "OFF";
  fan_mode: string;
  water_pump: "ON" | "OFF";
  feed_dispenser: string;
  feed_consumed_today: number;
  water_consumed_today: number;
}

export interface Notification {
  id: string;
  title: string;
  message: string;
  type: "success" | "warning" | "danger" | "info";
  link?: string;
  timestamp: string;
  read: boolean;
}

export interface FeedInventory {
  current_level: number;
  capacity: number;
  unit: string;
  last_refill: string;
}

export interface WaterInventory {
  current_level: number;
  capacity: number;
  unit: string;
  last_refill: string;
}

export interface FanSettings {
  auto_mode: boolean;
  temp_on: number;
  temp_off: number;
  last_activation?: string;
  last_deactivation?: string;
  total_run_time: number;
  updated_at: string;
}

export interface FeedSchedule {
  id: number;
  time: string;
  amount: number;
  enabled: boolean;
  label: string;
}

export interface WaterSchedule {
  id: number;
  time: string;
  duration: number;
  enabled: boolean;
  label: string;
  amount?: number;
}

export interface ChickenStatus {
  chick_id: string;
  status: "healthy" | "weak" | "unhealthy";
  weight: string;
  age: string;
  last_detection: string;
}

export interface DetectionRecord {
  id: string;
  time: string;
  chick_id: string;
  status: "healthy" | "weak" | "unhealthy";
  confidence: number;
  weight: string;
  activity: string;
  duration: string;
}

export interface SystemSettings {
  timezone: string;
  date_format: string;
  temperature_unit: "celsius" | "fahrenheit";
  language: string;
  refresh_interval: number;
  enable_sound: boolean;
  enable_browser_notifications: boolean;
  alert_duration: number;
  session_timeout: number;
  two_factor_auth: boolean;
  login_attempts: number;
  auto_backup: boolean;
  backup_frequency: "daily" | "weekly";
  data_retention_days: number;
  theme: "light" | "dark";
  compact_view: boolean;
  show_charts: boolean;
}

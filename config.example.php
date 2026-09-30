<?php
// ============================================================
// config.php — アプリ全体の設定
// ============================================================

// エラー表示（本番は false に）
ini_set('display_errors', false);
error_reporting(E_ALL);

// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// セッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_name('RECEIPT_APP_SESSION');

// ============================================================
// 環境設定（本番環境では直接値を記入）
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ocr_db');
define('DB_USER', getenv('DB_USER') ?: 'ocr_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_password_here');

define('APP_URL', getenv('APP_URL') ?: 'https://example.com/ocr');
define('APP_SECRET', getenv('APP_SECRET') ?: 'your_secret_here');

// Google OAuth
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID')     ?: 'your_google_client_id_here');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'your_google_client_secret_here');
define('GOOGLE_REDIRECT_URI',  APP_URL . '/api/auth/callback.php');

// Google Service Account JSON（1行で貼り付け）
define('GOOGLE_SERVICE_ACCOUNT_JSON', getenv('GOOGLE_SERVICE_ACCOUNT_JSON') ?: '');
define('SHEET_ID',        getenv('SHEET_ID')        ?: 'your_google_sheet_id_here');
define('LOG_SHEET_ID',    getenv('LOG_SHEET_ID')    ?: 'your_log_sheet_id_here');
define('DRIVE_FOLDER_ID', getenv('DRIVE_FOLDER_ID') ?: 'your_drive_folder_id_here');

// Gemini API
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'your_gemini_api_key_here');

// Cron 認証キー
define('CRON_SECRET', getenv('CRON_SECRET') ?: 'your_cron_secret_here');

// 管理者通知先
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: '');

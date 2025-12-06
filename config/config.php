<?php
// config/config.php
// Database Configuration

// Environment Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'salon_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('BASE_URL', 'http://localhost/salon');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('LOGO_PATH', UPLOAD_PATH . 'logos/');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');

// Security
define('BCRYPT_COST', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(LOGO_PATH)) {
    mkdir(LOGO_PATH, 0755, true);
}
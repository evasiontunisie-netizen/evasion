<?php
// ============================================================
// ERP PRO - Main Configuration
// ============================================================

define('ERP_VERSION', '1.0.0');
define('ERP_NAME', 'ERP Pro');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/erp', '/'));

// Environment
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', APP_ENV === 'development');

// Database
define('DB_HOST',     $_ENV['DB_HOST']     ?? 'localhost');
define('DB_PORT',     $_ENV['DB_PORT']     ?? '3306');
define('DB_NAME',     $_ENV['DB_NAME']     ?? 'erp_pro');
define('DB_USER',     $_ENV['DB_USER']     ?? 'root');
define('DB_PASS',     $_ENV['DB_PASS']     ?? '');
define('DB_CHARSET',  'utf8mb4');

// JWT
define('JWT_SECRET',         $_ENV['JWT_SECRET']         ?? 'erp_pro_jwt_secret_change_in_production_2024');
define('JWT_EXPIRES',        (int)($_ENV['JWT_EXPIRES']  ?? 3600));       // 1 hour
define('JWT_REFRESH_EXPIRES',(int)($_ENV['JWT_REFRESH']  ?? 604800));    // 7 days

// Security
define('BCRYPT_COST',    12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900); // 15 min
define('RATE_LIMIT_WINDOW',  60);  // 1 min
define('RATE_LIMIT_MAX',     100);

// File Upload
define('UPLOAD_PATH',    BASE_PATH . '/storage/uploads');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['jpg','jpeg','png','gif','webp','pdf','xlsx','xls','csv']);

// Cache
define('CACHE_PATH', BASE_PATH . '/storage/cache');
define('CACHE_TTL',  300);

// Logs
define('LOG_PATH', BASE_PATH . '/storage/logs');

// Pagination
define('DEFAULT_PAGE_SIZE', 25);
define('MAX_PAGE_SIZE',     200);

// Default language
define('DEFAULT_LANG', 'fr');

// CORS allowed origins
define('CORS_ORIGINS', $_ENV['CORS_ORIGINS'] ?? '*');

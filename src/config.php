<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials from Environment Variables (GKE ConfigMaps & Secrets compatible)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_DATABASE') ?: 'bootcamp_db');
define('DB_USER', getenv('DB_USER') ?: 'bootcamp_user');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'bootcamp_pass');

$pdo = null;
$db_error = null;

// GKE Robust Connection Retry Logic (Tries 10 times with 2 seconds delay)
$max_retries = 10;
$retry_delay = 2; // seconds

for ($i = 1; $i <= $max_retries; $i++) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        // Connect to MySQL server first (without database to allow auto-creation if database doesn't exist)
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Auto-create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        
        // Check if database needs initialization (check if 'ogrenci' table exists)
        $tableExists = false;
        try {
            $result = $pdo->query("SELECT 1 FROM ogrenci LIMIT 1");
            $tableExists = true;
        } catch (Exception $e) {
            $tableExists = false;
        }
        
        if (!$tableExists) {
            // Include database initializer to create tables, procedures, functions, triggers, and seed data
            require_once __DIR__ . '/db_init.php';
            initialize_database($pdo);
        }
        
        $db_error = null;
        break; // Successfully connected and initialized, break retry loop!
    } catch (PDOException $e) {
        $db_error = "Veritabanı bağlantı denemesi #$i başarısız oldu: " . $e->getMessage();
        if ($i < $max_retries) {
            sleep($retry_delay);
        } else {
            $pdo = null;
        }
    }
}

// Global function to get safe PDO connection
function getDB() {
    global $pdo, $db_error;
    if ($pdo === null) {
        throw new Exception("Veritabanı bağlantısı kurulamadı. Hata: " . $db_error);
    }
    return $pdo;
}

// CSRF Security Functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Safe POST wrapper
function is_post_request() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function validate_post_csrf() {
    if (!is_post_request()) {
        return false;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die("CSRF Güvenlik İhlali Tespit Edildi! (Geçersiz veya Eksik Token)");
    }
    return true;
}

// Flash messages helpers
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success' or 'danger'
        'message' => $message
    ];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
        $icon = $flash['type'] === 'success' ? '✅' : '⚠️';
        echo "<div class='alert {$class}'>
                <span>{$icon}</span>
                <div>" . htmlspecialchars($flash['message']) . "</div>
              </div>";
    }
}

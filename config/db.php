<?php
date_default_timezone_set('Asia/Aden');
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
    ini_set('display_errors', 0);
    error_reporting(0);
}
// ============================================
// Database Configuration
// ============================================
// Support optional local override config (ignored by Git)
if (file_exists(__DIR__ . '/db.local.php')) {
    require_once __DIR__ . '/db.local.php';
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (
    strpos($host, 'shebahotel.com') === false && 
    strpos($host, 'hostingersite.com') === false
) || php_sapi_name() === 'cli' || empty($host);

if (!defined('DB_HOST')) {
    if ($isLocal) {
        // LOCAL (XAMPP Default)
        define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1'); // Use IP not 'localhost' to force TCP on Windows
        define('DB_USER', getenv('DB_USER') ?: 'root');
        define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
        define('DB_NAME', getenv('DB_NAME') ?: 'restaurant_pos');
    } elseif (strpos($host, 'hostingersite.com') !== false) {
        // CLOUD (Hostinger Staging)
        define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
        define('DB_USER', getenv('DB_USER') ?: 'u985319832_restaurant_pos');
        define('DB_PASS', getenv('DB_PASS') ?: '');
        define('DB_NAME', getenv('DB_NAME') ?: 'u985319832_restaurant_pos');
    } else {
        // CLOUD (Production)
        define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
        define('DB_USER', getenv('DB_USER') ?: 'shebahot_restaurant_pos');
        define('DB_PASS', getenv('DB_PASS') ?: '');
        define('DB_NAME', getenv('DB_NAME') ?: 'shebahot_restaurant_pos');
    }
}
define('DB_CHARSET', 'utf8mb4');

// App Settings
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$hostVal = !empty($host) ? $host : 'localhost';

// Calculate the relative URL base path of the application dynamically
$scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';

if (php_sapi_name() === 'cli' || empty($scriptName) || empty($scriptFilename)) {
    $base_path = '/';
} else {
    $scriptName = str_replace('\\', '/', $scriptName);
    $scriptFilename = str_replace('\\', '/', $scriptFilename);
    $dir_app = str_replace('\\', '/', rtrim(dirname(__DIR__), '/\\'));
    
    // Get the relative path of the executing script from the app root
    $relative_path = '';
    if (strpos($scriptFilename, $dir_app) === 0) {
        $relative_path = substr($scriptFilename, strlen($dir_app));
    }
    
    // Remove the relative suffix from SCRIPT_NAME to get the base URL path
    if (!empty($relative_path) && substr($scriptName, -strlen($relative_path)) === $relative_path) {
        $base_path = substr($scriptName, 0, -strlen($relative_path));
    } else {
        $base_path = dirname($scriptName);
    }
    
    $base_path = '/' . trim($base_path, '/') . '/';
    if ($base_path === '//') {
        $base_path = '/';
    }
}

$baseUrl = $protocol . $hostVal . rtrim($base_path, '/');

define('BASE_PATH', $base_path);
define('APP_URL', $baseUrl);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('SESSION_NAME', 'pos_session');

// Connect
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        // Force TCP socket (not named pipes) and short timeout
        ini_set('default_socket_timeout', 5);
        $dsn = "mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5, // Windows-compatible MySQL timeout
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Fix: Illegal mix of collations and SQL Strict Mode + Timezone Sync
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("SET SESSION sql_mode=''");
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h2 style="font-family:sans-serif;color:#e74c3c;text-align:center;margin-top:100px">'
              . '⚠️ تعذر الاتصال بقاعدة البيانات'
              . '<br><small style="font-size:0.6em;color:#888">'
              . htmlspecialchars($e->getMessage())
              . '<br>تأكد من تشغيل MySQL في XAMPP Control Panel'
              . '</small></h2>');
        }
    }
    return $pdo;
}

// Start session if not started
function startSession()
{
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 30 * 24 * 60 * 60;
        ini_set('session.gc_maxlifetime', $lifetime);
        session_set_cookie_params($lifetime, BASE_PATH);
        session_name(SESSION_NAME);
        session_start();
    }
}

// Get current logged-in user
function getCurrentUser(): ?array
{
    startSession();
    return $_SESSION['user'] ?? null;
}

// Require authentication
function requireAuth(array $allowedRoles = [])
{
    $user = getCurrentUser();
    if (!$user) {
        if (isApiRequest()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'غير مصرح. يرجى تسجيل الدخول.']);
            exit;
        }
        header('Location: ' . BASE_PATH . 'login.php');
        exit;
    }
    // If the user has custom permissions, they can bypass the base role restriction
    // because their specific page access will be handled by the layout.
    if (!empty($user['permissions'])) {
        return;
    }

    if (!empty($allowedRoles) && !in_array($user['role'], $allowedRoles)) {
        if (isApiRequest()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية للوصول.']);
            exit;
        }
        header('Location: ' . BASE_PATH . 'login.php?error=unauthorized');
        exit;
    }
}

function isApiRequest(): bool
{
    // Strict check: only for AJAX calls initiated by our JS
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

// JSON response helper
function jsonResponse(bool $success, $data = null, string $message = '', int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get app settings
function getSettings(): array
{
    static $settings = null;
    if ($settings === null) {
        $db = getDB();
        $rows = $db->query("SELECT `key`, `value` FROM settings")->fetchAll();
        $settings = array_column($rows, 'value', 'key');
    }
    return $settings;
}

// Format price
function formatPrice(float $amount): string
{
    $settings = getSettings();
    $currency = $settings['currency'] ?? 'ريال';
    $pos = $settings['currency_position'] ?? 'after';
    $formatted = number_format($amount, 2);
    return $pos === 'before' ? $currency . ' ' . $formatted : $formatted . ' ' . $currency;
}

// Generate order number
function generateOrderNumber(): string
{
    $db = getDB();
    $prefix = date('Y'); // e.g. 2026

    // Find the latest numeric order number starting with the current year
    $stmt = $db->prepare("SELECT order_number FROM orders WHERE order_number REGEXP ? ORDER BY id DESC LIMIT 1");
    // Regex matching exactly Year + Numbers (e.g. 202601, 2026100)
    $stmt->execute(['^' . $prefix . '[0-9]+$']);
    $lastOrder = $stmt->fetchColumn();

    if ($lastOrder) {
        $sequence = (int) substr($lastOrder, 4) + 1;
    } else {
        $sequence = 1;
    }

    return $prefix . str_pad($sequence, 2, '0', STR_PAD_LEFT);
}

// Push SSE event
function pushEvent(string $eventType, array $payload, ?string $targetRoles = null)
{
    $db = getDB();
    $db->prepare("INSERT INTO sse_events (event_type, payload, target_roles) VALUES (?, ?, ?)")->execute([$eventType, json_encode($payload, JSON_UNESCAPED_UNICODE), $targetRoles]);
}

// Get user-category permissions
function getUserCategoryPermissions(int $userId): array
{
    $db = getDB();
    $stmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id = ?");
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'category_id');
}

// Log system activity
function logActivity(string $action, string $details = '')
{
    try {
        $user = getCurrentUser();
        $userId = $user ? $user['id'] : null;
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
    } catch (Exception $e) {
        // Silent fail for logging to prevent blocking main actions
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

// Check if an order is in a closed shift based on the settings
function isOrderInClosedShift(string $createdAt): bool
{
    $settings = getSettings();
    $closingTime = $settings['shift_closing_time'] ?? '00:00';

    // Parse closing time parts
    $parts = explode(':', $closingTime);
    $closeHour = isset($parts[0]) ? (int)$parts[0] : 0;
    $closeMin = isset($parts[1]) ? (int)$parts[1] : 0;

    // Get current time details
    $now = new DateTime();
    $currentHour = (int)$now->format('H');
    $currentMin = (int)$now->format('i');

    // Determine the start time of the CURRENT active shift
    $currentShiftStart = clone $now;
    if ($currentHour < $closeHour || ($currentHour == $closeHour && $currentMin < $closeMin)) {
        // If we are before the closing time today, the current shift started yesterday at closingTime
        $currentShiftStart->modify('-1 day');
    }
    $currentShiftStart->setTime($closeHour, $closeMin, 0);

    // Convert order's created_at to DateTime
    $orderTime = new DateTime($createdAt);

    // If the order was created BEFORE the start of the current active shift, it belongs to a closed shift!
    return $orderTime < $currentShiftStart;
}

// Check if user has permission to bypass a closed shift lock
function canUserBypassShiftLock(array $user): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }
    $userPerms = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
    if (is_array($userPerms) && in_array('bypass_closed_shift', $userPerms)) {
        return true;
    }
    return false;
}

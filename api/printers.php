<?php
/**
 * api/printers.php
 * Handles CRUD operations for the printers table.
 * Includes test print endpoint and is_active support.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/print_direct_lib.php';
requireAuth(['admin']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getPrinters();
        break;
    case 'POST':
        if ($action === 'delete') deletePrinter();
        elseif ($action === 'test') testPrinter();
        elseif ($action === 'toggle_active') togglePrinterActive();
        else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id']) && $input['id'] > 0) updatePrinter();
            else createPrinter();
        }
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getPrinters() {
    $db = getDB();
    $all = $_GET['all'] ?? false;
    $sql = "SELECT * FROM printers" . (!$all ? " WHERE is_active=1" : "") . " ORDER BY name";
    $rows = $db->query($sql)->fetchAll();
    jsonResponse(true, $rows);
}

function createPrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $name         = trim($input['name'] ?? '');
    $ip           = trim($input['ip'] ?? '');
    $port         = (int)($input['port'] ?? 9100);
    $type         = $input['type'] ?? 'cashier';
    $windows_name = trim($input['windows_name'] ?? '');
    $is_active    = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (empty($name)) {
        jsonResponse(false, null, 'اسم الطابعة مطلوب', 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO printers (name, ip, port, type, windows_name, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $ip ?: '', $port, $type, $windows_name, $is_active]);
        $id = $db->lastInsertId();
        logActivity("إضافة طابعة", "تمت إضافة طابعة جديدة: $name");
        jsonResponse(true, ['id' => $id], 'تمت إضافة الطابعة بنجاح');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

function updatePrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id           = (int)($input['id'] ?? 0);
    $name         = trim($input['name'] ?? '');
    $ip           = trim($input['ip'] ?? '');
    $port         = (int)($input['port'] ?? 9100);
    $type         = $input['type'] ?? 'cashier';
    $windows_name = trim($input['windows_name'] ?? '');
    $is_active    = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (!$id || empty($name)) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }

    try {
        $stmt = $db->prepare("UPDATE printers SET name=?, ip=?, port=?, type=?, windows_name=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $ip, $port, $type, $windows_name, $is_active, $id]);
        logActivity("تحديث طابعة", "تعديل بيانات الطابعة: $name");
        jsonResponse(true, null, 'تم تحديث بيانات الطابعة');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

function deletePrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    try {
        $db->prepare("UPDATE departments SET printer_id=NULL WHERE printer_id=?")->execute([$id]);
        $stmt = $db->prepare("DELETE FROM printers WHERE id=?");
        $stmt->execute([$id]);
        logActivity("حذف طابعة", "تم حذف الطابعة (معرف: $id)");
        jsonResponse(true, null, 'تم حذف الطابعة بنجاح');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

function testPrinter() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف الطابعة مطلوب', 400);

    $stmt = $db->prepare("SELECT * FROM printers WHERE id=?");
    $stmt->execute([$id]);
    $printer = $stmt->fetch();
    if (!$printer) jsonResponse(false, null, 'الطابعة غير موجودة', 404);

    $testData = "\x1B\x40"; // INIT
    $testData .= "\x1B\x61\x01"; // Center
    $testData .= "\x1B\x45\x01"; // Bold on
    $testData .= "=== TEST PRINT ===\x0A";
    $testData .= "Printer: " . $printer['name'] . "\x0A";
    $testData .= "IP: " . ($printer['ip'] ?: 'N/A') . "\x0A";
    $testData .= "Port: " . ($printer['port'] ?: 9100) . "\x0A";
    $testData .= "Date: " . date('Y-m-d H:i:s') . "\x0A";
    $testData .= "==================\x0A";
    $testData .= "\x1B\x45\x00"; // Bold off
    $testData .= "\x0A\x0A\x0A";
    $testData .= "\x1D\x56\x00"; // Cut

    // Enqueue test print instead of executing directly
    $printerIp = ($printer['ip'] ?? '') . (!empty($printer['port']) ? ':' . $printer['port'] : '');
    $stmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, request_id, status, created_at) VALUES (0, 'test', ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$printerIp, base64_encode($testData), 'test-printer-' . $id . '-' . bin2hex(random_bytes(6))]);

    jsonResponse(true, ['queue_id' => (int)$db->lastInsertId(), 'printer' => $printer['name']], '✅ تمت إضافة اختبار الطباعة لقائمة الانتظار');
}

function togglePrinterActive() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $is_active = (int)($input['is_active'] ?? 1);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    $db->prepare("UPDATE printers SET is_active=? WHERE id=?")->execute([$is_active, $id]);
    jsonResponse(true, null, 'تم تحديث حالة الطابعة');
}

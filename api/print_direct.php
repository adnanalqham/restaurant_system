<?php
/**
 * api/print_direct.php — ESC/POS raw bytes via PowerShell P/Invoke
 *
 * action=receipt → print customer receipt to cashier printer
 * action=kitchen → print kitchen ticket to kitchen printer
 * action=all     → print BOTH simultaneously
 * action=test    → print test page to cashier printer
 * action=department → print split by department (new routing)
 *
 * When enable_department_printing is ON and action=all:
 *   → Uses department routing engine instead of single kitchen print
 *
 * Printer names are read from the `printers` table.
 * Fallback: settings['usb_printer_name']
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/print_direct_lib.php';
require_once __DIR__ . '/print_engine.php';

$_appToken     = $_GET['_t']                    ?? '';
$_apiKeyHeader = $_SERVER['HTTP_X_API_KEY']     ?? '';
$_userAgent    = $_SERVER['HTTP_USER_AGENT']    ?? '';
$_settings     = getSettings();
$_validKey     = $_settings['kitchen_api_key']  ?? '';

$useApiKey = false;

if ($_appToken === 'SHEBA_APP_2026') {
    $useApiKey = true;
}
if (!$useApiKey) {
    if ($_apiKeyHeader === 'SHEBA_APP_2026' ||
        (!empty($_validKey) && !empty($_apiKeyHeader) && hash_equals($_validKey, $_apiKeyHeader))) {
        $useApiKey = true;
    }
}
if (!$useApiKey) {
    if (stripos($_userAgent, 'ShebaApp') !== false ||
        stripos($_userAgent, 'okhttp')   !== false ||
        stripos($_userAgent, 'Dart')     !== false) {
        $useApiKey = true;
    }
}

if (!$useApiKey) {
    startSession();
    requireAuth(['admin', 'cashier', 'waiter', 'inventory_monitor', 'chef', 'juice_bar', 'kitchen']);
    $currentUser = getCurrentUser();
    session_write_close();
} else {
    $currentUser = ['id' => 0, 'role' => 'kitchen', 'name' => 'API Client'];
}

$db       = getDB();
$settings = getSettings();
$action   = $_GET['action'] ?? 'all';
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId  = (int)($input['order_id'] ?? $_GET['order_id'] ?? 0);
$restName = trim(preg_replace('/[^\x20-\x7E]/', '', $settings['restaurant_name'] ?? '')) ?: 'Restaurant';

$enableDeptPrinting = ($settings['enable_department_printing'] ?? '0') === '1';

$cashierPrinter = getPrinterName($db, 'cashier', trim($settings['usb_printer_name'] ?? ''));
$kitchenPrinter = getPrinterName($db, 'kitchen');

// ── Test mode (enqueue only — never print directly) ──────────────────────────
if ($action === 'test') {
    $testOrder = [
        'order_number' => 'TEST', 'table_number' => '5',
        'waiter_name'  => 'Test', 'created_at' => date('Y-m-d H:i:s'),
        'manual_discount' => 0, 'total' => 100, 'refund_amount' => 0,
        'payment_method' => 'cash', 'notes' => '',
        'cashier_name' => '', 'direct_name' => '',
    ];
    $testItems = [['name' => 'Test Item', 'name_en' => 'Test Item', 'qty' => 1, 'price' => 100, 'total' => 100, 'notes' => '']];
    $b = buildReceiptESC($restName, $testOrder, $testItems, 0, 100, 100);
    $stmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, request_id, status, created_at) VALUES (0, 'test', ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$cashierPrinter, base64_encode($b), 'test-' . bin2hex(random_bytes(8))]);
    jsonResponse(true, ['queue_id' => (int)$db->lastInsertId(), 'printer' => $cashierPrinter], '✅ تمت إضافة اختبار الطباعة لقائمة الانتظار');
}

// ── Validate ──────────────────────────────────────────────────────────────────
if (!$orderId) jsonResponse(false, null, 'order_id مطلوب', 400);

// ── Fetch order ───────────────────────────────────────────────────────────────
$oStmt = $db->prepare("
    SELECT o.id, o.order_number, o.total, o.manual_discount, o.refund_amount,
           o.payment_method, o.created_at, o.table_number, o.notes, o.direct_name,
           w.name AS waiter_name, c.name AS cashier_name
    FROM orders o
    LEFT JOIN users w ON o.waiter_id = w.id
    LEFT JOIN users c ON o.cashier_id = c.id
    WHERE o.id = ?
");
$oStmt->execute([$orderId]);
$order = $oStmt->fetch(PDO::FETCH_ASSOC);
if (!$order) jsonResponse(false, null, 'الطلب غير موجود', 404);

$order['waiter_name'] = trim(
    $currentUser['name_en'] ?? preg_replace('/[^\x20-\x7E]/', '', $currentUser['name'] ?? '')
) ?: ($order['waiter_name'] ?? '');

// ── Fetch items ───────────────────────────────────────────────────────────────
$iStmt = $db->prepare("
    SELECT oi.item_name_ar AS name, oi.item_name_en AS name_en,
           oi.quantity AS qty, oi.unit_price AS price,
           oi.subtotal AS total, oi.notes, oi.category_id,
           oi.is_printed,
           c.name_ar AS category_name_ar, c.name_en AS category_name_en,
           c.print_group_ar, c.print_group_en,
           c.department_id
    FROM order_items oi
    LEFT JOIN categories c ON oi.category_id = c.id
    WHERE oi.order_id = ? AND oi.status != 'rejected'
    ORDER BY oi.id ASC
");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Totals ────────────────────────────────────────────────────────────────────
$discount = (float)($order['manual_discount'] ?? 0);
$subtotal = (float)$order['total'] + $discount;
$netTotal = (float)$order['total'] - (float)($order['refund_amount'] ?? 0);

$splitSetting = ($_settings['waiter_receipt_split'] ?? $settings['waiter_receipt_split'] ?? '0') === '1';
$isNetworkCashier = ($currentUser['print_type'] ?? '') === 'network';
$splitWaiterCopy = $splitSetting && $isNetworkCashier;

// ── Dispatch ──────────────────────────────────────────────────────────────────
switch ($action) {

    case 'receipt':
        $bytes  = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal, $splitWaiterCopy);
        // Enqueue receipt for queue_worker (never print directly)
        $stmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, request_id, status, created_at) VALUES (?, 'cashier', ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$orderId, $cashierPrinter, base64_encode($bytes), 'receipt-' . $orderId . '-' . bin2hex(random_bytes(6))]);
        try {
            $db->prepare("UPDATE orders SET print_count = print_count + 1 WHERE id = ?")->execute([$orderId]);
            $db->prepare("UPDATE order_items SET is_printed = 1 WHERE order_id = ? AND is_printed = 0")->execute([$orderId]);
        } catch (Exception $e) {}
        jsonResponse(true, ['queue_id' => (int)$db->lastInsertId()], '✅ تمت إضافة الفاتورة لقائمة الطباعة');
        break;

    case 'kitchen':
        if ($enableDeptPrinting) {
            // Enqueue department-split jobs
            $queued = enqueueOrderByDepartment($db, $orderId, 'direct');
            jsonResponse(true, ['queued' => $queued], '✅ تمت إضافة ' . count($queued) . ' مهمة طباعة');
        } else {
            // Enqueue single kitchen ticket
            $bytes  = buildKitchenESC($restName, $order, $items);
            $stmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, request_id, status, created_at) VALUES (?, 'kitchen', ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$orderId, $kitchenPrinter, base64_encode($bytes), 'kitchen-' . $orderId . '-' . bin2hex(random_bytes(6))]);
            jsonResponse(true, ['queue_id' => (int)$db->lastInsertId()], '✅ تمت إضافة تذكرة المطبخ لقائمة الطباعة');
        }
        break;

    case 'department':
        // Enqueue department-split jobs (no direct execution)
        $queued = enqueueOrderByDepartment($db, $orderId, 'direct');
        jsonResponse(true, ['queued' => $queued], '✅ تمت إضافة ' . count($queued) . ' مهمة طباعة');
        break;

    case 'get_esc':
        $stationUserId = (int)($_GET['station_user_id'] ?? 0);
        $type = 'kitchen';
        if ($stationUserId > 0) {
            try {
                $sStmt = $db->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                $sStmt->execute([$stationUserId]);
                $role = $sStmt->fetchColumn();
                if ($role === 'juice_bar') $type = 'bar';
            } catch (Exception $e) {}
        } else {
            $type = $_GET['type'] ?? 'kitchen';
        }

        $printerName = getPrinterName($db, $type);
        if (empty($printerName)) {
            $printerName = ($type === 'cashier' || $type === 'receipt')
                ? trim($settings['usb_printer_name'] ?? '')
                : 'MNK on 10.0.0.191';
        }

        if ($type === 'receipt' || $type === 'cashier') {
            $bytes = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal, $splitWaiterCopy);
        } else {
            $bytes = buildKitchenESC($restName, $order, $items);
        }

        jsonResponse(true, [
            'printer_name' => $printerName,
            'esc_pos_base64' => base64_encode($bytes)
        ]);
        break;

    case 'all':
    default:
        // Enqueue receipt
        $receiptBytes = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal, $splitWaiterCopy);
        $rStmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, request_id, status, created_at) VALUES (?, 'cashier', ?, ?, ?, 'pending', NOW())");
        $rStmt->execute([$orderId, $cashierPrinter, base64_encode($receiptBytes), 'receipt-' . $orderId . '-' . bin2hex(random_bytes(6))]);

        if ($enableDeptPrinting) {
            // Enqueue department-split jobs
            $deptQueued = enqueueOrderByDepartment($db, $orderId, 'direct');
            $msg = '✅ تمت إضافة الفاتورة + ' . count($deptQueued) . ' مهمة قسم';
            jsonResponse(true, ['receipt_queue_id' => (int)$db->lastInsertId(), 'departments' => $deptQueued], $msg);
        } else {
            // Enqueue single kitchen ticket
            $kitchenBytes = buildKitchenESC($restName, $order, $items);
            $kStmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, request_id, status, created_at) VALUES (?, 'kitchen', ?, ?, ?, 'pending', NOW())");
            $kStmt->execute([$orderId, $kitchenPrinter, base64_encode($kitchenBytes), 'kitchen-' . $orderId . '-' . bin2hex(random_bytes(6))]);
            $msg = '✅ تمت إضافة الفاتورة + تذكرة المطبخ لقائمة الطباعة';
            jsonResponse(true, ['receipt_queue_id' => (int)$db->lastInsertId(), 'kitchen_queue_id' => (int)$db->lastInsertId()], $msg);
        }
        break;
}

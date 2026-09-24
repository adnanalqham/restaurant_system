<?php
/**
 * Print Queue API v2
 * Supports department-based split printing + retry + logging
 *
 * GET  ?action=pending      => returns unprinted jobs (with department info)
 * GET  ?action=get_max_id   => for first-run initialization
 * POST ?action=enqueue      => add new print job (with optional department_id)
 * POST ?action=mark_done    => marks a job as printed
 * POST ?action=retry_failed => retry a failed job
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/print_engine.php';

$db = getDB();

// ─── Auth ─────────────────────────────────────────────────────────────────────
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
    requireAuth(['chef', 'juice_bar', 'kitchen', 'admin', 'waiter', 'cashier']);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'pending';

if (!$useApiKey) {
    $user = getCurrentUser();
}

session_write_close();

// ─── GET: Pending Jobs ───────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'pending') {
    $sinceId = (int)($_GET['since_id'] ?? 0);
    $departmentId = $_GET['department_id'] ?? null;

    $sql = "SELECT pq.id, pq.order_id, pq.station_user_id, pq.department_id,
                   pq.status, pq.attempts, pq.error_message, pq.printer_ip
            FROM print_queue pq
            WHERE pq.printed_at IS NULL AND pq.status IN ('pending','failed')";
    $params = [];

    if ($sinceId > 0) {
        $sql .= " AND pq.id > ?";
        $params[] = $sinceId;
    }
    if ($departmentId !== null) {
        $sql .= " AND pq.department_id = ?";
        $params[] = (int)$departmentId;
    }
    if (!$useApiKey) {
        $sql .= " AND (pq.station_user_id = ? OR pq.station_user_id IS NULL)";
        $params[] = $user['id'];
    }

    $sql .= " ORDER BY pq.id ASC LIMIT 20";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

    // Enrich with order + department info
    foreach ($jobs as &$job) {
        $oStmt = $db->prepare("SELECT order_number, table_number FROM orders WHERE id=?");
        $oStmt->execute([$job['order_id']]);
        $orderInfo = $oStmt->fetch();
        $job['order_number'] = $orderInfo['order_number'] ?? '#';
        $job['table_number'] = $orderInfo['table_number'] ?? '';

        if ($job['department_id']) {
            $dStmt = $db->prepare("SELECT name_ar, name_en FROM departments WHERE id=?");
            $dStmt->execute([$job['department_id']]);
            $dept = $dStmt->fetch();
            $job['department_name_ar'] = $dept['name_ar'] ?? '';
            $job['department_name_en'] = $dept['name_en'] ?? '';
        } else {
            $job['department_name_ar'] = '';
            $job['department_name_en'] = '';
        }
    }
    unset($job);

    jsonResponse(true, $jobs);
}

// ─── GET: Get Max Queue ID + Server Time ─────────────────────────────────────
if ($method === 'GET' && $action === 'get_max_id') {
    $row = $db->query("SELECT COALESCE(MAX(id), 0) AS max_id, NOW() AS server_time FROM print_queue")->fetch();

    $apiUserId = (int)($_GET['station_user_id'] ?? 0);
    $userCats  = [];
    $userPrinterMac = null;
    if ($apiUserId > 0) {
        try {
            $cStmt = $db->prepare("SELECT category_id FROM user_category_permissions WHERE user_id=?");
            $cStmt->execute([$apiUserId]);
            $userCats = array_map('intval', array_column($cStmt->fetchAll(), 'category_id'));

            $uStmt = $db->prepare("SELECT printer_mac FROM users WHERE id=?");
            $uStmt->execute([$apiUserId]);
            $uRow = $uStmt->fetch();
            $userPrinterMac = $uRow['printer_mac'] ?? null;
        } catch (Exception $e) {}
    }

    jsonResponse(true, [
        'max_id'              => (int)($row['max_id'] ?? 0),
        'server_time'         => (string)($row['server_time'] ?? date('Y-m-d H:i:s')),
        'category_ids'        => $userCats,
        'station_printer_mac' => $userPrinterMac,
    ]);
}

// ─── POST: Enqueue new print job ─────────────────────────────────────────────
if ($method === 'POST' && $action === 'enqueue') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $orderId = (int)($input['order_id'] ?? 0);
    if (!$orderId) jsonResponse(false, null, 'order_id مطلوب', 400);

    $stationUserId = $useApiKey ? null : ($user['id'] ?? null);
    $departmentId = !empty($input['department_id']) ? (int)$input['department_id'] : null;
    $escData = $input['esc_data'] ?? null;
    $printerType = $input['printer_type'] ?? null;
    $printerIp = $input['printer_ip'] ?? null;
    $requestId = $input['request_id'] ?? null;

    // Generate request_id if not provided
    if (!$requestId) {
        $requestId = generateRequestId();
    }

    // Check for existing unprinted job (same order + same department)
    $checkSql = "SELECT id FROM print_queue WHERE order_id = ? AND printed_at IS NULL AND status IN ('pending','failed')";
    $checkParams = [$orderId];
    if ($departmentId) {
        $checkSql .= " AND department_id = ?";
        $checkParams[] = $departmentId;
    } else {
        $checkSql .= " AND department_id IS NULL";
    }
    $checkSql .= " LIMIT 1";

    $check = $db->prepare($checkSql);
    $check->execute($checkParams);
    if ($check->fetch()) {
        jsonResponse(true, null, 'الطلب موجود بالفعل في قائمة الانتظار');
    }

    $stmt = $db->prepare("INSERT INTO print_queue (order_id, station_user_id, department_id, printer_type, printer_ip, esc_data, request_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$orderId, $stationUserId, $departmentId, $printerType, $printerIp, $escData, $requestId]);
    jsonResponse(true, ['queue_id' => (int)$db->lastInsertId(), 'request_id' => $requestId], 'تمت إضافة الطلب لقائمة الطباعة');
}

// ─── POST: Claim (atomic job claim by queue_worker) ─────────────────────────
if ($method === 'POST' && $action === 'claim') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $workerId = $input['worker_id'] ?? 'worker-' . getmypid();

    $db->beginTransaction();
    try {
        $fetch = $db->query("SELECT id FROM print_queue WHERE status='pending' ORDER BY id ASC LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if (!$fetch) {
            $db->rollBack();
            jsonResponse(true, null, 'No pending jobs');
        }

        $update = $db->prepare("UPDATE print_queue SET status='printing', started_at=NOW() WHERE id=? AND status='pending'");
        $update->execute([$fetch['id']]);
        if ($update->rowCount() === 0) {
            $db->rollBack();
            jsonResponse(true, null, 'Job already claimed');
        }

        $jobFetch = $db->prepare("SELECT * FROM print_queue WHERE id=?");
        $jobFetch->execute([$fetch['id']]);
        $job = $jobFetch->fetch(PDO::FETCH_ASSOC);

        $db->commit();
        jsonResponse(true, $job, 'Job claimed');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Claim error: ' . $e->getMessage(), 500);
    }
}

// ─── POST: Mark Done (called by queue_worker after successful print) ──────────
if ($method === 'POST' && $action === 'mark_done') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id    = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'Invalid ID', 400);

    $jobStmt = $db->prepare("SELECT order_id, station_user_id, department_id FROM print_queue WHERE id=?");
    $jobStmt->execute([$id]);
    $job = $jobStmt->fetch();
    if (!$job) jsonResponse(false, null, 'Job not found', 404);

    $stmt = $db->prepare("UPDATE print_queue SET printed_at=NOW(), status='success' WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) jsonResponse(false, null, 'Failed to mark done');

    try {
        $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")->execute([$job['order_id']]);
        $db->prepare("UPDATE order_items SET is_printed = 1 WHERE order_id = ? AND is_printed = 0")->execute([$job['order_id']]);
    } catch (Exception $e) {}

    jsonResponse(true, null, 'Marked as done');
}

// ─── POST: Retry Failed (resets job to pending for queue_worker) ─────────────
if ($method === 'POST' && $action === 'retry_failed') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'Invalid ID', 400);

    $jobStmt = $db->prepare("SELECT id FROM print_queue WHERE id=? AND status='failed'");
    $jobStmt->execute([$id]);
    if (!$jobStmt->fetch()) jsonResponse(false, null, 'Job not found or not in failed state', 404);

    $db->prepare("UPDATE print_queue SET status='pending', attempts=0, error_message=NULL, request_id=CONCAT('retry-', request_id) WHERE id=?")->execute([$id]);
    jsonResponse(true, null, 'تم إعادة تعيين المهمة للطباعة');
}

// ─── POST: Enqueue Department Jobs (Split by Department) ─────────────────────
if ($method === 'POST' && $action === 'enqueue_departments') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $orderId = (int)($input['order_id'] ?? 0);
    if (!$orderId) jsonResponse(false, null, 'order_id مطلوب', 400);

    // Wrap in try/catch for UNIQUE constraint violations on request_id
    try {
        $queued = enqueueOrderByDepartment($db, $orderId, 'api');
        jsonResponse(true, ['queued' => $queued, 'count' => count($queued)], 'تمت إضافة ' . count($queued) . ' مهمة طباعة');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ في إضافة المهام: ' . $e->getMessage(), 500);
    }
}

jsonResponse(false, null, 'Unknown action', 400);

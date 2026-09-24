<?php
/**
 * api/print_engine.php — Core Department Routing Engine
 *
 * Product → Category → Department → Printer
 *
 * Replaces old print_group logic with professional department routing.
 * Called by print_direct.php, orders.php, and queue_worker.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/print_direct_lib.php';

/**
 * Get the printer assigned to a department
 */
function getDepartmentPrinter(PDO $db, int $departmentId): ?array {
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.ip, p.port, p.type, p.windows_name
        FROM departments d
        JOIN printers p ON d.printer_id = p.id
        WHERE d.id = ? AND d.is_active = 1 AND p.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$departmentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Group order items by department
 * Returns: [ ['department' => [...], 'printer' => [...], 'items' => [...]] ]
 */
function groupItemsByDepartment(PDO $db, int $orderId): array {
    $settings = getSettings();
    $enableDeptPrinting = ($settings['enable_department_printing'] ?? '0') === '1';

    if (!$enableDeptPrinting) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT oi.id, oi.item_name_ar AS name, oi.item_name_en AS name_en,
               oi.quantity AS qty, oi.unit_price AS price,
               oi.subtotal AS total, oi.notes, oi.is_printed,
               oi.category_id,
               c.department_id,
               c.name_ar AS cat_name_ar, c.name_en AS cat_name_en
        FROM order_items oi
        LEFT JOIN categories c ON oi.category_id = c.id
        WHERE oi.order_id = ? AND oi.status != 'rejected'
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($items as $item) {
        $deptId = (int)($item['department_id'] ?? 0);
        $deptKey = $deptId > 0 ? $deptId : 0;

        if (!isset($groups[$deptKey])) {
            if ($deptId > 0) {
                $deptStmt = $db->prepare("SELECT id, name_ar, name_en FROM departments WHERE id = ? AND is_active = 1");
                $deptStmt->execute([$deptId]);
                $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
                $printer = getDepartmentPrinter($db, $deptId);

                $groups[$deptKey] = [
                    'department_id' => $deptId,
                    'department_name_ar' => $dept['name_ar'] ?? 'غير محدد',
                    'department_name_en' => $dept['name_en'] ?? 'Unassigned',
                    'printer' => $printer,
                    'items' => [],
                ];
            } else {
                $groups[$deptKey] = [
                    'department_id' => 0,
                    'department_name_ar' => 'غير محدد',
                    'department_name_en' => 'Unassigned',
                    'printer' => null,
                    'items' => [],
                ];
            }
        }
        $groups[$deptKey]['items'][] = $item;
    }

    return array_values($groups);
}

/**
 * Send ESC/POS to network printer via fsockopen (IP:Port)
 * Returns ['ok' => bool, 'msg' => string]
 */
function sendToNetworkPrinter(string $ip, int $port, string $data, int $timeout = 5): array {
    try {
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return ['ok' => false, 'msg' => "فشل الاتصال: $errstr ($errno)"];
        }
        stream_set_timeout($socket, $timeout);
        fwrite($socket, $data);
        fclose($socket);
        return ['ok' => true, 'msg' => '✅ تمت الطباعة عبر الشبكة'];
    } catch (Exception $e) {
        return ['ok' => false, 'msg' => 'خطأ: ' . $e->getMessage()];
    }
}

/**
 * Print a department group to its assigned printer.
 * Falls back to Windows printer name if IP is not set.
 */
function printDepartmentGroup(PDO $db, array $group, array $order, string $restName): array {
    $printer = $group['printer'];
    $items = $group['items'];

    if (empty($items)) {
        return ['ok' => true, 'msg' => 'لا توجد أصناف'];
    }

    // Build ESC/POS ticket for this department
    $bytes = buildDepartmentTicketESC($restName, $order, $items, $group['department_name_en']);

    if ($printer && !empty($printer['ip'])) {
        // Network printer via IP:Port
        $result = sendToNetworkPrinter($printer['ip'], (int)($printer['port'] ?: 9100), $bytes);
    } elseif ($printer && !empty($printer['windows_name'])) {
        // Windows shared printer
        $result = rawPrint($printer['windows_name'], $bytes);
    } else {
        // Fallback: try windows_name from printers table
        $windowsName = getPrinterName($db, $printer['type'] ?? 'kitchen');
        if ($windowsName) {
            $result = rawPrint($windowsName, $bytes);
        } else {
            $result = ['ok' => false, 'msg' => 'لم يتم تحديد طابعة لهذا القسم'];
        }
    }

    return $result;
}

/**
 * Build a department-specific ESC/POS ticket
 */
if (!function_exists('buildDepartmentTicketESC')) {
    function buildDepartmentTicketESC(string $restName, array $o, array $items, string $deptName = ''): string {
        $LF       = "\x0A";
        $CENTER   = "\x1B\x61\x01";
        $LEFT     = "\x1B\x61\x00";
        $BOLD_ON  = "\x1B\x45\x01";
        $BOLD_OFF = "\x1B\x45\x00";
        $BIG_ON   = "\x1B\x21\x30";
        $BIG_OFF  = "\x1B\x21\x00";
        $CUT      = "\x1D\x56\x00";
        $W        = 32;

        $b = "\x1B\x40"; // INIT

        // Header
        $b .= $CENTER . $BOLD_ON;
        $b .= str_pad($restName, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BOLD_OFF;

        if ($deptName) {
            $b .= $CENTER . $BOLD_ON;
            $b .= str_pad('== ' . strtoupper($deptName) . ' ==', $W, ' ', STR_PAD_BOTH) . $LF;
            $b .= $BOLD_OFF;
        }

        $b .= str_repeat('=', $W) . $LF;

        // Table + Order
        $b .= $CENTER . $BIG_ON . $BOLD_ON;
        $table = 'TABLE: ' . ($o['table_number'] ?: 'TKW');
        $b .= str_pad($table, $W, ' ', STR_PAD_BOTH) . $LF;
        $b .= $BIG_OFF . $BOLD_OFF . $LEFT;

        $pad = max(1, $W - strlen('Order: #' . $o['order_number']) - strlen('Time: ' . date('H:i', strtotime($o['created_at']))));
        $b .= 'Order: #' . $o['order_number'] . str_repeat(' ', $pad) . 'Time: ' . date('H:i', strtotime($o['created_at'])) . $LF;

        if (!empty($o['waiter_name'])) {
            $wn = trim(preg_replace('/[^\x20-\x7E]/', '', $o['waiter_name']));
            if ($wn) $b .= 'Waiter: ' . $wn . $LF;
        }
        if (!empty($o['notes'])) {
            $n = trim(preg_replace('/[^\x20-\x7E]/', '', $o['notes']));
            if ($n) $b .= 'NOTE: ' . substr($n, 0, $W - 6) . $LF;
        }

        $b .= str_repeat('=', $W) . $LF;

        // Items
        $oldItems = [];
        $newItems = [];
        foreach ($items as $item) {
            if (!empty($item['is_printed'])) $oldItems[] = $item;
            else $newItems[] = $item;
        }

        if (!empty($oldItems) && !empty($newItems)) {
            foreach ($oldItems as $item) {
                $name = trim($item['name_en'] ?? '') ?: trim(preg_replace('/[^\x20-\x7E]/', '', $item['name'] ?? '')) ?: 'Item';
                $qty = (int)$item['qty'];
                $line = '-- x' . $qty . ' ' . $name . ' --';
                if (strlen($line) > $W) $line = substr($line, 0, $W);
                $b .= $line . $LF;
                $b .= str_repeat('-', $W) . $LF;
            }
            $b .= $CENTER . $BIG_ON . $BOLD_ON;
            $b .= str_pad('** NEW ITEMS **', $W, ' ', STR_PAD_BOTH) . $LF;
            $b .= $BIG_OFF . $BOLD_OFF . $LEFT;
            $b .= str_repeat('=', $W) . $LF;
        }

        $itemsToPrint = !empty($newItems) ? $newItems : $items;
        foreach ($itemsToPrint as $item) {
            $name = trim($item['name_en'] ?? '') ?: trim(preg_replace('/[^\x20-\x7E]/', '', $item['name'] ?? '')) ?: 'Item';
            $qty = (int)$item['qty'];

            $b .= $BIG_ON . $BOLD_ON;
            $b .= 'x' . $qty . $LF;
            $b .= $BIG_OFF . $BOLD_ON;

            if (strlen($name) > $W) $name = substr($name, 0, $W - 1) . '.';
            $b .= $name . $LF . $BOLD_OFF;

            if (!empty($item['notes'])) {
                $nt = trim(preg_replace('/[^\x20-\x7E]/', '', $item['notes'] ?? ''));
                if ($nt) $b .= '  !! ' . substr($nt, 0, $W - 6) . $LF;
            }
            $b .= str_repeat('-', $W) . $LF;
        }

        $b .= str_repeat('=', $W) . $LF;
        $b .= $CENTER . '** ' . strtoupper($deptName ?: 'KITCHEN') . ' **' . $LF;
        $b .= $LF . $LF . $LF;
        $b .= $CUT;

        return $b;
    }
}

/**
 * Generate a unique request ID for idempotency
 */
function generateRequestId(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Enqueue department print jobs (routing only, no execution).
 * Inserts one queue entry per department group with a unique request_id.
 * Returns array of queue IDs keyed by department key.
 */
function enqueueOrderByDepartment(PDO $db, int $orderId, ?string $prefix = null): array {
    $settings = getSettings();
    $restName = trim(preg_replace('/[^\x20-\x7E]/', '', $settings['restaurant_name'] ?? '')) ?: 'Restaurant';

    $oStmt = $db->prepare("
        SELECT o.id, o.order_number, o.table_number, o.created_at, o.notes,
               w.name AS waiter_name, c.name AS cashier_name
        FROM orders o
        LEFT JOIN users w ON o.waiter_id = w.id
        LEFT JOIN users c ON o.cashier_id = c.id
        WHERE o.id = ?
    ");
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return [];

    $groups = groupItemsByDepartment($db, $orderId);
    $queued = [];

    foreach ($groups as $i => $group) {
        $items = $group['items'];
        if (empty($items)) continue;

        $printer = $group['printer'];
        $bytes = buildDepartmentTicketESC($restName, $order, $items, $group['department_name_en']);

        $departmentKey = $group['department_id'] > 0 ? $group['department_id'] : 'unassigned';
        $requestId = ($prefix ? $prefix . '-' : '') . ($group['department_id'] > 0 ? $group['department_id'] : '0') . '-' . bin2hex(random_bytes(8));

        $stmt = $db->prepare("
            INSERT INTO print_queue (order_id, printer_type, printer_ip, esc_data, status, request_id, created_at)
            VALUES (?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([
            $orderId,
            $printer['type'] ?? 'department',
            $printer['ip'] ?? '',
            base64_encode($bytes),
            $requestId,
        ]);
        $queued[$departmentKey] = [
            'queue_id' => (int)$db->lastInsertId(),
            'request_id' => $requestId,
            'department' => $group['department_name_ar'],
        ];
    }

    return $queued;
}

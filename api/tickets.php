<?php
/**
 * API: Tickets (ticket_types & ticket_sales)
 * Endpoints:
 *   GET  ?action=get_types          - list all ticket types
 *   POST ?action=add_type           - add ticket type
 *   POST ?action=edit_type          - edit ticket type
 *   POST ?action=delete_type        - delete ticket type (only if no sales)
 *   GET  ?action=get_sales          - list ticket sales (with filters)
 *   POST ?action=sell               - sell a ticket
 *   GET  ?action=stats              - summary stats for a ticket type
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_types';

// ── Helper ────────────────────────────────────────────────────────────────────
function jsonOut(bool $ok, $data = null, string $msg = ''): void
{
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function postJson(): array
{
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── GET: ticket types list ────────────────────────────────────────────────────
if ($action === 'get_types') {
    $stmt = $db->query("
        SELECT 
            t.*,
            (t.serial_to - t.serial_from + 1) AS total_count,
            COALESCE(s.sold_count, 0) AS sold_count,
            (t.serial_to - t.serial_from + 1 - COALESCE(s.sold_count, 0)) AS remaining_count
        FROM ticket_types t
        LEFT JOIN (
            SELECT ticket_type_id, COUNT(*) AS sold_count
            FROM ticket_sales
            GROUP BY ticket_type_id
        ) s ON s.ticket_type_id = t.id
        ORDER BY t.id DESC
    ");
    jsonOut(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── POST: add ticket type ─────────────────────────────────────────────────────
if ($action === 'add_type') {
    $body = postJson();
    $name = trim($body['name'] ?? '');
    $price = floatval($body['price'] ?? 0);
    $from = intval($body['serial_from'] ?? 0);
    $to = intval($body['serial_to'] ?? 0);

    if (!$name) jsonOut(false, null, 'اسم التذكرة مطلوب');
    if ($price <= 0) jsonOut(false, null, 'السعر يجب أن يكون أكبر من صفر');
    if ($from <= 0 || $to <= 0) jsonOut(false, null, 'أرقام البداية والنهاية مطلوبة');
    if ($from > $to) jsonOut(false, null, 'رقم البداية يجب أن يكون أصغر من رقم النهاية');

    $stmt = $db->prepare("INSERT INTO ticket_types (name, price, serial_from, serial_to, created_by) VALUES (?,?,?,?,?)");
    $stmt->execute([$name, $price, $from, $to, $user['id']]);
    jsonOut(true, ['id' => $db->lastInsertId()], 'تمت إضافة نوع التذكرة بنجاح');
}

// ── POST: edit ticket type ────────────────────────────────────────────────────
if ($action === 'edit_type') {
    $body = postJson();
    $id = intval($body['id'] ?? 0);
    $name = trim($body['name'] ?? '');
    $price = floatval($body['price'] ?? 0);
    $from = intval($body['serial_from'] ?? 0);
    $to = intval($body['serial_to'] ?? 0);
    $is_active = intval($body['is_active'] ?? 1);

    if (!$id) jsonOut(false, null, 'معرّف غير صحيح');
    if (!$name) jsonOut(false, null, 'اسم التذكرة مطلوب');
    if ($price <= 0) jsonOut(false, null, 'السعر يجب أن يكون أكبر من صفر');
    if ($from > $to) jsonOut(false, null, 'رقم البداية يجب أن يكون أصغر من رقم النهاية');

    $stmt = $db->prepare("UPDATE ticket_types SET name=?, price=?, serial_from=?, serial_to=?, is_active=? WHERE id=?");
    $stmt->execute([$name, $price, $from, $to, $is_active, $id]);
    jsonOut(true, null, 'تم تحديث نوع التذكرة بنجاح');
}

// ── POST: delete ticket type ──────────────────────────────────────────────────
if ($action === 'delete_type') {
    $body = postJson();
    $id = intval($body['id'] ?? 0);
    if (!$id) jsonOut(false, null, 'معرّف غير صحيح');

    // Check if sales exist
    $cnt = $db->prepare("SELECT COUNT(*) FROM ticket_sales WHERE ticket_type_id = ?");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
        jsonOut(false, null, 'لا يمكن حذف نوع تذكرة لديها مبيعات مسجلة. يمكنك تعطيلها فقط.');
    }

    $db->prepare("DELETE FROM ticket_types WHERE id = ?")->execute([$id]);
    jsonOut(true, null, 'تم حذف نوع التذكرة بنجاح');
}

// ── GET: ticket sales list ────────────────────────────────────────────────────
if ($action === 'get_sales') {
    $typeId = intval($_GET['ticket_type_id'] ?? 0);
    $from = $_GET['from_date'] ?? '';
    $to = $_GET['to_date'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($typeId) {
        $where[] = 'ts.ticket_type_id = ?';
        $params[] = $typeId;
    }
    if ($from) {
        $where[] = 'DATE(ts.sold_at) >= ?';
        $params[] = $from;
    }
    if ($to) {
        $where[] = 'DATE(ts.sold_at) <= ?';
        $params[] = $to;
    }

    $whereStr = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT 
            ts.*,
            tt.name AS ticket_name,
            tt.price AS ticket_price
        FROM ticket_sales ts
        JOIN ticket_types tt ON tt.id = ts.ticket_type_id
        WHERE $whereStr
        ORDER BY ts.sold_at DESC
        LIMIT 1000
    ");
    $stmt->execute($params);
    jsonOut(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── POST: sell ticket ─────────────────────────────────────────────────────────
if ($action === 'sell') {
    $body = postJson();
    $typeId = intval($body['ticket_type_id'] ?? 0);
    $quantity = intval($body['quantity'] ?? 1);
    $notes = trim($body['notes'] ?? '');

    $phone = trim($body['phone'] ?? '');
    if (!$typeId) jsonOut(false, null, 'يرجى تحديد نوع التذكرة');
    if ($quantity < 1) jsonOut(false, null, 'الكمية يجب أن تكون على الأقل 1');

    // Get ticket type
    $typeStmt = $db->prepare("SELECT * FROM ticket_types WHERE id = ? AND is_active = 1");
    $typeStmt->execute([$typeId]);
    $type = $typeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$type) jsonOut(false, null, 'نوع التذكرة غير موجود أو معطّل');

    // Get already sold serials for this type
    $soldStmt = $db->prepare("SELECT serial_number FROM ticket_sales WHERE ticket_type_id = ? ORDER BY serial_number ASC");
    $soldStmt->execute([$typeId]);
    $soldSerials = array_column($soldStmt->fetchAll(PDO::FETCH_ASSOC), 'serial_number');
    $soldSet = array_flip($soldSerials);

    // Find next available serials
    $available = [];
    for ($i = $type['serial_from']; $i <= $type['serial_to'] && count($available) < $quantity; $i++) {
        if (!isset($soldSet[$i])) {
            $available[] = $i;
        }
    }

    if (count($available) < $quantity) {
        $remaining = count($available);
        jsonOut(false, null, "لا يوجد تذاكر كافية. المتبقي: $remaining تذكرة فقط");
    }

    // Insert sales
    $insertStmt = $db->prepare("
        INSERT INTO ticket_sales (ticket_type_id, serial_number, sale_price, sold_by, cashier_name, phone, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $db->beginTransaction();
    try {
        foreach ($available as $serial) {
            $insertStmt->execute([
                $typeId,
                $serial,
                $type['price'],
                $user['id'],
                $user['name'],
                $phone ?: null,
                $notes ?: null
            ]);
        }
        $db->commit();
        jsonOut(true, [
            'sold_serials' => $available,
            'total_amount' => $type['price'] * $quantity,
            'ticket_name' => $type['name'],
            'price' => $type['price']
        ], 'تمت عملية البيع بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        jsonOut(false, null, 'حدث خطأ أثناء البيع: ' . $e->getMessage());
    }
}

// ── GET: stats for a single ticket type ──────────────────────────────────────
if ($action === 'stats') {
    $typeId = intval($_GET['ticket_type_id'] ?? 0);
    if (!$typeId) jsonOut(false, null, 'معرّف غير صحيح');

    $stmt = $db->prepare("
        SELECT 
            t.*,
            (t.serial_to - t.serial_from + 1) AS total_count,
            COALESCE(s.sold_count, 0) AS sold_count,
            COALESCE(s.total_revenue, 0) AS total_revenue,
            (t.serial_to - t.serial_from + 1 - COALESCE(s.sold_count, 0)) AS remaining_count
        FROM ticket_types t
        LEFT JOIN (
            SELECT ticket_type_id, COUNT(*) AS sold_count, SUM(sale_price) AS total_revenue
            FROM ticket_sales
            GROUP BY ticket_type_id
        ) s ON s.ticket_type_id = t.id
        WHERE t.id = ?
    ");
    $stmt->execute([$typeId]);
    jsonOut(true, $stmt->fetch(PDO::FETCH_ASSOC));
}

jsonOut(false, null, 'إجراء غير معروف');

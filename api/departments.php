<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getDepartments();
        break;
    case 'POST':
        if ($action === 'delete') deleteDepartment();
        else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id']) && $input['id'] > 0) updateDepartment();
            else createDepartment();
        }
        break;
    default:
        jsonResponse(false, null, 'Method not allowed', 405);
}

function getDepartments() {
    $db = getDB();
    $all = $_GET['all'] ?? false;
    $sql = "SELECT d.*, p.name AS printer_name, p.ip AS printer_ip, p.type AS printer_type
            FROM departments d
            LEFT JOIN printers p ON d.printer_id = p.id"
            . (!$all ? " WHERE d.is_active=1" : "") .
            " ORDER BY d.name_ar";
    $rows = $db->query($sql)->fetchAll();
    jsonResponse(true, $rows);
}

function createDepartment() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $name_ar = trim($input['name_ar'] ?? '');
    $name_en = trim($input['name_en'] ?? '');
    $printer_id = !empty($input['printer_id']) ? (int)$input['printer_id'] : null;
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (empty($name_ar) || empty($name_en)) {
        jsonResponse(false, null, 'يرجى إدخال اسم القسم بالعربية والإنجليزية', 400);
    }

    $stmt = $db->prepare("INSERT INTO departments (name_ar, name_en, printer_id, is_active) VALUES (?,?,?,?)");
    $stmt->execute([$name_ar, $name_en, $printer_id, $is_active]);
    $id = $db->lastInsertId();
    logActivity("إضافة قسم", "تمت إضافة قسم جديد: $name_ar");
    jsonResponse(true, ['id' => $id], 'تمت إضافة القسم بنجاح');
}

function updateDepartment() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $name_ar = trim($input['name_ar'] ?? '');
    $name_en = trim($input['name_en'] ?? '');
    $printer_id = !empty($input['printer_id']) ? (int)$input['printer_id'] : null;
    $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if (!$id || empty($name_ar) || empty($name_en)) {
        jsonResponse(false, null, 'بيانات غير مكتملة', 400);
    }

    $stmt = $db->prepare("UPDATE departments SET name_ar=?, name_en=?, printer_id=?, is_active=? WHERE id=?");
    $stmt->execute([$name_ar, $name_en, $printer_id, $is_active, $id]);
    logActivity("تحديث قسم", "تعديل بيانات قسم: $name_ar");
    jsonResponse(true, null, 'تم تعديل القسم بنجاح');
}

function deleteDepartment() {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(false, null, 'معرف غير صالح', 400);

    try {
        $db->prepare("UPDATE categories SET department_id=NULL WHERE department_id=?")->execute([$id]);
        $db->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
        logActivity("حذف قسم", "تم حذف القسم (معرف: $id)");
        jsonResponse(true, null, 'تم حذف القسم بنجاح');
    } catch (Exception $e) {
        jsonResponse(false, null, 'خطأ: ' . $e->getMessage(), 500);
    }
}

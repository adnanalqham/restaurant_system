<?php
require_once __DIR__ . '/../config/db.php';
requireAuth(['admin', 'accountant']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getLogs();
} else {
    jsonResponse(false, null, 'Method not allowed', 405);
}

function getLogs() {
    $db = getDB();
    $limit  = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    $sort   = ($_GET['sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    
    $where = ["1=1"];
    $params = [];

    // Search query (order number, operation, details)
    if (!empty($_GET['search'])) {
        $search = trim($_GET['search']);
        $where[] = "(al.action LIKE ? OR al.details LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // User filter
    if (!empty($_GET['user_id'])) {
        $where[] = "al.user_id = ?";
        $params[] = (int)$_GET['user_id'];
    }

    // Date filter
    if (!empty($_GET['date'])) {
        $where[] = "DATE(al.created_at) = ?";
        $params[] = $_GET['date'];
    }

    $whereClause = implode(" AND ", $where);
    
    $stmt = $db->prepare("
        SELECT al.*, u.name as user_name, r.name as user_role
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE $whereClause
        ORDER BY al.created_at $sort
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Total count for pagination
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM activity_log al WHERE $whereClause");
    $totalStmt->execute($params);
    $total = $totalStmt->fetchColumn();
    
    // Users list for dropdown
    $usersList = $db->query("SELECT id, name FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, [
        'logs' => $logs, 
        'total' => (int)$total,
        'users' => $usersList
    ]);
}

<?php
require_once __DIR__ . '/../config/db.php';
startSession();
$_currentUser = getCurrentUser();
// Allow: admin/cashier/accountant by role, OR any user with custom 'reports' permission
if (!$_currentUser) { http_response_code(401); die(json_encode(['success'=>false,'message'=>'غير مصرح'])); }
$_isAllowedRole = in_array($_currentUser['role'], ['admin','cashier','accountant']);
$_hasReportPerm = !empty($_currentUser['permissions']) && in_array('reports', json_decode($_currentUser['permissions'], true) ?? []);
if (!$_isAllowedRole && !$_hasReportPerm) { http_response_code(403); die(json_encode(['success'=>false,'message'=>'ليس لديك صلاحية'])); }

$action = $_GET['action'] ?? 'daily';

switch ($action) {
    case 'daily':    getDailyReport();    break;
    case 'range':    getRangeReport();    break;
    case 'top_items': getTopItems();      break;
    case 'summary':  getSummary();        break;
    case 'dashboard_charts': getDashboardCharts(); break;
    default: jsonResponse(false, null, 'إجراء غير معروف', 400);
}

function getDailyReport() {
    $db   = getDB();
    $user = getCurrentUser();
    $date = $_GET['date'] ?? date('Y-m-d');

    // ── Cashier self-filter: a cashier only sees orders THEY confirmed ──────
    // The cashier_id is set to whoever pressed "تأكيد الدفع" in updateOrderStatus
    $cashierWhere   = '';
    $cashierParams  = [];
    if ($user['role'] === 'cashier') {
        $cashierWhere  = ' AND o.cashier_id = ?';
        $cashierParams = [$user['id']];
    }

    // Fetch orders (with cashier self-filter)
    $stmt = $db->prepare("
        SELECT o.*, w.name as waiter_name, c.name as cashier_name,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN users w ON o.waiter_id = w.id
        LEFT JOIN users c ON o.cashier_id = c.id
        WHERE DATE(o.created_at) = ? $cashierWhere
        ORDER BY o.created_at DESC
    ");
    $stmt->execute(array_merge([$date], $cashierParams));
    $orders = $stmt->fetchAll();

    // Attach items
    foreach ($orders as &$order) {
        $iStmt = $db->prepare("SELECT item_name_ar, quantity, unit_price, subtotal, status FROM order_items WHERE order_id=? AND is_confirmed=1 ORDER BY id ASC");
        $iStmt->execute([$order['id']]);
        $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order);

    // ── Stats: filtered by cashier_id when role=cashier ──────────────────────
    // This ensures a cashier's revenue/stats only count orders THEY confirmed
    $statCashierWhere  = '';
    $statCashierInner  = '';
    $statParams        = [$date, $date];
    if ($user['role'] === 'cashier') {
        $statCashierWhere = ' AND cashier_id = ?';
        $statCashierInner = ' AND o2.cashier_id = ?';
        $statParams       = [$date, $user['id'], $date, $user['id']];
    }

    $statStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN 1 ELSE 0 END) as paid_orders,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN (total - refund_amount) ELSE 0 END), 0) as total_revenue,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' THEN (total - refund_amount) ELSE 0 END), 0) as total_wallet,
            IFNULL(SUM(refund_amount), 0) as refunded_amount,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN manual_discount ELSE 0 END), 0) as total_discounts,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN service_charge ELSE 0 END), 0) as total_service,
            SUM(CASE WHEN status IN ('cancelled','refunded') THEN 1 ELSE 0 END) as cancelled_orders,
            AVG(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN (total - refund_amount) ELSE NULL END) as avg_order_value,
            (SELECT IFNULL(SUM(oi.quantity), 0) 
             FROM order_items oi 
             JOIN orders o2 ON oi.order_id = o2.id 
             WHERE DATE(o2.created_at) = ? AND o2.paid_at IS NOT NULL AND o2.status != 'refunded' AND oi.is_confirmed = 1 $statCashierInner) as total_pieces
        FROM orders WHERE DATE(created_at) = ? $statCashierWhere
    ");
    $statStmt->execute($statParams);
    $stats = $statStmt->fetch();

    // Top items (filtered by cashier when applicable)
    $itemCashierWhere = $user['role'] === 'cashier' ? ' AND o.cashier_id = ?' : '';
    $itemParams       = $user['role'] === 'cashier' ? [$date, $user['id']] : [$date];

    $itemStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, i.item_number, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue, c.icon as cat_icon
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) = ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.is_confirmed = 1 $itemCashierWhere
        GROUP BY oi.item_id, oi.item_name_ar, i.item_number, c.icon
        ORDER BY total_qty DESC
    ");
    $itemStmt->execute($itemParams);
    $topItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Cashier breakdown per item
    $cashierSalesStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, u.name as cashier_name, SUM(oi.quantity) as qty
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) = ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.status != 'rejected' AND oi.is_confirmed = 1
        GROUP BY oi.item_id, oi.item_name_ar, u.id, u.name
    ");
    $cashierSalesStmt->execute([$date]);
    $cashierSales = $cashierSalesStmt->fetchAll(PDO::FETCH_ASSOC);

    $salesMap = [];
    foreach ($cashierSales as $cs) {
        $key = $cs['item_name_ar'] . '_' . ($cs['item_id'] ?? 0);
        $salesMap[$key][] = $cs['cashier_name'] . ' (' . $cs['qty'] . ')';
    }

    foreach ($topItems as &$item) {
        $key = $item['item_name_ar'] . '_' . ($item['item_id'] ?? 0);
        $item['cashier_breakdown'] = isset($salesMap[$key]) ? implode(' | ', $salesMap[$key]) : '';
    }
    unset($item);

    jsonResponse(true, ['orders' => $orders, 'stats' => $stats, 'top_items' => $topItems, 'date' => $date]);
}

function getRangeReport() {
    $db    = getDB();
    $from  = $_GET['from'] ?? date('Y-m-01');
    $to    = $_GET['to']   ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT 
            DATE(o.created_at) as date,
            COUNT(o.id) as orders_count,
            SUM(CASE WHEN o.paid_at IS NOT NULL AND o.status != 'refunded' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN o.paid_at IS NOT NULL AND o.status != 'refunded' THEN (o.total - o.refund_amount) ELSE 0 END) as revenue
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY DATE(o.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();

    $total = $db->prepare("SELECT SUM(total - refund_amount) as total FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at) BETWEEN ? AND ?");
    $total->execute([$from, $to]);
    $totalRev = $total->fetch();

    $walletTotalStmt = $db->prepare("SELECT SUM(total - refund_amount) as total FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE(created_at) BETWEEN ? AND ?");
    $walletTotalStmt->execute([$from, $to]);
    $walletTotal = $walletTotalStmt->fetch();

    // Total discounts for the range (same conditions as export_report.php)
    $discountStmt = $db->prepare("SELECT IFNULL(SUM(manual_discount),0) as total FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at) BETWEEN ? AND ?");
    $discountStmt->execute([$from, $to]);
    $totalDiscounts = (float)$discountStmt->fetchColumn();

    // Total refunds for the range (same conditions as export_report.php)
    $refundStmt = $db->prepare("SELECT IFNULL(SUM(refund_amount),0) as total FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
    $refundStmt->execute([$from, $to]);
    $totalRefunds = (float)$refundStmt->fetchColumn();

    // Total pieces sold for the range
    $piecesStmt = $db->prepare("SELECT IFNULL(SUM(oi.quantity),0) as total FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.is_confirmed = 1 AND DATE(o.created_at) BETWEEN ? AND ?");
    $piecesStmt->execute([$from, $to]);
    $totalPieces = (int)$piecesStmt->fetchColumn();

    // Fetch cashier sales breakdown for this range
    $cashierSalesStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, u.name as cashier_name, SUM(oi.quantity) as qty
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.status != 'rejected' AND oi.is_confirmed = 1
        GROUP BY oi.item_id, oi.item_name_ar, u.id, u.name
    ");
    $cashierSalesStmt->execute([$from, $to]);
    $cashierSales = $cashierSalesStmt->fetchAll(PDO::FETCH_ASSOC);

    $salesMap = [];
    foreach ($cashierSales as $cs) {
        $key = $cs['item_name_ar'] . '_' . ($cs['item_id'] ?? 0);
        $salesMap[$key][] = $cs['cashier_name'] . ' (' . $cs['qty'] . ')';
    }

    $itemStmt = $db->prepare("
        SELECT oi.item_id, oi.item_name_ar, i.item_number, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_revenue, c.icon as cat_icon
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.is_confirmed = 1
        GROUP BY oi.item_id, oi.item_name_ar, i.item_number, c.icon
        ORDER BY total_qty DESC
    ");
    $itemStmt->execute([$from, $to]);
    $topItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($topItems as &$item) {
        $key = $item['item_name_ar'] . '_' . ($item['item_id'] ?? 0);
        $item['cashier_breakdown'] = isset($salesMap[$key]) ? implode(' | ', $salesMap[$key]) : '';
    }
    unset($item);

    jsonResponse(true, [
        'rows'             => $rows,
        'total_revenue'    => $totalRev['total'] ?? 0,
        'total_wallet'     => $walletTotal['total'] ?? 0,
        'total_discounts'  => $totalDiscounts,
        'total_refunds'    => $totalRefunds,
        'total_pieces'     => $totalPieces,
        'top_items'        => $topItems,
        'from'             => $from,
        'to'               => $to
    ]);
}

function getTopItems() {
    $db   = getDB();
    $date = $_GET['date'] ?? date('Y-m-d');
    $limit = (int)($_GET['limit'] ?? 10);

    $stmt = $db->prepare("
        SELECT oi.item_name_ar, oi.item_name_en, c.name_ar as cat_name,
               SUM(oi.quantity) as total_qty,
               SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        WHERE DATE(o.created_at) = ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.is_confirmed = 1
        GROUP BY oi.item_id, oi.item_name_ar, oi.item_name_en, c.name_ar
        ORDER BY total_qty DESC
        LIMIT ?
    ");
    $stmt->execute([$date, $limit]);
    jsonResponse(true, $stmt->fetchAll());
}

function getSummary() {
    $db = getDB();
    $today = date('Y-m-d');
    $month = date('Y-m');
    
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    // Always fetch actual today's revenue
    $todayRevStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at)=?");
    $todayRevStmt->execute([$today]);
    $todayRev = (float)$todayRevStmt->fetchColumn();

    // Always fetch actual today's wallet revenue (deposits)
    $todayWalletStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE(created_at)=?");
    $todayWalletStmt->execute([$today]);
    $todayWallet = (float)$todayWalletStmt->fetchColumn();

    // Always fetch actual today's external revenue (customer_type = 'room')
    $todayExtStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND customer_type='room' AND DATE(created_at)=?");
    $todayExtStmt->execute([$today]);
    $todayExternal = (float)$todayExtStmt->fetchColumn();

    // Always fetch global pending orders
    $pendingCount = $db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','sent_to_cashier','confirmed','in_progress')")->fetchColumn();

    // Fetch Period Revenue based on filter (or current month if no filter)
    $periodRev = 0;
    $periodWallet = 0;
    $periodExternal = 0;
    if ($from && $to) {
        $revStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $revStmt->execute([$from, $to]);
        $periodRev = (float)$revStmt->fetchColumn();

        $walletStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $walletStmt->execute([$from, $to]);
        $periodWallet = (float)$walletStmt->fetchColumn();

        $extStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND customer_type='room' AND DATE(created_at) >= ? AND DATE(created_at) <= ?");
        $extStmt->execute([$from, $to]);
        $periodExternal = (float)$extStmt->fetchColumn();
    } else {
        $monthRevStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND DATE_FORMAT(created_at,'%Y-%m')=?");
        $monthRevStmt->execute([$month]);
        $periodRev = (float)$monthRevStmt->fetchColumn();

        $monthWalletStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND payment_method='wallet' AND DATE_FORMAT(created_at,'%Y-%m')=?");
        $monthWalletStmt->execute([$month]);
        $periodWallet = (float)$monthWalletStmt->fetchColumn();

        $monthExtStmt = $db->prepare("SELECT SUM(total - refund_amount) FROM orders WHERE paid_at IS NOT NULL AND status != 'refunded' AND customer_type='room' AND DATE_FORMAT(created_at,'%Y-%m')=?");
        $monthExtStmt->execute([$month]);
        $periodExternal = (float)$monthExtStmt->fetchColumn();
    }

    jsonResponse(true, [
        'today_revenue'   => $todayRev,
        'period_revenue'  => $periodRev,
        'pending_orders'  => (int)$pendingCount,
        'today_wallet'    => $todayWallet,
        'period_wallet'   => $periodWallet,
        'today_external'  => $todayExternal,
        'period_external' => $periodExternal,
    ]);
}

function getDashboardCharts() {
    $db = getDB();
    
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    
    // 1. Revenue Trend (Dynamic Days)
    $trendData = [];
    $begin = new DateTime($from);
    $end = new DateTime($to);
    $end->modify('+1 day'); // Include end date
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($begin, $interval, $end);
    
    foreach ($period as $dt) {
        $dateStr = $dt->format("Y-m-d");
        $trendData[$dateStr] = ['date' => $dateStr, 'revenue' => 0, 'orders' => 0];
    }
    
    $trendStmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            SUM(total - refund_amount) as revenue,
            COUNT(id) as orders
        FROM orders
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? AND paid_at IS NOT NULL AND status != 'refunded'
        GROUP BY DATE(created_at)
    ");
    $trendStmt->execute([$from, $to]);
    $trendResults = $trendStmt->fetchAll();
    foreach ($trendResults as $row) {
        if (isset($trendData[$row['date']])) {
            $trendData[$row['date']]['revenue'] = (float)$row['revenue'];
            $trendData[$row['date']]['orders'] = (int)$row['orders'];
        }
    }
    
    // Final trend array
    $trendFinal = array_values($trendData);
    
    // 2. Top Waiters (Date Range)
    $waitersStmt = $db->prepare("
        SELECT 
            u.name as waiter_name,
            COUNT(o.id) as orders_count,
            SUM(o.total - o.refund_amount) as total_revenue
        FROM orders o
        JOIN users u ON o.waiter_id = u.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY u.id, u.name
        ORDER BY orders_count DESC
        LIMIT 5
    ");
    $waitersStmt->execute([$from, $to]);
    $topWaiters = $waitersStmt->fetchAll();
    
    // 2.5 Top Cashiers (Date Range)
    $cashiersStmt = $db->prepare("
        SELECT 
            u.name as cashier_name,
            COUNT(o.id) as orders_count,
            SUM(o.total - o.refund_amount) as total_revenue
        FROM orders o
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? AND o.paid_at IS NOT NULL AND o.status != 'refunded'
        GROUP BY u.id, u.name
        ORDER BY total_revenue DESC
    ");
    $cashiersStmt->execute([$from, $to]);
    $topCashiers = $cashiersStmt->fetchAll();
    
    // 3. Top Categories (Date Range)
    $catStmt = $db->prepare("
        SELECT 
            c.name_ar as cat_name,
            SUM(oi.subtotal) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' AND oi.is_confirmed = 1
        GROUP BY c.id, c.name_ar
        ORDER BY total_revenue DESC
    ");
    $catStmt->execute([$from, $to]);
    $topCategories = $catStmt->fetchAll();
    
    // 4. Current Status Snapshot (Date Range)
    $statusStmt = $db->prepare("
        SELECT status, COUNT(id) as count
        FROM orders
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
        GROUP BY status
    ");
    $statusStmt->execute([$from, $to]);
    $statuses = $statusStmt->fetchAll();

    // Auto-fix old orders that have wallet_id but missing wallet_name
    $db->exec("
        UPDATE orders o
        JOIN wallets w ON o.wallet_id = w.id
        SET o.wallet_name = CONCAT(w.name, ' (', w.account_number, ')')
        WHERE o.payment_method = 'wallet'
          AND (o.wallet_name IS NULL OR o.wallet_name = '')
          AND o.wallet_id IS NOT NULL
    ");

    // Auto-fix orders that have missing wallet details but have keyword in payment_reference
    $db->exec("
        UPDATE orders
        SET wallet_id = 5, wallet_name = 'جيب (555578)'
        WHERE payment_method = 'wallet'
          AND (wallet_name IS NULL OR wallet_name = '')
          AND wallet_id IS NULL
          AND (
            payment_reference LIKE '%جيب%'
            OR payment_reference LIKE '%jeeb%'
            OR payment_reference LIKE '%جييب%'
            OR payment_reference LIKE '%جيييب%'
            OR payment_reference LIKE '%جييييب%'
            OR payment_reference LIKE '%جيييييب%'
            OR payment_reference LIKE '%جييييييب%'
          )
    ");

    $db->exec("
        UPDATE orders
        SET wallet_id = 3, wallet_name = 'جوالي (699999)'
        WHERE payment_method = 'wallet'
          AND (wallet_name IS NULL OR wallet_name = '')
          AND wallet_id IS NULL
          AND (
            payment_reference LIKE '%جوالي%'
            OR payment_reference LIKE '%جوا%'
          )
    ");

    $db->exec("
        UPDATE orders
        SET wallet_id = 4, wallet_name = 'كاش (777777)'
        WHERE payment_method = 'wallet'
          AND (wallet_name IS NULL OR wallet_name = '')
          AND wallet_id IS NULL
          AND (
            payment_reference LIKE '%كاش%'
          )
    ");

    $db->exec("
        UPDATE orders
        SET wallet_id = 2, wallet_name = 'فلوسك (888888)'
        WHERE payment_method = 'wallet'
          AND (wallet_name IS NULL OR wallet_name = '')
          AND wallet_id IS NULL
          AND (
            payment_reference LIKE '%فلوس%'
          )
    ");

    // 5. Wallets Breakdown (Date Range)
    $walletsStmt = $db->prepare("
        SELECT 
            CASE 
                WHEN (o.wallet_name IS NULL OR o.wallet_name = '') AND w.name IS NOT NULL
                    THEN CONCAT(w.name, ' (', w.account_number, ')')
                WHEN o.wallet_name IS NOT NULL AND o.wallet_name != '' THEN o.wallet_name
                ELSE 'محفظة غير محددة'
            END as wallet_name,
            COUNT(o.id) as orders_count,
            SUM(o.total - o.refund_amount) as total_revenue
        FROM orders o
        LEFT JOIN wallets w ON o.wallet_id = w.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ? 
          AND o.paid_at IS NOT NULL 
          AND o.status != 'refunded' 
          AND o.payment_method = 'wallet'
        GROUP BY wallet_name
        ORDER BY total_revenue DESC
    ");
    $walletsStmt->execute([$from, $to]);
    $walletsData = $walletsStmt->fetchAll();
    
    jsonResponse(true, [
        'trend' => $trendFinal,
        'waiters' => $topWaiters,
        'cashiers' => $topCashiers,
        'categories' => $topCategories,
        'statuses' => $statuses,
        'wallets' => $walletsData
    ]);
}

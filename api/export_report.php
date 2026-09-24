<?php
/**
 * Formatted Excel Export for Daily Report
 * Generates a real .xls (Excel HTML format) with proper layout
 */
require_once __DIR__ . '/../config/db.php';
startSession();
$currentUser = getCurrentUser();
if (!$currentUser) {
    http_response_code(401);
    die('غير مصرح');
}
$isAllowedRole = in_array($currentUser['role'], ['admin', 'cashier', 'accountant']);
$hasReportPerm = !empty($currentUser['permissions']) &&
    in_array('reports', json_decode($currentUser['permissions'], true) ?? []);
if (!$isAllowedRole && !$hasReportPerm) {
    http_response_code(403);
    die('ليس لديك صلاحية');
}

$date = $_GET['date'] ?? date('Y-m-d');
$from = $_GET['from'] ?? $date;
$to = $_GET['to'] ?? $date;
$mode = $_GET['mode'] ?? 'detailed';
$settings = getSettings();
$restName = $settings['restaurant_name'] ?? 'نظام الكاشير';
$db = getDB();

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

// ─── ITEMS MODE: aggregated items export ──────────────────────────────────
if ($mode === 'items') {
    // Build label for filename
    $rangeLabel = ($from === $to) ? $from : ($from . '_الى_' . $to);
    $filename = 'تقرير_الاصناف_' . $rangeLabel . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    // Query: group by item_id + item_name exactly as the website shows
    // MUST match reports.php: paid_at IS NOT NULL AND status != 'refunded'
    $cashierFilterSql = "";
    $params = [$from, $to];
    if ($currentUser['role'] === 'cashier') {
        $cashierFilterSql = " AND o.cashier_id = ? ";
        $params[] = $currentUser['id'];
    }

    $itemsStmt = $db->prepare("
        SELECT
            oi.item_id,
            oi.item_name_ar                   AS item_name,
            i.item_number,
            o.payment_method,
            o.wallet_name,
            o.customer_type,
            o.customer_ref,
            oi.unit_price,
            SUM(oi.quantity)                  AS total_qty,
            SUM(oi.subtotal)                  AS total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
          AND o.paid_at IS NOT NULL
          AND o.status != 'refunded'
          AND oi.status != 'rejected'
          AND oi.is_confirmed = 1
          $cashierFilterSql
        GROUP BY oi.item_id, oi.item_name_ar, i.item_number, oi.unit_price, o.payment_method, o.wallet_name, o.customer_type, o.customer_ref
        ORDER BY total_qty DESC
    ");
    $itemsStmt->execute($params);
    $itemsData = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

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

    foreach ($itemsData as &$row) {
        $key = $row['item_name'] . '_' . ($row['item_id'] ?? 0);
        $row['cashier_breakdown'] = isset($salesMap[$key]) ? implode(' | ', $salesMap[$key]) : '';
    }
    unset($row);

    $grandQty = array_sum(array_column($itemsData, 'total_qty'));
    $grandRev = array_sum(array_column($itemsData, 'total_revenue'));
    $periodLabel = ($from === $to) ? $from : ($from . ' — ' . $to);
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">

    <head>
        <meta charset="utf-8">
        <!--[if gte mso 9]><xml>
 <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
  <x:Name>إحصائيات الأصناف</x:Name>
  <x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions>
 </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->
        <style>
            body {
                font-family: Arial;
                direction: rtl;
            }

            table {
                border-collapse: collapse;
                width: 100%;
            }

            td,
            th {
                border: 1px solid #ccc;
                padding: 5px 10px;
                white-space: nowrap;
                font-size: 11pt;
            }

            .title {
                background: #1a3c5e;
                color: #fff;
                font-size: 14pt;
                font-weight: bold;
                text-align: center;
            }

            .subtitle {
                background: #2e6da4;
                color: #fff;
                font-size: 10pt;
                text-align: center;
            }

            .hdr {
                background: #2e6da4;
                color: #fff;
                font-weight: bold;
                text-align: center;
            }

            .alt-row {
                background: #f5f9ff;
            }

            .item-row {
                background: #ffffff;
            }

            .num {
                text-align: center;
            }

            .money {
                text-align: center;
                color: #1a3c5e;
                font-weight: bold;
            }

            .grand-row {
                background: #1a3c5e;
                color: #fff;
                font-weight: bold;
                font-size: 12pt;
                text-align: center;
            }
        </style>
    </head>

    <body>
        <table>
            <tr>
                <td colspan="7" class="title"><?= htmlspecialchars($restName) ?> — إحصائيات الأصناف المباعة</td>
            </tr>
            <tr>
                <td colspan="7" class="subtitle">الفترة: <?= $periodLabel ?> | إجمالي الحبات:
                    <?= number_format($grandQty) ?> | إجمالي الإيرادات: <?= number_format($grandRev, 2) ?> ريال
                </td>
            </tr>
            <tr>
                <th class="hdr">#</th>
                <th class="hdr">اسم الصنف</th>
                <th class="hdr">سعر الوحدة</th>
                <th class="hdr">مبيعات الكاشير</th>
                <th class="hdr">طريقة الدفع</th>
                <th class="hdr">الأعداد المباعة</th>
                <th class="hdr">المبلغ الإجمالي (ريال)</th>
            </tr>
            <?php foreach ($itemsData as $idx => $row):
                $payMethodText = '-';
                $ct = $row['customer_type'] ?? 'normal';
                if ($ct === 'room') {
                    $payMethodText = 'مبيعات خارجي (غرفة)' . (!empty($row['customer_ref']) ? ' (' . htmlspecialchars($row['customer_ref']) . ')' : '');
                } elseif ($ct === 'staff') {
                    $payMethodText = 'موظف' . (!empty($row['customer_ref']) ? ' (' . htmlspecialchars($row['customer_ref']) . ')' : '');
                } else {
                    if ($row['payment_method'] === 'cash') {
                        $payMethodText = 'كاش';
                    } elseif ($row['payment_method'] === 'wallet') {
                        $payMethodText = 'محفظة' . (!empty($row['wallet_name']) ? ' (' . htmlspecialchars($row['wallet_name']) . ')' : '');
                    }
                }
                ?>
                <tr class="<?= $idx % 2 === 0 ? 'item-row' : 'alt-row' ?>">
                    <td class="num"><?= $idx + 1 ?></td>
                    <td><?= !empty($row['item_number']) ? '(' . htmlspecialchars($row['item_number']) . ') ' : '' ?><?= htmlspecialchars($row['item_name']) ?>
                    </td>
                    <td class="money"><?= number_format((float) $row['unit_price'], 2) ?></td>
                    <td style="white-space:normal;text-align:center"><?= htmlspecialchars($row['cashier_breakdown'] ?? '-') ?>
                    </td>
                    <td class="num"><?= $payMethodText ?></td>
                    <td class="num" style="font-weight:700;color:#1a3c5e"><?= number_format((int) $row['total_qty']) ?></td>
                    <td class="money"><?= number_format((float) $row['total_revenue'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="grand-row">
                <td colspan="5">الإجمالي الكلي — <?= count($itemsData) ?> سجل</td>
                <td><?= number_format($grandQty) ?> حبة</td>
                <td><?= number_format($grandRev, 2) ?> ريال</td>
            </tr>
        </table>
    </body>

    </html>
    <?php
    exit;
}


// ─── REFUNDS AUDIT MODE ──────────────────────────────────────────────────
if ($mode === 'refunds_audit') {
    $rangeLabel = ($from === $to) ? $from : ($from . '_الى_' . $to);
    $filename = 'تقرير_المرتجعات_' . $rangeLabel . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF";

    $cashierFilterPlain = "";
    $paramsPlain = [$from, $to];
    if ($currentUser['role'] === 'cashier') {
        $cashierFilterPlain = " AND o.cashier_id = ?";
        $paramsPlain[] = $currentUser['id'];
    }

    $stmt = $db->prepare("
        SELECT
            o.order_number,
            DATE(o.created_at)    AS order_date,
            TIME(o.created_at)    AS order_time,
            o.status,
            o.total,
            o.refund_amount,
            o.manual_discount,
            u.name                AS cashier_name,
            o.notes
        FROM orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        WHERE o.refund_amount > 0
          AND DATE(o.created_at) BETWEEN ? AND ?
          $cashierFilterPlain
        ORDER BY o.refund_amount DESC
    ");
    $stmt->execute($paramsPlain);
    $refundRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRefunds    = array_sum(array_column($refundRows, 'refund_amount'));
    $periodLabel = ($from === $to) ? $from : ($from . ' — ' . $to);

    $statusLabels = [
        'paid'               => 'مدفوع (استرجاع جزئي)',
        'partially_refunded' => 'مسترجع جزئياً',
        'refunded'           => 'مسترجع بالكامل',
        'cancelled'          => 'ملغي',
    ];
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="utf-8">
    <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
      <x:Name>تقرير المرتجعات</x:Name>
      <x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions>
    </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
    <style>
        body { font-family: Arial; direction: rtl; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ccc; padding: 6px 10px; font-size: 11pt; }
        .title   { background:#1a3c5e; color:#fff; font-size:14pt; font-weight:bold; text-align:center; }
        .sub     { background:#2e6da4; color:#fff; font-size:10pt; text-align:center; }
        .hdr     { background:#c0392b; color:#fff; font-weight:bold; text-align:center; }
        .money   { text-align:center; color:#c0392b; font-weight:bold; }
        .num     { text-align:center; }
        .total-row { background:#fdecea; font-weight:bold; }
        .note    { color:#888; font-size:9.5pt; }
    </style></head>
    <body><table>
        <tr><td colspan="9" class="title"><?= htmlspecialchars($restName) ?> — تقرير تفصيلي للمرتجعات</td></tr>
        <tr><td colspan="9" class="sub">الفترة: <?= $periodLabel ?> | عدد السجلات: <?= count($refundRows) ?> | إجمالي المرتجعات: <?= number_format($totalRefunds, 2) ?> ريال</td></tr>
        <tr>
            <th class="hdr">#</th>
            <th class="hdr">رقم الطلب</th>
            <th class="hdr">التاريخ</th>
            <th class="hdr">الوقت</th>
            <th class="hdr">حالة الطلب</th>
            <th class="hdr">إجمالي الفاتورة</th>
            <th class="hdr">قيمة المرتجع</th>
            <th class="hdr">الكاشير</th>
            <th class="hdr">ملاحظات</th>
        </tr>
        <?php $i = 1; foreach ($refundRows as $r): ?>
        <tr>
            <td class="num"><?= $i++ ?></td>
            <td class="num" style="font-weight:bold"><?= htmlspecialchars($r['order_number']) ?></td>
            <td class="num"><?= $r['order_date'] ?></td>
            <td class="num"><?= $r['order_time'] ?></td>
            <td class="num"><?= $statusLabels[$r['status']] ?? $r['status'] ?></td>
            <td class="num"><?= number_format($r['total'], 2) ?></td>
            <td class="money"><?= number_format($r['refund_amount'], 2) ?></td>
            <td class="num"><?= htmlspecialchars($r['cashier_name'] ?? '-') ?></td>
            <td class="note" style="white-space:normal;max-width:200px"><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="6" style="text-align:right;padding-right:10px">إجمالي المرتجعات (<?= count($refundRows) ?> سجل)</td>
            <td class="money"><?= number_format($totalRefunds, 2) ?> ريال</td>
            <td colspan="2"></td>
        </tr>
    </table></body></html>
    <?php
    exit;
}

// ─── REJECTED ITEMS AUDIT MODE ───────────────────────────────────────────
if ($mode === 'rejected_audit') {
    $rangeLabel = ($from === $to) ? $from : ($from . '_الى_' . $to);
    $filename = 'تقرير_الاصناف_المرفوضة_' . $rangeLabel . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF";

    $cashierFilterO = "";
    $paramsO = [$from, $to];
    if ($currentUser['role'] === 'cashier') {
        $cashierFilterO = " AND o.cashier_id = ?";
        $paramsO[] = $currentUser['id'];
    }

    $stmt = $db->prepare("
        SELECT
            o.order_number,
            DATE(o.created_at)    AS order_date,
            TIME(o.created_at)    AS order_time,
            oi.item_name_ar       AS item_name,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            u.name                AS cashier_name,
            o.notes               AS order_notes
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN users u ON o.cashier_id = u.id
        WHERE oi.status = 'rejected'
          AND o.paid_at IS NOT NULL
          AND o.status != 'refunded'
          AND DATE(o.created_at) BETWEEN ? AND ?
          $cashierFilterO
        ORDER BY o.created_at, o.id
    ");
    $stmt->execute($paramsO);
    $rejRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalValue   = array_sum(array_column($rejRows, 'subtotal'));
    $totalQty     = array_sum(array_column($rejRows, 'quantity'));
    $affectedOrders = count(array_unique(array_column($rejRows, 'order_number')));
    $periodLabel  = ($from === $to) ? $from : ($from . ' — ' . $to);
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="utf-8">
    <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
      <x:Name>الأصناف المرفوضة</x:Name>
      <x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions>
    </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
    <style>
        body { font-family: Arial; direction: rtl; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ccc; padding: 6px 10px; font-size: 11pt; }
        .title   { background:#1a3c5e; color:#fff; font-size:14pt; font-weight:bold; text-align:center; }
        .sub     { background:#2e6da4; color:#fff; font-size:10pt; text-align:center; }
        .hdr     { background:#d35400; color:#fff; font-weight:bold; text-align:center; }
        .money   { text-align:center; color:#d35400; font-weight:bold; }
        .num     { text-align:center; }
        .total-row { background:#fff3e0; font-weight:bold; }
        .note    { color:#888; font-size:9.5pt; }
    </style></head>
    <body><table>
        <tr><td colspan="9" class="title"><?= htmlspecialchars($restName) ?> — تقرير تفصيلي للأصناف المرفوضة</td></tr>
        <tr><td colspan="9" class="sub">الفترة: <?= $periodLabel ?> | عدد الأصناف: <?= count($rejRows) ?> | عدد الطلبات المتأثرة: <?= $affectedOrders ?> | إجمالي القيمة: <?= number_format($totalValue, 2) ?> ريال</td></tr>
        <tr>
            <th class="hdr">#</th>
            <th class="hdr">رقم الطلب</th>
            <th class="hdr">التاريخ</th>
            <th class="hdr">الوقت</th>
            <th class="hdr">اسم الصنف</th>
            <th class="hdr">الكمية</th>
            <th class="hdr">سعر الوحدة</th>
            <th class="hdr">القيمة الإجمالية</th>
            <th class="hdr">ملاحظات الطلب</th>
        </tr>
        <?php $i = 1; foreach ($rejRows as $r): ?>
        <tr>
            <td class="num"><?= $i++ ?></td>
            <td class="num" style="font-weight:bold"><?= htmlspecialchars($r['order_number']) ?></td>
            <td class="num"><?= $r['order_date'] ?></td>
            <td class="num"><?= $r['order_time'] ?></td>
            <td><?= htmlspecialchars($r['item_name']) ?></td>
            <td class="num"><?= number_format($r['quantity']) ?></td>
            <td class="num"><?= number_format($r['unit_price'], 2) ?></td>
            <td class="money"><?= number_format($r['subtotal'], 2) ?></td>
            <td class="note" style="white-space:normal;max-width:200px"><?= htmlspecialchars($r['order_notes'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="5" style="text-align:right;padding-right:10px">الإجمالي — <?= count($rejRows) ?> صنف في <?= $affectedOrders ?> طلب</td>
            <td class="num"><?= number_format($totalQty) ?> حبة</td>
            <td></td>
            <td class="money"><?= number_format($totalValue, 2) ?> ريال</td>
            <td></td>
        </tr>
    </table></body></html>
    <?php
    exit;
}

// ─── MANAGEMENT MODE: comprehensive administrative report ────────────────
if ($mode === 'management') {
    $rangeLabel = ($from === $to) ? $from : ($from . '_الى_' . $to);
    $filename = 'التقرير_الاداري_' . $rangeLabel . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    $cashierFilterPlain = "";
    $cashierFilterO = "";
    $paramsPlain = [$from, $to];
    $paramsO = [$from, $to];
    if ($currentUser['role'] === 'cashier') {
        $cashierFilterPlain = " AND cashier_id = ? ";
        $cashierFilterO = " AND o.cashier_id = ? ";
        $paramsPlain[] = $currentUser['id'];
        $paramsO[] = $currentUser['id'];
    }

    // 1. Orders by Status
    $statusStmt = $db->prepare("
        SELECT status, COUNT(*) as cnt 
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ? $cashierFilterPlain
        GROUP BY status
    ");
    $statusStmt->execute($paramsPlain);
    $statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusCounts = [
        'pending'         => 0,
        'sent_to_cashier' => 0,
        'confirmed'       => 0,
        'in_progress'     => 0,
        'ready'           => 0,
        'paid'            => 0,
        'refunded'        => 0,
        'cancelled'       => 0,
        'rejected'        => 0,
    ];
    $totalOrders = 0;
    foreach ($statusRows as $r) {
        $statusCounts[$r['status']] = (int)$r['cnt'];
        $totalOrders += (int)$r['cnt'];
    }

    // 2. Financial Metrics (General)
    $finStmt = $db->prepare("
        SELECT 
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN (total + manual_discount) ELSE 0 END), 0) as total_before_disc,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN manual_discount ELSE 0 END), 0) as total_discount,
            IFNULL(SUM(refund_amount), 0) as total_refund,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' THEN (total - refund_amount) ELSE 0 END), 0) as net_revenue,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' AND payment_method = 'cash' THEN (total - refund_amount) ELSE 0 END), 0) as cash_net,
            IFNULL(SUM(CASE WHEN paid_at IS NOT NULL AND status != 'refunded' AND payment_method = 'wallet' THEN (total - refund_amount) ELSE 0 END), 0) as wallet_net,
            IFNULL(SUM(CASE WHEN status IN ('pending', 'sent_to_cashier') THEN total ELSE 0 END), 0) as pending_cashier_amount,
            IFNULL(SUM(CASE WHEN status != 'cancelled' THEN (total - refund_amount) ELSE 0 END), 0) as grand_active_total
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ? $cashierFilterPlain
    ");
    $finStmt->execute($paramsPlain);
    $fin = $finStmt->fetch(PDO::FETCH_ASSOC);

    // 2b. Rejected items within paid orders (oi.status='rejected')
    $rejItemsStmt = $db->prepare("
        SELECT 
            COUNT(oi.id)               AS rejected_items_count,
            IFNULL(SUM(oi.subtotal),0) AS rejected_items_value
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
          AND o.paid_at IS NOT NULL
          AND o.status != 'refunded'
          AND oi.status = 'rejected'
          $cashierFilterO
    ");
    $rejItemsStmt->execute($paramsO);
    $rejItems = $rejItemsStmt->fetch(PDO::FETCH_ASSOC);

    // 3. Cashier breakdown
    $cashierStmt = $db->prepare("
        SELECT 
            u.name as cashier_name,
            COUNT(o.id) as total_orders,
            SUM(CASE WHEN o.payment_method = 'cash' AND (o.customer_type IS NULL OR o.customer_type NOT IN ('staff', 'room')) THEN (o.total - o.refund_amount) ELSE 0 END) as cash_sales,
            SUM(CASE WHEN o.payment_method = 'wallet' AND (o.customer_type IS NULL OR o.customer_type NOT IN ('staff', 'room')) THEN (o.total - o.refund_amount) ELSE 0 END) as wallet_sales,
            SUM(CASE WHEN o.customer_type = 'staff' THEN (o.total - o.refund_amount) ELSE 0 END) as staff_sales,
            SUM(CASE WHEN o.customer_type = 'room' THEN (o.total - o.refund_amount) ELSE 0 END) as room_sales,
            SUM(o.total - o.refund_amount) as total_sales
        FROM orders o
        JOIN users u ON o.cashier_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' $cashierFilterO
        GROUP BY u.id, u.name
        ORDER BY total_sales DESC
    ");
    $cashierStmt->execute($paramsO);
    $cashiers = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Wallet breakdown
    $walletStmt = $db->prepare("
        SELECT 
            wallet_name,
            COUNT(id) as total_orders,
            SUM(total - refund_amount) as total_sales
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ? AND paid_at IS NOT NULL AND status != 'refunded' AND payment_method = 'wallet' $cashierFilterPlain
        GROUP BY wallet_name
        ORDER BY total_sales DESC
    ");
    $walletStmt->execute($paramsPlain);
    $wallets = $walletStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Special Customer Segments
    $segStmt = $db->prepare("
        SELECT 
            customer_type,
            COUNT(id) as total_orders,
            SUM(total - refund_amount) as total_sales
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ? AND paid_at IS NOT NULL AND status != 'refunded' $cashierFilterPlain
        GROUP BY customer_type
        ORDER BY total_sales DESC
    ");
    $segStmt->execute($paramsPlain);
    $segmentsRows = $segStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $segments = [
        'normal' => ['name' => 'زبائن عاديين', 'count' => 0, 'sales' => 0],
        'staff'  => ['name' => 'موظفين', 'count' => 0, 'sales' => 0],
        'room'   => ['name' => 'مبيعات خارجي (غرف)', 'count' => 0, 'sales' => 0],
    ];
    foreach ($segmentsRows as $r) {
        $type = $r['customer_type'] ?: 'normal';
        if (isset($segments[$type])) {
            $segments[$type]['count'] = (int)$r['total_orders'];
            $segments[$type]['sales'] = (float)$r['total_sales'];
        }
    }

    // 6. Waiter Performance
    $waiterStmt = $db->prepare("
        SELECT 
            u.name as waiter_name,
            COUNT(o.id) as total_orders,
            SUM(o.total - o.refund_amount) as total_sales
        FROM orders o
        JOIN users u ON o.waiter_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.paid_at IS NOT NULL AND o.status != 'refunded' $cashierFilterO
        GROUP BY u.id, u.name
        ORDER BY total_sales DESC
    ");
    $waiterStmt->execute($paramsO);
    $waiters = $waiterStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. All items sorted by qty (most sold first)
    $topItemsStmt = $db->prepare("
        SELECT 
            oi.item_name_ar as item_name,
            i.item_number,
            SUM(oi.quantity) as total_qty,
            SUM(oi.subtotal) as total_sales
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN items i ON i.id = oi.item_id
        WHERE DATE(o.created_at) BETWEEN ? AND ? 
          AND o.paid_at IS NOT NULL 
          AND o.status != 'refunded' 
          AND oi.status != 'rejected'
          AND oi.is_confirmed = 1
          $cashierFilterO
        GROUP BY oi.item_id, oi.item_name_ar, i.item_number
        ORDER BY total_qty DESC
    ");
    $topItemsStmt->execute($paramsO);
    $topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Sales by Item Category
    $catStmt = $db->prepare("
        SELECT 
            c.name_ar as cat_name,
            SUM(oi.quantity) as total_qty,
            SUM(oi.subtotal) as total_sales
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN categories c ON oi.category_id = c.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? 
          AND o.paid_at IS NOT NULL 
          AND o.status != 'refunded' 
          AND oi.status != 'rejected'
          AND oi.is_confirmed = 1
          $cashierFilterO
        GROUP BY c.id, c.name_ar
        ORDER BY total_sales DESC
    ");
    $catStmt->execute($paramsO);
    $categorySales = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Detailed Refunds for Management
    $refStmt = $db->prepare("
        SELECT
            o.order_number,
            o.created_at,
            o.status,
            o.total,
            o.refund_amount,
            u.name                AS cashier_name,
            o.notes
        FROM orders o
        LEFT JOIN users u ON o.cashier_id = u.id
        WHERE o.refund_amount > 0
          AND DATE(o.created_at) BETWEEN ? AND ?
          $cashierFilterO
        ORDER BY o.id ASC
    ");
    $refStmt->execute($paramsO);
    $managementRefunds = $refStmt->fetchAll(PDO::FETCH_ASSOC);

    // 10. Detailed Rejected Items for Management
    $rejStmt = $db->prepare("
        SELECT
            o.order_number,
            o.created_at,
            oi.item_name_ar       AS item_name,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            c.name                AS cashier_name,
            w.name                AS waiter_name,
            o.notes               AS order_notes
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN users c ON o.cashier_id = c.id
        LEFT JOIN users w ON o.waiter_id = w.id
        WHERE oi.status = 'rejected'
          AND o.paid_at IS NOT NULL
          AND o.status != 'refunded'
          AND DATE(o.created_at) BETWEEN ? AND ?
          $cashierFilterO
        ORDER BY o.created_at ASC, o.id ASC
    ");
    $rejStmt->execute($paramsO);
    $managementRejected = $rejStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="utf-8">
        <!--[if gte mso 9]><xml>
         <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
          <x:Name>التقرير الإداري</x:Name>
          <x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions>
         </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
        </xml><![endif]-->
        <style>
            body { font-family: Arial; direction: rtl; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            td, th { border: 1px solid #ccc; padding: 6px 10px; font-size: 11pt; }
            .title { background: #1a3c5e; color: #fff; font-size: 14pt; font-weight: bold; text-align: center; }
            .subtitle { background: #2e6da4; color: #fff; font-size: 10pt; text-align: center; }
            .sec-header { background: #2f7e9b; color: #fff; font-weight: bold; font-size: 12pt; text-align: right; padding: 8px 10px; }
            .hdr { background: #e2f1f6; color: #1a3c5e; font-weight: bold; text-align: center; }
            .num { text-align: center; }
            .money { text-align: center; font-weight: bold; color: #1a3c5e; }
            .total-row { background: #eaf6ea; font-weight: bold; color: #27ae60; }
            .sep-row { height: 15px; border: none; background: #ffffff; }
            .label { font-weight: bold; color: #333; }
        </style>
    </head>
    <body>
        <table>
            <!-- Title -->
            <tr>
                <td colspan="7" class="title">التقرير المالي والإداري الشامل (ملخص الإدارة)</td>
            </tr>
            <tr>
                <td colspan="7" class="subtitle">الفترة: <?= $from ?> إلى <?= $to ?> | اسم النظام: <?= htmlspecialchars($restName) ?></td>
            </tr>
            
            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 1: KPIs -->
            <tr>
                <td colspan="7" class="sec-header">أولاً: مؤشرات الحركة والتشغيل (عدد الطلبات)</td>
            </tr>
            <tr>
                <th colspan="5" class="hdr">مؤشر الحركة للطلب</th>
                <th colspan="2" class="hdr">العدد (طلب)</th>
            </tr>
            <tr>
                <td colspan="5" class="label">إجمالي الطلبات المسجلة بالفترة (شاملة كل الحالات)</td>
                <td colspan="2" class="num" style="font-weight:bold"><?= number_format($totalOrders) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#27ae60">الطلبات المقبولة والمباعة (التي تم دفعها)</td>
                <td colspan="2" class="num" style="color:#27ae60; font-weight:bold"><?= number_format($statusCounts['paid']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#c0392b">الطلبات الملغية تماماً (أُلغيت بالكامل من الكاشير قبل الدفع أو بدء التحضير)</td>
                <td colspan="2" class="num" style="color:#c0392b; font-weight:bold"><?= number_format($statusCounts['cancelled']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#e67e22">الطلبات المسترجعة بعد البيع (كانت مدفوعة بالكامل ثم أُرجعت واسترد العميل مبلغها)</td>
                <td colspan="2" class="num" style="color:#e67e22; font-weight:bold"><?= number_format($statusCounts['refunded']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#d35400">الطلبات المرفوضة بالكامل (طلبات أُلغيت أو رُفض تحضيرها بالكامل من الكاشير أو المطبخ)</td>
                <td colspan="2" class="num" style="color:#d35400; font-weight:bold"><?= number_format($statusCounts['rejected']) ?></td>
            </tr>
            <tr style="background:#fff8f0">
                <td colspan="5" class="label" style="color:#d35400; padding-right:30px">↳ عدد الأصناف المرفوضة داخل طلبات مقبولة ومدفوعة (رفض جزئي من المطبخ)</td>
                <td colspan="2" class="num" style="color:#d35400; font-weight:bold"><?= number_format($rejItems['rejected_items_count']) ?> صنف</td>
            </tr>
            <tr style="background:#fff8f0">
                <td colspan="5" class="label" style="color:#d35400; padding-right:30px">↳ قيمة الأصناف المرفوضة (خُصمت من إجمالي الأصناف المنفذة في القسم السابع)</td>
                <td colspan="2" class="num" style="color:#d35400; font-weight:bold"><?= number_format($rejItems['rejected_items_value'], 2) ?> ريال</td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#2980b9">الطلبات المعلقة بانتظار الكاشير (جديد)</td>
                <td colspan="2" class="num" style="color:#2980b9; font-weight:bold"><?= number_format($statusCounts['pending']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#8e44ad">الطلبات المرسلة للكاشير ولم تؤكد بعد (أُرسلت من الويتر أو الطاولة وبانتظار تأكيد الكاشير)</td>
                <td colspan="2" class="num" style="color:#8e44ad; font-weight:bold"><?= number_format($statusCounts['sent_to_cashier']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#16a085">الطلبات قيد التحضير في المطبخ (تم تأكيدها وبدأ طاقم المطبخ في تجهيزها بالفعل)</td>
                <td colspan="2" class="num" style="color:#16a085; font-weight:bold"><?= number_format($statusCounts['in_progress']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#27ae60">الطلبات الجاهزة للتسليم ولم تسلم (انتهى المطبخ من تحضيرها وبانتظار تسليمها للعميل)</td>
                <td colspan="2" class="num" style="color:#27ae60; font-weight:bold"><?= number_format($statusCounts['ready']) ?></td>
            </tr>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 2: General Financials -->
            <tr>
                <td colspan="7" class="sec-header">ثانياً: الحسابات والملخص المالي العام للفترة</td>
            </tr>
            <tr>
                <th colspan="5" class="hdr">البيان المالي للعمليات المقبولة</th>
                <th colspan="2" class="hdr">المبلغ المالي (ريال)</th>
            </tr>
            <tr>
                <td colspan="5" class="label">إجمالي المبيعات (قبل الخصم)</td>
                <td colspan="2" class="money"><?= number_format($fin['total_before_disc'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#c0392b">إجمالي الخصومات الممنوحة</td>
                <td colspan="2" class="money" style="color:#c0392b">-<?= number_format($fin['total_discount'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="color:#e67e22">إجمالي المبالغ المسترجعة (المرتجعات)</td>
                <td colspan="2" class="money" style="color:#e67e22">-<?= number_format($fin['total_refund'], 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="5" class="label" style="font-size:12pt">صافي الإيرادات الكلية للفترة (كاشير + محافظ)</td>
                <td colspan="2" class="money" style="font-size:12pt"><?= number_format($fin['net_revenue'], 2) ?></td>
            </tr>
            <tr class="total-row" style="background:#eef2f7; color:#1a3c5e">
                <td colspan="5" class="label" style="font-size:11pt">إجمالي المبيعات الكلي (شامل المدفوع والمعلق والمسترجع - مطابق لتقرير اليومي)</td>
                <td colspan="2" class="money" style="font-size:11pt; color:#1a3c5e"><?= number_format($fin['grand_active_total'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="padding-right:20px; font-size:10pt; color:#27ae60">➔ نصيب النقد العيني (كاش)</td>
                <td colspan="2" class="money" style="font-size:10pt; color:#27ae60"><?= number_format($fin['cash_net'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="padding-right:20px; font-size:10pt; color:#2980b9">➔ نصيب الإيداع الرقمي (محافظ)</td>
                <td colspan="2" class="money" style="font-size:10pt; color:#2980b9"><?= number_format($fin['wallet_net'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="label" style="padding-right:20px; font-size:10pt; color:#e67e22">➔ مبيعات معلقة (مرسلة للكاشير ولم تؤكد)</td>
                <td colspan="2" class="money" style="font-size:10pt; color:#e67e22"><?= number_format($fin['pending_cashier_amount'], 2) ?></td>
            </tr>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 3: Cashiers -->
            <tr>
                <td colspan="7" class="sec-header">ثالثاً: تفصيل مبيعات الكاشيرية ونشاطهم</td>
            </tr>
            <tr>
                <th class="hdr">اسم الكاشير</th>
                <th class="hdr">عدد الطلبات المقبولة</th>
                <th class="hdr">مبيعات نقدية (كاش)</th>
                <th class="hdr">مبيعات المحافظ</th>
                <th class="hdr">مبيعات الموظفين</th>
                <th class="hdr">مبيعات خارجي (غرف)</th>
                <th class="hdr">إجمالي مبيعات الكاشير</th>
            </tr>
            <?php 
            $totC_orders = 0; $totC_cash = 0; $totC_wallet = 0; $totC_staff = 0; $totC_room = 0; $totC_sales = 0;
            foreach ($cashiers as $c): 
                $totC_orders += $c['total_orders'];
                $totC_cash   += $c['cash_sales'];
                $totC_wallet += $c['wallet_sales'];
                $totC_staff  += $c['staff_sales'];
                $totC_room   += $c['room_sales'];
                $totC_sales  += $c['total_sales'];
            ?>
            <tr>
                <td class="label"><?= htmlspecialchars($c['cashier_name']) ?></td>
                <td class="num"><?= number_format($c['total_orders']) ?></td>
                <td class="money" style="font-weight:normal"><?= number_format($c['cash_sales'], 2) ?></td>
                <td class="money" style="font-weight:normal"><?= number_format($c['wallet_sales'], 2) ?></td>
                <td class="money" style="font-weight:normal"><?= number_format($c['staff_sales'], 2) ?></td>
                <td class="money" style="font-weight:normal"><?= number_format($c['room_sales'], 2) ?></td>
                <td class="money"><?= number_format($c['total_sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td class="label">إجمالي الكاشيرية</td>
                <td class="num"><?= number_format($totC_orders) ?></td>
                <td class="money"><?= number_format($totC_cash, 2) ?></td>
                <td class="money"><?= number_format($totC_wallet, 2) ?></td>
                <td class="money"><?= number_format($totC_staff, 2) ?></td>
                <td class="money"><?= number_format($totC_room, 2) ?></td>
                <td class="money"><?= number_format($totC_sales, 2) ?></td>
            </tr>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 4: Wallets -->
            <tr>
                <td colspan="7" class="sec-header">رابعاً: تفصيل مبيعات الإيداع الرقمي (المحافظ الإلكترونية)</td>
            </tr>
            <tr>
                <th colspan="3" class="hdr">اسم المحفظة الإلكترونية</th>
                <th class="hdr">عدد العمليات</th>
                <th colspan="3" class="hdr">إجمالي المبالغ المودعة</th>
            </tr>
            <?php 
            $totW_orders = 0; $totW_sales = 0;
            foreach ($wallets as $w): 
                $totW_orders += $w['total_orders'];
                $totW_sales  += $w['total_sales'];
            ?>
            <tr>
                <td colspan="3" class="label"><?= htmlspecialchars($w['wallet_name'] ?: 'محفظة غير محددة الاسم') ?></td>
                <td class="num"><?= number_format($w['total_orders']) ?></td>
                <td colspan="3" class="money"><?= number_format($w['total_sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3" class="label">إجمالي إيداعات المحافظ</td>
                <td class="num"><?= number_format($totW_orders) ?></td>
                <td colspan="3" class="money"><?= number_format($totW_sales, 2) ?></td>
            </tr>
            <?php 
            $walletSalesDiff = $totW_sales - $totC_wallet;
            if (abs($walletSalesDiff) > 0.01): 
            ?>
            <tr>
                <td colspan="7" style="color:#c0392b; font-size:10pt; text-align:right; white-space:normal; background:#fdf2f2; padding:10px; border:1px solid #fde2e2;">
                    ⚠️ <strong>تنبيه بخصوص الفارق:</strong> يوجد فارق قدره (<?= number_format($walletSalesDiff, 2) ?> ريال) بين إجمالي إيداعات المحافظ الإلكترونية هنا (<?= number_format($totW_sales, 2) ?> ريال) وإجمالي مبيعات المحافظ لدى الكاشيرية في البند ثالثاً (<?= number_format($totC_wallet, 2) ?> ريال).
                    <br>
                    <strong>السبب:</strong> هذا الفارق يمثل عمليات الدفع بالمحافظ التي تمت لصالح (الموظفين أو مبيعات الغرف/الخارجي). في جدول الكاشيرية (ثالثاً)، يتم تصنيف مبيعات الموظفين والغرف في أعمدة مستقلة بشكل منفصل منعاً للازدواجية وتكرار الحساب، بينما يسرد هذا الجدول (رابعاً) جميع الإيداعات الرقمية لكافة شرائح العملاء دون استثناء.
                </td>
            </tr>
            <?php endif; ?>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 5: Segments -->
            <tr>
                <td colspan="7" class="sec-header">خامساً: مبيعات فئات التشغيل وشرائح العملاء</td>
            </tr>
            <tr>
                <th colspan="3" class="hdr">شريحة المبيعات</th>
                <th class="hdr">عدد الطلبات</th>
                <th colspan="3" class="hdr">إجمالي المبيعات المحققة</th>
            </tr>
            <?php 
            $totS_orders = 0; $totS_sales = 0;
            foreach ($segments as $key => $s): 
                $totS_orders += $s['count'];
                $totS_sales  += $s['sales'];
            ?>
            <tr>
                <td colspan="3" class="label"><?= $s['name'] ?></td>
                <td class="num"><?= number_format($s['count']) ?></td>
                <td colspan="3" class="money"><?= number_format($s['sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3" class="label">إجمالي المبيعات الشرائحية</td>
                <td class="num"><?= number_format($totS_orders) ?></td>
                <td colspan="3" class="money"><?= number_format($totS_sales, 2) ?></td>
            </tr>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 6: Waiters -->
            <tr>
                <td colspan="7" class="sec-header">سادساً: نشاط ومبيعات الويترز (الندلاء) بالفترة</td>
            </tr>
            <tr>
                <th colspan="3" class="hdr">اسم الويتر (الندل)</th>
                <th class="hdr">عدد الطلبات المحققة</th>
                <th colspan="3" class="hdr">إجمالي المبيعات</th>
            </tr>
            <?php 
            $totWait_orders = 0; $totWait_sales = 0;
            foreach ($waiters as $wt): 
                $totWait_orders += $wt['total_orders'];
                $totWait_sales  += $wt['total_sales'];
            ?>
            <tr>
                <td colspan="3" class="label"><?= htmlspecialchars($wt['waiter_name']) ?></td>
                <td class="num"><?= number_format($wt['total_orders']) ?></td>
                <td colspan="3" class="money"><?= number_format($wt['total_sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3" class="label">إجمالي مبيعات الويترية</td>
                <td class="num"><?= number_format($totWait_orders) ?></td>
                <td colspan="3" class="money"><?= number_format($totWait_sales, 2) ?></td>
            </tr>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 7: Top Items -->
            <tr>
                <td colspan="7" class="sec-header">سابعاً: جميع الأصناف المباعة مرتبةً حسب الأكثر طلباً</td>
            </tr>
            <tr>
                <th class="hdr">رمز/رقم الصنف</th>
                <th colspan="3" class="hdr">اسم الصنف</th>
                <th class="hdr">الكمية المباعة (حبة)</th>
                <th colspan="2" class="hdr">إجمالي المبيعات (ريال)</th>
            </tr>
            <?php 
            $totI_qty = 0; $totI_sales = 0;
            foreach ($topItems as $item): 
                $totI_qty   += $item['total_qty'];
                $totI_sales += $item['total_sales'];
            ?>
            <tr>
                <td class="num"><?= htmlspecialchars($item['item_number'] ?: '-') ?></td>
                <td colspan="3" class="label"><?= htmlspecialchars($item['item_name']) ?></td>
                <td class="num"><?= number_format($item['total_qty']) ?></td>
                <td colspan="2" class="money"><?= number_format($item['total_sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="label">إجمالي الأصناف المنفذة فعلياً (بعد استبعاد الأصناف المرفوضة)</td>
                <td class="num"><?= number_format($totI_qty) ?></td>
                <td colspan="2" class="money"><?= number_format($totI_sales, 2) ?></td>
            </tr>
            <tr>
                <td colspan="7" style="color:#555; font-size:9.5pt; text-align:right; white-space:normal; background:#fffbe6; padding:12px 14px; border:1px solid #f0d060; line-height:1.9;">
                    💡 <strong>توضيح مالي هام للمحاسبين:</strong>
                    <br>
                    إجمالي هذا الجدول (<strong><?= number_format($totI_sales, 2) ?> ريال</strong>) يمثل مجموع مبيعات <strong>الأصناف المنفذة فعلياً فقط</strong>، مع استبعاد الأصناف التي رُفضت من المطبخ.
                    <br>
                    الفرق بينه وبين إجمالي المبيعات في الملخص المالي (<strong><?= number_format($fin['total_before_disc'], 2) ?> ريال</strong>) = <strong><?= number_format($fin['total_before_disc'] - $totI_sales, 2) ?> ريال</strong> يمثل قيمة الأصناف المرفوضة التي احتُسبت في الفاتورة الأصلية ثم خُصمت.
                    <br><br>
                    صافي الإيرادات الكلية الواردة في <strong>(ثانياً: الملخص المالي)</strong> تُحسب وفق المعادلة:
                    <br>
                    <strong>
                        صافي الإيرادات (<?= number_format($fin['net_revenue'], 2) ?> ريال)
                        = إجمالي المبيعات (<?= number_format($fin['total_before_disc'], 2) ?> ريال)
                        − الخصومات (<?= number_format($fin['total_discount'], 2) ?> ريال)
                        − المرتجعات (<?= number_format($fin['total_refund'], 2) ?> ريال)
                    </strong>
                </td>
            </tr>

            <tr><td colspan="7" class="sep-row"></td></tr>

            <!-- Section 8: Category Sales -->
            <tr>
                <td colspan="7" class="sec-header">ثامناً: مبيعات فئات الأصناف والأطعمة (أقسام المنيو)</td>
            </tr>
            <tr>
                <th colspan="3" class="hdr">اسم الفئة</th>
                <th class="hdr">الكمية المباعة (حبة)</th>
                <th colspan="3" class="hdr">إجمالي الإيرادات (ريال)</th>
            </tr>
            <?php 
            $totCat_qty = 0; $totCat_sales = 0;
            foreach ($categorySales as $cat): 
                $totCat_qty   += $cat['total_qty'];
                $totCat_sales += $cat['total_sales'];
            ?>
            <tr>
                <td colspan="3" class="label"><?= htmlspecialchars($cat['cat_name']) ?></td>
                <td class="num"><?= number_format($cat['total_qty']) ?></td>
                <td colspan="3" class="money"><?= number_format($cat['total_sales'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="3" class="label">إجمالي مبيعات الفئات</td>
                <td class="num"><?= number_format($totCat_qty) ?></td>
                <td colspan="3" class="money"><?= number_format($totCat_sales, 2) ?></td>
            </tr>
        </table>

        <br>

        <!-- Section 9: Detailed Refunds Report -->
        <table>
            <tr>
                <td colspan="8" class="sec-header">تاسعاً: تقرير تفصيلي للطلبات المسترجعة والمبالغ المرتدة للعملاء</td>
            </tr>
            <tr>
                <th class="hdr">رقم الطلب</th>
                <th class="hdr">التاريخ</th>
                <th class="hdr">الوقت</th>
                <th class="hdr">حالة الطلب</th>
                <th class="hdr">الكاشير</th>
                <th class="hdr">إجمالي الفاتورة</th>
                <th class="hdr">قيمة المرتجع</th>
                <th class="hdr">ملاحظات الطلب / سبب المرتجع (إن وجد)</th>
            </tr>
            <?php 
            $totM_refunds = 0;
            $statusLabels = [
                'paid'               => 'مدفوع (استرجاع جزئي)',
                'partially_refunded' => 'مسترجع جزئياً',
                'refunded'           => 'مسترجع بالكامل',
                'cancelled'          => 'ملغي',
            ];
            foreach ($managementRefunds as $r): 
                $totM_refunds += (float) $r['refund_amount'];
            ?>
            <tr>
                <td class="num" style="font-weight:bold"><?= htmlspecialchars($r['order_number']) ?></td>
                <td class="num"><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
                <td class="num"><?= date('h:i A', strtotime($r['created_at'])) ?></td>
                <td class="num"><?= $statusLabels[$r['status']] ?? statusAr($r['status']) ?></td>
                <td class="num"><?= htmlspecialchars($r['cashier_name'] ?? '-') ?></td>
                <td class="money"><?= number_format($r['total'], 2) ?></td>
                <td class="money" style="color:#c0392b"><?= number_format($r['refund_amount'], 2) ?></td>
                <td style="white-space:normal;max-width:200px"><?= htmlspecialchars($r['notes'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row" style="background:#fde8e8; color:#c0392b;">
                <td colspan="5" class="label" style="text-align:right; padding-right:10px; color:#c0392b;">عدد الطلبات التي تحتوي على مرتجعات: <?= count($managementRefunds) ?></td>
                <td class="label" style="text-align:right; padding-right:10px; color:#c0392b;">إجمالي قيمة المرتجعات:</td>
                <td class="money" style="color:#c0392b;"><?= number_format($totM_refunds, 2) ?> ريال</td>
                <td></td>
            </tr>
        </table>

        <br>

        <!-- Section 10: Detailed Rejected Items Report -->
        <table>
            <tr>
                <td colspan="10" class="sec-header">عاشراً: تقرير تفصيلي للأصناف المرفوضة من المطبخ والمستبعدة من المبيعات المنفذة</td>
            </tr>
            <tr>
                <th class="hdr">رقم الطلب</th>
                <th class="hdr">التاريخ</th>
                <th class="hdr">الوقت</th>
                <th class="hdr">اسم الصنف</th>
                <th class="hdr">الكمية</th>
                <th class="hdr">سعر الوحدة</th>
                <th class="hdr">قيمة الصنف</th>
                <th class="hdr">الكاشير</th>
                <th class="hdr">الويتر</th>
                <th class="hdr">ملاحظات الطلب</th>
            </tr>
            <?php 
            $totMR_qty = 0;
            $totMR_value = 0;
            $affectedOrders = [];
            foreach ($managementRejected as $r): 
                $totMR_qty += (int) $r['quantity'];
                $totMR_value += (float) $r['subtotal'];
                $affectedOrders[] = $r['order_number'];
            ?>
            <tr>
                <td class="num" style="font-weight:bold"><?= htmlspecialchars($r['order_number']) ?></td>
                <td class="num"><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
                <td class="num"><?= date('h:i A', strtotime($r['created_at'])) ?></td>
                <td><?= htmlspecialchars($r['item_name']) ?></td>
                <td class="num"><?= number_format($r['quantity']) ?></td>
                <td class="money"><?= number_format($r['unit_price'], 2) ?></td>
                <td class="money" style="color:#d35400"><?= number_format($r['subtotal'], 2) ?></td>
                <td class="num"><?= htmlspecialchars($r['cashier_name'] ?? '-') ?></td>
                <td class="num"><?= htmlspecialchars($r['waiter_name'] ?? '-') ?></td>
                <td style="white-space:normal;max-width:200px"><?= htmlspecialchars($r['order_notes'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row" style="background:#fff3e0; color:#d35400;">
                <td colspan="4" class="label" style="text-align:right; padding-right:10px; color:#d35400;">عدد الأصناف المرفوضة: <?= count($managementRejected) ?> | عدد الطلبات المتأثرة: <?= count(array_unique($affectedOrders)) ?></td>
                <td class="num" style="color:#d35400;"><?= number_format($totMR_qty) ?> حبة</td>
                <td></td>
                <td class="money" style="color:#d35400;"><?= number_format($totMR_value, 2) ?> ريال</td>
                <td colspan="3"></td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    exit;
}

// ─── Fetch orders (for summary / detailed modes) ──────────────────────────
$cashierFilterSql = "";
$params = [$from, $to];
if ($currentUser['role'] === 'cashier') {
    $cashierFilterSql = " AND o.cashier_id = ? ";
    $params[] = $currentUser['id'];
}

$stmt = $db->prepare("
    SELECT o.id, o.order_number, o.total, o.manual_discount, o.refund_amount, o.is_payment_modified,
           o.payment_method, o.status, o.created_at, o.table_number, o.notes, o.wallet_name,
           o.customer_type, o.customer_ref, o.guest_name, o.payment_reference,
           w.name AS waiter_name,
           c.name AS cashier_name
    FROM orders o 
    LEFT JOIN users w ON o.waiter_id = w.id
    LEFT JOIN users c ON o.cashier_id = c.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
          $cashierFilterSql
    ORDER BY o.id ASC
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Attach items
foreach ($orders as &$order) {
    $iStmt = $db->prepare(
        "SELECT oi.item_name_ar, oi.quantity, oi.unit_price, oi.subtotal, i.item_number
         FROM order_items oi
         LEFT JOIN items i ON i.id = oi.item_id
         WHERE oi.order_id = ? AND oi.is_confirmed = 1 ORDER BY oi.id ASC"
    );
    $iStmt->execute([$order['id']]);
    $order['items'] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($order);

// ─── Stats (paid orders only, excluding cancelled/refunded) ─────────────────
$totalNet = 0;
$totalDiscount = 0;
$totalBeforeDisc = 0;
$paidOrdersCount = 0;
$cancelledCount = 0;
$refundedCount = 0;
$pendingActiveCount = 0;

foreach ($orders as $o) {
    if ($o['status'] === 'cancelled') {
        $cancelledCount++;
    } elseif ($o['status'] === 'refunded') {
        $refundedCount++;
    } elseif (!empty($o['paid_at']) || in_array($o['status'], ['paid', 'delivered', 'partially_refunded'])) {
        $paidOrdersCount++;
        // Accumulate financial stats only for paid/completed orders
        $totalNet += (float) $o['total'] - (float) ($o['refund_amount'] ?? 0);
        $totalDiscount += (float) ($o['manual_discount'] ?? 0);
        $totalBeforeDisc += (float) $o['total'] + (float) ($o['manual_discount'] ?? 0);
    } else {
        $pendingActiveCount++;
    }
}

// ─── Output Excel (XLS via HTML) ──────────────────────────────────────────
$rangeLabel2 = ($from === $to) ? $from : ($from . '_' . $to);
$filename = 'تقرير_' . $rangeLabel2 . '_' . ($mode === 'detailed' ? 'تفصيلي' : 'عادي') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: no-cache');

// Helper: payment label
function payLabel($p)
{
    return $p === 'cash' ? 'كاش' : ($p === 'wallet' ? 'محفظة' : '-');
}

function statusAr($s)
{
    $map = [
        'pending'         => 'بانتظار الكاشير',
        'sent_to_cashier' => 'مرسل للكاشير',
        'confirmed'       => 'مؤكد',
        'in_progress'     => 'قيد التحضير',
        'ready'           => 'جاهز',
        'paid'            => 'مدفوع',
        'refunded'        => 'مسترجع',
        'cancelled'       => 'ملغي',
        'rejected'        => 'مرفوض',
    ];
    return $map[$s] ?? $s;
}

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

<head>
    <meta charset="utf-8">
    <!--[if gte mso 9]><xml>
 <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
  <x:Name>تقرير يومي</x:Name>
  <x:WorksheetOptions><x:DisplayRightToLeft/></x:WorksheetOptions>
 </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->
    <style>
        body {
            font-family: Arial;
            direction: rtl;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            border: 1px solid #ccc;
            padding: 5px 8px;
            white-space: nowrap;
            font-size: 11pt;
        }

        .title {
            background: #1a3c5e;
            color: #fff;
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
        }

        .subtitle {
            background: #2e6da4;
            color: #fff;
            font-size: 10pt;
            text-align: center;
        }

        .hdr {
            background: #2e6da4;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        .order-hdr {
            background: #f0f4fa;
            font-weight: bold;
            color: #1a3c5e;
        }

        .item-row {
            background: #ffffff;
        }

        .item-row td:first-child {
            background: #f8fbff;
        }

        .alt-row {
            background: #f5f5f5;
        }

        .cancelled-row {
            background: #fde8e8;
            color: #c0392b;
        }

        .cancelled-row td {
            color: #c0392b;
        }

        .total-row {
            background: #e8f4e8;
            font-weight: bold;
            color: #1d6f42;
        }

        .grand-row {
            background: #1a3c5e;
            color: #fff;
            font-weight: bold;
            font-size: 12pt;
        }

        .cancelled-summary-row {
            background: #fde8e8;
            font-weight: bold;
            color: #c0392b;
        }

        .sep {
            height: 8px;
            background: #e0e0e0;
        }

        .num {
            text-align: center;
        }

        .money {
            text-align: center;
            color: #1a3c5e;
            font-weight: bold;
        }

        .discount {
            text-align: center;
            color: #c0392b;
        }

        .net {
            text-align: center;
            color: #1d6f42;
            font-weight: bold;
        }

        .muted {
            color: #999;
            text-align: center;
        }

        .badge-cash {
            background: #27ae60;
            color: #fff;
            padding: 1px 6px;
            border-radius: 4px;
        }

        .badge-wallet {
            background: #2980b9;
            color: #fff;
            padding: 1px 6px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <table>
        <?php if ($mode === 'summary'): ?>
            <!-- ═══ SUMMARY MODE ═══ -->
            <tr>
                <td colspan="14" class="title"><?= htmlspecialchars($restName) ?> — كشف يومي</td>
            </tr>
            <tr>
                <td colspan="14" class="subtitle">الفترة: <?= $from ?> إلى <?= $to ?> | إجمالي الطلبات: <?= count($orders) ?> (مدفوع: <?= $paidOrdersCount ?> | ملغي: <?= $cancelledCount ?> | مسترجع: <?= $refundedCount ?> | أرسل للكاشير: <?= $pendingActiveCount ?>) | صافي الإيرادات: <?= number_format($totalNet, 2) ?> ريال</td>
            </tr>
            <tr>
                <th class="hdr">#</th>
                <th class="hdr">رقم الطلب</th>
                <th class="hdr">طريقة الدفع</th>
                <th class="hdr">حالة الدفع</th>
                <th class="hdr">نوع العميل</th>
                <th class="hdr">المرجع</th>
                <th class="hdr">الحالة</th>
                <th class="hdr">الوقت</th>
                <th class="hdr">الويتر</th>
                <th class="hdr">مؤكد الدفع</th>
                <th class="hdr">قبل الخصم</th>
                <th class="hdr">الخصم</th>
                <th class="hdr">الصافي</th>
                <th class="hdr">الملاحظات</th>
            </tr>
            <?php $i = 1;
            foreach ($orders as $o):
                $isCancelled = ($o['status'] === 'cancelled');
                $beforeDisc = (float) $o['total'] + (float) ($o['manual_discount'] ?? 0);
                $net = (float) $o['total'] - (float) ($o['refund_amount'] ?? 0);
                $rowClass = $isCancelled ? 'cancelled-row' : (($i % 2 === 0) ? 'alt-row' : 'item-row');
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="num"><?= $i++ ?></td>
                    <td class="num"><?= $o['order_number'] ?></td>
                    <td class="num">
                        <?php if ($o['payment_method'] === 'cash'): ?>
                            كاش
                        <?php elseif ($o['payment_method'] === 'wallet'): ?>
                            محفظة: <?= htmlspecialchars($o['wallet_name'] ?? 'رقمية') ?>
                            <?= !empty($o['payment_reference']) ? ' (مرجع: ' . htmlspecialchars($o['payment_reference']) . ')' : '' ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td class="num">
                        <?= (int) ($o['is_payment_modified'] ?? 0) === 1 ? 'معدّل' : 'غير معدل' ?>
                    </td>
                    <td class="num">
                        <?php
                        $ct = $o['customer_type'] ?? 'normal';
                        if ($ct === 'room') echo 'مبيعات خارجي (غرفة)';
                        elseif ($ct === 'staff') echo 'موظف';
                        else echo 'عادي';
                        ?>
                    </td>
                    <td class="num"><?= htmlspecialchars(($o['customer_ref'] ?? '-') . (!empty($o['guest_name']) ? ' - ' . $o['guest_name'] : '')) ?></td>
                    <td class="num" style="<?= $isCancelled ? 'color:#c0392b;font-weight:bold' : '' ?>"><?= statusAr($o['status']) ?></td>
                    <td class="num"><?= date('h:i A', strtotime($o['created_at'])) ?></td>
                    <td><?= htmlspecialchars($o['waiter_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($o['cashier_name'] ?? '-') ?></td>
                    <td class="money"><?= number_format($beforeDisc, 2) ?></td>
                    <td class="discount"><?= number_format((float) ($o['manual_discount'] ?? 0), 2) ?></td>
                    <td class="net"><?= number_format($net, 2) ?></td>
                    <td style="white-space:normal;max-width:200px"><?= htmlspecialchars($o['notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($cancelledCount > 0): ?>
            <tr class="cancelled-summary-row">
                <td colspan="14" style="text-align:center">❌ الطلبات الملغية: <?= $cancelledCount ?> طلب ملغي — لا تُحتسب في إجمالي الإيرادات أدناه</td>
            </tr>
            <?php endif; ?>
            <tr class="grand-row">
                <td colspan="10" style="text-align:center">الإجمالي الكلي للطلبات المدفوعة — <?= $paidOrdersCount ?> طلب مدفوع</td>
                <td class="num"><?= number_format($totalBeforeDisc, 2) ?></td>
                <td class="num"><?= number_format($totalDiscount, 2) ?></td>
                <td class="num"><?= number_format($totalNet, 2) ?> ريال</td>
                <td></td>
            </tr>

        <?php else: ?>
            <!-- ═══ DETAILED MODE ═══ -->
            <tr>
                <td colspan="16" class="title"><?= htmlspecialchars($restName) ?> — كشف يومي تفصيلي</td>
            </tr>
            <tr>
                <td colspan="16" class="subtitle">الفترة: <?= $from ?> إلى <?= $to ?> | إجمالي الطلبات: <?= count($orders) ?> (مدفوع: <?= $paidOrdersCount ?> | ملغي: <?= $cancelledCount ?> | مسترجع: <?= $refundedCount ?> | أرسل للكاشير: <?= $pendingActiveCount ?>) | صافي الإيرادات: <?= number_format($totalNet, 2) ?> ريال</td>
            </tr>
            <tr>
                <th class="hdr">رقم الطلب</th>
                <th class="hdr">الصنف</th>
                <th class="hdr">الكمية</th>
                <th class="hdr">سعر الوحدة</th>
                <th class="hdr">إجمالي الصنف</th>
                <th class="hdr">إجمالي الطلب</th>
                <th class="hdr">الخصم</th>
                <th class="hdr">الصافي</th>
                <th class="hdr">نوع المبيعات</th>
                <th class="hdr">المرجع</th>
                <th class="hdr">الويتر</th>
                <th class="hdr">مؤكد الدفع</th>
                <th class="hdr">الوقت</th>
                <th class="hdr">طريقة الدفع</th>
                <th class="hdr">حالة الدفع</th>
                <th class="hdr">الملاحظات</th>
            </tr>
            <?php foreach ($orders as $idx => $o):
                $isCancelled = ($o['status'] === 'cancelled');
                $beforeDisc = (float) $o['total'] + (float) ($o['manual_discount'] ?? 0);
                $net = (float) $o['total'] - (float) ($o['refund_amount'] ?? 0);
                $items = $o['items'] ?? [];
                $itemCount = count($items);
                $mergeRows = max($itemCount, 1);
                ?>
                <!-- Order separator -->
                <?php if ($idx > 0): ?>
                    <tr>
                        <td colspan="16" class="sep"></td>
                    </tr>
                <?php endif; ?>

                <!-- First item row (with order info) -->
                <?php if ($itemCount > 0): ?>
                    <!-- Item rows -->
                    <?php foreach ($items as $iIdx => $item): ?>
                        <tr class="<?= $isCancelled ? 'cancelled-row' : ($iIdx % 2 === 0 ? 'item-row' : 'alt-row') ?>">
                            <?php if ($iIdx === 0): ?>
                                <td class="num <?= $isCancelled ? '' : 'order-hdr' ?>" rowspan="<?= $mergeRows + 1 ?>" style="<?= $isCancelled ? 'color:#c0392b;font-weight:bold' : '' ?>"><?= $o['order_number'] ?></td>
                            <?php endif; ?>
                            <td><?= !empty($item['item_number']) ? '(' . htmlspecialchars($item['item_number']) . ') ' : '' ?><?= htmlspecialchars($item['item_name_ar']) ?>
                            </td>
                            <td class="num"><?= (int) $item['quantity'] ?></td>
                            <td class="<?= $isCancelled ? 'muted' : 'money' ?>"><?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td class="<?= $isCancelled ? 'muted' : 'money' ?>"><?= number_format((float) $item['subtotal'], 2) ?></td>
                            <?php if ($iIdx === 0): ?>
                                <td class="money" rowspan="<?= $mergeRows + 1 ?>"><?= number_format($beforeDisc, 2) ?></td>
                                <td class="discount" rowspan="<?= $mergeRows + 1 ?>">
                                    <?= number_format((float) ($o['manual_discount'] ?? 0), 2) ?>
                                </td>
                                <td class="net" rowspan="<?= $mergeRows + 1 ?>"><?= number_format($net, 2) ?></td>
                                <td class="num" rowspan="<?= $mergeRows + 1 ?>">
                                    <?php
                                    $ct = $o['customer_type'] ?? 'normal';
                                    if ($ct === 'room')
                                        echo 'مبيعات خارجي (غرفة)';
                                    elseif ($ct === 'staff')
                                        echo 'موظف';
                                    else
                                        echo 'عادي';
                                    ?>
                                </td>
                                <td class="num" rowspan="<?= $mergeRows + 1 ?>"><?= htmlspecialchars(($o['customer_ref'] ?? '-') . (!empty($o['guest_name']) ? ' - ' . $o['guest_name'] : '')) ?></td>
                                <td rowspan="<?= $mergeRows + 1 ?>"><?= htmlspecialchars($o['waiter_name'] ?? '-') ?></td>
                                <td rowspan="<?= $mergeRows + 1 ?>"><?= htmlspecialchars($o['cashier_name'] ?? '-') ?></td>
                                <td class="num" rowspan="<?= $mergeRows + 1 ?>"><?= date('h:i A', strtotime($o['created_at'])) ?></td>
                                <td class="num" rowspan="<?= $mergeRows + 1 ?>">
                                    <?php if ($o['payment_method'] === 'cash'): ?>
                                        كاش
                                    <?php elseif ($o['payment_method'] === 'wallet'): ?>
                                        محفظة:
                                        <?= htmlspecialchars($o['wallet_name'] ?? 'رقمية') ?>
                                        <?= !empty($o['payment_reference']) ? ' (مرجع: ' . htmlspecialchars($o['payment_reference']) . ')' : '' ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="num" rowspan="<?= $mergeRows + 1 ?>">
                                    <?= (int) ($o['is_payment_modified'] ?? 0) === 1 ? 'معدّل' : 'غير معدل' ?>
                                </td>
                                <td rowspan="<?= $mergeRows + 1 ?>" style="white-space:normal;max-width:200px">
                                    <?= htmlspecialchars($o['notes'] ?? '') ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="<?= $isCancelled ? 'cancelled-row' : 'item-row' ?>">
                        <td class="num order-hdr" rowspan="2"><?= $o['order_number'] ?></td>
                        <td colspan="4" class="muted">لا توجد أصناف</td>
                        <td class="money" rowspan="2"><?= number_format($beforeDisc, 2) ?></td>
                        <td class="discount" rowspan="2"><?= number_format((float) ($o['manual_discount'] ?? 0), 2) ?></td>
                        <td class="net" rowspan="2"><?= number_format($net, 2) ?></td>
                        <td class="num" rowspan="2">
                            <?php
                            $ct = $o['customer_type'] ?? 'normal';
                            if ($ct === 'room')
                                echo 'مبيعات خارجي (غرفة)';
                            elseif ($ct === 'staff')
                                echo 'موظف';
                            else
                                echo 'عادي';
                            ?>
                        </td>
                        <td class="num" rowspan="2"><?= htmlspecialchars(($o['customer_ref'] ?? '-') . (!empty($o['guest_name']) ? ' - ' . $o['guest_name'] : '')) ?></td>
                        <td rowspan="2"><?= htmlspecialchars($o['waiter_name'] ?? '-') ?></td>
                        <td rowspan="2"><?= htmlspecialchars($o['cashier_name'] ?? '-') ?></td>
                        <td class="num" rowspan="2"><?= date('h:i A', strtotime($o['created_at'])) ?></td>
                        <td class="num" rowspan="2">
                            <?php if ($o['payment_method'] === 'cash'): ?>
                                كاش
                            <?php elseif ($o['payment_method'] === 'wallet'): ?>
                                محفظة:
                                <?= htmlspecialchars($o['wallet_name'] ?? 'رقمية') ?>
                                <?= !empty($o['payment_reference']) ? ' (مرجع: ' . htmlspecialchars($o['payment_reference']) . ')' : '' ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="num" rowspan="2">
                            <?= (int) ($o['is_payment_modified'] ?? 0) === 1 ? 'معدّل' : 'غير معدل' ?>
                        </td>
                        <td rowspan="2" style="white-space:normal;max-width:200px"><?= htmlspecialchars($o['notes'] ?? '') ?></td>
                    </tr>
                <?php endif; ?>
                <!-- Order total row -->
                <tr class="total-row">
                    <td colspan="3" style="text-align:right;padding-right:10px">
                        الإجمالي — <?= $itemCount ?> صنف
                    </td>
                    <td class="net"><?= number_format(array_sum(array_column($items, 'subtotal')), 2) ?> ريال</td>
                </tr>
            <?php endforeach; ?>

            <!-- Grand total -->
            <tr>
                <td colspan="16" class="sep"></td>
            </tr>
            <?php if ($cancelledCount > 0): ?>
            <tr class="cancelled-summary-row">
                <td colspan="16" style="text-align:center">❌ الطلبات الملغية: <?= $cancelledCount ?> طلب ملغي — موضحة بالأحمر ولا تُحتسب في الإيرادات</td>
            </tr>
            <?php endif; ?>
            <tr class="grand-row">
                <td colspan="5" style="text-align:center;font-size:13pt">الإجمالي الكلي للطلبات المدفوعة — <?= $paidOrdersCount ?> طلب مدفوع</td>
                <td style="text-align:center;font-size:13pt"><?= number_format($totalBeforeDisc, 2) ?> ريال</td>
                <td style="text-align:center"><?= number_format($totalDiscount, 2) ?> ريال</td>
                <td style="text-align:center;font-size:13pt"><?= number_format($totalNet, 2) ?> ريال</td>
                <td colspan="8"></td>
            </tr>
        <?php endif; ?>
    </table>
</body>

</html>
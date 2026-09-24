<?php
/**
 * api/print_helper.php
 * Helper to trigger print jobs. In production mode (enable_department_printing=ON),
 * enqueues locally instead of forwarding to the external print server.
 */
require_once __DIR__ . '/../config/db.php';

function triggerPrint($action, $orderId, $printerId = null, $forcePrintAll = false) {
  $currentUser = getCurrentUser();
  $settings = getSettings();
  $db = getDB();

  $enableDeptPrinting = ($settings['enable_department_printing'] ?? '0') === '1';

  // Check if auto-print is enabled for this action (skip check if forcePrintAll is true)
  if (!$forcePrintAll) {
    if ($action === 'kitchen' && ($settings['auto_print_kitchen'] ?? '0') !== '1') return true;
    if ($action === 'receipt' && ($settings['auto_print_receipt'] ?? '0') !== '1') return true;
  }

  // ─── Production mode: enqueue locally ──────────────────────────────────────
  if ($enableDeptPrinting) {
    if ($action === 'kitchen') {
      require_once __DIR__ . '/print_engine.php';
      enqueueOrderByDepartment($db, (int)$orderId, 'helper');
    } else {
      // Receipt enqueue with pre-built bytes
      $oStmt = $db->prepare("SELECT order_number, total, manual_discount, refund_amount, payment_method, created_at, table_number, notes, direct_name, waiter_name, cashier_name FROM orders WHERE id=?");
      $oStmt->execute([$orderId]);
      $order = $oStmt->fetch(PDO::FETCH_ASSOC);
      if (!$order) return false;

      $iStmt = $db->prepare("SELECT item_name_ar AS name, item_name_en AS name_en, quantity AS qty, unit_price AS price, subtotal AS total, notes FROM order_items WHERE order_id=? AND status != 'rejected'");
      $iStmt->execute([$orderId]);
      $items = $iStmt->fetchAll(PDO::FETCH_ASSOC);

      $restName = trim(preg_replace('/[^\x20-\x7E]/', '', $settings['restaurant_name'] ?? '')) ?: 'Restaurant';
      $discount = (float)($order['manual_discount'] ?? 0);
      $subtotal = (float)$order['total'] + $discount;
      $netTotal = (float)$order['total'] - (float)($order['refund_amount'] ?? 0);

      require_once __DIR__ . '/print_direct_lib.php';
      $bytes = buildReceiptESC($restName, $order, $items, $discount, $subtotal, $netTotal);

      $stmt = $db->prepare("INSERT INTO print_queue (order_id, printer_type, esc_data, request_id, status, created_at) VALUES (?, 'receipt', ?, ?, 'pending', NOW())");
      $stmt->execute([$orderId, base64_encode($bytes), 'receipt-' . $orderId . '-' . bin2hex(random_bytes(6))]);
    }
    return true;
  }

  // ─── Legacy mode: forward to external print server ─────────────────────────
  $serverUrl = $settings['print_server_url'] ?? '';
  if (empty($serverUrl)) {
    error_log("Print trigger failed: print_server_url is not configured in settings.");
    return false;
  }

  $serverKey = $settings['print_server_key'] ?? '';

  $endpointMap = [
    'receipt' => '/print/receipt',
    'kitchen' => '/print/kitchen',
    'test'    => '/print/test'
  ];

  if (!isset($endpointMap[$action])) return false;

  $url = rtrim($serverUrl, '/') . $endpointMap[$action];

  $stmt = $db->prepare("
    SELECT o.order_number, o.total, o.manual_discount, o.refund_amount, 
           o.payment_method, o.created_at, o.table_number, o.notes,
           w.name AS waiter_name, c.name AS cashier_name
    FROM orders o 
    LEFT JOIN users w ON o.waiter_id = w.id
    LEFT JOIN users c ON o.cashier_id = c.id
    WHERE o.id = ?
  ");
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$order) return false;

  $iStmt = $db->prepare("
    SELECT id, item_name_ar AS name, quantity AS qty, unit_price AS price, subtotal AS total, notes, is_printed
    FROM order_items 
    WHERE order_id = ? AND status != 'rejected'
    ORDER BY id
  ");
  $iStmt->execute([$orderId]);
  $itemsToPrint = $iStmt->fetchAll(PDO::FETCH_ASSOC);

  if ($action === 'kitchen' && !$forcePrintAll) {
      $hasNewItems = false;
      foreach ($itemsToPrint as $item) {
          if (empty($item['is_printed'])) { $hasNewItems = true; break; }
      }
      if (!$hasNewItems) return true;
  }

  $order['items'] = $itemsToPrint;

  $payload = [
    'orderId' => $orderId,
    'type'    => $action,
    'printerId' => $printerId,
    'isAddition' => ($action === 'kitchen' && !$forcePrintAll),
    'meta'    => [
      'number' => $order['order_number'],
      'table'  => $order['table_number'] ?: '-',
      'waiter' => $order['waiter_name'] ?: '-',
      'cashier'=> $order['cashier_name'] ?: '-',
      'time'   => date('Y-m-d H:i:s', strtotime($order['created_at'])),
      'notes'  => $order['notes']
    ],
    'items'   => array_map(function($i) {
        return [
          'name'  => $i['name'],
          'qty'   => (int)$i['qty'],
          'price' => (float)$i['price'],
          'total' => (float)$i['total'],
          'notes' => $i['notes'],
          'is_printed' => (int)($i['is_printed'] ?? 0),
        ];
    }, $order['items']),
    'totals'  => [
      'subtotal' => (float)$order['total'] + (float)($order['manual_discount'] ?? 0),
      'discount' => (float)($order['manual_discount'] ?? 0),
      'total'    => (float)$order['total'] - (float)($order['refund_amount'] ?? 0)
    ]
  ];

  $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $json,
    CURLOPT_TIMEOUT        => (int)($settings['print_timeout_sec'] ?? 5),
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'X-Api-Key: ' . $serverKey
    ]
  ]);

  $res = curl_exec($ch);
  $err = curl_error($ch);

  if ($res !== false && !empty($itemsToPrint)) {
      $itemIds = array_column($itemsToPrint, 'id');
      if (!empty($itemIds)) {
          $marks = implode(',', array_fill(0, count($itemIds), '?'));
          $db->prepare("UPDATE order_items SET is_printed = 1 WHERE id IN ($marks)")->execute($itemIds);
      }
  }

  $logStmt = $db->prepare("INSERT INTO print_logs (user_id, order_id, printer_type, status, error_message) VALUES (?,?,?,?,?)");
  $logStmt->execute([
    $currentUser ? $currentUser['id'] : 0,
    $orderId,
    'ip',
    $res !== false ? 'success' : 'failed',
    $err ?: ($res === false ? 'Timeout or connection refused' : null)
  ]);

  return $res !== false;
}
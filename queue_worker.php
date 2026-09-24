<?php
/**
 * queue_worker.php — Background Printing Worker
 *
 * Single execution path: claims jobs atomically, prints via pre-built ESC/POS data,
 * enforces idempotency via request_id UNIQUE constraint.
 *
 * Usage:
 *   php queue_worker.php              (single run)
 *   php queue_worker.php --daemon     (continuous loop)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/api/print_engine.php';

$isDaemon = in_array('--daemon', $argv ?? []);

echo "[" . date('Y-m-d H:i:s') . "] Queue Worker started (daemon: " . ($isDaemon ? 'yes' : 'no') . ")\n";

do {
    try {
        $db = getDB();

        // ─── Atomic Claim ─────────────────────────────────────────────────────
        $claimedId = null;
        $db->beginTransaction();
        try {
            $next = $db->query("SELECT id FROM print_queue WHERE status='pending' ORDER BY id ASC LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            if ($next) {
                $up = $db->prepare("UPDATE print_queue SET status='printing', started_at=NOW() WHERE id=? AND status='pending'");
                $up->execute([$next['id']]);
                if ($up->rowCount() > 0) {
                    $claimedId = (int)$next['id'];
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        if (!$claimedId) {
            if ($isDaemon) {
                sleep(5);
                continue;
            }
            echo "[" . date('Y-m-d H:i:s') . "] No pending jobs.\n";
            break;
        }

        // ─── Fetch claimed job ───────────────────────────────────────────────
        $jStmt = $db->prepare("
            SELECT pq.*, o.order_number, o.table_number, o.created_at, o.notes,
                   w.name AS waiter_name
            FROM print_queue pq
            JOIN orders o ON pq.order_id = o.id
            LEFT JOIN users w ON o.waiter_id = w.id
            WHERE pq.id=?
        ");
        $jStmt->execute([$claimedId]);
        $job = $jStmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            echo "[" . date('Y-m-d H:i:s') . "] Job #{$claimedId} not found after claim, skipping.\n";
            continue;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Processing job #{$job['id']} (Order #{$job['order_number']})...\n";

        // ─── Idempotency Check ────────────────────────────────────────────────
        if (!empty($job['request_id'])) {
            $dup = $db->prepare("SELECT id FROM print_queue WHERE request_id=? AND status='success' AND id != ? LIMIT 1");
            $dup->execute([$job['request_id'], $job['id']]);
            if ($dup->fetch()) {
                echo "  ⏭ Skipping (request_id {$job['request_id']} already succeeded elsewhere)\n";
                $db->prepare("UPDATE print_queue SET status='success', printed_at=NOW() WHERE id=?")->execute([$job['id']]);
                continue;
            }
        }

        // ─── Execute Print (esc_data only — no rebuild fallback) ──────────────
        $result = null;

        try {
            if (empty($job['esc_data'])) {
                throw new Exception('Missing esc_data — production safety: rebuild paths are disabled. Re-enqueue the print job.');
            }

            $bytes = base64_decode($job['esc_data'], true);
            if ($bytes === false) {
                throw new Exception('Invalid base64 esc_data');
            }

            $ip = $job['printer_ip'] ?? '';
            if ($ip) {
                $parts = explode(':', $ip);
                $host = $parts[0];
                $port = (int)($parts[1] ?? 9100);
                $result = sendToNetworkPrinter($host, $port, $bytes);
            } else {
                $printerType = $job['printer_type'] ?? 'kitchen';
                $windowsName = getPrinterName($db, $printerType);
                if ($windowsName) {
                    $result = rawPrint($windowsName, $bytes);
                } else {
                    $result = ['ok' => false, 'msg' => 'No printer ip or windows name configured'];
                }
            }
        } catch (Exception $execEx) {
            $result = ['ok' => false, 'msg' => $execEx->getMessage()];
        }

        // ─── Update Status ────────────────────────────────────────────────────
        if ($result && ($result['ok'] ?? false)) {
            $db->prepare("UPDATE print_queue SET printed_at=NOW(), status='success', error_message=NULL WHERE id=?")->execute([$job['id']]);
            $db->prepare("UPDATE orders SET kitchen_print_count = kitchen_print_count + 1 WHERE id = ?")->execute([$job['order_id']]);
            $db->prepare("UPDATE order_items SET is_printed = 1 WHERE order_id = ? AND is_printed = 0")->execute([$job['order_id']]);
            echo "  ✅ Printed successfully\n";
        } else {
            $errMsg = ($result['msg'] ?? 'Unknown error');
            $db->prepare("UPDATE print_queue SET status='failed', attempts=attempts+1, error_message=? WHERE id=?")->execute([$errMsg, $job['id']]);
            echo "  ❌ Failed: {$errMsg}\n";
        }

        // Log
        try {
            $printerType = $job['printer_type'] ?? ($job['department_id'] ? 'department' : 'kitchen');
            $logStmt = $db->prepare("INSERT INTO print_logs (user_id, order_id, printer_type, status, attempts, error_message) VALUES (0, ?, ?, ?, ?, ?)");
            $logStmt->execute([$job['order_id'], $printerType, ($result && $result['ok']) ? 'success' : 'failed', $job['attempts'] ?? 0, ($result && $result['ok']) ? null : ($result['msg'] ?? 'Unknown')]);
        } catch (Exception $logEx) {
            // Non-critical
        }

    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
        if ($isDaemon) sleep(10);
    }

} while ($isDaemon);

echo "[" . date('Y-m-d H:i:s') . "] Queue Worker stopped.\n";

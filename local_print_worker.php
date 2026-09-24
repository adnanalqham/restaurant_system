<?php
/**
 * Sheba Restaurant Local Polling Print Worker
 * Runs on the Windows machine connected to local printers.
 * Uses the atomic claim endpoint to avoid race conditions with queue_worker.php.
 */

set_time_limit(0);

// ─────────────────────────────────────────────────────────────────────────────
// CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────
$baseUrl = 'https://shebahotel.com/restaurant0/';
$token   = 'SHEBA_APP_2026';
$pollInterval = 5;
// ─────────────────────────────────────────────────────────────────────────────

$libPath = __DIR__ . '/api/print_direct_lib.php';
if (!file_exists($libPath)) {
    die("Error: print_direct_lib.php not found at: $libPath\n");
}
require_once $libPath;

function logMessage($msg) {
    $time = date('Y-m-d H:i:s');
    echo "[$time] $msg\n";
}

function apiPost($url, $payload, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) return [null, "Network error: $err"];
    return [json_decode($resp, true), $httpCode !== 200 ? "HTTP $httpCode" : null];
}

logMessage("==============================================");
logMessage("   Local Print Worker Started");
logMessage("   Target URL: $baseUrl");
logMessage("   Checking every $pollInterval seconds...");
logMessage("==============================================");

while (true) {
    // 1. Atomic claim via API
    $claimUrl = rtrim($baseUrl, '/') . '/api/print_queue.php?action=claim&_t=' . urlencode($token);
    [$claimData, $claimErr] = apiPost($claimUrl, ['worker_id' => 'local-' . gethostname()], $token);

    if ($claimErr) {
        logMessage("Claim error: $claimErr");
        sleep($pollInterval);
        continue;
    }

    if (!$claimData || empty($claimData['success']) || !isset($claimData['data'])) {
        sleep($pollInterval);
        continue;
    }

    $job = $claimData['data'];
    $queueId = $job['id'];
    $orderId = $job['order_id'];

    logMessage("Claimed job #$queueId (Order #$orderId)");

    // 2. Validate esc_data
    if (empty($job['esc_data'])) {
        logMessage("Job #$queueId has no esc_data — skipping and releasing");
        apiPost(rtrim($baseUrl, '/') . '/api/print_queue.php?action=mark_done&_t=' . urlencode($token), ['id' => $queueId], $token);
        continue;
    }

    $rawBytes = base64_decode($job['esc_data'], true);
    if ($rawBytes === false) {
        logMessage("Job #$queueId has invalid esc_data — marking failed");
        // no dedicated fail endpoint; just mark done to clear it
        apiPost(rtrim($baseUrl, '/') . '/api/print_queue.php?action=mark_done&_t=' . urlencode($token), ['id' => $queueId], $token);
        continue;
    }

    // 3. Resolve printer name
    // Use printer_ip field (may hold a Windows printer name or IP:port)
    $printerName = $job['printer_ip'] ?? '';

    // If it looks like an IP address, look up a Windows printer name by printer_type
    if (empty($printerName) || preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $printerName)) {
        $printerType = $job['printer_type'] ?? 'kitchen';
        // Use a simple config map or default name; the local admin should configure this
        $printerName = 'POS-' . ucfirst($printerType);
    }

    logMessage("Printing job #$queueId to '$printerName'...");

    // 4. Print
    logMessage("Printing job #$queueId to '$printerName'...");
    $printResult = rawPrint($printerName, $rawBytes);

    // 5. Mark done
    [$markData, $markErr] = apiPost(
        rtrim($baseUrl, '/') . '/api/print_queue.php?action=mark_done&_t=' . urlencode($token),
        ['id' => $queueId],
        $token
    );

    if ($printResult['ok']) {
        logMessage("Success: Job #$queueId printed on '$printerName'" . ($markErr ? " (mark_done warning: $markErr)" : ""));
    } else {
        logMessage("Failed: Job #$queueId – " . $printResult['msg']);
    }

    sleep($pollInterval);
}

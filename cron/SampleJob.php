#!/usr/bin/env php
<?php

/**
 * sample_job.php
 *
 * This is a sample cron job file to demonstrate how cron scripts work.
 *
 * WHAT IS A CRON JOB?
 * --------------------
 * A cron job is a PHP script that runs automatically on a schedule
 * (e.g. every day at midnight). It is NOT accessible via HTTP —
 * it runs directly via the PHP CLI (command line).
 *
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────

// Load config (adjust path based on where your cron folder is)
$config = require __DIR__ . '/../config.php';

// Set timezone from config
date_default_timezone_set($config['timezone'] ?? 'UTC');

// Connect to database
$db   = $config['databases'][$config['db_default']];
$conn = new mysqli(
    $db['host']     ?? 'localhost',
    $db['user']     ?? '',
    $db['password'] ?? '',
    $db['name']     ?? '',
    $db['port']     ?? 3306,
);

if ($conn->connect_error) {
    echo "[ERROR] DB connection failed: " . $conn->connect_error . "\n";
    exit(1);
}

$conn->set_charset($db['charset'] ?? 'utf8mb4');

// Optional: load services you need
// require_once __DIR__ . '/../services/LogService.php';
// require_once __DIR__ . '/../services/MailService.php';

// ── Job starts here ───────────────────────────────────────────────────────────

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "[{$timestamp}] Job started.\n";

// ── Example Task 1: Mark expired records ─────────────────────────────────────

try {
    // Find records that should be marked expired
    $stmt = $conn->prepare("
        SELECT id FROM sample_table
        WHERE expiry_date < CURDATE()
          AND status   <> 'expired'
          AND deleted   = 0
    ");
    $stmt->execute();
    $expiredRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $expiredCount = 0;

    foreach ($expiredRecords as $record) {
        $stmt = $conn->prepare("UPDATE sample_table SET status = 'expired' WHERE id = ?");
        $stmt->bind_param('i', $record['id']);
        $stmt->execute();
        $stmt->close();
        $expiredCount++;
    }

    echo "[{$timestamp}] Marked expired: {$expiredCount} record(s).\n";
} catch (Throwable $e) {
    echo "[ERROR] Task 1 failed: " . $e->getMessage() . "\n";
}

// ── Example Task 2: Cleanup old soft-deleted records ─────────────────────────

try {
    // Permanently delete records soft-deleted more than 90 days ago
    $stmt = $conn->prepare("
        DELETE FROM sample_table
        WHERE deleted    = 1
          AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletedCount = $stmt->affected_rows;
    $stmt->close();

    echo "[{$timestamp}] Cleaned up: {$deletedCount} old record(s).\n";
} catch (Throwable $e) {
    echo "[ERROR] Task 2 failed: " . $e->getMessage() . "\n";
}

// ── Example Task 3: Send summary email ───────────────────────────────────────

// Uncomment if you want to send a daily summary email
// try {
//     require_once __DIR__ . '/../services/MailService.php';
//
//     MailService::send(
//         to:      'admin@yourdomain.com',
//         subject: 'Daily Job Summary - ' . date('Y-m-d'),
//         body:    "<p>Expired: {$expiredCount} | Cleaned: {$deletedCount}</p>",
//         toName:  'Admin',
//     );
//
//     echo "[{$timestamp}] Summary email sent.\n";
//
// } catch (Throwable $e) {
//     echo "[ERROR] Email failed: " . $e->getMessage() . "\n";
// }

// ── Wrap up ───────────────────────────────────────────────────────────────────

$conn->close();

$elapsed = round(microtime(true) - $startTime, 3);
echo "[{$timestamp}] Job completed in {$elapsed}s.\n";

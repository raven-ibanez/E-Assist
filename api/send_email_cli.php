<?php
/*
 * ============================================================
 *  api/send_email_cli.php — BACKGROUND EMAIL SENDER
 * ============================================================
 *  This script is called in the background to send PHPMailer emails
 *  without blocking the main HTTP user interface request.
 * ============================================================
 */

// Prevent web access (CLI only)
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Read CLI arguments
$enrollment_id = $argv[1] ?? '';
$decision      = $argv[2] ?? '';
$reason        = $argv[3] ?? '';
$triggeredBy   = $argv[4] ?? '';
$admin_id      = $argv[5] ?? null;

if (!$enrollment_id || !$decision) {
    die("Missing required arguments.\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/email_config.php';

// Send the status email
$result = sendStatusEmail($conn, $enrollment_id, $decision, $reason, $triggeredBy);

// Fetch student/parent name for logs
$stmtName = $conn->prepare("SELECT first_name, last_name, middle_name, suffix FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.id = ?");
$stmtName->bind_param("i", $enrollment_id);
$stmtName->execute();
$nameRes = $stmtName->get_result()->fetch_assoc();
$studentName = 'Unknown';
if ($nameRes) {
    $suffixStr = !empty($nameRes['suffix']) ? ' ' . $nameRes['suffix'] : '';
    $middleStr = !empty($nameRes['middle_name']) ? ' ' . $nameRes['middle_name'] : '';
    $studentName = $nameRes['last_name'] . $suffixStr . ', ' . $nameRes['first_name'] . $middleStr;
}

// Log results to system logs
if ($admin_id) {
    if ($result['success']) {
        $logDetails = "Auto-sent status update email ({$decision}) by {$triggeredBy}. " . $result['message'];
        $logStmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, target_id, target_name, details) VALUES (?, 'Email Sent', ?, ?, ?)");
        $logStmt->bind_param("iiss", $admin_id, $enrollment_id, $studentName, $logDetails);
        $logStmt->execute();
    } else {
        $logDetails = "Failed to send status update email ({$decision}) by {$triggeredBy}. Error: " . $result['message'];
        $logStmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, target_id, target_name, details) VALUES (?, 'Email Error', ?, ?, ?)");
        $logStmt->bind_param("iiss", $admin_id, $enrollment_id, $studentName, $logDetails);
        $logStmt->execute();
    }
}
?>

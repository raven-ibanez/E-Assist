<?php
/*
 * ============================================================
 *  api/send_email.php — STANDALONE EMAIL API ENDPOINT
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Provides a dedicated endpoint for sending/resending
 *    enrollment status emails.
 *  - Also includes a test action to verify SMTP connectivity.
 *
 *  HOW IT'S CALLED:
 *    api/send_email.php?action=send_status_email   (POST)
 *    api/send_email.php?action=test_email           (POST)
 * ============================================================
 */

require_once '../db.php';
require_once 'email_config.php';

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);


// =============================================================
//  ACTION: SEND STATUS EMAIL
//  - Sends (or resends) an enrollment status email.
//  - Requires: enrollment_id, decision, admin_id
//  - Optional: reason (for declined/dropped)
// =============================================================
if ($action === 'send_status_email') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $decision      = $data['decision'] ?? '';
    $admin_id      = $data['admin_id'] ?? '';
    $reason        = $data['reason'] ?? '';

    if (!$enrollment_id || !$decision || !$admin_id) {
        sendJSON(['error' => 'Enrollment ID, decision, and admin ID are required.'], 400);
    }

    if (!in_array($decision, ['approved', 'declined', 'approved_dtf', 'dropped'])) {
        sendJSON(['error' => 'Invalid decision type. Must be: approved, declined, approved_dtf, or dropped.'], 400);
    }

    // Send the email
    $result = sendStatusEmail($conn, $enrollment_id, $decision, $reason);

    if ($result['success']) {
        // Log the email send action
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

        // Use the existing logAction function from registrar.php context (defined in db.php scope)
        // Since we require db.php, we need to define logAction here too
        $logStmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, target_id, target_name, details) VALUES (?, ?, ?, ?, ?)");
        $logType = "Email Sent";
        $logDetails = "Sent {$decision} status email. " . $result['message'];
        $logStmt->bind_param("isiss", $admin_id, $logType, $enrollment_id, $studentName, $logDetails);
        $logStmt->execute();

        sendJSON([
            'message'    => $result['message'],
            'email_sent' => true
        ]);
    } else {
        sendJSON([
            'error'      => $result['message'],
            'email_sent' => false
        ], 500);
    }
}


// =============================================================
//  ACTION: TEST EMAIL
//  - Sends a test email to verify SMTP configuration.
//  - Requires: test_email (recipient address), admin_id
// =============================================================
if ($action === 'test_email') {
    $testEmail = $data['test_email'] ?? '';
    $admin_id  = $data['admin_id'] ?? '';

    if (!$testEmail) {
        sendJSON(['error' => 'A test email address is required.'], 400);
    }

    $subject = '🧪 SMTP Test — ' . SCHOOL_NAME;
    $body = wrapEmailTemplate(
        'SMTP Test',
        '#2563eb',
        '🧪',
        'SMTP Configuration Test',
        '<p style="font-size:15px; color:#333; line-height:1.7; text-align:center;">
            If you received this email, your SMTP settings are configured correctly!
        </p>
        <p style="font-size:13px; color:#6b7280; text-align:center; margin-top:12px;">
            Sent at: ' . date('Y-m-d H:i:s') . ' (server time)
        </p>'
    );

    $result = sendEnrollmentEmail($testEmail, 'Test Recipient', $subject, $body);

    if ($result['success']) {
        sendJSON(['message' => 'Test email sent successfully to ' . $testEmail, 'email_sent' => true]);
    } else {
        sendJSON(['error' => $result['message'], 'email_sent' => false], 500);
    }
}


// =============================================================
//  ACTION: GET EMAIL STATUS FOR ENROLLMENT
//  - Checks if an email was previously sent for this enrollment.
//  - Returns the latest email log entry.
// =============================================================
if ($action === 'email_status') {
    $enrollment_id = $_GET['enrollment_id'] ?? '';

    if (!$enrollment_id) {
        sendJSON(['error' => 'Enrollment ID is required.'], 400);
    }

    $stmt = $conn->prepare("
        SELECT action_type, details, created_at 
        FROM system_logs 
        WHERE target_id = ? AND action_type = 'Email Sent' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        sendJSON([
            'email_sent'  => true,
            'last_sent_at' => $result['created_at'],
            'details'     => $result['details']
        ]);
    } else {
        sendJSON([
            'email_sent'  => false,
            'last_sent_at' => null,
            'details'     => 'No email has been sent for this enrollment.'
        ]);
    }
}


// If no valid action matched
sendJSON(['error' => 'Invalid action'], 400);
?>

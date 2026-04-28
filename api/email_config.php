<?php
/*
 * ============================================================
 *  api/email_config.php — EMAIL CONFIGURATION & HELPERS
 * ============================================================
 */

// --- Load PHPMailer classes (no Composer needed) ---
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ============================================================
//  SMTP SETTINGS — CHANGE THESE TO YOUR OWN
// ============================================================
define('SMTP_HOST', 'smtp.gmail.com');          // Gmail SMTP server
define('SMTP_PORT', 587);                       // TLS port
define('SMTP_USER', 'cerenojames14@gmail.com');    // ← Your Gmail address
define('SMTP_PASS', 'ikdv rmlz bpad fgmh');        // ← Your Gmail App Password (16 chars)
define('SMTP_SECURE', 'tls');                   // Encryption type

// --- Sender Details ---
define('SENDER_NAME', 'Brother Sun Sister Moon Academy Inc.');
define('SENDER_EMAIL', SMTP_USER);              // Uses the same Gmail address

// --- School Info (used in email templates) ---
define('SCHOOL_NAME', 'Brother Sun Sister Moon Academy Inc.');
define('SCHOOL_ADDRESS', 'Quezon City, Metro Manila');
define('SCHOOL_CONTACT', 'For inquiries, please contact the Registrar\'s Office.');

/**
 *  sendEnrollmentEmail() — Core email sender
 */
function sendEnrollmentEmail($toEmail, $toName, $subject, $htmlBody)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully to ' . $toEmail];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Email failed: ' . $mail->ErrorInfo];
    }
}

/**
 *  wrapEmailTemplate() — Master HTML email wrapper
 */
function wrapEmailTemplate($title, $statusColor, $statusIcon, $statusText, $bodyContent)
{
    $schoolName = SCHOOL_NAME;
    $schoolAddr = SCHOOL_ADDRESS;
    $schoolContact = SCHOOL_CONTACT;
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6fa; font-family: 'Segoe UI', Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6fa; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #7b1113 0%, #a0151a 50%, #c9282d 100%); padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:700; letter-spacing:0.5px;">{$schoolName}</h1>
                            <p style="margin:6px 0 0; color:rgba(255,255,255,0.85); font-size:13px; letter-spacing:0.3px;">Enrollment Management System</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:{$statusColor}; padding:20px 40px; text-align:center;">
                            <span style="font-size:32px; display:block; margin-bottom:6px;">{$statusIcon}</span>
                            <h2 style="margin:0; color:#ffffff; font-size:18px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">{$statusText}</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 40px;">{$bodyContent}</td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8f9fa; padding:24px 40px; border-top:1px solid #e9ecef;">
                            <p style="margin:0 0 8px; font-size:12px; color:#6c757d; text-align:center;">{$schoolContact}</p>
                            <p style="margin:0 0 4px; font-size:12px; color:#6c757d; text-align:center;">{$schoolAddr}</p>
                            <p style="margin:0; font-size:11px; color:#adb5bd; text-align:center;">© {$year} {$schoolName}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function buildReceivedEmail($studentName)
{
    $body = <<<HTML
        <p style="font-size:15px; color:#333; line-height:1.7; margin:0 0 16px;">
            Dear Parent/Guardian,
        </p>
        <p style="font-size:15px; color:#333; line-height:1.7; margin:0 0 16px;">
            Application has been received, please check your Email for further updates for <strong>{$studentName}</strong>.
        </p>
HTML;
    return wrapEmailTemplate('Application Received — ' . SCHOOL_NAME, '#3b82f6', '📩', 'Application Received', $body);
}

function buildApplicationApprovedEmail($studentName, $gradeLevel, $schoolYear, $studentNo, $isDTF = false, $missingDocs = [])
{
    $dtfText = $isDTF ? " However, there are <strong style=\"color:#d97706;\">missing documents</strong> that need to be submitted." : ".";
    $docListHtml = '';
    if ($isDTF && !empty($missingDocs)) {
        $docItems = '';
        foreach ($missingDocs as $doc) {
            $docItems .= '<li style="margin:6px 0; font-size:14px;">' . htmlspecialchars($doc) . '</li>';
        }
        $docListHtml = "<div style='background:#fffbeb; padding:15px; border-left:4px solid #f59e0b; margin:15px 0;'><p style='margin:0 0 8px; font-weight:700;'>📄 Missing Documents</p><ul>{$docItems}</ul></div>";
    }
    $body = <<<HTML
        <p>Dear Parent/Guardian,</p>
        <p>We are pleased to inform you that the enrollment application for your child has been <strong style="color:#16a34a;">approved</strong>{$dtfText}</p>
        <table width="100%" style="background:#f0fdf4; border-radius:12px; padding:20px; margin:20px 0;">
            <tr><td><strong>Student:</strong> {$studentName}<br><strong>ID:</strong> {$studentNo}<br><strong>Grade:</strong> {$gradeLevel}<br><strong>Year:</strong> {$schoolYear}</td></tr>
        </table>
        {$docListHtml}
        <p>Please wait for the Cashier to review your payment status.</p>
HTML;
    return wrapEmailTemplate('Application Approved', '#16a34a', '✅', 'Application Approved', $body);
}

function buildPaymentReceivedEmail($studentName, $gradeLevel, $schoolYear)
{
    $body = <<<HTML
        <p>Dear Parent/Guardian,</p>
        <p>We are pleased to inform you that the payment for the enrollment of your child has been <strong style="color:#16a34a;">received</strong>.</p>
        <table width="100%" style="background:#f0fdf4; border-radius:12px; padding:20px; margin:20px 0;">
            <tr><td><strong>Student:</strong> {$studentName}<br><strong>Grade:</strong> {$gradeLevel}<br><strong>Year:</strong> {$schoolYear}</td></tr>
        </table>
        <p>Please wait for the Registrar to complete the application review.</p>
HTML;
    return wrapEmailTemplate('Payment Received', '#16a34a', '💳', 'Payment Received', $body);
}

function buildOfficiallyEnrolledEmail($studentName, $gradeLevel, $schoolYear, $studentNo, $isDTF = false, $missingDocs = [])
{
    $dtfText = $isDTF ? " However, there are <strong style=\"color:#d97706;\">missing documents</strong> that need to be submitted." : ".";
    $docListHtml = '';
    if ($isDTF && !empty($missingDocs)) {
        $docItems = '';
        foreach ($missingDocs as $doc) {
            $docItems .= '<li style="margin:6px 0; font-size:14px;">' . htmlspecialchars($doc) . '</li>';
        }
        $docListHtml = "<div style='background:#fffbeb; padding:15px; border-left:4px solid #f59e0b; margin:15px 0;'><p style='margin:0 0 8px; font-weight:700;'>📄 Missing Documents</p><ul>{$docItems}</ul></div>";
    }
    $body = <<<HTML
        <p>Dear Parent/Guardian,</p>
        <p>The enrollment application for your child has been <strong style="color:#16a34a;">approved</strong> and they are now <strong>officially enrolled</strong>{$dtfText}</p>
        <table width="100%" style="background:#f0fdf4; border-radius:12px; padding:20px; margin:20px 0;">
            <tr><td><strong>Student:</strong> {$studentName}<br><strong>ID:</strong> {$studentNo}<br><strong>Grade:</strong> {$gradeLevel}<br><strong>Year:</strong> {$schoolYear}</td></tr>
        </table>
        {$docListHtml}
HTML;
    return wrapEmailTemplate('Officially Enrolled', '#16a34a', '🎉', 'Officially Enrolled', $body);
}

function buildApplicationDeclinedEmail($studentName, $gradeLevel, $reason = '')
{
    $reasonHtml = $reason ? "<div style='background:#fef2f2; padding:15px; border-left:4px solid #dc2626; margin:15px 0;'><p style='margin:0; color:#dc2626;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p></div>" : "";
    $body = <<<HTML
        <p>Dear Parent/Guardian,</p>
        <p>We regret to inform you that the enrollment application for <strong>{$studentName}</strong> has been <strong style="color:#dc2626;">declined</strong>.</p>
        {$reasonHtml}
        <p>Please contact the Registrar's Office for further assistance.</p>
HTML;
    return wrapEmailTemplate('Application Denied', '#dc2626', '❌', 'Application Denied', $body);
}

function buildPaymentDeclinedEmail($studentName, $gradeLevel, $reason = '')
{
    $reasonHtml = $reason ? "<div style='background:#fef2f2; padding:15px; border-left:4px solid #dc2626; margin:15px 0;'><p style='margin:0; color:#dc2626;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p></div>" : "";
    $body = <<<HTML
        <p>Dear Parent/Guardian,</p>
        <p>We regret to inform you that the payment for the enrollment of <strong>{$studentName}</strong> has been <strong style="color:#dc2626;">declined</strong>.</p>
        {$reasonHtml}
        <p><strong>Please contact the school office to validate your payment details.</strong></p>
HTML;
    return wrapEmailTemplate('Payment Declined', '#dc2626', '❌', 'Payment Declined', $body);
}

function buildPaymentUpdatedEmail($studentName, $gradeLevel, $totalPaid, $totalAmount, $currentAmount = null)
{
    $balance = max(0, $totalAmount - $totalPaid);
    $totalPaidFmt = '₱' . number_format($totalPaid, 2);
    $balanceFmt = '₱' . number_format($balance, 2);

    $currentAmountHtml = '';
    if ($currentAmount !== null && $currentAmount > 0) {
        $currentAmountFmt = '₱' . number_format($currentAmount, 2);
        $currentAmountHtml = "<p style='font-size:15px; color:#333; margin-bottom:12px;'>We have received your payment of <strong>{$currentAmountFmt}</strong> for this installment.</p>";
    }

    $body = <<<HTML
        <p>Dear Parent/Guardian,</p>
        <p>There has been an update to the payment records for <strong>{$studentName}</strong>.</p>
        {$currentAmountHtml}
        <table width="100%" style="background:#f0fdf4; border-radius:12px; padding:20px; margin:20px 0;">
            <tr><td><strong>Total Paid:</strong> {$totalPaidFmt}<br><strong>Remaining Balance:</strong> {$balanceFmt}</td></tr>
        </table>
HTML;
    return wrapEmailTemplate('Payment Updated', '#10b981', '💳', 'Payment Updated', $body);
}

function getEnrollmentDataForEmail($conn, $enrollment_id)
{
    $stmt = $conn->prepare("
        SELECT 
            s.first_name, s.last_name, s.middle_name, s.suffix, s.student_no,
            s.psa_birth_cert, s.sf10_document, s.picture_2x2,
            p.first_name AS parent_first_name, p.last_name AS parent_last_name, 
            p.middle_name AS parent_middle_name, p.email AS parent_email,
            gl.name AS grade_level, sy.label AS school_year, e.documents_pending,
            COALESCE(pay.tuition_fee, 0) + COALESCE(pay.books_fee, 0) AS total_amount,
            COALESCE((SELECT SUM(amount_paid) FROM payment_transactions WHERE payment_id = pay.id), 0) AS total_paid,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN parents p ON s.parent_id = p.id
        JOIN grade_levels gl ON e.grade_level_id = gl.id
        JOIN school_years sy ON e.school_year_id = sy.id
        LEFT JOIN payments pay ON e.id = pay.enrollment_id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $suffixStr = !empty($result['suffix']) ? ' ' . $result['suffix'] : '';
        $middleStr = !empty($result['middle_name']) ? ' ' . $result['middle_name'] : '';
        $result['student_full_name'] = $result['last_name'] . $suffixStr . ', ' . $result['first_name'] . $middleStr;
        $result['parent_full_name'] = $result['parent_first_name'] . (!empty($result['parent_middle_name']) ? ' ' . $result['parent_middle_name'] : '') . ' ' . $result['parent_last_name'];
        $result['missing_docs'] = [];
        if (empty($result['psa_birth_cert']))
            $result['missing_docs'][] = 'PSA Birth Certificate';
        if (empty($result['sf10_document']))
            $result['missing_docs'][] = 'SF10 (New Students) or Previous Report Card';
        if (empty($result['picture_2x2']))
            $result['missing_docs'][] = '2×2 ID Picture';
    }
    return $result;
}

function sendStatusEmail($conn, $enrollment_id, $decision, $reason = '', $triggeredBy = '')
{
    $data = getEnrollmentDataForEmail($conn, $enrollment_id);
    if (!$data || empty($data['parent_email']))
        return ['success' => false, 'message' => 'Enrollment or email not found.'];

    $reg = $data['registrar_status'];
    $cash = $data['cashier_status'];
    $docs_pending = $data['documents_pending'];
    $subject = '';
    $htmlBody = '';

    switch ($decision) {
        case 'received':
            $subject = '📩 Application Received — ' . $data['student_full_name'];
            $htmlBody = buildReceivedEmail($data['student_full_name']);
            break;
        case 'approved':
        case 'approved_dtf':
            $isDTF = ($decision === 'approved_dtf' || $docs_pending);

            $sentCount = 0;
            $errors = [];

            // 1. Send specific review email
            if ($triggeredBy === 'Registrar') {
                $subject = ($isDTF ? "Application Approved (Missing Documents)" : "Application Approved") . " — " . $data['student_full_name'];
                $htmlBody = buildApplicationApprovedEmail($data['student_full_name'], $data['grade_level'], $data['school_year'], $data['student_no'], $isDTF, $data['missing_docs']);
                $res = sendEnrollmentEmail($data['parent_email'], $data['parent_full_name'], $subject, $htmlBody);
                if ($res['success'])
                    $sentCount++;
                else
                    $errors[] = $res['message'];
            } elseif ($triggeredBy === 'Cashier') {
                $subject = "Payment Received — " . $data['student_full_name'];
                $htmlBody = buildPaymentReceivedEmail($data['student_full_name'], $data['grade_level'], $data['school_year']);
                $res = sendEnrollmentEmail($data['parent_email'], $data['parent_full_name'], $subject, $htmlBody);
                if ($res['success'])
                    $sentCount++;
                else
                    $errors[] = $res['message'];
            }

            // 2. If BOTH are approved, send the Officially Enrolled email
            if ($reg === 'approved' && $cash === 'approved') {
                $subjectFinal = "Officially Enrolled — " . $data['student_full_name'];
                $htmlBodyFinal = buildOfficiallyEnrolledEmail($data['student_full_name'], $data['grade_level'], $data['school_year'], $data['student_no'], $isDTF, $data['missing_docs']);
                $resFinal = sendEnrollmentEmail($data['parent_email'], $data['parent_full_name'], $subjectFinal, $htmlBodyFinal);
                if ($resFinal['success'])
                    $sentCount++;
                else
                    $errors[] = $resFinal['message'];
            }

            if ($sentCount > 0) {
                return ['success' => true, 'message' => $sentCount . ' email(s) sent successfully.' . (count($errors) > 0 ? ' Errors: ' . implode(', ', $errors) : '')];
            } else {
                return ['success' => false, 'message' => 'No emails sent.' . (count($errors) > 0 ? ' Errors: ' . implode(', ', $errors) : '')];
            }
        // break; // Logic returns directly

        case 'declined':
            if ($triggeredBy === 'Cashier') {
                $subject = "Payment Declined";
                $htmlBody = buildPaymentDeclinedEmail($data['student_full_name'], $data['grade_level'], $reason);
            } else {
                $subject = "Application denied";
                $htmlBody = buildApplicationDeclinedEmail($data['student_full_name'], $data['grade_level'], $reason);
            }
            break;
        case 'payment_updated':
            $currentAmount = (is_numeric($reason) && $reason > 0) ? $reason : null;
            $subject = '💳 Payment Record Updated — ' . $data['student_full_name'];
            $htmlBody = buildPaymentUpdatedEmail($data['student_full_name'], $data['grade_level'], $data['total_paid'], $data['total_amount'], $currentAmount);
            break;
        case 'dropped':
            $subject = '🚫 Enrollment Status: Dropped — ' . $data['student_full_name'];
            $htmlBody = buildApplicationDeclinedEmail($data['student_full_name'], $data['grade_level'], $reason);
            break;
    }

    if ($subject && $htmlBody) {
        return sendEnrollmentEmail($data['parent_email'], $data['parent_full_name'], $subject, $htmlBody);
    }
    return ['success' => false, 'message' => 'Unknown decision or missing template.'];
}
?>
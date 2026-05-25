<?php
/*
 * ============================================================
 *  api/re-enroll.php — EXISTING STUDENT RE-ENROLLMENT
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Receives re-enrollment data for an existing student.
 *  - Validates that the student number exists.
 *  - Prevents duplicate enrollment (same student + grade + school year).
 *  - Creates a new enrollment record linked to the existing student.
 *  - Saves uploaded documents (report card, clearance).
 *  - Creates payment & payment transaction records.
 *  - Sends email notification via PHPMailer.
 *
 *  HOW IT'S CALLED:
 *    The existing student enrollment form (enroll-existing.html)
 *    sends a POST request with form data using apiPostForm().
 * ============================================================
 */

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Invalid request method.'], 400);
}

// --- Read form fields ---
$student_no       = $_POST['student_no'] ?? '';
$grade_level_id   = $_POST['grade_level_id'] ?? '';
$session_id       = $_POST['session_id'] ?? '';
$school_year_id   = $_POST['school_year_id'] ?? '';
$payment_method_id = $_POST['payment_method_id'] ?? '';
$payment_mode     = $_POST['payment_mode'] ?? 'Full Payment';
$payment_mode_id  = $_POST['payment_mode_id'] ?? null;
$months_count     = !empty($_POST['months_count']) ? intval($_POST['months_count']) : null;
$tuition_fee      = $_POST['tuition_fee'] ?? null;
$books_fee        = $_POST['books_fee'] ?? null;
$reference_number = $_POST['reference_number'] ?? '';

// --- Validate required fields ---
if (!$student_no || !$grade_level_id || !$session_id || !$school_year_id || !$payment_method_id) {
    sendJSON(['error' => 'All required fields must be filled.'], 400);
}

// --- Look up the student ---
$stmtLookup = $conn->prepare("SELECT id, first_name, last_name, middle_name, suffix, parent_id FROM students WHERE student_no = ? AND status = 'active' LIMIT 1");
$stmtLookup->bind_param("s", $student_no);
$stmtLookup->execute();
$student = $stmtLookup->get_result()->fetch_assoc();

if (!$student) {
    sendJSON(['error' => 'Student does not exist.'], 404);
}

$studentId = $student['id'];

// --- Prevent duplicate enrollment (same student + grade level + school year) ---
$stmtDup = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND grade_level_id = ? AND school_year_id = ? LIMIT 1");
$stmtDup->bind_param("iii", $studentId, $grade_level_id, $school_year_id);
$stmtDup->execute();
$existing = $stmtDup->get_result()->fetch_assoc();

if ($existing) {
    sendJSON(['error' => 'This student is already enrolled in the selected grade level and school year.'], 400);
}

// --- File upload setup ---
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Report Card (required for returning students)
$report_card_path = null;
if (isset($_FILES['report_card']) && $_FILES['report_card']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_reportcard_' . basename($_FILES['report_card']['name']);
    $report_card_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['report_card']['tmp_name'], $uploadDir . $filename);
}

// Accomplished Clearance (required for returning students)
$clearance_path = null;
if (isset($_FILES['clearance']) && $_FILES['clearance']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_clearance_' . basename($_FILES['clearance']['name']);
    $clearance_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['clearance']['tmp_name'], $uploadDir . $filename);
}

// Validate required documents
if (!$report_card_path) {
    sendJSON(['error' => 'Report Card is required for re-enrollment.'], 400);
}
if (!$clearance_path) {
    sendJSON(['error' => 'Accomplished Clearance is required for re-enrollment.'], 400);
}

// --- Save to database ---
try {
    $conn->begin_transaction();

    // A. Insert enrollment record (type = returning)
    $enrollType = 'returning';
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, school_year_id, grade_level_id, session_id, enrollment_type, report_card, clearance) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('DB prepare failed (enrollments insert): ' . $conn->error);
    }
    $stmt->bind_param("iiiisss", $studentId, $school_year_id, $grade_level_id, $session_id, $enrollType, $report_card_path, $clearance_path);
    $stmt->execute();
    $enrollmentId = $conn->insert_id;

    // B. Insert payment record — detect if payments.payment_mode_id column exists
    $hasPaymentModeId = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_mode_id'");
    if ($colCheck && $colCheck->num_rows > 0) $hasPaymentModeId = true;

    $db_payment_mode = (empty($months_count) || $months_count <= 0) ? 'Full' : 'Monthly';

    if ($hasPaymentModeId) {
        $stmt = $conn->prepare("INSERT INTO payments (enrollment_id, payment_method_id, payment_mode, payment_mode_id, months_count, tuition_fee, books_fee, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisiidds", $enrollmentId, $payment_method_id, $db_payment_mode, $payment_mode_id, $months_count, $tuition_fee, $books_fee, $reference_number);
            $stmt->execute();
            $paymentId = $conn->insert_id;
        } else {
            throw new Exception('DB prepare failed (payments insert): ' . $conn->error);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO payments (enrollment_id, payment_method_id, payment_mode, months_count, tuition_fee, books_fee, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisidds", $enrollmentId, $payment_method_id, $db_payment_mode, $months_count, $tuition_fee, $books_fee, $reference_number);
            $stmt->execute();
            $paymentId = $conn->insert_id;
        } else {
            throw new Exception('DB prepare failed (payments insert): ' . $conn->error);
        }
    }

    // C. Initial payment transaction
    $initial_payment = $_POST['initial_payment'] ?? 0;
    if ($initial_payment > 0) {
        $stmt = $conn->prepare("INSERT INTO payment_transactions (payment_id, amount_paid, payment_method_id, reference_number, notes) VALUES (?, ?, ?, ?, 'Initial Payment (Re-enrollment)')");
        if (!$stmt) {
            throw new Exception('DB prepare failed (payment_transactions insert): ' . $conn->error);
        }
        $stmt->bind_param("idis", $paymentId, $initial_payment, $payment_method_id, $reference_number);
        $stmt->execute();
    }

    $conn->commit();

    // Build student full name for response
    $suffixStr = !empty($student['suffix']) ? ' ' . $student['suffix'] : '';
    $middleStr = !empty($student['middle_name']) ? ' ' . $student['middle_name'] : '';
    $fullName = trim($student['last_name'] . $suffixStr . ', ' . $student['first_name'] . $middleStr);

    // Send status email in background
    require_once __DIR__ . '/email_config.php';
    sendStatusEmailInBackground($enrollmentId, 'received');

    sendJSON([
        'message'    => 'Re-enrollment successful!',
        'student_no' => $student_no,
        'name'       => $fullName
    ]);

} catch (Exception $e) {
    $conn->rollback();
    sendJSON(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
}
?>

<?php
/*
 * ============================================================
 *  api/register.php — ENROLLMENT FORM SUBMISSION
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Receives all the enrollment form data from the browser.
 *  - Saves uploaded files (PSA, SF10, 2x2, custom file fields)
 *    to the "uploads" folder.
 *  - Inserts the Parent, Student, and Enrollment records into DB.
 *  - Saves custom form field values (enrollment_field_values).
 *  - Generates a unique Student Number (e.g., 2026-00001).
 *
 *  HOW IT'S CALLED:
 *    The enrollment form (enroll-payment.html) sends a POST request
 *    with all the form data using apiPostForm().
 * ============================================================
 */

require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Invalid request method.'], 400);
}

// --- Standard enrollment fields ---
$first_name         = $_POST['first_name'] ?? '';
$last_name          = $_POST['last_name'] ?? '';
$middle_name        = $_POST['middle_name'] ?? '';
$suffix             = $_POST['suffix'] ?? '';
$birth_date         = $_POST['birth_date'] ?? '';
$gender             = $_POST['gender'] ?? '';
$religion           = $_POST['religion'] ?? '';
$house_no_street    = $_POST['house_no_street'] ?? '';
$barangay           = $_POST['barangay'] ?? '';
$city_municipality  = $_POST['city_municipality'] ?? '';
$province           = $_POST['province'] ?? '';
$grade_level_id     = $_POST['grade_level_id'] ?? '';
$session_id         = $_POST['session_id'] ?? '';
$school_year_id     = $_POST['school_year_id'] ?? '';
$relation_id        = $_POST['relation_id'] ?? '';
$parent_first_name  = $_POST['parent_first_name'] ?? '';
$parent_last_name   = $_POST['parent_last_name'] ?? '';
$parent_middle_name = $_POST['parent_middle_name'] ?? '';
$parent_contact     = $_POST['parent_contact'] ?? '';
$parent_telephone   = $_POST['parent_telephone'] ?? '';
$occupation         = $_POST['occupation'] ?? '';
$income_range_id    = $_POST['income_range_id'] ?? '';
$previous_school    = $_POST['previous_school'] ?? '';
$payment_method_id  = $_POST['payment_method_id'] ?? '';
$payment_mode       = $_POST['payment_mode'] ?? 'Full Payment';
$payment_mode_id    = $_POST['payment_mode_id'] ?? null;
$months_count       = !empty($_POST['months_count']) ? intval($_POST['months_count']) : null;
$tuition_fee        = $_POST['tuition_fee'] ?? null;
$books_fee          = $_POST['books_fee'] ?? null;
$reference_number   = $_POST['reference_number'] ?? '';
$email              = $_POST['email'] ?? '';

// --- Validate required fields ---
if (!$first_name || !$last_name || !$email || !$house_no_street || !$barangay || !$city_municipality || !$province || !$payment_method_id || !$school_year_id) {
    sendJSON(['error' => 'All required fields including Payment details and School Year must be filled.'], 400);
}

// 2x2 picture is always required
if (!isset($_FILES['picture_2x2']) || $_FILES['picture_2x2']['error'] !== UPLOAD_ERR_OK) {
    sendJSON(['error' => 'A 2x2 Picture is required to complete your enrollment.'], 400);
}

// --- File upload setup ---
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// --- Standard file uploads ---
$psa_path = null;
if (isset($_FILES['psa_birth_cert']) && $_FILES['psa_birth_cert']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_psa_' . basename($_FILES['psa_birth_cert']['name']);
    $psa_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['psa_birth_cert']['tmp_name'], $uploadDir . $filename);
}

$sf10_path = null;
if (isset($_FILES['sf10_document']) && $_FILES['sf10_document']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_sf10_' . basename($_FILES['sf10_document']['name']);
    $sf10_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['sf10_document']['tmp_name'], $uploadDir . $filename);
}

$picture_2x2_path = null;
if (isset($_FILES['picture_2x2']) && $_FILES['picture_2x2']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_2x2_' . basename($_FILES['picture_2x2']['name']);
    $picture_2x2_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['picture_2x2']['tmp_name'], $uploadDir . $filename);
}

// --- Collect custom field values (text/number/date/select/textarea) ---
// These arrive as custom_field_{id}=value in POST
$customTextValues = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'custom_field_') === 0) {
        $fieldId = intval(substr($key, strlen('custom_field_')));
        if ($fieldId > 0) {
            $customTextValues[$fieldId] = $value;
        }
    }
}

// --- Collect custom file uploads (custom_file_{id}) ---
$customFileValues = [];
foreach ($_FILES as $key => $fileInfo) {
    if (strpos($key, 'custom_file_') === 0 && $fileInfo['error'] === UPLOAD_ERR_OK) {
        $fieldId = intval(substr($key, strlen('custom_file_')));
        if ($fieldId > 0) {
            $filename = time() . '_customfile' . $fieldId . '_' . basename($fileInfo['name']);
            $filePath = 'api/uploads/' . $filename;
            move_uploaded_file($fileInfo['tmp_name'], $uploadDir . $filename);
            $customFileValues[$fieldId] = $filePath;
        }
    }
}

// --- Save to database ---
try {
    $conn->begin_transaction();

    // A. Insert parent record
    $stmt = $conn->prepare("INSERT INTO parents (first_name, last_name, middle_name, relation_id, mobile, telephone, occupation, income_range_id, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisssis", $parent_first_name, $parent_last_name, $parent_middle_name, $relation_id, $parent_contact, $parent_telephone, $occupation, $income_range_id, $email);
    $stmt->execute();
    $parentId = $conn->insert_id;

    // B. Generate student number
    $stmtCount = $conn->query("SELECT COUNT(*) as total FROM students");
    $count = $stmtCount->fetch_assoc()['total'] + 1;
    $studentNo = date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

    // C. Insert student record
    $stmt = $conn->prepare("INSERT INTO students (parent_id, student_no, first_name, last_name, middle_name, suffix, birth_date, gender, religion, house_no_street, barangay, city_municipality, province, previous_school, psa_birth_cert, sf10_document, picture_2x2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssssssssss", $parentId, $studentNo, $first_name, $last_name, $middle_name, $suffix, $birth_date, $gender, $religion, $house_no_street, $barangay, $city_municipality, $province, $previous_school, $psa_path, $sf10_path, $picture_2x2_path);
    $stmt->execute();
    $studentId = $conn->insert_id;

    // D. Insert enrollment record
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, school_year_id, grade_level_id, session_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $studentId, $school_year_id, $grade_level_id, $session_id);
    $stmt->execute();
    $enrollmentId = $conn->insert_id;

    // E. Insert payment record (with payment_mode_id)
    $stmt = $conn->prepare("INSERT INTO payments (enrollment_id, payment_method_id, payment_mode, payment_mode_id, months_count, tuition_fee, books_fee, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiidds", $enrollmentId, $payment_method_id, $payment_mode, $payment_mode_id, $months_count, $tuition_fee, $books_fee, $reference_number);
    $stmt->execute();
    $paymentId = $conn->insert_id;

    // F. Initial payment transaction
    $initial_payment = $_POST['initial_payment'] ?? 0;
    if ($initial_payment > 0) {
        $stmt = $conn->prepare("INSERT INTO payment_transactions (payment_id, amount_paid, payment_method_id, reference_number, notes) VALUES (?, ?, ?, ?, 'Initial Payment')");
        $stmt->bind_param("idis", $paymentId, $initial_payment, $payment_method_id, $reference_number);
        $stmt->execute();
    }

    // G. Save custom text/select/textarea field values
    if (!empty($customTextValues)) {
        $stmtCf = $conn->prepare("INSERT INTO enrollment_field_values (enrollment_id, field_id, field_value) VALUES (?, ?, ?)");
        foreach ($customTextValues as $fieldId => $value) {
            if (trim($value) !== '') {
                $stmtCf->bind_param("iis", $enrollmentId, $fieldId, $value);
                $stmtCf->execute();
            }
        }
    }

    // H. Save custom file field values
    if (!empty($customFileValues)) {
        $stmtCf2 = $conn->prepare("INSERT INTO enrollment_field_values (enrollment_id, field_id, field_value) VALUES (?, ?, ?)");
        foreach ($customFileValues as $fieldId => $filePath) {
            $stmtCf2->bind_param("iis", $enrollmentId, $fieldId, $filePath);
            $stmtCf2->execute();
        }
    }

    $conn->commit();

    // Send status email in background
    require_once __DIR__ . '/email_config.php';
    sendStatusEmailInBackground($enrollmentId, 'received');

    sendJSON([
        'message'    => 'Registration successful!',
        'student_no' => $studentNo,
        'name'       => trim(preg_replace('/\s+/', ' ', "$last_name" . ($suffix ? " $suffix" : "") . ", $first_name $middle_name"))
    ]);

} catch (Exception $e) {
    $conn->rollback();
    sendJSON(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
}
?>
<?php
/*
 * ============================================================
 *  api/register.php — ENROLLMENT FORM SUBMISSION
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Receives all the enrollment form data from the browser.
 *  - Saves uploaded files (PSA, SF10) to the "uploads" folder.
 *  - Inserts the Parent, Student, and Enrollment records into the database.
 *  - Generates a unique Student Number (e.g., 2026-00001).
 *
 *  HOW IT'S CALLED:
 *    The enrollment form (enroll-payment.html) sends a POST request
 *    with all the form data using apiPostForm().
 * ============================================================
 */

// Connect to the database
require_once '../db.php';

// Only allow POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Invalid request method.'], 400);
}

// --- STEP 1: Collect all form data ---
// Each variable matches a "name" attribute in the HTML form inputs.
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
$occupation         = $_POST['occupation'] ?? '';
$income_range_id    = $_POST['income_range_id'] ?? '';
$previous_school    = $_POST['previous_school'] ?? '';
$payment_method_id  = $_POST['payment_method_id'] ?? '';
$payment_mode       = $_POST['payment_mode'] ?? 'Monthly';
$months_count       = ($payment_mode === 'Monthly') ? 10 : null;  // Monthly is always 10 months
$tuition_fee        = $_POST['tuition_fee'] ?? null;
$books_fee          = $_POST['books_fee'] ?? null;
$reference_number   = $_POST['reference_number'] ?? '';
$email              = $_POST['email'] ?? '';

// --- STEP 2: Validate required fields ---
if (!$first_name || !$last_name || !$email || !$house_no_street || !$barangay || !$city_municipality || !$province || !$payment_method_id || !$school_year_id) {
    sendJSON(['error' => 'All required fields including Payment details and School Year must be filled.'], 400);
}

// --- STEP 3: Handle file uploads ---
// Save uploaded files to the "api/uploads/" folder.
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);  // Create the folder if it doesn't exist
}

// Upload PSA Birth Certificate (optional)
$psa_path = null;
if (isset($_FILES['psa_birth_cert']) && $_FILES['psa_birth_cert']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_psa_' . basename($_FILES['psa_birth_cert']['name']);
    $psa_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['psa_birth_cert']['tmp_name'], $uploadDir . $filename);
}

// Upload SF10 / Form 137 (optional — for transferees only)
$sf10_path = null;
if (isset($_FILES['sf10_document']) && $_FILES['sf10_document']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_sf10_' . basename($_FILES['sf10_document']['name']);
    $sf10_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['sf10_document']['tmp_name'], $uploadDir . $filename);
}

// Upload 2x2 Picture (optional)
$picture_2x2_path = null;
if (isset($_FILES['picture_2x2']) && $_FILES['picture_2x2']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_2x2_' . basename($_FILES['picture_2x2']['name']);
    $picture_2x2_path = 'api/uploads/' . $filename;
    move_uploaded_file($_FILES['picture_2x2']['tmp_name'], $uploadDir . $filename);
}

// --- STEP 4: Save everything to the database ---
try {
    // Start a Transaction — if anything fails, nothing gets saved (all-or-nothing).
    $conn->begin_transaction();

    // A. Insert the Parent record (no email uniqueness check — same parent can enroll multiple children)
    $stmt = $conn->prepare("INSERT INTO parents (first_name, last_name, middle_name, relation_id, contact_no, occupation, income_range_id, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissss", $parent_first_name, $parent_last_name, $parent_middle_name, $relation_id, $parent_contact, $occupation, $income_range_id, $email);
    $stmt->execute();
    $parentId = $conn->insert_id;  // Get the new parent's ID

    // C. Generate a unique Student Number (format: YEAR-00001)
    $stmtCount = $conn->query("SELECT COUNT(*) as total FROM students");
    $count = $stmtCount->fetch_assoc()['total'] + 1;
    $studentNo = date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

    // D. Insert the Student record (Linking to the Parent)
    $stmt = $conn->prepare("INSERT INTO students (parent_id, student_no, first_name, last_name, middle_name, suffix, birth_date, gender, religion, house_no_street, barangay, city_municipality, province, previous_school, psa_birth_cert, sf10_document, picture_2x2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssssssssssss", $parentId, $studentNo, $first_name, $last_name, $middle_name, $suffix, $birth_date, $gender, $religion, $house_no_street, $barangay, $city_municipality, $province, $previous_school, $psa_path, $sf10_path, $picture_2x2_path);
    $stmt->execute();
    $studentId = $conn->insert_id;

    // E. Create the Enrollment record (linking to student and school year)
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, school_year_id, grade_level_id, session_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $studentId, $school_year_id, $grade_level_id, $session_id);
    $stmt->execute();
    $enrollmentId = $conn->insert_id;

    // F. Create the Payment record (storing fees and month count)
    $stmt = $conn->prepare("INSERT INTO payments (enrollment_id, payment_method_id, payment_mode, months_count, tuition_fee, books_fee, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissids", $enrollmentId, $payment_method_id, $payment_mode, $months_count, $tuition_fee, $books_fee, $reference_number);
    $stmt->execute();

    // Save everything!
    $conn->commit();

    // Send success response back to the browser
    sendJSON([
        'message' => 'Registration successful!',
        'student_no' => $studentNo,
        'name' => "$first_name $last_name"
    ]);

} catch (Exception $e) {
    // If anything went wrong, undo all database changes
    $conn->rollback();
    sendJSON(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
}
?>
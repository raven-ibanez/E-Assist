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
$birth_date         = $_POST['birth_date'] ?? '';
$gender             = $_POST['gender'] ?? '';
$address            = $_POST['address'] ?? '';
$grade_level_id     = $_POST['grade_level_id'] ?? '';
$session_preference = $_POST['session_preference'] ?? '';
$relation_id        = $_POST['relation_id'] ?? '';
$parent_name        = $_POST['parent_name'] ?? '';
$parent_contact     = $_POST['parent_contact'] ?? '';
$occupation         = $_POST['occupation'] ?? '';
$monthly_income     = $_POST['monthly_income'] ?? '';
$previous_school    = $_POST['previous_school'] ?? '';
$payment_method     = $_POST['payment_method'] ?? '';
$reference_number   = $_POST['reference_number'] ?? '';
$email              = $_POST['email'] ?? '';

// --- STEP 2: Validate required fields ---
if (!$first_name || !$last_name || !$email || !$payment_method || !$reference_number) {
    sendJSON(['error' => 'All required fields including Payment details must be filled.'], 400);
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

// --- STEP 4: Save everything to the database ---
try {
    // Start a Transaction — if anything fails, nothing gets saved (all-or-nothing).
    $pdo->beginTransaction();

    // A. Check if the email is already registered
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'This email is already registered.'], 400);
    }

    // B. Insert the Parent record
    $stmt = $pdo->prepare("INSERT INTO parents (full_name, relation_id, contact_no, occupation, monthly_income, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$parent_name, $relation_id, $parent_contact, $occupation, $monthly_income, $email, 'N/A']);
    $parentId = $pdo->lastInsertId();  // Get the new parent's ID

    // C. Generate a unique Student Number (format: YEAR-00001)
    $stmtCount = $pdo->query("SELECT COUNT(*) as total FROM students");
    $count = $stmtCount->fetch()['total'] + 1;
    $studentNo = date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

    // D. Insert the Student record
    $stmt = $pdo->prepare("INSERT INTO students (student_no, first_name, last_name, birth_date, gender, address, previous_school, psa_birth_cert, sf10_document) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$studentNo, $first_name, $last_name, $birth_date, $gender, $address, $previous_school, $psa_path, $sf10_path]);
    $studentId = $pdo->lastInsertId();

    // E. Create the Enrollment record (linking student + parent, status = Pending)
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, parent_id, grade_level_id, session_preference, payment_method, reference_number, status_id) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$studentId, $parentId, $grade_level_id, $session_preference, $payment_method, $reference_number]);

    // Save everything!
    $pdo->commit();

    // Send success response back to the browser
    sendJSON([
        'message' => 'Registration successful!',
        'student_no' => $studentNo,
        'name' => "$first_name $last_name"
    ]);

} catch (Exception $e) {
    // If anything went wrong, undo all database changes
    $pdo->rollBack();
    sendJSON(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
}
?>
<?php
/**
 * api/register.php - STUDENT REGISTRATION LOGIC
 * ---------------------------------------------------------
 * This file is called when a user submits the registration form.
 * It handles:
 * 1. Taking the data from the form.
 * 2. Saving uploaded files (PSA, SF10).
 * 3. Saving the Parent and Student info into the database.
 * ---------------------------------------------------------
 */

// Include the database connection file
require_once '../db.php';

// 1. Security Check: Only allow POST requests (form submissions)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Invalid request method. Go back to the form.'], 400);
}

// 2. Collect Data from the Form ($_POST)
// The names inside the brackets (like 'first_name') must match the "name" attribute in your HTML.
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
$email              = $_POST['email'] ?? '';
$password           = $_POST['password'] ?? '';

// 3. Basic Validation
// Check if essential fields are empty
if (!$first_name || !$last_name || !$email || !$password) {
    sendJSON(['error' => 'Name, Email, and Password are required.'], 400);
}

// 4. Handle File Uploads
// We save files into a folder called "uploads" so we don't lose them.
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create the folder if it doesn't exist
}

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

// 5. Database Operations
try {
    // Start a "Transaction" - this means if one part fails, none of it is saved.
    $pdo->beginTransaction();

    // A. Check if the Email is already used
    $stmt = $pdo->prepare("SELECT id FROM parents WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'This email is already registered.'], 400);
    }

    // B. Insert Parent Info
    $sqlParent = "INSERT INTO parents (full_name, relation_id, contact_no, occupation, monthly_income, email, password) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmtParent = $pdo->prepare($sqlParent);
    $stmtParent->execute([$parent_name, $relation_id, $parent_contact, $occupation, $monthly_income, $email, $password]);
    $parentId = $pdo->lastInsertId(); // Get the ID of the new parent

    // C. Generate a unique Student Number (e.g., 2026-00001)
    $stmtCount = $pdo->query("SELECT COUNT(*) as total FROM students");
    $count = $stmtCount->fetch()['total'] + 1;
    $studentNo = date('Y') . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);

    // D. Insert Student Info
    $sqlStudent = "INSERT INTO students (student_no, first_name, last_name, birth_date, gender, address, previous_school, psa_birth_cert, sf10_document) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtStudent = $pdo->prepare($sqlStudent);
    $stmtStudent->execute([$studentNo, $first_name, $last_name, $birth_date, $gender, $address, $previous_school, $psa_path, $sf10_path]);
    $studentId = $pdo->lastInsertId();

    // E. Link Student and Parent in the 'enrollments' table (Status 1 = Pending)
    $stmtEnroll = $pdo->prepare("INSERT INTO enrollments (student_id, parent_id, grade_level_id, session_preference, status_id) VALUES (?, ?, ?, ?, 1)");
    $stmtEnroll->execute([$studentId, $parentId, $grade_level_id, $session_preference]);

    // Save everything!
    $pdo->commit();

    // Send success message back to the website
    sendJSON([
        'message' => 'Registration successful! You can now check your progress.',
        'student_no' => $studentNo,
        'name' => "$first_name $last_name"
    ]);

} catch (Exception $e) {
    // If something goes wrong, "Rollback" (undo everything)
    $pdo->rollBack();
    sendJSON(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
}
?>

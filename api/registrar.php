<?php
/*
 * ============================================================
 *  api/registrar.php — EMPLOYEE DASHBOARD BACKEND
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Handles employee login (admin, registrar, cashier).
 *  - Provides student enrollment data for the registrar dashboard.
 *  - Provides payment data for the cashier dashboard.
 *  - Manages employee accounts (add/delete) for the admin dashboard.
 *
 *  HOW IT'S CALLED:
 *    The dashboards call this file with different "action" values:
 *      api/registrar.php?action=login
 *      api/registrar.php?action=students
 *      api/registrar.php?action=payments
 *      api/registrar.php?action=employees
 *      etc.
 * ============================================================
 */

// Connect to the database
require_once '../db.php';

// Read the "action" from the URL
$action = $_GET['action'] ?? '';

// Read any JSON data sent in the request body (used for login, approve/reject, etc.)
$data = json_decode(file_get_contents('php://input'), true);


// =============================================================
//  ACTION: LOGIN
//  - Checks username and password against the "admin" table.
//  - Returns the user's role (admin, registrar, or cashier).
// =============================================================
if ($action === 'login') {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    // Find the user in the database
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if ($user) {
        // Login success — send back username and role
        sendJSON([
            'message'  => 'Login successful',
            'username' => $user['username'],
            'role'     => $user['role']
        ]);
    } else {
        // Login failed
        sendJSON(['error' => 'Invalid credentials.'], 401);
    }
}


// =============================================================
//  ACTION: GET ALL STUDENTS
//  - Used by: Registrar Dashboard & Admin Dashboard (Enrollments tab)
//  - Returns a list of all enrollment applications.
// =============================================================
if ($action === 'students') {
    $stmt = $pdo->query("
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            s.student_no, 
            s.first_name, 
            s.last_name,
            gl.name AS grade_level,
            p.full_name AS parent_name,
            p.contact_no AS parent_contact,
            es.name AS status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON e.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN enrollment_statuses es ON e.status_id     = es.id
        ORDER BY e.applied_at DESC
    ");
    sendJSON($stmt->fetchAll());
}


// =============================================================
//  ACTION: GET SINGLE STUDENT DETAIL
//  - Used when clicking "View" on a student row.
//  - Returns ALL info about one enrollment (student, parent, docs).
// =============================================================
if ($action === 'detail') {
    $id = $_GET['id'] ?? '';

    $stmt = $pdo->prepare("
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            e.session_preference,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.birth_date, 
            s.gender, 
            s.address,
            s.previous_school, 
            s.psa_birth_cert, 
            s.sf10_document,
            gl.name AS grade_level,
            p.full_name AS parent_name,
            p.contact_no AS parent_contact,
            p.email, 
            p.occupation, 
            p.monthly_income,
            r.name AS parent_relation,
            es.name AS status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON e.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN enrollment_statuses es ON e.status_id     = es.id
        JOIN relations r           ON p.relation_id    = r.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        sendJSON($row);
    } else {
        sendJSON(['error' => 'Enrollment not found.'], 404);
    }
}


// =============================================================
//  ACTION: APPROVE OR REJECT A STUDENT
//  - Changes the enrollment status to "Approved" or "Rejected".
// =============================================================
if ($action === 'validate') {
    $id = $_GET['id'] ?? '';
    $status_name = $data['action'] ?? '';  // "Approved" or "Rejected"

    // Find the status ID from the name
    $stmt = $pdo->prepare("SELECT id FROM enrollment_statuses WHERE name = ?");
    $stmt->execute([$status_name]);
    $status = $stmt->fetch();

    if (!$status) {
        sendJSON(['error' => 'Invalid status name.'], 400);
    }

    // Update the enrollment record
    $stmt = $pdo->prepare("UPDATE enrollments SET status_id = ? WHERE id = ?");
    $stmt->execute([$status['id'], $id]);
    sendJSON(['message' => "Student record has been $status_name."]);
}


// =============================================================
//  ACTION: GET PAYMENT INFO
//  - Used by: Cashier Dashboard & Admin Dashboard (Payments tab)
//  - Returns all enrollments with payment method and reference.
// =============================================================
if ($action === 'payments') {
    $stmt = $pdo->query("
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            e.payment_method, 
            e.reference_number,
            s.student_no, 
            s.first_name, 
            s.last_name,
            gl.name AS grade_level,
            p.full_name AS parent_name,
            p.contact_no AS parent_contact,
            es.name AS status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON e.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN enrollment_statuses es ON e.status_id     = es.id
        ORDER BY e.applied_at DESC
    ");
    sendJSON($stmt->fetchAll());
}


// =============================================================
//  ACTION: GET ALL EMPLOYEE ACCOUNTS
//  - Used by: Admin Dashboard (Employees tab)
//  - Returns a list of all employee usernames and roles.
// =============================================================
if ($action === 'employees') {
    $stmt = $pdo->query("SELECT id, username, role FROM admin ORDER BY role, username");
    sendJSON($stmt->fetchAll());
}


// =============================================================
//  ACTION: ADD A NEW EMPLOYEE ACCOUNT
//  - Used by: Admin Dashboard "Add Employee" form.
// =============================================================
if ($action === 'add_employee') {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $role     = $data['role'] ?? '';

    // Validate input
    if (!$username || !$password || !$role) {
        sendJSON(['error' => 'Username, password, and role are required.'], 400);
    }
    if (!in_array($role, ['admin', 'registrar', 'cashier'])) {
        sendJSON(['error' => 'Invalid role.'], 400);
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'Username already exists.'], 400);
    }

    // Insert the new employee
    $stmt = $pdo->prepare("INSERT INTO admin (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password, $role]);
    sendJSON(['message' => "Employee '$username' created as $role."]);
}


// =============================================================
//  ACTION: DELETE AN EMPLOYEE ACCOUNT
//  - Used by: Admin Dashboard delete button.
// =============================================================
if ($action === 'delete_employee') {
    $id = $_GET['id'] ?? '';

    // Find the employee first
    $stmt = $pdo->prepare("SELECT username FROM admin WHERE id = ?");
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        sendJSON(['error' => 'Employee not found.'], 404);
    }

    // Delete the employee
    $stmt = $pdo->prepare("DELETE FROM admin WHERE id = ?");
    $stmt->execute([$id]);
    sendJSON(['message' => "Employee '{$target['username']}' has been deleted."]);
}


// If no valid action matched, return an error
sendJSON(['error' => 'Invalid action'], 400);
?>

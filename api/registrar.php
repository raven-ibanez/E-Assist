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

/**
 * Helper function to calculate the overall status string
 * based on the individual registrar and cashier reviews.
 */
function calculateStatus($reg, $cash) {
    if ($reg === 'declined' || $cash === 'declined') return 'Declined';
    if ($reg === 'approved' && $cash === 'approved') return 'Enrolled';
    if ($reg === 'approved') return 'For Payment Review';
    if ($cash === 'approved') return 'For Application Review';
    return 'Pending';
}

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

    // Find the user in the database (Joining with roles table)
    $stmt = $conn->prepare("
        SELECT a.*, r.name AS role_name 
        FROM admin a
        JOIN roles r ON a.role_id = r.id
        WHERE a.username = ? AND a.password = ?
    ");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        // Login success — send back ID, username and the HUMAN-READABLE role name
        sendJSON([
            'message'  => 'Login successful',
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role_name']
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
    $result = $conn->query("
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.suffix,
            gl.name AS grade_level,
            p.contact_no AS parent_contact,
            CONCAT(p.first_name, ' ', p.last_name) AS parent_name,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        ORDER BY e.applied_at DESC
    ");

    if (!$result) {
        sendJSON(['error' => 'Failed to fetch students: ' . $conn->error], 500);
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$item) {
        $item['status'] = calculateStatus($item['registrar_status'], $item['cashier_status']);
    }

    sendJSON($rows);
}


// =============================================================
//  ACTION: GET SINGLE STUDENT DETAIL
//  - Used when clicking "View" on a student row.
//  - Returns ALL info about one enrollment (student, parent, docs).
// =============================================================
if ($action === 'detail') {
    $id = $_GET['id'] ?? '';

    $stmt = $conn->prepare("
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            sess.name AS session_preference,
            pm.name AS payment_method,
            pay.reference_number,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.suffix,
            s.birth_date, 
            s.gender, 
            s.address,
            s.previous_school, 
            s.psa_birth_cert, 
            s.sf10_document,
            gl.name AS grade_level,
            p.contact_no AS parent_contact,
            CONCAT(p.first_name, ' ', p.last_name) AS parent_name,
            p.email, 
            p.occupation, 
            ir.range_label AS monthly_income,
            r.name AS parent_relation,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN relations r           ON p.relation_id    = r.id
        JOIN sessions sess         ON e.session_id     = sess.id
        LEFT JOIN payments pay     ON e.id             = pay.enrollment_id
        LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
        LEFT JOIN income_ranges ir ON p.income_range_id = ir.id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $row['status'] = calculateStatus($row['registrar_status'], $row['cashier_status']);
        sendJSON($row);
    } else {
        sendJSON(['error' => 'Enrollment not found or query error.'], 404);
    }
}





// =============================================================
//  ACTION: GET PAYMENT INFO
//  - Used by: Cashier Dashboard & Admin Dashboard (Payments tab)
//  - Returns all enrollments with payment method and reference.
// =============================================================
if ($action === 'payments') {
    $result = $conn->query("
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            pm.name AS payment_method, 
            pay.reference_number,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.suffix,
            gl.name AS grade_level,
            p.contact_no AS parent_contact,
            CONCAT(p.first_name, ' ', p.last_name) AS parent_name,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        LEFT JOIN payments pay     ON e.id             = pay.enrollment_id
        LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
        ORDER BY e.applied_at DESC
    ");

    if (!$result) {
        sendJSON(['error' => 'Failed to fetch payments: ' . $conn->error], 500);
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$item) {
        $item['status'] = calculateStatus($item['registrar_status'], $item['cashier_status']);
    }

    sendJSON($rows);
}


// =============================================================
//  ACTION: REVIEW APPLICATION (Registrar approve/decline)
//  - Used by: Registrar Dashboard
//  - Updates registrar_status and recalculates the overall status.
// =============================================================
if ($action === 'review_application') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $decision      = $data['decision'] ?? '';  // 'approved' or 'declined'
    $admin_id      = $data['admin_id'] ?? '';

    if (!$enrollment_id || !$admin_id || !in_array($decision, ['approved', 'declined'])) {
        sendJSON(['error' => 'Invalid request. Enrollment ID, Admin ID, and decision required.'], 400);
    }

    // Insert the new review record (The "Middle Man")
    $stmt = $conn->prepare("INSERT INTO enrollment_reviews (enrollment_id, admin_id, review_type, decision) VALUES (?, ?, 'Registrar', ?)");
    $stmt->bind_param("iis", $enrollment_id, $admin_id, $decision);
    $stmt->execute();

    // To send back the new dynamic status, we fetch the cashier status
    $stmtCash = $conn->prepare("SELECT decision FROM enrollment_reviews WHERE enrollment_id = ? AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1");
    $stmtCash->bind_param("i", $enrollment_id);
    $stmtCash->execute();
    $cashRow = $stmtCash->get_result()->fetch_assoc();
    $cashStatus = $cashRow['decision'] ?? 'pending';

    $newOverallStatus = calculateStatus($decision, $cashStatus);

    sendJSON(['message' => 'Registrar review saved.', 'new_status' => $newOverallStatus]);
}


// =============================================================
//  ACTION: REVIEW PAYMENT (Cashier approve/decline)
//  - Used by: Cashier Dashboard
//  - Updates cashier_status and recalculates the overall status.
// =============================================================
if ($action === 'review_payment') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $decision      = $data['decision'] ?? '';  // 'approved' or 'declined'
    $admin_id      = $data['admin_id'] ?? '';

    if (!$enrollment_id || !$admin_id || !in_array($decision, ['approved', 'declined'])) {
        sendJSON(['error' => 'Invalid request. Enrollment ID, Admin ID, and decision required.'], 400);
    }

    // Insert the new review record (The "Middle Man")
    $stmt = $conn->prepare("INSERT INTO enrollment_reviews (enrollment_id, admin_id, review_type, decision) VALUES (?, ?, 'Cashier', ?)");
    $stmt->bind_param("iis", $enrollment_id, $admin_id, $decision);
    $stmt->execute();

    // To send back the new dynamic status, we fetch the registrar status
    $stmtReg = $conn->prepare("SELECT decision FROM enrollment_reviews WHERE enrollment_id = ? AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1");
    $stmtReg->bind_param("i", $enrollment_id);
    $stmtReg->execute();
    $regRow = $stmtReg->get_result()->fetch_assoc();
    $regStatus = $regRow['decision'] ?? 'pending';

    $newOverallStatus = calculateStatus($regStatus, $decision);

    sendJSON(['message' => 'Cashier review saved.', 'new_status' => $newOverallStatus]);
}


// =============================================================
//  ACTION: GET ALL EMPLOYEE ACCOUNTS
//  - Used by: Admin Dashboard (Employees tab)
//  - Returns a list of all employee usernames and roles.
// =============================================================
if ($action === 'employees') {
    $result = $conn->query("
        SELECT a.id, a.username, r.name AS role 
        FROM admin a
        JOIN roles r ON a.role_id = r.id
        ORDER BY r.name, a.username
    ");

    if (!$result) {
        sendJSON(['error' => 'Failed to fetch employees: ' . $conn->error], 500);
    }

    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}


// =============================================================
//  ACTION: ADD A NEW EMPLOYEE ACCOUNT
//  - Used by: Admin Dashboard "Add Employee" form.
// =============================================================
if ($action === 'add_employee') {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $role_id  = $data['role_id'] ?? '';

    // Validate input
    if (!$username || !$password || !$role_id) {
        sendJSON(['error' => 'Username, password, and role are required.'], 400);
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        sendJSON(['error' => 'Username already exists.'], 400);
    }

    // Insert the new employee
    $stmt = $conn->prepare("INSERT INTO admin (username, password, role_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $username, $password, $role_id);
    $stmt->execute();
    sendJSON(['message' => "Employee account created successfully."]);
}


// =============================================================
//  ACTION: DELETE AN EMPLOYEE ACCOUNT
//  - Used by: Admin Dashboard delete button.
// =============================================================
if ($action === 'delete_employee') {
    $id = $_GET['id'] ?? '';

    // Find the employee first
    $stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$target) {
        sendJSON(['error' => 'Employee not found.'], 404);
    }

    // Delete the employee
    $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    sendJSON(['message' => "Employee '{$target['username']}' has been deleted."]);
}


// If no valid action matched, return an error
sendJSON(['error' => 'Invalid action'], 400);
?>

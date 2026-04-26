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
 * Also accepts an optional $docs_pending flag for DTF enrollments.
 */
function calculateStatus($reg, $cash, $docs_pending = 0) {
    if ($reg === 'dropped') return 'Dropped';
    if ($reg === 'declined' || $cash === 'declined') return 'Declined';
    if ($reg === 'declined' || $cash === 'declined') return 'Declined';
    if ($reg === 'approved' && $cash === 'approved') {
        return $docs_pending ? 'Enrolled (Doc. Pending)' : 'Enrolled';
    }
    if ($reg === 'approved') return 'For Payment Review';
    if ($cash === 'approved') return 'For Application Review';
    return 'Pending';
}

/**
 * Log an administrative action to the system_logs table.
 */
function logAction($admin_id, $action, $target_id = null, $target_name = null, $details = null) {
    global $conn;
    if (!$admin_id) return; // Cannot log without admin ID
    $stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, target_id, target_name, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $admin_id, $action, $target_id, $target_name, $details);
    $stmt->execute();
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

    // Find the user by username only (password check done in PHP for case-sensitivity)
    $stmt = $conn->prepare("
        SELECT a.*, r.name AS role_name 
        FROM admin a
        JOIN roles r ON a.role_id = r.id
        WHERE a.username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Check if account is active
        if (isset($user['is_active']) && $user['is_active'] == 0) {
            sendJSON(['error' => 'This account has been deactivated. Please contact the administrator.'], 403);
        }

        // Login success — send back ID, username and the HUMAN-READABLE role name
        sendJSON([
            'message'  => 'Login successful',
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role_name']
        ]);
    } else {
        // Login failed (wrong password or unknown username)
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
            e.documents_pending,
            s.id AS student_id,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.middle_name,
            s.suffix,
            gl.name AS grade_level,
            p.id AS parent_id,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.middle_name AS parent_middle_name,
            p.contact_no AS parent_contact,
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
        $item['status'] = calculateStatus($item['registrar_status'], $item['cashier_status'], $item['documents_pending']);
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
            e.documents_pending,
            sy.label AS school_year,
            sess.name AS session_preference,
            pm.name AS payment_method,
            pay.id AS payment_id,
            pay.payment_mode,
            pay.months_count,
            pay.tuition_fee,
            pay.books_fee,
            pay.reference_number,
            COALESCE(pay.tuition_fee, 0) + COALESCE(pay.books_fee, 0) as total_amount,
            COALESCE((SELECT SUM(amount_paid) FROM payment_transactions WHERE payment_id = pay.id), 0) as total_paid,
            s.id AS student_id,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.middle_name,
            s.suffix,
            s.birth_date, 
            s.gender,
            s.religion,
            s.house_no_street,
            s.barangay,
            s.city_municipality,
            s.province,
            s.previous_school, 
            s.psa_birth_cert, 
            s.sf10_document,
            s.picture_2x2,
            gl.name AS grade_level,
            p.id AS parent_id,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.middle_name AS parent_middle_name,
            p.contact_no AS parent_contact,
            p.email, 
            p.occupation, 
            ir.range_label AS monthly_income,
            r.name AS parent_relation,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status,
            (SELECT notes FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1) AS registrar_notes,
            (SELECT notes FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1) AS cashier_notes
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN relations r           ON p.relation_id    = r.id
        JOIN sessions sess         ON e.session_id     = sess.id
        JOIN school_years sy       ON e.school_year_id = sy.id
        LEFT JOIN payments pay     ON e.id             = pay.enrollment_id
        LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
        LEFT JOIN income_ranges ir ON p.income_range_id = ir.id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $row['status'] = calculateStatus($row['registrar_status'], $row['cashier_status'], $row['documents_pending']);
        $row['balance'] = ($row['total_amount'] ?? 0) - ($row['total_paid'] ?? 0);
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
            e.documents_pending,
            (SELECT notes FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1) AS registrar_notes,
            (SELECT notes FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1) AS cashier_notes,
            sy.label AS school_year,
            pm.name AS payment_method, 
            pay.id AS payment_id,
            pay.payment_mode,
            pay.months_count,
            pay.tuition_fee,
            pay.books_fee,
            pay.reference_number,
            COALESCE(pay.tuition_fee, 0) + COALESCE(pay.books_fee, 0) as total_amount,
            COALESCE((SELECT SUM(amount_paid) FROM payment_transactions WHERE payment_id = pay.id), 0) as total_paid,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.middle_name,
            s.suffix,
            gl.name AS grade_level,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.middle_name AS parent_middle_name,
            p.contact_no AS parent_contact,
            COALESCE((SELECT 1 FROM payment_transactions WHERE payment_id = pay.id AND amount_paid < 0 LIMIT 1), 0) AS has_refund,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN school_years sy       ON e.school_year_id = sy.id
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
        $item['balance'] = ($item['total_amount'] ?? 0) - ($item['total_paid'] ?? 0);
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
    $decision      = $data['decision'] ?? '';  // 'approved', 'declined', or 'approved_dtf'
    $admin_id      = $data['admin_id'] ?? '';
    $reason        = $data['reason'] ?? null;

    if (!$enrollment_id || !$admin_id || !in_array($decision, ['approved', 'declined', 'approved_dtf', 'dropped'])) {
        sendJSON(['error' => 'Invalid request. Enrollment ID, Admin ID, and decision required.'], 400);
    }

    // For DTF: the db review is still 'approved', but we flag the enrollment
    // For Dropped: insert 'dropped' as the decision directly
    $db_decision  = ($decision === 'approved_dtf') ? 'approved' : $decision;
    $docs_pending = ($decision === 'approved_dtf') ? 1 : 0;

    // Insert the new review record (The "Middle Man")
    $stmt = $conn->prepare("INSERT INTO enrollment_reviews (enrollment_id, admin_id, review_type, decision, notes) VALUES (?, ?, 'Registrar', ?, ?)");
    $stmt->bind_param("iiss", $enrollment_id, $admin_id, $db_decision, $reason);
    $stmt->execute();

    // Set the documents_pending flag on the enrollment
    $stmt2 = $conn->prepare("UPDATE enrollments SET documents_pending = ? WHERE id = ?");
    $stmt2->bind_param("ii", $docs_pending, $enrollment_id);
    $stmt2->execute();

    // Log the action
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

    $logMsg = "Registrar review: " . ucfirst($decision);
    if ($decision === 'approved_dtf') $logMsg = "Registrar Approved (Doc. to Follow)";
    if ($reason) $logMsg .= " | Reason: " . $reason;
    logAction($admin_id, "Application Review", $enrollment_id, $studentName, $logMsg);

    // To send back the new dynamic status, we fetch the cashier status
    $stmtCash = $conn->prepare("SELECT decision FROM enrollment_reviews WHERE enrollment_id = ? AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1");
    $stmtCash->bind_param("i", $enrollment_id);
    $stmtCash->execute();
    $cashRow = $stmtCash->get_result()->fetch_assoc();
    $cashStatus = $cashRow['decision'] ?? 'pending';

    $newOverallStatus = calculateStatus($db_decision, $cashStatus, $docs_pending);

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
    $reason        = $data['reason'] ?? null;

    if (!$enrollment_id || !$admin_id || !in_array($decision, ['approved', 'declined', 'refunded'])) {
        sendJSON(['error' => 'Invalid request. Enrollment ID, Admin ID, and decision required.'], 400);
    }

    // Insert the new review record (The "Middle Man")
    $stmt = $conn->prepare("INSERT INTO enrollment_reviews (enrollment_id, admin_id, review_type, decision, notes) VALUES (?, ?, 'Cashier', ?, ?)");
    $stmt->bind_param("iiss", $enrollment_id, $admin_id, $decision, $reason);
    $stmt->execute();

    // Log the action
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

    $logMsg = "Cashier review: " . ucfirst($decision);
    if ($reason) $logMsg .= " | Reason: " . $reason;
    logAction($admin_id, "Payment Review", $enrollment_id, $studentName, $logMsg);

    // To send back the new dynamic status, fetch registrar status and documents_pending
    $stmtReg = $conn->prepare("SELECT decision FROM enrollment_reviews WHERE enrollment_id = ? AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1");
    $stmtReg->bind_param("i", $enrollment_id);
    $stmtReg->execute();
    $regRow = $stmtReg->get_result()->fetch_assoc();
    $regStatus = $regRow['decision'] ?? 'pending';

    $stmtDoc = $conn->prepare("SELECT documents_pending FROM enrollments WHERE id = ?");
    $stmtDoc->bind_param("i", $enrollment_id);
    $stmtDoc->execute();
    $docRow = $stmtDoc->get_result()->fetch_assoc();
    $docs_pending = $docRow['documents_pending'] ?? 0;

    $newOverallStatus = calculateStatus($regStatus, $decision, $docs_pending);

    sendJSON(['message' => 'Cashier review saved.', 'new_status' => $newOverallStatus]);
}


// =============================================================
//  ACTION: UPDATE STUDENT INFORMATION (Registrar edit)
//  - Updates student and parent records.
// =============================================================
if ($action === 'update_student') {
    $student_id        = $_POST['student_id'] ?? '';
    $parent_id         = $_POST['parent_id'] ?? '';
    $first_name        = $_POST['first_name'] ?? '';
    $last_name         = $_POST['last_name'] ?? '';
    $middle_name       = $_POST['middle_name'] ?? '';
    $suffix            = $_POST['suffix'] ?? '';
    $birth_date        = $_POST['birth_date'] ?? '';
    $gender            = $_POST['gender'] ?? '';
    $religion          = $_POST['religion'] ?? '';
    $house_no_street   = $_POST['house_no_street'] ?? '';
    $barangay          = $_POST['barangay'] ?? '';
    $city_municipality = $_POST['city_municipality'] ?? '';
    $province          = $_POST['province'] ?? '';
    $previous_school   = $_POST['previous_school'] ?? '';
    $parent_first_name = $_POST['parent_first_name'] ?? '';
    $parent_last_name  = $_POST['parent_last_name'] ?? '';
    $parent_middle_name = $_POST['parent_middle_name'] ?? '';
    $parent_contact    = $_POST['parent_contact'] ?? '';
    $parent_email      = $_POST['parent_email'] ?? '';
    $parent_occupation = $_POST['parent_occupation'] ?? '';

    if (!$student_id || !$parent_id || !$first_name || !$last_name) {
        sendJSON(['error' => 'Student ID, Parent ID, first name and last name are required.'], 400);
    }

    // Update student record
    $stmt = $conn->prepare("UPDATE students SET first_name=?, last_name=?, middle_name=?, suffix=?, birth_date=?, gender=?, religion=?, house_no_street=?, barangay=?, city_municipality=?, province=?, previous_school=? WHERE id=?");
    $stmt->bind_param("ssssssssssssi", $first_name, $last_name, $middle_name, $suffix, $birth_date, $gender, $religion, $house_no_street, $barangay, $city_municipality, $province, $previous_school, $student_id);
    $stmt->execute();

    // Update parent record
    $stmt2 = $conn->prepare("UPDATE parents SET first_name=?, last_name=?, middle_name=?, contact_no=?, email=?, occupation=? WHERE id=?");
    $stmt2->bind_param("ssssssi", $parent_first_name, $parent_last_name, $parent_middle_name, $parent_contact, $parent_email, $parent_occupation, $parent_id);
    $stmt2->execute();

    // Log the action (if admin_id is provided)
    $admin_id = $_POST['admin_id'] ?? null;
    if ($admin_id) {
        $suffixStr = !empty($suffix) ? ' ' . $suffix : '';
        $middleStr = !empty($middle_name) ? ' ' . $middle_name : '';
        $formattedName = $last_name . $suffixStr . ', ' . $first_name . $middleStr;
        logAction($admin_id, "Update Student", $student_id, $formattedName, "Updated student/parent profile information.");
    }

    sendJSON(['message' => 'Student information updated successfully.']);
}


// =============================================================
//  ACTION: UPLOAD DOCUMENT (Registrar uploads missing document)
//  - Accepts a file upload for PSA, SF10, or 2x2 picture.
//  - Saves file to uploads/ folder and clears the documents_pending flag.
// =============================================================
if ($action === 'upload_document') {
    $enrollment_id = $_POST['enrollment_id'] ?? '';
    $student_id    = $_POST['student_id'] ?? '';
    $doc_type      = $_POST['doc_type'] ?? '';  // 'psa', 'sf10', or 'picture'

    if (!$enrollment_id || !$student_id || !in_array($doc_type, ['psa', 'sf10', 'picture'])) {
        sendJSON(['error' => 'Invalid request. Missing required fields.'], 400);
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        sendJSON(['error' => 'No file uploaded or upload error.'], 400);
    }

    // Determine upload directory and column name
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext      = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
    $safeExt  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
    $filename = $doc_type . '_' . $student_id . '_' . time() . '.' . $safeExt;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['document']['tmp_name'], $destPath)) {
        sendJSON(['error' => 'Failed to save uploaded file.'], 500);
    }

    $webPath = 'uploads/' . $filename;

    $colMap = ['psa' => 'psa_birth_cert', 'sf10' => 'sf10_document', 'picture' => 'picture_2x2'];
    $col    = $colMap[$doc_type];

    // Update the student's document path
    $stmt = $conn->prepare("UPDATE students SET `{$col}` = ? WHERE id = ?");
    $stmt->bind_param("si", $webPath, $student_id);
    $stmt->execute();

    // Log the action (if admin_id is provided)
    $admin_id = $_POST['admin_id'] ?? null;
    if ($admin_id) {
        $stmtName = $conn->prepare("SELECT first_name, last_name, middle_name, suffix FROM students WHERE id = ?");
        $stmtName->bind_param("i", $student_id);
        $stmtName->execute();
        $nameRes = $stmtName->get_result()->fetch_assoc();
        $studentName = 'Unknown';
        if ($nameRes) {
            $suffixStr = !empty($nameRes['suffix']) ? ' ' . $nameRes['suffix'] : '';
            $middleStr = !empty($nameRes['middle_name']) ? ' ' . $nameRes['middle_name'] : '';
            $studentName = $nameRes['last_name'] . $suffixStr . ', ' . $nameRes['first_name'] . $middleStr;
        }

        logAction($admin_id, "Upload Document", $student_id, $studentName, "Uploaded missing " . strtoupper($doc_type) . " document.");
    }

    // Check if all three documents are now uploaded (clear flag if so)
    $stmtCheck = $conn->prepare("SELECT psa_birth_cert, sf10_document, picture_2x2 FROM students WHERE id = ?");
    $stmtCheck->bind_param("i", $student_id);
    $stmtCheck->execute();
    $docs = $stmtCheck->get_result()->fetch_assoc();

    // Clear documents_pending flag only when all required documents are present
    if ($docs['psa_birth_cert'] && $docs['sf10_document'] && $docs['picture_2x2']) {
        $stmtFlag = $conn->prepare("UPDATE enrollments SET documents_pending = 0 WHERE id = ?");
        $stmtFlag->bind_param("i", $enrollment_id);
        $stmtFlag->execute();
        sendJSON(['message' => 'Document uploaded. All documents complete — flag cleared!', 'path' => $webPath, 'flag_cleared' => true]);
    } else {
        sendJSON(['message' => 'Document uploaded successfully.', 'path' => $webPath, 'flag_cleared' => false]);
    }
}


// =============================================================
//  ACTION: GET ALL EMPLOYEE ACCOUNTS
//  - Used by: Admin Dashboard (Employees tab)
//  - Returns a list of all employee usernames and roles.
// =============================================================
if ($action === 'employees') {
    $result = $conn->query("
        SELECT a.id, a.username, a.is_active, r.name AS role 
        FROM admin a
        JOIN roles r ON a.role_id = r.id
        ORDER BY a.is_active DESC, r.name, a.username
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

    // Hash the password before storing (bcrypt, case-sensitive)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 5-Active-Employee Limit Check
    $resCount = $conn->query("SELECT COUNT(*) as active_count FROM admin WHERE is_active = 1");
    $countRow = $resCount->fetch_assoc();
    if ($countRow['active_count'] >= 5) {
        sendJSON(['error' => 'Employee limit reached. You can only have 5 active accounts. Please deactivate an existing account first.'], 403);
    }

    // Insert the new employee
    $stmt = $conn->prepare("INSERT INTO admin (username, password, role_id, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("ssi", $username, $hashedPassword, $role_id);
    $stmt->execute();

    // Log the action (if admin_id is provided)
    $admin_id = $data['admin_id'] ?? null;
    if ($admin_id) {
        logAction($admin_id, "Account Created", $conn->insert_id, $username, "Created new employee account: $username");
    }

    sendJSON(['message' => "Employee account created successfully."]);
}


// =============================================================
//  ACTION: TOGGLE EMPLOYEE STATUS (Activate/Deactivate)
//  - Used by: Admin Dashboard toggle button.
// =============================================================
if ($action === 'toggle_employee_status') {
    $id = $_GET['id'] ?? '';

    // Find the employee first
    $stmt = $conn->prepare("SELECT username, is_active FROM admin WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$target) {
        sendJSON(['error' => 'Employee not found.'], 404);
    }

    $new_status = ($target['is_active'] == 1) ? 0 : 1;

    // If activating, check the limit again
    if ($new_status == 1) {
        $resCount = $conn->query("SELECT COUNT(*) as active_count FROM admin WHERE is_active = 1");
        $countRow = $resCount->fetch_assoc();
        if ($countRow['active_count'] >= 5) {
            sendJSON(['error' => 'Cannot activate. You already have 5 active accounts.'], 403);
        }
    }

    // Update the employee status
    $stmt = $conn->prepare("UPDATE admin SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $id);
    $stmt->execute();

    // Log the action
    $admin_id = $_GET['admin_id'] ?? null;
    if ($admin_id) {
        $actionName = ($new_status == 1) ? "Account Activated" : "Account Deactivated";
        logAction($admin_id, $actionName, $id, $target['username'], "Toggled account status for {$target['username']} to " . ($new_status == 1 ? 'Active' : 'Inactive'));
    }

    sendJSON(['message' => "Employee '{$target['username']}' status updated successfully."]);
}


// =============================================================
//  ACTION: GET SYSTEM LOGS
//  - Used by: Admin Dashboard (Logs tab)
// =============================================================
if ($action === 'get_logs') {
    $result = $conn->query("
        SELECT l.*, a.username as employee_name, r.name as employee_role
        FROM system_logs l
        JOIN admin a ON l.admin_id = a.id
        JOIN roles r ON a.role_id = r.id
        ORDER BY l.created_at DESC
        LIMIT 500
    ");

    if (!$result) {
        sendJSON(['error' => 'Failed to fetch logs: ' . $conn->error], 500);
    }

    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}


// =============================================================
//  ACTION: UNDO DROP (Admin revert dropped status)
//  - Used by: Admin Dashboard
//  - Deletes the latest 'dropped' review record.
// =============================================================
if ($action === 'undo_drop') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $admin_id      = $data['admin_id'] ?? '';

    if (!$enrollment_id || !$admin_id) {
        sendJSON(['error' => 'Missing enrollment or admin ID.'], 400);
    }

    // Delete the latest 'dropped' review for this enrollment
    // We target 'Registrar' type because 'dropped' is a registrar-level status
    $stmt = $conn->prepare("DELETE FROM enrollment_reviews WHERE enrollment_id = ? AND decision = 'dropped' AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Log the action
        $stmtName = $conn->prepare("SELECT first_name, last_name, middle_name, suffix FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.id = ?");
        $stmtName->bind_param("i", $enrollment_id);
        $stmtName->execute();
        $resName = $stmtName->get_result()->fetch_assoc();
        $studentName = 'Unknown';
        if ($resName) {
            $suffixStr = !empty($resName['suffix']) ? ' ' . $resName['suffix'] : '';
            $middleStr = !empty($resName['middle_name']) ? ' ' . $resName['middle_name'] : '';
            $studentName = $resName['last_name'] . $suffixStr . ', ' . $resName['first_name'] . $middleStr;
        }

        logAction($admin_id, "Undo Drop", $enrollment_id, $studentName, "Administrator reverted the 'Dropped' status.");
        
        sendJSON(['message' => 'Dropped status undone successfully.']);
    } else {
        sendJSON(['error' => 'No dropped status found to undo.'], 404);
    }
}


// =============================================================
//  ACTION: UNDO REFUND (Admin revert refunded status)
//  - Used by: Cashier Dashboard
//  - Deletes the latest 'refunded' review record.
// =============================================================
if ($action === 'undo_refund') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $admin_id      = $data['admin_id'] ?? '';

    if (!$enrollment_id || !$admin_id) {
        sendJSON(['error' => 'Missing enrollment or admin ID.'], 400);
    }

    // Delete the latest 'refunded' review for this enrollment
    $stmt = $conn->prepare("DELETE FROM enrollment_reviews WHERE enrollment_id = ? AND decision = 'refunded' AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Log the action
        $stmtName = $conn->prepare("SELECT first_name, last_name, middle_name, suffix FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.id = ?");
        $stmtName->bind_param("i", $enrollment_id);
        $stmtName->execute();
        $resName = $stmtName->get_result()->fetch_assoc();
        $studentName = 'Unknown';
        if ($resName) {
            $suffixStr = !empty($resName['suffix']) ? ' ' . $resName['suffix'] : '';
            $middleStr = !empty($resName['middle_name']) ? ' ' . $resName['middle_name'] : '';
            $studentName = $resName['last_name'] . $suffixStr . ', ' . $resName['first_name'] . $middleStr;
        }

        logAction($admin_id, "Undo Refund", $enrollment_id, $studentName, "Administrator reverted the 'Refunded' status.");
        
        sendJSON(['message' => 'Refunded status undone successfully.']);
    } else {
        sendJSON(['error' => 'No refunded status found to undo.'], 404);
    }
}


// =============================================================
//  ACTION: REFUND EXCESS (Cashier refund excess payment)
//  - Used by: Cashier Dashboard
//  - Calculates Paid - Total and adds a negative transaction.
// =============================================================
if ($action === 'refund_excess') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $admin_id      = $data['admin_id'] ?? '';

    if (!$enrollment_id || !$admin_id) {
        sendJSON(['error' => 'Enrollment ID and Admin ID are required.'], 400);
    }

    // Get current payment info
    $stmt = $conn->prepare("
        SELECT 
            pay.id as payment_id,
            (COALESCE(pay.tuition_fee, 0) + COALESCE(pay.books_fee, 0)) as total_fee,
            (SELECT SUM(amount_paid) FROM payment_transactions WHERE payment_id = pay.id) as total_paid,
            s.first_name, s.last_name, s.middle_name, s.suffix
        FROM payments pay
        JOIN enrollments e ON pay.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $payInfo = $stmt->get_result()->fetch_assoc();

    if (!$payInfo) {
        sendJSON(['error' => 'Payment record not found.'], 404);
    }

    $excess = ($payInfo['total_paid'] ?? 0) - ($payInfo['total_fee'] ?? 0);

    if ($excess <= 0) {
        sendJSON(['error' => 'No excess payment found to refund.'], 400);
    }

    // Add negative transaction
    $negExcess = -$excess;
    $notes = "Excess payment refund";
    $method_id = 2; // Default to Cash (ID 2 in seeds) or use a "Refund" method if exists

    $stmtInsert = $conn->prepare("INSERT INTO payment_transactions (payment_id, amount_paid, payment_method_id, notes) VALUES (?, ?, ?, ?)");
    $stmtInsert->bind_param("idis", $payInfo['payment_id'], $negExcess, $method_id, $notes);
    $stmtInsert->execute();

    // Log the action
    $suffixStr = !empty($payInfo['suffix']) ? ' ' . $payInfo['suffix'] : '';
    $middleStr = !empty($payInfo['middle_name']) ? ' ' . $payInfo['middle_name'] : '';
    $studentName = $payInfo['last_name'] . $suffixStr . ', ' . $payInfo['first_name'] . $middleStr;
    
    logAction($admin_id, "Excess Refunded", $enrollment_id, $studentName, "Refunded excess payment of ₱" . number_format($excess, 2));

    sendJSON(['message' => 'Excess payment refunded successfully.']);
}


// =============================================================
//  ACTION: UNDO EXCESS REFUND (Cashier revert refund)
//  - Deletes the most recent negative transaction for a payment.
// =============================================================
if ($action === 'undo_excess_refund') {
    $enrollment_id = $data['enrollment_id'] ?? '';
    $admin_id      = $data['admin_id'] ?? '';

    if (!$enrollment_id || !$admin_id) {
        sendJSON(['error' => 'Enrollment ID and Admin ID are required.'], 400);
    }

    // Get payment ID
    $stmt = $conn->prepare("SELECT id FROM payments WHERE enrollment_id = ?");
    $stmt->bind_param("i", $enrollment_id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();

    if (!$pay) {
        sendJSON(['error' => 'Payment record not found.'], 404);
    }

    $payment_id = $pay['id'];

    // Find latest negative transaction
    $stmt2 = $conn->prepare("SELECT id, amount_paid FROM payment_transactions WHERE payment_id = ? AND amount_paid < 0 ORDER BY created_at DESC LIMIT 1");
    $stmt2->bind_param("i", $payment_id);
    $stmt2->execute();
    $trans = $stmt2->get_result()->fetch_assoc();

    if (!$trans) {
        sendJSON(['error' => 'No refund transaction found to undo.'], 404);
    }

    // Delete it
    $stmt3 = $conn->prepare("DELETE FROM payment_transactions WHERE id = ?");
    $stmt3->bind_param("i", $trans['id']);
    $stmt3->execute();

    // Log
    logAction($admin_id, "Undo Excess Refund", $enrollment_id, null, "Reverted a refund of ₱" . number_format(abs($trans['amount_paid']), 2));

    sendJSON(['message' => 'Refund undone successfully.']);
}


// =============================================================
//  ACTION: ADD PAYMENT TRANSACTION (Cashier update balance)
//  - Used by: Cashier Dashboard
// =============================================================
if ($action === 'add_payment') {
    $payment_id       = $data['payment_id'] ?? '';
    $amount           = $data['amount'] ?? 0;
    $method_id        = $data['method_id'] ?? '';
    $ref              = $data['reference'] ?? '';
    $notes            = $data['notes'] ?? '';
    $admin_id         = $data['admin_id'] ?? '';

    if (!$payment_id || !$amount || !$method_id || !$admin_id) {
        sendJSON(['error' => 'Missing required fields (Payment ID, amount, method, and Admin ID).'], 400);
    }

    $stmt = $conn->prepare("INSERT INTO payment_transactions (payment_id, amount_paid, payment_method_id, reference_number, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idiss", $payment_id, $amount, $method_id, $ref, $notes);
    $stmt->execute();

    // Log the action
    $stmtStudent = $conn->prepare("SELECT s.first_name, s.last_name, s.middle_name, s.suffix, e.id as enrollment_id FROM payments pay JOIN enrollments e ON pay.enrollment_id = e.id JOIN students s ON e.student_id = s.id WHERE pay.id = ?");
    $stmtStudent->bind_param("i", $payment_id);
    $stmtStudent->execute();
    $student = $stmtStudent->get_result()->fetch_assoc();
    $studentName = 'Unknown';
    $enrollment_id = null;
    if ($student) {
        $suffixStr = !empty($student['suffix']) ? ' ' . $student['suffix'] : '';
        $middleStr = !empty($student['middle_name']) ? ' ' . $student['middle_name'] : '';
        $studentName = $student['last_name'] . $suffixStr . ', ' . $student['first_name'] . $middleStr;
        $enrollment_id = $student['enrollment_id'];
    }
    
    logAction($admin_id, "Update Payment", $enrollment_id, $studentName, "Added payment of ₱" . number_format($amount, 2) . ". Ref: $ref");

    sendJSON(['message' => 'Payment updated successfully.']);
}


// =============================================================
//  ACTION: GET PAYMENT HISTORY
//  - Used by: Cashier & Admin dashboards to see installment details.
// =============================================================
if ($action === 'payment_history') {
    $payment_id = $_GET['payment_id'] ?? '';

    if (!$payment_id) {
        sendJSON(['error' => 'Payment ID is required.'], 400);
    }

    $stmt = $conn->prepare("
        SELECT pt.*, pm.name as method_name
        FROM payment_transactions pt
        JOIN payment_methods pm ON pt.payment_method_id = pm.id
        WHERE pt.payment_id = ?
        ORDER BY pt.created_at DESC
    ");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}


// If no valid action matched, return an error
sendJSON(['error' => 'Invalid action'], 400);
?>

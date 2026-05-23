<?php
/*
 * ============================================================
 *  api/reports.php — REPORTS MODULE BACKEND
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Serves filtered lists of students and payments for reports.
 *  - Handles queries from Registrar, Cashier, and Admin report tabs.
 *
 *  HOW IT'S CALLED:
 *    api/reports.php?action=registrar&grade_level_id=All&status=All&start_date=2026-05-01&end_date=2026-05-31
 *    api/reports.php?action=cashier&payment_method_id=All&payment_mode=All&start_date=2026-05-01&end_date=2026-05-31
 * ============================================================
 */

require_once '../db.php';

// Helper function to calculate the overall status string
function calculateStatus($reg, $cash, $docs_pending = 0) {
    if ($reg === 'dropped') return 'Dropped';
    
    // IF Cashier approved BUT Registrar declined -> Still Declined but with refund option
    if ($cash === 'approved' && $reg === 'declined') return 'Declined';
    
    if ($reg === 'declined' || $cash === 'declined') return 'Declined';
    if ($cash === 'refunded') return 'Refunded';
    
    if ($reg === 'approved' && $cash === 'approved') {
        return $docs_pending ? 'Enrolled (Doc. Pending)' : 'Enrolled';
    }
    if ($reg === 'approved') return 'For Payment Review';
    if ($cash === 'approved') return 'For Application Review';
    return 'Pending';
}

$action = $_GET['action'] ?? '';

// --- Registrar Report Data ---
if ($action === 'registrar') {
    $grade_level_id = $_GET['grade_level_id'] ?? 'All';
    $status_filter  = $_GET['status'] ?? 'All';
    $start_date     = $_GET['start_date'] ?? '';
    $end_date       = $_GET['end_date'] ?? '';

    $query = "
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
            gl.id AS grade_level_id,
            sess.name AS session_preference,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.middle_name AS parent_middle_name,
            p.mobile AS parent_mobile,
            p.telephone AS parent_telephone,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN sessions sess         ON e.session_id     = sess.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if ($grade_level_id !== 'All' && $grade_level_id !== '') {
        $query .= " AND e.grade_level_id = ?";
        $params[] = intval($grade_level_id);
        $types .= "i";
    }

    if ($start_date !== '') {
        $query .= " AND DATE(e.applied_at) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }

    if ($end_date !== '') {
        $query .= " AND DATE(e.applied_at) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    $query .= " ORDER BY e.applied_at DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        sendJSON(['error' => 'Failed to prepare statement: ' . $conn->error], 500);
    }
    
    $filtered_rows = [];
    foreach ($rows as $item) {
        $item['status'] = calculateStatus($item['registrar_status'], $item['cashier_status'], $item['documents_pending']);
        // Apply status filter in PHP since status is dynamically calculated
        if ($status_filter === 'All' || $item['status'] === $status_filter) {
            $filtered_rows[] = $item;
        }
    }

    sendJSON($filtered_rows);
}

// --- Cashier Report Data ---
if ($action === 'cashier') {
    $payment_method_id = $_GET['payment_method_id'] ?? 'All';
    $payment_mode      = $_GET['payment_mode'] ?? 'All';
    $start_date        = $_GET['start_date'] ?? '';
    $end_date          = $_GET['end_date'] ?? '';

    $query = "
        SELECT 
            e.id AS enrollment_id, 
            e.applied_at,
            e.documents_pending,
            sy.label AS school_year,
            pm.name AS payment_method, 
            pm.id AS payment_method_id,
            pay.id AS payment_id,
            pay.payment_mode,
            pay.tuition_fee,
            pay.books_fee,
            pay.reference_number,
            pay.applied_at AS payment_applied_at,
            COALESCE(pay.tuition_fee, 0) + COALESCE(pay.books_fee, 0) as total_amount,
            COALESCE((SELECT SUM(amount_paid) FROM payment_transactions WHERE payment_id = pay.id), 0) as total_paid,
            s.student_no, 
            s.first_name, 
            s.last_name,
            s.middle_name,
            s.suffix,
            gl.name AS grade_level,
            gl.id AS grade_level_id,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.middle_name AS parent_middle_name,
            p.mobile AS parent_mobile,
            p.telephone AS parent_telephone,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Registrar' ORDER BY created_at DESC LIMIT 1), 'pending') AS registrar_status,
            COALESCE((SELECT decision FROM enrollment_reviews WHERE enrollment_id = e.id AND review_type = 'Cashier' ORDER BY created_at DESC LIMIT 1), 'pending') AS cashier_status
        FROM enrollments e
        JOIN students s            ON e.student_id    = s.id
        JOIN parents p             ON s.parent_id     = p.id
        JOIN grade_levels gl       ON e.grade_level_id = gl.id
        JOIN school_years sy       ON e.school_year_id = sy.id
        LEFT JOIN payments pay     ON e.id             = pay.enrollment_id
        LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if ($payment_method_id !== 'All' && $payment_method_id !== '') {
        $query .= " AND pay.payment_method_id = ?";
        $params[] = intval($payment_method_id);
        $types .= "i";
    }

    if ($payment_mode !== 'All' && $payment_mode !== '') {
        $query .= " AND pay.payment_mode = ?";
        $params[] = $payment_mode;
        $types .= "s";
    }

    if ($start_date !== '') {
        $query .= " AND DATE(e.applied_at) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }

    if ($end_date !== '') {
        $query .= " AND DATE(e.applied_at) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }

    $query .= " ORDER BY e.applied_at DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        sendJSON(['error' => 'Failed to prepare statement: ' . $conn->error], 500);
    }

    foreach ($rows as &$item) {
        $item['status'] = calculateStatus($item['registrar_status'], $item['cashier_status'], $item['documents_pending']);
        $item['balance'] = ($item['total_amount'] ?? 0) - ($item['total_paid'] ?? 0);
    }

    sendJSON($rows);
}

sendJSON(['error' => 'Invalid action'], 400);
?>

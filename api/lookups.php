<?php
/*
 * ============================================================
 *  api/lookups.php — DROPDOWN DATA
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Provides data for dropdown menus in the enrollment form.
 *  - The frontend (HTML/JS) calls this file to get the list
 *    of grade levels, sessions, payment methods, and custom fields.
 *
 *  HOW TO USE (from JavaScript):
 *    const grades   = await apiGet('api/lookups.php?action=grade-levels');
 *    const modes    = await apiGet('api/lookups.php?action=payment-modes');
 *    const fields   = await apiGet('api/lookups.php?action=form-fields&step=1');
 * ============================================================
 */

require_once '../db.php';

$action = $_GET['action'] ?? '';

// --- Grade Levels ---
if ($action === 'grade-levels') {
    $result = $conn->query("SELECT id, name FROM grade_levels ORDER BY sort_order");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- Parent Relationships ---
if ($action === 'relations') {
    $result = $conn->query("SELECT id, name FROM relations ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- Income Ranges ---
if ($action === 'income-ranges') {
    $result = $conn->query("SELECT id, range_label FROM income_ranges ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- Sessions ---
if ($action === 'sessions') {
    $result = $conn->query("SELECT id, name, eligible_grades, note FROM sessions ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- Payment Methods ---
if ($action === 'payment-methods') {
    $result = $conn->query("SELECT id, name, details FROM payment_methods ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- Payment Modes (dynamic, configurable by admin, mapped to grade levels) ---
if ($action === 'payment-modes') {
    $grade_level_id = isset($_GET['grade_level_id']) ? intval($_GET['grade_level_id']) : 0;
    if ($grade_level_id > 0) {
        $stmt = $conn->prepare("SELECT id, grade_level_id, name, description, installment_count, installment_amount, tuition_fee, books_fee FROM payment_modes WHERE grade_level_id = ? AND is_active = 1 ORDER BY sort_order, id");
        $stmt->bind_param("i", $grade_level_id);
        $stmt->execute();
        sendJSON($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    } else {
        $result = $conn->query("SELECT id, grade_level_id, name, description, installment_count, installment_amount, tuition_fee, books_fee FROM payment_modes WHERE is_active = 1 ORDER BY grade_level_id, sort_order, id");
        sendJSON($result->fetch_all(MYSQLI_ASSOC));
    }
}

// --- Custom Form Fields (per step) ---
if ($action === 'form-fields') {
    $step = isset($_GET['step']) ? intval($_GET['step']) : 0;
    if ($step > 0) {
        $stmt = $conn->prepare("SELECT id, field_name, field_label, field_type, field_options, is_required, placeholder, hint_text FROM form_fields WHERE step = ? AND is_active = 1 ORDER BY sort_order, id");
        $stmt->bind_param("i", $step);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        // Decode field_options JSON for select types
        foreach ($rows as &$row) {
            if ($row['field_type'] === 'select' && $row['field_options']) {
                $row['field_options'] = json_decode($row['field_options'], true) ?: [];
            } else {
                $row['field_options'] = [];
            }
        }
        sendJSON($rows);
    } else {
        // All steps combined (for admin preview)
        $result = $conn->query("SELECT id, step, field_name, field_label, field_type, field_options, is_required, is_active, sort_order, placeholder, hint_text FROM form_fields ORDER BY step, sort_order, id");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            if ($row['field_type'] === 'select' && $row['field_options']) {
                $row['field_options'] = json_decode($row['field_options'], true) ?: [];
            } else {
                $row['field_options'] = [];
            }
        }
        sendJSON($rows);
    }
}

// --- Employee Roles ---
if ($action === 'roles') {
    $result = $conn->query("SELECT id, name FROM roles ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- Student Lookup (for re-enrollment by student number) ---
if ($action === 'student-lookup') {
    $student_no = $_GET['student_no'] ?? '';
    if (empty($student_no)) {
        sendJSON(['error' => 'Student number is required.'], 400);
    }
    $stmt = $conn->prepare("
        SELECT 
            s.id AS student_id, s.student_no, s.first_name, s.last_name, s.middle_name, s.suffix,
            s.birth_date, s.gender, s.religion,
            s.house_no_street, s.barangay, s.city_municipality, s.province,
            s.previous_school, s.picture_2x2,
            p.id AS parent_id, p.first_name AS parent_first_name, p.last_name AS parent_last_name,
            p.middle_name AS parent_middle_name, p.relation_id,
            p.mobile AS parent_contact, p.telephone AS parent_telephone,
            p.occupation, p.income_range_id, p.email,
            r.name AS relation_name,
            ir.range_label AS income_label
        FROM students s
        JOIN parents p ON s.parent_id = p.id
        LEFT JOIN relations r ON p.relation_id = r.id
        LEFT JOIN income_ranges ir ON p.income_range_id = ir.id
        WHERE s.student_no = ? AND s.status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param("s", $student_no);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if (!$result) {
        sendJSON(['error' => 'Student does not exist.'], 404);
    }
    sendJSON($result);
}

// --- School Years ---
if ($action === 'school-years') {
    $result = $conn->query("SELECT id, label, is_current FROM school_years ORDER BY id DESC");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

sendJSON(['error' => 'Invalid action'], 400);
?>

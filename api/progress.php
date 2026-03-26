<?php
/**
 * api/progress.php - CHECK ENROLLMENT STATUS
 * ---------------------------------------------------------
 * This file allows a parent to login and see if their child's
 * enrollment has been approved or is still pending.
 * ---------------------------------------------------------
 */

require_once '../db.php';

// 1. Receive Login Data (Email and Password)
// Since the frontend sends this as JSON, we use file_get_contents('php://input')
$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (!$email || !$password) {
    sendJSON(['error' => 'Email and password are required.'], 400);
}

// 2. Search for the Parent and their Student info
$sql = "SELECT 
            s.student_no,
            s.first_name,
            s.last_name,
            gl.name AS grade_level,
            p.full_name AS parent_name,
            es.name AS status,
            e.applied_at
        FROM parents p
        JOIN enrollments e ON e.parent_id = p.id
        JOIN students s ON e.student_id = s.id
        JOIN grade_levels gl ON e.grade_level_id = gl.id
        JOIN enrollment_statuses es ON e.status_id = es.id
        WHERE p.email = ? AND p.password = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$email, $password]);
$row = $stmt->fetch();

// 3. Send the result back
if ($row) {
    sendJSON($row);
} else {
    sendJSON(['error' => 'No account found. Please check your email and password.'], 404);
}
?>

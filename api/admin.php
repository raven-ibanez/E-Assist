<?php
/**
 * api/admin.php - ADMIN DASHBOARD BACKEND
 * ---------------------------------------------------------
 * This file handles all tasks for the school administrator,
 * including logging in and managing student enrollments.
 * ---------------------------------------------------------
 */

require_once '../db.php';

// Determine what the admin wants to do
$action = $_GET['action'] ?? '';
// Receive any JSON data sent by the dashboard
$data = json_decode(file_get_contents('php://input'), true);

// ---------------------------------------------------------
// 1. ADMIN LOGIN
// ---------------------------------------------------------
if ($action === 'login') {
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $admin = $stmt->fetch();

    if ($admin) {
        sendJSON(['message' => 'Login successful', 'username' => $admin['username']]);
    } else {
        sendJSON(['error' => 'Invalid admin credentials.'], 401);
    }
}

// ---------------------------------------------------------
// 2. GET ALL STUDENTS (For the Dashboard Table)
// ---------------------------------------------------------
if ($action === 'students') {
    $sql = "SELECT 
                e.id AS enrollment_id, e.applied_at,
                s.student_no, s.first_name, s.last_name,
                gl.name AS grade_level,
                p.full_name AS parent_name,
                es.name AS status
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN parents p ON e.parent_id = p.id
            JOIN grade_levels gl ON e.grade_level_id = gl.id
            JOIN enrollment_statuses es ON e.status_id = es.id
            ORDER BY e.applied_at DESC";
            
    $stmt = $pdo->query($sql);
    sendJSON($stmt->fetchAll());
}

// ---------------------------------------------------------
// 3. GET SINGLE STUDENT DETAIL
// ---------------------------------------------------------
if ($action === 'detail') {
    $id = $_GET['id'] ?? '';
    $sql = "SELECT 
                e.id AS enrollment_id, e.applied_at,
                s.student_no, s.first_name, s.last_name,
                s.birth_date, s.gender, s.address,
                gl.name AS grade_level,
                p.full_name AS parent_name,
                p.contact_no AS parent_contact,
                p.email,
                es.name AS status
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN parents p ON e.parent_id = p.id
            JOIN grade_levels gl ON e.grade_level_id = gl.id
            JOIN enrollment_statuses es ON e.status_id = es.id
            WHERE e.id = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if ($row) {
        sendJSON($row);
    } else {
        sendJSON(['error' => 'Enrollment not found.'], 404);
    }
}

// ---------------------------------------------------------
// 4. APPROVE OR REJECT A STUDENT
// ---------------------------------------------------------
if ($action === 'validate') {
    $id = $_GET['id'] ?? '';
    $status_name = $data['action'] ?? ''; // 'Approved' or 'Rejected'

    // Look up the ID of the status (e.g., Approved = 2, Rejected = 3)
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

// Default error message
sendJSON(['error' => 'Invalid admin action'], 400);
?>

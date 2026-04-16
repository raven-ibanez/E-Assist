<?php
/*
 * ============================================================
 *  api/lookups.php — DROPDOWN DATA
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Provides data for dropdown menus in the enrollment form.
 *  - The frontend (HTML/JS) calls this file to get the list
 *    of grade levels and parent relationships.
 *
 *  HOW TO USE (from JavaScript):
 *    const grades = await apiGet('api/lookups.php?action=grade-levels');
 *    const relations = await apiGet('api/lookups.php?action=relations');
 * ============================================================
 */

// Connect to the database
require_once '../db.php';

// Read the "action" from the URL (e.g., ?action=grade-levels)
$action = $_GET['action'] ?? '';

// --- ACTION: Get all grade levels (for the enrollment form dropdown) ---
if ($action === 'grade-levels') {
    $result = $conn->query("SELECT id, name FROM grade_levels ORDER BY sort_order");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- ACTION: Get all relationships (Mother, Father, Guardian, etc.) ---
if ($action === 'relations') {
    $result = $conn->query("SELECT id, name FROM relations ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- ACTION: Get all income ranges ---
if ($action === 'income-ranges') {
    $result = $conn->query("SELECT id, range_label FROM income_ranges ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- ACTION: Get all sessions ---
if ($action === 'sessions') {
    $result = $conn->query("SELECT id, name FROM sessions ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- ACTION: Get all payment methods ---
if ($action === 'payment-methods') {
    $result = $conn->query("SELECT id, name FROM payment_methods ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// --- ACTION: Get all employee roles ---
if ($action === 'roles') {
    $result = $conn->query("SELECT id, name FROM roles ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}

// If no valid action was provided, return an error
sendJSON(['error' => 'Invalid action'], 400);
?>

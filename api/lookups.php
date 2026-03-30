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
    $stmt = $pdo->query("SELECT id, name FROM grade_levels ORDER BY sort_order");
    sendJSON($stmt->fetchAll());
}

// --- ACTION: Get all relationships (Mother, Father, Guardian, etc.) ---
if ($action === 'relations') {
    $stmt = $pdo->query("SELECT id, name FROM relations ORDER BY id");
    sendJSON($stmt->fetchAll());
}

// If no valid action was provided, return an error
sendJSON(['error' => 'Invalid action'], 400);
?>

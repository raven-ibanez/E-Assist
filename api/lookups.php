<?php
/**
 * api/lookups.php - DATA LOOKUP HELPER
 * ---------------------------------------------------------
 * This file helps the website get lists of things from the database,
 * like Grade Levels or Parent Relations.
 * ---------------------------------------------------------
 */

require_once '../db.php';

// Get the "action" from the URL (e.g., lookups.php?action=grade-levels)
$action = $_GET['action'] ?? '';

// 1. Get List of Grade Levels
if ($action === 'grade-levels') {
    $stmt = $pdo->query("SELECT id, name FROM grade_levels ORDER BY sort_order");
    sendJSON($stmt->fetchAll());
} 

// 2. Get List of Parent Relations (Father, Mother, etc.)
if ($action === 'relations') {
    $stmt = $pdo->query("SELECT id, name FROM relations ORDER BY id");
    sendJSON($stmt->fetchAll());
}

// If no valid action was found
sendJSON(['error' => 'Invalid lookup action'], 400);
?>

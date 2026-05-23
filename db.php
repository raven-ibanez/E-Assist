<?php
/*
 * ============================================================
 *  db.php — DATABASE CONNECTION
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Connects PHP to the MySQL database using MySQLi (Object-Oriented).
 *  - Provides a helper function (sendJSON) to send data back
 *    to the browser in JSON format.
 *
 *  This file is "included" by other PHP files using:
 *      require_once '../db.php';
 * ============================================================
 */

// --- STEP 1: Hide PHP errors from showing on the page ---
// ob_start() captures any accidental output so it doesn't break our JSON responses.
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Global exception handler to guarantee that any uncaught PHP Error or Exception
// is returned as a clean JSON response rather than crashing with an empty response.
set_exception_handler(function ($exception) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Server error: ' . $exception->getMessage()
    ]);
    exit;
});

// Disable mysqli exception reporting (standard for codebases expecting execute() to return false).
// This ensures local database validation and duplicate check code works as intended.
mysqli_report(MYSQLI_REPORT_OFF);


// --- STEP 2: Database login details ---
// These must match your XAMPP MySQL settings.
$host = 'localhost';       // The server (localhost = your computer)
$db = 'enrollment_db';  // The database name
$user = 'root';            // MySQL username (default for XAMPP)
$pass = '';                // MySQL password (empty by default in XAMPP)
$charset = 'utf8mb4';        // Character encoding (supports emojis, etc.)

// --- STEP 3: Connect to the database using MySQLi ---
// Create the connection. We use "$conn" everywhere to talk to the database.
$conn = new mysqli($host, $user, $pass, $db);

// --- STEP 4: Check for connection errors ---
if ($conn->connect_error) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// --- STEP 5: Set character set (supports emojis, etc.) ---
$conn->set_charset($charset);

/*
 * --- HELPER FUNCTION: sendJSON() ---
 * Sends a PHP array back to the browser as JSON.
 *
 * Example usage:
 *   sendJSON(['message' => 'Success!']);         → sends {"message":"Success!"}
 *   sendJSON(['error' => 'Not found.'], 404);    → sends 404 error with {"error":"Not found."}
 */
function sendJSON($data, $status = 200)
{
    ob_clean();                        // Clear any accidental output
    http_response_code($status);       // Set HTTP status (200 = OK, 400 = Bad Request, etc.)
    header('Content-Type: application/json');  // Tell browser this is JSON
    echo json_encode($data);           // Convert PHP array to JSON string
    exit;                              // Stop the script
}
?>
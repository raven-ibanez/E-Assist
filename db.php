<?php
/**
 * db.php - DATABASE CONNECTION SETTINGS
 * ---------------------------------------------------------
 * This file handles the connection between PHP and your MySQL database.
 * We use "PDO" (PHP Data Objects) because it is secure and modern.
 * ---------------------------------------------------------
 */

// 1. Error Reporting Configuration
// ob_start() captures output so we can clean it before sending JSON errors.
ob_start(); 
error_reporting(E_ALL);
// We hide errors from the screen for security, but they still happen in the background.
ini_set('display_errors', 0); 

// 2. Database Credentials
// These are the details needed to talk to your XAMPP MySQL server.
$host = 'localhost';
$db   = 'enrollment_db';
$user = 'root';
$pass = ''; // XAMPP default password is empty
$charset = 'utf8mb4';

// 3. The Connection String (DSN)
// This tells PHP where the database is and what name it has.
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// 4. PDO Options
// These settings make the database connection "behave" better.
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Show errors as "Exceptions" so we can catch them.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return data as an associative array (e.g., ['id' => 1]).
    PDO::ATTR_EMULATE_PREPARES   => false,                 // Use real prepared statements for better security.
];

// 5. Try to Connect
try {
    // Create the connection object called $pdo
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If connection fails, stop everything and send a JSON error message.
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

/**
 * sendJSON($data, $status)
 * A helper function to send data back to the JavaScript (frontend) in a clean format.
 */
function sendJSON($data, $status = 200) {
    ob_clean(); // Clear any accidental text output
    http_response_code($status); // Set the HTTP status (e.g., 200 OK or 400 Bad Request)
    header('Content-Type: application/json'); // Tell the browser this is JSON data
    echo json_encode($data); // Convert the PHP array to a JSON string
    exit; // Stop the script
}
?>

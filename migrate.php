<?php
require_once 'db.php';

$errors = [];
$steps = [];

// 1. Drop columns tuition_fee and books_fee from grade_levels if they exist
$checkTuition = $conn->query("SHOW COLUMNS FROM grade_levels LIKE 'tuition_fee'");
if ($checkTuition->num_rows > 0) {
    $conn->query("ALTER TABLE grade_levels DROP COLUMN tuition_fee");
    $steps[] = 'Removed tuition_fee from grade_levels: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'tuition_fee in grade_levels: already removed';
}

$checkBooks = $conn->query("SHOW COLUMNS FROM grade_levels LIKE 'books_fee'");
if ($checkBooks->num_rows > 0) {
    $conn->query("ALTER TABLE grade_levels DROP COLUMN books_fee");
    $steps[] = 'Removed books_fee from grade_levels: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'books_fee in grade_levels: already removed';
}

// 2. Drop existing payment_modes table
$conn->query("DROP TABLE IF EXISTS payment_modes");
$steps[] = 'Dropped old payment_modes table: ' . ($conn->error ?: 'OK');

// 3. Create payment_modes table with grade_level_id
$sql = "CREATE TABLE payment_modes (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    grade_level_id     INT NOT NULL,
    name               VARCHAR(100) NOT NULL,
    description        VARCHAR(255) DEFAULT NULL,
    installment_count  INT DEFAULT NULL,
    installment_amount DECIMAL(10,2) DEFAULT NULL,
    tuition_fee        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    books_fee          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    sort_order         INT NOT NULL DEFAULT 0,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE CASCADE,
    UNIQUE KEY uq_grade_mode (grade_level_id, name)
)";
$conn->query($sql);
$steps[] = 'payment_modes table (grade-level associated): ' . ($conn->error ?: 'OK');

// 4. Seed default payment modes per grade level dynamically
$gradeLevelsResult = $conn->query("SELECT id, name FROM grade_levels");
if ($gradeLevelsResult) {
    while ($gl = $gradeLevelsResult->fetch_assoc()) {
        $glId = $gl['id'];
        $glName = $gl['name'];

        // Determine default fees
        $fullTuition = 23500.00;
        $booksFee = 7000.00;
        $instAmount = 1700.00;

        if (strtolower($glName) === 'kinder') {
            $fullTuition = 21000.00;
            $booksFee = 5000.00;
            $instAmount = 1500.00;
        } elseif (in_array(strtolower($glName), ['grade 1', 'grade 2', 'grade 3'])) {
            $fullTuition = 23500.00;
            $booksFee = 6500.00;
            $instAmount = 1700.00;
        }

        // Seed Full Payment
        $stmt = $conn->prepare("INSERT INTO payment_modes (grade_level_id, name, description, installment_count, installment_amount, tuition_fee, books_fee, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)");
        $nameFull = 'Full Payment';
        $descFull = 'Pay the entire amount at once';
        $countFull = null;
        $amountFull = null;
        $stmt->bind_param("issdddd", $glId, $nameFull, $descFull, $countFull, $amountFull, $fullTuition, $booksFee);
        $stmt->execute();

        // Seed Monthly Payment
        $stmt2 = $conn->prepare("INSERT INTO payment_modes (grade_level_id, name, description, installment_count, installment_amount, tuition_fee, books_fee, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 2)");
        $nameMonthly = 'Monthly';
        $descMonthly = 'Pay in 10 monthly installments';
        $countMonthly = 10;
        $totalTuitionMonthly = $instAmount * 10;
        $stmt2->bind_param("issdddd", $glId, $nameMonthly, $descMonthly, $countMonthly, $instAmount, $totalTuitionMonthly, $booksFee);
        $stmt2->execute();
    }
    $steps[] = 'Seeded default payment modes for grade levels: OK';
} else {
    $steps[] = 'Seeding skipped: failed to query grade levels';
}

// 5. Ensure payments.payment_mode_id constraint (optional check)
$check = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_mode_id'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN payment_mode_id INT DEFAULT NULL AFTER payment_mode");
    $steps[] = 'Added payment_mode_id column to payments: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'payment_mode_id column in payments: already exists';
}

// 6. Add status column to students table if it doesn't exist
$checkStudentStatus = $conn->query("SHOW COLUMNS FROM students LIKE 'status'");
if ($checkStudentStatus->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN status ENUM('active', 'archived', 'deleted') NOT NULL DEFAULT 'active' AFTER picture_2x2");
    $steps[] = 'Added status column to students table: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'status column in students: already exists';
}

// 7. Add status column to admin table if it doesn't exist
$checkAdminStatus = $conn->query("SHOW COLUMNS FROM admin LIKE 'status'");
if ($checkAdminStatus->num_rows === 0) {
    $conn->query("ALTER TABLE admin ADD COLUMN status ENUM('active', 'archived', 'deleted') NOT NULL DEFAULT 'active' AFTER is_active");
    $steps[] = 'Added status column to admin table: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'status column in admin: already exists';
}

// 8. Add enrollment_type column to enrollments table if it doesn't exist
$checkEnrollType = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'enrollment_type'");
if ($checkEnrollType->num_rows === 0) {
    $conn->query("ALTER TABLE enrollments ADD COLUMN enrollment_type ENUM('new', 'returning') NOT NULL DEFAULT 'new' AFTER session_id");
    $steps[] = 'Added enrollment_type column to enrollments table: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'enrollment_type column in enrollments: already exists';
}

// 9. Add report_card column to enrollments table if it doesn't exist
$checkReportCard = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'report_card'");
if ($checkReportCard->num_rows === 0) {
    $conn->query("ALTER TABLE enrollments ADD COLUMN report_card VARCHAR(255) DEFAULT NULL AFTER enrollment_type");
    $steps[] = 'Added report_card column to enrollments table: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'report_card column in enrollments: already exists';
}

// 10. Add clearance column to enrollments table if it doesn't exist
$checkClearance = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'clearance'");
if ($checkClearance->num_rows === 0) {
    $conn->query("ALTER TABLE enrollments ADD COLUMN clearance VARCHAR(255) DEFAULT NULL AFTER report_card");
    $steps[] = 'Added clearance column to enrollments table: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'clearance column in enrollments: already exists';
}

// 11. Add status column to enrollments table if it doesn't exist
$checkEnrollStatus = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'status'");
if ($checkEnrollStatus->num_rows === 0) {
    $conn->query("ALTER TABLE enrollments ADD COLUMN status ENUM('active', 'archived', 'deleted') NOT NULL DEFAULT 'active' AFTER documents_pending");
    $steps[] = 'Added status column to enrollments table: ' . ($conn->error ?: 'OK');
} else {
    $steps[] = 'status column in enrollments: already exists';
}

echo implode("\n", $steps) . "\nMigration complete!\n";
?>

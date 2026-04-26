-- ============================================================
--  ENROLLMENT SYSTEM — DATABASE SCHEMA
--  Database name: enrollment_db
-- ============================================================
--  HOW TO USE:
--  1. Open phpMyAdmin (http://localhost/phpmyadmin)
--  2. Click "Import" tab
--  3. Choose this file and click "Go"
--  This will create all the tables and sample data automatically.
-- ============================================================

-- Create the database (if it doesn't exist yet)
CREATE DATABASE IF NOT EXISTS enrollment_db;
USE enrollment_db;

-- ============================================================
--  CLEAN START: Remove old tables if they exist
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS payment_transactions;
DROP TABLE IF EXISTS enrollment_reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS parents;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS income_ranges;
DROP TABLE IF EXISTS relations;
DROP TABLE IF EXISTS grade_levels;
DROP TABLE IF EXISTS school_years;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  TABLE: school_years
--  Stores the available school years (e.g., 2025-2026).
--  Used in the enrollment form to tag which year a student enrolled.
-- ============================================================
CREATE TABLE IF NOT EXISTS school_years (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    label      VARCHAR(20) NOT NULL UNIQUE,   -- e.g., "2025-2026"
    is_current TINYINT(1) NOT NULL DEFAULT 0  -- 1 = currently active year
);

INSERT IGNORE INTO school_years (label, is_current) VALUES
    ('2023-2024', 0),
    ('2024-2025', 0),
    ('2025-2026', 1),
    ('2026-2027', 0),
    ('2027-2028', 0),
    ('2028-2029', 0),
    ('2029-2030', 0),
    ('2030-2031', 0);


-- ============================================================
--  TABLE: grade_levels
--  Stores the available grade levels (Kinder, Grade 1–6).
--  Used in the enrollment form dropdown.
-- ============================================================
CREATE TABLE IF NOT EXISTS grade_levels (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    sort_order INT NOT NULL
);

INSERT IGNORE INTO grade_levels (name, sort_order) VALUES
    ('Kinder',  1),
    ('Grade 1', 2),
    ('Grade 2', 3),
    ('Grade 3', 4),
    ('Grade 4', 5),
    ('Grade 5', 6),
    ('Grade 6', 7);


-- ============================================================
--  TABLE: relations
--  Defines the relationship between parent/guardian and student.
-- ============================================================
CREATE TABLE IF NOT EXISTS relations (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT IGNORE INTO relations (name) VALUES
    ('Mother'),
    ('Father'),
    ('Guardian'),
    ('Grandparent'),
    ('Other');


-- ============================================================
--  TABLE: income_ranges
--  Stores the available income ranges for parents.
-- ============================================================
CREATE TABLE IF NOT EXISTS income_ranges (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    range_label VARCHAR(100) NOT NULL UNIQUE
);

INSERT IGNORE INTO income_ranges (range_label) VALUES
    ('Below ₱10,000'),
    ('₱10,000 - ₱30,000'),
    ('₱30,000 - ₱50,000'),
    ('Above ₱50,000');


-- ============================================================
--  TABLE: sessions
--  Stores the available school sessions.
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

INSERT IGNORE INTO sessions (name) VALUES
    ('Kinder 1 (2.5 hrs)'),
    ('Kinder 2 - Session 1 (2.5 hrs)'),
    ('Kinder 2 - Session 2 (2.5 hrs)'),
    ('AM Session'),
    ('PM Session'),
    ('Morning Session'),
    ('Afternoon Session');


-- ============================================================
--  TABLE: parents
--  Stores parent/guardian information.
--  NOTE: email is NOT unique — one parent can enroll multiple children.
-- ============================================================
CREATE TABLE IF NOT EXISTS parents (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    middle_name    VARCHAR(100) DEFAULT NULL,
    relation_id    INT NOT NULL,                    -- Links to "relations" table
    contact_no     VARCHAR(20) NOT NULL,
    occupation     VARCHAR(100) DEFAULT NULL,
    income_range_id INT DEFAULT NULL,               -- Links to "income_ranges" table
    email          VARCHAR(100) NOT NULL,           -- NOT unique: same parent can enroll multiple children
    FOREIGN KEY (relation_id) REFERENCES relations(id),
    FOREIGN KEY (income_range_id) REFERENCES income_ranges(id)
);


-- ============================================================
--  TABLE: students
--  Stores student personal information.
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT NOT NULL,                  -- Links to "parents" table
    student_no      VARCHAR(20) NOT NULL UNIQUE,   -- e.g., "2026-00001"
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    middle_name     VARCHAR(100) DEFAULT NULL,
    suffix          VARCHAR(10) DEFAULT NULL,
    birth_date      DATE NOT NULL,
    gender          ENUM('Male', 'Female') NOT NULL,
    religion        VARCHAR(100) DEFAULT NULL,
    house_no_street VARCHAR(255) NOT NULL,
    barangay        VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province        VARCHAR(100) NOT NULL,
    previous_school VARCHAR(255) DEFAULT NULL,      -- Only for transferees
    psa_birth_cert  VARCHAR(255) DEFAULT NULL,      -- File path to uploaded PSA
    sf10_document   VARCHAR(255) DEFAULT NULL,      -- File path to uploaded SF10
    picture_2x2     VARCHAR(255) NOT NULL,      -- File path to uploaded 2x2 Picture (Required)
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payment_methods (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT IGNORE INTO payment_methods (name) VALUES
    ('GCash'),
    ('Cash'),
    ('Bank Transfer');

-- ============================================================
--  TABLE: enrollments
--  Links a student to their school year and grade.
--  This is the main table the registrar and cashier look at.
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    student_id         INT NOT NULL,
    school_year_id     INT NOT NULL,               -- Links to "school_years" table
    grade_level_id     INT NOT NULL,
    session_id         INT NOT NULL,               -- Links to "sessions" table
    documents_pending  TINYINT(1) NOT NULL DEFAULT 0, -- 1 = Approved but documents still needed
    applied_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)         REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (school_year_id)     REFERENCES school_years(id),
    FOREIGN KEY (grade_level_id)     REFERENCES grade_levels(id),
    FOREIGN KEY (session_id)         REFERENCES sessions(id)
);

-- ============================================================
--  TABLE: payments
--  Stores the total fees and payment plan for an enrollment.
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id      INT NOT NULL,
    payment_method_id  INT NOT NULL,
    payment_mode      ENUM('Full', 'Monthly') NOT NULL DEFAULT 'Monthly',
    months_count      INT DEFAULT NULL,             -- For Monthly mode: usually 10 months
    tuition_fee       DECIMAL(10,2) NOT NULL,       -- TOTAL tuition amount
    books_fee         DECIMAL(10,2) NOT NULL,       -- TOTAL books fee
    reference_number  VARCHAR(100) DEFAULT NULL,    -- Initial payment reference
    applied_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id)     REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
);

-- ============================================================
--  TABLE: payment_transactions
--  Records each actual payment made by the student (installments).
-- ============================================================
CREATE TABLE IF NOT EXISTS payment_transactions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    payment_id     INT NOT NULL,
    amount_paid    DECIMAL(10,2) NOT NULL,
    payment_method_id INT NOT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    notes          VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
);


-- ============================================================
--  TABLE: roles (employee roles)
--  Defines the different access levels for system accounts.
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
);

INSERT IGNORE INTO roles (name) VALUES
    ('admin'),
    ('registrar'),
    ('cashier');

-- ============================================================
--  TABLE: admin (employee accounts)
--  Stores login credentials for the system employees.
--  Passwords are stored as bcrypt hashes (PASSWORD_BCRYPT).
--  The "role_id" column determines what dashboard they can access.
-- ============================================================
CREATE TABLE IF NOT EXISTS admin (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,               -- bcrypt hash
    role_id  INT NOT NULL DEFAULT 2,              -- Links to "roles" table (default: registrar)
    is_active TINYINT(1) NOT NULL DEFAULT 1,      -- 1 = active, 0 = deactivated
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Default employee accounts (passwords are bcrypt hashes, case-sensitive)
-- Generated with: password_hash('plaintext', PASSWORD_DEFAULT)
-- Plaintext => admin=admin123, registrar=registrar123, cashier=cashier123
INSERT IGNORE INTO admin (username, password, role_id, is_active) VALUES
    ('admin',     '$2y$10$t.PZskHjxJEAPVlNFmgS8uFz3ywhVIyKILEgyOLNShY1QyeRpwoNC', 1, 1),
    ('registrar', '$2y$10$d.UDomEf9f2yxWlEmEiZGefPkqIGypqTsXx./ev89vwpgZsLWB2RC', 2, 1),
    ('cashier',   '$2y$10$Wmk0h.er4bfoI7jd.3ipzenn42.2CTXFnoowtTahhJfFwtY8GPZlW', 3, 1);
-- IMPORTANT: Passwords are bcrypt hashed and case-sensitive.
-- To reset a password, delete the row and re-insert, or run:
--   UPDATE admin SET password = '$new_hash' WHERE username = 'xxx';


-- ============================================================
--  TABLE: enrollment_reviews
--  Records administrative decisions (middle man).
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollment_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    admin_id      INT NOT NULL,
    review_type   ENUM('Registrar', 'Cashier') NOT NULL,
    decision      ENUM('approved', 'declined', 'dropped', 'refunded') NOT NULL,
    notes         TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)      REFERENCES admin(id) ON DELETE CASCADE
);


-- ============================================================
--  TABLE: system_logs
--  Tracks all administrative actions for auditing purposes.
-- ============================================================
CREATE TABLE IF NOT EXISTS system_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT NOT NULL,
    action_type VARCHAR(100) NOT NULL, -- e.g., "Registrar Approved", "Cashier Refunded", "Student Update"
    target_id   INT DEFAULT NULL,     -- Enrollment ID or Student ID
    target_name VARCHAR(255) DEFAULT NULL, -- Student Name for quick reference
    details     TEXT DEFAULT NULL,    -- Extra info
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
);

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
DROP TABLE IF EXISTS admin_logs;
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
SET FOREIGN_KEY_CHECKS = 1;
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
-- ============================================================
CREATE TABLE IF NOT EXISTS parents (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    middle_name    VARCHAR(100) DEFAULT NULL,
    relation_id    INT NOT NULL,                    -- Links to "relations" table
    contact_no     VARCHAR(20) NOT NULL,
    occupation     VARCHAR(100) DEFAULT NULL,
    income_range_id INT DEFAULT NULL,                -- Links to "income_ranges" table
    email          VARCHAR(100) NOT NULL UNIQUE,
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
    address         TEXT NOT NULL,
    previous_school VARCHAR(255) DEFAULT NULL,      -- Only for transferees
    psa_birth_cert  VARCHAR(255) DEFAULT NULL,      -- File path to uploaded PSA
    sf10_document   VARCHAR(255) DEFAULT NULL,      -- File path to uploaded SF10
    picture_2x2     VARCHAR(255) DEFAULT NULL,      -- File path to uploaded 2x2 Picture
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
--  Links a student to their parent with enrollment details.
--  This is the main table the registrar and cashier look at.
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    student_id         INT NOT NULL,
    grade_level_id     INT NOT NULL,
    session_id         INT NOT NULL,               -- Links to "sessions" table
    applied_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)         REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (grade_level_id)     REFERENCES grade_levels(id),
    FOREIGN KEY (session_id)         REFERENCES sessions(id)
);

-- ============================================================
--  TABLE: payments
--  Stores the specific transaction details for an enrollment.
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id      INT NOT NULL,
    payment_method_id  INT NOT NULL,
    payment_mode      ENUM('Full', 'Monthly') NOT NULL DEFAULT 'Monthly',
    reference_number  VARCHAR(100) DEFAULT NULL,
    applied_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id)     REFERENCES enrollments(id) ON DELETE CASCADE,
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
--  The "role_id" column determines what dashboard they can access.
-- ============================================================
CREATE TABLE IF NOT EXISTS admin (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id  INT NOT NULL DEFAULT 2,           -- Links to "roles" table (default: registrar)
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Default employee accounts
INSERT IGNORE INTO admin (username, password, role_id) VALUES ('admin', 'admin123', 1);
INSERT IGNORE INTO admin (username, password, role_id) VALUES ('registrar', 'registrar123', 2);
INSERT IGNORE INTO admin (username, password, role_id) VALUES ('cashier', 'cashier123', 3);


-- ============================================================
--  TABLE: enrollment_reviews
--  Records administrative decisions (middle man).
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollment_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    admin_id      INT NOT NULL,
    review_type   ENUM('Registrar', 'Cashier') NOT NULL,
    decision      ENUM('approved', 'declined') NOT NULL,
    notes         TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)      REFERENCES admin(id) ON DELETE CASCADE
);

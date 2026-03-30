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
--  TABLE: enrollment_statuses
--  The possible statuses of an enrollment application.
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollment_statuses (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT IGNORE INTO enrollment_statuses (id, name) VALUES
    (1, 'Pending'),
    (2, 'Approved'),
    (3, 'Rejected');


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
--  TABLE: students
--  Stores student personal information.
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_no      VARCHAR(20) NOT NULL UNIQUE,   -- e.g., "2026-00001"
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    birth_date      DATE NOT NULL,
    gender          ENUM('Male', 'Female') NOT NULL,
    address         TEXT NOT NULL,
    previous_school VARCHAR(255) DEFAULT NULL,      -- Only for transferees
    psa_birth_cert  VARCHAR(255) DEFAULT NULL,      -- File path to uploaded PSA
    sf10_document   VARCHAR(255) DEFAULT NULL       -- File path to uploaded SF10
);


-- ============================================================
--  TABLE: parents
--  Stores parent/guardian information.
-- ============================================================
CREATE TABLE IF NOT EXISTS parents (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(255) NOT NULL,
    relation_id    INT NOT NULL,                    -- Links to "relations" table
    contact_no     VARCHAR(20) NOT NULL,
    occupation     VARCHAR(100) DEFAULT NULL,
    monthly_income VARCHAR(50) DEFAULT NULL,
    email          VARCHAR(100) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,            -- Stores 'N/A' (not used for login)
    FOREIGN KEY (relation_id) REFERENCES relations(id)
);


-- ============================================================
--  TABLE: enrollments
--  Links a student to their parent with enrollment details.
--  This is the main table the registrar and cashier look at.
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollments (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    student_id         INT NOT NULL,
    parent_id          INT NOT NULL,
    grade_level_id     INT NOT NULL,
    session_preference VARCHAR(100) DEFAULT NULL,  -- e.g., "AM Session"
    payment_method     VARCHAR(50) DEFAULT NULL,   -- GCash, Maya, Bank Transfer
    reference_number   VARCHAR(100) DEFAULT NULL,  -- Payment reference number
    status_id          INT NOT NULL DEFAULT 1,     -- 1 = Pending
    applied_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)     REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id)      REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    FOREIGN KEY (status_id)      REFERENCES enrollment_statuses(id)
);


-- ============================================================
--  TABLE: admin (employee accounts)
--  Stores login credentials for the system employees.
--  The "role" column determines what dashboard they can access:
--    admin     → admin-dashboard.html (full access)
--    registrar → registrar-dashboard.html (enrollment management)
--    cashier   → cashier-dashboard.html (payment viewing)
-- ============================================================
CREATE TABLE IF NOT EXISTS admin (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role     VARCHAR(20) NOT NULL DEFAULT 'registrar'
);

-- Default employee accounts
INSERT IGNORE INTO admin (username, password, role) VALUES ('admin', 'admin123', 'admin');
INSERT IGNORE INTO admin (username, password, role) VALUES ('registrar', 'registrar123', 'registrar');
INSERT IGNORE INTO admin (username, password, role) VALUES ('cashier', 'cashier123', 'cashier');

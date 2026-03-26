-- =========================================================
--  ENROLLMENT SYSTEM — MYSQL DATABASE SCHEMA
--  Database: enrollment_db
-- =========================================================

CREATE DATABASE IF NOT EXISTS enrollment_db;
USE enrollment_db;

-- ---------------------------------------------------------
-- LOOKUP TABLE 1: grade_levels
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS grade_levels (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    sort_order INT NOT NULL
);

-- Seed grade levels
INSERT IGNORE INTO grade_levels (name, sort_order) VALUES
    ('Kinder',  1),
    ('Grade 1', 2),
    ('Grade 2', 3),
    ('Grade 3', 4),
    ('Grade 4', 5),
    ('Grade 5', 6),
    ('Grade 6', 7);

-- ---------------------------------------------------------
-- LOOKUP TABLE 2: enrollment_statuses
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollment_statuses (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Seed statuses
INSERT IGNORE INTO enrollment_statuses (id, name) VALUES
    (1, 'Pending'),
    (2, 'Approved'),
    (3, 'Rejected');

-- ---------------------------------------------------------
-- LOOKUP TABLE 3: relations
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS relations (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Seed relations
INSERT IGNORE INTO relations (name) VALUES
    ('Mother'),
    ('Father'),
    ('Guardian'),
    ('Grandparent'),
    ('Other');

-- ---------------------------------------------------------
-- TABLE: students
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    student_no VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    gender     ENUM('Male', 'Female') NOT NULL,
    address    TEXT NOT NULL,
    previous_school VARCHAR(255) DEFAULT NULL,
    psa_birth_cert VARCHAR(255) DEFAULT NULL,
    sf10_document  VARCHAR(255) DEFAULT NULL
);

-- ---------------------------------------------------------
-- TABLE: parents
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS parents (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(255) NOT NULL,
    relation_id INT NOT NULL,
    contact_no  VARCHAR(20) NOT NULL,
    occupation     VARCHAR(100) DEFAULT NULL,
    monthly_income VARCHAR(50) DEFAULT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    FOREIGN KEY (relation_id) REFERENCES relations(id)
);

-- ---------------------------------------------------------
-- TABLE: enrollments
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    parent_id      INT NOT NULL,
    grade_level_id INT NOT NULL,
    session_preference VARCHAR(100) DEFAULT NULL,
    status_id      INT NOT NULL DEFAULT 1,
    applied_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    FOREIGN KEY (status_id) REFERENCES enrollment_statuses(id)
);

-- ---------------------------------------------------------
-- TABLE: admin
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Default admin account
INSERT IGNORE INTO admin (username, password) VALUES ('admin', 'admin123');

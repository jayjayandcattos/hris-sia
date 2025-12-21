-- ========================================
-- HRIS-SIA DATABASE MIGRATION
-- ========================================
-- Run this script on your BankingDB database
-- Last Updated: December 14, 2025
-- ========================================

-- ========================================
-- ROLE MANAGEMENT CHANGES
-- ========================================
-- Add managed_department_id column to user_account table
ALTER TABLE user_account 
ADD COLUMN IF NOT EXISTS managed_department_id INT DEFAULT NULL AFTER role;

-- Add foreign key constraint for department reference
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = 'BankingDB' 
    AND TABLE_NAME = 'user_account' 
    AND CONSTRAINT_NAME = 'fk_user_account_department'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE user_account ADD CONSTRAINT fk_user_account_department FOREIGN KEY (managed_department_id) REFERENCES department(department_id) ON DELETE SET NULL',
    'SELECT "Constraint already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================================
-- REMOVE MANAGER ROLE - TRANSFER TO SUPERVISOR
-- ========================================
UPDATE user_account SET role = 'Supervisor' WHERE role = 'Manager';

-- Set managed_department_id for ALL existing Supervisors based on their employee's department
-- This ensures supervisors created before this migration also get department filtering
UPDATE user_account ua
JOIN employee e ON ua.employee_id = e.employee_id
SET ua.managed_department_id = e.department_id
WHERE ua.role = 'Supervisor' 
AND (ua.managed_department_id IS NULL OR ua.managed_department_id = 0);

SELECT 'Manager role migrated to Supervisor and department IDs set!' as status;

-- ========================================
-- CLEANUP DUPLICATE ACCOUNTS
-- ========================================
-- Remove old manager/supervisor test accounts to prevent duplicates
DELETE FROM user_account WHERE username = 'manager';
DELETE FROM user_account WHERE username = 'supervisor';

-- Remove any supervisor_* accounts that might have been created previously
DELETE FROM user_account WHERE username LIKE 'supervisor_%';

SELECT 'Cleaned up duplicate accounts!' as status;

-- ========================================
-- ROLE CHANGE AUDIT LOG TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS role_change_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    changed_by INT NOT NULL,
    old_role VARCHAR(20) DEFAULT NULL,
    new_role VARCHAR(20) NOT NULL,
    old_department_id INT DEFAULT NULL,
    new_department_id INT DEFAULT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_changed_at (changed_at),
    FOREIGN KEY (user_id) REFERENCES user_account(user_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES user_account(user_id) ON DELETE CASCADE,
    FOREIGN KEY (old_department_id) REFERENCES department(department_id) ON DELETE SET NULL,
    FOREIGN KEY (new_department_id) REFERENCES department(department_id) ON DELETE SET NULL
);

-- ========================================
-- ADD LOAN OFFICER POSITION
-- ========================================
INSERT INTO `position` (position_id, position_title, job_description, salary_grade) 
VALUES (25, 'Loan Officer', 'Loan Officer - Reviews and approves loan applications', 8)
ON DUPLICATE KEY UPDATE position_title = 'Loan Officer';

SELECT 'Loan Officer position added!' as status;

-- ========================================
-- CLEANUP AND ENSURE ALL DEPARTMENTS EXIST
-- ========================================
-- Disable foreign key checks to allow duplicate removal
SET FOREIGN_KEY_CHECKS = 0;

-- Remove duplicate departments (keep lowest ID)
DELETE d1 FROM department d1
INNER JOIN department d2 
WHERE d1.department_id > d2.department_id 
AND d1.department_name = d2.department_name;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Now insert departments (will skip if exists due to ON DUPLICATE KEY)
INSERT INTO department (department_name) VALUES 
    ('Customer Service'),
    ('Finance'),
    ('Human Resources'),
    ('IT'),
    ('Marketing'),
    ('Operations'),
    ('Sales')
ON DUPLICATE KEY UPDATE department_name = VALUES(department_name);

SELECT 'Departments cleaned and verified!' as status;

-- ========================================
-- CREATE SUPERVISORS FOR EACH DEPARTMENT
-- ========================================
-- Each supervisor is linked to a UNIQUE employee from that department
-- Password for all supervisors: 'password'

-- Supervisor for Customer Service - employee from CS department
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_cs',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'Customer Service'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

-- Supervisor for Finance - employee from Finance department  
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_finance',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'Finance'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

-- Supervisor for Human Resources - employee from HR department
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_hr',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'Human Resources'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

-- Supervisor for IT - employee from IT department
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_it',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'IT'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

-- Supervisor for Marketing - employee from Marketing department
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_marketing',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'Marketing'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

-- Supervisor for Operations - employee from Operations department
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_operations',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'Operations'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

-- Supervisor for Sales - employee from Sales department
INSERT INTO user_account (employee_id, username, password_hash, role, managed_department_id)
SELECT 
    e.employee_id,
    'supervisor_sales',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Supervisor',
    d.department_id
FROM employee e
JOIN department d ON d.department_name = 'Sales'
WHERE e.department_id = d.department_id AND e.employment_status = 'Active'
AND NOT EXISTS (SELECT 1 FROM user_account ua WHERE ua.employee_id = e.employee_id)
LIMIT 1;

SELECT 'Department supervisors created!' as status;

-- ========================================
-- EVENT PARTICIPANTS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS event_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    employee_id INT NOT NULL,
    rsvp_status ENUM('Pending', 'Accepted', 'Declined', 'Maybe') DEFAULT 'Pending',
    rsvp_date DATETIME DEFAULT NULL,
    invited_by INT DEFAULT NULL,
    notified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_id (event_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_rsvp_status (rsvp_status),
    FOREIGN KEY (event_id) REFERENCES recruitment(recruitment_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES user_account(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_participant (event_id, employee_id)
);

SELECT 'Event participants table created!' as status;

-- ========================================
-- ROLE HIERARCHY & PERMISSIONS (Updated)
-- ========================================
-- 
-- 1. Admin - Full system access
--    - Can approve all leaves
--    - Can manage all employees
--    - Can assign user roles
--    - Can view all departments
--
-- 2. HR Manager - HR administrative access
--    - Can approve all leaves
--    - Can view all employees
--    - Can manage recruitment
--    - Cannot assign roles
--
-- 3. Supervisor - Department-specific access
--    - Can ONLY see data from their assigned department
--    - Can approve leaves for their department ONLY
--    - Has VIEW-ONLY access to employee data (cannot edit)
--    - managed_department_id determines scope
--    - Department filtering applied to:
--      * Dashboard statistics
--      * Employee lists
--      * Attendance records
--      * Leave requests
--      * Events/Recruitment
--      * Applicants
--
-- 4. Employee - Self-service access only
--    - Via employee_dashboard.php
--    - Can view own attendance
--    - Can submit leave requests
--    - Can respond to event invitations
--
-- NOTE: Manager role has been REMOVED and merged into Supervisor
-- ========================================

-- ========================================
-- PERMISSION FUNCTIONS IN auth.php
-- ========================================
-- isSupervisor() - Check if user is supervisor
-- getUserDepartmentId($conn) - Get supervisor's department
-- canViewEmployeeData() - Supervisors can VIEW (not edit)
-- canEditEmployeeData() - Only Admin/HR can edit
-- supervisorViewOnly() - Returns true for view-only supervisors
-- canApproveLeavesForEmployee($conn, $emp_id) - Department check
-- ========================================

SELECT '=== MIGRATION COMPLETE ===' as status;
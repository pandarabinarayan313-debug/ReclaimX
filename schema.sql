CREATE DATABASE IF NOT EXISTS reclaimx_db;
USE reclaimx_db;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. OTP VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    purpose ENUM('register', 'forgot_password') NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_purpose (email, purpose),
    INDEX idx_otp (otp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ITEMS TABLE (LINKED TO USERS)
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type ENUM('lost', 'found') NOT NULL,
    title VARCHAR(255) NOT NULL,
    item VARCHAR(100) NOT NULL,
    color VARCHAR(50) NOT NULL,
    location VARCHAR(150) NOT NULL,
    date DATE NOT NULL,
    reporter_name VARCHAR(150) DEFAULT 'Anonymous',
    reporter_email VARCHAR(255) NOT NULL,
    reporter_phone VARCHAR(50) DEFAULT 'N/A',
    description TEXT,
    status ENUM('active', 'resolved') DEFAULT 'active',
    image_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_status (type, status),
    INDEX idx_matching (item, color, location),
    CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Initial Data
INSERT INTO items (type, title, item, color, location, date, reporter_name, reporter_email, reporter_phone, description, status)
VALUES 
('lost', 'Scientific Calculator Casio FX-991EX', 'Calculator', 'Black', 'Library', '2026-10-28', 'Alex Rivera', 'alex.r@college.edu', '5552345678', 'Left on the 2nd floor study desk.', 'active'),
('found', 'Casio ClassWiz FX-991EX Calculator', 'Calculator', 'Black', 'Library', '2026-10-28', 'Sarah Jenkins', 's.jenkins@college.edu', '5558765432', 'Found on 2nd-floor study cubicle #14.', 'active');
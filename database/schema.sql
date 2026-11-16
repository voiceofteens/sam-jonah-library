-- Schema inferred from the application code. Import via phpMyAdmin.
CREATE DATABASE IF NOT EXISTS sam_jonah_library CHARACTER SET utf8mb4;
USE sam_jonah_library;

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(30) NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS facilities (
  facility_id INT AUTO_INCREMENT PRIMARY KEY,
  facility_code VARCHAR(20) NOT NULL UNIQUE,
  facility_name VARCHAR(120) NOT NULL,
  facility_type VARCHAR(20) NOT NULL,
  capacity INT NOT NULL DEFAULT 1,
  location VARCHAR(150) NULL,
  buffer_minutes INT NOT NULL DEFAULT 10,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS operating_hours (
  day_of_week TINYINT PRIMARY KEY,
  opening_time TIME NULL,
  closing_time TIME NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS bookings (
  booking_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  facility_id INT NOT NULL,
  booking_type VARCHAR(12) NOT NULL DEFAULT 'individual',
  group_size INT NOT NULL DEFAULT 1,
  time_method VARCHAR(20) NOT NULL DEFAULT 'custom',
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  booking_source VARCHAR(12) NOT NULL DEFAULT 'online',
  status VARCHAR(12) NOT NULL DEFAULT 'confirmed',
  check_in_at DATETIME NULL, check_in_method VARCHAR(20) NULL,
  check_out_at DATETIME NULL, check_out_method VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conflict (facility_id, status, start_at, end_at),
  INDEX idx_user (user_id, start_at),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (facility_id) REFERENCES facilities(facility_id)
);
CREATE TABLE IF NOT EXISTS booking_audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL, actor_user_id INT NULL,
  action VARCHAR(40) NOT NULL, description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, booking_id INT NULL, message TEXT NOT NULL,
  notification_type VARCHAR(30) NOT NULL DEFAULT 'general',
  scheduled_at DATETIME NULL, sent_at DATETIME NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_unread (user_id, is_read)
);
INSERT IGNORE INTO operating_hours VALUES (1,NULL,NULL,1),(2,'09:00','21:00',0),(3,'09:00','21:00',0),(4,'09:00','21:00',0),(5,'09:00','21:00',0),(6,'09:00','21:00',0),(7,'10:00','16:00',0);

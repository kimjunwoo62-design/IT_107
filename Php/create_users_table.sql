-- SQL migration to create users table
CREATE DATABASE IF NOT EXISTS registration_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE registration_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_number VARCHAR(64) NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(10) DEFAULT NULL,
  last_name VARCHAR(100) NOT NULL,
  name_extension VARCHAR(10) DEFAULT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  birthdate DATE NOT NULL,
  age TINYINT UNSIGNED NOT NULL,
  address TEXT,

  -- Security Questions and Answers (Answers will be hashed)
  security_q1 TEXT NOT NULL,
  security_a1_hash VARCHAR(255) NOT NULL,
  security_q2 TEXT NOT NULL,
  security_a2_hash VARCHAR(255) NOT NULL,
  security_q3 TEXT NOT NULL,
  security_a3_hash VARCHAR(255) NOT NULL,

  -- Login Security
  failed_login_attempts TINYINT UNSIGNED DEFAULT 0,
  lockout_until TIMESTAMP NULL DEFAULT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

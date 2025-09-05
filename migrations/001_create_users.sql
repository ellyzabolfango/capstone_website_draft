-- =========================================================
-- Table: users
-- Purpose: Stores all system users (Admins and SK officials)
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,          -- PK

  -- Login credentials
  username   VARCHAR(50)  NOT NULL UNIQUE,                     -- system login name/to be auto generated
  password   VARCHAR(255) NOT NULL,                            -- hashed password

  -- Basic info
  fullname   VARCHAR(100) NOT NULL,
  email      VARCHAR(100) NOT NULL UNIQUE,

  -- Legacy text fields (kept for now to avoid breaking views)
  barangay   VARCHAR(100) NOT NULL,                            -- to be replaced by barangay_id
  position   VARCHAR(100) NOT NULL,                            -- to be replaced by position_id

  -- Access & status
  role       ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active  TINYINT(1) NOT NULL DEFAULT 0,                    -- 1=active, 0=inactive

  -- Timestamps
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

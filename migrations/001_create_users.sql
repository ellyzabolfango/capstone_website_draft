CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  fullname VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  location VARCHAR(100) NOT NULL,
  position VARCHAR(100) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (replace HASHED_PASSWORD with actual hash of Admin123)
INSERT INTO users (username, password, role, email)
VALUES ('admin', '$2y$10$lx9jECsIDWizj7.eLK1YT.oaacPLyLgz.OsflUaefQBwIN/lDQVPq', 'admin', 'admin@example.com');

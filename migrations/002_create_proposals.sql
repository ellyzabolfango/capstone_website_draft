CREATE TABLE IF NOT EXISTS proposals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  attachment_path VARCHAR(255) NULL,
  source VARCHAR(100),
  status ENUM('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  budget DECIMAL(12,2) DEFAULT 0.00,
  submitted_by INT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
);

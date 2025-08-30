CREATE TABLE IF NOT EXISTS proposals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  attachment_path VARCHAR(255) NULL,

  source VARCHAR(100) NULL,         -- where it came from (e.g., Barangay, NGO)
  ppa_ref VARCHAR(100) NULL,        -- optional ref to ABYIP/CBYDP item
  fiscal_year YEAR NULL,

  budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('Pending','Approved','Rejected','Completed') NOT NULL DEFAULT 'Pending',

  submitted_by INT UNSIGNED NULL,
  approved_by  INT UNSIGNED NULL,

  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status (status),
  INDEX idx_fiscal (fiscal_year),

  CONSTRAINT fk_proposals_submitter FOREIGN KEY (submitted_by)
    REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_proposals_approver FOREIGN KEY (approved_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

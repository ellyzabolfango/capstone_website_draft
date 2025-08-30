CREATE TABLE IF NOT EXISTS reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  proposal_id INT UNSIGNED NULL,              -- optional: report may or may not be per-proposal
  report_type VARCHAR(50) NOT NULL,           -- 'Summary', 'Budget', 'Status', etc.
  title VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,

  generated_by INT UNSIGNED NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_type (report_type),
  INDEX idx_proposal (proposal_id),

  CONSTRAINT fk_reports_proposal FOREIGN KEY (proposal_id)
    REFERENCES proposals(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_user FOREIGN KEY (generated_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

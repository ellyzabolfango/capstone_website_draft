CREATE TABLE IF NOT EXISTS activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,

  activity_name VARCHAR(150) NOT NULL,
  description TEXT NULL,

  start_date DATE NULL,
  end_date DATE NULL,

  status ENUM('Not Started','Ongoing','Completed') NOT NULL DEFAULT 'Not Started',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_proposal (proposal_id),

  CONSTRAINT fk_activities_proposal FOREIGN KEY (proposal_id)
    REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

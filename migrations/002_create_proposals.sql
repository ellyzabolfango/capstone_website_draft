-- =========================================================
-- Table: proposals
-- Purpose: Stores submissions (Program/Project/Activity)
-- Linked to: users (created_by, approved_by)
-- =========================================================

CREATE TABLE IF NOT EXISTS proposals (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- PK

  title              VARCHAR(150) NOT NULL,
  description        TEXT NOT NULL,
  attachment_path    VARCHAR(255) NULL,

  implementation_date DATE NULL,

  budget             DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  category           ENUM('Program','Project','Activity') NULL,

  status             ENUM('Pending','Approved','Rejected','Completed')  NOT NULL DEFAULT 'Pending',

  created_by         INT UNSIGNED NULL,
  approved_by        INT UNSIGNED NULL,

  submitted_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_proposals_creator  FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_proposals_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,

  -- Helpful indexes for dashboards & filters
  INDEX idx_proposals_status (status),
  INDEX idx_proposals_category (category),
  INDEX idx_proposals_dates (implementation_date, submitted_at),
  INDEX idx_proposals_created_by (created_by)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

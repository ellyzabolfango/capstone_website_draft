-- =========================================================
-- Table: templates
-- Purpose: Stores downloadable forms (e.g., ABYIP form)
-- =========================================================

CREATE TABLE IF NOT EXISTS templates (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  name         VARCHAR(150) NOT NULL,
  file_path    VARCHAR(255) NOT NULL,

  uploaded_by  INT UNSIGNED NULL,
  uploaded_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_templates_user FOREIGN KEY (uploaded_by)
    REFERENCES users(id) ON DELETE SET NULL,

  -- Helpful index for quick filtering by uploader
  INDEX idx_templates_uploader (uploaded_by)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

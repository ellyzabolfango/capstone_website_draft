-- =========================================================
-- Table: barangays
-- Purpose: Admin-maintained reference list of barangays
-- =========================================================

CREATE TABLE IF NOT EXISTS ref_barangays (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,                 -- e.g., "Barangay 310"

  --   city       VARCHAR(120) NOT NULL DEFAULT 'Calbayog City', 
  -- commented since system is in Calbayog City. Ready for a wider range

  is_active  TINYINT(1)  NOT NULL DEFAULT 1,        -- soft toggle
  created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uk_ref_barangays_name (name),
  INDEX idx_barangays_active (is_active)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- CREATE TABLE IF NOT EXISTS settings (
--   id INT PRIMARY KEY DEFAULT 1,
--   total_budget DECIMAL(12,2) NOT NULL DEFAULT 0.00,
--   fiscal_year YEAR NOT NULL
-- );

-- INSERT INTO settings (id, total_budget, fiscal_year)
-- VALUES (1, 500000.00, YEAR(CURDATE()))
-- ON DUPLICATE KEY UPDATE total_budget = VALUES(total_budget), fiscal_year = VALUES(fiscal_year);
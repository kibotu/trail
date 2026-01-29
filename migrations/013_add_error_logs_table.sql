-- Add error logs table for tracking HTTP errors
-- Stores URL, status code, and occurrence count
-- All text fields use prepared statements to prevent SQL injection
-- Data is stored as plain text (no HTML) to prevent XSS when viewing logs

CREATE TABLE IF NOT EXISTS trail_error_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_code SMALLINT UNSIGNED NOT NULL,
    url VARCHAR(2048) NOT NULL,
    referer VARCHAR(2048) DEFAULT NULL,
    user_agent VARCHAR(512) DEFAULT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    occurrence_count INT UNSIGNED DEFAULT 1,
    first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_code (status_code),
    INDEX idx_url (url(255)),
    INDEX idx_last_seen (last_seen_at),
    INDEX idx_user_id (user_id),
    UNIQUE KEY unique_error (status_code, url(255), user_id),
    FOREIGN KEY (user_id) REFERENCES trail_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

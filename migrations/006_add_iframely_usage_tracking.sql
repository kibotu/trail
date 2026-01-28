-- Migration: Add iframe.ly API usage tracking
-- Purpose: Track monthly API usage to enforce 2000 request/month limit
-- Created: 2026-01-28

-- Create table to track iframe.ly API usage by month
CREATE TABLE IF NOT EXISTS trail_iframely_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    month INT NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    limit_reached_at DATETIME NULL,
    notification_sent BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year_month (year, month),
    INDEX idx_year_month (year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert current month with 0 count if not exists
INSERT IGNORE INTO trail_iframely_usage (year, month, request_count)
VALUES (YEAR(NOW()), MONTH(NOW()), 0);

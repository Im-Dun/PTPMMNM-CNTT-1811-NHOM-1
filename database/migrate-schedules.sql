CREATE TABLE IF NOT EXISTS update_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    saved_page_id INT NOT NULL,
    created_by INT NOT NULL,
    frequency ENUM('hourly','daily','weekly','monthly') NOT NULL,
    run_time TIME NOT NULL DEFAULT '08:00:00',
    day_of_week TINYINT UNSIGNED NULL,
    day_of_month TINYINT UNSIGNED NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    notify_success TINYINT(1) NOT NULL DEFAULT 1,
    notify_failure TINYINT(1) NOT NULL DEFAULT 1,
    next_run_at DATETIME NOT NULL,
    last_run_at DATETIME NULL,
    last_status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY one_schedule_per_page (saved_page_id),
    KEY due_schedules (enabled, next_run_at),
    FOREIGN KEY (saved_page_id) REFERENCES saved_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_id INT NULL,
    type ENUM('success','failure') NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY user_unread (user_id, is_read, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES update_schedules(id) ON DELETE SET NULL
);

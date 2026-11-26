USE pemweb_obe_proyeksemester;

CREATE TABLE IF NOT EXISTS login_attempts (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    email_hash CHAR(64)    NOT NULL,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempt_ip_email (ip, email_hash, created_at),
    INDEX idx_attempt_ip (ip, created_at)
) ENGINE=InnoDB;
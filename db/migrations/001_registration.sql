CREATE TABLE members (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    last_name VARCHAR(80) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    pseudo VARCHAR(40) NOT NULL,
    email VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY members_email (email),
    UNIQUE KEY members_pseudo (pseudo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE member_slots (
    member_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    PRIMARY KEY (member_id, starts_at),
    KEY member_slots_starts (starts_at),
    CONSTRAINT member_slots_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    purpose VARCHAR(20) NOT NULL,
    payload TEXT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY login_codes_email_created (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PeaceWatch database schema
-- 1. In phpMyAdmin (or the mysql console) create an empty database, e.g. "peacewatch",
--    with collation utf8mb4_unicode_ci.
-- 2. Select it, then import this file, then import seed.sql.
-- 3. Put the database name/user/password in config/database.php.

SET NAMES utf8mb4;

-- 1. USERS (reporters and the admin)
CREATE TABLE users (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100) NOT NULL,
  email              VARCHAR(150) NOT NULL UNIQUE,
  phone              VARCHAR(30)  NOT NULL,
  community          VARCHAR(150) NULL,
  emergency_name     VARCHAR(100) NULL,
  emergency_phone    VARCHAR(30)  NULL,
  password_hash      VARCHAR(255) NOT NULL,
  role               ENUM('reporter','admin') NOT NULL DEFAULT 'reporter',
  account_status     ENUM('active','suspended') NOT NULL DEFAULT 'active',
  suspended_reason   VARCHAR(255) NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at      TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. REPORTS (the core table)
CREATE TABLE reports (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED NOT NULL,
  latitude           DECIMAL(10,7) NULL,
  longitude          DECIMAL(10,7) NULL,
  accuracy_m         INT UNSIGNED NULL,
  location_available TINYINT(1) NOT NULL DEFAULT 1,
  description        TEXT NULL,
  severity           ENUM('low','medium','critical') NOT NULL DEFAULT 'medium',
  status             ENUM('received','acknowledged','dispatched','on_scene','resolved','false_alarm')
                     NOT NULL DEFAULT 'received',
  outcome_note       TEXT NULL,
  acknowledged_at    TIMESTAMP NULL DEFAULT NULL,
  resolved_at        TIMESTAMP NULL DEFAULT NULL,
  overdue_alerted_at TIMESTAMP NULL DEFAULT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_status_created (status, created_at),
  INDEX idx_severity (severity),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. STATUS HISTORY (powers the reporter's timeline tracker)
CREATE TABLE report_status_history (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  status      ENUM('received','acknowledged','dispatched','on_scene','resolved','false_alarm') NOT NULL,
  changed_by  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_user FOREIGN KEY (changed_by) REFERENCES users(id),
  INDEX idx_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. REPORT UPDATES (follow-ups the reporter posts on the same case)
CREATE TABLE report_updates (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  body        TEXT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_upd_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_upd_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. MESSAGES (admin to reporter only, read-only for the reporter)
CREATE TABLE messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  admin_id    INT UNSIGNED NOT NULL,
  body        VARCHAR(500) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_admin FOREIGN KEY (admin_id) REFERENCES users(id),
  INDEX idx_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ADMIN INTERNAL NOTES (never shown to reporters)
CREATE TABLE report_notes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id   INT UNSIGNED NOT NULL,
  admin_id    INT UNSIGNED NOT NULL,
  note        TEXT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_note_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_note_admin FOREIGN KEY (admin_id) REFERENCES users(id),
  INDEX idx_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. QUICK-REPLY TEMPLATES
CREATE TABLE quick_replies (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  body        VARCHAR(500) NOT NULL,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. IN-APP NOTIFICATIONS (for reporters)
CREATE TABLE notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  report_id   INT UNSIGNED NULL,
  type        ENUM('status_change','message') NOT NULL,
  body        VARCHAR(255) NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. AUDIT LOG (admin accountability)
CREATE TABLE report_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT UNSIGNED NOT NULL,
  report_id   INT UNSIGNED NULL,
  target_user INT UNSIGNED NULL,
  action      VARCHAR(50) NOT NULL,
  detail      VARCHAR(500) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(id),
  INDEX idx_created (created_at),
  INDEX idx_log_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

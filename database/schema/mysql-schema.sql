-- Manifold Timer — production schema (MySQL / MariaDB)
-- v1.0.0 — Initial build.
--
-- Production has no SSH: apply this by hand in phpMyAdmin. It mirrors the
-- Laravel migrations (only the app's own tables — framework tables such as
-- `sessions`, `cache`, `jobs`, `users` are created by `php artisan migrate`).
--
-- Charset/engine chosen to match Laravel defaults.

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
CREATE TABLE `kids` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(255) NOT NULL,
  `pin`           VARCHAR(255) NOT NULL,           -- bcrypt hash, never plaintext
  `color`         VARCHAR(7)  NOT NULL DEFAULT '#6366f1',
  `dark_mode`     TINYINT(1)  NOT NULL DEFAULT 0,
  `work_minutes`  INT UNSIGNED NULL DEFAULT NULL,  -- per-kid override; NULL = use cycle_settings
  `break_minutes` INT UNSIGNED NULL DEFAULT NULL,  -- per-kid override; NULL = use cycle_settings
  `cutoff_time`   TIME NULL DEFAULT NULL,          -- per-kid override; NULL = use cycle_settings
  `created_at`    TIMESTAMP NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `active`     TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `cycle_settings` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `work_minutes`  INT UNSIGNED NOT NULL DEFAULT 120,
  `break_minutes` INT UNSIGNED NOT NULL DEFAULT 45,
  `cutoff_time`   TIME NOT NULL DEFAULT '00:00:00',  -- 00:00 = reset at midnight
  `created_at`    TIMESTAMP NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `timer_sessions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kid_id`           BIGINT UNSIGNED NOT NULL,
  `category_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
  `category_name`    VARCHAR(255) NULL DEFAULT NULL,   -- snapshot at creation
  `phase`            ENUM('work','break') NOT NULL,
  `started_at`       TIMESTAMP NOT NULL,
  `ended_at`         TIMESTAMP NULL DEFAULT NULL,       -- NULL = running (work only)
  `duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`       TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `timer_sessions_kid_id_started_at_index` (`kid_id`, `started_at`),
  KEY `timer_sessions_kid_id_ended_at_index`   (`kid_id`, `ended_at`),
  CONSTRAINT `timer_sessions_kid_id_foreign`
    FOREIGN KEY (`kid_id`) REFERENCES `kids` (`id`),
  CONSTRAINT `timer_sessions_category_id_foreign`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `feedback_threads` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kid_id`      BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('glitch','feature') NOT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,          -- NULL = open
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_threads_kid_id_resolved_at_index` (`kid_id`, `resolved_at`),
  CONSTRAINT `feedback_threads_kid_id_foreign`
    FOREIGN KEY (`kid_id`) REFERENCES `kids` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
CREATE TABLE `feedback_messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id`  BIGINT UNSIGNED NOT NULL,
  `sender`     ENUM('kid','parent') NOT NULL,
  `body`       TEXT NOT NULL,
  `read_at`    TIMESTAMP NULL DEFAULT NULL,           -- when the recipient saw it
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_messages_thread_id_sender_index` (`thread_id`, `sender`),
  CONSTRAINT `feedback_messages_thread_id_foreign`
    FOREIGN KEY (`thread_id`) REFERENCES `feedback_threads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed data (edit names/PINs after import; PINs must be bcrypt hashes).
INSERT INTO `categories` (`name`, `active`, `created_at`, `updated_at`)
VALUES ('Game', 1, NOW(), NOW()),
       ('TV',   1, NOW(), NOW()),
       ('Tablet', 1, NOW(), NOW());

INSERT INTO `cycle_settings` (`work_minutes`, `break_minutes`, `cutoff_time`, `created_at`, `updated_at`)
VALUES (120, 45, '00:00:00', NOW(), NOW());

-- Kids: create the two rows, then set real PINs from the app or with a bcrypt
-- hash. Example placeholders below use a hash of the seeded PIN — replace them.
-- INSERT INTO `kids` (`name`, `pin`, `color`, `dark_mode`, `created_at`, `updated_at`)
-- VALUES ('Kid One', '<bcrypt-hash>', '#3b82f6', 0, NOW(), NOW()),
--        ('Kid Two', '<bcrypt-hash>', '#ec4899', 0, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- ===========================================================================
-- Incremental changes (apply only if upgrading an existing install)
-- ===========================================================================

-- v1.1.0 — per-kid cycle overrides. Skip if the `kids` table above was just
-- created with these columns; run only when upgrading a v1.0.0 database.
-- ALTER TABLE `kids`
--   ADD COLUMN `work_minutes`  INT UNSIGNED NULL DEFAULT NULL AFTER `dark_mode`,
--   ADD COLUMN `break_minutes` INT UNSIGNED NULL DEFAULT NULL AFTER `work_minutes`,
--   ADD COLUMN `cutoff_time`   TIME NULL DEFAULT NULL AFTER `break_minutes`;

-- v1.6.0 — feedback chat (glitch / feature reports). Run on an existing install
-- to add the two new tables. Also available as a ready-to-import file at
-- deploy/feedback-tables.sql. Skip if the CREATE TABLEs above already ran.
-- CREATE TABLE `feedback_threads` ( … );   -- see the CREATE section above
-- CREATE TABLE `feedback_messages` ( … );  -- see the CREATE section above

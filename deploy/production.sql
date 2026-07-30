-- ===========================================================================
-- Manifold Timer — production database (MySQL / MariaDB)
-- Import this ONCE in phpMyAdmin (Import tab) into the app's database.
--
-- Session & cache use the `file` driver in production (see .env), so NO
-- framework tables (sessions/cache/jobs/users/migrations) are needed — just
-- the four app tables below, already seeded with your real data.
-- ===========================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `kids` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(255) NOT NULL,
  `pin`           VARCHAR(255) NOT NULL,           -- bcrypt hash, never plaintext
  `color`         VARCHAR(7)  NOT NULL DEFAULT '#6366f1',
  `dark_mode`     TINYINT(1)  NOT NULL DEFAULT 0,
  `work_minutes`  INT UNSIGNED NULL DEFAULT NULL,  -- per-kid override; NULL = use cycle_settings
  `break_minutes` INT UNSIGNED NULL DEFAULT NULL,
  `cutoff_time`   TIME NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `active`     TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cycle_settings` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `work_minutes`  INT UNSIGNED NOT NULL DEFAULT 120,
  `break_minutes` INT UNSIGNED NOT NULL DEFAULT 45,
  `cutoff_time`   TIME NOT NULL DEFAULT '00:00:00',
  `created_at`    TIMESTAMP NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `timer_sessions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kid_id`           BIGINT UNSIGNED NOT NULL,
  `category_id`      BIGINT UNSIGNED NULL DEFAULT NULL,
  `category_name`    VARCHAR(255) NULL DEFAULT NULL,
  `phase`            ENUM('work','break') NOT NULL,
  `started_at`       TIMESTAMP NOT NULL,
  `ended_at`         TIMESTAMP NULL DEFAULT NULL,
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

-- --- Seed (your current local data; PINs carried over as bcrypt hashes) ------

-- kids  (Robin PIN + Adora PIN are the same ones you use locally)
INSERT INTO `kids` (`name`,`pin`,`color`,`dark_mode`,`work_minutes`,`break_minutes`,`cutoff_time`,`created_at`,`updated_at`) VALUES ('Robin','$2y$12$p/5/J/fq1L/mgjmqAaEVmOycJwMVSP8spGoCo8htjNlikOlZcym72','#3b82f6',0,120,45,'00:00:00',NOW(),NOW());
INSERT INTO `kids` (`name`,`pin`,`color`,`dark_mode`,`work_minutes`,`break_minutes`,`cutoff_time`,`created_at`,`updated_at`) VALUES ('Adora','$2y$12$j6ahqQH9aTyMBO3dq8JQQOxO.cX/ne9y/Ega3YiuzsJQ01GiTZRoi','#ec4899',1,NULL,NULL,NULL,NOW(),NOW());

-- categories
INSERT INTO `categories` (`name`,`active`,`created_at`,`updated_at`) VALUES ('Televizor',1,NOW(),NOW());
INSERT INTO `categories` (`name`,`active`,`created_at`,`updated_at`) VALUES ('Tableta',1,NOW(),NOW());
INSERT INTO `categories` (`name`,`active`,`created_at`,`updated_at`) VALUES ('Telefon',1,NOW(),NOW());

-- global default cycle
INSERT INTO `cycle_settings` (`work_minutes`,`break_minutes`,`cutoff_time`,`created_at`,`updated_at`) VALUES (120,45,'00:00:00',NOW(),NOW());

SET FOREIGN_KEY_CHECKS = 1;

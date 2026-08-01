-- Manifold Timer — v1.6.0 schema change: feedback chat (glitch / feature reports)
--
-- Production has no SSH / no `artisan migrate`. Apply this ONCE by hand:
--   phpMyAdmin → select the `manifold_timer` database → Import → this file → Go
-- (or paste it into the SQL tab and run). Safe to run on the live DB; it only
-- ADDS two new tables and touches nothing existing.

CREATE TABLE `feedback_threads` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kid_id`      BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('glitch','feature') NOT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,          -- NULL = open, set = resolved
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_threads_kid_id_resolved_at_index` (`kid_id`, `resolved_at`),
  CONSTRAINT `feedback_threads_kid_id_foreign`
    FOREIGN KEY (`kid_id`) REFERENCES `kids` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `feedback_messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id`  BIGINT UNSIGNED NOT NULL,
  `sender`     ENUM('kid','parent') NOT NULL,
  `body`       TEXT NOT NULL,
  `read_at`    TIMESTAMP NULL DEFAULT NULL,           -- when the recipient saw it (drives unread badges)
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `feedback_messages_thread_id_sender_index` (`thread_id`, `sender`),
  CONSTRAINT `feedback_messages_thread_id_foreign`
    FOREIGN KEY (`thread_id`) REFERENCES `feedback_threads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

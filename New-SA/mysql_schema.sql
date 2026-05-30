-- =====================================================
-- MySQL Schema — SA Beauty Website（完整版含合併後結構）
-- 使用方式：phpMyAdmin > SQL 頁籤 > 貼上並執行
-- =====================================================

CREATE DATABASE IF NOT EXISTS `sa_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sa_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 清除舊 table（相容舊版 sa_db_setup.sql 的殘留結構）
DROP TABLE IF EXISTS `account_deletions`;
DROP TABLE IF EXISTS `analysis_history`;
DROP TABLE IF EXISTS `php_sessions`;
DROP TABLE IF EXISTS `product_requests`;
DROP TABLE IF EXISTS `user_product_interactions`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `product_favorites`;
DROP TABLE IF EXISTS `product_ratings`;
DROP TABLE IF EXISTS `product_colors`;
DROP TABLE IF EXISTS `skintones`;
DROP TABLE IF EXISTS `comment_reports`;
DROP TABLE IF EXISTS `comment_likes`;
DROP TABLE IF EXISTS `video_reports`;
DROP TABLE IF EXISTS `video_appeals`;
DROP TABLE IF EXISTS `video_comments`;
DROP TABLE IF EXISTS `likes`;
DROP TABLE IF EXISTS `follows`;
DROP TABLE IF EXISTS `carousel_images`;
DROP TABLE IF EXISTS `data`;
DROP TABLE IF EXISTS `videos`;
DROP TABLE IF EXISTS `users`;

-- =====================================================
-- 1. users（帳號 + 個人資料 + LAB 權重，已合併）
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
  `username`            VARCHAR(50)   NOT NULL,
  `email`               VARCHAR(255)  NOT NULL DEFAULT '',
  `email_verified`      TINYINT(1)    NOT NULL DEFAULT 0,
  `verification_code`   VARCHAR(10)   DEFAULT NULL,
  `verification_expiry` DATETIME      DEFAULT NULL,
  `password`            VARCHAR(255)  NOT NULL,
  `role`                VARCHAR(10)   NOT NULL DEFAULT 'user',
  `status`              VARCHAR(20)   DEFAULT 'active',
  `suspended_at`        DATETIME      DEFAULT NULL,
  `reset_token`         VARCHAR(255)  DEFAULT NULL,
  `reset_expiry`        DATETIME      DEFAULT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- 個人資料（原 user_profiles）
  `gender`              VARCHAR(20)   DEFAULT NULL,
  `skin_type`           VARCHAR(50)   DEFAULT NULL,
  `skin_tone`           VARCHAR(50)   DEFAULT NULL,
  `skin_concerns`       TEXT          DEFAULT NULL,
  `age`                 INT(11)       DEFAULT NULL,
  `allergies`           TEXT          DEFAULT NULL,
  `avatar_url`          TEXT          DEFAULT NULL,
  `makeup_finish`       VARCHAR(50)   DEFAULT NULL,
  `makeup_style`        VARCHAR(50)   DEFAULT NULL,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- LAB 色差權重（原 user_lab_weights）
  `weight_l`            FLOAT         DEFAULT 1.0,
  `weight_a`            FLOAT         DEFAULT 1.0,
  `weight_b`            FLOAT         DEFAULT 1.0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 2. carousel_images（首頁輪播）
-- =====================================================
CREATE TABLE IF NOT EXISTS `carousel_images` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `filename`   VARCHAR(255) DEFAULT '',
  `image_path` VARCHAR(255) NOT NULL DEFAULT '',
  `sort_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 3. videos（影片）
-- =====================================================
CREATE TABLE IF NOT EXISTS `videos` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `title`          VARCHAR(255) NOT NULL,
  `description`    TEXT         DEFAULT NULL,
  `filename`       VARCHAR(255) NOT NULL,
  `file_path`      VARCHAR(500) NOT NULL,
  `thumbnail`      VARCHAR(500) DEFAULT NULL,
  `uploaded_by`    VARCHAR(100) NOT NULL,
  `upload_time`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active`      TINYINT(1)   DEFAULT 1,
  `likes`          INT(11)      DEFAULT 0,
  `view_count`     INT(11)      DEFAULT 0,
  `tags`           VARCHAR(500) NOT NULL DEFAULT '',
  `removed_reason` TEXT         DEFAULT NULL,
  `removed_at`     DATETIME     DEFAULT NULL,
  `removed_by`     VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 4. likes（影片按讚）
-- =====================================================
CREATE TABLE IF NOT EXISTS `likes` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`  VARCHAR(100) NOT NULL,
  `video_id` INT(11)      NOT NULL,
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_likes_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 5. video_comments（留言）
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_comments` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `video_id`   INT(11)      NOT NULL,
  `parent_id`  INT(11)      DEFAULT NULL,
  `username`   VARCHAR(100) NOT NULL,
  `content`    TEXT         NOT NULL,
  `likes`      INT(11)      DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_comments_video`  FOREIGN KEY (`video_id`)  REFERENCES `videos` (`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `video_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 6. comment_likes（留言按讚）
-- =====================================================
CREATE TABLE IF NOT EXISTS `comment_likes` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `comment_id` INT(11)      NOT NULL,
  `user_id`    VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comment_user` (`comment_id`, `user_id`),
  CONSTRAINT `fk_comment_likes` FOREIGN KEY (`comment_id`) REFERENCES `video_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 7. video_reports（影片檢舉）
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_reports` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `video_id`    INT(11)      NOT NULL,
  `reported_by` VARCHAR(100) NOT NULL,
  `reason`      VARCHAR(100) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`      VARCHAR(20)  DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_reports_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 8. video_appeals（影片申訴）
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_appeals` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `video_id`    INT(11)      NOT NULL,
  `username`    VARCHAR(100) NOT NULL,
  `reason`      TEXT         NOT NULL,
  `status`      VARCHAR(20)  DEFAULT 'pending',
  `admin_note`  TEXT         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 9. comment_reports（留言檢舉）
-- =====================================================
CREATE TABLE IF NOT EXISTS `comment_reports` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `comment_id`  INT(11)      NOT NULL,
  `reported_by` VARCHAR(100) NOT NULL,
  `reason`      VARCHAR(100) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`      VARCHAR(20)  DEFAULT 'pending',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_comment_reports` FOREIGN KEY (`comment_id`) REFERENCES `video_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 10. follows（追蹤）
-- =====================================================
CREATE TABLE IF NOT EXISTS `follows` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `follower`   VARCHAR(100) NOT NULL,
  `following`  VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `follower_following` (`follower`, `following`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 11. product_origins（產地，正規化）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_origins` (
  `id`   INT(11)     NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 12. data（美妝產品）
-- =====================================================
CREATE TABLE IF NOT EXISTS `data` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `brand`       VARCHAR(50)  DEFAULT NULL,
  `category`    VARCHAR(20)  DEFAULT NULL,
  `name`        VARCHAR(100) DEFAULT NULL,
  `purpose`     TEXT         DEFAULT NULL,
  `origin_id`   INT(11)      DEFAULT NULL,
  `precautions` TEXT         DEFAULT NULL,
  `image_url`   TEXT         DEFAULT NULL,
  `view_count`  INT(11)      DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_data_origin` FOREIGN KEY (`origin_id`) REFERENCES `product_origins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 13. ingredients（成分字典，正規化）
-- =====================================================
CREATE TABLE IF NOT EXISTS `ingredients` (
  `id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 14. product_ingredients（產品↔成分，多對多）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_ingredients` (
  `product_id`    INT(11) NOT NULL,
  `ingredient_id` INT(11) NOT NULL,
  PRIMARY KEY (`product_id`, `ingredient_id`),
  CONSTRAINT `fk_pi_product`    FOREIGN KEY (`product_id`)    REFERENCES `data`(`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_pi_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 15. product_colors（產品色號）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_colors` (
  `color_id`   INT(11)      NOT NULL AUTO_INCREMENT,
  `p_id`       INT(11)      NOT NULL,
  `color_name` VARCHAR(100) NOT NULL DEFAULT '',
  `color_hex`  VARCHAR(20)  NOT NULL DEFAULT '',
  `color_img`  VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`color_id`),
  KEY `p_id` (`p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 16. skintones（膚色配對）
-- =====================================================
CREATE TABLE IF NOT EXISTS `skintones` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `ToneName`     VARCHAR(50) NOT NULL,
  `HexValue`     VARCHAR(7)  NOT NULL,
  `LAB_L`        FLOAT       NOT NULL,
  `LAB_a`        FLOAT       NOT NULL,
  `LAB_b`        FLOAT       NOT NULL,
  `ToneCategory` VARCHAR(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 17. product_favorites（產品收藏）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_favorites` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL,
  `product_id` INT(11)      NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`username`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 18. product_ratings（產品評分）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_ratings` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `product_id` INT(11)      NOT NULL,
  `username`   VARCHAR(100) NOT NULL,
  `attribute`  VARCHAR(50)  NOT NULL,
  `score`      INT(11)      NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_user_attr` (`product_id`, `username`, `attribute`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 16. user_product_interactions（互動紀錄，已合併）
-- =====================================================
CREATE TABLE IF NOT EXISTS `user_product_interactions` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  `username`            VARCHAR(100) NOT NULL,
  `product_id`          VARCHAR(100) NOT NULL,
  `source`              VARCHAR(50)  DEFAULT 'ai_recommendation',
  `click_count`         INT(11)      DEFAULT 1,
  `clicked_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `feedback_type`       VARCHAR(50)  DEFAULT NULL,
  `detected_l`          FLOAT        DEFAULT NULL,
  `detected_a`          FLOAT        DEFAULT NULL,
  `detected_b`          FLOAT        DEFAULT NULL,
  `feedback_updated_at` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`username`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 17. product_requests（申請+回報，已合併）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_requests` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `type`         VARCHAR(20)  NOT NULL,
  `username`     VARCHAR(100) NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `status`       VARCHAR(20)  DEFAULT 'pending',
  `admin_note`   TEXT         DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `product_name` VARCHAR(200) DEFAULT NULL,
  `brand`        VARCHAR(100) DEFAULT NULL,
  `category`     VARCHAR(50)  DEFAULT NULL,
  `price`        VARCHAR(50)  DEFAULT NULL,
  `purchase_link` TEXT        DEFAULT NULL,
  `product_id`   INT(11)      DEFAULT NULL,
  `report_type`  VARCHAR(50)  DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 18. analysis_history（AI 膚質分析紀錄）
-- =====================================================
CREATE TABLE IF NOT EXISTS `analysis_history` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(100) NOT NULL,
  `skin_type`     VARCHAR(50)  DEFAULT NULL,
  `skin_tone`     VARCHAR(100) DEFAULT NULL,
  `skin_concerns` TEXT         DEFAULT NULL,
  `quiz_skin_type` VARCHAR(50) DEFAULT NULL,
  `ai_skin_type`  VARCHAR(50)  DEFAULT NULL,
  `analyzed_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 19. notifications（通知）
-- =====================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `recipient`   VARCHAR(100) NOT NULL,
  `actor`       VARCHAR(100) NOT NULL DEFAULT '系統',
  `type`        VARCHAR(50)  DEFAULT 'new_video',
  `message`     TEXT         DEFAULT NULL,
  `video_id`    INT(11)      DEFAULT NULL,
  `video_title` VARCHAR(255) DEFAULT NULL,
  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
  `has_appealed` TINYINT(1)  NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `recipient` (`recipient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 20. account_deletions（帳號刪除紀錄）
-- =====================================================
CREATE TABLE IF NOT EXISTS `account_deletions` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) DEFAULT NULL,
  `reasons`    TEXT         DEFAULT NULL,
  `deleted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 21. php_sessions（PDO session，本機用預設 file session 即可）
-- =====================================================
CREATE TABLE IF NOT EXISTS `php_sessions` (
  `id`            VARCHAR(128) NOT NULL,
  `data`          TEXT         NOT NULL DEFAULT '',
  `last_activity` INT(11)      NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

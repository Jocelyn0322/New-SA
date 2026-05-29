-- =====================================================
-- sa_db 完整資料庫設定檔
-- 兩人共用：自動支援 port 3306 / 3307
-- 使用方式：phpMyAdmin > SQL 頁籤 > 貼上並執行
-- =====================================================

CREATE DATABASE IF NOT EXISTS `sa_db`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `sa_db`;

-- =====================================================
-- 1. users（登入 / 註冊）
-- =====================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                  int(11)      NOT NULL AUTO_INCREMENT,
  `username`            varchar(50)  NOT NULL,
  `email`               varchar(255) NOT NULL DEFAULT '',
  `email_verified`      tinyint(1)   NOT NULL DEFAULT 0,
  `verification_code`   varchar(10)  DEFAULT NULL,
  `verification_expiry` datetime     DEFAULT NULL,
  `password`            varchar(255) NOT NULL,
  `role`                varchar(10)  NOT NULL DEFAULT 'user',
  `created_at`          timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `users`
  (`id`, `username`, `email`, `email_verified`, `verification_code`, `verification_expiry`, `password`, `role`, `created_at`)
VALUES
  (1,  'admin',  '',                      0, NULL, NULL, '123456', 'admin', '2026-04-20 07:27:29'),
  (2,  'user1',  '',                      0, NULL, NULL, '123456', 'user',  '2026-04-20 07:27:29'),
  (8,  'user2',  '',                      0, NULL, NULL, '123456', 'user',  '2026-04-20 07:27:29'),
  (9,  '杜昕',   '',                      0, NULL, NULL, '950301', 'user',  '2026-04-28 07:21:15'),
  (18, '范欣榆', 'chip.0322tw@gmail.com', 1, NULL, NULL, '000000', 'user',  '2026-05-11 16:18:44');

-- =====================================================
-- 2. user_profiles（個人資料）
-- =====================================================
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `username`      varchar(100) NOT NULL,
  `gender`        varchar(10)  DEFAULT NULL,
  `skin_type`     varchar(50)  DEFAULT NULL,
  `skin_tone`     varchar(50)  DEFAULT NULL,
  `skin_concerns` text         DEFAULT NULL,
  `age`           int(11)      DEFAULT NULL,
  `allergies`     text         DEFAULT NULL,
  `created_at`    timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `user_profiles`
  (`id`, `username`, `gender`, `skin_type`, `skin_tone`, `skin_concerns`, `age`, `allergies`)
VALUES
  (1, '杜昕',  '女性', '混合肌', '中等淺', '痘痘, 粉刺', 20, ''),
  (3, 'user1', '女性', '乾燥肌', '中等淺', '毛孔粗大',   18, '');

-- =====================================================
-- 3. carousel_images（首頁輪播）
-- =====================================================
CREATE TABLE IF NOT EXISTS `carousel_images` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `filename`   varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 4. videos（影片）
-- =====================================================
CREATE TABLE IF NOT EXISTS `videos` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `title`       varchar(255) NOT NULL,
  `description` text         DEFAULT NULL,
  `filename`    varchar(255) NOT NULL,
  `file_path`   varchar(500) NOT NULL,
  `thumbnail`   varchar(500) DEFAULT NULL,
  `uploaded_by` varchar(100) NOT NULL,
  `upload_time` timestamp    NOT NULL DEFAULT current_timestamp(),
  `is_active`   tinyint(1)   DEFAULT 1,
  `likes`       int(11)      DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `videos`
  (`id`, `title`, `description`, `filename`, `file_path`, `thumbnail`, `uploaded_by`, `upload_time`, `is_active`, `likes`)
VALUES
  (1, '美',    '我每',      '1776757769_dfZUxdhkKurZ.mp4',                                     'videos/1776757769_dfZUxdhkKurZ.mp4',                                     NULL, 'user1', '2026-04-21 07:49:29', 1, 1),
  (2, 'vvjo3', 'oijo3q2jm', '1776759042_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4',  'videos/1776759042_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4',  NULL, 'user1', '2026-04-21 08:10:42', 1, 1),
  (3, '吃',    '好好吃',    '1776759082_01e9e5f18f09cea4010370039dae282857_4610.mp4video.MP4',  'videos/1776759082_01e9e5f18f09cea4010370039dae282857_4610.mp4video.MP4',  NULL, 'user1', '2026-04-21 08:11:22', 1, 0),
  (4, '我',    '',          '1776759393_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4',  'videos/1776759393_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4',  NULL, 'user2', '2026-04-21 08:16:33', 1, 0),
  (5, '我',    '',          '1776759420_ScreenRecording_04-21-202611-23-10_1.MP4',              'videos/1776759420_ScreenRecording_04-21-202611-23-10_1.MP4',              NULL, 'user2', '2026-04-21 08:17:00', 1, 0),
  (6, '我',    '',          '1776759517_ScreenRecording_04-21-202611-23-10_1.MP4',              'videos/1776759517_ScreenRecording_04-21-202611-23-10_1.MP4',              NULL, 'user2', '2026-04-21 08:18:37', 1, 0),
  (7, '我',    '',          '1776759551_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4',  'videos/1776759551_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4',  NULL, 'user2', '2026-04-21 08:19:11', 1, 1);

-- =====================================================
-- 5. likes（影片按讚）
-- =====================================================
CREATE TABLE IF NOT EXISTS `likes` (
  `id`       int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`  varchar(100) NOT NULL,
  `video_id` int(11)      NOT NULL,
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_likes_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `likes` (`id`, `user_id`, `video_id`) VALUES
  (6, 'user2', 1),
  (7, 'user2', 2),
  (9, 'user1', 7);

-- =====================================================
-- 6. video_comments（留言）
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_comments` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `video_id`   int(11)      NOT NULL,
  `parent_id`  int(11)      DEFAULT NULL,
  `username`   varchar(100) NOT NULL,
  `content`    text         NOT NULL,
  `likes`      int(11)      DEFAULT 0,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_comments_video`  FOREIGN KEY (`video_id`)  REFERENCES `videos` (`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `video_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `video_comments` (`id`, `video_id`, `parent_id`, `username`, `content`, `likes`, `created_at`) VALUES
  (1, 7, NULL, 'user1',  '好帥',  0, '2026-05-11 12:35:39'),
  (3, 2, NULL, '范欣榆', '好帥',  0, '2026-05-11 16:22:25'),
  (4, 7, 1,    '范欣榆', '謝謝',  0, '2026-05-11 16:28:26');

-- =====================================================
-- 7. comment_likes（留言按讚）
-- =====================================================
CREATE TABLE IF NOT EXISTS `comment_likes` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `comment_id` int(11)      NOT NULL,
  `user_id`    varchar(100) NOT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `comment_user` (`comment_id`, `user_id`),
  CONSTRAINT `fk_comment_likes` FOREIGN KEY (`comment_id`) REFERENCES `video_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 8. video_reports（檢舉）
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_reports` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `video_id`    int(11)      NOT NULL,
  `reported_by` varchar(100) NOT NULL,
  `reason`      varchar(100) NOT NULL,
  `description` text         DEFAULT NULL,
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  `status`      varchar(20)  DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `video_id` (`video_id`),
  CONSTRAINT `fk_reports_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `video_reports` (`id`, `video_id`, `reported_by`, `reason`, `description`, `created_at`, `status`) VALUES
  (1, 7, '范欣榆', '不當內容', '他長太帥太帥太帥太帥了', '2026-05-11 16:28:53', 'pending');

-- =====================================================
-- 9. products（底妝產品）
-- =====================================================
CREATE TABLE IF NOT EXISTS `products` (
  `p_id`        int(11)      NOT NULL AUTO_INCREMENT COMMENT '產品自動編號',
  `brand`       varchar(50)  NOT NULL COMMENT '品牌',
  `category`    varchar(20)  NOT NULL COMMENT '分類',
  `name`        varchar(100) NOT NULL COMMENT '產品全名',
  `purpose`     text         NOT NULL COMMENT '用途',
  `origin`      varchar(30)  NOT NULL COMMENT '產地',
  `ingredients` text         NOT NULL COMMENT '成分',
  `precautions` text         NOT NULL COMMENT '注意事項',
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `products` (`p_id`, `brand`, `category`, `name`, `purpose`, `origin`, `ingredients`, `precautions`, `created_at`) VALUES
(1,  '植村秀',    '底妝', '無極限超時輕粉底液',           '控油、持裝、修飾毛孔', '日本', '夏威夷籽油、抗氧化粉體', '使用前請先搖均勻，避免乾燥處', '2026-04-18 10:28:39'),
(2,  '植村秀',    '底妝', '無極限裸光精粹粉底液',         '養膚型粉底液、極致裸光妝感、長效保濕', '日本', '90%精華液基底、日本山茶花籽油', '建議搭配保濕妝前乳使用', '2026-04-18 10:31:59'),
(3,  '植村秀',    '底妝', '無極限持久遮瑕筆',             '遮蓋黑眼圈、泛紅、痘疤', '日本', '礦物粉體、持裝薄膜', '少量多次疊加，避免厚重', '2026-04-18 10:31:59'),
(4,  '植村秀',    '底妝', '無極限水潤光粉底霜',           '頂級養膚、保濕、打造奶油肌妝感', '日本', '日本山茶花籽油、高濃度護膚精華', '建議搭配專用粉底刷或海綿均勻推開', '2026-04-18 11:07:47'),
(5,  '植村秀',    '底妝', '無極限四色遮瑕盤',             '專業校色、遮蓋黑眼圈、修飾泛紅、打亮提亮', '日本', '日本山茶花籽油、高濃度顯色因子', '質地較為濕潤，建議搭配專用遮瑕刷使用', '2026-04-18 13:53:01'),
(6,  'YSL',      '底妝', '恆久完美無瑕持妝粉底',         '高度遮瑕、全天持妝、抗汗控油', '法國', '茉莉花萃取、持妝持色科技', '質地乾得較快，建議分區上妝', '2026-04-18 17:46:09'),
(7,  'YSL',      '底妝', '恆久完美裸光無瑕粉底露',       '輕薄妝感、水光保濕、養膚裸妝', '法國', '85%養膚精華、玻尿酸、冷壓茉莉花', '使用前請務必搖均勻', '2026-04-18 17:46:09'),
(8,  'YSL',      '底妝', '恆久完美精華準無瑕遮瑕液',     '局部遮瑕、修飾黑眼圈、精準校色、保濕服貼', '法國', '茉莉花精粹、多重養膚精華', '刷頭設計精確，可直接點塗於瑕疵處', '2026-04-18 18:08:15'),
(9,  'CHANEL',   '底妝', '原生美肌微滴水粉底',           '裸妝感、極緻保濕、提亮膚色、水嫩光澤', '法國', '橙花精萃、微滴粒子、輕盈水基底', '質地極為輕透，建議使用內附專屬刷具上妝', '2026-04-18 18:17:20'),
(10, 'CHANEL',   '底妝', '雪紡輕霧持久粉底',             '完美持妝、遮瑕力高、控油柔霧妝效', '法國', '完美親膚複合物、柔焦粉體', '建議由臉部中央向外推勻', '2026-04-18 18:23:18'),
(11, 'CHANEL',   '底妝', '恆潤裸光水慕絲粉底',           '潤澤保濕、勻亮膚色、絲緞裸光妝效', '法國', '潤膚油成分、長效持妝粉體、保濕因子', '質地如慕絲般滑順，可用指腹或粉底刷上妝', '2026-04-19 06:56:18'),
(12, 'CHANEL',   '底妝', '奢華晶鑽賦活粉底乳霜',         '頂級養膚、賦活肌膚、遮瑕同時煥發鑽石光采', '法國', '五月梵尼蘭豆莢、鑽石粉末', '質地為潤澤乳霜，適合乾性或熟齡肌膚', '2026-04-19 07:04:36'),
(13, 'CHANEL',   '底妝', '1號紅色山茶花活能粉底液',      '亮澤妝效、修飾瑕疵、對抗老化跡象', '法國', '紅色山茶花精萃油、潤澤成分', '質地滑順，可重複疊擦增加遮瑕力', '2026-04-19 07:18:44'),
(14, 'CHANEL',   '底妝', '香奈兒初生光采持久遮瑕膏',     '局部遮瑕、修飾斑點、高度防曬保護 SPF40', '法國', '礦物濾鏡、維他命E衍生物、山茶花萃取', '膏體質地較為紮實，建議用指腹溫熱後輕拍上妝', '2026-04-20 08:38:49'),
(15, 'CHANEL',   '底妝', '雪紡輕霧持久遮瑕膏',           '極致持妝、無瑕修飾校色、柔霧妝感', '法國', '柔焦粉體、長效持妝薄膜成分', '蜜桃色調能有效校正青紫色暗沈', '2026-04-20 08:59:18'),
(16, 'CHANEL',   '底妝', '香奈兒潤色遮瑕筆',             '長效潤色、修飾遮瑕、眼部提亮、自然妝效', '法國', '礦物遮瑕粉體、保濕潤澤成分', '質地水潤，特別適合乾性肌膚或眼下遮瑕使用', '2026-04-20 08:59:18'),
(17, 'Dior',     '底妝', '超完美持久柔光粉底液',         '水光妝效、長效持妝、保濕 SPF50', '法國', '鳶尾花萃取、三色堇萃取、朱槿花萃取', '質地較潤澤，適合偏乾肌或想要奶油肌妝感的人', '2026-04-20 10:08:05'),
(18, 'Dior',     '底妝', '超完美持久柔霧粉底液',         '霧光妝效、長效持妝、保濕 SPF25', '法國', '鳶尾花萃取、三色堇萃取、朱槿花萃取', '主打耐汗抗油，適合偏油肌或夏天戶外活動', '2026-04-20 10:08:05'),
(19, 'Dior',     '底妝', '超完美持久遮瑕膏',             '24小時持久遮瑕、保濕養膚、修飾黑眼圈', '法國', '鳶尾花萃取、野生三色堇萃取、金旱蓮萃取', '刷頭較大，建議先點在手背再用指腹上妝', '2026-04-20 10:20:28'),
(20, 'Dior',     '底妝', '精萃再生花蜜微導粉底',         '頂級養膚、微導玫瑰精華注入、賦活亮澤', '法國', '岡維拉玫瑰精萃、微量營養素、玫瑰金粉體', '適合乾性肌、熟齡肌或秋冬季節使用', '2026-04-20 10:20:28'),
(21, 'Nars',     '底妝', '裸光肌萃粉底精華',             '養膚型底妝、保濕亮澤、均勻膚色、修飾細紋', 'USA', '仿生燕麥、水飛薊、可可多肽、日本麥冬', '使用前請務必搖勻', '2026-04-20 11:23:16'),
(22, 'Nars',     '底妝', '妝點甜心遮瑕蜜',               '完美遮瑕、修飾黑眼圈與瑕疵、長效保濕', 'USA', '葡萄籽萃取、厚朴萃取、維他命E、礦物粉末', '不致粉刺配方，建議點狀塗抹後再拍開', '2026-04-20 11:23:16'),
(23, 'MAYBELLINE','底妝', 'FIT ME 水光奇蹟保濕粉底液',   '混合肌與全肌適用、保濕水光', '美國', '高濃度玻尿酸精華、維他命E', '質地較水感，使用前建議先輕微搖勻', '2026-04-20 11:57:13'),
(24, 'MAYBELLINE','底妝', 'FIT ME 反孔特霧粉底液',       '油性與混合肌適用、霧面妝感、修飾毛孔', '美國', 'Aerogel 礦物柔霧粉體、無油配方 SPF22', '乾得較快，建議分區上妝後迅速推勻', '2026-04-20 12:01:45'),
(25, 'MAYBELLINE','底妝', '裸霧光持久水粉底',             '空氣感輕薄妝感、柔霧光澤 SPF12', '美國', '360°柔光科技、空氣感鎖妝粉體', '質地輕盈，使用前需充分搖勻', '2026-04-20 12:15:42'),
(26, 'MAYBELLINE','底妝', '無敵特霧超持久粉底液',         '高遮瑕力、30H超長效持妝、無油配方', '美國', '高遮瑕粉體、長效控油因子', '乾得非常快，建議分區上妝迅速推勻', '2026-04-20 12:15:42');

-- =====================================================
-- 10. product_colors（產品色號）
-- =====================================================
CREATE TABLE IF NOT EXISTS `product_colors` (
  `color_id`  int(11)      NOT NULL AUTO_INCREMENT,
  `p_id`      int(11)      NOT NULL,
  `color_name` varchar(100) NOT NULL,
  `color_hex` varchar(20)  NOT NULL,
  `color_img` varchar(255) NOT NULL,
  PRIMARY KEY (`color_id`),
  KEY `p_id` (`p_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `product_colors` (`color_id`, `p_id`, `color_name`, `color_hex`, `color_img`) VALUES
(1,  1, '584', '#F7E3D1', '最白皙(粉色調)'),
(2,  1, '594', '#F9EBD7', '白皙(象牙色調)'),
(3,  1, '674', '#F2D5B9', '自然偏白(最熱門主打色)'),
(4,  1, '664', '#F0CB9F', '自然色'),
(5,  1, '774', '#EED0A6', '自然偏黃(適合容易泛紅的膚質)'),
(6,  1, '764', '#EAC18F', '標準自然偏黃'),
(7,  1, '463', '#D9A083', '健康膚色'),
(8,  2, '584', '#F7E3D1', '最白皙水光感'),
(9,  2, '674', '#F2D5B9', '自然偏白(最熱賣)'),
(10, 2, '664', '#F0CB9F', '自然色'),
(11, 2, '774', '#EED0A6', '自然偏黃'),
(12, 2, '574', '#FADAC0', '中性調自然白'),
(13, 3, '5-light',  '#F9E2D2', '適合粉底574, 584'),
(14, 3, '6-medium', '#EBC7A7', '適合粉底664'),
(15, 3, '7-medium', '#D9A083', '適合粉底764, 774'),
(16, 3, '5-fair',   '#FFF1E6', '適合粉底594'),
(17, 4, '584', '#F7E3D1', '最白皙色號'),
(18, 4, '674', '#F2D5B9', '經典自然偏白(最推薦)'),
(19, 4, '574', '#F4DAC0', '中性調自然色'),
(20, 4, '774', '#EED0A6', '自然偏黃調'),
(21, 4, '664', '#F0CB9F', '自然健康膚色'),
(22, 5, '粉色盤-校色橘', '#E9967A', '修飾青紫色黑眼圈'),
(23, 5, '粉色盤-提亮膚', '#F5E0CD', '淚溝、凹陷處提亮'),
(24, 5, '粉色盤-自然膚', '#EBC7A7', '遮蓋單點、痘疤'),
(25, 5, '粉色盤-陰影色', '#C69C7C', '輪廓修容、增加立體感'),
(26, 5, '紫色盤-校色紫', '#D1D1F0', '修飾蠟黃、暗沉，提亮膚色'),
(27, 5, '紫色盤-提亮膚', '#F8F0E3', '偏白皙的提亮色'),
(28, 5, '紫色盤-自然膚', '#EED0A6', '適合偏黃膚色的遮瑕'),
(29, 5, '紫色盤-深膚色', '#BE9B7B', '適合校深瑕疵或陰影'),
(30, 3, '5-mediem',  '#F2D1B3', '適合粉底564'),
(31, 3, '6-light',   '#F2D5B9', '適合粉底674'),
(32, 3, '7-light',   '#EAC18F', '適合粉底784'),
(33, 7, 'LC1',  '#F9E2D2', '極粉嫩白皙'),
(34, 7, 'LC2',  '#FAD8C9', '粉嫩白皙'),
(35, 7, 'LC3',  '#F2C7B5', '粉嫩色'),
(36, 7, 'LC6.5','#E3B499', '健康色粉調'),
(37, 7, 'LN1',  '#FDF0E5', '明亮白皙'),
(38, 7, 'LN4',  '#F5DCC4', '自然白皙'),
(39, 7, 'LN7',  '#EBC7A7', '自然色'),
(40, 7, 'LW1',  '#FAEBD7', '暖調自然白'),
(41, 7, 'LW4',  '#F2D1B3', '暖調自然白'),
(42, 7, 'LW7',  '#E6C2A0', '暖調自然色'),
(43, 7, 'MN7',  '#D9A083', '健康中性色'),
(44, 6, 'LC1',  '#F9E2D2', '極粉嫩白皙'),
(45, 6, 'LC2',  '#FAD8C9', '粉嫩白皙'),
(46, 6, 'LC3',  '#F2C7B5', '粉嫩色'),
(47, 6, 'LC6',  '#E9B9A2', '健康粉調色'),
(48, 6, 'LN1',  '#FDF0E5', '明亮白皙'),
(49, 6, 'LN4',  '#F5DCC4', '自然白皙'),
(50, 6, 'LN7',  '#EBC7A7', '自然色'),
(51, 6, 'LW1',  '#FAEBD7', '暖調白皙'),
(52, 6, 'LW4',  '#F2D1B3', '暖調自然白'),
(53, 6, 'LW7',  '#E6C2A0', '暖調自然色'),
(54, 6, 'MN7',  '#D9A083', '健康中性色'),
(55, 8, 'LC1',  '#FAD8C9', '適合粉底LC1'),
(56, 8, 'LN4',  '#F5DCC4', '適合粉底LN1/LN4'),
(57, 8, 'LW7',  '#E6C2A0', '適合暖調自然膚色'),
(58, 8, 'MN1',  '#EBC7A7', '中性調自然色'),
(59, 8, 'MN7',  '#D9A083', '健康中性膚色'),
(60, 9, 'B10',  '#FAEBD7', '最白皙色號'),
(61, 9, 'B12',  '#F7E3D1', '白皙色'),
(62, 9, 'B20',  '#F5DCC4', '自然偏白'),
(63, 9, 'BD21', '#F2D5B9', '暖調自然白'),
(64, 9, 'B30',  '#EBC7A7', '自然色'),
(65, 9, 'B40',  '#D9A083', '健康膚色'),
(66, 10, 'BD01', '#FDF0E5', '最白皙暖色調'),
(67, 10, 'B10',  '#FAEBD7', '經典明亮白'),
(68, 10, 'BR12', '#F7E3D1', '白皙帶粉嫩感'),
(69, 10, 'B20',  '#F5DCC4', '自然偏白'),
(70, 10, 'BD21', '#F2D5B9', '自然暖調色'),
(71, 10, 'BR22', '#FAD8C9', '自然粉色調'),
(72, 10, 'B30',  '#EBC7A7', '中間自然色'),
(73, 10, 'BD31', '#EED0A6', '健康暖色調'),
(74, 11, 'B10',  '#FAEBD7', '經典明亮白'),
(75, 11, 'B20',  '#F5DCC4', '自然裸膚色(中性調)'),
(76, 11, 'BD21', '#F2D5B9', '自然裸膚色(帶暖色調)'),
(77, 11, 'BR22', '#FAD8C9', '自然裸膚色(帶粉色調)'),
(78, 11, 'B30',  '#EBC7A7', '健康膚色(中性調)'),
(79, 11, 'BO33', '#E9C9A2', '健康橄欖膚色'),
(80, 11, 'BD31', '#EED0A6', '健康膚色(帶暖色調)'),
(81, 11, 'BR32', '#E3B499', '健康膚色(帶粉色調)'),
(82, 11, 'B40',  '#D9A083', '黝黑膚色(中性調)'),
(83, 12, '10 - BEIGE',      '#FAEBD7', '明亮白皙(中性調)'),
(84, 12, '12 - BEIGE ROSÉ', '#F7E3D1', '白皙色(帶粉調)'),
(85, 12, '20 - BEIGE',      '#F5DCC4', '自然偏白(最熱門色)'),
(86, 12, '21 - BEIGE DORÉ', '#F2D5B9', '自然色(帶暖黃調)'),
(87, 12, '30 - BEIGE',      '#EBC7A7', '中間自然色'),
(88, 12, '40 - BEIGE',      '#D9A083', '健康膚色'),
(89, 13, 'BD01', '#FDF0E5', '白皙膚色帶暖色調'),
(90, 13, 'B10',  '#FAEBD7', '自然偏白中性膚色'),
(91, 13, 'BR12', '#F7E3D1', '自然偏白帶粉嫩色'),
(92, 13, 'B20',  '#F5DCC4', '自然裸膚中性膚色'),
(93, 13, 'BD21', '#F2D5B9', '自然裸膚偏暖膚色'),
(94, 13, 'BR22', '#FAD8C9', '自然裸膚偏粉膚色'),
(95, 13, 'B30',  '#EBC7A7', '健康中性膚色'),
(96, 14, 'BR02', '#FAE5D3', '白皙偏粉膚色'),
(97, 14, 'B10',  '#FAEBD7', '自然偏白中性膚色'),
(98, 14, 'BR12', '#F7E3D1', '自然偏白帶粉色'),
(99, 14, 'B20',  '#F5DCC4', '自然裸膚中性色'),
(100,14, 'B30',  '#EBC7A7', '健康中性膚色'),
(101,15, '蜜桃', '#E6B291', '針對青紫色黑眼圈校色專用'),
(102,15, '杏桃', '#D2946F', '根據使用者膚色進行局部遮瑕'),
(103,16, 'B10',  '#FAEBD7', '自然偏白中性膚色'),
(104,16, 'B20',  '#F5DCC4', '自然裸膚中性色'),
(105,16, '30',   '#EBC7A7', '健康中性膚色'),
(106,17, '0N',   '#F6E4D9', '最白皙膚色(中性調)'),
(107,17, '1N',   '#F2D6C5', '亞洲熱賣明亮白(中性調)'),
(108,17, '1.5N', '#EED0BC', '自然偏白(中性調)'),
(109,17, '2N',   '#E8C2AB', '自然膚色(中性調)'),
(110,17, '0W',   '#F8E6CD', '最白皙膚色(暖色調)'),
(111,17, '1W',   '#F1D8B8', '明亮白皙(暖色調)'),
(112,17, '0CR',  '#F9E2D2', '最白皙膚色(冷粉調)'),
(113,17, '1CR',  '#F4D7C9', '明亮白皙(冷粉調)'),
(114,18, '0N',   '#F6E4D9', '最白皙膚色(中性調)'),
(115,18, '1N',   '#F2D6C5', '亞洲熱賣明亮白(中性調)'),
(116,18, '1.5N', '#EED0BC', '自然偏白(中性調)'),
(117,18, '2N',   '#E8C2AB', '自然膚色(中性調)'),
(118,18, '0W',   '#F8E6CD', '最白皙膚色(暖色調)'),
(119,18, '1W',   '#F1D8B8', '明亮白皙(暖色調)'),
(120,18, '0CR',  '#F9E2D2', '最白皙膚色(冷粉調)'),
(121,18, '1CR',  '#F4D7C9', '明亮白皙(冷粉調)'),
(122,19, '0N',   '#F6E4D9', '最白皙中性膚色'),
(123,19, '1N',   '#F2D6C5', '明亮白皙中性'),
(124,19, '1.5N', '#EED0BC', '自然偏白中性膚色'),
(125,19, '2N',   '#E8C2AB', '自然中性膚色'),
(126,19, '0.5N', '#F9EDE4', '極極白皙中性色'),
(127,19, '1W',   '#F1D8B8', '明亮白皙暖色調'),
(128,17, '1CR',  '#F4D7C9', '明亮白皙冷粉調'),
(129,20, '0N',   '#F6E4D9', '最白皙膚色(瓷白色)'),
(130,20, '1N',   '#F2D6C5', '明亮白皙(最受歡迎色號)'),
(131,20, '2N',   '#E8C2AB', '自然膚色'),
(132,20, '1W',   '#F1D8B8', '明亮偏暖(修飾暗沈)'),
(133,20, '1CR',  '#F4D7C9', '明亮偏粉(增加紅潤感)'),
(134,21, 'YULONG',    '#F6E4D9', '極白皙中性調'),
(135,21, 'SIBERIA',   '#F9F1E2', '極白皙帶黃調'),
(136,21, 'MONT BLANC','#F5DCC4', '白皙帶粉調'),
(137,21, 'DEAUVILLE', '#F2D6C5', '明亮帶金黃調'),
(138,21, 'VIENNA',    '#EBC7A7', '明亮帶粉桃調'),
(139,21, 'OSLO',      '#FAEBD7', '極白皙(粉調/冷色)'),
(140,21, 'GOBI',      '#F3E5AB', '白皙(黃調/暖色)'),
(141,21, 'SALZBURG',  '#F5DEB3', '白皙(中性調/平衡)'),
(142,21, 'FIJI',      '#EBC79E', '自然偏白(黃調)'),
(143,21, 'PUNJAB',    '#D2B48C', '自然色(中性帶桃)'),
(144,21, 'SANTA FE',  '#C19A6B', '健康膚色(中性)'),
(145,22, 'CHANTILLY', '#FDF0E5', '白皙粉膚色'),
(146,22, 'VANILLA',   '#F7E3D1', '偏粉調的明亮色'),
(147,22, 'CUSTARD',   '#E8C2AB', '黃調自然色'),
(148,22, 'HONEY',     '#FFB07C', '蜜桃調'),
(149,22, 'CANNELLE',  '#EDC9AF', '亮桃色'),
(150,22, 'MACADAMIA', '#E1A95F', '自然色'),
(151,23, '110', '#F3DBC1', '白皙色'),
(152,23, '112', '#EBC7A7', '明亮色'),
(153,23, '115', '#E3B494', '自然色'),
(154,23, '120', '#D9A683', '健康色'),
(155,24, '110', '#F3DBC1', '白皙色'),
(156,24, '109', '#F7E3D1', '極白皙色(帶粉)'),
(157,24, '112', '#EBC7A7', '明亮色'),
(158,24, '118', '#D2B48C', '自然色(偏黃)'),
(159,24, '120', '#D9A683', '自然色'),
(160,24, '128', '#C49B72', '焦糖色(健康肌)'),
(161,26, '112', '#EBC7A7', '溫柔(自然偏粉)'),
(162,26, '110', '#F3DBC1', '白皙色'),
(163,26, '120', '#D9A683', '自然色'),
(164,26, '128', '#C49B72', '裸米色'),
(165,25, 'C05', '#F8E3D5', '粉膚白皙'),
(166,25, 'N10', '#F2D1B3', '象牙白皙'),
(167,25, 'W20', '#ECC099', '自然白皙'),
(168,25, 'N30', '#E5B28A', '自然明亮'),
(169,25, 'W40', '#DDA47B', '自然膚色'),
(170,25, 'N50', '#D1956C', '健康膚色');

-- =====================================================
-- 11. skintones（膚色配對）
-- =====================================================
CREATE TABLE IF NOT EXISTS `skintones` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `ToneName`     varchar(50) NOT NULL,
  `HexValue`     varchar(7)  NOT NULL,
  `LAB_L`        float       NOT NULL,
  `LAB_a`        float       NOT NULL,
  `LAB_b`        float       NOT NULL,
  `ToneCategory` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `skintones` (`id`, `ToneName`, `HexValue`, `LAB_L`, `LAB_a`, `LAB_b`, `ToneCategory`) VALUES
(1,  '粉一白',     '#FAF2F0', 95.8, 2.3,  1.4,  'Pink'),
(2,  '黃一白',     '#F9E6D1', 92.4, 3.4,  9.6,  'Yellow'),
(3,  '中一白',     '#F5DED1', 90.1, 8.8,  11.2, 'Neutral'),
(4,  '橄欖一白',   '#FBF3E4', 96.4, 0.4,  9.5,  'Olive'),
(5,  '粉二白',     '#F5E8E5', 92.6, 4.3,  2.2,  'Pink'),
(6,  '黃二白',     '#F2D8B6', 87.5, 5.7,  21,   'Yellow'),
(7,  '中二白',     '#E1C1AF', 80.5, 11.8, 14.7, 'Neutral'),
(8,  '橄欖二白',   '#D0C5B1', 79.4, -0.6, 12.3, 'Olive'),
(9,  '粉三白',     '#EAD8D1', 87.8, 4.8,  4.4,  'Pink'),
(10, '黃三白',     '#F0C594', 82.2, 12.4, 33.7, 'Yellow'),
(11, '中三白',     '#D0A994', 71.3, 13.9, 15.6, 'Neutral'),
(12, '橄欖三白',   '#A2947D', 60.9, -0.1, 15.5, 'Olive'),
(13, '偏紅冷一白', '#F5F0ED', 95,   1.4,  1.2,  'Red-Cool'),
(14, '偏紅冷二白', '#E8DACB', 87.8, 4.8,  9.4,  'Red-Cool'),
(15, '偏紅暖一白', '#E4CEBA', 84.1, 8,    14.9, 'Red-Warm'),
(16, '偏紅暖二白', '#C4AD96', 71.6, 6.8,  18,   'Red-Warm'),
(17, '中性冷一白', '#F1ECE9', 93.3, 1.5,  1.1,  'Neutral-Cool'),
(18, '中性冷二白', '#D0B6A5', 76.7, 8.9,  14.4, 'Neutral-Cool'),
(19, '中性暖一白', '#C1B6A3', 73.8, -0.2, 11.7, 'Neutral-Warm'),
(20, '中性暖二白', '#AF9E8E', 65.5, 3.8,  11.7, 'Neutral-Warm'),
(21, '偏綠冷一白', '#F7F2ED', 95.6, 0.9,  1.5,  'Green-Cool'),
(22, '偏綠冷二白', '#D0BAAA', 76.9, 7.9,  12,   'Green-Cool'),
(23, '偏綠暖一白', '#A59C8D', 63.8, 1,    9.4,  'Green-Warm'),
(24, '偏綠暖二白', '#9D9485', 60.9, 1.2,  9.5,  'Green-Warm');

-- =====================================================
-- 完成！請確認 sa_db 的所有資料表都已建立。
-- =====================================================

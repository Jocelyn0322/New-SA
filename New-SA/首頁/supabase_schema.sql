-- =====================================================
-- Supabase (PostgreSQL) Schema  SA Beauty Website
-- 請在 Supabase > SQL Editor 貼上此檔案並執行
-- =====================================================

-- 1. Users（含個人資料，原 user_profiles 已合併）
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL DEFAULT '',
    email_verified SMALLINT NOT NULL DEFAULT 0,
    verification_code VARCHAR(10),
    verification_expiry TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(10) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    created_at TIMESTAMP DEFAULT NOW(),
    -- 個人資料欄位（原 user_profiles）
    gender VARCHAR(20),
    skin_type VARCHAR(50),
    skin_tone VARCHAR(50),
    skin_concerns TEXT,
    age INTEGER,
    allergies TEXT,
    avatar_url TEXT,
    makeup_finish VARCHAR(50),
    makeup_style VARCHAR(50),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- 2. Videos
CREATE TABLE IF NOT EXISTS videos (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    thumbnail VARCHAR(500),
    uploaded_by VARCHAR(100) NOT NULL,
    upload_time TIMESTAMP DEFAULT NOW(),
    is_active SMALLINT DEFAULT 1,
    likes INTEGER DEFAULT 0
);

-- 3. Video Likes
CREATE TABLE IF NOT EXISTS likes (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    video_id INTEGER NOT NULL,
    CONSTRAINT fk_likes_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);

-- 4. Carousel Images
CREATE TABLE IF NOT EXISTS carousel_images (
    id SERIAL PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW()
);

-- 5. Video Comments
CREATE TABLE IF NOT EXISTS video_comments (
    id SERIAL PRIMARY KEY,
    video_id INTEGER NOT NULL,
    parent_id INTEGER DEFAULT NULL,
    username VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    likes INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT fk_comments_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES video_comments(id) ON DELETE CASCADE
);

-- 6. Comment Likes
CREATE TABLE IF NOT EXISTS comment_likes (
    id SERIAL PRIMARY KEY,
    comment_id INTEGER NOT NULL,
    user_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE (comment_id, user_id),
    CONSTRAINT fk_comment_likes FOREIGN KEY (comment_id) REFERENCES video_comments(id) ON DELETE CASCADE
);

-- 7. Video Reports
CREATE TABLE IF NOT EXISTS video_reports (
    id SERIAL PRIMARY KEY,
    video_id INTEGER NOT NULL,
    reported_by VARCHAR(100) NOT NULL,
    reason VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    status VARCHAR(20) DEFAULT 'pending',
    CONSTRAINT fk_reports_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
);

-- 8. Products
CREATE TABLE IF NOT EXISTS data (
    id SERIAL PRIMARY KEY,
    brand VARCHAR(50),
    category VARCHAR(20),
    name VARCHAR(100),
    purpose TEXT,
    origin VARCHAR(30),
    ingredients TEXT,
    precautions TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- 9. Product Colors
CREATE TABLE IF NOT EXISTS product_colors (
    color_id SERIAL PRIMARY KEY,
    p_id INTEGER,
    color_name VARCHAR(100),
    color_hex VARCHAR(20),
    color_img VARCHAR(100)
);

-- （user_profiles 已合併至 users，此 table 已移除）

-- =====================================================
-- 初始資料
-- =====================================================

INSERT INTO users (id, username, email, email_verified, verification_code, verification_expiry, password, role, created_at)
VALUES
    (1,  'admin',  '',                     0, NULL, NULL, '123456', 'admin', '2026-04-20 07:27:29'),
    (2,  'user1',  '',                     0, NULL, NULL, '123456', 'user',  '2026-04-20 07:27:29'),
    (8,  'user2',  '',                     0, NULL, NULL, '123456', 'user',  '2026-04-20 07:27:29'),
    (9,  '杜昕',   '',                     0, NULL, NULL, '950301', 'user',  '2026-04-28 07:21:15'),
    (18, '范欣榆', 'chip.0322tw@gmail.com', 1, NULL, NULL, '000000', 'user', '2026-05-11 16:18:44')
ON CONFLICT (id) DO NOTHING;
SELECT setval('users_id_seq', 19);

INSERT INTO videos (id, title, description, filename, file_path, thumbnail, uploaded_by, upload_time, is_active, likes)
VALUES
    (1, '美',    '我每',      '1776757769_dfZUxdhkKurZ.mp4',                                    'videos/1776757769_dfZUxdhkKurZ.mp4',                                    NULL, 'user1', '2026-04-21 07:49:29', 1, 1),
    (2, 'vvjo3', 'oijo3q2jm', '1776759042_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4', 'videos/1776759042_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4', NULL, 'user1', '2026-04-21 08:10:42', 1, 1),
    (3, '吃',    '好好吃',    '1776759082_01e9e5f18f09cea4010370039dae282857_4610.mp4video.MP4', 'videos/1776759082_01e9e5f18f09cea4010370039dae282857_4610.mp4video.MP4', NULL, 'user1', '2026-04-21 08:11:22', 1, 0),
    (4, '我',    '',          '1776759393_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4', 'videos/1776759393_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4', NULL, 'user2', '2026-04-21 08:16:33', 1, 0),
    (5, '我',    '',          '1776759420_ScreenRecording_04-21-202611-23-10_1.MP4',             'videos/1776759420_ScreenRecording_04-21-202611-23-10_1.MP4',             NULL, 'user2', '2026-04-21 08:17:00', 1, 0),
    (6, '我',    '',          '1776759517_ScreenRecording_04-21-202611-23-10_1.MP4',             'videos/1776759517_ScreenRecording_04-21-202611-23-10_1.MP4',             NULL, 'user2', '2026-04-21 08:18:37', 1, 0),
    (7, '我',    '',          '1776759551_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4', 'videos/1776759551_01e9ce38f42f6a36010370039d4dfb0092_4610.mp4video.MP4', NULL, 'user2', '2026-04-21 08:19:11', 1, 1)
ON CONFLICT (id) DO NOTHING;
SELECT setval('videos_id_seq', 8);

INSERT INTO likes (id, user_id, video_id) VALUES (6, 'user2', 1), (7, 'user2', 2), (9, 'user1', 7)
ON CONFLICT (id) DO NOTHING;
SELECT setval('likes_id_seq', 10);

INSERT INTO data (id, brand, category, name, purpose, origin, ingredients, precautions, created_at)
VALUES
    (101, 'MAC',    '唇膏', '霧幻性感唇膏',     '提升氣色、霧面妝效',  '加拿大', '蠟、顏料、維他命E',   '建議先塗抹護唇膏', '2026-04-21 01:34:31'),
    (102, 'Romand', '唇釉', '果凍持久唇釉',     '水光感、染唇持久',   '韓國',   '水, 辛基十二烷醇',   '需用眼唇專用卸妝', '2026-04-21 01:34:31'),
    (103, 'Dior',   '唇膏', '癮誘粉漾潤唇膏',   '潤澤、自然潤色',    '法國',   '櫻桃油, 乳油木果油', '避免高溫環境',    '2026-04-21 01:34:31'),
    (104, 'KATE',   '唇膏', '怪獸級持色唇膏',   '保濕、長效持色',    '日本',   '聚二甲基矽氧烷',    '使用後請蓋緊蓋子', '2026-04-21 01:34:31'),
    (105, 'YSL',    '唇釉', '奢華緞面漆光唇釉', '漆光亮澤、持久',    '法國',   '水, 變性酒精',     '避免陽光直射',    '2026-04-21 01:34:31')
ON CONFLICT (id) DO NOTHING;
SELECT setval('data_id_seq', 200);

INSERT INTO product_colors (color_id, p_id, color_name, color_hex, color_img)
VALUES
    (1, 101, '#602 Chili',    '#8F332E', '經典小辣椒'),
    (2, 101, '#707 Ruby Woo', '#9B111E', '復古正紅'),
    (3, 102, '#06 Figfig',    '#B65353', '無花果色'),
    (4, 102, '#07 Jujube',    '#A0423E', '紅棗色'),
    (5, 103, '#001 Pink',     '#FFD1DC', '嬰兒粉')
ON CONFLICT (color_id) DO NOTHING;
SELECT setval('product_colors_color_id_seq', 6);

-- 個人資料初始值（原 user_profiles，現在直接 UPDATE users）
UPDATE users SET gender='女性', skin_type='混合肌', skin_tone='中等淺', skin_concerns='痘痘, 粉刺', age=20, allergies='' WHERE username='杜昕';
UPDATE users SET gender='女性', skin_type='乾燥肌', skin_tone='中等淺', skin_concerns='毛孔粗大',   age=18, allergies='' WHERE username='user1';

INSERT INTO video_comments (id, video_id, parent_id, username, content, likes, created_at)
VALUES
    (1, 7, NULL, 'user1', '好帥',  0, '2026-05-11 12:35:39'),
    (3, 2, NULL, '范欣榆', '好帥', 0, '2026-05-11 16:22:25'),
    (4, 7, 1,   '范欣榆', '謝謝',  0, '2026-05-11 16:28:26')
ON CONFLICT (id) DO NOTHING;
SELECT setval('video_comments_id_seq', 5);

INSERT INTO video_reports (id, video_id, reported_by, reason, description, created_at, status)
VALUES (1, 7, '范欣榆', '不當內容', '他長太帥太帥太帥太帥了', '2026-05-11 16:28:53', 'pending')
ON CONFLICT (id) DO NOTHING;
SELECT setval('video_reports_id_seq', 2);

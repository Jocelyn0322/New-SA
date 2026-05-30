-- =====================================================
-- Migration: 將 users 表的個人資料欄位搬到 user_profiles
-- 執行前請先備份資料庫！
-- =====================================================

-- Step 1: 補充 user_profiles 缺少的欄位（只加還沒有的）
ALTER TABLE `user_profiles`
  ADD COLUMN `weight_l` FLOAT DEFAULT 1.0,
  ADD COLUMN `weight_a` FLOAT DEFAULT 1.0,
  ADD COLUMN `weight_b` FLOAT DEFAULT 1.0;

-- Step 2: 把 users 現有資料同步到 user_profiles（有衝突就更新）
INSERT INTO `user_profiles`
  (username, gender, skin_type, skin_tone, skin_concerns, age, allergies,
   avatar_url, makeup_finish, makeup_style, weight_l, weight_a, weight_b)
SELECT
  username, gender, skin_type, skin_tone, skin_concerns, age, allergies,
  avatar_url, makeup_finish, makeup_style, weight_l, weight_a, weight_b
FROM `users`
ON DUPLICATE KEY UPDATE
  gender        = VALUES(gender),
  skin_type     = VALUES(skin_type),
  skin_tone     = VALUES(skin_tone),
  skin_concerns = VALUES(skin_concerns),
  age           = VALUES(age),
  allergies     = VALUES(allergies),
  avatar_url    = VALUES(avatar_url),
  makeup_finish = VALUES(makeup_finish),
  makeup_style  = VALUES(makeup_style),
  weight_l      = VALUES(weight_l),
  weight_a      = VALUES(weight_a),
  weight_b      = VALUES(weight_b);

-- Step 3: 確認資料都搬過去後，才移除 users 的欄位
ALTER TABLE `users`
  DROP COLUMN `gender`,
  DROP COLUMN `skin_type`,
  DROP COLUMN `skin_tone`,
  DROP COLUMN `skin_concerns`,
  DROP COLUMN `age`,
  DROP COLUMN `allergies`,
  DROP COLUMN `avatar_url`,
  DROP COLUMN `makeup_finish`,
  DROP COLUMN `makeup_style`,
  DROP COLUMN `weight_l`,
  DROP COLUMN `weight_a`,
  DROP COLUMN `weight_b`;

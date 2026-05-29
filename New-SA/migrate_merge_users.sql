-- =====================================================
-- Migration: 將 user_profiles 合併進 users
-- 執行順序：在 Supabase > SQL Editor 貼上並執行
-- =====================================================

-- Step 1: 將 user_profiles 的欄位全部加到 users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS gender        VARCHAR(20),
    ADD COLUMN IF NOT EXISTS skin_type     VARCHAR(50),
    ADD COLUMN IF NOT EXISTS skin_tone     VARCHAR(50),
    ADD COLUMN IF NOT EXISTS skin_concerns TEXT,
    ADD COLUMN IF NOT EXISTS age           INTEGER,
    ADD COLUMN IF NOT EXISTS allergies     TEXT,
    ADD COLUMN IF NOT EXISTS avatar_url    TEXT,
    ADD COLUMN IF NOT EXISTS makeup_finish VARCHAR(50),
    ADD COLUMN IF NOT EXISTS makeup_style  VARCHAR(50),
    ADD COLUMN IF NOT EXISTS updated_at    TIMESTAMP DEFAULT NOW();

-- Step 2: 把 user_profiles 現有資料複製到 users
UPDATE users u
SET
    gender        = up.gender,
    skin_type     = up.skin_type,
    skin_tone     = up.skin_tone,
    skin_concerns = up.skin_concerns,
    age           = up.age,
    allergies     = up.allergies,
    avatar_url    = up.avatar_url,
    makeup_finish = up.makeup_finish,
    makeup_style  = up.makeup_style,
    updated_at    = up.updated_at
FROM user_profiles up
WHERE u.username = up.username;

-- Step 3: 刪除舊 table
DROP TABLE IF EXISTS user_profiles;

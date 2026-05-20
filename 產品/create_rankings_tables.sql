-- 1. 產品觀看數
ALTER TABLE products ADD COLUMN IF NOT EXISTS view_count INTEGER DEFAULT 0;

-- 2. 影片觀看數
ALTER TABLE videos ADD COLUMN IF NOT EXISTS view_count INTEGER DEFAULT 0;

-- 3. 產品收藏紀錄（DB 版，用於排名統計）
CREATE TABLE IF NOT EXISTS product_favorites (
    id         SERIAL PRIMARY KEY,
    username   VARCHAR(100) NOT NULL,
    product_id INTEGER      NOT NULL,
    created_at TIMESTAMP    DEFAULT NOW(),
    UNIQUE (username, product_id)
);

-- 4. 每月排名快照
CREATE TABLE IF NOT EXISTS monthly_rankings (
    id        SERIAL PRIMARY KEY,
    month     VARCHAR(7)   NOT NULL,           -- YYYY-MM
    rank_type VARCHAR(30)  NOT NULL,           -- product_views / product_favs / video_views / video_likes
    rank_no   INTEGER      NOT NULL,
    item_id   INTEGER      NOT NULL,
    item_name VARCHAR(200),
    score     INTEGER      DEFAULT 0,
    saved_at  TIMESTAMP    DEFAULT NOW()
);

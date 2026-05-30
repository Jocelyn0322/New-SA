-- =====================================================
-- 清理 data 表的舊文字欄位（正規化完成後執行）
-- 執行前確認 product_origins、product_ingredients、ingredients 表都有資料
-- =====================================================

-- 確認 origin_id 已填好（此查詢結果應為 0）
-- SELECT COUNT(*) FROM data WHERE origin IS NOT NULL AND origin != '' AND origin_id IS NULL;

-- 確認成分已匯入（此查詢結果應為 0）
-- SELECT COUNT(*) FROM data WHERE ingredients IS NOT NULL AND ingredients != ''
--   AND id NOT IN (SELECT DISTINCT product_id FROM product_ingredients);

-- 刪除舊欄位
ALTER TABLE `data` DROP COLUMN IF EXISTS `origin`;
ALTER TABLE `data` DROP COLUMN IF EXISTS `ingredients`;

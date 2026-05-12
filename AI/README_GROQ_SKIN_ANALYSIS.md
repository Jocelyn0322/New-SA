# Groq Vision 串接流程（Node.js）

## 功能

`services/SkinAnalysisService.js` 提供完整流程：
1. 上傳圖片到 Groq Vision 模型進行膚況分析。
2. 嚴格要求模型僅回傳 JSON（含 `detected_lab`、`skin_type`、`features`、`confidence_score`）。
3. 依 `detected_lab` 以 Delta E 公式比對 `SkinTones` 最近色號。
4. 依 `skin_type` 對應 `SkinTypes`。
5. 依「色號 + 膚質 + 妝效（預設 Matte）」查詢產品。

## 環境變數

建立 `.env`：

```env
GROQ_API_KEY=your_groq_key
GROQ_VISION_MODEL=meta-llama/llama-4-scout-17b-16e-instruct

DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=makeupmakeup
```

## 快速執行

```bash
npm install
npm run analyze:skin -- ./path/to/face.jpg Matte
```

## 主要 API

- `uploadAndAnalyze(imageInput)`
  - `imageInput`: `string` 檔案路徑 / `Buffer` / `File`
  - 呼叫 Groq 並回傳已驗證的分析 JSON

- `resolveSkinProfileAndProducts(analysis, userPreference='Matte')`
  - 取得最近 `SkinTones`、對應 `SkinTypes`，並查出產品清單

- `getProductRecommendationSql()`
  - 回傳 JOIN `ProductShades` + `Products` 的 SQL 範本

## SQL 重點

最近色號（Delta E）：

```sql
SELECT
  id,
  ToneName,
  HexValue,
  ToneCategory,
  SQRT(
    POW(LAB_L - ?, 2) +
    POW(LAB_a - ?, 2) +
    POW(LAB_b - ?, 2)
  ) AS delta_e
FROM SkinTones
ORDER BY delta_e ASC
LIMIT 1;
```

產品過濾：

```sql
SELECT p.*, ps.*
FROM ProductShades ps
JOIN Products p ON p.id = ps.ProductID
WHERE ps.SkinToneID = ?
  AND (
    FIND_IN_SET(CAST(? AS CHAR), REPLACE(REPLACE(p.SuitableSkinType, '，', ','), ' ', '')) > 0
    OR p.SuitableSkinType LIKE CONCAT('%', ?, '%')
  )
  AND (p.Finish = ? OR p.MakeupEffect = ?);
```

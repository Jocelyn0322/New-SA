import fs from 'node:fs/promises';
import path from 'node:path';

const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

const SYSTEM_PROMPT = `你是資深皮膚科專家與影像皮膚分析師。你必須嚴格遵守以下規範：
1) 只根據提供的臉部照片進行分析，不要臆測不存在資訊。
2) 你要分析：
   - T 區油脂分佈（額頭、鼻子）
   - 兩頰油脂分佈
   - 毛孔細緻度（是否明顯可見）
   - 是否有局部泛紅
3) 你只能輸出 JSON，不得輸出任何解釋、註解、Markdown、前後文。
4) JSON 欄位必須完全符合下列格式與鍵名：
{
  "detected_lab": {"L": 0.0, "a": 0.0, "b": 0.0},
  "skin_type": "混油皮/乾性皮/油性皮/中性皮/混乾皮/敏感肌",
  "features": {
    "t_zone_shine": "high/medium/low",
    "pore_visibility": "visible/fine",
    "redness": true
  },
  "confidence_score": 0.95
}
5) skin_type 僅能是以下之一：混油皮、乾性皮、油性皮、中性皮、混乾皮、敏感肌。
6) features.t_zone_shine 僅能是 high / medium / low。
7) features.pore_visibility 僅能是 visible / fine。
8) confidence_score 必須為 0 到 1 之間的小數。
9) detected_lab 需為數值（L, a, b）。
10) 若影像資訊不足，也必須回傳同格式 JSON，並降低 confidence_score。`;

const USER_PROMPT = `請分析這張臉部照片，輸出指定 JSON。`;

const PRODUCT_RECOMMENDATION_PROMPT = `你是彩妝顧問，請根據使用者的膚質、妝感偏好、妝容風格，從候選產品中選出最適合的。

評估標準：
1) 膚質適配：考慮產品成分、用途是否適合使用者的膚質（混油皮/乾性/油性/中性等）
2) 分類匹配：優先選擇適合使用者需求的產品分類（底妝/眼妝/脣妝/修容等）
3) 功效匹配：根據用途（控油、保濕、遮蓋等）與使用者膚質特徵匹配
4) 原產地：日本品牌通常質量可靠，值得加分
5) 妝感風格：根據使用者偏好的妝容風格選擇

回傳 JSON 格式：
{
  "products": [
    {
      "id": 1,
      "reason": "簡短推薦理由（50字內）",
      "match_score": 0.95
    }
  ]
}

規則：
- 只能從提供的候選產品中選擇
- 最多推薦 3 個產品
- match_score 必須是 0 到 1 的小數
- reason 應說明產品如何解決使用者的膚質問題或符合其妝容風格`;


const SKIN_TYPE_ALIASES = {
  '混油皮': ['混油皮', 'combination oily', 'combo_oily', 'combination-oily'],
  '乾性皮': ['乾性皮', '乾肌', 'dry', 'dry skin'],
  '油性皮': ['油性皮', '油肌', 'oily', 'oily skin'],
  '中性皮': ['中性皮', 'normal', 'normal skin'],
  '混乾皮': ['混乾皮', 'combination dry', 'combo_dry', 'combination-dry'],
  '敏感肌': ['敏感肌', 'sensitive', 'sensitive skin']
};

let mysqlModulePromise = null;

async function loadMysqlClient() {
  if (!mysqlModulePromise) {
    mysqlModulePromise = import('mysql2/promise').catch((error) => {
      if (error?.code === 'ERR_MODULE_NOT_FOUND') {
        return null;
      }
      throw error;
    });
  }

  const module = await mysqlModulePromise;
  return module?.default || null;
}

export class SkinAnalysisService {
  constructor({
    apiKey = process.env.GROQ_API_KEY,
    model = process.env.GROQ_VISION_MODEL || 'meta-llama/llama-4-scout-17b-16e-instruct',
    dbConfig = {
      host: process.env.DB_HOST || '127.0.0.1',
      port: Number(process.env.DB_PORT || 3306),
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || '',
      database: process.env.DB_NAME || 'makeupmakeup'
    }
  } = {}) {
    if (!apiKey) {
      throw new Error('缺少 GROQ_API_KEY，請先設定環境變數');
    }
    this.apiKey = apiKey;
    this.model = model;
    this.dbConfig = dbConfig;
  }

  async uploadAndAnalyze(imageInput) {
    const { base64, mimeType } = await this.toBase64(imageInput);

    const payload = {
      model: this.model,
      temperature: 0,
      response_format: { type: 'json_object' },
      messages: [
        { role: 'system', content: SYSTEM_PROMPT },
        {
          role: 'user',
          content: [
            { type: 'text', text: USER_PROMPT },
            {
              type: 'image_url',
              image_url: {
                url: `data:${mimeType};base64,${base64}`
              }
            }
          ]
        }
      ]
    };

    const response = await fetch(GROQ_ENDPOINT, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${this.apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Groq API 請求失敗 (${response.status}): ${errorText}`);
    }

    const result = await response.json();
    const content = result?.choices?.[0]?.message?.content;
    if (!content) {
      throw new Error('Groq API 回傳缺少 message.content');
    }

    const parsed = this.parseModelJson(content);
    return this.validateAnalysis(parsed);
  }

  async resolveSkinProfileAndProducts(analysis, userPreference = 'Matte', makeupPreference = {}, userContext = {}) {
    const mysqlClient = await loadMysqlClient();
    if (!mysqlClient) {
      return {
        analysis,
        nearestSkinTone: null,
        products: [],
        makeupPreference: {
          finish: makeupPreference?.finish || userPreference || 'Matte',
          style: makeupPreference?.style || '日常通勤'
        },
        warnings: ['缺少 mysql2 套件，已略過資料庫比對與產品推薦']
      };
    }

    let conn;
    try {
      conn = await mysqlClient.createConnection(this.dbConfig);
    } catch (dbErr) {
      return {
        analysis,
        nearestSkinTone: null,
        products: [],
        makeupPreference: {
          finish: makeupPreference?.finish || userPreference || 'Matte',
          style: makeupPreference?.style || '日常通勤'
        },
        warnings: ['資料庫連線失敗，已略過產品推薦']
      };
    }
    try {
      const skinTonesTable = await this.findTable(conn, ['SkinTones', 'skintones', 'skin_tones', 'skin_tone']);
      const productsTable = await this.findTable(conn, ['Products', 'products', 'product', 'product_list', 'product_info']);
      const productColorsTable = await this.findTable(conn, ['product_colors', 'ProductColors', 'product_shades', 'ProductShades']);

      const warnings = [];
      const resolvedMakeupPreference = {
        finish: makeupPreference?.finish || userPreference || 'Matte',
        style: makeupPreference?.style || '日常通勤'
      };
      const normalizedUsername = typeof userContext?.username === 'string' ? userContext.username.trim() : '';
      const userLabWeights = normalizedUsername ? await this.getUserLabWeights(conn, normalizedUsername) : null;
      const userTextureBias = normalizedUsername ? await this.getUserTextureBias(conn, normalizedUsername) : null;
      let nearestSkinTone = null;
      if (!skinTonesTable) {
        warnings.push('缺少 SkinTones 資料表，已跳過最近膚色色號匹配');
      } else {
        try {
          nearestSkinTone = await this.findNearestSkinTone(conn, skinTonesTable, analysis.detected_lab, userLabWeights);
        } catch (error) {
          warnings.push(`最近膚色色號匹配失敗：${error.message}`);
        }
      }

      let products = [];
      if (!productsTable) {
        warnings.push('缺少 Products 資料表，已跳過產品推薦');
      } else {
        try {
          // Load all product candidates
          let candidates = [];
          try {
            candidates = await this.loadAllProductCandidates(conn, productsTable);
          } catch (err) {
            console.warn('[SkinAnalysisService] Failed to load products:', err.message);
            warnings.push(`產品數據加載失敗：${err.message}`);
          }

          // Use AI to rank products based on user's skin profile and preferences
          if (candidates.length > 0) {
            try {
              const ranked = await this.rankProductsWithAI({
                analysis,
                makeupPreference: resolvedMakeupPreference,
                userTextureBias,
                candidates
              });
              products = this.mergeRankedProducts(candidates, ranked);
            } catch (rankError) {
              console.warn('[SkinAnalysisService] AI ranking failed:', rankError.message);
              warnings.push(`AI 產品排名失敗，改用智能本地排名`);
              // Use local smart ranking when AI fails
              products = this.localSmartRank(analysis, resolvedMakeupPreference, candidates, userTextureBias);
            }
          }

          if (!products.length && candidates.length > 0) {
            products = candidates.slice(0, 3);
          }
        } catch (error) {
          warnings.push(`產品推薦失敗：${error.message}`);
        }
      }

      if (products.length) {
        products = this.applyTextureBiasToProducts(products, userTextureBias);
        if (productColorsTable && nearestSkinTone?.id) {
          try {
            products = await this.attachShadeRecommendations(conn, products, productColorsTable, nearestSkinTone, analysis.detected_lab);
          } catch (shadeError) {
            warnings.push(`色號推薦補充失敗：${shadeError.message}`);
          }
        }
      }

      return {
        analysis,
        nearestSkinTone,
        products,
        makeupPreference: resolvedMakeupPreference,
        userLabWeights,
        userTextureBias,
        warnings
      };
    } finally {
      await conn.end();
    }
  }

  async findNearestSkinTone(conn, tableName, lab, labWeights = null) {
    const weights = {
      L: Number(labWeights?.weightL || 1),
      a: Number(labWeights?.weightA || 1),
      b: Number(labWeights?.weightB || 1)
    };

    const [rows] = await conn.execute(
      `SELECT
        id,
        ToneName,
        HexValue,
        ToneCategory,
        SQRT(
          POW((LAB_L - ?) * ?, 2) +
          POW((LAB_a - ?) * ?, 2) +
          POW((LAB_b - ?) * ?, 2)
        ) AS delta_e
      FROM \`${tableName}\`
      ORDER BY delta_e ASC
      LIMIT 1`,
      [lab.L, weights.L, lab.a, weights.a, lab.b, weights.b]
    );

    if (!rows.length) {
      throw new Error('SkinTones 無資料，無法計算最接近色號');
    }

    const row = rows[0];
    return {
      id: row.id,
      toneName: row.ToneName,
      hex: row.HexValue,
      category: row.ToneCategory,
      deltaE: Number(row.delta_e),
      weights
    };
  }

  async getUserLabWeights(conn, username) {
    if (!username) {
      return null;
    }

    const [rows] = await conn.execute(
      `SELECT weight_L, weight_a, weight_b
       FROM \`UserLabWeights\`
       WHERE username = ?
       LIMIT 1`,
      [username]
    ).catch(() => [[], null]);

    if (!Array.isArray(rows) || !rows.length) {
      return { username, weightL: 1, weightA: 1, weightB: 1 };
    }

    const row = rows[0];
    return {
      username,
      weightL: Number(row.weight_L) || 1,
      weightA: Number(row.weight_a) || 1,
      weightB: Number(row.weight_b) || 1
    };
  }

  async getUserTextureBias(conn, username) {
    if (!username) {
      return { hydrationBias: 0, tooDryCount: 0, tooOilyCount: 0 };
    }

    const [rows] = await conn.execute(
      `SELECT feedback_type, COUNT(*) AS cnt
       FROM \`UserProductFeedback\`
       WHERE username = ?
         AND feedback_type IN ('too_dry', 'too_oily')
       GROUP BY feedback_type`
      ,
      [username]
    ).catch(() => [[], null]);

    let tooDryCount = 0;
    let tooOilyCount = 0;
    for (const row of rows || []) {
      if (row.feedback_type === 'too_dry') {
        tooDryCount = Number(row.cnt) || 0;
      }
      if (row.feedback_type === 'too_oily') {
        tooOilyCount = Number(row.cnt) || 0;
      }
    }

    const hydrationBias = Math.max(-3, Math.min(3, tooDryCount - tooOilyCount));
    return { hydrationBias, tooDryCount, tooOilyCount };
  }

  async attachShadeRecommendations(conn, products, productColorsTable, nearestSkinTone, detectedLab) {
    const columns = await this.getTableColumns(conn, productColorsTable);
    const productIdCol = this.pickExistingColumn(columns, ['ProductID', 'product_id', 'p_id', 'productId']);
    const shadeNameCol = this.pickExistingColumn(columns, ['ShadeName', 'shade_name', 'Shade', 'shade', 'ColorName', 'color_name', 'name']);
    const skinToneIdCol = this.pickExistingColumn(columns, ['SkinToneID', 'skin_tone_id', 'tone_id', 'SkinToneId']);
    const labLCol = this.pickExistingColumn(columns, ['LAB_L', 'lab_l', 'L']);
    const labACol = this.pickExistingColumn(columns, ['LAB_a', 'lab_a', 'A']);
    const labBCol = this.pickExistingColumn(columns, ['LAB_b', 'lab_b', 'B']);
    const hexCol = this.pickExistingColumn(columns, ['HexValue', 'hex', 'Hex', 'hex_value']);

    if (!productIdCol || !shadeNameCol) {
      return products;
    }

    const withShade = [];
    for (const product of products) {
      const productId = product.p_id ?? product.id ?? product.ProductID ?? product.productId;
      if (productId === undefined || productId === null) {
        withShade.push(product);
        continue;
      }

      let shadeRow = null;

      if (skinToneIdCol && nearestSkinTone?.id) {
        const [exactRows] = await conn.execute(
          `SELECT *
           FROM \`${productColorsTable}\`
           WHERE \`${productIdCol}\` = ? AND \`${skinToneIdCol}\` = ?
           LIMIT 1`,
          [productId, nearestSkinTone.id]
        );
        if (Array.isArray(exactRows) && exactRows.length) {
          shadeRow = exactRows[0];
        }
      }

      if (!shadeRow && labLCol && labACol && labBCol && detectedLab) {
        const [labRows] = await conn.execute(
          `SELECT *,
             SQRT(
               POW(\`${labLCol}\` - ?, 2) +
               POW(\`${labACol}\` - ?, 2) +
               POW(\`${labBCol}\` - ?, 2)
             ) AS shade_delta
           FROM \`${productColorsTable}\`
           WHERE \`${productIdCol}\` = ?
           ORDER BY shade_delta ASC
           LIMIT 1`,
          [detectedLab.L, detectedLab.a, detectedLab.b, productId]
        );
        if (Array.isArray(labRows) && labRows.length) {
          shadeRow = labRows[0];
        }
      }

      if (!shadeRow) {
        const [fallbackRows] = await conn.execute(
          `SELECT *
           FROM \`${productColorsTable}\`
           WHERE \`${productIdCol}\` = ?
           LIMIT 1`,
          [productId]
        );
        if (Array.isArray(fallbackRows) && fallbackRows.length) {
          shadeRow = fallbackRows[0];
        }
      }

      if (shadeRow) {
        withShade.push({
          ...product,
          recommendedShade: shadeRow[shadeNameCol] ?? null,
          recommendedShadeHex: hexCol ? (shadeRow[hexCol] ?? null) : null
        });
      } else {
        withShade.push(product);
      }
    }

    return withShade;
  }



  async loadAllProductCandidates(conn, productsTable) {
    const [rows] = await conn.execute(`SELECT * FROM \`${productsTable}\` LIMIT 24`);
    return rows;
  }

  async getTableColumns(conn, tableName) {
    const [columnsRows] = await conn.query(`SHOW COLUMNS FROM \`${tableName}\``);
    return columnsRows.map((item) => item.Field);
  }

  async rankProductsWithAI({ analysis, makeupPreference, userTextureBias, candidates }) {
    // If we have very few candidates or API fails, use smart local ranking
    // This ensures recommendations are always reasonable even without AI
    if (candidates.length <= 3) {
      return candidates.map((item, idx) => ({
        id: item.p_id ?? item.id,
        reason: `根據您的${analysis.skin_type}膚質和${makeupPreference.finish}妝感偏好推薦`,
        match_score: 0.7 + idx * 0.1 // 0.7, 0.8, 0.9
      }));
    }

    const candidatePayload = candidates.map((item) => ({
      id: item.p_id ?? item.id ?? item.ProductID ?? item.productId,
      brand: item.brand || item.Brand || '',
      name: item.name || item.productName || item.ProductName || '',
      category: item.category || item.Category || '',
      purpose: item.purpose || item.Purpose || '',
      origin: item.origin || item.Origin || '',
      ingredients: item.ingredients || item.Ingredients || '',
      precautions: item.precautions || item.Precautions || ''
    }));

    const payload = {
      model: this.model,
      temperature: 0.2,
      response_format: { type: 'json_object' },
      messages: [
        {
          role: 'system',
          content: PRODUCT_RECOMMENDATION_PROMPT
        },
        {
          role: 'user',
          content: JSON.stringify({
            user: {
              skin_type: analysis.skin_type,
              confidence_score: analysis.confidence_score,
              features: analysis.features,
              makeup_preference: makeupPreference,
              texture_bias: userTextureBias || { hydrationBias: 0 }
            },
            candidates: candidatePayload
          })
        }
      ]
    };

    const response = await fetch(GROQ_ENDPOINT, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${this.apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Groq 產品推薦請求失敗 (${response.status}): ${errorText}`);
    }

    const result = await response.json();
    const content = result?.choices?.[0]?.message?.content;
    if (!content) {
      throw new Error('Groq 產品推薦回傳缺少 message.content');
    }

    const parsed = this.parseModelJson(content);
    const products = Array.isArray(parsed?.products) ? parsed.products : [];
    return products
      .filter((item) => item && item.id !== undefined && item.id !== null)
      .map((item) => ({
        id: item.id,
        reason: String(item.reason || '').trim(),
        match_score: Number(item.match_score)
      }));
  }

  mergeRankedProducts(candidates, rankedProducts) {
    if (!Array.isArray(candidates) || !Array.isArray(rankedProducts)) {
      return [];
    }

    const candidateMap = new Map(
      candidates.map((item) => {
        const candidateId = item.p_id ?? item.id ?? item.ProductID ?? item.productId;
        return [String(candidateId), item];
      })
    );

    const merged = rankedProducts
      .map((entry) => {
        const candidate = candidateMap.get(String(entry.id));
        if (!candidate) {
          return null;
        }

        return {
          ...candidate,
          recommendationReason: entry.reason || '',
          matchScore: Number.isFinite(entry.match_score) ? entry.match_score : null
        };
      })
      .filter(Boolean);

    return merged.slice(0, 3);
  }

  localSmartRank(analysis, makeupPreference, candidates, userTextureBias = null) {
    // Score products based on features and user's skin profile
    const scored = candidates.map((item, idx) => {
      let score = 0.5; // Base score

      // Boost score based on category relevance
      const category = String(item.category || item.Category || '').toLowerCase();
      if (category.includes('底妝') || category.includes('foundation')) score += 0.15;
      
      // Boost score based on skin type matching in purpose/ingredients
      const purpose = String(item.purpose || item.Purpose || '').toLowerCase();
      const ingredients = String(item.ingredients || item.Ingredients || '').toLowerCase();
      
      if (analysis.skin_type.includes('油')) {
        // Oily skin - prefer oil control
        if (purpose.includes('控油') || purpose.includes('油') || ingredients.includes('控油')) score += 0.15;
      } else if (analysis.skin_type.includes('乾')) {
        // Dry skin - prefer moisturizing
        if (purpose.includes('保濕') || purpose.includes('水') || ingredients.includes('保濕')) score += 0.15;
      }
      
      // High shine (T-zone) indicates need for matte products
      if (analysis.features.t_zone_shine === 'high') {
        if (makeupPreference.finish === '霧面' || makeupPreference.finish === 'Matte') score += 0.1;
      }

      const hydrationBias = Number(userTextureBias?.hydrationBias || 0);
      if (hydrationBias > 0) {
        if (purpose.includes('保濕') || purpose.includes('水潤') || purpose.includes('修護')) {
          score += Math.min(0.12, hydrationBias * 0.04);
        }
      } else if (hydrationBias < 0) {
        if (purpose.includes('控油') || purpose.includes('持妝') || purpose.includes('清爽')) {
          score += Math.min(0.12, Math.abs(hydrationBias) * 0.04);
        }
      }

      return { item, score: Math.min(score, 1.0), reason: this.generateReason(analysis, item) };
    });

    return scored
      .sort((a, b) => b.score - a.score)
      .slice(0, 3)
      .map((entry, idx) => ({
        ...entry.item,
        recommendationReason: entry.reason,
        matchScore: entry.score
      }));
  }

  applyTextureBiasToProducts(products, userTextureBias = null) {
    if (!Array.isArray(products) || !products.length) {
      return products;
    }

    const hydrationBias = Number(userTextureBias?.hydrationBias || 0);
    if (!hydrationBias) {
      return products;
    }

    return products
      .map((item) => {
        const purpose = String(item.purpose || item.Purpose || '').toLowerCase();
        const current = Number(item.matchScore);
        const baseScore = Number.isFinite(current) ? current : 0.6;
        let bonus = 0;

        if (hydrationBias > 0) {
          if (purpose.includes('保濕') || purpose.includes('水潤') || purpose.includes('修護')) {
            bonus = Math.min(0.08, hydrationBias * 0.025);
          }
        } else {
          if (purpose.includes('控油') || purpose.includes('持妝') || purpose.includes('清爽')) {
            bonus = Math.min(0.08, Math.abs(hydrationBias) * 0.025);
          }
        }

        return {
          ...item,
          matchScore: Number(Math.min(1, baseScore + bonus).toFixed(3))
        };
      })
      .sort((left, right) => (right.matchScore || 0) - (left.matchScore || 0))
      .slice(0, 3);
  }

  generateReason(analysis, product) {
    const category = product.category || product.Category || '';
    const purpose = product.purpose || product.Purpose || '';
    const skinType = analysis.skin_type;
    
    if (category.includes('底妝')) {
      if (analysis.features.t_zone_shine === 'high') {
        return `T 區易出油，適合${product.brand}的${category}產品來控制油脂`;
      } else if (analysis.features.pore_visibility === 'visible') {
        return `毛孔明顯，${product.brand}的${category}可幫助修飾`;
      }
    }
    
    if (purpose.includes('保濕') && skinType.includes('乾')) {
      return `膚質偏乾，${product.brand}的${category}提供充足保濕`;
    }
    
    if (purpose.includes('控油') && skinType.includes('油')) {
      return `膚質偏油，${product.brand}的${category}有助控油`;
    }
    
    return `根據膚質分析，${product.brand}的${category}是合適的選擇`;
  }

  async findTable(conn, candidates) {
    const [rows] = await conn.query('SHOW TABLES');
    const tables = rows.map((row) => Object.values(row)[0]);

    for (const candidate of candidates) {
      const exact = tables.find((t) => t === candidate);
      if (exact) {
        return exact;
      }
      const caseInsensitive = tables.find((t) => String(t).toLowerCase() === candidate.toLowerCase());
      if (caseInsensitive) {
        return caseInsensitive;
      }
    }
    return null;
  }

  pickExistingColumn(columns, candidates) {
    for (const candidate of candidates) {
      const exact = columns.find((col) => col === candidate);
      if (exact) {
        return exact;
      }
      const ci = columns.find((col) => String(col).toLowerCase() === candidate.toLowerCase());
      if (ci) {
        return ci;
      }
    }
    return null;
  }

  async toBase64(imageInput) {
    if (typeof imageInput === 'string') {
      const absolutePath = path.resolve(imageInput);
      const data = await fs.readFile(absolutePath);
      return {
        base64: data.toString('base64'),
        mimeType: this.detectMimeTypeFromPath(absolutePath)
      };
    }

    if (Buffer.isBuffer(imageInput)) {
      return {
        base64: imageInput.toString('base64'),
        mimeType: 'image/jpeg'
      };
    }

    if (typeof File !== 'undefined' && imageInput instanceof File) {
      const arrayBuffer = await imageInput.arrayBuffer();
      const data = Buffer.from(arrayBuffer);
      return {
        base64: data.toString('base64'),
        mimeType: imageInput.type || 'image/jpeg'
      };
    }

    throw new Error('imageInput 僅支援檔案路徑(string)、Buffer 或 File');
  }

  detectMimeTypeFromPath(filePath) {
    const lower = filePath.toLowerCase();
    if (lower.endsWith('.png')) return 'image/png';
    if (lower.endsWith('.webp')) return 'image/webp';
    if (lower.endsWith('.gif')) return 'image/gif';
    return 'image/jpeg';
  }

  parseModelJson(content) {
    const trimmed = String(content).trim();
    try {
      return JSON.parse(trimmed);
    } catch {
      const start = trimmed.indexOf('{');
      const end = trimmed.lastIndexOf('}');
      if (start >= 0 && end > start) {
        const candidate = trimmed.slice(start, end + 1);
        return JSON.parse(candidate);
      }
      throw new Error('Groq 回傳不是有效 JSON');
    }
  }

  validateAnalysis(payload) {
    const skinTypes = ['混油皮', '乾性皮', '油性皮', '中性皮', '混乾皮', '敏感肌'];
    const shines = ['high', 'medium', 'low'];
    const pores = ['visible', 'fine'];

    if (!payload?.detected_lab || typeof payload.detected_lab !== 'object') {
      throw new Error('缺少 detected_lab');
    }

    const L = Number(payload.detected_lab.L);
    const a = Number(payload.detected_lab.a);
    const b = Number(payload.detected_lab.b);

    if ([L, a, b].some(Number.isNaN)) {
      throw new Error('detected_lab 需為數值');
    }

    if (!skinTypes.includes(payload.skin_type)) {
      throw new Error(`skin_type 不在允許範圍: ${payload.skin_type}`);
    }

    if (!payload.features || typeof payload.features !== 'object') {
      throw new Error('缺少 features');
    }

    if (!shines.includes(payload.features.t_zone_shine)) {
      throw new Error('features.t_zone_shine 必須是 high/medium/low');
    }

    if (!pores.includes(payload.features.pore_visibility)) {
      throw new Error('features.pore_visibility 必須是 visible/fine');
    }

    if (typeof payload.features.redness !== 'boolean') {
      throw new Error('features.redness 必須是 boolean');
    }

    const confidence = Number(payload.confidence_score);
    if (Number.isNaN(confidence) || confidence < 0 || confidence > 1) {
      throw new Error('confidence_score 必須介於 0 到 1');
    }

    return {
      detected_lab: { L, a, b },
      skin_type: payload.skin_type,
      features: {
        t_zone_shine: payload.features.t_zone_shine,
        pore_visibility: payload.features.pore_visibility,
        redness: payload.features.redness
      },
      confidence_score: confidence
    };
  }
}

export const skinAnalysisSystemPrompt = SYSTEM_PROMPT;

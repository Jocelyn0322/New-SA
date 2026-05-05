try {
  await import('dotenv/config');
} catch (error) {
  if (error?.code !== 'ERR_MODULE_NOT_FOUND') {
    throw error;
  }
}

const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
const ALLOWED_SKIN_TYPES = ['混油皮', '乾性皮', '油性皮', '中性皮', '混乾皮', '敏感肌'];

const SYSTEM_PROMPT = `你是皮膚科成分顧問。請根據指定膚質，列出「不適合或需謹慎使用」的化妝品成分。
嚴格規則：
1) 只能輸出 JSON，不可輸出任何說明文字。
2) JSON 結構必須為：
{
  "skin_type": "混油皮",
  "avoid_ingredients": [
    {"ingredient": "Alcohol Denat.", "reason": "可能加劇乾燥與刺激"}
  ],
  "suitable_focus": ["保濕修護", "低刺激配方"],
  "disclaimer": "僅供保養建議，若有皮膚疾病請諮詢醫師"
}
3) avoid_ingredients 至少 4 項，最多 8 項。
4) reason 請簡短、可讀。
5) 請使用繁體中文。`;

function parseModelJson(content) {
  const trimmed = String(content || '').trim();
  try {
    return JSON.parse(trimmed);
  } catch {
    const start = trimmed.indexOf('{');
    const end = trimmed.lastIndexOf('}');
    if (start >= 0 && end > start) {
      return JSON.parse(trimmed.slice(start, end + 1));
    }
    throw new Error('模型回傳不是有效 JSON');
  }
}

function validatePayload(payload, requestedSkinType) {
  if (!payload || typeof payload !== 'object') {
    throw new Error('AI 回傳格式錯誤');
  }

  const skinType = String(payload.skin_type || requestedSkinType || '').trim();
  if (!ALLOWED_SKIN_TYPES.includes(skinType)) {
    throw new Error('AI 回傳的膚質類型不合法');
  }

  const avoid = Array.isArray(payload.avoid_ingredients) ? payload.avoid_ingredients : [];
  const normalizedAvoid = avoid
    .map((item) => ({
      ingredient: String(item?.ingredient || '').trim(),
      reason: String(item?.reason || '').trim()
    }))
    .filter((item) => item.ingredient && item.reason)
    .slice(0, 8);

  if (normalizedAvoid.length === 0) {
    throw new Error('AI 未回傳可用的成分避雷項目');
  }

  const suitableFocus = Array.isArray(payload.suitable_focus)
    ? payload.suitable_focus.map((item) => String(item).trim()).filter(Boolean).slice(0, 6)
    : [];

  const disclaimer = String(payload.disclaimer || '僅供保養建議，若有皮膚疾病請諮詢醫師').trim();

  return {
    skin_type: skinType,
    avoid_ingredients: normalizedAvoid,
    suitable_focus: suitableFocus,
    disclaimer
  };
}

async function readStdin() {
  return new Promise((resolve, reject) => {
    let body = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk) => {
      body += chunk;
    });
    process.stdin.on('end', () => resolve(body));
    process.stdin.on('error', reject);
  });
}

async function main() {
  const apiKey = process.env.GROQ_API_KEY;
  if (!apiKey) {
    throw new Error('缺少 GROQ_API_KEY，請先設定環境變數');
  }

  const raw = await readStdin();
  const input = JSON.parse(raw || '{}');
  const skinType = String(input.skinType || '').trim();

  if (!ALLOWED_SKIN_TYPES.includes(skinType)) {
    throw new Error('skinType 不在允許範圍');
  }

  const model = process.env.GROQ_TEXT_MODEL || process.env.GROQ_VISION_MODEL || 'meta-llama/llama-4-scout-17b-16e-instruct';

  const response = await fetch(GROQ_ENDPOINT, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      model,
      temperature: 0.2,
      response_format: { type: 'json_object' },
      messages: [
        { role: 'system', content: SYSTEM_PROMPT },
        {
          role: 'user',
          content: `請針對膚質「${skinType}」提供不適合或需謹慎使用的化妝品成分建議。`
        }
      ]
    })
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

  const parsed = parseModelJson(content);
  const validated = validatePayload(parsed, skinType);

  process.stdout.write(JSON.stringify(validated));
}

main().catch((error) => {
  process.stderr.write(JSON.stringify({
    error: true,
    message: error.message || 'ingredient advice failed'
  }));
  process.exit(1);
});

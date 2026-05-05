try {
  await import('dotenv/config');
} catch (error) {
  if (error?.code !== 'ERR_MODULE_NOT_FOUND') {
    throw error;
  }
}
import { SkinAnalysisService } from '../services/SkinAnalysisService.js';

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
  const raw = await readStdin();
  if (!raw) {
    throw new Error('缺少輸入內容');
  }

  const payload = JSON.parse(raw);
  const imageBase64 = String(payload.imageBase64 || '').trim();
  const userPreference = String(payload.userPreference || 'Matte');
  const makeupPreference = payload.makeupPreference && typeof payload.makeupPreference === 'object'
    ? payload.makeupPreference
    : {};

  if (!imageBase64) {
    throw new Error('缺少 imageBase64');
  }

  const cleanBase64 = imageBase64.includes(',')
    ? imageBase64.split(',').pop()
    : imageBase64;

  const imageBuffer = Buffer.from(cleanBase64, 'base64');
  if (!imageBuffer.length) {
    throw new Error('圖片格式錯誤，無法解析 Base64');
  }

  const service = new SkinAnalysisService();
  const analysis = await service.uploadAndAnalyze(imageBuffer);
  const linkedResult = await service.resolveSkinProfileAndProducts(analysis, userPreference, makeupPreference);

  process.stdout.write(JSON.stringify(linkedResult));
}

main().catch((error) => {
  process.stderr.write(JSON.stringify({
    error: true,
    message: error.message || 'analysis failed'
  }));
  process.exit(1);
});

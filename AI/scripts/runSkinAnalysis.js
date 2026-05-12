try {
  await import('dotenv/config');
} catch (error) {
  if (error?.code !== 'ERR_MODULE_NOT_FOUND') {
    throw error;
  }
}
import { SkinAnalysisService } from '../services/SkinAnalysisService.js';

async function main() {
  const imagePath = process.argv[2];
  const finish = process.argv[3] || 'Matte';

  if (!imagePath) {
    console.error('用法: node scripts/runSkinAnalysis.js <imagePath> [finish]');
    process.exit(1);
  }

  const service = new SkinAnalysisService();
  const analysis = await service.uploadAndAnalyze(imagePath);
  const linkedResult = await service.resolveSkinProfileAndProducts(analysis, finish);

  console.log(JSON.stringify(linkedResult, null, 2));
}

main().catch((error) => {
  console.error('[runSkinAnalysis] Error:', error.message);
  process.exit(1);
});

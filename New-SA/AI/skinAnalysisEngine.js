// skinAnalysisEngine.js
// Comprehensive skin analysis engine for tone and type detection

function ensureSkinTonesData(skinTonesData) {
  if (!Array.isArray(skinTonesData) || skinTonesData.length === 0) {
    throw new Error('skinTonesData is required and must be a non-empty array');
  }
  return skinTonesData;
}

/**
 * Skin Profile data structure for 6 skin types
 */
const SKIN_PROFILES = {
  DRY: {
    type: 'DRY',
    displayName: '乾性肌',
    description: '視覺低反光、毛孔細；全臉緊繃起皮、卡粉',
    characteristics: ['low_reflection', 'fine_pores', 'tight_feeling', 'powdery_finish'],
    recommendedFinish: 'Hydrating'
  },
  OILY: {
    type: 'OILY',
    displayName: '油性肌',
    description: '視覺高反光、毛孔粗；全臉泛油光、易長痘',
    characteristics: ['high_reflection', 'coarse_pores', 'oily_shine', 'acne_prone'],
    recommendedFinish: 'Matte'
  },
  NEUTRAL: {
    type: 'NEUTRAL',
    displayName: '中性肌',
    description: '視覺分佈均勻細膩；水油平衡、很少出問題',
    characteristics: ['balanced_reflection', 'fine_texture', 'balanced_oil', 'problem_free'],
    recommendedFinish: 'Natural'
  },
  COMBO_DRY: {
    type: 'COMBO_DRY',
    displayName: '混乾皮',
    description: 'T區油、臉頰乾；T區毛孔明顯、臉頰卡粉',
    characteristics: ['t_zone_oily', 'cheeks_dry', 't_zone_pores', 'cheeks_powdery'],
    recommendedFinish: 'Matte'
  },
  COMBO_OILY: {
    type: 'COMBO_OILY',
    displayName: '混油皮',
    description: 'T區油光長痘、臉頰清爽；T區毛孔粗大、底妝易脫妝',
    characteristics: ['t_zone_oily_acne', 'cheeks_clear', 't_zone_coarse_pores', 'makeup_fade'],
    recommendedFinish: 'Matte'
  },
  SENSITIVE: {
    type: 'SENSITIVE',
    displayName: '敏感肌',
    description: '局部泛紅；遇刺激易刺痛',
    characteristics: ['redness', 'irritation', 'reactive', 'delicate'],
    recommendedFinish: 'Gentle'
  }
};

/**
 * Convert RGB color to LAB color space
 * @param {number} r - Red component (0-255)
 * @param {number} g - Green component (0-255)
 * @param {number} b - Blue component (0-255)
 * @returns {Object} LAB color {L, a, b}
 */
function rgbToLab(r, g, b) {
  // Normalize RGB to 0-1
  let rNorm = r / 255;
  let gNorm = g / 255;
  let bNorm = b / 255;

  // Apply gamma correction
  rNorm = rNorm > 0.04045 ? Math.pow((rNorm + 0.055) / 1.055, 2.4) : rNorm / 12.92;
  gNorm = gNorm > 0.04045 ? Math.pow((gNorm + 0.055) / 1.055, 2.4) : gNorm / 12.92;
  bNorm = bNorm > 0.04045 ? Math.pow((bNorm + 0.055) / 1.055, 2.4) : bNorm / 12.92;

  // Convert to XYZ
  let x = rNorm * 0.4124 + gNorm * 0.3576 + bNorm * 0.1805;
  let y = rNorm * 0.2126 + gNorm * 0.7152 + bNorm * 0.0722;
  let z = rNorm * 0.0193 + gNorm * 0.1192 + bNorm * 0.9505;

  // Normalize by D65 white point
  x /= 0.95047;
  y /= 1.0;
  z /= 1.08883;

  // Convert to LAB
  const f = (t) => t > Math.pow(6/29, 3) ? Math.pow(t, 1/3) : (1/3) * Math.pow(29/6, 2) * t + 4/29;

  const L = 116 * f(y) - 16;
  const a = 500 * (f(x) - f(y));
  const b_lab = 200 * (f(y) - f(z));

  return { L, a, b: b_lab };
}

/**
 * Calculate Euclidean distance between two points in LAB color space
 * @param {Object} lab1 - First LAB color {L, a, b}
 * @param {Object} lab2 - Second LAB color {L, a, b}
 * @returns {number} Euclidean distance
 */
function calculateLabDistance(lab1, lab2) {
  const deltaL = lab1.L - lab2.L;
  const deltaA = lab1.a - lab2.a;
  const deltaB = lab1.b - lab2.b;
  return Math.sqrt(deltaL * deltaL + deltaA * deltaA + deltaB * deltaB);
}

/**
 * Find the closest skin tone match for given RGB values
 * @param {number} r - Red component (0-255)
 * @param {number} g - Green component (0-255)
 * @param {number} b - Blue component (0-255)
 * @returns {Object} Closest matching skin tone with distance
 */
function findClosestSkinToneFromRgb(r, g, b, skinTonesData) {
  const detectedLab = rgbToLab(r, g, b);
  return findClosestSkinTone(detectedLab, skinTonesData);
}

/**
 * Find the closest skin tone match for a given LAB color
 * @param {Object} detectedLab - Detected skin tone in LAB {L, a, b}
 * @returns {Object} Closest matching skin tone with distance
 */
function findClosestSkinTone(detectedLab, skinTonesData) {
  if (!detectedLab || typeof detectedLab.L !== 'number' ||
      typeof detectedLab.a !== 'number' || typeof detectedLab.b !== 'number') {
    throw new Error('Invalid LAB color input');
  }

  let closestTone = null;
  let minDistance = Infinity;

  const tones = ensureSkinTonesData(skinTonesData);
  for (const tone of tones) {
    // Convert RGB to LAB if not already present
    const toneLab = tone.lab || rgbToLab(tone.rgb.r, tone.rgb.g, tone.rgb.b);
    const distance = calculateLabDistance(detectedLab, toneLab);
    if (distance < minDistance) {
      minDistance = distance;
      closestTone = { ...tone, lab: toneLab, distance };
    }
  }

  return closestTone;
}

/**
 * Analyze skin type based on image analysis and user questionnaire
 * @param {Object} analysisData - Analysis data from image processing
 * @param {Object} userAnswers - User's questionnaire answers
 * @returns {Object} Skin type analysis result
 */
function analyzeSkinType(analysisData, userAnswers = {}) {
  const {
    tZoneReflection = 0.5,    // T-zone reflection (0-1)
    cheekReflection = 0.5,    // Cheek reflection (0-1)
    poreDensity = 0.5,        // Pore density (0-1)
    rednessLevel = 0.3        // Redness level (0-1)
  } = analysisData;

  // Default user answers
  const answers = {
    sensitiveReaction: userAnswers.sensitiveReaction || false,
    tightFeeling: userAnswers.tightFeeling || false,
    oilControl: userAnswers.oilControl || false,
    ...userAnswers
  };

  // Skin type determination logic
  let skinType = 'NEUTRAL';

  // Rule 1: Combo Dry - T-zone oily AND cheeks dry
  if (tZoneReflection > 0.6 && cheekReflection < 0.4) {
    skinType = 'COMBO_DRY';
  }
  // Rule 2: Combo Oily - T-zone oily AND cheeks slightly oily
  else if (tZoneReflection > 0.6 && cheekReflection > 0.5) {
    skinType = 'COMBO_OILY';
  }
  // Rule 3: Sensitive - High redness OR user reports sensitivity
  else if (rednessLevel > 0.5 || answers.sensitiveReaction) {
    skinType = 'SENSITIVE';
  }
  // Rule 4: Dry - Low overall reflection AND user reports tightness
  else if (tZoneReflection < 0.4 && cheekReflection < 0.4 && answers.tightFeeling) {
    skinType = 'DRY';
  }
  // Rule 5: Oily - High reflection and coarse pores
  else if (tZoneReflection > 0.7 && poreDensity > 0.6) {
    skinType = 'OILY';
  }

  return {
    type: skinType,
    profile: SKIN_PROFILES[skinType],
    analysisData: {
      tZoneReflection,
      cheekReflection,
      poreDensity,
      rednessLevel
    },
    userAnswers: answers,
    confidence: calculateConfidence(analysisData, answers)
  };
}

/**
 * Calculate confidence score for skin type analysis
 * @param {Object} analysisData - Image analysis data
 * @param {Object} userAnswers - User questionnaire answers
 * @returns {number} Confidence score (0-1)
 */
function calculateConfidence(analysisData, userAnswers) {
  let confidence = 0.5; // Base confidence

  // Image analysis contributes 60% to confidence
  const imageFactors = ['tZoneReflection', 'cheekReflection', 'poreDensity', 'rednessLevel'];
  let imageScore = 0;

  imageFactors.forEach(factor => {
    if (Math.abs(analysisData[factor] - 0.5) > 0.3) {
      imageScore += 0.15; // Clear deviation from neutral
    }
  });

  // User answers contribute 40% to confidence
  let userScore = 0;
  if (userAnswers.sensitiveReaction) userScore += 0.15;
  if (userAnswers.tightFeeling) userScore += 0.15;
  if (userAnswers.oilControl) userScore += 0.1;

  confidence = Math.min(0.95, 0.5 + imageScore + userScore);

  return confidence;
}

/**
 * Get recommended products based on skin tone and type
 * @param {Object} skinTone - Detected skin tone
 * @param {Object} skinType - Detected skin type
 * @param {Array} productDatabase - Available products
 * @returns {Array} Filtered recommended products
 */
function getRecommendedProducts(skinTone, skinType, productDatabase = []) {
  const recommendedFinish = skinType.profile.recommendedFinish;

  return productDatabase.filter(product => {
    // Match finish type
    if (recommendedFinish === 'Matte' && !product.tags.includes('霧面')) {
      return false;
    }

    // Additional filtering based on skin type
    switch (skinType.type) {
      case 'DRY':
        return !product.hasAlcohol && product.tags.includes('保濕');
      case 'OILY':
        return product.tags.includes('控油') || product.tags.includes('霧面');
      case 'SENSITIVE':
        return !product.hasAlcohol && product.tags.includes('溫和');
      case 'COMBO_DRY':
        return product.tags.includes('保濕') && product.tags.includes('控油');
      case 'COMBO_OILY':
        return product.tags.includes('控油') && product.tags.includes('霧面');
      default:
        return true;
    }
  });
}

export {
  SKIN_PROFILES,
  findClosestSkinTone,
  findClosestSkinToneFromRgb,
  analyzeSkinType,
  getRecommendedProducts,
  calculateLabDistance,
  rgbToLab
};
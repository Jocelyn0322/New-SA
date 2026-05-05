// skinToneMatcher.js
// Skin tone matching algorithm using Euclidean distance in LAB color space

function ensureSkinTonesData(skinTonesData) {
  if (!Array.isArray(skinTonesData) || skinTonesData.length === 0) {
    throw new Error('skinTonesData is required and must be a non-empty array');
  }
  return skinTonesData;
}

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
 * Find the closest skin tone match with quality check
 * @param {Object} detectedLab - Detected skin tone in LAB {L, a, b}
 * @param {number} maxDistance - Maximum acceptable distance (default: 20)
 * @returns {Object|null} Closest matching skin tone with distance, or null if no good match
 */
function findClosestSkinToneWithThreshold(detectedLab, maxDistance = 20, skinTonesData) {
  const result = findClosestSkinTone(detectedLab, skinTonesData);

  if (result.distance <= maxDistance) {
    return result;
  }

  return null; // No suitable match found
}

/**
 * Find skin tones within a certain distance threshold
 * @param {Object} detectedLab - Detected skin tone in LAB {L, a, b}
 * @param {number} threshold - Maximum distance to consider
 * @returns {Array} Array of matching skin tones with distances
 */
function findSkinTonesWithinThreshold(detectedLab, threshold = 10, skinTonesData) {
  if (!detectedLab || typeof detectedLab.L !== 'number' ||
      typeof detectedLab.a !== 'number' || typeof detectedLab.b !== 'number') {
    throw new Error('Invalid LAB color input');
  }

  const matches = [];
  const tones = ensureSkinTonesData(skinTonesData);
  for (const tone of tones) {
    // Convert RGB to LAB if not already present
    const toneLab = tone.lab || rgbToLab(tone.rgb.r, tone.rgb.g, tone.rgb.b);
    const distance = calculateLabDistance(detectedLab, toneLab);
    if (distance <= threshold) {
      matches.push({ ...tone, lab: toneLab, distance });
    }
  }

  // Sort by distance (closest first)
  matches.sort((a, b) => a.distance - b.distance);

  return matches;
}

export {
  findClosestSkinTone,
  findClosestSkinToneFromRgb,
  findClosestSkinToneWithThreshold,
  findSkinTonesWithinThreshold,
  calculateLabDistance,
  rgbToLab
};
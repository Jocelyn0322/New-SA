// example.js
// Example usage of the skin tone matcher

import { findClosestSkinTone, findClosestSkinToneWithThreshold, findSkinTonesWithinThreshold } from './skinToneMatcher.js';

// Sample skin tone data for example usage. In production, this should come from the database.
const sampleSkinTonesData = [
  { toneName: '粉一白', hex: '#F9E6E1', rgb: { r: 249, g: 230, b: 225 }, category: 'Pink' },
  { toneName: '黃一白', hex: '#FDE6CE', rgb: { r: 253, g: 230, b: 206 }, category: 'Yellow' }
];

// Example detected skin tone in LAB color space
// This would typically come from image analysis
const detectedSkinTone = {
  L: 85.5,  // Lightness
  a: 8.2,   // Red-green axis
  b: 15.8   // Yellow-blue axis
};

console.log('Detected skin tone LAB:', detectedSkinTone);

// Find the closest matching skin tone (always returns a match)
const closestMatch = findClosestSkinTone(detectedSkinTone, sampleSkinTonesData);
console.log('\n=== 總是找到最接近的匹配 ===');
console.log(`Name: ${closestMatch.toneName}`);
console.log(`Category: ${closestMatch.category}`);
console.log(`Hex: ${closestMatch.hex}`);
console.log(`LAB: L=${closestMatch.lab.L}, a=${closestMatch.lab.a}, b=${closestMatch.lab.b}`);
console.log(`Distance: ${closestMatch.distance.toFixed(2)}`);

// Test with different thresholds
console.log('\n=== 不同距離閾值測試 ===');

const thresholds = [5, 10, 15, 20, 30];

thresholds.forEach(threshold => {
  const result = findClosestSkinToneWithThreshold(detectedSkinTone, threshold, sampleSkinTonesData);
  if (result) {
    console.log(`閾值 ${threshold}: ✅ 找到匹配 - ${result.toneName} (距離: ${result.distance.toFixed(2)})`);
  } else {
    console.log(`閾值 ${threshold}: ❌ 沒有找到合適匹配`);
  }
});

// Test with a very different skin tone (simulating "小麥皮" - wheat skin)
console.log('\n=== 測試非常不同的膚色 ===');
const veryDifferentSkinTone = {
  L: 40,   // Much darker
  a: 15,   // More red
  b: 25    // More yellow
};

console.log('非常不同的膚色 LAB:', veryDifferentSkinTone);

const veryDifferentMatch = findClosestSkinTone(veryDifferentSkinTone, sampleSkinTonesData);
console.log(`最接近匹配: ${veryDifferentMatch.toneName} (距離: ${veryDifferentMatch.distance.toFixed(2)})`);

// Test threshold for very different skin tone
const thresholdResult = findClosestSkinToneWithThreshold(veryDifferentSkinTone, 15, sampleSkinTonesData);
if (thresholdResult) {
  console.log(`✅ 在 15 單位閾值內找到匹配`);
} else {
  console.log(`❌ 在 15 單位閾值內沒有找到匹配 - 這表示膚色超出您的資料庫範圍`);
}

// Find all matches within a threshold
const threshold = 15; // Adjust threshold as needed
const matches = findSkinTonesWithinThreshold(detectedSkinTone, threshold, sampleSkinTonesData);
console.log(`\n=== 在 ${threshold} 單位內的所有匹配 ===`);
matches.forEach((match, index) => {
  console.log(`${index + 1}. ${match.toneName} (${match.category}) - Distance: ${match.distance.toFixed(2)}`);
});
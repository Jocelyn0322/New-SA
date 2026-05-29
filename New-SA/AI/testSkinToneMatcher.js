// testSkinToneMatcher.js
// Unit tests for skin tone matcher functions

import { findClosestSkinTone, findClosestSkinToneWithThreshold, findSkinTonesWithinThreshold, calculateLabDistance } from './skinToneMatcher.js';

const sampleSkinTonesData = [
  { toneName: '粉一白', hex: '#F9E6E1', rgb: { r: 249, g: 230, b: 225 }, category: 'Pink' },
  { toneName: '黃一白', hex: '#FDE6CE', rgb: { r: 253, g: 230, b: 206 }, category: 'Yellow' },
  { toneName: '偏紅暖一白', hex: '#EACDB7', rgb: { r: 234, g: 205, b: 183 }, category: 'Red-Warm' }
];

// Test calculateLabDistance
function testCalculateLabDistance() {
  console.log('Testing calculateLabDistance...');

  const lab1 = { L: 50, a: 10, b: 5 };
  const lab2 = { L: 60, a: 15, b: 10 };

  const distance = calculateLabDistance(lab1, lab2);
  const expected = Math.sqrt((10*10) + (5*5) + (5*5)); // 11.1803...

  console.log(`Distance: ${distance.toFixed(4)}, Expected: ${expected.toFixed(4)}`);
  assert(Math.abs(distance - expected) < 0.0001, 'Distance calculation failed');
  console.log('✓ calculateLabDistance test passed');
}

// Test findClosestSkinTone
function testFindClosestSkinTone() {
  console.log('\nTesting findClosestSkinTone...');

  // Test with a known skin tone (should match itself)
  const testTone = { L: 92.64966587501561, a: 5.63762886972663, b: 4.75456835933683 }; // 粉一白 exact LAB
  const result = findClosestSkinTone(testTone, sampleSkinTonesData);

  console.log(`Input: ${JSON.stringify(testTone)}`);
  console.log(`Match: ${result.toneName}, Distance: ${result.distance.toFixed(4)}`);

  assert(result.toneName === '粉一白', 'Should match exact tone');
  assert(result.distance < 0.001, 'Distance should be very small for exact match');
  console.log('✓ findClosestSkinTone test passed');
}

// Test findClosestSkinToneWithThreshold
function testFindClosestSkinToneWithThreshold() {
  console.log('\nTesting findClosestSkinToneWithThreshold...');

  // Test with a close match
  const closeTone = { L: 84.1, a: 8.0, b: 14.9 }; // Very close to 偏紅暖一白
  const result = findClosestSkinToneWithThreshold(closeTone, 5, sampleSkinTonesData);
  assert(result !== null, 'Should find match within threshold');
  assert(result.toneName === '偏紅暖一白', 'Should match the correct tone');
  console.log('✓ findClosestSkinToneWithThreshold close match test passed');

  // Test with a far match
  const farTone = { L: 40, a: 15, b: 25 }; // Very different tone
  const farResult = findClosestSkinToneWithThreshold(farTone, 15, sampleSkinTonesData);
  assert(farResult === null, 'Should return null for tone outside threshold');
  console.log('✓ findClosestSkinToneWithThreshold far match test passed');
}

// Test findSkinTonesWithinThreshold
function testFindSkinTonesWithinThreshold() {
  console.log('\nTesting findSkinTonesWithinThreshold...');

  const testTone = { L: 90, a: 5, b: 5 };
  const threshold = 10;
  const results = findSkinTonesWithinThreshold(testTone, threshold, sampleSkinTonesData);

  console.log(`Input: ${JSON.stringify(testTone)}, Threshold: ${threshold}`);
  console.log(`Found ${results.length} matches`);

  results.forEach(match => {
    assert(match.distance <= threshold, `Distance ${match.distance} exceeds threshold ${threshold}`);
  });

  // Should find at least one match
  assert(results.length > 0, 'Should find at least one match within threshold');

  // Results should be sorted by distance
  for (let i = 1; i < results.length; i++) {
    assert(results[i-1].distance <= results[i].distance, 'Results not sorted by distance');
  }

  console.log('✓ findSkinTonesWithinThreshold test passed');
}

// Test error handling
function testErrorHandling() {
  console.log('\nTesting error handling...');

  // Test invalid input
  try {
    findClosestSkinTone(null);
    assert(false, 'Should throw error for null input');
  } catch (error) {
    console.log('✓ Correctly threw error for null input');
  }

  try {
    findClosestSkinTone({ L: 50 }); // Missing a and b
    assert(false, 'Should throw error for incomplete LAB');
  } catch (error) {
    console.log('✓ Correctly threw error for incomplete LAB');
  }
}

// Simple assertion function
function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

// Run all tests
function runTests() {
  try {
    testCalculateLabDistance();
    testFindClosestSkinTone();
    testFindClosestSkinToneWithThreshold();
    testFindSkinTonesWithinThreshold();
    testErrorHandling();
    console.log('\n🎉 All tests passed!');
  } catch (error) {
    console.error('\n❌ Test failed:', error.message);
    process.exit(1);
  }
}

runTests();
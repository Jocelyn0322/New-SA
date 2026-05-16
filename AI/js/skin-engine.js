// skin-engine.js — Skin-type calibration algorithm.
// Pure logic; no DOM or camera dependencies.

const getBaseSkinScore = () => ({
    '混油皮': 0, '乾性皮': 0, '油性皮': 0,
    '中性皮': 0, '混乾皮': 0, '敏感肌': 0,
});

const clamp01 = (value) => Math.max(0, Math.min(1, value));

const calcStdDev = (numbers) => {
    if (!numbers.length) return 0;
    const mean     = numbers.reduce((sum, v) => sum + v, 0) / numbers.length;
    const variance = numbers.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / numbers.length;
    return Math.sqrt(variance);
};

const resolveSkinFamily = (skinType) => {
    if (['油性皮', '混油皮'].includes(skinType)) return 'oily';
    if (['乾性皮', '混乾皮'].includes(skinType)) return 'dry';
    if (skinType === '敏感肌') return 'sensitive';
    return 'neutral';
};

const getCalibratedSkinType = (aiOrManualSkinType, aiConfidence = 0.5) => {
    const combinedScores = getBaseSkinScore();
    const quizScores     = getBaseSkinScore();
    const aiScores       = getBaseSkinScore();

    if (aiOrManualSkinType && aiScores[aiOrManualSkinType] !== undefined) {
        aiScores[aiOrManualSkinType] += 4.0;
    }

    const { q1, q2, q3, q4 } = quizAnswers.value;

    const answerVectorMap = {
        q1: { A: -2, B: -1, C: 1, D: 2, E: 0 },
        q2: { A: -2, B: -1, C: 2, D: 0 },
        q3: { A: -2, B: -1, C: 2, D: 0 },
        q4: { A: 2, B: 1, C: -2 },
    };

    const answerVector = [
        answerVectorMap.q1[q1] ?? 0,
        answerVectorMap.q2[q2] ?? 0,
        answerVectorMap.q3[q3] ?? 0,
        answerVectorMap.q4[q4] ?? 0,
    ];

    const stdDev          = calcStdDev(answerVector);
    const consistencyScore = Number(clamp01(1 - stdDev / 2.4).toFixed(2));

    const q1Weights = {
        A: { '乾性皮': 2.8, '敏感肌': 0.6 },
        B: { '混乾皮': 2.8, '敏感肌': 0.4 },
        C: { '混油皮': 2.8, '敏感肌': 0.4 },
        D: { '油性皮': 2.8, '敏感肌': 0.3 },
        E: { '中性皮': 2.8, '敏感肌': 0.2 },
    };
    const q2Weights = {
        A: { '乾性皮': 2.2, '混乾皮': 0.8, '敏感肌': 0.4 },
        B: { '混乾皮': 2.0, '混油皮': 2.0, '敏感肌': 0.3 },
        C: { '油性皮': 2.2, '混油皮': 1.0, '敏感肌': 0.3 },
        D: { '中性皮': 2.2, '敏感肌': 0.2 },
    };
    const q3Weights = {
        A: { '乾性皮': 2.2, '敏感肌': 0.5 },
        B: { '混乾皮': 2.0, '敏感肌': 0.6 },
        C: { '混油皮': 2.4, '油性皮': 1.2, '敏感肌': 0.5 },
        D: { '中性皮': 2.4, '敏感肌': 0.2 },
    };
    const q4Weights = {
        A: { '敏感肌': 5.5 },
        B: { '敏感肌': 3.2 },
        C: { '敏感肌': 0.0 },
    };

    [q1Weights[q1], q2Weights[q2], q3Weights[q3], q4Weights[q4]].forEach((weights) => {
        if (!weights) return;
        Object.entries(weights).forEach(([skinType, weight]) => {
            if (quizScores[skinType] !== undefined) quizScores[skinType] += weight;
        });
    });

    const consistencyIssues = [];
    if (q4 === 'A' && q3 === 'D') {
        consistencyIssues.push('Q3 與 Q4 可能不同維度，需同時看持妝與耐受度');
    }
    if (q4 === 'A' && q1 === 'E' && q2 === 'D' && q3 === 'D') {
        consistencyIssues.push('Q4 顯示高度敏感，但其他題目偏穩定，建議保留敏感肌標記');
    }

    let quizWeight = 0.50 + 0.35 * consistencyScore;
    let aiWeight   = 0.20 + 0.20 * clamp01(aiConfidence);

    Object.keys(combinedScores).forEach((skinType) => {
        combinedScores[skinType] = quizScores[skinType] * quizWeight + aiScores[skinType] * aiWeight;
    });

    const findTopType = (scoreMap) => {
        let topType = '中性皮', topScore = -Infinity;
        Object.entries(scoreMap).forEach(([skinType, score]) => {
            if (score > topScore) { topScore = score; topType = skinType; }
        });
        return { topType, topScore };
    };

    const quizTop    = findTopType(quizScores);
    const aiTop      = findTopType(aiScores);
    const combinedTop = findTopType(combinedScores);

    const combinedNonSensitiveScores = { ...combinedScores };
    delete combinedNonSensitiveScores['敏感肌'];
    const nonSensitiveTop      = findTopType(combinedNonSensitiveScores);
    const sensitiveQuizScore   = quizScores['敏感肌']    || 0;
    const sensitiveCombinedScore = combinedScores['敏感肌'] || 0;
    const sensitiveForced      = q4 === 'A';
    const secondaryType        = (sensitiveForced || sensitiveCombinedScore >= 2.8 || sensitiveQuizScore >= 2.8)
        ? '敏感肌' : '';

    const aiFamily             = resolveSkinFamily(aiTop.topType);
    const quizFamily           = resolveSkinFamily(quizTop.topType);
    const crossCategoryConflict = aiTop.topScore > 0 && aiFamily !== quizFamily;
    const conflictGap          = Math.abs((quizScores[quizTop.topType] || 0) - (quizScores[aiTop.topType] || 0));
    const isOutlier            = crossCategoryConflict && conflictGap >= 1.2;

    if (isOutlier) {
        quizWeight *= 0.75;
        aiWeight   *= 1.25;
        const normalized = quizWeight + aiWeight;
        quizWeight /= normalized;
        aiWeight   /= normalized;
        Object.keys(combinedScores).forEach((skinType) => {
            combinedScores[skinType] = quizScores[skinType] * quizWeight + aiScores[skinType] * aiWeight;
        });
    }

    const needsRetestFlag = consistencyScore < 0.42 || (isOutlier && consistencyScore < 0.7) || consistencyIssues.length >= 3;

    const hasQuizAnswers      = Object.values(quizAnswers.value).some(v => v !== '');
    const blockRecommendation = hasQuizAnswers && (consistencyScore < 0.42 || (isOutlier && consistencyScore < 0.55));

    let finalType = nonSensitiveTop.topType || '中性皮';
    if (finalType === '敏感肌') finalType = '中性皮';

    return {
        finalType, secondaryType,
        scores: combinedScores, quizScores, aiScores,
        quizWeight, aiWeight,
        consistencyScore, consistencyIssues,
        isOutlier, needsRetest: needsRetestFlag,
        blockRecommendation, sensitiveForced,
    };
};

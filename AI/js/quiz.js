// quiz.js — Tone quiz, skin-type quiz, and step navigation logic.
// Depends on: state.js (refs), skin-engine.js (getCalibratedSkinType).

const setToneQuizAnswer = (questionKey, optionValue) => {
    toneQuizAnswers.value[questionKey] = optionValue;
    toneGuess.value = inferToneGuess();
    const order = ['t1', 't2', 't3'];
    const idx = order.indexOf(questionKey);
    if (idx < 0) return;
    toneQuizTyping.value = true;
    setTimeout(() => {
        toneQuizTyping.value = false;
        if (toneQuizStep.value <= idx + 1) toneQuizStep.value = idx + 2;
        if (toneQuizStep.value > toneQuizData.length) applyToneGuessToSelection();
        nextTick(() => {
            const el = document.getElementById('chatEnd');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }, 700);
};

const toneQuizOptionClass = (questionKey, optionValue) => {
    const selected = toneQuizAnswers.value[questionKey] === optionValue;
    return selected
        ? 'w-full text-left p-2.5 rounded-xl border border-amber-400 bg-amber-100 text-amber-900 font-semibold shadow-sm transition'
        : 'w-full text-left p-2.5 rounded-xl border border-amber-200 bg-white text-gray-700 hover:border-amber-300 hover:bg-amber-50 transition';
};

const ensureToneQuizCompleted = () => {
    const { t1, t2, t3 } = toneQuizAnswers.value;
    if (!t1 || !t2 || !t3) {
        alert('請先完成第一頁膚色問答。');
        return false;
    }
    return true;
};

const inferToneGuess = () => {
    const score = { cool: 0, warm: 0, neutral: 0 };
    const answerToFamily = { A: 'cool', B: 'warm', C: 'neutral' };

    ['t1', 't2', 't3'].forEach((key) => {
        const family = answerToFamily[toneQuizAnswers.value[key]];
        if (family) score[family] += 1;
    });

    if (score.cool === 0 && score.warm === 0 && score.neutral === 0) return '';
    if (score.cool > score.warm && score.cool >= score.neutral) return '偏冷調';
    if (score.warm > score.cool && score.warm >= score.neutral) return '偏暖調';
    return '中性調';
};

const applyToneGuessToSelection = () => {
    if (!toneGuess.value || !Array.isArray(skinTonesData.value) || !skinTonesData.value.length) return;

    const keywordMap = {
        '偏冷調': ['冷'],
        '偏暖調': ['暖', '黃'],
        '中性調': ['中性', '中']
    };

    const keywords = keywordMap[toneGuess.value] || [];
    const matched = skinTonesData.value.find((tone) =>
        keywords.some((keyword) => String(tone.toneName || '').includes(keyword))
    );

    if (matched?.toneName) skinTone.value = matched.toneName;
};

const goToSkinTypeStep = () => {
    if (!ensureToneQuizCompleted()) return;
    applyToneGuessToSelection();
    currentStep.value = 2;
};

const goToCameraStep = () => {
    if (!ensureQuizCompleted()) return;
    const quizPreview = getCalibratedSkinType('');
    manualSkinType.value        = quizPreview.finalType || manualSkinType.value;
    manualSensitiveSkin.value   = quizPreview.secondaryType === '敏感肌';
    skinTypeResult.value        = quizPreview.finalType || '';
    skinTypeSecondary.value     = quizPreview.secondaryType || '';
    needsRetest.value           = quizPreview.needsRetest;
    consistencyScoreValue.value = quizPreview.consistencyScore.toFixed(2);
    confirmedSkinTone.value     = false;
    confirmedSkinType.value     = false;
    showMakeupPreference.value  = false;
    currentStep.value = 3;
};

const confirmSkinTone = () => { confirmedSkinTone.value = !confirmedSkinTone.value; };
const confirmSkinType = () => { confirmedSkinType.value = !confirmedSkinType.value; };

const backToToneAndSkinPage = () => { currentStep.value = 3; };
const backToResultsPage     = () => { currentStep.value = 4; };
const goToConfirmStep       = () => { currentStep.value = 5; };

const goToMakeupStep = () => {
    if (!canChooseMakeupPreference.value) {
        alert('請先確認膚色與膚質。');
        return;
    }
    showMakeupPreference.value = true;
    currentStep.value = 5;
};

const setQuizAnswer = (questionKey, optionValue) => {
    quizAnswers.value[questionKey] = optionValue;
    const order = ['q1', 'q2', 'q3', 'q4'];
    const idx = order.indexOf(questionKey);
    if (idx < 0) return;
    skinQuizTyping.value = true;
    setTimeout(() => {
        skinQuizTyping.value = false;
        if (skinQuizStep.value <= idx + 1) skinQuizStep.value = idx + 2;
        nextTick(() => {
            const el = document.getElementById('chatEnd');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }, 700);
};

const quizOptionClass = (questionKey, optionValue) => {
    const selected = quizAnswers.value[questionKey] === optionValue;
    return selected
        ? 'w-full text-left p-2.5 rounded-xl border border-rose-400 bg-rose-100 text-rose-900 font-semibold shadow-sm transition'
        : 'w-full text-left p-2.5 rounded-xl border border-rose-200 bg-white text-gray-700 hover:border-rose-300 hover:bg-rose-50 transition';
};

const makeupOptionClass = (currentValue, optionValue) => {
    const selected = currentValue === optionValue;
    return selected
        ? 'w-full text-left px-3 py-2.5 rounded-xl border border-pink-400 bg-pink-100 text-pink-900 font-semibold shadow-sm transition'
        : 'w-full text-left px-3 py-2.5 rounded-xl border border-pink-200 bg-white text-gray-700 hover:border-pink-300 hover:bg-pink-50 transition';
};

const setMakeupPreference = (key, value) => {
    if (key === 'finish') makeupFinish.value = value;
    if (key === 'style')  makeupStyle.value  = value;
};

const ensureQuizCompleted = () => {
    const { q1, q2, q3, q4 } = quizAnswers.value;
    if (!q1 || !q2 || !q3 || !q4) {
        alert('請先完成「膚質校正問卷（含反向驗證）」才能得到更準確的膚質判定。');
        return false;
    }
    return true;
};

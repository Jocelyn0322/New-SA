// skin-analysis.js — Color sampling, tone matching, manual selector, and image analysis entry point.
// Depends on: state.js, skin-engine.js (getCalibratedSkinType), quiz.js (inferToneGuess), api.js (analyzeWithGroq, analyzeSkinTone)

// ── YCbCr skin-pixel gate ────────────────────────────────────────
const isSkinColor = (r, g, b) => {
    const y  = 0.299 * r + 0.587 * g + 0.114 * b;
    const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
    const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
    return y > 80 && cb > 85 && cb < 135 && cr > 135 && cr < 180;
};

// ── Color utilities ──────────────────────────────────────────────
const hexToRgb = (hex) => {
    if (!hex || typeof hex !== 'string') return null;
    const value = hex.replace('#', '');
    const bigint = parseInt(value, 16);
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8)  & 255,
        b:  bigint        & 255
    };
};

const getRgbDistance = (rgb1, rgb2) =>
    Math.sqrt(
        Math.pow(rgb1.r - rgb2.r, 2) +
        Math.pow(rgb1.g - rgb2.g, 2) +
        Math.pow(rgb1.b - rgb2.b, 2)
    );

const findClosestSkinTone = (r, g, b) => {
    if (!skinTonesData.value || !skinTonesData.value.length) return null;
    const targetRgb = { r, g, b };
    let closest = null;
    let minDistance = Infinity;
    for (const tone of skinTonesData.value) {
        const toneRgb = tone.rgb || hexToRgb(tone.hex);
        if (!toneRgb) continue;
        const distance = getRgbDistance(targetRgb, toneRgb);
        if (distance < minDistance) { minDistance = distance; closest = { ...tone, rgb: toneRgb }; }
    }
    return closest;
};

const rgbToHex = (r, g, b) =>
    '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();

const classifySkinTone = (r, g, b) => {
    const closest = findClosestSkinTone(r, g, b);
    return closest ? closest.toneName : '未知膚色';
};

// ── Cheek sampling ───────────────────────────────────────────────
const getCheekSampleRegions = () => {
    if (!canvas.value) return [];
    const width     = canvas.value.width;
    const height    = canvas.value.height;
    const cheekY    = Math.floor(height * 0.42);
    const cheekH    = Math.floor(height * 0.24);
    const cheekW    = Math.max(40, Math.floor(width * 0.18));
    return [
        { x: Math.floor(width * 0.16), y: cheekY, width: cheekW, height: cheekH },
        { x: Math.floor(width * 0.66), y: cheekY, width: cheekW, height: cheekH }
    ];
};

const sampleCheekSkinTone = () => {
    if (!canvas.value) return null;

    const ctx       = canvas.value.getContext('2d');
    const imageData = ctx.getImageData(0, 0, canvas.value.width, canvas.value.height);
    const { data, width, height } = imageData;
    const samples   = [];

    getCheekSampleRegions().forEach((region) => {
        const startX = Math.max(0, region.x);
        const startY = Math.max(0, region.y);
        const endX   = Math.min(width,  region.x + region.width);
        const endY   = Math.min(height, region.y + region.height);

        for (let y = startY; y < endY; y++) {
            for (let x = startX; x < endX; x++) {
                const idx = (y * width + x) * 4;
                const r = data[idx], g = data[idx + 1], b = data[idx + 2];
                if (!isSkinColor(r, g, b)) continue;
                const brightness = 0.299 * r + 0.587 * g + 0.114 * b;
                if (brightness < 95 || brightness > 240) continue;
                samples.push({ r, g, b, brightness });
            }
        }
    });

    if (!samples.length) return null;

    samples.sort((a, b) => b.brightness - a.brightness);
    const selectedCount   = Math.max(18, Math.floor(samples.length * 0.28));
    const selectedSamples = samples.slice(0, selectedCount);

    const totals = selectedSamples.reduce((acc, s) => {
        acc.r += s.r; acc.g += s.g; acc.b += s.b;
        return acc;
    }, { r: 0, g: 0, b: 0 });

    return {
        r:     Math.round(totals.r / selectedSamples.length),
        g:     Math.round(totals.g / selectedSamples.length),
        b:     Math.round(totals.b / selectedSamples.length),
        count: selectedSamples.length
    };
};

// ── Tone family helpers ──────────────────────────────────────────
const getToneGuessFamily = () => {
    const guess = toneGuess.value || inferToneGuess();
    if (guess === '偏冷調') return 'cool';
    if (guess === '偏暖調') return 'warm';
    if (guess === '中性調') return 'neutral';
    return '';
};

const getToneFamilyFromToneName = (toneName) => {
    const name = String(toneName || '');
    if (name.includes('中性冷') || name.includes('偏紅冷') || name.includes('偏綠冷') || name.includes('粉')) return 'cool';
    if (name.includes('中性暖') || name.includes('偏紅暖') || name.includes('偏綠暖') || name.includes('黃')) return 'warm';
    if (name.includes('中一白') || name.includes('中二白') || name.includes('中三白') || name.includes('中性')) return 'neutral';
    if (name.includes('橄欖')) return 'olive';
    return '';
};

const updateToneMismatchWarning = (toneName, sourceLabel = '後續選擇') => {
    const guessedFamily   = getToneGuessFamily();
    const selectedFamily  = getToneFamilyFromToneName(toneName);

    if (!guessedFamily || !selectedFamily) { toneMismatchWarning.value = ''; return false; }

    if (guessedFamily !== selectedFamily) {
        const familyLabelMap = { cool: '偏冷調', warm: '偏暖調', neutral: '中性調', olive: '橄欖調' };
        toneMismatchWarning.value = `${sourceLabel} 選到的是 ${toneName}，但第一頁問卷推測偏 ${familyLabelMap[guessedFamily] || guessedFamily}，差異有點大，建議再確認一次。`;
        alert(toneMismatchWarning.value);
        return true;
    }

    toneMismatchWarning.value = '';
    return false;
};

const getToneFamilyKeywords = (family) => {
    const keywordMap = {
        cool:    ['冷', '藍', '紫', '粉'],
        warm:    ['暖', '黃', '金', '橘'],
        neutral: ['中性', '中', '自然']
    };
    return keywordMap[family] || [];
};

const selectToneByFamilyPreference = (baseRgb, preferredFamily) => {
    if (!baseRgb) return null;

    const fallbackTone = findClosestSkinTone(baseRgb.r, baseRgb.g, baseRgb.b);
    if (!preferredFamily || !Array.isArray(skinTonesData.value) || !skinTonesData.value.length) return fallbackTone;

    const keywords         = getToneFamilyKeywords(preferredFamily);
    const familyCandidates = skinTonesData.value
        .filter((tone) => keywords.some((kw) => String(tone.toneName || '').includes(kw)))
        .map((tone) => {
            const toneRgb = tone.rgb || hexToRgb(tone.hex);
            if (!toneRgb) return null;
            return { ...tone, rgb: toneRgb, distance: getRgbDistance(baseRgb, toneRgb) };
        })
        .filter(Boolean)
        .sort((a, b) => a.distance - b.distance);

    return familyCandidates[0] || fallbackTone;
};

// ── Multi-step manual tone selector ─────────────────────────────
const startManualSelector = () => {
    showManualSelector.value    = true;
    manualSelectorStep.value    = 1;
    selectedBase.value          = '';
    selectedDepth.value         = '';
    selectedHueBias.value       = '';
    matchedFinalTone.value      = null;
};

const closeManualSelector = () => {
    showManualSelector.value    = false;
    manualSelectorStep.value    = 0;
    selectedBase.value          = '';
    selectedDepth.value         = '';
    selectedHueBias.value       = '';
    matchedFinalTone.value      = null;
};

const selectBase = (base) => {
    selectedBase.value       = base;
    manualSelectorStep.value = 2;
};

const selectDepth = (depth) => {
    selectedDepth.value      = depth;
    manualSelectorStep.value = 3;
};

const selectHueBias = (bias) => {
    selectedHueBias.value    = bias;
    findMatchingTone(selectedBase.value, selectedDepth.value, bias);
    manualSelectorStep.value = 4;
};

const findMatchingTone = (base, depth, hueBias) => {
    let toneName = '', searchTerms = [];

    if (base === 'A') {
        if (hueBias === 'α')                       { toneName = `偏紅暖${depth}白`; searchTerms = ['偏紅', '暖', depth, '白']; }
        else if (hueBias === 'β' || hueBias === 'γ') { toneName = `黃${depth}白`;   searchTerms = ['黃', depth, '白']; }
    } else if (base === 'B') {
        if (hueBias === 'α')                       { toneName = `偏紅冷${depth}白`; searchTerms = ['偏紅', '冷', depth, '白']; }
        else if (hueBias === 'β' || hueBias === 'γ') { toneName = `粉${depth}白`;   searchTerms = ['粉', depth, '白']; }
    } else if (base === 'C') {
        if (hueBias === 'α' || hueBias === 'γ')   { toneName = `中性暖${depth}白`; searchTerms = ['中性', '暖', depth, '白']; }
        else if (hueBias === 'β')                   { toneName = `中性冷${depth}白`; searchTerms = ['中性', '冷', depth, '白']; }
    } else if (base === 'D') {
        toneName = `橄欖${depth}白`; searchTerms = ['橄欖', depth, '白'];
    }

    console.log(`Finding tone: base=${base}, depth=${depth}, bias=${hueBias}, expected=${toneName}`);

    let matching = null;
    if (skinTonesData.value?.length) {
        matching = skinTonesData.value.find(t => t.toneName === toneName);
        if (!matching && searchTerms.length) {
            matching = skinTonesData.value.find(t =>
                searchTerms.slice(0, -1).every(term => t.toneName.includes(term))
            );
        }
        if (!matching && searchTerms.length) {
            matching = skinTonesData.value.find(t => t.toneName.includes(searchTerms[0]));
        }
    }

    matchedFinalTone.value = matching || { toneName, hex: '#FFB6D9', rgb: { r: 255, g: 182, b: 217 } };
};

const confirmManualToneSelection = async () => {
    if (!matchedFinalTone.value?.toneName) {
        alert('請完成所有步驟以選擇膚色');
        return;
    }

    const tone = matchedFinalTone.value;
    skinTone.value = tone.toneName;
    skinCoordinate.value = {
        type:     tone.toneName,
        rgb:      tone.rgb ? `${tone.rgb.r}, ${tone.rgb.g}, ${tone.rgb.b}` : '',
        hex:      tone.hex || '',
        rawRgb:   tone.rgb || null,
        toneGuess: toneGuess.value || ''
    };
    updateToneMismatchWarning(tone.toneName, '手動膚色選擇');

    const baseLabel = { A: '暖/黃', B: '冷/粉', C: '中性', D: '橄欖' }[selectedBase.value];
    const biasLabel = { α: '泛紅暖', β: '純淨冷', γ: '溫暖柔和' }[selectedHueBias.value];
    toneFusionNote.value = `您選擇的膚色：${tone.toneName}（${baseLabel} × ${selectedDepth.value}級 × ${biasLabel}）`;

    closeManualSelector();
    await analyzeSkinTone();
};

// ── Image analysis entry point ───────────────────────────────────
const analyzeSkinFromImage = async () => {
    const cheekSample = sampleCheekSkinTone();
    if (!cheekSample) {
        alert('未檢測到足夠的膚色像素，請調整角度或光線');
        return;
    }

    const preferredFamily = getToneGuessFamily();
    const resolvedTone    = selectToneByFamilyPreference(cheekSample, preferredFamily) || {
        toneName: '未知膚色',
        hex:  rgbToHex(cheekSample.r, cheekSample.g, cheekSample.b),
        rgb:  cheekSample
    };

    skinCoordinate.value = {
        type:     resolvedTone.toneName,
        rgb:      `${resolvedTone.rgb.r}, ${resolvedTone.rgb.g}, ${resolvedTone.rgb.b}`,
        hex:      resolvedTone.hex,
        rawRgb:   cheekSample,
        toneGuess: toneGuess.value || ''
    };
    skinTone.value = skinCoordinate.value.type;

    toneFusionNote.value = preferredFamily
        ? `已將臉頰最亮膚色與前面膚色問卷（${toneGuess.value || '未判定'}）融合後作為初始膚色。`
        : '已將臉頰最亮膚色作為初始膚色。';

    currentStep.value = 4;
    await analyzeWithGroq();
};

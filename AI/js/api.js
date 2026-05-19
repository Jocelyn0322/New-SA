// api.js — All fetch/API calls: skin tone data, product recommendations, feedback, ingredient advice, Groq AI analysis.
// Depends on: state.js, skin-engine.js (getCalibratedSkinType), skin-analysis.js (selectToneByFamilyPreference, hexToRgb, updateToneMismatchWarning, getToneGuessFamily)

// ── Data loading ─────────────────────────────────────────────────
const initSkinToneData = () => {
    fetch('./getSkinTones.php')
        .then(async (response) => {
            if (!response.ok) {
                const errorBody = await response.json().catch(() => ({}));
                throw new Error(errorBody.message || '無法從資料庫取得膚色資料');
            }
            return response.json();
        })
        .then((data) => {
            skinTonesData.value    = data;
            window.skinTonesData   = data;
            dataLoaded.value       = true;
        })
        .catch((error) => {
            console.error('Failed to load skin tone data:', error);
            alert('載入膚色資料失敗，請檢查資料庫連線或後端設定');
        });
};

// ── Product helpers ──────────────────────────────────────────────
const mapProduct = (item, index) => {
    const brand       = item.brand       || item.Brand       || '通用';
    const productName = item.productName || item.ProductName || item.name || '推薦產品';
    const category    = item.category    || item.Category    || '';
    const purpose     = item.purpose     || item.Purpose     || '';
    const id          = item.p_id || item.ProductID || item.productId || item.id || index + 1;

    return {
        id,
        brand,
        productName,
        name: `${brand} ${productName}`.trim(),
        category,
        purpose,
        recommendedShade: item.recommendedShade || item.shadeName || item.shade || '',
        description: (item.recommendationReason || item.reason)
            ? `${item.recommendationReason || item.reason}`
            : `分類: ${category || '-'}｜用途: ${purpose || '-'}`,
        matchScore: item.matchScore ?? item.match_score ?? null
    };
};

const feedbackTypeText = (type) => {
    const mapping = {
        just_right: '色號剛好',
        too_yellow: '偏黃',
        too_dark:   '偏暗',
        too_dry:    '太乾',
        too_oily:   '太油'
    };
    return mapping[type] || type || '未標記';
};

const extractDetectedLab = () => {
    const raw = skinCoordinate.value?.rawRgb;
    if (!raw) return null;
    const L = (0.2126 * raw.r + 0.7152 * raw.g + 0.0722 * raw.b) / 2.55;
    return {
        L: Number(L.toFixed(2)),
        a: Number(((raw.r - raw.g) / 2).toFixed(2)),
        b: Number(((raw.g - raw.b) / 2).toFixed(2))
    };
};

// ── Click & feedback tracking ────────────────────────────────────
const recordProductClick = async (productId) => {
    if (!productId) return;
    try {
        await fetch('./recordProductClick.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ productId: String(productId), source: 'ai_recommendation' })
        });
    } catch (error) {
        console.warn('record click failed:', error);
    }
};

const loadFeedbackHistory = async () => {
    try {
        const response = await fetch('./getProductFeedbackHistory.php');
        const result   = await response.json().catch(() => ({}));
        if (!response.ok || result.error) return;
        feedbackHistory.value = Array.isArray(result.history) ? result.history : [];
    } catch (error) {
        console.warn('loadFeedbackHistory failed:', error);
    }
};

const submitProductFeedback = async (product, feedbackType) => {
    try {
        const productId = product?.id ?? product?.p_id ?? product?.ProductID ?? product?.productId;
        if (!productId) { alert('找不到產品編號，無法送出回饋'); return; }

        await recordProductClick(productId);

        const response = await fetch('./recordProductFeedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ productId: String(productId), feedbackType, detectedLab: extractDetectedLab() })
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.error) throw new Error(result.message || '回饋提交失敗');

        const weights = result.weights || {};
        alert(`${result.message || '回饋已儲存'}\n目前 LAB 權重：L=${weights.L ?? '-'} / a=${weights.a ?? '-'} / b=${weights.b ?? '-'}`);
        await loadFeedbackHistory();
    } catch (error) {
        console.error('submitProductFeedback failed:', error);
        alert(`回饋提交失敗：${error.message}`);
    }
};

// ── Ingredient advice ────────────────────────────────────────────
const fetchIngredientAdvice = async (skinType) => {
    if (!skinType) { ingredientAdvice.value = null; return; }

    ingredientLoading.value = true;
    try {
        const response = await fetch('./getIngredientAdvice.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ skinType })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.error) throw new Error(result.message || '成分分析失敗');
        ingredientAdvice.value = result;
    } catch (error) {
        console.error('Ingredient advice failed:', error);
        ingredientAdvice.value = {
            skin_type: skinType,
            avoid_ingredients: [{ ingredient: 'AI 分析暫時無法使用', reason: error.message }],
            suitable_focus: [],
            disclaimer: '目前使用備援訊息，請稍後重試。'
        };
    } finally {
        ingredientLoading.value = false;
    }
};

// ── Groq AI analysis ─────────────────────────────────────────────
const analyzeWithGroq = async () => {
    isAnalyzing.value = true;
    try {
        const toneGuessFamily      = getToneGuessFamily();
        const cameraToneBeforeGroq = skinCoordinate.value ? { ...skinCoordinate.value } : null;
        const imageBase64          = canvas.value.toDataURL('image/jpeg', 0.9);

        const response = await fetch('./analyzeSkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                imageBase64,
                toneGuess:         toneGuess.value || '',
                toneQuizAnswers:   toneQuizAnswers.value,
                userPreference:    makeupFinish.value || '霧面',
                makeupPreference:  { finish: makeupFinish.value || '霧面', style: makeupStyle.value || '日常通勤' }
            })
        });

        const result = await response.json().catch(() => ({}));
        if (!response.ok || result.error) throw new Error(result.message || 'Groq 分析失敗');

        const analysis         = result.analysis || {};
        const nearestSkinTone  = result.nearestSkinTone || {};
        const makeupPreference = result.makeupPreference || {
            finish: makeupFinish.value || '霧面',
            style:  makeupStyle.value  || '日常通勤'
        };

        skinTypeResult.value    = analysis.skin_type          || '';
        skinTypeSecondary.value = analysis.secondary_skin_type || '';
        skinFeatures.value      = analysis.features            || null;
        confidenceScore.value   = typeof analysis.confidence_score === 'number'
            ? analysis.confidence_score.toFixed(2)
            : null;

        const cameraBaseRgb    = cameraToneBeforeGroq?.rawRgb || skinCoordinate.value?.rawRgb || null;
        const matchedCameraTone = cameraBaseRgb
            ? selectToneByFamilyPreference(cameraBaseRgb, toneGuessFamily)
            : null;

        if (nearestSkinTone.toneName) {
            const matchedTone = skinTonesData.value.find(t => t.toneName === nearestSkinTone.toneName);
            const toneRgb     = matchedTone?.rgb || hexToRgb(nearestSkinTone.hex || matchedTone?.hex || '');
            const fusedTone   = matchedCameraTone || (toneRgb ? {
                toneName: nearestSkinTone.toneName,
                hex:      nearestSkinTone.hex || matchedTone?.hex || (cameraToneBeforeGroq?.hex || '-'),
                rgb:      toneRgb
            } : nearestSkinTone);

            skinCoordinate.value = {
                type:     fusedTone.toneName || nearestSkinTone.toneName,
                rgb:      fusedTone.rgb ? `${fusedTone.rgb.r}, ${fusedTone.rgb.g}, ${fusedTone.rgb.b}` : (cameraToneBeforeGroq?.rgb || '-'),
                hex:      fusedTone.hex || nearestSkinTone.hex || matchedTone?.hex || (cameraToneBeforeGroq?.hex || '-'),
                rawRgb:   cameraToneBeforeGroq?.rawRgb || skinCoordinate.value?.rawRgb || null,
                toneGuess: toneGuess.value || ''
            };
            updateToneMismatchWarning(skinCoordinate.value.type, '相機膚色分析');
            skinTone.value       = skinCoordinate.value.type;
            toneFusionNote.value = toneGuessFamily
                ? `最終膚色已綜合：相機臉頰採樣 × ${toneGuess.value || '未判定'}，優先保留同調性。`
                : '最終膚色已綜合相機臉頰採樣結果。';
        } else if (matchedCameraTone) {
            skinCoordinate.value = {
                type:     matchedCameraTone.toneName,
                rgb:      `${matchedCameraTone.rgb.r}, ${matchedCameraTone.rgb.g}, ${matchedCameraTone.rgb.b}`,
                hex:      matchedCameraTone.hex || (cameraToneBeforeGroq?.hex || '-'),
                rawRgb:   cameraToneBeforeGroq?.rawRgb || skinCoordinate.value?.rawRgb || null,
                toneGuess: toneGuess.value || ''
            };
            updateToneMismatchWarning(skinCoordinate.value.type, '相機膚色分析');
            skinTone.value       = skinCoordinate.value.type;
            toneFusionNote.value = `最終膚色已以臉頰採樣結合膚色問卷（${toneGuess.value || '未判定'}）校正。`;
        }

        if (Array.isArray(result.products) && result.products.length > 0) {
            recommendations.value = result.products.map(mapProduct);
        } else if (nearestSkinTone.toneName) {
            await recommendProducts(nearestSkinTone.toneName, manualSensitiveSkin.value || skinTypeSecondary.value === '敏感肌');
        } else {
            recommendations.value = [];
        }

        const baseSkinType = manualSkinType.value || analysis.skin_type || '';
        const fused        = getCalibratedSkinType(baseSkinType, Number(analysis.confidence_score ?? 0.5));
        skinTypeResult.value        = fused.finalType;
        skinTypeSecondary.value     = fused.secondaryType || skinTypeSecondary.value || '';
        manualSkinType.value        = fused.finalType;
        needsRetest.value           = fused.needsRetest;
        consistencyScoreValue.value = fused.consistencyScore.toFixed(2);
        fusionNote.value            = `綜合判定：AI(${baseSkinType || '未判定'}) × ${Math.round(fused.aiWeight * 100)}% + 問卷 × ${Math.round(fused.quizWeight * 100)}%` + (fused.secondaryType ? `，${fused.secondaryType} 已獨立標註` : '');
        makeupPreferenceNote.value  = `妝感偏好：${makeupPreference.finish || '霧面'}｜妝容風格：${makeupPreference.style || '日常通勤'}`;
        retestMessage.value         = fused.needsRetest
            ? '哎呀！偵測到您的描述有些矛盾，為了提供最精準的底妝建議，要不要重新確認一下膚況或重新拍攝照片呢？'
            : (fused.isOutlier ? '問卷結果與 AI 視覺特徵跨類別衝突，已標記為異常資料（Outlier）。' : '');
        makeupFinish.value = makeupPreference.finish || makeupFinish.value;
        makeupStyle.value  = makeupPreference.style  || makeupStyle.value;

        if (fused.blockRecommendation) {
            recommendations.value  = [];
            ingredientAdvice.value = null;
        } else {
            await fetchIngredientAdvice(fused.secondaryType || fused.finalType);
        }

        await loadFeedbackHistory();
        currentStep.value = 4;
    } catch (error) {
        console.error('Groq analysis failed:', error);
        alert(`AI 膚質分析失敗：${error.message}`);
    } finally {
        isAnalyzing.value = false;
    }
};

// ── Product recommendations ──────────────────────────────────────
const recommendProducts = async (skinType, sensitive = false) => {
    try {
        const response = await fetch(`./getRecommendedProducts.php?skinType=${encodeURIComponent(skinType || '')}&sensitive=${sensitive ? '1' : '0'}&limit=6`);
        const data     = await response.json();
        if (!response.ok || data.status !== 'success') throw new Error(data.message || '無法取得推薦產品');
        recommendations.value = Array.isArray(data.products) ? data.products : [];
    } catch (error) {
        console.error('Failed to load recommended products:', error);
        recommendations.value = [];
    }
};

// ── Save result & redirect ───────────────────────────────────────
const showLoginPromptModal = () => {
    const overlay = document.createElement('div');
    overlay.className = 'morandi-modal-overlay';

    const box = document.createElement('div');
    box.className = 'morandi-modal-box';

    const msg = document.createElement('p');
    msg.className = 'morandi-modal-msg';
    msg.textContent = '您尚未登入，分析結果無法儲存。現在要登入嗎？';

    const btnRow = document.createElement('div');
    btnRow.style.cssText = 'display:flex;gap:10px;justify-content:center;margin-top:16px;';

    const btnLogin = document.createElement('button');
    btnLogin.className = 'morandi-modal-close';
    btnLogin.textContent = '前往登入';
    btnLogin.style.cssText = 'background:#b5a8c0;color:#fff;';
    btnLogin.addEventListener('click', () => { window.location.href = '../首頁/login.php'; });

    const btnHome = document.createElement('button');
    btnHome.className = 'morandi-modal-close';
    btnHome.textContent = '返回首頁';
    btnHome.addEventListener('click', () => { window.location.href = '../產品/index.php'; });

    btnRow.appendChild(btnLogin);
    btnRow.appendChild(btnHome);
    box.appendChild(msg);
    box.appendChild(btnRow);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
};

const finishAndSave = async () => {
    const skinType = manualSkinType.value
        || (typeof skinTypeResult.value === 'object' ? skinTypeResult.value?.profile?.displayName : skinTypeResult.value)
        || '';
    const skinTone = skinCoordinate.value?.type || '';
    const concerns = [];
    if (manualSensitiveSkin.value || skinTypeSecondary.value === '敏感肌') concerns.push('敏感肌');

    try {
        const resp = await fetch('./saveAnalysisResult.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                skinType,
                skinTone,
                skinConcerns:  concerns.join(', '),
                makeupFinish:  makeupFinish.value  || '',
                makeupStyle:   makeupStyle.value   || '',
            })
        });
        const result = await resp.json().catch(() => ({}));

        if (!result.loggedIn) {
            showLoginPromptModal();
        } else {
            window.location.href = '../產品/skinmatch.php';
        }
    } catch (_) {
        window.location.href = '../產品/skinmatch.php';
    }
};

// ── Manual analysis (no camera) ──────────────────────────────────
const analyzeManual = async () => {
    if (!skinTone.value)       { alert('請選擇膚色類型'); return; }
    if (!manualSkinType.value) { alert('請選擇膚質（手動輸入）'); return; }
    await analyzeSkinTone();
};

const analyzeSkinTone = async () => {
    const selectedTone = skinTonesData.value.find(t => t.toneName === skinTone.value);
    if (selectedTone) {
        const toneRgb = selectedTone.rgb || hexToRgb(selectedTone.hex) || { r: 0, g: 0, b: 0 };
        skinCoordinate.value = {
            type:      selectedTone.toneName,
            rgb:       `${toneRgb.r}, ${toneRgb.g}, ${toneRgb.b}`,
            hex:       selectedTone.hex,
            rawRgb:    toneRgb,
            toneGuess: toneGuess.value || ''
        };
        updateToneMismatchWarning(selectedTone.toneName, '手動膚色分析');
        skinTone.value = skinCoordinate.value.type;
    } else {
        skinCoordinate.value = { type: '分析中...', rgb: '...', hex: '...' };
    }

    const fused = getCalibratedSkinType(manualSkinType.value);
    skinTypeResult.value        = fused.finalType;
    skinTypeSecondary.value     = manualSensitiveSkin.value ? '敏感肌' : (fused.secondaryType || '');
    manualSkinType.value        = fused.finalType;
    needsRetest.value           = fused.needsRetest;
    consistencyScoreValue.value = fused.consistencyScore.toFixed(2);
    fusionNote.value            = `綜合判定：手動膚質(${manualSkinType.value}) × ${Math.round(fused.aiWeight * 100)}% + 問卷 × ${Math.round(fused.quizWeight * 100)}%` + (skinTypeSecondary.value ? `，${skinTypeSecondary.value} 已獨立標註` : '');
    toneFusionNote.value        = toneGuess.value
        ? `手動膚色結果已參考前面的膚色問卷（${toneGuess.value}）。`
        : '手動膚色結果已完成。';
    makeupPreferenceNote.value  = `妝感偏好：${makeupFinish.value}｜妝容風格：${makeupStyle.value}`;
    skinFeatures.value          = null;
    confidenceScore.value       = null;
    retestMessage.value         = fused.needsRetest
        ? '哎呀！偵測到問卷與手動設定有些矛盾，為了提供更精準的底妝建議，建議你再確認一次膚況與問卷答案。'
        : (fused.isOutlier ? '問卷結果與手動膚質設定衝突，建議再確認一次。' : '');

    if (fused.blockRecommendation) {
        recommendations.value  = [];
        ingredientAdvice.value = null;
    } else {
        await recommendProducts(skinTone.value, manualSensitiveSkin.value || skinTypeSecondary.value === '敏感肌');
        await fetchIngredientAdvice(fused.secondaryType || fused.finalType);
    }

    currentStep.value = 4;
};

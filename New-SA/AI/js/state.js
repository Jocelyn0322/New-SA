// state.js — Vue reactive state, computed, and alert modal
// Must be the FIRST script loaded (all other modules depend on these refs).

const { createApp, ref, nextTick, onUnmounted, computed, watch } = Vue;

const dataLoaded            = ref(false);
const currentStep           = ref(1);
const skinTonesData         = ref([]);
const cameraActive          = ref(false);
const skinTone              = ref('');
const manualSkinType        = ref('');
const manualSensitiveSkin   = ref(false);
const makeupFinish          = ref('霧面');
const makeupStyle           = ref('日常通勤');
const confirmedSkinTone     = ref(false);
const confirmedSkinType     = ref(false);
const showMakeupPreference  = ref(false);
const skinTypeOptions       = ['混油皮', '乾性皮', '油性皮', '中性皮', '混乾皮', '敏感肌'];
const toneQuizAnswers       = ref({ t1: '', t2: '', t3: '' });
const toneGuess             = ref('');
const toneFusionNote        = ref('');
const quizAnswers           = ref({ q1: '', q3: '', q4: '', q5: '' });

// ── Chat quiz step state ─────────────────────────────────────────
const toneQuizStep    = ref(1);   // 1=Q1visible, 2=Q2, 3=Q3, 4=done
const skinQuizStep    = ref(1);   // 1=Q1, 2=Q2, 3=Q3, 4=Q4, 5=done
const toneQuizTyping  = ref(false);
const skinQuizTyping  = ref(false);

const toneQuizData = [
    { key: 't1', q: '手腕內側的血管，看起來偏什麼顏色？', opts: [
        { val: 'A', label: '藍色或紫色' },
        { val: 'B', label: '綠色' },
        { val: 'C', label: '兩種都有，或看不太出來' }
    ]},
    { key: 't2', q: '曬太陽後，你的皮膚通常會？', opts: [
        { val: 'A', label: '先紅後黑，容易曬傷' },
        { val: 'B', label: '直接變黑，不太會紅' },
        { val: 'C', label: '看情況，兩者都會' }
    ]},
    { key: 't3', q: '戴哪種金屬飾品，比較顯氣色？', opts: [
        { val: 'A', label: '銀色、白金' },
        { val: 'B', label: '金色、玫瑰金' },
        { val: 'C', label: '兩種都好看' }
    ]},
];

const skinQuizData = [
    { key: 'q1', q: '洗臉後 30 分鐘，不擦任何保養品，臉的感覺？', opts: [
        { val: 'A', label: '全臉緊繃，甚至脫皮或有細紋感' },
        { val: 'B', label: 'T 區微出油，但兩頰還是有點緊' },
        { val: 'C', label: 'T 區出油明顯，兩頰感覺普通' },
        { val: 'D', label: '全臉都有明顯油光，容易悶痘' },
        { val: 'E', label: '不緊繃也不油，皮膚很舒適' }
    ]},
    { key: 'q3', q: '下午 3–4 點，你的臉通常是？', opts: [
        { val: 'A', label: '臉很乾、緊繃，偶有脫皮' },
        { val: 'B', label: '鼻翼或T區輕微出油，臉頰偏乾' },
        { val: 'C', label: 'T 區出油明顯，但臉頰還好' },
        { val: 'D', label: '全臉都出油，臉摸起來很油膩' },
        { val: 'E', label: '整體舒適，沒有特別乾或油' }
    ]},
    { key: 'q4', q: '皮膚對外界刺激的耐受度？', hint: '敏感肌可以和其他膚質重疊，這個問題單獨評估耐受度', opts: [
        { val: 'A', label: '常常泛紅、刺痛，新保養品很容易過敏' },
        { val: 'B', label: '換季或壓力大時容易長疹子或泛紅' },
        { val: 'C', label: '偶爾對特定成分有反應，但大多沒問題' },
        { val: 'D', label: '很少不適，幾乎什麼都能用' }
    ]},
    { key: 'q5', q: '早上起床，臉的狀況通常是？', opts: [
        { val: 'A', label: '非常乾，感覺緊繃甚至有脫皮' },
        { val: 'B', label: '有點乾，需要趕快塗保濕' },
        { val: 'C', label: 'T 區出油，兩頰還好' },
        { val: 'D', label: '全臉都油，枕頭都有油印' },
        { val: 'E', label: '皮膚感覺正常舒適' }
    ]},
];
const skinCoordinate        = ref(null);
const skinTypeResult        = ref('');
const skinTypeSecondary     = ref('');
const skinFeatures          = ref(null);
const confidenceScore       = ref(null);
const consistencyScoreValue = ref(null);
const fusionNote            = ref('');
const makeupPreferenceNote  = ref('');
const needsRetest           = ref(false);
const retestMessage         = ref('');
const recommendations       = ref([]);
const ingredientAdvice      = ref(null);
const ingredientLoading     = ref(false);
const quizDerivedSkinType   = ref('');
const aiDetectedSkinType    = ref('');
const analysisHistory       = ref([]);
const isAnalyzing           = ref(false);
const faceDetectionBusy     = ref(false);
const cameraWarning         = ref('');
const showResultModal       = ref(false);
const hasShownNaturalLightReminder = ref(false);
const alertVisible          = ref(false);
const alertMessage          = ref('');
const video                 = ref(null);
const canvas                = ref(null);
const showManualSelector    = ref(false);
const manualSelectorStep    = ref(0);
const selectedBase          = ref('');
const selectedDepth         = ref('');
const selectedHueBias       = ref('');
const matchedFinalTone      = ref(null);
const toneMismatchWarning   = ref('');
const showSwatchCard        = ref(false);

const canChooseMakeupPreference = computed(() => confirmedSkinTone.value && confirmedSkinType.value);

const _toneCategories = {
    'Pink': '粉調', 'Yellow': '黃調', 'Neutral': '中性調', 'Olive': '橄欖調',
    'Red-Cool': '偏紅冷調', 'Red-Warm': '偏紅暖調',
    'Neutral-Cool': '中性冷調', 'Neutral-Warm': '中性暖調',
    'Green-Cool': '偏綠冷調', 'Green-Warm': '偏綠暖調',
};
const skinTonesByCategory = computed(() => {
    const groups = {};
    skinTonesData.value.forEach(t => {
        const cat = t.category || 'Other';
        if (!groups[cat]) groups[cat] = { label: _toneCategories[cat] || cat, tones: [] };
        groups[cat].tones.push(t);
    });
    return Object.values(groups);
});

// ── Result modal ────────────────────────────────────────────────
const openResultModal  = () => { showResultModal.value = true; };
const closeResultModal = () => { showResultModal.value = false; };

// ── Custom alert modal (replaces window.alert) ──────────────────
const showAlert = (msg) => {
    alertMessage.value = String(msg);
    alertVisible.value = true;

    const prev = document.getElementById('morandi-alert-modal');
    if (prev) prev.remove();

    const overlay = document.createElement('div');
    overlay.id = 'morandi-alert-modal';
    overlay.className = 'morandi-modal-overlay';

    const box = document.createElement('div');
    box.className = 'morandi-modal-box';

    const p = document.createElement('p');
    p.className = 'morandi-modal-msg';
    p.style.whiteSpace = 'pre-line';
    p.textContent = String(msg);

    const btn = document.createElement('button');
    btn.className = 'morandi-modal-close';
    btn.textContent = '關閉';

    const close = () => { overlay.remove(); alertVisible.value = false; };
    btn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

    box.appendChild(p);
    box.appendChild(btn);
    overlay.appendChild(box);
    document.body.appendChild(overlay);
};

window.alert = showAlert;

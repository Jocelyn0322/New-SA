// state.js — Vue reactive state, computed, and alert modal
// Must be the FIRST script loaded (all other modules depend on these refs).

const { createApp, ref, nextTick, onUnmounted, computed } = Vue;

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
const quizAnswers           = ref({ q1: '', q2: '', q3: '', q4: '' });

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
        { val: 'A', label: '全臉緊繃，甚至脫皮' },
        { val: 'B', label: 'T 區微出油，兩頰緊繃' },
        { val: 'C', label: 'T 區出油明顯，兩頰還好' },
        { val: 'D', label: '全臉都有明顯油光' },
        { val: 'E', label: '不緊繃也不油，很舒適' }
    ]},
    { key: 'q2', q: '觀察日常毛孔和膚質狀態？', opts: [
        { val: 'A', label: '毛孔細緻，但容易有乾紋' },
        { val: 'B', label: 'T 區毛孔大，兩頰細緻' },
        { val: 'C', label: '全臉毛孔粗大，常有黑頭' },
        { val: 'D', label: '皮膚平滑均勻' }
    ]},
    { key: 'q3', q: '下午 3–4 點，上了妝的臉通常是？', opts: [
        { val: 'A', label: '嚴重浮粉、起皮，妝吸不住' },
        { val: 'B', label: '鼻翼脫妝掉粉，兩頰很乾' },
        { val: 'C', label: 'T 區油光滿面，妝色暗沉' },
        { val: 'D', label: '妝感完整，只有微出油' }
    ]},
    { key: 'q4', q: '皮膚對外界刺激的耐受度？', hint: '敏感肌可以和其他膚質重疊，這個問題單獨評估你的耐受度', opts: [
        { val: 'A', label: '換季、新品容易泛紅刺痛' },
        { val: 'B', label: '偶爾起疹，但很快恢復' },
        { val: 'C', label: '很少不適，皮膚像城牆' }
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
const feedbackHistory       = ref([]);
const quizDerivedSkinType   = ref('');
const aiDetectedSkinType    = ref('');
const analysisHistory       = ref([]);
const isAnalyzing           = ref(false);
const faceDetectionBusy     = ref(false);
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

const canChooseMakeupPreference = computed(() => confirmedSkinTone.value && confirmedSkinType.value);

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

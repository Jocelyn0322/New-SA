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

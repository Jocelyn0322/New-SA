// app.js — Vue app entry point. Must be loaded last.
// All refs/functions from state.js, quiz.js, skin-engine.js, camera-liveness.js, skin-analysis.js, api.js
// must already be in global scope when this script runs.

const app = createApp({
    setup() {
        onUnmounted(() => stopCamera());

        initSkinToneData();
        loadFeedbackHistory();
        loadAnalysisHistory();

        const scrollToLatest = () => nextTick(() => {
            const chipZone = document.querySelector('.chip-zone');
            if (chipZone) { chipZone.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); return; }
            const chatBody = document.querySelector('.chat-body');
            if (chatBody) chatBody.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'end' });
        });
        watch(toneQuizTyping, v => { if (!v) scrollToLatest(); });
        watch(skinQuizTyping,  v => { if (!v) scrollToLatest(); });
        watch(toneQuizStep,  () => scrollToLatest());
        watch(skinQuizStep,   () => scrollToLatest());

        return {
            // ── State refs ──────────────────────────────────────
            alertVisible,
            alertMessage,
            dataLoaded,
            currentStep,
            skinTonesData,
            cameraActive,
            skinTone,
            manualSkinType,
            manualSensitiveSkin,
            makeupFinish,
            makeupStyle,
            skinTypeOptions,
            confirmedSkinTone,
            confirmedSkinType,
            canChooseMakeupPreference,
            showMakeupPreference,
            toneQuizAnswers,
            toneGuess,
            toneFusionNote,
            toneQuizStep,
            skinQuizStep,
            toneQuizTyping,
            skinQuizTyping,
            toneQuizData,
            skinQuizData,
            quizAnswers,
            skinCoordinate,
            skinTypeResult,
            skinTypeSecondary,
            skinFeatures,
            confidenceScore,
            consistencyScoreValue,
            fusionNote,
            makeupPreferenceNote,
            needsRetest,
            retestMessage,
            recommendations,
            ingredientAdvice,
            ingredientLoading,
            feedbackHistory,
            quizDerivedSkinType,
            aiDetectedSkinType,
            analysisHistory,
            consistencyRate,
            quizAiMatch,
            isAnalyzing,
            faceDetectionBusy,
            showResultModal,
            toneMismatchWarning,
            showManualSelector,
            manualSelectorStep,
            selectedBase,
            selectedDepth,
            selectedHueBias,
            matchedFinalTone,

            // ── Modal ───────────────────────────────────────────
            closeResultModal,

            // ── Quiz (quiz.js) ──────────────────────────────────
            setToneQuizAnswer,
            toneQuizOptionClass,
            goToSkinTypeStep,
            goToCameraStep,
            confirmSkinTone,
            confirmSkinType,
            backToToneAndSkinPage,
            restartFromBeginning,
            backToResultsPage,
            goToConfirmStep,
            goToMakeupStep,
            setQuizAnswer,
            quizOptionClass,
            makeupOptionClass,
            setMakeupPreference,

            // ── Camera (camera-liveness.js) ─────────────────────
            startCamera,
            stopCamera,
            captureImage,

            // ── Manual selector (skin-analysis.js) ─────────────
            startManualSelector,
            closeManualSelector,
            selectBase,
            selectDepth,
            selectHueBias,
            findMatchingTone,
            confirmManualToneSelection,

            // ── API (api.js) ────────────────────────────────────
            feedbackTypeText,
            submitProductFeedback,
            analyzeManual,
            analyzeSkinTone,
            analyzeWithGroq,
            finishAndSave,
            skinFamilyMatch,
            formatDate,
        };
    }
});

app.mount('#app');

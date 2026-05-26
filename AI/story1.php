<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../產品/style.css?v=2">
    <link rel="stylesheet" href="story1-morandi.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <title>AI Skin Tone Detector</title>
</head>
<body>
    <?php include __DIR__ . '/../header.php'; ?>

    <div id="app">
        <div v-if="!dataLoaded" class="text-center py-20 text-gray-500">載入中...</div>

        <div v-if="dataLoaded" class="space-y-5">

            <!-- Progress indicator -->
            <div class="p-5 rounded-2xl border border-violet-100 bg-violet-50/70">
                <div class="flex items-center justify-between text-sm font-semibold text-violet-800">
                    <span>步驟 {{ currentStep <= 2 ? 1 : currentStep - 1 }} / 4</span>
                    <span v-if="currentStep <= 2">問卷</span>
                    <span v-else-if="currentStep === 3">相機拍照</span>
                    <span v-else-if="currentStep === 4">分析結果</span>
                    <span v-else>確認結果</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-violet-100 overflow-hidden">
                    <div class="h-full bg-violet-500 transition-all duration-300"
                         :style="{ width: currentStep <= 2 ? '25%' : currentStep === 3 ? '50%' : currentStep === 4 ? '75%' : '100%' }"></div>
                </div>
            </div>

            <?php include 'partials/step1-tone-quiz.php'; ?>
            <?php include 'partials/step2-skin-quiz.php'; ?>
            <?php include 'partials/step3-camera.php'; ?>
            <?php include 'partials/step4-results.php'; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Load order matters: state must be first, app must be last -->
    <?php $v = filemtime(__DIR__ . '/js/state.js'); ?>
    <script src="js/state.js?v=<?= filemtime(__DIR__.'/js/state.js') ?>"></script>
    <script src="js/quiz.js?v=<?= filemtime(__DIR__.'/js/quiz.js') ?>"></script>
    <script src="js/skin-engine.js?v=<?= filemtime(__DIR__.'/js/skin-engine.js') ?>"></script>
    <script src="js/camera-liveness.js?v=<?= filemtime(__DIR__.'/js/camera-liveness.js') ?>"></script>
    <script src="js/skin-analysis.js?v=<?= filemtime(__DIR__.'/js/skin-analysis.js') ?>"></script>
    <script src="js/api.js?v=<?= filemtime(__DIR__.'/js/api.js') ?>"></script>
    <script src="js/app.js?v=<?= filemtime(__DIR__.'/js/app.js') ?>"></script>
</body>
</html>

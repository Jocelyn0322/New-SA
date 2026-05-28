<div v-if="currentStep === 3" class="p-5 md:p-6 border border-violet-100 rounded-2xl bg-gradient-to-br from-violet-50 to-fuchsia-50 shadow-sm" style="position:relative;">

    <!-- 分析中 overlay -->
    <div v-if="isAnalyzing" style="position:absolute;inset:0;z-index:20;border-radius:1rem;background:rgba(253,242,244,0.88);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;">
        <svg style="width:44px;height:44px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="#f5c6d0" stroke-width="3"/>
            <path d="M12 2a10 10 0 0 1 10 10" stroke="#6b2d3e" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <p style="font-size:15px;font-weight:700;color:#6b2d3e;">正在分析中，請稍候…</p>
    </div>
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-extrabold text-gray-900 text-lg">📸 第 3 頁：相機拍照</h3>
        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-violet-700 border border-violet-200">Camera</span>
    </div>
    <p class="text-sm text-gray-600 mb-3">請拍攝臉部照片，系統會先分析結果，再讓你進行確認。</p>
    <button @click="startCamera" class="w-full bg-gray-900 text-white py-3 rounded-xl font-bold hover:bg-gray-800 active:scale-[0.99] transition duration-200 shadow-lg shadow-gray-900/20">
        啟動相機
    </button>
    <video ref="video" v-show="cameraActive" autoplay playsinline class="mt-4 w-full rounded-xl border border-violet-100 shadow-md" style="transform: scaleX(-1);"></video>
    <div v-if="cameraActive" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
        <button @click="captureImage" :disabled="faceDetectionBusy" :class="faceDetectionBusy ? 'bg-gray-400 text-white py-2.5 rounded-xl font-bold cursor-not-allowed' : 'bg-indigo-600 text-white py-2.5 rounded-xl font-bold hover:bg-indigo-500 transition duration-200'">
            <span v-if="!faceDetectionBusy">拍攝並分析</span>
            <span v-else>偵測中… 請稍候</span>
        </button>
        <button @click="stopCamera" class="bg-white text-rose-600 py-2.5 rounded-xl font-bold border border-rose-200 hover:bg-rose-50 transition duration-200">
            停止相機
        </button>
    </div>
    <canvas ref="canvas" class="hidden"></canvas>

    <!-- 不想拍照 / 手動選色入口 -->
    <div v-if="!showManualSelector" class="mt-4 p-4 rounded-xl bg-white/90 border border-gray-200">
        <p class="text-sm text-gray-700 mb-3">不想拍照？</p>
        <button @click="startManualSelector" class="w-full bg-emerald-600 text-white py-2.5 rounded-xl font-bold hover:bg-emerald-500 transition mb-3">
            自行選擇膚色與膚質
        </button>
        <button @click="currentStep = 2" class="w-full bg-white border border-gray-300 text-gray-700 py-2.5 rounded-xl font-bold hover:bg-gray-50 transition">
            返回：膚質問答
        </button>
    </div>

    <!-- Step 1: Base Tone Selection -->
    <div v-if="showManualSelector && manualSelectorStep === 1" class="mt-4 p-5 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200">
        <p class="text-sm font-bold text-gray-800 mb-3">第 1 步：判定「色調基底」(The Base)</p>
        <p class="text-xs text-gray-600 mb-4">觀察妳的肌膚在自然光下，最接近哪種狀態？</p>
        <div class="space-y-2">
            <button @click="selectBase('A')" :class="selectedBase === 'A' ? 'border-emerald-400 bg-emerald-100 text-emerald-900' : 'border-emerald-200 bg-white text-gray-700 hover:bg-emerald-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 A（暖/黃）</p>
                <p class="text-xs text-gray-600 mt-1">膚色看起來帶金黃感，穿橘色或大地色很有精神。</p>
            </button>
            <button @click="selectBase('B')" :class="selectedBase === 'B' ? 'border-emerald-400 bg-emerald-100 text-emerald-900' : 'border-emerald-200 bg-white text-gray-700 hover:bg-emerald-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 B（冷/粉）</p>
                <p class="text-xs text-gray-600 mt-1">膚色看起來帶粉紅或青紫色，穿純白色、藍色很顯白。</p>
            </button>
            <button @click="selectBase('C')" :class="selectedBase === 'C' ? 'border-emerald-400 bg-emerald-100 text-emerald-900' : 'border-emerald-200 bg-white text-gray-700 hover:bg-emerald-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 C（中性）</p>
                <p class="text-xs text-gray-600 mt-1">膚色很平均，沒有明顯的黃感或粉感。</p>
            </button>
            <button @click="selectBase('D')" :class="selectedBase === 'D' ? 'border-emerald-400 bg-emerald-100 text-emerald-900' : 'border-emerald-200 bg-white text-gray-700 hover:bg-emerald-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 D（橄欖）</p>
                <p class="text-xs text-gray-600 mt-1">膚色帶有微微的青綠色或「灰色感」，素顏時容易顯得「菜色」。</p>
            </button>
        </div>
        <div class="mt-4 grid gap-2">
            <button @click="closeManualSelector" class="bg-white border border-emerald-200 text-emerald-700 py-2.5 rounded-xl font-bold hover:bg-emerald-50">取消</button>
        </div>
    </div>

    <!-- Step 2: Depth Level Selection -->
    <div v-if="showManualSelector && manualSelectorStep === 2" class="mt-4 p-5 rounded-xl bg-gradient-to-br from-teal-50 to-cyan-50 border border-teal-200">
        <p class="text-sm font-bold text-gray-800 mb-3">第 2 步：判定「明亮深度」(The Depth)</p>
        <p class="text-xs text-gray-600 mb-4">在人群中，妳對自己膚色深淺的直覺是？</p>
        <div class="space-y-2">
            <button @click="selectDepth('1')" :class="selectedDepth === '1' ? 'border-teal-400 bg-teal-100 text-teal-900' : 'border-teal-200 bg-white text-gray-700 hover:bg-teal-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">1 級（一白）</p>
                <p class="text-xs text-gray-600 mt-1">非常白，通常是粉底液最淺號，曬太陽容易紅腫脫皮。</p>
            </button>
            <button @click="selectDepth('2')" :class="selectedDepth === '2' ? 'border-teal-400 bg-teal-100 text-teal-900' : 'border-teal-200 bg-white text-gray-700 hover:bg-teal-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">2 級（二白）</p>
                <p class="text-xs text-gray-600 mt-1">自然偏白，是一般大眾認知的「白皙」，曬後會變紅再轉黑。</p>
            </button>
            <button @click="selectDepth('3')" :class="selectedDepth === '3' ? 'border-teal-400 bg-teal-100 text-teal-900' : 'border-teal-200 bg-white text-gray-700 hover:bg-teal-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">3 級（三白）</p>
                <p class="text-xs text-gray-600 mt-1">自然健康色，膚色飽滿，曬後很快就變均勻的小麥色。</p>
            </button>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <button @click="manualSelectorStep = 1" class="bg-white border border-teal-200 text-teal-700 py-2.5 rounded-xl font-bold hover:bg-teal-50">上一步</button>
            <button @click="closeManualSelector" class="bg-white border border-teal-200 text-teal-700 py-2.5 rounded-xl font-bold hover:bg-teal-50">取消</button>
        </div>
    </div>

    <!-- Step 3: Hue Bias Selection -->
    <div v-if="showManualSelector && manualSelectorStep === 3" class="mt-4 p-5 rounded-xl bg-gradient-to-br from-cyan-50 to-sky-50 border border-cyan-200">
        <p class="text-sm font-bold text-gray-800 mb-3">第 3 步：判定「特殊偏向」(The Hue Bias)</p>
        <p class="text-xs text-gray-600 mb-4">請觀察妳臉部的局部特徵，哪一個最符合？</p>
        <div class="space-y-2">
            <button @click="selectHueBias('α')" :class="selectedHueBias === 'α' ? 'border-cyan-400 bg-cyan-100 text-cyan-900' : 'border-cyan-200 bg-white text-gray-700 hover:bg-cyan-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 α（泛紅暖）</p>
                <p class="text-xs text-gray-600 mt-1">我常態性臉部泛紅（非過敏），皮膚看起來紅潤感很重。</p>
            </button>
            <button @click="selectHueBias('β')" :class="selectedHueBias === 'β' ? 'border-cyan-400 bg-cyan-100 text-cyan-900' : 'border-cyan-200 bg-white text-gray-700 hover:bg-cyan-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 β（純淨冷）</p>
                <p class="text-xs text-gray-600 mt-1">我沒有明顯泛紅，膚色看起來很純淨或偏向冷色調。</p>
            </button>
            <button @click="selectHueBias('γ')" :class="selectedHueBias === 'γ' ? 'border-cyan-400 bg-cyan-100 text-cyan-900' : 'border-cyan-200 bg-white text-gray-700 hover:bg-cyan-50'" class="w-full p-3 rounded-lg border transition text-left">
                <p class="font-bold">選項 γ（溫暖柔和）</p>
                <p class="text-xs text-gray-600 mt-1">我覺得膚色雖然有色調，但整體偏向溫暖、柔和。</p>
            </button>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <button @click="manualSelectorStep = 2" class="bg-white border border-cyan-200 text-cyan-700 py-2.5 rounded-xl font-bold hover:bg-cyan-50">上一步</button>
            <button @click="closeManualSelector" class="bg-white border border-cyan-200 text-cyan-700 py-2.5 rounded-xl font-bold hover:bg-cyan-50">取消</button>
        </div>
    </div>

    <!-- Step 4: Confirmation -->
    <div v-if="showManualSelector && manualSelectorStep === 4" class="mt-4 p-5 rounded-xl bg-gradient-to-br from-sky-50 to-indigo-50 border border-sky-200">
        <p class="text-sm font-bold text-gray-800 mb-4">確認膚色結果</p>
        <div v-if="matchedFinalTone" class="p-4 rounded-lg bg-white border border-sky-200 mb-4">
            <p class="text-sm text-gray-600">推薦膚色：</p>
            <div class="flex items-center gap-3 mt-2">
                <span class="inline-block h-8 w-8 rounded border-2 border-gray-300" :style="{ backgroundColor: matchedFinalTone.hex || '#ccc' }"></span>
                <p class="text-lg font-bold text-gray-900">{{ matchedFinalTone.toneName }}</p>
            </div>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <button @click="manualSelectorStep = 3" class="bg-white border border-sky-200 text-sky-700 py-2.5 rounded-xl font-bold hover:bg-sky-50">修改選擇</button>
            <button @click="confirmManualToneSelection" class="bg-sky-600 text-white py-2.5 rounded-xl font-bold hover:bg-sky-500">確認並完成</button>
        </div>
    </div>
</div>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="style.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
        <title>AI Skin Tone Detector</title>
</head>
<body>
    <?php include 'header.php'; ?>

    <div id="app">
        <!-- Skin Tone Detector Component -->
        <div v-if="dataLoaded" class="space-y-5">
                <div class="p-5 rounded-2xl border border-violet-100 bg-violet-50/70">
                    <div class="flex items-center justify-between text-sm font-semibold text-violet-800">
                        <span>步驟 {{ currentStep }} / 4</span>
                        <span v-if="currentStep === 1">第一頁：膚色問答</span>
                        <span v-else-if="currentStep === 2">第二頁：膚質問答</span>
                        <span v-else-if="currentStep === 3">第三頁：相機拍照</span>
                        <span v-else>第四頁：結果確認</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-violet-100 overflow-hidden">
                        <div class="h-full bg-violet-500 transition-all duration-300" :style="{ width: `${(currentStep / 4) * 100}%` }"></div>
                    </div>
                </div>

                <div v-if="currentStep === 1" class="p-5 md:p-6 border border-amber-100 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-extrabold text-gray-900 text-lg">🌤️ 第 1 頁：膚色問答</h3>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-amber-700 border border-amber-200">Skin Tone Quiz</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">先用簡單問答預測冷暖調，後面的相機分析會更穩。</p>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q1. 手腕血管看起來比較偏？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setToneQuizAnswer('t1', 'A')" :class="toneQuizOptionClass('t1', 'A')">(A) 藍紫色</button>
                                <button type="button" @click="setToneQuizAnswer('t1', 'B')" :class="toneQuizOptionClass('t1', 'B')">(B) 綠色</button>
                                <button type="button" @click="setToneQuizAnswer('t1', 'C')" :class="toneQuizOptionClass('t1', 'C')">(C) 兩種都有或看不太出來</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q2. 曬太陽後你的皮膚通常？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setToneQuizAnswer('t2', 'A')" :class="toneQuizOptionClass('t2', 'A')">(A) 先紅再黑</button>
                                <button type="button" @click="setToneQuizAnswer('t2', 'B')" :class="toneQuizOptionClass('t2', 'B')">(B) 很快曬黑</button>
                                <button type="button" @click="setToneQuizAnswer('t2', 'C')" :class="toneQuizOptionClass('t2', 'C')">(C) 看情況，兩者都會</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q3. 你戴哪種飾品比較顯氣色？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setToneQuizAnswer('t3', 'A')" :class="toneQuizOptionClass('t3', 'A')">(A) 銀色</button>
                                <button type="button" @click="setToneQuizAnswer('t3', 'B')" :class="toneQuizOptionClass('t3', 'B')">(B) 金色</button>
                                <button type="button" @click="setToneQuizAnswer('t3', 'C')" :class="toneQuizOptionClass('t3', 'C')">(C) 都可以</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="toneGuess" class="mt-3 p-3 rounded-xl border border-amber-200 bg-white text-sm text-amber-900">
                        問卷預估膚色方向：<span class="font-bold">{{ toneGuess }}</span>
                    </div>
                    <button @click="goToSkinTypeStep" class="mt-4 w-full bg-amber-600 text-white py-3 rounded-xl font-bold hover:bg-amber-500 active:scale-[0.99] transition duration-200 shadow-lg shadow-amber-600/20">
                        下一步：膚質問答
                    </button>
                                    
                </div>

                <div v-if="currentStep === 2" class="p-5 md:p-6 border border-indigo-100 rounded-2xl bg-gradient-to-br from-indigo-50 to-sky-50 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-extrabold text-gray-900 text-lg">📝 第 2 頁：膚質問答</h3>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-indigo-700 border border-indigo-200">User Quiz</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3">回答完後再拍照，系統能更精準判定你的膚質。</p>
                    <div class="space-y-3 text-sm">
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q1. 判斷基本油份 (洗臉後 30 分鐘，不擦保養品)</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q1','A')" :class="quizOptionClass('q1','A')">(A) 全臉緊繃，甚至有細紋、脫皮感。</button>
                                <button type="button" @click="setQuizAnswer('q1','B')" :class="quizOptionClass('q1','B')">(B) T區開始出油，但兩頰感覺緊繃。</button>
                                <button type="button" @click="setQuizAnswer('q1','C')" :class="quizOptionClass('q1','C')">(C) T區出油明顯，兩頰感覺舒適不緊繃。</button>
                                <button type="button" @click="setQuizAnswer('q1','D')" :class="quizOptionClass('q1','D')">(D) 全臉皆有明顯油光。</button>
                                <button type="button" @click="setQuizAnswer('q1','E')" :class="quizOptionClass('q1','E')">(E) 全臉不緊繃也不油膩，非常舒適。</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q2. 判斷毛孔與紋理 (視覺觀察)</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q2','A')" :class="quizOptionClass('q2','A')">(A) 毛孔細緻看不見，但容易有乾紋。</button>
                                <button type="button" @click="setQuizAnswer('q2','B')" :class="quizOptionClass('q2','B')">(B) 僅 T 區毛孔明顯，兩頰細緻。</button>
                                <button type="button" @click="setQuizAnswer('q2','C')" :class="quizOptionClass('q2','C')">(C) 全臉毛孔粗大，容易長黑頭、粉刺。</button>
                                <button type="button" @click="setQuizAnswer('q2','D')" :class="quizOptionClass('q2','D')">(D) 皮膚平滑，質地均勻。</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q3. 判斷持妝表現 (下午 3-4 點的狀態)</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q3','A')" :class="quizOptionClass('q3','A')">(A) 嚴重浮粉、起皮，感覺妝吸不住。</button>
                                <button type="button" @click="setQuizAnswer('q3','B')" :class="quizOptionClass('q3','B')">(B) 鼻翼兩側脫妝掉粉，但兩頰很乾。</button>
                                <button type="button" @click="setQuizAnswer('q3','C')" :class="quizOptionClass('q3','C')">(C) T區油光滿面，有明顯暗沉。</button>
                                <button type="button" @click="setQuizAnswer('q3','D')" :class="quizOptionClass('q3','D')">(D) 妝感依然完整，僅微出油。</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q4. 判斷敏感度 (獨立維度)</p>
                            <p class="text-xs text-gray-500 mb-2">敏感肌可能重疊在以上任何膚質上，但在你的分類中它被獨立出來，代表要偵測「耐受度」。</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q4','A')" :class="quizOptionClass('q4','A')">(A) 換季、風吹或用新產品時，極易泛紅、刺痛、發癢。</button>
                                <button type="button" @click="setQuizAnswer('q4','B')" :class="quizOptionClass('q4','B')">(B) 偶爾因疲勞或特定成分長疹子，但很快恢復。</button>
                                <button type="button" @click="setQuizAnswer('q4','C')" :class="quizOptionClass('q4','C')">(C) 皮膚像城牆一樣厚實，很少有不適感。</button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <button @click="currentStep = 1" class="bg-white text-indigo-600 py-2.5 rounded-xl font-bold border border-indigo-200 hover:bg-indigo-50 transition duration-200">
                            返回：膚色問答
                        </button>
                        <button @click="goToCameraStep" class="bg-indigo-600 text-white py-2.5 rounded-xl font-bold hover:bg-indigo-500 transition duration-200">
                            下一步：相機分析
                        </button>
                    </div>
                </div>

                <div v-if="currentStep === 3" class="p-5 md:p-6 border border-violet-100 rounded-2xl bg-gradient-to-br from-violet-50 to-fuchsia-50 shadow-sm">
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

                    <!-- Multi-step manual selector -->
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
                            <button @click="closeManualSelector" class="bg-white border border-emerald-200 text-emerald-700 py-2.5 rounded-xl font-bold hover:bg-emerald-50">
                                取消
                            </button>
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
                            <button @click="manualSelectorStep = 1" class="bg-white border border-teal-200 text-teal-700 py-2.5 rounded-xl font-bold hover:bg-teal-50">
                                上一步
                            </button>
                            <button @click="closeManualSelector" class="bg-white border border-teal-200 text-teal-700 py-2.5 rounded-xl font-bold hover:bg-teal-50">
                                取消
                            </button>
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
                            <button @click="manualSelectorStep = 2" class="bg-white border border-cyan-200 text-cyan-700 py-2.5 rounded-xl font-bold hover:bg-cyan-50">
                                上一步
                            </button>
                            <button @click="closeManualSelector" class="bg-white border border-cyan-200 text-cyan-700 py-2.5 rounded-xl font-bold hover:bg-cyan-50">
                                取消
                            </button>
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
                            <button @click="manualSelectorStep = 3" class="bg-white border border-sky-200 text-sky-700 py-2.5 rounded-xl font-bold hover:bg-sky-50">
                                修改選擇
                            </button>
                            <button @click="confirmManualToneSelection" class="bg-sky-600 text-white py-2.5 rounded-xl font-bold hover:bg-sky-500">
                                確認並完成
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="currentStep === 4" class="space-y-5">
                    <div v-if="skinCoordinate" class="p-5 border border-sky-100 rounded-2xl bg-gradient-to-br from-sky-50 to-blue-50 shadow-sm">
                        <h3 class="font-extrabold mb-3 text-gray-900">🎯 您的膚色座標</h3>
                        <div class="grid gap-2 text-sm md:text-base">
                            <p><span class="text-gray-500">類型：</span><span class="font-semibold">{{ skinCoordinate.type }}</span></p>
                            <p><span class="text-gray-500">RGB：</span><span class="font-medium">{{ skinCoordinate.rgb }}</span></p>
                            <p class="flex items-center gap-2"><span class="text-gray-500">HEX：</span><span class="font-medium">{{ skinCoordinate.hex }}</span><span class="inline-block h-4 w-4 rounded-full border border-white shadow" :style="{ backgroundColor: skinCoordinate.hex }"></span></p>
                            <p v-if="toneFusionNote" class="text-xs md:text-sm text-sky-700">{{ toneFusionNote }}</p>
                        </div>
                    </div>

                    <div class="p-5 border border-purple-100 rounded-2xl bg-gradient-to-br from-purple-50 to-fuchsia-50 shadow-sm">
                        <h3 class="font-extrabold mb-2 text-gray-900">🧴 膚質分析結果</h3>
                        <p class="text-lg font-bold text-purple-800">{{ skinTypeResult.profile?.displayName || skinTypeResult || manualSkinType || '尚未判定' }}</p>
                        <p class="text-sm text-gray-700 mt-1">膚質：{{ manualSkinType || skinTypeResult || '尚未判定' }}{{ manualSensitiveSkin ? ' + 敏感肌' : '' }}</p>
                        <p class="text-xs text-gray-500 mt-1">若這裡還是空白，請先選擇下方膚質再按「套用這個膚質」。</p>
                        <p v-if="skinTypeSecondary" class="text-sm font-semibold text-rose-700 mt-1">第二結果：{{ skinTypeSecondary }}</p>
                        <p v-if="fusionNote" class="text-xs text-purple-700 mt-1">{{ fusionNote }}</p>
                        <p v-if="makeupPreferenceNote" class="text-xs text-pink-700 mt-1">{{ makeupPreferenceNote }}</p>
                        <p v-if="confidenceScore !== null" class="text-sm text-gray-700 mt-2">信心分數: {{ confidenceScore }}</p>
                        <p v-if="consistencyScoreValue !== null" class="text-xs text-indigo-700 mt-1">一致性分數: {{ consistencyScoreValue }}</p>
                        <div class="mt-4 rounded-2xl border border-purple-100 bg-white/90 p-4 space-y-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 mb-2">可直接沿用前面判定，或自行修改膚質</p>
                                <select
                                    v-model="manualSkinType"
                                    class="w-full rounded-xl border border-purple-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-800 focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-200"
                                >
                                    <option disabled value="">請選擇膚質</option>
                                    <option v-for="option in skinTypeOptions" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                                <input
                                    v-model="manualSensitiveSkin"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-purple-300 text-purple-600 focus:ring-purple-400"
                                />
                                同時標記為敏感肌
                            </label>
                            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
                                <span>目前使用：{{ manualSkinType || '尚未選擇' }}{{ manualSensitiveSkin ? ' + 敏感肌' : '' }}</span>
                                <button
                                    type="button"
                                    @click="analyzeManual"
                                    class="rounded-xl bg-purple-600 px-3 py-2 font-bold text-white transition hover:bg-purple-500"
                                >
                                    套用這個膚質
                                </button>
                            </div>
                        </div>
                        <div v-if="toneMismatchWarning" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                            {{ toneMismatchWarning }}
                        </div>
                        <div v-if="needsRetest" class="mt-2 text-xs md:text-sm p-2.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-800">
                            檢測到結果一致性偏低，建議重新確認問卷作答與分析設定（needs_retest）。
                        </div>
                        <div v-if="retestMessage" class="mt-2 text-xs md:text-sm p-2.5 rounded-xl border border-rose-300 bg-rose-50 text-rose-800">
                            {{ retestMessage }}
                        </div>
                        <div v-if="skinFeatures" class="text-sm text-gray-700 mt-2 space-y-1">
                            <p>T 區油光: {{ skinFeatures.t_zone_shine }}</p>
                            <p>毛孔狀態: {{ skinFeatures.pore_visibility }}</p>
                            <p>泛紅: {{ skinFeatures.redness ? '是' : '否' }}</p>
                        </div>
                    </div>

                    <div class="p-5 md:p-6 border border-amber-100 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-extrabold text-gray-900 text-lg">確認你的分析結果</h4>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-amber-700 border border-amber-200">Confirm Step</span>
                        </div>
                        <div class="grid md:grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="confirmSkinTone"
                                class="rounded-2xl border p-4 text-left transition-all duration-200"
                                :class="confirmedSkinTone ? 'border-emerald-300 bg-emerald-50 text-emerald-800 shadow-sm' : 'border-amber-200 bg-white text-gray-700 hover:border-amber-300 hover:bg-amber-50'"
                            >
                                <p class="font-bold text-base">確認膚色</p>
                                <p class="text-sm mt-1">{{ confirmedSkinTone ? '已確認目前膚色結果' : '點我確認目前的膚色判定' }}</p>
                            </button>
                            <button
                                type="button"
                                @click="confirmSkinType"
                                class="rounded-2xl border p-4 text-left transition-all duration-200"
                                :class="confirmedSkinType ? 'border-emerald-300 bg-emerald-50 text-emerald-800 shadow-sm' : 'border-amber-200 bg-white text-gray-700 hover:border-amber-300 hover:bg-amber-50'"
                            >
                                <p class="font-bold text-base">確認膚質</p>
                                <p class="text-sm mt-1">{{ confirmedSkinType ? '已確認目前膚質結果' : '點我確認目前的膚質判定' }}</p>
                            </button>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white/90 border border-amber-100 p-4">
                            <div class="text-sm text-gray-600">
                                狀態：
                                <span class="font-semibold" :class="confirmedSkinTone ? 'text-emerald-600' : 'text-gray-400'">膚色已確認</span>
                                ／
                                <span class="font-semibold" :class="confirmedSkinType ? 'text-emerald-600' : 'text-gray-400'">膚質已確認</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="backToToneAndSkinPage"
                                    class="rounded-xl px-4 py-2.5 text-sm font-bold bg-white text-amber-700 border border-amber-200 hover:bg-amber-50 transition-all duration-200"
                                >
                                    返回前一頁（測膚色與膚質）
                                </button>
                                <button
                                    type="button"
                                    @click="goToMakeupStep"
                                    :disabled="!canChooseMakeupPreference"
                                    class="rounded-xl px-4 py-2.5 text-sm font-bold transition-all duration-200"
                                    :class="canChooseMakeupPreference ? 'bg-gray-900 text-white hover:bg-gray-800' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                >
                                    前往妝感偏好頁
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="showMakeupPreference" class="p-5 md:p-6 border border-pink-100 rounded-2xl bg-gradient-to-br from-pink-50 to-rose-50 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-extrabold text-gray-900 text-lg">💋 第 4 頁：妝感與妝容偏好</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-pink-700 border border-pink-200">AI 偏好輸入</span>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">先確認膚色與膚質，再選妝感會更準。</p>
                        <div class="grid gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 mb-2">妝感</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" @click="setMakeupPreference('finish', '霧面')" :class="makeupOptionClass(makeupFinish, '霧面')">霧面</button>
                                    <button type="button" @click="setMakeupPreference('finish', '水光感')" :class="makeupOptionClass(makeupFinish, '水光感')">水光感</button>
                                    <button type="button" @click="setMakeupPreference('finish', '奶油肌')" :class="makeupOptionClass(makeupFinish, '奶油肌')">奶油肌</button>
                                    <button type="button" @click="setMakeupPreference('finish', '自然裸妝')" :class="makeupOptionClass(makeupFinish, '自然裸妝')">自然裸妝</button>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-gray-800 mb-2">喜歡的妝容風格</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" @click="setMakeupPreference('style', '日常通勤')" :class="makeupOptionClass(makeupStyle, '日常通勤')">日常通勤</button>
                                    <button type="button" @click="setMakeupPreference('style', '韓系清透')" :class="makeupOptionClass(makeupStyle, '韓系清透')">韓系清透</button>
                                    <button type="button" @click="setMakeupPreference('style', '歐美立體')" :class="makeupOptionClass(makeupStyle, '歐美立體')">歐美立體</button>
                                    <button type="button" @click="setMakeupPreference('style', '約會精緻')" :class="makeupOptionClass(makeupStyle, '約會精緻')">約會精緻</button>
                                </div>
                            </div>

                            <div class="rounded-xl border border-pink-100 bg-white/80 p-3 text-sm text-gray-700">
                                <p class="font-semibold text-gray-900 mb-1">目前偏好</p>
                                <p>妝感：{{ makeupFinish || '未選擇，預設霧面' }}</p>
                                <p>妝容：{{ makeupStyle || '未選擇，預設日常通勤' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <button @click="showMakeupPreference = false" class="bg-white text-pink-600 py-2.5 rounded-xl font-bold border border-pink-200 hover:bg-pink-50 transition duration-200">
                                返回：確認結果
                            </button>
                            <button @click="analyzeWithGroq" class="bg-pink-600 text-white py-2.5 rounded-xl font-bold hover:bg-pink-500 transition duration-200">
                                送出偏好並完成分析
                            </button>
                        </div>
                    </div>

                    <div v-if="recommendations.length > 0" class="p-5 border border-emerald-100 rounded-2xl bg-gradient-to-br from-emerald-50 to-lime-50 shadow-sm">
                        <h3 class="font-extrabold mb-3 text-gray-900">💄 推薦產品</h3>
                        <div class="grid gap-3">
                            <div v-for="product in recommendations" :key="product.id" class="p-3.5 bg-white/90 border border-emerald-100 rounded-xl hover:shadow-md transition duration-200">
                                <p class="font-bold text-gray-900">{{ product.brand || '通用' }}｜{{ product.productName || product.name || '推薦產品' }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ product.description }}</p>
                                <p v-if="product.matchScore !== null" class="text-xs text-emerald-700 mt-2">AI 匹配度：{{ product.matchScore }}</p>
                                <p v-if="product.recommendedShade" class="text-xs text-indigo-700 mt-1">建議色號：{{ product.recommendedShade }}</p>
                                <div class="mt-3">
                                    <p class="text-xs text-gray-500 mb-2">如果你用過這項產品，請標記實際妝效：</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                        <button type="button" @click="submitProductFeedback(product, 'just_right')" class="text-xs py-2 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition">色號剛好</button>
                                        <button type="button" @click="submitProductFeedback(product, 'too_yellow')" class="text-xs py-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 transition">偏黃</button>
                                        <button type="button" @click="submitProductFeedback(product, 'too_dark')" class="text-xs py-2 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition">偏暗</button>
                                        <button type="button" @click="submitProductFeedback(product, 'too_dry')" class="text-xs py-2 rounded-lg border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 transition">太乾</button>
                                        <button type="button" @click="submitProductFeedback(product, 'too_oily')" class="text-xs py-2 rounded-lg border border-lime-200 bg-lime-50 text-lime-700 hover:bg-lime-100 transition">太油</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="feedbackHistory.length > 0" class="p-5 border border-cyan-100 rounded-2xl bg-gradient-to-br from-cyan-50 to-sky-50 shadow-sm">
                        <h3 class="font-extrabold mb-3 text-gray-900">🧾 歷史回饋清單</h3>
                        <div class="space-y-2">
                            <div v-for="item in feedbackHistory" :key="`${item.product_id}-${item.updated_at}`" class="p-3 rounded-xl border border-cyan-100 bg-white/90">
                                <p class="text-sm text-gray-700">產品 ID：<span class="font-semibold">{{ item.product_id }}</span></p>
                                <p class="text-xs text-cyan-700 mt-1">最近回饋：{{ feedbackTypeText(item.feedback_type) }} ｜ {{ item.updated_at }}</p>
                                <div class="mt-2 grid grid-cols-2 sm:grid-cols-5 gap-2">
                                    <button type="button" @click="submitProductFeedback({ id: item.product_id }, 'just_right')" class="text-xs py-2 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition">改成：剛好</button>
                                    <button type="button" @click="submitProductFeedback({ id: item.product_id }, 'too_yellow')" class="text-xs py-2 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 transition">改成：偏黃</button>
                                    <button type="button" @click="submitProductFeedback({ id: item.product_id }, 'too_dark')" class="text-xs py-2 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition">改成：偏暗</button>
                                    <button type="button" @click="submitProductFeedback({ id: item.product_id }, 'too_dry')" class="text-xs py-2 rounded-lg border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 transition">改成：太乾</button>
                                    <button type="button" @click="submitProductFeedback({ id: item.product_id }, 'too_oily')" class="text-xs py-2 rounded-lg border border-lime-200 bg-lime-50 text-lime-700 hover:bg-lime-100 transition">改成：太油</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="ingredientAdvice" class="p-5 border border-rose-100 rounded-2xl bg-gradient-to-br from-rose-50 to-pink-50 shadow-sm">
                        <h3 class="font-extrabold mb-2 text-gray-900">🧪 成分避雷（AI）</h3>
                        <p class="text-sm text-gray-600 mb-2">膚質：{{ ingredientAdvice.skin_type }}</p>
                        <ul class="space-y-2">
                            <li v-for="item in ingredientAdvice.avoid_ingredients" :key="item.ingredient" class="bg-white/90 border border-rose-100 rounded-xl p-3">
                                <p class="font-semibold text-gray-900">{{ item.ingredient }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ item.reason }}</p>
                            </li>
                        </ul>
                        <div v-if="ingredientAdvice.suitable_focus && ingredientAdvice.suitable_focus.length" class="mt-3 text-sm text-gray-700">
                            <p class="font-semibold">建議著重：</p>
                            <p>{{ ingredientAdvice.suitable_focus.join('、') }}</p>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">{{ ingredientAdvice.disclaimer }}</p>
                    </div>

                    <div v-if="isAnalyzing" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium animate-pulse">
                        正在使用 AI 分析照片，請稍候...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const { createApp, ref, nextTick, onUnmounted, computed } = Vue;

        // App data
        const dataLoaded = ref(false);
        const currentStep = ref(1);
        const skinTonesData = ref([]);
        const cameraActive = ref(false);
        const skinTone = ref('');
        const manualSkinType = ref('');
        const manualSensitiveSkin = ref(false);
        const makeupFinish = ref('霧面');
        const makeupStyle = ref('日常通勤');
        const confirmedSkinTone = ref(false);
        const confirmedSkinType = ref(false);
        const showMakeupPreference = ref(false);
        const skinTypeOptions = ['混油皮', '乾性皮', '油性皮', '中性皮', '混乾皮', '敏感肌'];
        const toneQuizAnswers = ref({
            t1: '',
            t2: '',
            t3: ''
        });
        const toneGuess = ref('');
        const toneFusionNote = ref('');
        const quizAnswers = ref({
            q1: '',
            q2: '',
            q3: '',
            q4: ''
        });
        const skinCoordinate = ref(null);
        const skinTypeResult = ref('');
        const skinTypeSecondary = ref('');
        const skinFeatures = ref(null);
        const confidenceScore = ref(null);
        const consistencyScoreValue = ref(null);
        const fusionNote = ref('');
        const makeupPreferenceNote = ref('');
        const needsRetest = ref(false);
        const retestMessage = ref('');
        const recommendations = ref([]);
        const ingredientAdvice = ref(null);
        const ingredientLoading = ref(false);
        const feedbackHistory = ref([]);
        const isAnalyzing = ref(false);
        const faceDetectionBusy = ref(false);
        const showResultModal = ref(false);
        const hasShownNaturalLightReminder = ref(false);
        const video = ref(null);
        const canvas = ref(null);
        // Multi-step manual selector states - NEW LOGIC
        const showManualSelector = ref(false);
        const manualSelectorStep = ref(0); // 0=off, 1=base tone, 2=depth level, 3=hue bias, 4=confirm
        const selectedBase = ref(''); // 'A' (warm/yellow), 'B' (cool/pink), 'C' (neutral), 'D' (olive)
        const selectedDepth = ref(''); // '1', '2', '3'
        const selectedHueBias = ref(''); // 'α', 'β', 'γ'
        const matchedFinalTone = ref(null); // Final matched tone from logic matrix
        const toneMismatchWarning = ref('');
        const canChooseMakeupPreference = computed(() => confirmedSkinTone.value && confirmedSkinType.value);

        const openResultModal = () => {
            showResultModal.value = true;
        };

        const closeResultModal = () => {
            showResultModal.value = false;
        };

        const setToneQuizAnswer = (questionKey, optionValue) => {
            toneQuizAnswers.value[questionKey] = optionValue;
            toneGuess.value = inferToneGuess();
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
            const answerToFamily = {
                A: 'cool',
                B: 'warm',
                C: 'neutral'
            };

            ['t1', 't2', 't3'].forEach((key) => {
                const family = answerToFamily[toneQuizAnswers.value[key]];
                if (family) {
                    score[family] += 1;
                }
            });

            if (score.cool === 0 && score.warm === 0 && score.neutral === 0) {
                return '';
            }

            if (score.cool > score.warm && score.cool >= score.neutral) {
                return '偏冷調';
            }
            if (score.warm > score.cool && score.warm >= score.neutral) {
                return '偏暖調';
            }
            return '中性調';
        };

        const applyToneGuessToSelection = () => {
            if (!toneGuess.value || !Array.isArray(skinTonesData.value) || !skinTonesData.value.length) {
                return;
            }

            const keywordMap = {
                '偏冷調': ['冷'],
                '偏暖調': ['暖', '黃'],
                '中性調': ['中性', '中']
            };

            const keywords = keywordMap[toneGuess.value] || [];
            const matched = skinTonesData.value.find((tone) =>
                keywords.some((keyword) => String(tone.toneName || '').includes(keyword))
            );

            if (matched?.toneName) {
                skinTone.value = matched.toneName;
            }
        };

        const goToSkinTypeStep = () => {
            if (!ensureToneQuizCompleted()) {
                return;
            }
            applyToneGuessToSelection();
            currentStep.value = 2;
        };

        const goToCameraStep = () => {
            if (!ensureQuizCompleted()) {
                return;
            }
            const quizPreview = getCalibratedSkinType('');
            manualSkinType.value = quizPreview.finalType || manualSkinType.value;
            manualSensitiveSkin.value = quizPreview.secondaryType === '敏感肌';
            skinTypeResult.value = quizPreview.finalType || '';
            skinTypeSecondary.value = quizPreview.secondaryType || '';
            needsRetest.value = quizPreview.needsRetest;
            consistencyScoreValue.value = quizPreview.consistencyScore.toFixed(2);
            confirmedSkinTone.value = false;
            confirmedSkinType.value = false;
            showMakeupPreference.value = false;
            currentStep.value = 3;
        };

        const confirmSkinTone = () => {
            confirmedSkinTone.value = true;
        };

        const confirmSkinType = () => {
            confirmedSkinType.value = true;
        };

        const backToToneAndSkinPage = () => {
            currentStep.value = 3;
        };

        const goToMakeupStep = () => {
            if (!canChooseMakeupPreference.value) {
                alert('請先確認膚色與膚質。');
                return;
            }

            showMakeupPreference.value = true;
            currentStep.value = 4;
        };

        const setQuizAnswer = (questionKey, optionValue) => {
            quizAnswers.value[questionKey] = optionValue;
        };

        const quizOptionClass = (questionKey, optionValue) => {
            const selected = quizAnswers.value[questionKey] === optionValue;
            return selected
                ? 'w-full text-left p-2.5 rounded-xl border border-indigo-400 bg-indigo-100 text-indigo-900 font-semibold shadow-sm transition'
                : 'w-full text-left p-2.5 rounded-xl border border-indigo-200 bg-white text-gray-700 hover:border-indigo-300 hover:bg-indigo-50 transition';
        };

        const makeupOptionClass = (currentValue, optionValue) => {
            const selected = currentValue === optionValue;
            return selected
                ? 'w-full text-left px-3 py-2.5 rounded-xl border border-pink-400 bg-pink-100 text-pink-900 font-semibold shadow-sm transition'
                : 'w-full text-left px-3 py-2.5 rounded-xl border border-pink-200 bg-white text-gray-700 hover:border-pink-300 hover:bg-pink-50 transition';
        };

        const setMakeupPreference = (key, value) => {
            if (key === 'finish') {
                makeupFinish.value = value;
            }
            if (key === 'style') {
                makeupStyle.value = value;
            }
        };


        const ensureQuizCompleted = () => {
            const { q1, q2, q3, q4 } = quizAnswers.value;
            if (!q1 || !q2 || !q3 || !q4) {
                alert('請先完成「膚質校正問卷（含反向驗證）」才能得到更準確的膚質判定。');
                return false;
            }
            return true;
        };

        const getBaseSkinScore = () => ({
            '混油皮': 0,
            '乾性皮': 0,
            '油性皮': 0,
            '中性皮': 0,
            '混乾皮': 0,
            '敏感肌': 0,
        });

        const clamp01 = (value) => Math.max(0, Math.min(1, value));

        const calcStdDev = (numbers) => {
            if (!numbers.length) return 0;
            const mean = numbers.reduce((sum, value) => sum + value, 0) / numbers.length;
            const variance = numbers.reduce((sum, value) => sum + Math.pow(value - mean, 2), 0) / numbers.length;
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
            const quizScores = getBaseSkinScore();
            const aiScores = getBaseSkinScore();

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

            const stdDev = calcStdDev(answerVector);
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
                    if (quizScores[skinType] !== undefined) {
                        quizScores[skinType] += weight;
                    }
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
            let aiWeight = 0.20 + 0.20 * clamp01(aiConfidence);

            Object.keys(combinedScores).forEach((skinType) => {
                combinedScores[skinType] = quizScores[skinType] * quizWeight + aiScores[skinType] * aiWeight;
            });

            const findTopType = (scoreMap) => {
                let topType = '中性皮';
                let topScore = -Infinity;
                Object.entries(scoreMap).forEach(([skinType, score]) => {
                    if (score > topScore) {
                        topScore = score;
                        topType = skinType;
                    }
                });
                return { topType, topScore };
            };

            const quizTop = findTopType(quizScores);
            const aiTop = findTopType(aiScores);
            const combinedTop = findTopType(combinedScores);

            const combinedNonSensitiveScores = { ...combinedScores };
            delete combinedNonSensitiveScores['敏感肌'];
            const nonSensitiveTop = findTopType(combinedNonSensitiveScores);
            const sensitiveQuizScore = quizScores['敏感肌'] || 0;
            const sensitiveCombinedScore = combinedScores['敏感肌'] || 0;
            const sensitiveForced = q4 === 'A';
            const secondaryType = (sensitiveForced || sensitiveCombinedScore >= 2.8 || sensitiveQuizScore >= 2.8)
                ? '敏感肌'
                : '';

            const aiFamily = resolveSkinFamily(aiTop.topType);
            const quizFamily = resolveSkinFamily(quizTop.topType);
            const crossCategoryConflict = aiTop.topScore > 0 && aiFamily !== quizFamily;
            const conflictGap = Math.abs((quizScores[quizTop.topType] || 0) - (quizScores[aiTop.topType] || 0));
            const isOutlier = crossCategoryConflict && conflictGap >= 1.2;

            if (isOutlier) {
                quizWeight *= 0.75;
                aiWeight *= 1.25;
                const normalized = quizWeight + aiWeight;
                quizWeight /= normalized;
                aiWeight /= normalized;

                Object.keys(combinedScores).forEach((skinType) => {
                    combinedScores[skinType] = quizScores[skinType] * quizWeight + aiScores[skinType] * aiWeight;
                });
            }

            const needsRetestFlag = consistencyScore < 0.42 || (isOutlier && consistencyScore < 0.7) || consistencyIssues.length >= 3;
            
            // Only block recommendation if user has answered the quiz AND consistency is poor
            // If no quiz answers yet (all empty), allow recommendations based on AI analysis alone
            const hasQuizAnswers = Object.values(quizAnswers.value).some(v => v !== '');
            const blockRecommendation = hasQuizAnswers && (consistencyScore < 0.42 || (isOutlier && consistencyScore < 0.55));

            let finalType = nonSensitiveTop.topType || '中性皮';
            if (finalType === '敏感肌') {
                finalType = '中性皮';
            }

            return {
                finalType,
                secondaryType,
                scores: combinedScores,
                quizScores,
                aiScores,
                quizWeight,
                aiWeight,
                consistencyScore,
                consistencyIssues,
                isOutlier,
                needsRetest: needsRetestFlag,
                blockRecommendation,
                sensitiveForced,
            };
        };

        // Load skin tone data from database
        fetch('./getSkinTones.php')
            .then(async response => {
                if (!response.ok) {
                    const errorBody = await response.json().catch(() => ({}));
                    throw new Error(errorBody.message || '無法從資料庫取得膚色資料');
                }
                return response.json();
            })
            .then(data => {
                skinTonesData.value = data;
                window.skinTonesData = data;
                dataLoaded.value = true;
            })
            .catch(error => {
                console.error('Failed to load skin tone data:', error);
                alert('載入膚色資料失敗，請檢查資料庫連線或後端設定');
            });

        // Camera functions
        const startCamera = async () => {
            try {
                if (!hasShownNaturalLightReminder.value) {
                    alert('提醒：請在自然光下拍攝，結果會更準確。');
                    hasShownNaturalLightReminder.value = true;
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: 640,
                        height: 480,
                        facingMode: 'user'
                    }
                });
                cameraActive.value = true;
                await nextTick();
                if (!video.value) {
                    console.warn('video ref 尚未綁定，嘗試使用 document.querySelector fallback');
                    const domVideo = document.querySelector('video');
                    if (!domVideo) {
                        throw new Error('視頻元素尚未渲染，請稍候再試');
                    }
                    video.value = domVideo;
                }
                video.value.srcObject = stream;
                video.value.onloadedmetadata = () => {
                    video.value.play().catch(() => {})
                };
            } catch (error) {
                let errorMessage = '無法訪問相機: ';
                if (error.name === 'NotAllowedError') {
                    errorMessage += '請允許相機權限';
                } else if (error.name === 'NotFoundError') {
                    errorMessage += '未找到相機設備';
                } else if (error.name === 'NotReadableError') {
                    errorMessage += '相機被其他應用占用';
                } else if (error.name === 'OverconstrainedError') {
                    errorMessage += '相機不支持請求的配置';
                } else {
                    errorMessage += error.message;
                }
                alert(errorMessage);
            }
        };

        const stopCamera = () => {
            if (video.value && video.value.srcObject) {
                const stream = video.value.srcObject;
                const tracks = stream.getTracks();
                tracks.forEach(track => track.stop());
                video.value.srcObject = null;
            }
            cameraActive.value = false;
        };

        // Robust face detection: prefer native FaceDetector, fallback to BlazeFace (tfjs) when unavailable.
        const ensureFallbackFaceDetector = (() => {
            let blazefaceModel = null;
            let loadingPromise = null;

            return async () => {
                // If native API exists, use it
                if (typeof window.FaceDetector === 'function') {
                    return {
                        detect: async (videoEl) => {
                            const fd = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 2 });
                            const faces = await fd.detect(videoEl);
                            return Array.isArray(faces) ? faces.map(f => ({ raw: f, boundingBox: f.boundingBox })) : [];
                        }
                    };
                }

                // If model already loaded, return wrapper
                if (blazefaceModel) {
                    return {
                        detect: async (videoEl) => {
                            const preds = await blazefaceModel.estimateFaces(videoEl, false);
                            return Array.isArray(preds) ? preds.map(pred => {
                                const topLeft = pred.topLeft || [0, 0];
                                const bottomRight = pred.bottomRight || [0, 0];
                                const [x1, y1] = topLeft;
                                const [x2, y2] = bottomRight;
                                return { raw: pred, boundingBox: { x: x1, y: y1, width: x2 - x1, height: y2 - y1 } };
                            }) : [];
                        }
                    };
                }

                // Lazy-load TF + BlazeFace once
                if (!loadingPromise) {
                    loadingPromise = (async () => {
                        // Load TF script
                        await new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.7.0/dist/tf.min.js';
                            s.async = true;
                            s.onload = resolve;
                            s.onerror = reject;
                            document.head.appendChild(s);
                        });

                        // Load BlazeFace
                        await new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = 'https://cdn.jsdelivr.net/npm/@tensorflow-models/blazeface@0.0.7/dist/blazeface.min.js';
                            s.async = true;
                            s.onload = resolve;
                            s.onerror = reject;
                            document.head.appendChild(s);
                        });

                        // Wait for global 'blazeface' to be available and load model
                        if (typeof blazeface === 'undefined') {
                            throw new Error('BlazeFace library未正確載入');
                        }
                        blazefaceModel = await blazeface.load();
                    })();
                }

                await loadingPromise;

                return {
                    detect: async (videoEl) => {
                        const preds = await blazefaceModel.estimateFaces(videoEl, false);
                        return Array.isArray(preds) ? preds.map(pred => {
                            const topLeft = pred.topLeft || [0, 0];
                            const bottomRight = pred.bottomRight || [0, 0];
                            const [x1, y1] = topLeft;
                            const [x2, y2] = bottomRight;
                            return { raw: pred, boundingBox: { x: x1, y: y1, width: x2 - x1, height: y2 - y1 } };
                        }) : [];
                    }
                };
            };
        })();

        const detectFacesInFrame = async () => {
            if (!video.value) {
                throw new Error('相機畫面尚未準備好');
            }

            const detector = await ensureFallbackFaceDetector();
            let faces = [];
            try {
                const results = await detector.detect(video.value);
                faces = Array.isArray(results) ? results : [];
            } catch (err) {
                console.warn('face detector error:', err);
                faces = [];
            }

            if (!faces.length) {
                throw new Error('請先讓畫面中出現清楚的人臉，再進行拍照');
            }

            if (faces.length > 1) {
                throw new Error('畫面中偵測到多人，請只保留一張臉再拍照');
            }

            const face = faces[0];
            if (!face || !face.boundingBox) {
                throw new Error('人臉偵測失敗，請重新對準臉部');
            }

            return face;
        };

        const captureImage = async () => {
            if (!video.value) {
                console.warn('video ref 未綁定，嘗試 fallback');
                video.value = document.querySelector('video');
            }
            if (!canvas.value) {
                console.warn('canvas ref 未綁定，嘗試 fallback');
                canvas.value = document.querySelector('canvas');
            }

            if (!video.value || !canvas.value) {
                alert('相機未啟動或畫布未準備好');
                return;
            }

            if (faceDetectionBusy.value) {
                return;
            }

            faceDetectionBusy.value = true;
            try {
                console.log('開始人臉偵測');
                // timeout if face detection or model loading takes too long
                const face = await Promise.race([
                    detectFacesInFrame(),
                    new Promise((_, reject) => setTimeout(() => reject(new Error('人臉偵測逾時，請確認網路或改用手動模式')), 7000))
                ]);
                console.log('人臉偵測完成', face);

                // Run obstacle and liveness checks before proceeding
                try {
                    const ok = await checkObstacleAndLiveness(face);
                    if (!ok) {
                        return;
                    }
                    // Challenge-response to mitigate replay/photo-attacks
                    try {
                        const challengeOk = await awaitUserChallenge(face);
                        if (!challengeOk) {
                            return;
                        }
                    } catch (chErr) {
                        console.error('challenge error:', chErr);
                        alert(chErr.message || '挑戰回應失敗，請重試');
                        return;
                    }
                } catch (checkErr) {
                    console.error('checkObstacleAndLiveness error:', checkErr);
                    alert(checkErr.message || '活體或遮擋檢測失敗');
                    return;
                }
            } catch (error) {
                console.error('detectFacesInFrame error:', error);
                alert(`無法拍照：${error.message}`);
                return;
            } finally {
                faceDetectionBusy.value = false;
            }

            const ctx = canvas.value.getContext('2d');
            canvas.value.width = video.value.videoWidth;
            canvas.value.height = video.value.videoHeight;
            ctx.save();
            ctx.translate(canvas.value.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);
            ctx.restore();

            // 進行膚色分析：優先抓左右臉頰較亮的膚色點
            await analyzeSkinFromImage();
        };

        // --- Obstacle and Liveness helpers ---
        const isSkinPixel = (r, g, b) => {
            // Simple YCbCr skin detection heuristic
            const y = 0.299 * r + 0.587 * g + 0.114 * b;
            const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
            const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
            return (cb >= 77 && cb <= 127) && (cr >= 133 && cr <= 173) && y > 40;
        };

        const sampleFacePatch = (face, size = 128) => {
            const off = document.createElement('canvas');
            off.width = size;
            off.height = size;
            const ctx = off.getContext('2d');
            const bb = face.boundingBox;
            const sx = Math.max(0, Math.floor(bb.x));
            const sy = Math.max(0, Math.floor(bb.y));
            const sw = Math.max(1, Math.floor(bb.width));
            const sh = Math.max(1, Math.floor(bb.height));
            ctx.drawImage(video.value, sx, sy, sw, sh, 0, 0, size, size);
            return ctx.getImageData(0, 0, size, size);
        };

        const computeSkinRatioRegion = (imageData, region = 'lower') => {
            const { data, width, height } = imageData;
            let skinCount = 0;
            let total = 0;
            const mid = Math.floor(height * 0.5);
            const y0 = region === 'upper' ? 0 : mid;
            const y1 = region === 'upper' ? mid : height;
            for (let y = y0; y < y1; y += 3) {
                for (let x = 0; x < width; x += 3) {
                    const i = (y * width + x) * 4;
                    const r = data[i], g = data[i + 1], b = data[i + 2];
                    if (isSkinPixel(r, g, b)) skinCount++;
                    total++;
                }
            }
            return total > 0 ? (skinCount / total) : 0;
        };

        const computeLaplacianVariance = (imageData) => {
            const { data, width, height } = imageData;
            // convert to grayscale small buffer
            const gray = new Float32Array(width * height);
            for (let i = 0; i < width * height; i++) {
                const idx = i * 4;
                gray[i] = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
            }
            // Laplacian kernel approximation
            const lap = new Float32Array(width * height);
            for (let y = 1; y < height - 1; y++) {
                for (let x = 1; x < width - 1; x++) {
                    const i = y * width + x;
                    const v = -4 * gray[i] + gray[i - 1] + gray[i + 1] + gray[i - width] + gray[i + width];
                    lap[i] = v;
                }
            }
            // variance
            let mean = 0, sq = 0, cnt = 0;
            for (let i = 0; i < lap.length; i++) {
                const v = lap[i];
                mean += v; sq += v * v; cnt++;
            }
            mean /= cnt; const variance = sq / cnt - mean * mean;
            return variance;
        };

        // Detect screen Moiré patterns via FFT frequency analysis
        // Screens typically show repetitive patterns at 60-120Hz
        const detectScreenMoireViaFFT = (imageData) => {
            const { data, width, height } = imageData;
            // Use central region for analysis
            const centerX = Math.floor(width / 2);
            const centerY = Math.floor(height / 2);
            const regionSize = 64;
            const x0 = Math.max(0, centerX - regionSize / 2);
            const y0 = Math.max(0, centerY - regionSize / 2);
            
            const gray = new Float32Array(regionSize * regionSize);
            for (let y = 0; y < regionSize; y++) {
                for (let x = 0; x < regionSize; x++) {
                    const srcIdx = ((y0 + y) * width + (x0 + x)) * 4;
                    const dstIdx = y * regionSize + x;
                    gray[dstIdx] = 0.299 * data[srcIdx] + 0.587 * data[srcIdx + 1] + 0.114 * data[srcIdx + 2];
                }
            }
            
            // Simple 1D horizontal FFT (detect vertical stripes = horizontal frequencies)
            const freqStrength = {};
            for (let y = 0; y < regionSize; y++) {
                const row = new Float32Array(regionSize);
                for (let x = 0; x < regionSize; x++) {
                    row[x] = gray[y * regionSize + x];
                }
                // Simple frequency detection via autocorrelation
                for (let lag = 1; lag <= regionSize / 4; lag++) {
                    let sum = 0;
                    for (let i = 0; i < regionSize - lag; i++) {
                        sum += row[i] * row[i + lag];
                    }
                    const freq = Math.round(regionSize / lag);
                    if (!freqStrength[freq]) freqStrength[freq] = 0;
                    freqStrength[freq] += Math.abs(sum);
                }
            }
            
            // Check for strong peaks in screen-typical frequencies (6-20px periodicity)
            let moireScore = 0;
            for (let freq = 6; freq <= 20; freq++) {
                if (freqStrength[freq]) {
                    moireScore += freqStrength[freq];
                }
            }
            return moireScore;
        };

        // Analyze skin texture coherence (natural skin vs photo)
        const analyzeSkinTextureCoherence = (imageData) => {
            const { data, width, height } = imageData;
            // Sample random skin-colored pixels and check local variance
            const isSkin = (r, g, b) => {
                const y = 0.299 * r + 0.587 * g + 0.114 * b;
                const cb = 128 + 0.5 * (b - y);
                const cr = 128 + 0.5 * (r - y);
                return y > 40 && cb >= 77 && cb <= 127 && cr >= 133 && cr <= 173;
            };
            
            let coherenceScore = 0;
            let sampleCount = 0;
            
            // Check multiple regions for skin texture
            for (let attempt = 0; attempt < 20; attempt++) {
                const x = Math.floor(Math.random() * (width - 8));
                const y = Math.floor(Math.random() * (height - 8));
                const centerIdx = (y * width + x) * 4;
                const r = data[centerIdx];
                const g = data[centerIdx + 1];
                const b = data[centerIdx + 2];
                
                if (!isSkin(r, g, b)) continue;
                
                // Check neighboring pixels variance
                let variance = 0;
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        if (dx === 0 && dy === 0) continue;
                        const idx = ((y + dy) * width + (x + dx)) * 4;
                        const nr = data[idx];
                        const ng = data[idx + 1];
                        const nb = data[idx + 2];
                        variance += Math.pow(nr - r, 2) + Math.pow(ng - g, 2) + Math.pow(nb - b, 2);
                    }
                }
                // Natural skin should have moderate variance, photos often have less
                coherenceScore += variance;
                sampleCount++;
            }
            
            return sampleCount > 0 ? coherenceScore / sampleCount : 0;
        };

        // Helper: Wait for multiple frames and collect callback results
        const waitForFrames = (durationMs, intervalMs = 200, cb) => new Promise((resolve) => {
            const start = Date.now();
            const results = [];
            const tick = async () => {
                const now = Date.now();
                if (now - start >= durationMs) {
                    resolve(results);
                    return;
                }
                try {
                    const r = await cb();
                    results.push(r);
                } catch (e) {
                    results.push(null);
                }
                setTimeout(tick, intervalMs);
            };
            tick();
        });

        // --- Debug panel helpers (non-blocking UI for liveness metrics) ---
        const ensureDebugPanel = () => {
            if (document.getElementById('liveness-debug')) return document.getElementById('liveness-debug');
            const d = document.createElement('div');
            d.id = 'liveness-debug';
            d.style.position = 'fixed';
            d.style.right = '12px';
            d.style.bottom = '12px';
            d.style.zIndex = 99999;
            d.style.minWidth = '260px';
            d.style.maxWidth = '380px';
            d.style.background = 'rgba(0,0,0,0.75)';
            d.style.color = '#fff';
            d.style.fontSize = '13px';
            d.style.padding = '10px';
            d.style.borderRadius = '8px';
            d.style.fontFamily = 'system-ui, -apple-system, "Helvetica Neue", Arial';
            d.innerHTML = '<strong>Liveness Debug</strong><div id="liveness-debug-body" style="margin-top:8px;line-height:1.3;"></div>';
            document.body.appendChild(d);
            return d;
        };

        const updateDebugPanel = (obj) => {
            const panel = ensureDebugPanel();
            const body = document.getElementById('liveness-debug-body');
            if (!body) return;
            const rows = [];
            const push = (k, v) => rows.push(`<div><strong>${k}:</strong> ${v}</div>`);
            if (obj.instruction) push('Instruction', obj.instruction);
            if (typeof obj.isLive !== 'undefined') push('isLive', obj.isLive ? '✅' : '❌');
            if (typeof obj.horizontalMovement !== 'undefined') push('horizontalMovement', Math.round(obj.horizontalMovement));
            if (typeof obj.sizeVariation !== 'undefined') push('sizeVariation', Math.round(obj.sizeVariation));
            if (typeof obj.noseRange !== 'undefined') push('noseRange', obj.noseRange.toFixed(3));
            if (typeof obj.eyeDistRange !== 'undefined') push('eyeDistRange', Math.round(obj.eyeDistRange));
            if (typeof obj.bgCorr !== 'undefined') push('bgCorr', obj.bgCorr.toFixed(3));
            if (obj.reason) push('Reason', obj.reason);
            body.innerHTML = rows.join('');
        };

        // Head rotation detection: Track face position changes to verify liveness
        // Photos cannot change position, real faces can move/rotate
        const detectHeadRotation = async () => {
            console.log('Starting head rotation detection...');
            updateDebugPanel({ instruction: '請依指示自然轉動頭部：向左→向右→點頭' });
            
            const measurements = [];
            let lastFace = null;
            
            // Collect face position measurements for 4 seconds
            const positions = await waitForFrames(4000, 150, async () => {
                try {
                    // draw current frame to main canvas if available (for background sampling)
                    let bgLum = null;
                    try {
                        if (canvas && canvas.value && canvas.value.getContext) {
                            const ctxMain = canvas.value.getContext('2d');
                            ctxMain.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);
                            // sample small patches (top-left and top-right)
                            const w = Math.max(8, Math.floor(canvas.value.width * 0.08));
                            const h = Math.max(8, Math.floor(canvas.value.height * 0.08));
                            const p1 = ctxMain.getImageData(4, 4, w, h).data;
                            const p2 = ctxMain.getImageData(canvas.value.width - 4 - w, 4, w, h).data;
                            const avgLum = (arr) => {
                                let s = 0, cnt = 0;
                                for (let i = 0; i < arr.length; i += 4) {
                                    const r = arr[i], g = arr[i + 1], b = arr[i + 2];
                                    s += 0.299 * r + 0.587 * g + 0.114 * b;
                                    cnt++;
                                }
                                return s / cnt;
                            };
                            bgLum = (avgLum(p1) + avgLum(p2)) / 2;
                        }
                    } catch (bgErr) {
                        bgLum = null;
                    }

                    const face = await detectFacesInFrame().catch(() => null);
                    if (!face || !face.boundingBox) return null;
                    
                    const bb = face.boundingBox;
                    const centerX = bb.x + bb.width / 2;
                    const centerY = bb.y + bb.height / 2;
                    const width = bb.width;
                    const height = bb.height;

                    // landmarks: prefer BlazeFace raw landmarks if available
                    let noseRelX = null, noseRelY = null, eyeDistance = null;
                    try {
                        const raw = face.raw || {};
                        const lms = raw.landmarks || raw.landmark || null;
                        if (Array.isArray(lms) && lms.length) {
                            // BlazeFace: landmarks order [rightEye, leftEye, nose, mouthRight, mouthLeft]
                            const nose = lms[2] || lms[0];
                            const leftEye = lms[1] || lms[0];
                            const rightEye = lms[0] || lms[1];
                            noseRelX = (nose[0] - bb.x) / bb.width;
                            noseRelY = (nose[1] - bb.y) / bb.height;
                            eyeDistance = Math.hypot((leftEye[0] - rightEye[0]), (leftEye[1] - rightEye[1]));
                        }
                    } catch (lmErr) {
                        // ignore
                    }

                    measurements.push({ centerX, centerY, width, height, noseRelX, noseRelY, eyeDistance, bgLum });
                    lastFace = face;
                    
                    return { centerX, centerY, width, height, noseRelX, noseRelY, eyeDistance, bgLum };
                } catch (e) {
                    return null;
                }
            });
            
            const validPositions = positions.filter(p => p !== null);
            
            if (validPositions.length < 5) {
                updateDebugPanel({ isLive: false, reason: '追蹤樣本太少，請將臉部置中並再試一次' });
                return { success: false, reason: '追蹤失敗' };
            }
            
            // Analyze position changes with smoothing to reduce noise
            const rawCenterX = validPositions.map(p => p.centerX);
            const rawCenterY = validPositions.map(p => p.centerY);
            const widthValues = validPositions.map(p => p.width);

            // Simple moving average smoothing (window=3)
            const smooth = (arr) => {
                if (arr.length < 3) return arr.slice();
                const out = [];
                for (let i = 0; i < arr.length; i++) {
                    const a = arr[i - 1] ?? arr[i];
                    const b = arr[i];
                    const c = arr[i + 1] ?? arr[i];
                    out.push((a + b + c) / 3);
                }
                return out;
            };

            const centerXValues = smooth(rawCenterX);
            const centerYValues = smooth(rawCenterY);
            
            const minX = Math.min(...centerXValues);
            const maxX = Math.max(...centerXValues);
            const minY = Math.min(...centerYValues);
            const maxY = Math.max(...centerYValues);
            const minW = Math.min(...widthValues);
            const maxW = Math.max(...widthValues);
            
            const horizontalMovement = maxX - minX;
            const verticalMovement = maxY - minY;
            const sizeVariation = maxW - minW;
            
            // Analyze motion smoothness to distinguish real head rotation from phone shaking
            const xDeltas = [];
            const yDeltas = [];
            for (let i = 1; i < centerXValues.length; i++) {
                xDeltas.push(centerXValues[i] - centerXValues[i - 1]);
                yDeltas.push(centerYValues[i] - centerYValues[i - 1]);
            }

            // Compute variance of deltas (how much acceleration/changes)
            const calcVar = (arr) => {
                if (!arr.length) return 0;
                const mean = arr.reduce((a, b) => a + b, 0) / arr.length;
                return arr.reduce((s, d) => s + Math.pow(d - mean, 2), 0) / arr.length;
            };
            const xDeltaVar = calcVar(xDeltas);
            const yDeltaVar = calcVar(yDeltas);

            // Count meaningful sign changes in deltas (ignore tiny deltas as noise)
            let xSignChanges = 0, ySignChanges = 0;
            const MIN_DELTA_TO_COUNT = 2; // px
            for (let i = 1; i < xDeltas.length; i++) {
                const a = Math.abs(xDeltas[i - 1]) > MIN_DELTA_TO_COUNT ? Math.sign(xDeltas[i - 1]) : 0;
                const b = Math.abs(xDeltas[i]) > MIN_DELTA_TO_COUNT ? Math.sign(xDeltas[i]) : 0;
                if (a !== 0 && b !== 0 && a !== b) xSignChanges++;

                const ya = Math.abs(yDeltas[i - 1]) > MIN_DELTA_TO_COUNT ? Math.sign(yDeltas[i - 1]) : 0;
                const yb = Math.abs(yDeltas[i]) > MIN_DELTA_TO_COUNT ? Math.sign(yDeltas[i]) : 0;
                if (ya !== 0 && yb !== 0 && ya !== yb) ySignChanges++;
            }

            console.log('Head motion analysis:', {
                horizontalMovement,
                verticalMovement,
                sizeVariation,
                xDeltaVar,
                yDeltaVar,
                xSignChanges,
                ySignChanges,
                measurements: validPositions.length
            });

            // Landmark-based analysis
            const noseRelValues = validPositions.map(p => typeof p.noseRelX === 'number' ? p.noseRelX : null).filter(v => v !== null);
            const eyeDistValues = validPositions.map(p => typeof p.eyeDistance === 'number' ? p.eyeDistance : null).filter(v => v !== null);
            const bgLums = validPositions.map(p => typeof p.bgLum === 'number' ? p.bgLum : null).filter(v => v !== null);

            const range = (arr) => arr.length ? Math.max(...arr) - Math.min(...arr) : 0;
            const noseRange = range(noseRelValues);
            const eyeDistRange = range(eyeDistValues);

            // Simple Pearson correlation between face center X and background luminance to detect camera motion
            const pearson = (a, b) => {
                if (!a.length || a.length !== b.length) return 0;
                const n = a.length;
                const meanA = a.reduce((s, v) => s + v, 0) / n;
                const meanB = b.reduce((s, v) => s + v, 0) / n;
                let num = 0, denA = 0, denB = 0;
                for (let i = 0; i < n; i++) {
                    const da = a[i] - meanA;
                    const db = b[i] - meanB;
                    num += da * db;
                    denA += da * da;
                    denB += db * db;
                }
                const den = Math.sqrt(denA * denB);
                return den ? num / den : 0;
            };

            let cameraMotion = false;
            try {
                if (bgLums.length >= 3) {
                    // align lengths by trimming to shortest (bgLums likely same length as center arrays)
                    const a = centerXValues.slice(-bgLums.length);
                    const b = bgLums.slice(-a.length);
                    const corr = pearson(a, b);
                    // background luminance sway indicates camera movement or scene change
                    const bgVar = calcVar(b);
                    cameraMotion = Math.abs(corr) > 0.55 && bgVar > 1; // empirical
                    console.log('Background-face correlation:', { corr, bgVar, cameraMotion });
                }
            } catch (e) {
                cameraMotion = false;
            }

            // Determine thresholds (more permissive):
            const hasHorizontalMovement = horizontalMovement > 18; // px
            const hasVerticalMovement = verticalMovement > 12; // px
            const hasSizeChange = sizeVariation > 8; // px

            // Shake detection: require larger variance to call "chaotic"
            const isTooChaotic = xDeltaVar > 1000 || yDeltaVar > 1000; // adjusted
            // Too many reversals indicates jitter; ignore small reversals
            const tooManyReversals = (xSignChanges > 8 && horizontalMovement > 0) || (ySignChanges > 8 && verticalMovement > 0);

            // Landmark evidence for real head rotation: nose relative X/Y should move within bounding box
            const noseEvidence = noseRange > 0.05; // fraction of bb width
            // Eye distance change can indicate turning toward/away from camera
            const eyeEvidence = eyeDistRange > 4; // pixels absolute change

            // Accept if landmark evidence present and movement coherent and background not moving
            const coherentMovement = (noseEvidence || eyeEvidence) && (hasHorizontalMovement || hasVerticalMovement) && !isTooChaotic && !tooManyReversals && !cameraMotion && validPositions.length >= 5;
            // Fallback: when landmarks missing, require larger absolute movement and low chaos and background stable
            const fallbackMovement = (!noseEvidence && horizontalMovement > 42 && xDeltaVar < 3000 && !cameraMotion && hasSizeChange);
            const isLive = (coherentMovement || fallbackMovement) && (hasSizeChange || horizontalMovement > 30);
            
            if (isLive) {
                console.log('✓ Head rotation detected - LIVE source confirmed');
                updateDebugPanel({ isLive: true, horizontalMovement, sizeVariation, noseRange, eyeDistRange, bgCorr: (cameraMotion ? 1 : 0) });
                return { success: true, reason: '檢測到真實頭部運動' };
            } else {
                let reason = '無頭部運動 - 可能是靜止照片';
                if (isTooChaotic) reason = '運動過於混亂 - 可能是手機搖晃';
                if (tooManyReversals) reason = '方向變化過於頻繁 - 可能是手機抖動';
                updateDebugPanel({ isLive: false, reason, horizontalMovement, sizeVariation, noseRange, eyeDistRange, bgCorr: (cameraMotion ? 1 : 0) });
                return { success: false, reason: reason };
            }
        };

        // Intelligent blink detection: Analyze waveform to distinguish real blinks from noise/photos
        const detectBlinkWaveform = (brightnessSamples) => {
            const data = brightnessSamples.filter(v => typeof v === 'number');
            if (data.length < 5) return { hasBlink: false, peaks: 0, reason: '樣本太少' };
            
            // Compute first derivative (rate of change)
            const derivatives = [];
            for (let i = 1; i < data.length; i++) {
                derivatives.push(data[i] - data[i - 1]);
            }
            
            // Find peaks: where derivative changes from positive to negative
            const peaks = [];
            for (let i = 1; i < derivatives.length; i++) {
                // Relaxed peak detection: threshold reduced from 1/-1 to 0.5/-0.5
                if (derivatives[i - 1] > 0.5 && derivatives[i] < -0.5) {
                    // Clear transition from increasing to decreasing (peak)
                    peaks.push(i);
                }
            }
            
            // Real blink should have 1-2 peaks (one opening after closing, or one closing peak)
            const minVal = Math.min(...data);
            const maxVal = Math.max(...data);
            const range = maxVal - minVal;
            
            console.log('Blink waveform analysis:', {
                samples: data.length,
                peaks: peaks.length,
                range: range,
                min: minVal,
                max: maxVal,
                derivatives: derivatives.slice(0, 5)
            });
            
            // Photo characteristics: very flat (range < 2) or random noise (peaks > 5)
            // Real blink: range >= 2 AND peaks <= 4 (some liveness with face upper region)
            if (range < 2) {
                return { hasBlink: false, peaks: peaks.length, reason: '❌ 亮度幾乎無變化\n這是靜止照片的特徵 - 無法使用照片拍攝' };
            }
            
            if (peaks.length > 5) {
                return { hasBlink: false, peaks: peaks.length, reason: '❌ 亮度波動異常\n檢測到照片、螢幕或低品質圖像特徵' };
            }
            
            // Real blink typically shows clear monotonic rise/fall or one peak
            // Relaxed: range >= 2 to allow for subtle blinks and face region dynamics
            if (range >= 2 && peaks.length <= 4) {
                return { hasBlink: true, peaks: peaks.length, reason: '檢測到真實眨眼波形' };
            }
            
            return { hasBlink: false, peaks: peaks.length, reason: '❌ 波形不符合活體驗證\n💡 提示：請勿使用照片或預錄視頻\n請使用真實攝像頭直播拍攝' };
        };

        const checkObstacleAndLiveness = async (face) => {
            // sample a normalized face patch
            const patch = sampleFacePatch(face, 128);
            const skinRatioLower = computeSkinRatioRegion(patch, 'lower');
            const skinRatioUpper = computeSkinRatioRegion(patch, 'upper');
            console.log('skinRatio lower:', skinRatioLower, 'upper:', skinRatioUpper);

            // ========== STEP 1: Occlusion Detection ==========
            if (skinRatioLower < 0.6) {
                alert('❌ 檢測到口罩或下方遮擋物。\n請移除口罩/圍巾以便系統讀取真正的臉部肌膚。');
                return false;
            }
            if (skinRatioUpper < 0.6) {
                alert('❌ 檢測到瀏海、眼鏡或眼部遮擋。\n請撥開頭髮或移除眼部遮擋物再重試。');
                return false;
            }
            console.log('✓ Step 1 passed: No occlusions detected');

            // ========== STEP 2: Head Rotation Detection (Liveness) ==========
            // Much more reliable than blink detection - photos cannot rotate
            const rotationResult = await detectHeadRotation();
            
            if (!rotationResult.success) {
                console.log('Head rotation check failed:', rotationResult.reason);
                return false;
            }
            
            console.log('✓ Step 2 passed: Head rotation detected (LIVE source)');

            // ========== STEP 3: Laplacian Variance (Texture) ==========
            // Screen/Moiré detection only (removed overly strict lower bound)
            const lapVar = computeLaplacianVariance(patch);
            console.log('laplacian variance:', lapVar);
            
            if (lapVar > 600) {
                // High variance could be live skin OR screen pattern
                // Need additional check via FFT for screen/Moiré patterns
                const moireScore = detectScreenMoireViaFFT(patch);
                console.log('moire FFT score:', moireScore);
                
                if (moireScore > 5000) {
                    alert('❌ 偵測到螢幕重複圖案特徵（Moiré）。\n這可能是翻拍照片或屏幕錄影。請勿翻拍，使用真實臉部。');
                    return false;
                }
            }
            // Note: Removed overly strict lower bound check (lapVar < 200)
            // Real video can have low variance in low-light or high motion blur
            // Blink detection (Step 2) and coherence check (Step 4) are sufficient
            console.log('✓ Step 3 passed: Texture analysis OK (lapVar:', lapVar, ')');

            // ========== STEP 4: Skin Texture Coherence ==========
            const coherenceScore = analyzeSkinTextureCoherence(patch);
            console.log('skin texture coherence:', coherenceScore);
            
            if (coherenceScore < 50) {
                updateDebugPanel({ isLive: false, reason: '皮膚紋理不自然，可能是照片或低品質圖像。請使用高質量鏡頭並確保光線充足。' });
                return false;
            }
            console.log('✓ Step 4 passed: Skin texture coherent (score:', coherenceScore, ')');

            // All checks passed
            console.log('✓✓✓ ALL LIVENESS CHECKS PASSED ✓✓✓');
            updateDebugPanel({ isLive: true, instruction: '活體驗證通過' });
            return true;
        };

        // Final verification: Simple confirmation
        // Head rotation already verified in STEP 2, so this is just a quick confirmation
        const awaitUserChallenge = async (face) => {
            console.log('=== FINAL CONFIRMATION ===');
            
            // Quick verification: Face is still detected
            try {
                const f = await detectFacesInFrame().catch(() => null);
                if (!f || !f.boundingBox) {
                    updateDebugPanel({ isLive: false, reason: '臉部檢測失敗，請確保臉部仍在鏡頭內' });
                    return false;
                }
            } catch (e) {
                return false;
            }
            
            console.log('✓ Final confirmation: User still present');
            return true;
        };

        const analyzeSkinFromImage = async () => {
            const cheekSample = sampleCheekSkinTone();

            if (!cheekSample) {
                alert('未檢測到足夠的膚色像素，請調整角度或光線');
                return;
            }

            const preferredFamily = getToneGuessFamily();
            const resolvedTone = selectToneByFamilyPreference(cheekSample, preferredFamily) || {
                toneName: '未知膚色',
                hex: rgbToHex(cheekSample.r, cheekSample.g, cheekSample.b),
                rgb: cheekSample
            };

            skinCoordinate.value = {
                type: resolvedTone.toneName,
                rgb: `${resolvedTone.rgb.r}, ${resolvedTone.rgb.g}, ${resolvedTone.rgb.b}`,
                hex: resolvedTone.hex,
                rawRgb: cheekSample,
                toneGuess: toneGuess.value || ''
            };
            skinTone.value = skinCoordinate.value.type;

            toneFusionNote.value = preferredFamily
                ? `已將臉頰最亮膚色與前面膚色問卷（${toneGuess.value || '未判定'}）融合後作為初始膚色。`
                : '已將臉頰最亮膚色作為初始膚色。';

            currentStep.value = 4;
            await analyzeWithGroq();
        };

        const mapProduct = (item, index) => {
            // Support both old naming and new schema (p_id, brand, name, category, purpose)
            const brand = item.brand || item.Brand || '通用';
            const productName = item.productName || item.ProductName || item.name || '推薦產品';
            const category = item.category || item.Category || '';
            const purpose = item.purpose || item.Purpose || '';
            const id = item.p_id || item.ProductID || item.productId || item.id || index + 1;
            
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
                too_dark: '偏暗',
                too_dry: '太乾',
                too_oily: '太油'
            };
            return mapping[type] || type || '未標記';
        };

        const extractDetectedLab = () => {
            const raw = skinCoordinate.value?.rawRgb;
            if (!raw) {
                return null;
            }

            // Approximate LAB-like tuple from current RGB for backend learning payload.
            const L = (0.2126 * raw.r + 0.7152 * raw.g + 0.0722 * raw.b) / 2.55;
            return {
                L: Number(L.toFixed(2)),
                a: Number(((raw.r - raw.g) / 2).toFixed(2)),
                b: Number(((raw.g - raw.b) / 2).toFixed(2))
            };
        };

        const recordProductClick = async (productId) => {
            if (!productId) {
                return;
            }

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
                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.error) {
                    return;
                }
                feedbackHistory.value = Array.isArray(result.history) ? result.history : [];
            } catch (error) {
                console.warn('loadFeedbackHistory failed:', error);
            }
        };

        const submitProductFeedback = async (product, feedbackType) => {
            try {
                const productId = product?.id ?? product?.p_id ?? product?.ProductID ?? product?.productId;
                if (!productId) {
                    alert('找不到產品編號，無法送出回饋');
                    return;
                }

                await recordProductClick(productId);

                const response = await fetch('./recordProductFeedback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        productId: String(productId),
                        feedbackType,
                        detectedLab: extractDetectedLab()
                    })
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.error) {
                    throw new Error(result.message || '回饋提交失敗');
                }

                const weights = result.weights || {};
                const summary = `目前 LAB 權重：L=${weights.L ?? '-'} / a=${weights.a ?? '-'} / b=${weights.b ?? '-'}`;
                alert(`${result.message || '回饋已儲存'}\n${summary}`);
                await loadFeedbackHistory();
            } catch (error) {
                console.error('submitProductFeedback failed:', error);
                alert(`回饋提交失敗：${error.message}`);
            }
        };

        const fetchIngredientAdvice = async (skinType) => {
            if (!skinType) {
                ingredientAdvice.value = null;
                return;
            }

            ingredientLoading.value = true;
            try {
                const response = await fetch('./getIngredientAdvice.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ skinType })
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.error) {
                    throw new Error(result.message || '成分分析失敗');
                }

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

        const analyzeWithGroq = async () => {
            isAnalyzing.value = true;
            try {
                const toneGuessFamily = getToneGuessFamily();
                const cameraToneBeforeGroq = skinCoordinate.value ? { ...skinCoordinate.value } : null;
                const imageBase64 = canvas.value.toDataURL('image/jpeg', 0.9);
                const response = await fetch('./analyzeSkin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        imageBase64,
                        toneGuess: toneGuess.value || '',
                        toneQuizAnswers: toneQuizAnswers.value,
                        userPreference: makeupFinish.value || '霧面',
                        makeupPreference: {
                            finish: makeupFinish.value || '霧面',
                            style: makeupStyle.value || '日常通勤'
                        }
                    })
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok || result.error) {
                    throw new Error(result.message || 'Groq 分析失敗');
                }

                const analysis = result.analysis || {};
                const nearestSkinTone = result.nearestSkinTone || {};
                const makeupPreference = result.makeupPreference || {
                    finish: makeupFinish.value || '霧面',
                    style: makeupStyle.value || '日常通勤'
                };

                skinTypeResult.value = analysis.skin_type || '';
                skinTypeSecondary.value = analysis.secondary_skin_type || '';
                skinFeatures.value = analysis.features || null;
                confidenceScore.value = typeof analysis.confidence_score === 'number'
                    ? analysis.confidence_score.toFixed(2)
                    : null;

                const cameraBaseRgb = cameraToneBeforeGroq?.rawRgb || skinCoordinate.value?.rawRgb || null;
                const matchedCameraTone = cameraBaseRgb
                    ? selectToneByFamilyPreference(cameraBaseRgb, toneGuessFamily)
                    : null;

                if (nearestSkinTone.toneName) {
                    const matchedTone = skinTonesData.value.find(t => t.toneName === nearestSkinTone.toneName);
                    const toneRgb = matchedTone?.rgb || hexToRgb(nearestSkinTone.hex || matchedTone?.hex || '');
                    const fusedTone = matchedCameraTone || (toneRgb ? {
                        toneName: nearestSkinTone.toneName,
                        hex: nearestSkinTone.hex || matchedTone?.hex || (cameraToneBeforeGroq?.hex || '-'),
                        rgb: toneRgb
                    } : nearestSkinTone);

                    skinCoordinate.value = {
                        type: fusedTone.toneName || nearestSkinTone.toneName,
                        rgb: fusedTone.rgb ? `${fusedTone.rgb.r}, ${fusedTone.rgb.g}, ${fusedTone.rgb.b}` : (cameraToneBeforeGroq?.rgb || '-'),
                        hex: fusedTone.hex || nearestSkinTone.hex || matchedTone?.hex || (cameraToneBeforeGroq?.hex || '-'),
                        rawRgb: cameraToneBeforeGroq?.rawRgb || skinCoordinate.value?.rawRgb || null,
                        toneGuess: toneGuess.value || ''
                    };
                    updateToneMismatchWarning(skinCoordinate.value.type, '相機膚色分析');
                    skinTone.value = skinCoordinate.value.type;
                    toneFusionNote.value = toneGuessFamily
                        ? `最終膚色已綜合：相機臉頰採樣 × ${toneGuess.value || '未判定'}，優先保留同調性。`
                        : '最終膚色已綜合相機臉頰採樣結果。';
                } else if (matchedCameraTone) {
                    skinCoordinate.value = {
                        type: matchedCameraTone.toneName,
                        rgb: `${matchedCameraTone.rgb.r}, ${matchedCameraTone.rgb.g}, ${matchedCameraTone.rgb.b}`,
                        hex: matchedCameraTone.hex || (cameraToneBeforeGroq?.hex || '-'),
                        rawRgb: cameraToneBeforeGroq?.rawRgb || skinCoordinate.value?.rawRgb || null,
                        toneGuess: toneGuess.value || ''
                    };
                    updateToneMismatchWarning(skinCoordinate.value.type, '相機膚色分析');
                    skinTone.value = skinCoordinate.value.type;
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
                const fused = getCalibratedSkinType(baseSkinType, Number(analysis.confidence_score ?? 0.5));
                skinTypeResult.value = fused.finalType;
                skinTypeSecondary.value = fused.secondaryType || skinTypeSecondary.value || '';
                manualSkinType.value = fused.finalType;
                needsRetest.value = fused.needsRetest;
                consistencyScoreValue.value = fused.consistencyScore.toFixed(2);
                fusionNote.value = `綜合判定：AI(${baseSkinType || '未判定'}) × ${Math.round(fused.aiWeight * 100)}% + 問卷 × ${Math.round(fused.quizWeight * 100)}%` + (fused.secondaryType ? `，${fused.secondaryType} 已獨立標註` : '');
                makeupPreferenceNote.value = `妝感偏好：${makeupPreference.finish || '霧面'}｜妝容風格：${makeupPreference.style || '日常通勤'}`;
                retestMessage.value = fused.needsRetest
                    ? '哎呀！偵測到您的描述有些矛盾，為了提供最精準的底妝建議，要不要重新確認一下膚況或重新拍攝照片呢？'
                    : (fused.isOutlier ? '問卷結果與 AI 視覺特徵跨類別衝突，已標記為異常資料（Outlier）。' : '');
                makeupFinish.value = makeupPreference.finish || makeupFinish.value;
                makeupStyle.value = makeupPreference.style || makeupStyle.value;

                if (fused.blockRecommendation) {
                    recommendations.value = [];
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

        // 膚色檢測函數
        const isSkinColor = (r, g, b) => {
            const y = 0.299 * r + 0.587 * g + 0.114 * b;
            const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b;
            const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b;
            return y > 80 && cb > 85 && cb < 135 && cr > 135 && cr < 180;
        };

        // Multi-step manual selector methods - NEW LOGIC (Base + Depth + Hue Bias)
        
        const startManualSelector = () => {
            showManualSelector.value = true;
            manualSelectorStep.value = 1;
            selectedBase.value = '';
            selectedDepth.value = '';
            selectedHueBias.value = '';
            matchedFinalTone.value = null;
        };

        const closeManualSelector = () => {
            showManualSelector.value = false;
            manualSelectorStep.value = 0;
            selectedBase.value = '';
            selectedDepth.value = '';
            selectedHueBias.value = '';
            matchedFinalTone.value = null;
        };

        const selectBase = (base) => {
            selectedBase.value = base;
            manualSelectorStep.value = 2; // Move to depth selection
        };

        const selectDepth = (depth) => {
            selectedDepth.value = depth;
            manualSelectorStep.value = 3; // Move to hue bias selection
        };

        const selectHueBias = (bias) => {
            selectedHueBias.value = bias;
            // Auto-find matching tone based on matching matrix
            findMatchingTone(selectedBase.value, selectedDepth.value, bias);
            manualSelectorStep.value = 4; // Move to confirmation
        };

        // Matching logic: Base + Depth + HueBias -> Final tone name
        const findMatchingTone = (base, depth, hueBias) => {
            // Build expected tone name based on matrix logic
            let toneName = '';
            let searchTerms = [];
            
            if (base === 'A') { // 暖/黃
                if (hueBias === 'α') { // 偏紅暖
                    toneName = `偏紅暖${depth}白`;
                    searchTerms = ['偏紅', '暖', depth, '白'];
                } else if (hueBias === 'β' || hueBias === 'γ') { // β純淨或γ溫暖都歸黃系
                    toneName = `黃${depth}白`;
                    searchTerms = ['黃', depth, '白'];
                }
            } else if (base === 'B') { // 冷/粉
                if (hueBias === 'α') { // 偏紅冷
                    toneName = `偏紅冷${depth}白`;
                    searchTerms = ['偏紅', '冷', depth, '白'];
                } else if (hueBias === 'β') { // 純淨冷
                    toneName = `粉${depth}白`;
                    searchTerms = ['粉', depth, '白'];
                } else if (hueBias === 'γ') { // 溫暖（但在冷基礎下還是粉系）
                    toneName = `粉${depth}白`;
                    searchTerms = ['粉', depth, '白'];
                }
            } else if (base === 'C') { // 中性
                if (hueBias === 'α') { // 泛紅 -> 偏暖
                    toneName = `中性暖${depth}白`;
                    searchTerms = ['中性', '暖', depth, '白'];
                } else if (hueBias === 'β') { // 純淨 -> 偏冷
                    toneName = `中性冷${depth}白`;
                    searchTerms = ['中性', '冷', depth, '白'];
                } else if (hueBias === 'γ') { // 溫暖 -> 偏暖
                    toneName = `中性暖${depth}白`;
                    searchTerms = ['中性', '暖', depth, '白'];
                }
            } else if (base === 'D') { // 橄欖
                toneName = `橄欖${depth}白`;
                searchTerms = ['橄欖', depth, '白'];
            }
            
            console.log(`Finding tone: base=${base}, depth=${depth}, bias=${hueBias}, expected toneName=${toneName}`);
            console.log(`skinTonesData loaded: ${skinTonesData.value && skinTonesData.value.length > 0}`);
            
            // Try exact match first
            let matching = null;
            if (skinTonesData.value && skinTonesData.value.length > 0) {
                matching = skinTonesData.value.find(tone => 
                    tone.toneName === toneName
                );
                console.log(`Exact match result: ${matching ? matching.toneName : 'not found'}`);
            }
            
            // If exact match fails, try finding by search terms (more flexible)
            if (!matching && searchTerms.length > 0 && skinTonesData.value && skinTonesData.value.length > 0) {
                matching = skinTonesData.value.find(tone => {
                    // Must contain all search terms except '白'
                    const hasAllTerms = searchTerms.slice(0, -1).every(term => 
                        tone.toneName.includes(term)
                    );
                    return hasAllTerms;
                });
                console.log(`Flexible match result: ${matching ? matching.toneName : 'not found'}`);
            }
            
            // If still no match, search more loosely by primary term
            if (!matching && searchTerms.length > 0 && skinTonesData.value && skinTonesData.value.length > 0) {
                const primaryTerm = searchTerms[0];
                matching = skinTonesData.value.find(tone => 
                    tone.toneName.includes(primaryTerm)
                );
                console.log(`Loose match by '${primaryTerm}': ${matching ? matching.toneName : 'not found'}`);
            }
            
            if (matching) {
                console.log(`Final match: ${matching.toneName} (${matching.hex})`);
            } else {
                console.log(`No match found, using fallback`);
            }
            
            matchedFinalTone.value = matching || {
                toneName: toneName,
                hex: '#FFB6D9', // Fallback pink color for 粉 (pink)
                rgb: { r: 255, g: 182, b: 217 }
            };
        };

        const confirmManualToneSelection = async () => {
            if (!matchedFinalTone.value || !matchedFinalTone.value.toneName) {
                alert('請完成所有步驟以選擇膚色');
                return;
            }
            
            const tone = matchedFinalTone.value;
            skinTone.value = tone.toneName;
            skinCoordinate.value = {
                type: tone.toneName,
                rgb: tone.rgb ? `${tone.rgb.r}, ${tone.rgb.g}, ${tone.rgb.b}` : '',
                hex: tone.hex || '',
                rawRgb: tone.rgb || null,
                toneGuess: toneGuess.value || ''
            };
            updateToneMismatchWarning(tone.toneName, '手動膚色選擇');
            
            const baseLabel = { 'A': '暖/黃', 'B': '冷/粉', 'C': '中性', 'D': '橄欖' }[selectedBase.value];
            const biasLabel = { 'α': '泛紅暖', 'β': '純淨冷', 'γ': '溫暖柔和' }[selectedHueBias.value];
            toneFusionNote.value = `您選擇的膚色：${tone.toneName}（${baseLabel} × ${selectedDepth.value}級 × ${biasLabel}）`;
            
            // Close manual selector and run analysis to populate recommendations & ingredient advice
            closeManualSelector();
            await analyzeSkinTone();
        };

        const getToneGuessFamily = () => {
            const guess = toneGuess.value || inferToneGuess();
            if (guess === '偏冷調') return 'cool';
            if (guess === '偏暖調') return 'warm';
            if (guess === '中性調') return 'neutral';
            return '';
        };

        const getToneFamilyFromToneName = (toneName) => {
            const name = String(toneName || '');

            if (name.includes('中性冷') || name.includes('偏紅冷') || name.includes('偏綠冷') || name.includes('粉')) {
                return 'cool';
            }

            if (name.includes('中性暖') || name.includes('偏紅暖') || name.includes('偏綠暖') || name.includes('黃')) {
                return 'warm';
            }

            if (name.includes('中一白') || name.includes('中二白') || name.includes('中三白') || name.includes('中性')) {
                return 'neutral';
            }

            if (name.includes('橄欖')) {
                return 'olive';
            }

            return '';
        };

        const updateToneMismatchWarning = (toneName, sourceLabel = '後續選擇') => {
            const guessedFamily = getToneGuessFamily();
            const selectedFamily = getToneFamilyFromToneName(toneName);

            if (!guessedFamily || !selectedFamily) {
                toneMismatchWarning.value = '';
                return false;
            }

            if (guessedFamily !== selectedFamily) {
                const familyLabelMap = {
                    cool: '偏冷調',
                    warm: '偏暖調',
                    neutral: '中性調',
                    olive: '橄欖調'
                };

                toneMismatchWarning.value = `${sourceLabel} 選到的是 ${toneName}，但第一頁問卷推測偏 ${familyLabelMap[guessedFamily] || guessedFamily}，差異有點大，建議再確認一次。`;
                alert(toneMismatchWarning.value);
                return true;
            }

            toneMismatchWarning.value = '';
            return false;
        };

        const getToneFamilyKeywords = (family) => {
            const keywordMap = {
                cool: ['冷', '藍', '紫', '粉'],
                warm: ['暖', '黃', '金', '橘'],
                neutral: ['中性', '中', '自然']
            };

            return keywordMap[family] || [];
        };

        const selectToneByFamilyPreference = (baseRgb, preferredFamily) => {
            if (!baseRgb) {
                return null;
            }

            const fallbackTone = findClosestSkinTone(baseRgb.r, baseRgb.g, baseRgb.b);
            if (!preferredFamily || !Array.isArray(skinTonesData.value) || !skinTonesData.value.length) {
                return fallbackTone;
            }

            const keywords = getToneFamilyKeywords(preferredFamily);
            const familyCandidates = skinTonesData.value
                .filter((tone) => keywords.some((keyword) => String(tone.toneName || '').includes(keyword)))
                .map((tone) => {
                    const toneRgb = tone.rgb || hexToRgb(tone.hex);
                    if (!toneRgb) {
                        return null;
                    }

                    return {
                        ...tone,
                        rgb: toneRgb,
                        distance: getRgbDistance(baseRgb, toneRgb)
                    };
                })
                .filter(Boolean)
                .sort((left, right) => left.distance - right.distance);

            return familyCandidates[0] || fallbackTone;
        };

        const getCheekSampleRegions = () => {
            if (!canvas.value) {
                return [];
            }

            const width = canvas.value.width;
            const height = canvas.value.height;
            const cheekY = Math.floor(height * 0.42);
            const cheekHeight = Math.floor(height * 0.24);
            const cheekWidth = Math.max(40, Math.floor(width * 0.18));

            return [
                {
                    x: Math.floor(width * 0.16),
                    y: cheekY,
                    width: cheekWidth,
                    height: cheekHeight
                },
                {
                    x: Math.floor(width * 0.66),
                    y: cheekY,
                    width: cheekWidth,
                    height: cheekHeight
                }
            ];
        };

        const sampleCheekSkinTone = () => {
            if (!canvas.value) {
                return null;
            }

            const ctx = canvas.value.getContext('2d');
            const imageData = ctx.getImageData(0, 0, canvas.value.width, canvas.value.height);
            const { data, width, height } = imageData;
            const samples = [];

            getCheekSampleRegions().forEach((region) => {
                const startX = Math.max(0, region.x);
                const startY = Math.max(0, region.y);
                const endX = Math.min(width, region.x + region.width);
                const endY = Math.min(height, region.y + region.height);

                for (let y = startY; y < endY; y++) {
                    for (let x = startX; x < endX; x++) {
                        const index = (y * width + x) * 4;
                        const r = data[index];
                        const g = data[index + 1];
                        const b = data[index + 2];

                        if (!isSkinColor(r, g, b)) {
                            continue;
                        }

                        const brightness = 0.299 * r + 0.587 * g + 0.114 * b;
                        if (brightness < 95 || brightness > 240) {
                            continue;
                        }

                        samples.push({ r, g, b, brightness });
                    }
                }
            });

            if (!samples.length) {
                return null;
            }

            samples.sort((left, right) => right.brightness - left.brightness);
            const selectedCount = Math.max(18, Math.floor(samples.length * 0.28));
            const selectedSamples = samples.slice(0, selectedCount);

            const totals = selectedSamples.reduce((accumulator, sample) => {
                accumulator.r += sample.r;
                accumulator.g += sample.g;
                accumulator.b += sample.b;
                return accumulator;
            }, { r: 0, g: 0, b: 0 });

            return {
                r: Math.round(totals.r / selectedSamples.length),
                g: Math.round(totals.g / selectedSamples.length),
                b: Math.round(totals.b / selectedSamples.length),
                count: selectedSamples.length
            };
        };

        const hexToRgb = (hex) => {
            if (!hex || typeof hex !== 'string') return null;
            const value = hex.replace('#', '');
            const bigint = parseInt(value, 16);
            return {
                r: (bigint >> 16) & 255,
                g: (bigint >> 8) & 255,
                b: bigint & 255
            };
        };

        const getRgbDistance = (rgb1, rgb2) => {
            return Math.sqrt(
                Math.pow(rgb1.r - rgb2.r, 2) +
                Math.pow(rgb1.g - rgb2.g, 2) +
                Math.pow(rgb1.b - rgb2.b, 2)
            );
        };

        const findClosestSkinTone = (r, g, b) => {
            if (!skinTonesData.value || skinTonesData.value.length === 0) return null;
            const targetRgb = { r, g, b };
            let closest = null;
            let minDistance = Infinity;
            for (const tone of skinTonesData.value) {
                const toneRgb = tone.rgb || hexToRgb(tone.hex);
                if (!toneRgb) continue;
                const distance = getRgbDistance(targetRgb, toneRgb);
                if (distance < minDistance) {
                    minDistance = distance;
                    closest = { ...tone, rgb: toneRgb };
                }
            }
            return closest;
        };

        // RGB轉HEX
        const rgbToHex = (r, g, b) => {
            return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
        };

        // 分類膚色
        const classifySkinTone = (r, g, b) => {
            const closest = findClosestSkinTone(r, g, b);
            return closest ? closest.toneName : '未知膚色';
        };

        // 產品推薦
        const recommendProducts = async (skinType, sensitive = false) => {
            try {
                const response = await fetch(`./getRecommendedProducts.php?skinType=${encodeURIComponent(skinType || '')}&sensitive=${sensitive ? '1' : '0'}&limit=6`);
                const data = await response.json();

                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || '無法取得推薦產品');
                }

                recommendations.value = Array.isArray(data.products) ? data.products : [];
            } catch (error) {
                console.error('Failed to load recommended products:', error);
                recommendations.value = [];
            }
        };

        const analyzeManual = async () => {
            if (!skinTone.value) {
                alert('請選擇膚色類型');
                return;
            }
            if (!manualSkinType.value) {
                alert('請選擇膚質（手動輸入）');
                return;
            }
            await analyzeSkinTone();
        };

        const analyzeSkinTone = async () => {
            const selectedTone = skinTonesData.value.find(tone => tone.toneName === skinTone.value);
            if (selectedTone) {
                const toneRgb = selectedTone.rgb || hexToRgb(selectedTone.hex) || { r: 0, g: 0, b: 0 };
                skinCoordinate.value = {
                    type: selectedTone.toneName,
                    rgb: `${toneRgb.r}, ${toneRgb.g}, ${toneRgb.b}`,
                    hex: selectedTone.hex,
                    rawRgb: toneRgb,
                    toneGuess: toneGuess.value || ''
                };
                updateToneMismatchWarning(selectedTone.toneName, '手動膚色分析');
                skinTone.value = skinCoordinate.value.type;
            } else {
                skinCoordinate.value = { type: '分析中...', rgb: '...', hex: '...' };
            }
            const fused = getCalibratedSkinType(manualSkinType.value);
            skinTypeResult.value = fused.finalType;
            skinTypeSecondary.value = manualSensitiveSkin.value ? '敏感肌' : (fused.secondaryType || '');
            manualSkinType.value = fused.finalType;
            needsRetest.value = fused.needsRetest;
            consistencyScoreValue.value = fused.consistencyScore.toFixed(2);
            fusionNote.value = `綜合判定：手動膚質(${manualSkinType.value}) × ${Math.round(fused.aiWeight * 100)}% + 問卷 × ${Math.round(fused.quizWeight * 100)}%` + (skinTypeSecondary.value ? `，${skinTypeSecondary.value} 已獨立標註` : '');
            toneFusionNote.value = toneGuess.value
                ? `手動膚色結果已參考前面的膚色問卷（${toneGuess.value}）。`
                : '手動膚色結果已完成。';
            makeupPreferenceNote.value = `妝感偏好：${makeupFinish.value}｜妝容風格：${makeupStyle.value}`;
            skinFeatures.value = null;
            confidenceScore.value = null;
            retestMessage.value = fused.needsRetest
                ? '哎呀！偵測到問卷與手動設定有些矛盾，為了提供更精準的底妝建議，建議你再確認一次膚況與問卷答案。'
                : (fused.isOutlier ? '問卷結果與手動膚質設定衝突，建議再確認一次。' : '');

            if (fused.blockRecommendation) {
                recommendations.value = [];
                ingredientAdvice.value = null;
            } else {
                await recommendProducts(skinTone.value, manualSensitiveSkin.value || skinTypeSecondary.value === '敏感肌');
                await fetchIngredientAdvice(fused.secondaryType || fused.finalType);
            }
            currentStep.value = 4;
        };

        // Create and mount Vue app
        const app = createApp({
            setup() {
                onUnmounted(() => {
                    stopCamera();
                });

                loadFeedbackHistory();

                return {
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
                    setToneQuizAnswer,
                    toneQuizOptionClass,
                    goToSkinTypeStep,
                    goToCameraStep,
                    confirmSkinTone,
                    confirmSkinType,
                    backToToneAndSkinPage,
                    goToMakeupStep,
                    quizAnswers,
                    setQuizAnswer,
                    quizOptionClass,
                    makeupOptionClass,
                    setMakeupPreference,
                    skinCoordinate,
                    skinTypeResult,
                    skinTypeSecondary,
                    toneMismatchWarning,
                    fusionNote,
                    makeupPreferenceNote,
                    needsRetest,
                    skinFeatures,
                    confidenceScore,
                    consistencyScoreValue,
                    retestMessage,
                    recommendations,
                    ingredientAdvice,
                    ingredientLoading,
                    feedbackHistory,
                    feedbackTypeText,
                    submitProductFeedback,
                    isAnalyzing,
                    faceDetectionBusy,
                    showResultModal,
                    closeResultModal,
                    startCamera,
                    stopCamera,
                    captureImage,
                    analyzeManual,
                    showManualSelector,
                    manualSelectorStep,
                    selectedBase,
                    selectedDepth,
                    selectedHueBias,
                    matchedFinalTone,
                    startManualSelector,
                    closeManualSelector,
                    selectBase,
                    selectDepth,
                    selectHueBias,
                    findMatchingTone,
                    confirmManualToneSelection
                };
            }
        });

        app.mount('#app');
    </script>

    <style>
        .fade-enter-active, .fade-leave-active { transition: opacity 0.5s; }
        .fade-enter-from, .fade-leave-to { opacity: 0; }
    </style>
</body>
</html>
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
                                <button type="button" @click="setToneQuizAnswer('t1', 'A')" :class="toneQuizOptionClass('t1', 'A')">(A) 藍紫色（偏冷）</button>
                                <button type="button" @click="setToneQuizAnswer('t1', 'B')" :class="toneQuizOptionClass('t1', 'B')">(B) 綠色（偏暖）</button>
                                <button type="button" @click="setToneQuizAnswer('t1', 'C')" :class="toneQuizOptionClass('t1', 'C')">(C) 兩種都有或看不太出來（中性）</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q2. 曬太陽後你的皮膚通常？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setToneQuizAnswer('t2', 'A')" :class="toneQuizOptionClass('t2', 'A')">(A) 先紅再黑（偏冷）</button>
                                <button type="button" @click="setToneQuizAnswer('t2', 'B')" :class="toneQuizOptionClass('t2', 'B')">(B) 很快曬黑（偏暖）</button>
                                <button type="button" @click="setToneQuizAnswer('t2', 'C')" :class="toneQuizOptionClass('t2', 'C')">(C) 看情況，兩者都會（中性）</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q3. 你戴哪種飾品比較顯氣色？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setToneQuizAnswer('t3', 'A')" :class="toneQuizOptionClass('t3', 'A')">(A) 銀色（偏冷）</button>
                                <button type="button" @click="setToneQuizAnswer('t3', 'B')" :class="toneQuizOptionClass('t3', 'B')">(B) 金色（偏暖）</button>
                                <button type="button" @click="setToneQuizAnswer('t3', 'C')" :class="toneQuizOptionClass('t3', 'C')">(C) 都可以（中性）</button>
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
                            <p class="font-semibold text-gray-800 mb-1">Q1. 洗完臉後 30 分鐘（不擦保養）感覺？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q1','A')" :class="quizOptionClass('q1','A')">(A) 全臉明顯緊繃，甚至有脫屑感</button>
                                <button type="button" @click="setQuizAnswer('q1','B')" :class="quizOptionClass('q1','B')">(B) T 區出油，但兩頰緊繃</button>
                                <button type="button" @click="setQuizAnswer('q1','C')" :class="quizOptionClass('q1','C')">(C) 全臉很舒服，不乾也不油</button>
                                <button type="button" @click="setQuizAnswer('q1','D')" :class="quizOptionClass('q1','D')">(D) 很快就全臉油光滿面</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q2. 下午帶妝常見問題？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q2','A')" :class="quizOptionClass('q2','A')">(A) 鼻頭額頭油亮且嚴重脫妝/暗沉</button>
                                <button type="button" @click="setQuizAnswer('q2','B')" :class="quizOptionClass('q2','B')">(B) 臉頰乾紋、卡粉或起皮</button>
                                <button type="button" @click="setQuizAnswer('q2','C')" :class="quizOptionClass('q2','C')">(C) 妝容維持不錯，僅微微變暗</button>
                                <button type="button" @click="setQuizAnswer('q2','D')" :class="quizOptionClass('q2','D')">(D) 容易發癢或出現紅斑</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q3. 換季/嘗試新品時皮膚反應？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q3','A')" :class="quizOptionClass('q3','A')">(A) 經常刺痛、發紅或乾癢</button>
                                <button type="button" @click="setQuizAnswer('q3','B')" :class="quizOptionClass('q3','B')">(B) 偶爾長痘但不刺痛</button>
                                <button type="button" @click="setQuizAnswer('q3','C')" :class="quizOptionClass('q3','C')">(C) 非常穩定，幾乎不鬧情緒</button>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 mb-1">Q4. 反向驗證：我嘗試新保養品幾乎都不會有反應？</p>
                            <div class="grid gap-2">
                                <button type="button" @click="setQuizAnswer('q4','A')" :class="quizOptionClass('q4','A')">(A) 同意（幾乎都很穩定）</button>
                                <button type="button" @click="setQuizAnswer('q4','B')" :class="quizOptionClass('q4','B')">(B) 普通（有時穩定、有時不穩）</button>
                                <button type="button" @click="setQuizAnswer('q4','C')" :class="quizOptionClass('q4','C')">(C) 不同意（常常會有反應）</button>
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
                        <button @click="captureImage" class="bg-indigo-600 text-white py-2.5 rounded-xl font-bold hover:bg-indigo-500 transition duration-200">
                            拍攝並分析
                        </button>
                        <button @click="stopCamera" class="bg-white text-rose-600 py-2.5 rounded-xl font-bold border border-rose-200 hover:bg-rose-50 transition duration-200">
                            停止相機
                        </button>
                    </div>
                    <canvas ref="canvas" class="hidden"></canvas>

                    <!-- 手動選擇 (備援，不想拍照時使用) -->
                    <div class="mt-4 p-4 rounded-xl bg-white/90 border border-gray-200">
                        <p class="text-sm text-gray-700 mb-2">不想拍照？你可以手動選擇膚色與膚質。</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">膚色</label>
                                <select v-model="skinTone" class="w-full p-2 border rounded">
                                    <option value="">-- 選擇膚色 --</option>
                                    <option v-for="tone in skinTonesData" :key="tone.toneName" :value="tone.toneName">{{ tone.toneName }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">膚質</label>
                                <select v-model="manualSkinType" class="w-full p-2 border rounded">
                                    <option value="">-- 選擇膚質 --</option>
                                    <option v-for="t in skinTypeOptions" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <button @click="analyzeManual" class="bg-emerald-600 text-white py-2.5 rounded-xl font-bold">跳過拍照並套用手動選擇</button>
                            <button @click="currentStep = 2" class="bg-white border py-2.5 rounded-xl">返回：膚質問答</button>
                        </div>
                    </div>

                    <button @click="currentStep = 2" class="mt-3 w-full bg-white text-violet-600 py-2.5 rounded-xl font-bold border border-violet-200 hover:bg-violet-50 transition duration-200">
                        返回：膚質問答
                    </button>
                </div>

                <div v-if="currentStep === 4" class="space-y-5">
                    <div v-if="skinCoordinate" class="p-5 border border-sky-100 rounded-2xl bg-gradient-to-br from-sky-50 to-blue-50 shadow-sm">
                        <h3 class="font-extrabold mb-3 text-gray-900">🎯 您的膚色座標</h3>
                        <div class="grid gap-2 text-sm md:text-base">
                            <p><span class="text-gray-500">類型：</span><span class="font-semibold">{{ skinCoordinate.type }}</span></p>
                            <p><span class="text-gray-500">RGB：</span><span class="font-medium">{{ skinCoordinate.rgb }}</span></p>
                            <p class="flex items-center gap-2"><span class="text-gray-500">HEX：</span><span class="font-medium">{{ skinCoordinate.hex }}</span><span class="inline-block h-4 w-4 rounded-full border border-white shadow" :style="{ backgroundColor: skinCoordinate.hex }"></span></p>
                        </div>
                    </div>

                    <div v-if="skinTypeResult" class="p-5 border border-purple-100 rounded-2xl bg-gradient-to-br from-purple-50 to-fuchsia-50 shadow-sm">
                        <h3 class="font-extrabold mb-2 text-gray-900">🧴 膚質分析結果</h3>
                        <p class="text-lg font-bold text-purple-800">{{ skinTypeResult.profile?.displayName || skinTypeResult }}</p>
                        <p v-if="fusionNote" class="text-xs text-purple-700 mt-1">{{ fusionNote }}</p>
                        <p v-if="makeupPreferenceNote" class="text-xs text-pink-700 mt-1">{{ makeupPreferenceNote }}</p>
                        <p v-if="confidenceScore !== null" class="text-sm text-gray-700 mt-2">信心分數: {{ confidenceScore }}</p>
                        <p v-if="consistencyScoreValue !== null" class="text-xs text-indigo-700 mt-1">一致性分數: {{ consistencyScoreValue }}</p>
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

    <script>
        const { createApp, ref, nextTick, onUnmounted, computed } = Vue;

        // App data
        const dataLoaded = ref(false);
        const currentStep = ref(1);
        const skinTonesData = ref([]);
        const cameraActive = ref(false);
        const skinTone = ref('');
        const manualSkinType = ref('');
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
        const quizAnswers = ref({
            q1: '',
            q2: '',
            q3: '',
            q4: ''
        });
        const skinCoordinate = ref(null);
        const skinTypeResult = ref('');
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
        const isAnalyzing = ref(false);
        const showResultModal = ref(false);
        const hasShownNaturalLightReminder = ref(false);
        const video = ref(null);
        const canvas = ref(null);
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
                q1: { A: -2, B: -1, C: 0, D: 2 },
                q2: { A: 2, B: -2, C: 0, D: 1 },
                q3: { A: -1, B: 1, C: 0 },
                q4: { A: -1, B: 0, C: 1 },
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
                A: { '乾性皮': 2.2 },
                B: { '混乾皮': 2.2 },
                C: { '中性皮': 2.2 },
                D: { '油性皮': 2.2 },
            };

            const q2Weights = {
                A: { '混油皮': 2.0, '油性皮': 1.2 },
                B: { '混乾皮': 2.0, '乾性皮': 1.2 },
                C: { '中性皮': 1.8 },
                D: { '敏感肌': 2.0 },
            };

            const q3Weights = {
                A: { '敏感肌': 2.8 },
                B: { '混油皮': 1.2, '混乾皮': 1.8, '油性皮': 0.8 },
                C: { '中性皮': 1.8 },
            };

            [q1Weights[q1], q2Weights[q2], q3Weights[q3]].forEach((weights) => {
                if (!weights) return;
                Object.entries(weights).forEach(([skinType, weight]) => {
                    if (quizScores[skinType] !== undefined) {
                        quizScores[skinType] += weight;
                    }
                });
            });

            const consistencyIssues = [];
            if (q3 === 'A' && q4 === 'A') {
                consistencyIssues.push('Q3 與 Q4 互相矛盾（敏感 vs 極穩定）');
            }
            if (q3 === 'C' && q4 === 'C') {
                consistencyIssues.push('Q3 與 Q4 互相矛盾（極穩定 vs 常常有反應）');
            }
            if (q2 === 'D' && q4 === 'A') {
                consistencyIssues.push('Q2 與 Q4 可能有矛盾（易刺激 vs 極穩定）');
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

            let finalType = '中性皮';
            let maxScore = -Infinity;
            Object.entries(combinedScores).forEach(([skinType, score]) => {
                if (score > maxScore) {
                    maxScore = score;
                    finalType = skinType;
                }
            });

            return {
                finalType,
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

            const ctx = canvas.value.getContext('2d');
            canvas.value.width = video.value.videoWidth;
            canvas.value.height = video.value.videoHeight;
            ctx.save();
            ctx.translate(canvas.value.width, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video.value, 0, 0, canvas.value.width, canvas.value.height);
            ctx.restore();

            // 進行膚色分析
            analyzeSkinFromImage();
        };

        const analyzeSkinFromImage = async () => {
            const ctx = canvas.value.getContext('2d');
            const imageData = ctx.getImageData(0, 0, canvas.value.width, canvas.value.height);
            const data = imageData.data;

            // 分析中央區域的膚色
            const centerX = Math.floor(canvas.value.width / 2);
            const centerY = Math.floor(canvas.value.height / 2);
            const sampleSize = Math.min(100, Math.floor(Math.min(canvas.value.width, canvas.value.height) / 4));

            let totalR = 0, totalG = 0, totalB = 0;
            let pixelCount = 0;

            // 採樣中央區域的像素
            for (let y = centerY - sampleSize; y < centerY + sampleSize; y++) {
                for (let x = centerX - sampleSize; x < centerX + sampleSize; x++) {
                    if (x >= 0 && x < canvas.value.width && y >= 0 && y < canvas.value.height) {
                        const index = (y * canvas.value.width + x) * 4;
                        const r = data[index];
                        const g = data[index + 1];
                        const b = data[index + 2];

                        // 簡單的膚色過濾
                        if (isSkinColor(r, g, b)) {
                            totalR += r;
                            totalG += g;
                            totalB += b;
                            pixelCount++;
                        }
                    }
                }
            }

            if (pixelCount === 0) {
                alert('未檢測到足夠的膚色像素，請調整角度或光線');
                return;
            }

            // 計算平均膚色
            const avgR = Math.round(totalR / pixelCount);
            const avgG = Math.round(totalG / pixelCount);
            const avgB = Math.round(totalB / pixelCount);

            const closestTone = findClosestSkinTone(avgR, avgG, avgB);
            const resolvedTone = closestTone || { toneName: '未知膚色', hex: rgbToHex(avgR, avgG, avgB), rgb: { r: avgR, g: avgG, b: avgB } };

            skinCoordinate.value = {
                type: resolvedTone.toneName,
                rgb: `${resolvedTone.rgb.r}, ${resolvedTone.rgb.g}, ${resolvedTone.rgb.b}`,
                hex: resolvedTone.hex
            };

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
                description: (item.recommendationReason || item.reason)
                    ? `${item.recommendationReason || item.reason}`
                    : `分類: ${category || '-'}｜用途: ${purpose || '-'}`,
                matchScore: item.matchScore ?? item.match_score ?? null
            };
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
                const imageBase64 = canvas.value.toDataURL('image/jpeg', 0.9);
                const response = await fetch('./analyzeSkin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        imageBase64,
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
                skinFeatures.value = analysis.features || null;
                confidenceScore.value = typeof analysis.confidence_score === 'number'
                    ? analysis.confidence_score.toFixed(2)
                    : null;

                if (nearestSkinTone.toneName) {
                    const matchedTone = skinTonesData.value.find(t => t.toneName === nearestSkinTone.toneName);
                    const toneRgb = matchedTone?.rgb || hexToRgb(nearestSkinTone.hex || matchedTone?.hex || '');
                    skinCoordinate.value = {
                        type: nearestSkinTone.toneName,
                        rgb: toneRgb ? `${toneRgb.r}, ${toneRgb.g}, ${toneRgb.b}` : (skinCoordinate.value?.rgb || '-'),
                        hex: nearestSkinTone.hex || matchedTone?.hex || (skinCoordinate.value?.hex || '-')
                    };
                }

                if (Array.isArray(result.products) && result.products.length > 0) {
                    recommendations.value = result.products.map(mapProduct);
                } else if (nearestSkinTone.toneName) {
                    recommendProducts(nearestSkinTone.toneName);
                } else {
                    recommendations.value = [];
                }

                const baseSkinType = manualSkinType.value || analysis.skin_type || '';
                const fused = getCalibratedSkinType(baseSkinType, Number(analysis.confidence_score ?? 0.5));
                skinTypeResult.value = fused.finalType;
                needsRetest.value = fused.needsRetest;
                consistencyScoreValue.value = fused.consistencyScore.toFixed(2);
                fusionNote.value = `綜合判定：AI(${baseSkinType || '未判定'}) × ${Math.round(fused.aiWeight * 100)}% + 問卷 × ${Math.round(fused.quizWeight * 100)}%`;
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
                    await fetchIngredientAdvice(fused.finalType);
                }

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
        const recommendProducts = (skinType) => {
            const productMap = {
                '粉一白': [{ id: 1, name: '玫瑰色調粉底', description: '適合粉一白，打造自然玫瑰氣色' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合粉白肌膚' }],
                '黃一白': [{ id: 1, name: '暖黃色調粉底', description: '適合黃一白，營造健康小麥色' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合黃白肌膚' }],
                '中一白': [{ id: 1, name: '中性色調粉底', description: '適合中一白，打造自然妝感' }, { id: 2, name: '綠色系遮瑕膏', description: '中和泛黃，提亮膚色' }],
                '橄欖一白': [{ id: 1, name: '橄欖色調粉底', description: '適合橄欖一白，展現清新活力' }, { id: 2, name: '紫色系遮瑕膏', description: '平衡暖調，均勻膚色' }],
                '粉二白': [{ id: 1, name: '粉色調粉底', description: '適合粉二白，打造溫柔氣質' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合粉白肌膚' }],
                '黃二白': [{ id: 1, name: '黃色調粉底', description: '適合黃二白，營造健康活力' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合黃白肌膚' }],
                '中二白': [{ id: 1, name: '中性色調粉底', description: '適合中二白，打造自然妝感' }, { id: 2, name: '綠色系遮瑕膏', description: '中和泛黃，提亮膚色' }],
                '橄欖二白': [{ id: 1, name: '橄欖色調粉底', description: '適合橄欖二白，展現清新活力' }, { id: 2, name: '紫色系遮瑕膏', description: '平衡暖調，均勻膚色' }],
                '粉三白': [{ id: 1, name: '粉色調粉底', description: '適合粉三白，打造溫柔氣質' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合粉白肌膚' }],
                '黃三白': [{ id: 1, name: '黃色調粉底', description: '適合黃三白，營造健康活力' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合黃白肌膚' }],
                '中三白': [{ id: 1, name: '中性色調粉底', description: '適合中三白，打造自然妝感' }, { id: 2, name: '綠色系遮瑕膏', description: '中和泛黃，提亮膚色' }],
                '橄欖三白': [{ id: 1, name: '橄欖色調粉底', description: '適合橄欖三白，展現清新活力' }, { id: 2, name: '紫色系遮瑕膏', description: '平衡暖調，均勻膚色' }],
                '偏紅冷一白': [{ id: 1, name: '冷紅色調粉底', description: '適合偏紅冷一白，打造冷冽氣質' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合冷紅肌膚' }],
                '偏紅冷二白': [{ id: 1, name: '冷紅色調粉底', description: '適合偏紅冷二白，打造冷冽氣質' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合冷紅肌膚' }],
                '偏紅暖一白': [{ id: 1, name: '暖紅色調粉底', description: '適合偏紅暖一白，營造溫暖氣息' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合暖紅肌膚' }],
                '偏紅暖二白': [{ id: 1, name: '暖紅色調粉底', description: '適合偏紅暖二白，營造溫暖氣息' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合暖紅肌膚' }],
                '中性冷一白': [{ id: 1, name: '冷中性色調粉底', description: '適合中性冷一白，打造清冷氣質' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合冷中性肌膚' }],
                '中性冷二白': [{ id: 1, name: '冷中性色調粉底', description: '適合中性冷二白，打造清冷氣質' }, { id: 2, name: '藍色系遮瑕膏', description: '中和泛紅，適合冷中性肌膚' }],
                '中性暖一白': [{ id: 1, name: '暖中性色調粉底', description: '適合中性暖一白，營造溫暖氣息' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合暖中性肌膚' }],
                '中性暖二白': [{ id: 1, name: '暖中性色調粉底', description: '適合中性暖二白，營造溫暖氣息' }, { id: 2, name: '黃色系遮瑕膏', description: '修飾暗沉，適合暖中性肌膚' }],
                '偏綠冷一白': [{ id: 1, name: '冷綠色調粉底', description: '適合偏綠冷一白，打造清新氣質' }, { id: 2, name: '粉色系遮瑕膏', description: '平衡冷綠調，均勻膚色' }],
                '偏綠冷二白': [{ id: 1, name: '冷綠色調粉底', description: '適合偏綠冷二白，打造清新氣質' }, { id: 2, name: '粉色系遮瑕膏', description: '平衡冷綠調，均勻膚色' }],
                '偏綠暖一白': [{ id: 1, name: '暖綠色調粉底', description: '適合偏綠暖一白，營造自然氣息' }, { id: 2, name: '紫色系遮瑕膏', description: '平衡暖綠調，均勻膚色' }],
                '偏綠暖二白': [{ id: 1, name: '暖綠色調粉底', description: '適合偏綠暖二白，營造自然氣息' }, { id: 2, name: '紫色系遮瑕膏', description: '平衡暖綠調，均勻膚色' }]
            };

            recommendations.value = productMap[skinType] || [
                { id: 1, name: '通用粉底液', description: '適合多種膚色，提供自然遮瑕' },
                { id: 2, name: '保濕霜', description: '補充水分，維持肌膚健康' }
            ];
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
                    hex: selectedTone.hex
                };
            } else {
                skinCoordinate.value = { type: '分析中...', rgb: '...', hex: '...' };
            }
            const fused = getCalibratedSkinType(manualSkinType.value);
            skinTypeResult.value = fused.finalType;
            needsRetest.value = fused.needsRetest;
            consistencyScoreValue.value = fused.consistencyScore.toFixed(2);
            fusionNote.value = `綜合判定：手動膚質(${manualSkinType.value}) × ${Math.round(fused.aiWeight * 100)}% + 問卷 × ${Math.round(fused.quizWeight * 100)}%`;
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
                recommendProducts(skinTone.value);
                await fetchIngredientAdvice(fused.finalType);
            }
            currentStep.value = 4;
        };

        // Create and mount Vue app
        const app = createApp({
            setup() {
                onUnmounted(() => {
                    stopCamera();
                });

                return {
                    dataLoaded,
                    currentStep,
                    skinTonesData,
                    cameraActive,
                    skinTone,
                    manualSkinType,
                    makeupFinish,
                    makeupStyle,
                    skinTypeOptions,
                    confirmedSkinTone,
                    confirmedSkinType,
                    canChooseMakeupPreference,
                    showMakeupPreference,
                    toneQuizAnswers,
                    toneGuess,
                    setToneQuizAnswer,
                    toneQuizOptionClass,
                    goToSkinTypeStep,
                    goToCameraStep,
                    confirmSkinTone,
                    confirmSkinType,
                    goToMakeupStep,
                    quizAnswers,
                    setQuizAnswer,
                    quizOptionClass,
                    makeupOptionClass,
                    setMakeupPreference,
                    skinCoordinate,
                    skinTypeResult,
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
                    isAnalyzing,
                    showResultModal,
                    closeResultModal,
                    startCamera,
                    stopCamera,
                    captureImage,
                    analyzeManual
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
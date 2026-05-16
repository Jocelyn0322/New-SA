<div v-if="currentStep === 4" class="space-y-5">

    <!-- 膚色座標 -->
    <div v-if="skinCoordinate" class="p-5 border border-sky-100 rounded-2xl bg-gradient-to-br from-sky-50 to-blue-50 shadow-sm">
        <h3 class="font-extrabold mb-3 text-gray-900">🎯 您的膚色座標</h3>
        <div class="grid gap-2 text-sm md:text-base">
            <p><span class="text-gray-500">類型：</span><span class="font-semibold">{{ skinCoordinate.type }}</span></p>
            <p><span class="text-gray-500">RGB：</span><span class="font-medium">{{ skinCoordinate.rgb }}</span></p>
            <p class="flex items-center gap-2">
                <span class="text-gray-500">HEX：</span>
                <span class="font-medium">{{ skinCoordinate.hex }}</span>
                <span class="inline-block h-4 w-4 rounded-full border border-white shadow" :style="{ backgroundColor: skinCoordinate.hex }"></span>
            </p>
            <p v-if="toneFusionNote" class="text-xs md:text-sm text-sky-700">{{ toneFusionNote }}</p>
        </div>
    </div>

    <!-- 膚質分析結果 -->
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
                    <option v-for="option in skinTypeOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                <input v-model="manualSensitiveSkin" type="checkbox" class="h-4 w-4 rounded border-purple-300 text-purple-600 focus:ring-purple-400" />
                同時標記為敏感肌
            </label>
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
                <span>目前使用：{{ manualSkinType || '尚未選擇' }}{{ manualSensitiveSkin ? ' + 敏感肌' : '' }}</span>
                <button type="button" @click="analyzeManual" class="rounded-xl bg-purple-600 px-3 py-2 font-bold text-white transition hover:bg-purple-500">套用這個膚質</button>
            </div>
        </div>
        <div v-if="toneMismatchWarning" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ toneMismatchWarning }}</div>
        <div v-if="needsRetest" class="mt-2 text-xs md:text-sm p-2.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-800">
            檢測到結果一致性偏低，建議重新確認問卷作答與分析設定（needs_retest）。
        </div>
        <div v-if="retestMessage" class="mt-2 text-xs md:text-sm p-2.5 rounded-xl border border-rose-300 bg-rose-50 text-rose-800">{{ retestMessage }}</div>
        <div v-if="skinFeatures" class="text-sm text-gray-700 mt-2 space-y-1">
            <p>T 區油光: {{ skinFeatures.t_zone_shine }}</p>
            <p>毛孔狀態: {{ skinFeatures.pore_visibility }}</p>
            <p>泛紅: {{ skinFeatures.redness ? '是' : '否' }}</p>
        </div>
    </div>

    <!-- 確認結果 -->
    <div class="p-5 md:p-6 border border-amber-100 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-extrabold text-gray-900 text-lg">確認你的分析結果</h4>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-amber-700 border border-amber-200">Confirm Step</span>
        </div>
        <div class="grid md:grid-cols-2 gap-3">
            <button type="button" @click="confirmSkinTone"
                class="rounded-2xl border p-4 text-left transition-all duration-200"
                :class="confirmedSkinTone ? 'border-emerald-300 bg-emerald-50 text-emerald-800 shadow-sm' : 'border-amber-200 bg-white text-gray-700 hover:border-amber-300 hover:bg-amber-50'">
                <p class="font-bold text-base">確認膚色</p>
                <p class="text-sm mt-1">{{ confirmedSkinTone ? '已確認目前膚色結果' : '點我確認目前的膚色判定' }}</p>
            </button>
            <button type="button" @click="confirmSkinType"
                class="rounded-2xl border p-4 text-left transition-all duration-200"
                :class="confirmedSkinType ? 'border-emerald-300 bg-emerald-50 text-emerald-800 shadow-sm' : 'border-amber-200 bg-white text-gray-700 hover:border-amber-300 hover:bg-amber-50'">
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
                <button type="button" @click="backToToneAndSkinPage"
                    class="rounded-xl px-4 py-2.5 text-sm font-bold bg-white text-amber-700 border border-amber-200 hover:bg-amber-50 transition-all duration-200">
                    返回前一頁（測膚色與膚質）
                </button>
                <button type="button" @click="goToMakeupStep"
                    :disabled="!canChooseMakeupPreference"
                    class="rounded-xl px-4 py-2.5 text-sm font-bold transition-all duration-200"
                    :class="canChooseMakeupPreference ? 'bg-gray-900 text-white hover:bg-gray-800' : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                    前往妝感偏好頁
                </button>
            </div>
        </div>
    </div>

    <!-- 妝感偏好 -->
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
            <button @click="showMakeupPreference = false" class="bg-white text-pink-600 py-2.5 rounded-xl font-bold border border-pink-200 hover:bg-pink-50 transition duration-200">返回：確認結果</button>
            <button @click="analyzeWithGroq" class="bg-pink-600 text-white py-2.5 rounded-xl font-bold hover:bg-pink-500 transition duration-200">送出偏好並完成分析</button>
        </div>
    </div>

    <!-- 推薦產品 -->
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

    <!-- 歷史回饋 -->
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

    <!-- 成分避雷 -->
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

    <!-- 分析中 -->
    <div v-if="isAnalyzing" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium animate-pulse">
        正在使用 AI 分析照片，請稍候...
    </div>

</div>

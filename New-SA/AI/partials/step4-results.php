<!-- ── Step 4：分析結果 ── -->
<div v-if="currentStep === 4" class="space-y-5">

    <!-- 膚色座標 -->
    <div v-if="skinCoordinate" class="p-5 rounded-2xl shadow-sm" style="background:linear-gradient(135deg,#6b2d3e,#c26b7c); border:1px solid #c26b7c;">
        <h3 class="font-extrabold mb-3" style="color:#fff;">🎯 您的膚色座標</h3>
        <div class="grid gap-2 text-sm md:text-base">
            <p><span style="color:rgba(255,255,255,.55);">類型：</span><span class="font-semibold" style="color:#fff;">{{ skinCoordinate.type }}</span></p>
            <p><span style="color:rgba(255,255,255,.55);">RGB：</span><span class="font-medium" style="color:#fff;">{{ skinCoordinate.rgb }}</span></p>
            <p class="flex items-center gap-2">
                <span style="color:rgba(255,255,255,.55);">HEX：</span>
                <span class="font-medium" style="color:#fff;">{{ skinCoordinate.hex }}</span>
                <span class="inline-block h-4 w-4 rounded-full shadow" :style="{ backgroundColor: skinCoordinate.hex, border: '1.5px solid rgba(255,255,255,.4)' }"></span>
            </p>
            <p v-if="toneFusionNote" class="text-xs md:text-sm" style="color:#f9cfd8;">{{ toneFusionNote }}</p>
        </div>
    </div>

    <!-- 膚質分析結果 -->
    <div class="p-5 rounded-2xl shadow-sm" style="background:linear-gradient(135deg,#fce8ec,#fdf2f4); border:1px solid #f5c6d0;">
        <h3 class="font-extrabold mb-2" style="color:#6b2d3e;">🧴 膚質分析結果</h3>
        <p class="text-lg font-bold" style="color:#3d1520;">{{ skinTypeResult.profile?.displayName || skinTypeResult || manualSkinType || '尚未判定' }}</p>
        <p class="text-sm mt-1" style="color:#6b2d3e;">
            膚質：{{ manualSkinType || skinTypeResult || '尚未判定' }}{{ manualSensitiveSkin ? ' + 敏感肌' : '' }}
            <span v-if="fusionNote || confidenceScore !== null || consistencyScoreValue !== null"
                  style="position:relative;display:inline-block;margin-left:6px;cursor:help;"
                  class="detail-hint">
                <span style="font-size:11px;color:#c09aaa;border-bottom:1px dashed #c09aaa;">詳細資訊</span>
                <span class="detail-tooltip">
                    <span v-if="fusionNote" style="display:block;">{{ fusionNote }}</span>
                    <span v-if="confidenceScore !== null" style="display:block;">信心分數：{{ confidenceScore }}</span>
                    <span v-if="consistencyScoreValue !== null" style="display:block;">一致性分數：{{ consistencyScoreValue }}</span>
                </span>
            </span>
        </p>
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

    <!-- 分析中 -->
    <div v-if="isAnalyzing" class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-medium animate-pulse">
        正在使用 AI 分析照片，請稍候...
    </div>

    <!-- 確認結果 -->
    <div class="p-5 md:p-6 border border-amber-100 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 shadow-sm">
        <h4 class="font-extrabold text-gray-900 text-lg mb-1">確認你的分析結果</h4>
        <p class="text-sm text-gray-500 mb-4">點擊右上角圓圈確認膚色與膚質，確認後才能進行下一步。</p>

        <div class="grid md:grid-cols-2 gap-3 mb-4">
            <!-- 確認膚色 -->
            <div class="rounded-2xl border p-4 transition-all duration-200 flex flex-col"
                 :class="confirmedSkinTone ? 'border-emerald-400 bg-emerald-50' : 'border-amber-300 bg-white'">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-bold text-base" :class="confirmedSkinTone ? 'text-emerald-800' : 'text-gray-700'">膚色</p>
                    <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center cursor-pointer transition-all duration-200 hover:scale-110"
                         :class="confirmedSkinTone ? 'bg-emerald-500 border-emerald-500' : 'bg-white border-gray-300 hover:border-emerald-400'"
                         @click="confirmSkinTone"
                         :title="confirmedSkinTone ? '點擊取消確認' : '點擊確認膚色'">
                        <svg v-if="confirmedSkinTone" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <!-- 目前膚色預覽 -->
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-block h-6 w-6 rounded-full border border-gray-300 shadow-sm flex-shrink-0"
                          :style="{ backgroundColor: skinCoordinate?.hex || '#e5c8c8' }"></span>
                    <span class="text-sm font-semibold text-gray-800">{{ skinCoordinate?.type || skinTone || '未選擇' }}</span>
                </div>
                <!-- 手動選膚色 -->
                <select v-model="skinTone" @change="analyzeSkinTone"
                    class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 focus:border-amber-400 focus:outline-none">
                    <option disabled value="">— 選擇其他膚色 —</option>
                    <option v-for="t in skinTonesData" :key="t.toneName" :value="t.toneName">{{ t.toneName }}</option>
                </select>
            </div>

            <!-- 確認膚質 -->
            <div class="rounded-2xl border p-4 transition-all duration-200 flex flex-col"
                 :class="confirmedSkinType ? 'border-emerald-400 bg-emerald-50' : 'border-amber-300 bg-white'">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-bold text-base" :class="confirmedSkinType ? 'text-emerald-800' : 'text-gray-700'">膚質</p>
                    <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center cursor-pointer transition-all duration-200 hover:scale-110"
                         :class="confirmedSkinType ? 'bg-emerald-500 border-emerald-500' : 'bg-white border-gray-300 hover:border-emerald-400'"
                         @click="confirmSkinType"
                         :title="confirmedSkinType ? '點擊取消確認' : '點擊確認膚質'">
                        <svg v-if="confirmedSkinType" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <!-- 目前膚質 -->
                <p class="text-sm font-semibold text-gray-800 mb-3">
                    {{ manualSkinType || skinTypeResult || '尚未選擇' }}{{ manualSensitiveSkin ? ' + 敏感肌' : '' }}
                </p>
                <!-- 手動選膚質 -->
                <select v-model="manualSkinType"
                    class="w-full rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 focus:border-amber-400 focus:outline-none mb-2">
                    <option disabled value="">— 選擇其他膚質 —</option>
                    <option v-for="option in skinTypeOptions" :key="option" :value="option">{{ option }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="manualSensitiveSkin" type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-500 focus:ring-amber-300" />
                    同時標記為敏感肌
                </label>
            </div>
        </div>

        <!-- 提示未確認 -->
        <div v-if="!canChooseMakeupPreference" class="mb-3 px-4 py-2.5 rounded-xl bg-amber-100 border border-amber-200 text-sm text-amber-800">
            ⚠️ 請先點擊上方兩個圓圈完成確認，再繼續下一步。
        </div>

        <!-- 操作列 -->
        <div class="flex flex-wrap gap-2 justify-between items-center">
            <button type="button" @click="restartFromBeginning"
                class="rounded-xl px-4 py-2.5 text-sm font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 transition-all duration-200">
                ← 重新檢測
            </button>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="goToMakeupStep"
                    :disabled="!canChooseMakeupPreference"
                    class="rounded-xl px-4 py-2.5 text-sm font-bold transition-all duration-200"
                    :class="canChooseMakeupPreference
                        ? 'bg-gray-900 text-white hover:bg-gray-800'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
                    選妝感偏好 →
                </button>
            </div>
        </div>
    </div>

</div>

<!-- ── Step 5：妝感偏好 ── -->
<div v-if="currentStep === 5" class="space-y-5">

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

    <!-- 妝感偏好 -->
    <div v-if="showMakeupPreference" class="p-5 md:p-6 border border-pink-100 rounded-2xl bg-gradient-to-br from-pink-50 to-rose-50 shadow-sm" style="position:relative;">
        <!-- 送出中 overlay -->
        <div v-if="isAnalyzing" style="position:absolute;inset:0;z-index:20;border-radius:1rem;background:rgba(253,242,244,0.88);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;">
            <svg style="width:44px;height:44px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="#f5c6d0" stroke-width="3"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="#6b2d3e" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <p style="font-size:15px;font-weight:700;color:#6b2d3e;">正在儲存，請稍候…</p>
        </div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-extrabold text-gray-900 text-lg">💋 妝感與妝容偏好</h3>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white text-pink-700 border border-pink-200">AI 偏好輸入</span>
        </div>
        <p class="text-sm text-gray-600 mb-4">先確認膚色與膚質，再選妝感會更準。</p>
        <div class="grid gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-800 mb-2">妝感</p>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="setMakeupPreference('finish', '霧面')" :class="makeupOptionClass(makeupFinish, '霧面')">霧面</button>
                    <button type="button" @click="setMakeupPreference('finish', '水光感')" :class="makeupOptionClass(makeupFinish, '水光感')">水光感</button>
                    <button type="button" @click="setMakeupPreference('finish', '自然光澤')" :class="makeupOptionClass(makeupFinish, '自然光澤')">自然光澤</button>
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
            <button @click="backToResultsPage" class="bg-white text-pink-600 py-2.5 rounded-xl font-bold border border-pink-200 hover:bg-pink-50 transition duration-200">← 返回分析結果</button>
            <button @click="finishAndSave" :disabled="isAnalyzing" class="bg-pink-600 text-white py-2.5 rounded-xl font-bold hover:bg-pink-500 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed">送出偏好並完成分析</button>
        </div>
    </div>

</div>

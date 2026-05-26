<!-- ── Step 4：分析結果 ── -->
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
            <div class="text-xs text-gray-500">
                目前使用：{{ manualSkinType || '尚未選擇' }}{{ manualSensitiveSkin ? ' + 敏感肌' : '' }}
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

    <!-- 操作列 -->
    <div class="flex justify-between items-center pt-1">
        <button type="button" @click="backToToneAndSkinPage"
            class="rounded-xl px-4 py-2.5 text-sm font-bold bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 transition-all duration-200">
            ← 重新拍照
        </button>
        <button type="button" @click="goToConfirmStep"
            class="rounded-xl px-5 py-2.5 text-sm font-bold bg-violet-600 text-white hover:bg-violet-700 transition-all duration-200 shadow-sm">
            繼續確認 →
        </button>
    </div>

</div>

<!-- ── Step 5：確認結果 ── -->
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

    <!-- 確認結果 -->
    <div class="p-5 md:p-6 border border-amber-100 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 shadow-sm">
        <div class="flex items-center justify-between mb-1">
            <h4 class="font-extrabold text-gray-900 text-lg">確認你的分析結果</h4>
        </div>
        <p class="text-sm text-gray-500 mb-4">請分別點擊下方兩個按鈕確認膚色與膚質，確認後才能進行下一步。</p>

        <div class="grid md:grid-cols-2 gap-3 mb-4">
            <!-- 確認膚色 -->
            <button type="button" @click="confirmSkinTone"
                class="rounded-2xl border p-4 text-left transition-all duration-200 relative"
                :class="confirmedSkinTone
                    ? 'border-emerald-400 bg-emerald-50 shadow-sm'
                    : 'border-amber-300 bg-white hover:border-amber-400 hover:bg-amber-50 hover:shadow-md active:scale-[0.99]'">
                <div class="absolute top-3 right-3 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all duration-200"
                     :class="confirmedSkinTone ? 'bg-emerald-500 border-emerald-500' : 'bg-white border-gray-300'">
                    <svg v-if="confirmedSkinTone" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="font-bold text-base pr-8" :class="confirmedSkinTone ? 'text-emerald-800' : 'text-gray-700'">確認膚色</p>
                <p class="text-sm mt-1" :class="confirmedSkinTone ? 'text-emerald-600' : 'text-gray-500'">
                    {{ confirmedSkinTone ? '膚色判定完成，再次點擊可取消' : '點此確認目前的膚色判定' }}
                </p>
            </button>

            <!-- 確認膚質 -->
            <button type="button" @click="confirmSkinType"
                class="rounded-2xl border p-4 text-left transition-all duration-200 relative"
                :class="confirmedSkinType
                    ? 'border-emerald-400 bg-emerald-50 shadow-sm'
                    : 'border-amber-300 bg-white hover:border-amber-400 hover:bg-amber-50 hover:shadow-md active:scale-[0.99]'">
                <div class="absolute top-3 right-3 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all duration-200"
                     :class="confirmedSkinType ? 'bg-emerald-500 border-emerald-500' : 'bg-white border-gray-300'">
                    <svg v-if="confirmedSkinType" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="font-bold text-base pr-8" :class="confirmedSkinType ? 'text-emerald-800' : 'text-gray-700'">確認膚質</p>
                <p class="text-sm mt-1" :class="confirmedSkinType ? 'text-emerald-600' : 'text-gray-500'">
                    {{ confirmedSkinType ? '膚質判定完成，再次點擊可取消' : '點此確認目前的膚質判定' }}
                </p>
            </button>
        </div>

        <!-- 提示未確認 -->
        <div v-if="!canChooseMakeupPreference" class="mb-3 px-4 py-2.5 rounded-xl bg-amber-100 border border-amber-200 text-sm text-amber-800">
            ⚠️ 請先點擊上方兩個按鈕完成確認，再繼續下一步。
        </div>

        <!-- 操作列 -->
        <div class="flex flex-wrap gap-2 justify-between items-center">
            <button type="button" @click="backToResultsPage"
                class="rounded-xl px-4 py-2.5 text-sm font-bold bg-white text-amber-700 border border-amber-200 hover:bg-amber-50 transition-all duration-200">
                ← 返回分析結果
            </button>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="finishAndSave"
                    class="rounded-xl px-4 py-2.5 text-sm font-bold border transition-all duration-200"
                    :class="canChooseMakeupPreference
                        ? 'bg-white text-indigo-700 border-indigo-200 hover:bg-indigo-50'
                        : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'"
                    :disabled="!canChooseMakeupPreference"
                    title="跳過妝感偏好，直接儲存並查看推薦">
                    直接查看產品推薦 →
                </button>
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

    <!-- 妝感偏好 -->
    <div v-if="showMakeupPreference" class="p-5 md:p-6 border border-pink-100 rounded-2xl bg-gradient-to-br from-pink-50 to-rose-50 shadow-sm">
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
            <button @click="showMakeupPreference = false" class="bg-white text-pink-600 py-2.5 rounded-xl font-bold border border-pink-200 hover:bg-pink-50 transition duration-200">返回：確認結果</button>
            <button @click="finishAndSave" class="bg-pink-600 text-white py-2.5 rounded-xl font-bold hover:bg-pink-500 transition duration-200">送出偏好並完成分析</button>
        </div>
    </div>

</div>

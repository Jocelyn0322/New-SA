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

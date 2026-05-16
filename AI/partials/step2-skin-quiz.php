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

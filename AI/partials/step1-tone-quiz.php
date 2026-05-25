<div v-if="currentStep === 1 || currentStep === 2" class="chat-card">

    <!-- Header -->
    <div class="chat-hd">
        <div class="hd-left">
            <div class="hd-av">✨</div>
            <div>
                <div class="hd-name">AI 膚況診斷助手</div>
                <div class="hd-sub">• 正在引導你完成個人設定</div>
            </div>
        </div>
        <div class="hd-right">
            <div class="hd-step">
                {{ toneQuizStep <= toneQuizData.length
                    ? toneQuizStep
                    : toneQuizData.length + skinQuizStep }} / 7
            </div>
            <div class="hd-track">
                <div class="hd-prog" :style="{ width: ((toneQuizStep <= toneQuizData.length
                    ? toneQuizStep - 1
                    : toneQuizData.length + skinQuizStep - 1) / 7 * 100) + '%' }"></div>
            </div>
        </div>
    </div>

    <!-- Chat history -->
    <div class="chat-body">

        <!-- 開場白 -->
        <div class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="bot-bubble">你好！先做幾個小問題，幫我了解你的膚色和膚質，後面拍照分析會更精準 🌸</div>
        </div>

        <!-- 膚色已答紀錄 -->
        <template v-for="(q, idx) in toneQuizData" :key="'th'+idx">
            <template v-if="toneQuizAnswers[q.key]">
                <div class="bot-row chat-in">
                    <div class="bot-av">✨</div>
                    <div class="bot-bubble">{{ q.q }}</div>
                </div>
                <div class="user-row chat-in">
                    <div class="user-bubble">{{ q.opts.find(o => o.val === toneQuizAnswers[q.key])?.label }}</div>
                </div>
            </template>
        </template>

        <!-- 打字中（膚色） -->
        <div v-if="toneQuizTyping" class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="typing-bubble">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>
        </div>

        <!-- 目前膚色題（未答） -->
        <div v-if="!toneQuizTyping && toneQuizStep <= toneQuizData.length" class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="bot-bubble">{{ toneQuizData[toneQuizStep - 1]?.q }}</div>
        </div>

        <!-- 膚色完成過渡 -->
        <div v-if="toneQuizStep > toneQuizData.length" class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="transition-bubble">
                膚色預測：<strong>{{ toneGuess || '中性調' }}</strong>！再來幾個膚質問題 📋
            </div>
        </div>

        <!-- 膚質已答紀錄 -->
        <template v-for="(q, idx) in skinQuizData" :key="'sh'+idx">
            <template v-if="quizAnswers[q.key]">
                <div class="bot-row chat-in">
                    <div class="bot-av">✨</div>
                    <div class="bot-bubble">
                        {{ q.q }}
                        <span v-if="q.hint" class="bubble-hint">{{ q.hint }}</span>
                    </div>
                </div>
                <div class="user-row chat-in">
                    <div class="user-bubble">{{ q.opts.find(o => o.val === quizAnswers[q.key])?.label }}</div>
                </div>
            </template>
        </template>

        <!-- 打字中（膚質） -->
        <div v-if="skinQuizTyping" class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="typing-bubble">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>
        </div>

        <!-- 目前膚質題（未答） -->
        <div v-if="!skinQuizTyping && toneQuizStep > toneQuizData.length && skinQuizStep <= skinQuizData.length"
             class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="bot-bubble">
                {{ skinQuizData[skinQuizStep - 1]?.q }}
                <span v-if="skinQuizData[skinQuizStep - 1]?.hint" class="bubble-hint">{{ skinQuizData[skinQuizStep - 1]?.hint }}</span>
            </div>
        </div>

        <!-- 全部完成 -->
        <div v-if="skinQuizStep > skinQuizData.length" class="bot-row chat-in">
            <div class="bot-av">✨</div>
            <div class="bot-bubble">問卷完成！接下來用相機拍一張臉部照片，我來幫你分析 📸</div>
        </div>

        <div id="chatEnd" style="height:2px;"></div>
    </div>

    <!-- 底部：膚色選項 Chips -->
    <div v-if="!toneQuizTyping && !skinQuizTyping && toneQuizStep <= toneQuizData.length" class="chip-zone">
        <div class="chip-hint">選一個最符合的</div>
        <div class="chip-row">
            <button v-for="opt in toneQuizData[toneQuizStep - 1]?.opts" :key="opt.val"
                class="chip" @click="setToneQuizAnswer(toneQuizData[toneQuizStep - 1].key, opt.val)">
                {{ opt.label }}
            </button>
        </div>
    </div>

    <!-- 底部：膚質選項 Chips -->
    <div v-if="!toneQuizTyping && !skinQuizTyping && toneQuizStep > toneQuizData.length && skinQuizStep <= skinQuizData.length"
         class="chip-zone">
        <div class="chip-hint">選一個最符合的</div>
        <div class="chip-row">
            <button v-for="opt in skinQuizData[skinQuizStep - 1]?.opts" :key="opt.val"
                class="chip" @click="setQuizAnswer(skinQuizData[skinQuizStep - 1].key, opt.val)">
                {{ opt.label }}
            </button>
        </div>
    </div>

    <!-- 底部：完成按鈕 -->
    <div v-if="skinQuizStep > skinQuizData.length" class="next-btn-wrap">
        <button class="next-btn" @click="goToCameraStep">下一步：拍照分析 →</button>
    </div>

</div>

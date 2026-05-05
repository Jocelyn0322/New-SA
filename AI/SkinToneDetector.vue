<template>
  <div class="brand-root">
    <div class="brand-noise"></div>
    <div class="container mx-auto px-4 py-8 max-w-6xl brand-shell">
      <header class="brand-hero">
        <div class="brand-headline-wrap">
          <h1 class="brand-title">AI Skin Tone Studio</h1>
          <p class="brand-subtitle">簡約、冷感、俐落的膚色分析頁。像高階美妝品牌官網一樣，先建立氣場，再進入分析。</p>
        </div>
        <div class="brand-meta">
          <span>01. CAMERA</span>
          <span>02. RESULT</span>
          <span>03. FINISH</span>
        </div>
      </header>

      <div class="brand-stepper">
        <div class="brand-stepper-row">
          <span :class="analysisStep === 'tone' ? 'is-active' : ''">拍照分析</span>
          <span class="brand-stepper-line"></span>
          <span :class="analysisStep === 'result' ? 'is-active' : ''">確認結果</span>
          <span class="brand-stepper-line"></span>
          <span :class="analysisStep === 'makeup' ? 'is-active' : ''">妝感偏好</span>
        </div>
        <div class="brand-progress">
          <div class="brand-progress-fill" :style="{ width: analysisStep === 'tone' ? '33%' : analysisStep === 'result' ? '66%' : '100%' }"></div>
        </div>
      </div>

      <div class="brand-panel brand-panel-camera">
          <div class="brand-panel-head">
            <div>
              <p class="brand-panel-label">CAMERA</p>
              <h3>智能相機掃描</h3>
            </div>
            <span class="brand-panel-chip">Live</span>
          </div>

          <p class="brand-copy">使用自然光拍攝。畫面越乾淨，結果越接近高級底妝那種「像沒上妝但很精緻」的感覺。</p>

          <button @click="startCamera" class="brand-btn brand-btn-primary">
            啟動智慧相機
          </button>

          <div v-if="cameraActive" class="brand-camera">
            <video ref="video" autoplay class="brand-video"></video>
            <div class="brand-live-badge">實時預覽</div>
          </div>
          <div v-if="cameraActive" class="brand-actions">
            <button @click="captureImage" class="brand-btn brand-btn-secondary">拍攝分析</button>
            <button @click="stopCamera" class="brand-btn brand-btn-ghost">停止相機</button>
          </div>

          <canvas ref="canvas" class="hidden"></canvas>
        </div>

        <div class="brand-panel brand-panel-tone">
          <div class="brand-panel-head">
            <div>
              <p class="brand-panel-label">MANUAL TONE</p>
              <h3>手動膚色選擇</h3>
            </div>
            <span class="brand-panel-chip brand-panel-chip-dark">Edited</span>
          </div>

          <p class="brand-copy">如果你想直接指定膚色類型，這裡可以快速補充。整體走極簡、乾淨、偏歐美 editorial 的閱讀節奏。</p>

          <div class="brand-field">
            <label>選擇您的膚色類型</label>
            <select v-model="skinTone" class="brand-select">
              <option value="">請選擇膚色類型</option>
              <option v-for="tone in skinTonesData" :key="tone.toneName" :value="tone.toneName">{{ tone.toneName }}</option>
            </select>
          </div>

          <button @click="analyzeManual" :disabled="!skinTone" class="brand-btn brand-btn-primary brand-btn-wide">
            開始分析推薦
          </button>
        </div>
      </div>

      <div v-if="analysisStep !== 'tone' && skinTypeResult" class="brand-results">
        <div class="brand-panel brand-panel-results">
          <div class="brand-panel-head brand-panel-head-space">
            <div>
              <p class="brand-panel-label">RESULTS</p>
              <h3>完整分析結果</h3>
            </div>
            <button @click="restartAnalysis" class="brand-link">重新分析</button>
          </div>

          <div class="brand-result-grid">
            <div class="brand-result-card brand-result-tone">
              <div class="brand-result-kicker">Skin Tone</div>
              <div class="brand-result-main">{{ skinCoordinate.type }}</div>
              <div class="brand-result-sub">{{ skinCoordinate.rgb }}</div>
              <div class="brand-swatch" :style="{ backgroundColor: skinCoordinate.hex }"></div>
              <div class="brand-hex">HEX {{ skinCoordinate.hex }}</div>
            </div>

            <div class="brand-result-card brand-result-type">
              <div class="brand-result-kicker">Skin Type</div>
              <div class="brand-result-main">{{ skinTypeResult.profile.displayName }}</div>
              <p class="brand-result-description">{{ skinTypeResult.profile.description }}</p>
              <div class="brand-pill">分析信心度 {{ Math.round(skinTypeResult.confidence * 100) }}%</div>
            </div>
          </div>

          <div class="brand-confirm">
            <div class="brand-confirm-head">
              <div>
                <h4>確認你的分析結果</h4>
                <p>確認膚色與膚質後，才能進入妝感偏好頁</p>
              </div>
              <span class="brand-panel-chip">Confirm</span>
            </div>

            <div class="brand-confirm-grid">
              <button type="button" @click="confirmSkinTone" class="brand-affirm" :class="confirmedSkinTone ? 'is-on' : ''">
                <p>確認膚色</p>
                <span>{{ confirmedSkinTone ? '已確認目前膚色結果' : '點我確認目前的膚色判定' }}</span>
              </button>

              <button type="button" @click="confirmSkinType" class="brand-affirm" :class="confirmedSkinType ? 'is-on' : ''">
                <p>確認膚質</p>
                <span>{{ confirmedSkinType ? '已確認目前膚質結果' : '點我確認目前的膚質判定' }}</span>
              </button>
            </div>

            <div class="brand-confirm-footer">
              <div>
                狀態：
                <span :class="confirmedSkinTone ? 'is-on' : ''">膚色已確認</span>
                ／
                <span :class="confirmedSkinType ? 'is-on' : ''">膚質已確認</span>
              </div>
              <button type="button" @click="goToMakeupStep" :disabled="!canChooseMakeupPreference" class="brand-btn brand-btn-primary brand-btn-compact" :class="canChooseMakeupPreference ? '' : 'is-disabled'">前往妝感偏好頁</button>
            </div>

            <div class="brand-radar">
              <h4>膚質分析雷達圖</h4>
              <div class="brand-radar-wrap">
                <svg viewBox="0 0 200 200" class="brand-radar-svg">
                  <circle cx="100" cy="100" r="80" fill="none" stroke="#2b2b2b" stroke-width="1"/>
                  <circle cx="100" cy="100" r="60" fill="none" stroke="#2b2b2b" stroke-width="1"/>
                  <circle cx="100" cy="100" r="40" fill="none" stroke="#2b2b2b" stroke-width="1"/>
                  <circle cx="100" cy="100" r="20" fill="none" stroke="#2b2b2b" stroke-width="1"/>
                  <line x1="100" y1="20" x2="100" y2="180" stroke="#2b2b2b" stroke-width="1"/>
                  <line x1="20" y1="100" x2="180" y2="100" stroke="#2b2b2b" stroke-width="1"/>
                  <line x1="35" y1="35" x2="165" y2="165" stroke="#2b2b2b" stroke-width="1"/>
                  <line x1="35" y1="165" x2="165" y2="35" stroke="#2b2b2b" stroke-width="1"/>
                  <polygon :points="getRadarPoints()" fill="rgba(255,255,255,0.08)" stroke="#ffffff" stroke-width="2"/>
                  <text x="100" y="15" text-anchor="middle" class="text-xs fill-gray-300">油性</text>
                  <text x="185" y="105" text-anchor="start" class="text-xs fill-gray-300">敏感</text>
                  <text x="100" y="195" text-anchor="middle" class="text-xs fill-gray-300">乾燥</text>
                  <text x="15" y="105" text-anchor="end" class="text-xs fill-gray-300">毛孔</text>
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="analysisStep === 'makeup'" class="brand-panel brand-panel-makeup">
        <div class="brand-panel-head brand-panel-head-space">
          <div>
            <p class="brand-panel-label">MAKEUP</p>
            <h3>妝感與妝容偏好</h3>
          </div>
          <button type="button" @click="analysisStep = 'result'" class="brand-link">返回確認頁</button>
        </div>

        <div class="brand-makeup-grid">
          <div class="brand-field">
            <label>妝感</label>
            <select v-model="makeupFinish" class="brand-select">
              <option value="霧面">霧面</option>
              <option value="水光感">水光感</option>
              <option value="奶油肌">奶油肌</option>
              <option value="自然裸妝">自然裸妝</option>
            </select>
          </div>

          <div class="brand-field">
            <label>妝容風格</label>
            <select v-model="makeupStyle" class="brand-select">
              <option value="日常通勤">日常通勤</option>
              <option value="韓系清透">韓系清透</option>
              <option value="歐美立體">歐美立體</option>
              <option value="約會精緻">約會精緻</option>
            </select>
          </div>
        </div>

        <div class="brand-summary">目前選擇：<span>{{ makeupFinish }}</span> / <span>{{ makeupStyle }}</span></div>

        <div class="brand-products">
          <div class="brand-panel-head brand-panel-head-mini">
            <div>
              <p class="brand-panel-label">TOP PICKS</p>
              <h4>專屬產品推薦</h4>
            </div>
          </div>

          <div v-if="recommendations.length > 0" class="brand-product-grid">
            <div v-for="product in recommendations" :key="product.id" class="brand-product-card">
              <h5>{{ product.brand }} - {{ product.name }}</h5>
              <div class="brand-tags">
                <span v-for="tag in product.tags" :key="tag" class="brand-tag">{{ tag }}</span>
              </div>
              <div class="brand-product-footer">
                <div>匹配度: <span>{{ product.matchRate }}%</span></div>
                <button class="brand-link brand-link-light">查看詳情</button>
              </div>
            </div>
          </div>

          <div v-else class="brand-empty">目前尚未產生推薦結果</div>
        </div>
      </div>
    </div>
</template>
<script setup>
import { ref, computed, onUnmounted, nextTick, defineExpose } from 'vue'
import { findClosestSkinToneFromRgb, analyzeSkinType, getRecommendedProducts } from './skinAnalysisEngine.js'

// Reactive state (minimal declarations needed by the script below)
const video = ref(null)
const canvas = ref(null)
const cameraActive = ref(false)
const analysisStep = ref('tone')
const skinTone = ref('')
const skinTonesData = ref([])
const skinCoordinate = ref(null)
const skinTypeResult = ref(null)
const questionnaireAnswers = ref({ sensitiveReaction: false, tightFeeling: false, oilControl: false })
const recommendations = ref([])
const confirmedSkinTone = ref(false)
const confirmedSkinType = ref(false)
const showQuestionnaire = ref(false)
const makeupFinish = ref('霧面')
const makeupStyle = ref('日常通勤')
const hasShownNaturalLightReminder = ref(false)

const startCamera = async () => {
  try {
    if (!hasShownNaturalLightReminder.value) {
      alert('提醒：請在自然光下拍攝，結果會更準確。')
      hasShownNaturalLightReminder.value = true
    }

    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
    cameraActive.value = true
    await nextTick()

    if (video.value) {
      video.value.srcObject = stream
      // 確保視頻開始播放
      video.value.onloadedmetadata = () => {
        video.value.play()
      }
    } else {
      throw new Error('視頻元素未找到')
    }

  } catch (error) {
    console.error('相機錯誤:', error)
    let errorMessage = '無法訪問相機: '

    if (error.name === 'NotAllowedError') {
      errorMessage += '請允許相機權限'
    } else if (error.name === 'NotFoundError') {
      errorMessage += '未找到相機設備'
    } else if (error.name === 'NotReadableError') {
      errorMessage += '相機被其他應用占用'
    } else if (error.name === 'OverconstrainedError') {
      errorMessage += '相機不支持請求的配置'
    } else {
      errorMessage += error.message
    }

    alert(errorMessage)
  }
}

const captureImage = () => {
  if (!video.value || !canvas.value) {
    alert('相機未啟動或畫布未準備好')
    return
  }

  const ctx = canvas.value.getContext('2d')
  canvas.value.width = video.value.videoWidth
  canvas.value.height = video.value.videoHeight
  ctx.drawImage(video.value, 0, 0)

  // 進行真正的膚色分析
  analyzeSkinFromImage()
}

const analyzeSkinFromImage = () => {
  const ctx = canvas.value.getContext('2d')
  const imageData = ctx.getImageData(0, 0, canvas.value.width, canvas.value.height)
  const data = imageData.data

  // 分析中央區域的膚色 (避免邊緣干擾)
  const centerX = Math.floor(canvas.value.width / 2)
  const centerY = Math.floor(canvas.value.height / 2)
  const sampleSize = Math.min(100, Math.floor(Math.min(canvas.value.width, canvas.value.height) / 4))

  let totalR = 0, totalG = 0, totalB = 0
  let pixelCount = 0

  // 採樣中央區域的像素
  for (let y = centerY - sampleSize; y < centerY + sampleSize; y++) {
    for (let x = centerX - sampleSize; x < centerX + sampleSize; x++) {
      if (x >= 0 && x < canvas.value.width && y >= 0 && y < canvas.value.height) {
        const index = (y * canvas.value.width + x) * 4
        const r = data[index]
        const g = data[index + 1]
        const b = data[index + 2]

        // 簡單的膚色過濾 (基於常見的膚色範圍)
        if (isSkinColor(r, g, b)) {
          totalR += r
          totalG += g
          totalB += b
          pixelCount++
        }
      }
    }
  }

  if (pixelCount === 0) {
    alert('未檢測到足夠的膚色像素，請調整角度或光線')
    return
  }

  // 計算平均膚色
  const avgR = Math.round(totalR / pixelCount)
  const avgG = Math.round(totalG / pixelCount)
  const avgB = Math.round(totalB / pixelCount)

  const closestTone = findClosestSkinTone(avgR, avgG, avgB)
  const resolvedTone = closestTone || { toneName: '未知膚色', hex: rgbToHex(avgR, avgG, avgB), rgb: { r: avgR, g: avgG, b: avgB } }

  skinCoordinate.value = {
    type: resolvedTone.toneName,
    rgb: `${resolvedTone.rgb.r}, ${resolvedTone.rgb.g}, ${resolvedTone.rgb.b}`,
    hex: resolvedTone.hex,
    rawRgb: { r: avgR, g: avgG, b: avgB }
  }

  const imageAnalysisData = {
    tZoneReflection: 0.7,
    cheekReflection: 0.3,
    poreDensity: 0.6,
    rednessLevel: 0.2
  }

  skinTypeResult.value = analyzeSkinType(imageAnalysisData, questionnaireAnswers.value)

  const mockProducts = [
    { id: 1, brand: 'YSL', name: '恆久完美粉底', tags: ['霧面', '含酒精', '高遮瑕'], hasAlcohol: true, matchRate: 98 },
    { id: 2, brand: 'Dior', name: '超完美持久', tags: ['光澤', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 95 },
    { id: 3, brand: 'Lancome', name: '零粉感', tags: ['霧面', '控油', '含酒精'], hasAlcohol: true, matchRate: 92 },
    { id: 4, brand: 'Estee Lauder', name: '雙重修飾粉底', tags: ['霧面', '溫和', '不含酒精'], hasAlcohol: false, matchRate: 89 },
    { id: 5, brand: 'Clinique', name: '持久完美粉底', tags: ['霧面', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 87 }
  ]

  recommendations.value = getRecommendedProducts(skinCoordinate.value, skinTypeResult.value, mockProducts).slice(0, 3)

  confirmedSkinTone.value = false
  confirmedSkinType.value = false
  showQuestionnaire.value = false
  analysisStep.value = 'result'
}

// 簡單的膚色檢測函數 (基於YCbCr色彩空間)
const isSkinColor = (r, g, b) => {
  // 轉換到YCbCr色彩空間
  const y = 0.299 * r + 0.587 * g + 0.114 * b
  const cb = 128 - 0.168736 * r - 0.331264 * g + 0.5 * b
  const cr = 128 + 0.5 * r - 0.418688 * g - 0.081312 * b

  // 膚色範圍 (經驗值)
  return y > 80 && cb > 85 && cb < 135 && cr > 135 && cr < 180
}

const hexToRgb = (hex) => {
  if (!hex || typeof hex !== 'string') return null
  const value = hex.replace('#', '')
  const bigint = parseInt(value, 16)
  return {
    r: (bigint >> 16) & 255,
    g: (bigint >> 8) & 255,
    b: bigint & 255
  }
}

const getRgbDistance = (rgb1, rgb2) => {
  return Math.sqrt(
    Math.pow(rgb1.r - rgb2.r, 2) +
    Math.pow(rgb1.g - rgb2.g, 2) +
    Math.pow(rgb1.b - rgb2.b, 2)
  )
}

const findClosestSkinTone = (r, g, b) => {
  return findClosestSkinToneFromRgb(r, g, b, skinTonesData)
}

// RGB轉HEX
const rgbToHex = (r, g, b) => {
  return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase()
}

// 基於RGB值分類膚色
const classifySkinTone = (r, g, b) => {
  const closest = findClosestSkinTone(r, g, b)
  return closest ? closest.toneName : '未知膚色'
}

// 處理問卷提交並進行完整分析
const submitQuestionnaire = () => {
  // 模擬圖像分析數據 (在實際應用中會從圖像處理中獲取)
  const imageAnalysisData = {
    tZoneReflection: 0.7,    // T區反光度
    cheekReflection: 0.3,   // 頰部反光度
    poreDensity: 0.6,       // 毛孔密度
    rednessLevel: 0.2       // 泛紅程度
  }

  // 分析膚質
  const skinTypeAnalysis = analyzeSkinType(imageAnalysisData, questionnaireAnswers.value)

  skinTypeResult.value = skinTypeAnalysis
  confirmedSkinTone.value = false
  confirmedSkinType.value = false
  analysisStep.value = 'result'

  // 獲取推薦產品
  const mockProducts = [
    { id: 1, brand: 'YSL', name: '恆久完美粉底', tags: ['霧面', '含酒精', '高遮瑕'], hasAlcohol: true, matchRate: 98 },
    { id: 2, brand: 'Dior', name: '超完美持久', tags: ['光澤', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 95 },
    { id: 3, brand: 'Lancome', name: '零粉感', tags: ['霧面', '控油', '含酒精'], hasAlcohol: true, matchRate: 92 },
    { id: 4, brand: 'Estee Lauder', name: '雙重修飾粉底', tags: ['霧面', '溫和', '不含酒精'], hasAlcohol: false, matchRate: 89 },
    { id: 5, brand: 'Clinique', name: '持久完美粉底', tags: ['霧面', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 87 }
  ]

  const recommendedProducts = getRecommendedProducts(skinCoordinate.value, skinTypeAnalysis, mockProducts)
  recommendations.value = recommendedProducts.slice(0, 3) // 顯示前3個推薦

  showQuestionnaire.value = false
  analysisStep.value = 'result'
}

const restartAnalysis = () => {
  analysisStep.value = 'tone'
  showQuestionnaire.value = false
  skinTone.value = ''
  skinCoordinate.value = null
  recommendations.value = []
  skinTypeResult.value = null
  questionnaireAnswers.value = {
    sensitiveReaction: false,
    tightFeeling: false,
    oilControl: false
  }
  confirmedSkinTone.value = false
  confirmedSkinType.value = false
  makeupFinish.value = '霧面'
  makeupStyle.value = '日常通勤'
}

const confirmSkinTone = () => {
  confirmedSkinTone.value = true
}

const confirmSkinType = () => {
  confirmedSkinType.value = true
}

const goToMakeupStep = () => {
  if (!canChooseMakeupPreference.value) {
    alert('請先確認膚色與膚質。')
    return
  }

  analysisStep.value = 'makeup'
}

const canChooseMakeupPreference = computed(() => confirmedSkinTone.value && confirmedSkinType.value)

// 生成雷達圖點
const getRadarPoints = () => {
  if (!skinTypeResult.value || !skinTypeResult.value.analysisData) return ''

  const data = skinTypeResult.value.analysisData
  const centerX = 100
  const centerY = 100
  const maxRadius = 80

  // 將數據標準化到0-1範圍，然後轉換為半徑
  const points = [
    { x: centerX, y: centerY - (data.tZoneReflection * maxRadius) }, // 頂部：油性
    { x: centerX + (data.rednessLevel * maxRadius), y: centerY },   // 右邊：敏感
    { x: centerX, y: centerY + ((1 - data.cheekReflection) * maxRadius) }, // 底部：乾燥
    { x: centerX - (data.poreDensity * maxRadius), y: centerY }     // 左邊：毛孔
  ]

  return points.map(p => `${p.x},${p.y}`).join(' ')
}

const analyzeManual = () => {
  if (!skinTone.value) {
    alert('請選擇膚色類型')
    return
  }
  analyzeSkinTone()
}

const analyzeSkinTone = () => {
  // 使用真實的膚色資料來生成模擬座標
  const selectedTone = skinTonesData.find(tone => tone.toneName === skinTone.value)
  
  if (selectedTone) {
    const toneRgb = selectedTone.rgb || hexToRgb(selectedTone.hex) || { r: 0, g: 0, b: 0 }
    skinCoordinate.value = {
      type: selectedTone.toneName,
      rgb: `${toneRgb.r}, ${toneRgb.g}, ${toneRgb.b}`,
      hex: selectedTone.hex
    }
  } else {
    skinCoordinate.value = { type: '分析中...', rgb: '...', hex: '...' }
  }

  const imageAnalysisData = {
    tZoneReflection: 0.5,
    cheekReflection: 0.5,
    poreDensity: 0.5,
    rednessLevel: 0.3
  }

  skinTypeResult.value = analyzeSkinType(imageAnalysisData, questionnaireAnswers.value)

  const mockProducts = [
    { id: 1, brand: 'YSL', name: '恆久完美粉底', tags: ['霧面', '含酒精', '高遮瑕'], hasAlcohol: true, matchRate: 98 },
    { id: 2, brand: 'Dior', name: '超完美持久', tags: ['光澤', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 95 },
    { id: 3, brand: 'Lancome', name: '零粉感', tags: ['霧面', '控油', '含酒精'], hasAlcohol: true, matchRate: 92 },
    { id: 4, brand: 'Estee Lauder', name: '雙重修飾粉底', tags: ['霧面', '溫和', '不含酒精'], hasAlcohol: false, matchRate: 89 },
    { id: 5, brand: 'Clinique', name: '持久完美粉底', tags: ['霧面', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 87 }
  ]

  recommendations.value = getRecommendedProducts(skinCoordinate.value, skinTypeResult.value, mockProducts).slice(0, 3)

  confirmedSkinTone.value = false
  confirmedSkinType.value = false

  analysisStep.value = 'result'
}

const stopCamera = () => {
  if (video.value && video.value.srcObject) {
    const stream = video.value.srcObject
    const tracks = stream.getTracks()
    tracks.forEach(track => track.stop())
    video.value.srcObject = null
  }
  cameraActive.value = false
}

onUnmounted(() => {
  stopCamera()
})

// 暴露方法給測試
defineExpose({
  cameraActive,
  skinTone,
  skinCoordinate,
  recommendations,
  makeupFinish,
  makeupStyle,
  startCamera,
  stopCamera,
  captureImage,
  analyzeManual,
  analyzeSkinTone,
  confirmSkinTone,
  confirmSkinType
})
</script>

<style scoped>
.brand-root {
  min-height: 100vh;
  color: #f5f1ea;
  background:
    radial-gradient(circle at top left, rgba(255, 255, 255, 0.08), transparent 28%),
    radial-gradient(circle at 85% 20%, rgba(255, 255, 255, 0.06), transparent 20%),
    linear-gradient(145deg, #090909 0%, #121212 46%, #1b1714 100%);
  position: relative;
  overflow: hidden;
}

.brand-noise {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.16;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 28px 28px;
  mask-image: radial-gradient(circle at center, black 60%, transparent 100%);
}

.brand-shell {
  position: relative;
  z-index: 1;
}

.brand-hero {
  display: grid;
  gap: 1.1rem;
  padding: 0.5rem 0 1.75rem;
}

.brand-kicker,
.brand-panel-label {
  letter-spacing: 0.32em;
  text-transform: uppercase;
  font-size: 0.72rem;
  color: rgba(245, 241, 234, 0.62);
}

.brand-headline-wrap {
  max-width: 52rem;
}

.brand-title {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: clamp(3rem, 8vw, 6.2rem);
  line-height: 0.94;
  letter-spacing: -0.06em;
  font-weight: 700;
  text-transform: uppercase;
  color: #fff8f0;
}

.brand-subtitle {
  margin-top: 1rem;
  max-width: 34rem;
  font-size: 1rem;
  line-height: 1.8;
  color: rgba(245, 241, 234, 0.72);
}

.brand-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.8rem 1.2rem;
  font-size: 0.72rem;
  letter-spacing: 0.28em;
  color: rgba(245, 241, 234, 0.54);
}

.brand-stepper {
  padding: 1rem 0 2rem;
}

.brand-stepper-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.24em;
  color: rgba(245, 241, 234, 0.45);
}

.brand-stepper-row .is-active {
  color: #fff8f0;
}

.brand-stepper-line {
  flex: 1;
  height: 1px;
  background: rgba(245, 241, 234, 0.18);
}

.brand-progress {
  margin-top: 0.85rem;
  height: 1px;
  background: rgba(245, 241, 234, 0.15);
  overflow: hidden;
}

.brand-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #fff8f0, #c7b299, #fff8f0);
}

.brand-grid,
.brand-result-grid,
.brand-confirm-grid,
.brand-makeup-grid,
.brand-product-grid {
  display: grid;
  gap: 1rem;
}

.brand-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: stretch;
}

.brand-panel {
  padding: 1.25rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(14, 14, 14, 0.58);
  backdrop-filter: blur(20px);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.28);
}

.brand-panel-camera,
.brand-panel-tone,
.brand-panel-results,
.brand-panel-makeup {
  border-radius: 1.75rem;
}

.brand-panel-head,
.brand-confirm-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.brand-panel-head h3,
.brand-confirm-head h4,
.brand-products h4 {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.55rem;
  line-height: 1.05;
  letter-spacing: -0.03em;
  color: #fff8f0;
}

.brand-panel-label {
  margin-bottom: 0.45rem;
}

.brand-panel-chip {
  border: 1px solid rgba(255, 248, 240, 0.22);
  color: #fff8f0;
  padding: 0.4rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.7rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

.brand-panel-chip-dark {
  background: rgba(255, 248, 240, 0.06);
}

.brand-copy {
  color: rgba(245, 241, 234, 0.68);
  line-height: 1.85;
  font-size: 0.95rem;
  margin-bottom: 1rem;
  max-width: 34rem;
}

.brand-btn,
.brand-select {
  width: 100%;
  min-height: 3.2rem;
  border-radius: 9999px;
  font: inherit;
}

.brand-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 180ms ease, background 180ms ease, border-color 180ms ease, color 180ms ease;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.brand-btn:hover {
  transform: translateY(-1px);
}

.brand-btn-primary {
  background: #f5f1ea;
  color: #121212;
  border: 1px solid rgba(245, 241, 234, 0.6);
}

.brand-btn-secondary {
  background: transparent;
  color: #fff8f0;
  border: 1px solid rgba(245, 241, 234, 0.22);
}

.brand-btn-ghost {
  background: rgba(255, 255, 255, 0.04);
  color: rgba(245, 241, 234, 0.88);
  border: 1px solid rgba(245, 241, 234, 0.14);
}

.brand-btn-wide {
  margin-top: 0.4rem;
}

.brand-btn-compact {
  width: auto;
  min-height: 2.8rem;
  padding: 0.8rem 1.15rem;
}

.brand-btn.is-disabled,
.brand-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  transform: none;
}

.brand-camera {
  position: relative;
  margin-top: 1rem;
}

.brand-video {
  width: 100%;
  display: block;
  min-height: 18rem;
  max-height: 22rem;
  object-fit: cover;
  border-radius: 1.25rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: #0f0f0f;
}

.brand-live-badge {
  position: absolute;
  top: 0.9rem;
  right: 0.9rem;
  padding: 0.35rem 0.65rem;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.56);
  color: #fff8f0;
  font-size: 0.7rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.brand-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  margin-top: 1rem;
}

.brand-field {
  display: grid;
  gap: 0.55rem;
  margin-bottom: 1rem;
}

.brand-field label {
  color: rgba(245, 241, 234, 0.78);
  font-size: 0.76rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

.brand-select {
  appearance: none;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(245, 241, 234, 0.18);
  color: #fff8f0;
  padding: 0.95rem 1.1rem;
}

.brand-summary {
  margin-top: 0.9rem;
  padding: 0.95rem 1rem;
  border-top: 1px solid rgba(245, 241, 234, 0.12);
  border-bottom: 1px solid rgba(245, 241, 234, 0.12);
  color: rgba(245, 241, 234, 0.72);
  letter-spacing: 0.03em;
}

.brand-summary span,
.brand-confirm-footer .is-on {
  color: #fff8f0;
}

.brand-results {
  margin-top: 1.25rem;
}

.brand-result-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-bottom: 1rem;
}

.brand-result-card {
  padding: 1.1rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
}

.brand-result-kicker {
  font-size: 0.7rem;
  letter-spacing: 0.26em;
  text-transform: uppercase;
  color: rgba(245, 241, 234, 0.5);
  margin-bottom: 0.8rem;
}

.brand-result-main {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: clamp(1.5rem, 3.8vw, 2.4rem);
  line-height: 1.04;
  letter-spacing: -0.04em;
  color: #fff8f0;
}

.brand-result-sub,
.brand-result-description {
  color: rgba(245, 241, 234, 0.66);
  line-height: 1.85;
  margin-top: 0.7rem;
}

.brand-swatch {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 9999px;
  margin-top: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.42);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.24);
}

.brand-hex,
.brand-pill {
  margin-top: 0.95rem;
  font-size: 0.76rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: rgba(245, 241, 234, 0.8);
}

.brand-pill {
  display: inline-flex;
  padding: 0.35rem 0.7rem;
  border: 1px solid rgba(245, 241, 234, 0.14);
  border-radius: 9999px;
}

.brand-confirm {
  margin-top: 1rem;
  padding: 1rem;
  border-top: 1px solid rgba(245, 241, 234, 0.12);
  border-bottom: 1px solid rgba(245, 241, 234, 0.12);
}

.brand-confirm-head p {
  color: rgba(245, 241, 234, 0.64);
  margin-top: 0.4rem;
  line-height: 1.7;
}

.brand-confirm-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.brand-affirm {
  padding: 1rem;
  border-radius: 1.25rem;
  text-align: left;
  background: rgba(255, 255, 255, 0.04);
  color: #fff8f0;
  border: 1px solid rgba(245, 241, 234, 0.14);
}

.brand-affirm p {
  font-size: 1rem;
  letter-spacing: 0.04em;
}

.brand-affirm span {
  display: block;
  margin-top: 0.45rem;
  font-size: 0.88rem;
  color: rgba(245, 241, 234, 0.66);
  line-height: 1.6;
}

.brand-affirm.is-on {
  border-color: rgba(255, 248, 240, 0.36);
  background: rgba(255, 248, 240, 0.08);
}

.brand-confirm-footer {
  margin-top: 1rem;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  color: rgba(245, 241, 234, 0.7);
}

.brand-confirm-footer > div {
  line-height: 1.8;
}

.brand-link {
  background: transparent;
  color: #fff8f0;
  border: none;
  padding: 0;
  font-size: 0.8rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  text-decoration: underline;
  text-underline-offset: 0.25rem;
}

.brand-link-light {
  color: #121212;
  text-decoration-color: rgba(18, 18, 18, 0.35);
}

.brand-radar {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(245, 241, 234, 0.12);
}

.brand-radar h4 {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.2rem;
  color: #fff8f0;
  margin-bottom: 0.9rem;
}

.brand-radar-wrap {
  display: flex;
  justify-content: center;
}

.brand-radar-svg {
  width: 17rem;
  max-width: 100%;
}

.brand-panel-makeup {
  margin-top: 1.25rem;
}

.brand-makeup-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-bottom: 0.8rem;
}

.brand-products {
  margin-top: 1.1rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(245, 241, 234, 0.12);
}

.brand-product-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.brand-product-card {
  padding: 1rem;
  border: 1px solid rgba(245, 241, 234, 0.14);
  background: rgba(255, 255, 255, 0.04);
  border-radius: 1.25rem;
}

.brand-product-card h5 {
  color: #fff8f0;
  font-size: 1rem;
  letter-spacing: 0.02em;
  margin-bottom: 0.8rem;
}

.brand-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-bottom: 0.9rem;
}

.brand-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.28rem 0.55rem;
  border-radius: 9999px;
  font-size: 0.72rem;
  color: #121212;
  background: #f5f1ea;
}

.brand-product-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  color: rgba(245, 241, 234, 0.7);
  font-size: 0.88rem;
}

.brand-product-footer span {
  color: #fff8f0;
}

.brand-empty {
  padding: 1.2rem;
  border-radius: 1.25rem;
  border: 1px dashed rgba(245, 241, 234, 0.2);
  color: rgba(245, 241, 234, 0.58);
  text-align: center;
}

.hidden {
  display: none;
}

@media (max-width: 900px) {
  .brand-grid,
  .brand-result-grid,
  .brand-confirm-grid,
  .brand-makeup-grid,
  .brand-product-grid {
    grid-template-columns: 1fr;
  }

  .brand-actions {
    grid-template-columns: 1fr;
  }

  .brand-panel-head,
  .brand-confirm-head,
  .brand-confirm-footer {
    align-items: flex-start;
  }
}

@media (max-width: 640px) {
  .brand-title {
    font-size: clamp(2.5rem, 14vw, 4rem);
  }

  .brand-panel,
  .brand-panel-results,
  .brand-panel-makeup {
    padding: 1rem;
    border-radius: 1.25rem;
  }

  .brand-confirm-grid {
    grid-template-columns: 1fr;
  }

  .brand-result-main {
    font-size: 1.6rem;
  }
}
</style>
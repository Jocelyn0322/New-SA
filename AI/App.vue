<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-100">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent mb-2">
          💄 AI 底妝決策系統
        </h1>
        <p class="text-gray-600 text-lg">智能分析您的膚質，推薦最適合的底妝產品</p>
      </div>

      <!-- 膚質設定 -->
      <div class="bg-white rounded-2xl shadow-xl p-6 border border-blue-100 mb-8">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
            <span class="text-2xl">👤</span>
          </div>
          <h2 class="text-xl font-bold text-gray-800">個人膚質設定</h2>
        </div>

        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200">
          <label class="flex items-center cursor-pointer group">
            <input
              type="checkbox"
              v-model="userProfile.isDrySkin"
              class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 mr-3"
            >
            <span class="text-gray-700 group-hover:text-blue-700 transition-colors duration-200">
              <span class="font-medium">我是乾性肌</span>
              <span class="text-sm text-gray-500 ml-2">系統將自動過濾含酒精成分的產品</span>
            </span>
          </label>
        </div>
      </div>

      <!-- 產品推薦 -->
      <div class="bg-white rounded-2xl shadow-xl p-6 border border-indigo-100">
        <div class="flex items-center mb-6">
          <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
            <span class="text-2xl">🛍️</span>
          </div>
          <h2 class="text-xl font-bold text-gray-800">專屬產品推薦</h2>
        </div>

        <div v-if="filteredProducts.length > 0" class="grid gap-4">
          <div
            v-for="p in filteredProducts"
            :key="p.id"
            class="bg-gradient-to-r from-white to-gray-50 p-6 rounded-xl border border-gray-200 hover:border-indigo-300 transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
          >
            <div class="flex justify-between items-start mb-3">
              <div class="flex-1">
                <h3 class="text-xl font-bold text-gray-800 mb-1">{{ p.brand }} - {{ p.name }}</h3>
                <div class="flex flex-wrap gap-2 mb-3">
                  <span
                    v-for="tag in p.tags"
                    :key="tag"
                    class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-medium"
                  >
                    {{ tag }}
                  </span>
                </div>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold text-green-600">{{ p.matchRate }}%</div>
                <div class="text-sm text-gray-500">匹配度</div>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <div class="flex items-center text-sm text-gray-600">
                <span v-if="p.hasAlcohol" class="text-red-500 font-medium">⚠️ 含酒精</span>
                <span v-else class="text-green-500 font-medium">✅ 不含酒精</span>
              </div>
              <button class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white px-6 py-2 rounded-lg font-medium hover:from-indigo-600 hover:to-purple-600 transition-all duration-300 transform hover:scale-105">
                查看詳情
              </button>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12">
          <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="text-4xl">😔</span>
          </div>
          <h3 class="text-xl font-bold text-gray-700 mb-2">抱歉，沒有符合您膚質的推薦產品</h3>
          <p class="text-gray-500">請調整您的膚質設定，或聯繫客服獲取更多建議</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

// 模擬資料庫數據 (代替後端 API)
const mockProducts = [
  { id: 1, brand: 'YSL', name: '恆久完美粉底', tags: ['霧面', '含酒精', '高遮瑕'], hasAlcohol: true, matchRate: 98 },
  { id: 2, brand: 'Dior', name: '超完美持久', tags: ['光澤', '保濕', '不含酒精'], hasAlcohol: false, matchRate: 95 },
  { id: 3, brand: 'Lancome', name: '零粉感', tags: ['霧面', '控油', '含酒精'], hasAlcohol: true, matchRate: 92 }
]

const userProfile = ref({
  isDrySkin: true
})

// 核心過濾邏輯 (Story 1.2)
const filteredProducts = computed(() => {
  if (userProfile.value.isDrySkin) {
    return mockProducts.filter(p => !p.hasAlcohol)
  }
  return mockProducts
})
</script>


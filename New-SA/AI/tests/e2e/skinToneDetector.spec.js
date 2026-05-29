import { test, expect } from '@playwright/test'

test.describe('膚色檢測 E2E 測試', () => {
  test.beforeEach(async ({ page }) => {
    // 導航到包含 SkinToneDetector 組件的頁面
    await page.goto('/story1.php')
    
    // 等待組件加載完成
    await page.waitForLoadState('networkidle')
  })

  test('應能正確渲染膚色檢測界面', async ({ page }) => {
    // 檢查標題和主要按鈕
    await expect(page.locator('text=📸 相機掃描')).toBeVisible()
    await expect(page.locator('text=✏️ 手動輸入參數')).toBeVisible()
    
    // 檢查啟動相機按鈕
    const startCameraBtn = page.locator('button:has-text("啟動相機")')
    await expect(startCameraBtn).toBeVisible()
    await expect(startCameraBtn).toBeEnabled()
  })

  test('應能選擇膚色類型並分析', async ({ page }) => {
    // Wait for page and Vue app to load
    await page.waitForLoadState('networkidle', { timeout: 15000 })
    await page.waitForTimeout(1000)
    
    // 選擇膚色類型 - 使用 selectOption 而不是點擊 option
    const skinToneSelect = page.locator('select')
    await skinToneSelect.selectOption('粉一白')
    
    // 驗證選擇
    await expect(skinToneSelect).toHaveValue('粉一白')
    
    // 點擊分析按鈕
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    await analyzeBtn.click()
    
    // 等待結果顯示（增加超時時間以確保結果渲染）
    await expect(page.locator('text=您的膚色座標')).toBeVisible({ timeout: 10000 })
    
    // 驗證結果內容
    await expect(page.locator('text=粉一白')).toBeVisible()
    await expect(page.locator('text=RGB:')).toBeVisible()
    await expect(page.locator('text=HEX:')).toBeVisible()
  })

  test('應能為不同膚色類型推薦產品', async ({ page }) => {
    const skinToneSelect = page.locator('select')
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    
    const skinTypes = ['粉一白', '黃一白', '偏紅冷一白', '偏紅暖一白', '中性冷一白']
    
    for (const skinType of skinTypes) {
      // 選擇膚色類型 - 使用 selectOption
      await skinToneSelect.selectOption(skinType)
      
      // 點擊分析
      await analyzeBtn.click()
      
      // 驗證推薦產品部分出現
      await expect(page.locator('text=💄 推薦產品')).toBeVisible({ timeout: 5000 })
      
      // 驗證至少有一個產品推薦
      const products = page.locator('div.mb-2.p-2.bg-white')
      await expect(products.first()).toBeVisible()
      
      console.log(`✓ 成功推薦 ${skinType} 的產品`)
    }
  })

  test('應能驗證膚色座標顯示正確的RGB和HEX值', async ({ page }) => {
    // 選擇膚色 - 使用 selectOption
    const skinToneSelect = page.locator('select')
    await skinToneSelect.selectOption('黃二白')
    
    // 分析
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    await analyzeBtn.click()
    
    // 等待結果
    await expect(page.locator('text=您的膚色座標')).toBeVisible({ timeout: 5000 })
    
    // 驗證RGB值格式 (應該是 "xxx, xxx, xxx" 格式)
    const rgbText = page.locator('p:has-text("RGB:")')
    const rgbContent = await rgbText.textContent()
    const rgbMatch = rgbContent.match(/RGB: (\d+), (\d+), (\d+)/)
    expect(rgbMatch).toBeTruthy()
    
    // 驗證HEX值格式
    const hexText = page.locator('p:has-text("HEX:")')
    const hexContent = await hexText.textContent()
    const hexMatch = hexContent.match(/HEX: #([0-9A-F]{6})/)
    expect(hexMatch).toBeTruthy()
  })

  test('應能顯示正確的產品推薦信息', async ({ page }) => {
    // 選擇膚色 - 使用 selectOption
    const skinToneSelect = page.locator('select')
    await skinToneSelect.selectOption('偏紅暖一白')
    
    // 分析
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    await analyzeBtn.click()
    
    // 等待推薦出現
    await expect(page.locator('text=💄 推薦產品')).toBeVisible({ timeout: 5000 })
    
    // 驗證產品卡片結構
    const productCards = page.locator('div.mb-2.p-2.bg-white')
    const count = await productCards.count()
    expect(count).toBeGreaterThan(0)
    
    // 驗證每個產品都有名稱和描述
    for (let i = 0; i < count; i++) {
      const card = productCards.nth(i)
      const name = card.locator('p.font-bold').first()
      const desc = card.locator('p.text-sm')
      
      await expect(name).toBeVisible()
      await expect(desc).toBeVisible()
      
      const nameText = await name.textContent()
      const descText = await desc.textContent()
      
      expect(nameText).toBeTruthy()
      expect(descText).toBeTruthy()
    }
  })

  test('應能未選擇膚色時顯示警告', async ({ page }) => {
    // 直接點擊分析按鈕，不選擇膚色
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    
    // 監聽 alert 對話框
    page.once('dialog', dialog => {
      expect(dialog.message()).toContain('請選擇膚色類型')
      dialog.accept()
    })
    
    await analyzeBtn.click()
  })

  test('應能正確渲染所有膚色選項', async ({ page }) => {
    // Wait for page to fully load
    await page.waitForLoadState('networkidle', { timeout: 15000 })
    
    // Wait for Vue app to initialize
    await page.waitForTimeout(2000)
    
    // Get detailed info about the application state
    const appState = await page.evaluate(() => {
      return {
        skinTonesData: window.skinTonesData ? window.skinTonesData.length : 'undefined',
        selectElement: document.querySelector('select') ? 'exists' : 'not found',
        selectOptions: Array.from(document.querySelectorAll('select option')).map(o => o.textContent),
        vueApp: window.__VUE__?.app ? 'present' : 'not found'
      }
    })
    console.log('App state:', appState)
    
    const skinToneSelect = page.locator('select')
    
    // 首先檢查 select 中實際有哪些選項
    const options = await skinToneSelect.locator('option').allTextContents()
    console.log('Available options in select:', options)
    
    // 檢查是否有選項 (除了默認的 "選擇膚色類型")
    if (options.length <= 1) {
      // 如果選項仍然沒有加載，檢查控制台
      const logs = await page.evaluate(() => {
        return {
          skinTonesData: typeof window.skinTonesData !== 'undefined' ? 'loaded' : 'not loaded',
          dataLength: window.skinTonesData?.length || 0
        }
      })
      console.log('Debug info:', logs)
    }
    
    const expectedOptions = ['粉一白', '黃一白', '中一白', '橄欖一白', '偏紅冷一白']
    
    for (const option of expectedOptions) {
      // 驗證選項存在且可以選擇
      await skinToneSelect.selectOption(option)
      await expect(skinToneSelect).toHaveValue(option)
    }
  })

  test('應能多次進行膚色分析', async ({ page }) => {
    const skinToneSelect = page.locator('select')
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    
    // 第一次分析
    await skinToneSelect.selectOption('粉一白')
    await analyzeBtn.click()
    await expect(page.locator('text=粉一白')).toBeVisible({ timeout: 10000 })
    
    // 第二次分析
    await skinToneSelect.selectOption('黃三白')
    await analyzeBtn.click()
    await expect(page.locator('text=黃三白')).toBeVisible({ timeout: 10000 })
    
    console.log('✓ 多次分析成功')
  })

  test('應能驗證UI響應式設計', async ({ page, viewport }) => {
    // 驗證按鈕在各種視圖寬度下都可見
    const buttons = page.locator('button')
    
    for (let i = 0; i < await buttons.count(); i++) {
      const button = buttons.nth(i)
      await expect(button).toBeInViewport()
    }
  })
})

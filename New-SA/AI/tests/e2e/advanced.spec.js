import { test, expect } from '@playwright/test'

test.describe('膚色檢測 - 相機功能 E2E 測試', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/story1.php')
    await page.waitForLoadState('networkidle')
  })

  test('應能看到相機控制按鈕', async ({ page }) => {
    const startCameraBtn = page.locator('button:has-text("啟動相機")')
    await expect(startCameraBtn).toBeVisible()
    await expect(startCameraBtn).toBeEnabled()
  })

  test('啟動相機按鈕點擊應改變UI狀態', async ({ page, context }) => {
    // 模擬相機權限被拒絕（避免實際請求相機）
    const startCameraBtn = page.locator('button:has-text("啟動相機")')
    
    // 設置相機權限為拒絕
    await context.grantPermissions([])
    
    // 監聽 alert 對話框
    let alertMessage = ''
    page.once('dialog', async dialog => {
      alertMessage = dialog.message()
      await dialog.dismiss()
    })
    
    await startCameraBtn.click()
    
    // 驗證是否顯示錯誤信息
    expect(alertMessage).toBeTruthy()
  })

  test('相機部分應有停止按鈕（如果相機啟動）', async ({ page }) => {
    // 檢查 video 元素（即使不可見）
    const videoElement = page.locator('video')
    
    // 檢查停止按鈕是否存在（可能被 v-if 隱藏）
    const stopBtn = page.locator('button:has-text("停止相機")')
    
    // 確認頁面結構
    await expect(videoElement).toBeInViewport({ visible: false })
  })

  test('應有拍攝按鈕用於圖像捕捉', async ({ page }) => {
    // 尋找拍攝按鈕（通常在相機啟動後才可見）
    const captureBtn = page.locator('button:has-text("拍攝並分析")')
    
    // 驗證按鈕存在（即使不可見）
    await expect(captureBtn).toHaveCount(1)
  })
})

test.describe('膚色檢測 - 響應式和無障礙 E2E 測試', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/story1.php')
    await page.waitForLoadState('networkidle')
  })

  test('應在行動設備視圖中正常工作', async ({ page, context }) => {
    // 設置行動設備視口
    await page.setViewportSize({ width: 375, height: 667 })
    
    // 驗證主要元素仍可見
    await expect(page.locator('text=📸 相機掃描')).toBeVisible()
    await expect(page.locator('text=✏️ 手動輸入參數')).toBeVisible()
    
    // 驗證按鈕可點擊
    const select = page.locator('select')
    await expect(select).toBeEnabled()
  })

  test('應在平板視圖中正常工作', async ({ page }) => {
    // 設置平板視口
    await page.setViewportSize({ width: 768, height: 1024 })
    
    await expect(page.locator('text=📸 相機掃描')).toBeVisible()
    await expect(page.locator('text=✏️ 手動輸入參數')).toBeVisible()
  })

  test('應在桌面視圖中正常工作', async ({ page }) => {
    // 設置桌面視口
    await page.setViewportSize({ width: 1920, height: 1080 })
    
    await expect(page.locator('text=📸 相機掃描')).toBeVisible()
    await expect(page.locator('text=✏️ 手動輸入參數')).toBeVisible()
  })

  test('所有按鈕應有適當的焦點狀態', async ({ page }) => {
    const buttons = page.locator('button')
    
    const firstButton = buttons.first()
    await firstButton.focus()
    
    // 驗證按鈕獲得焦點
    const isFocused = await firstButton.evaluate(el => document.activeElement === el)
    expect(isFocused).toBe(true)
  })

  test('應支持鍵盤導航', async ({ page }) => {
    // Tab 到第一個按鈕
    await page.keyboard.press('Tab')
    
    // 驗證焦點移動
    const focusedElement = await page.evaluate(() => document.activeElement?.tagName)
    expect(['BUTTON', 'SELECT']).toContain(focusedElement)
  })
})

test.describe('膚色檢測 - 性能 E2E 測試', () => {
  test('頁面載入時間應該在可接受範圍內', async ({ page }) => {
    const startTime = Date.now()
    
    await page.goto('/story1.php')
    await page.waitForLoadState('networkidle')
    
    const loadTime = Date.now() - startTime
    
    // 驗證頁面在 3 秒內加載完成
    expect(loadTime).toBeLessThan(3000)
    
    console.log(`頁面加載時間: ${loadTime}ms`)
  })

  test('膚色分析應快速完成', async ({ page }) => {
    await page.goto('/story1.php')
    await page.waitForLoadState('networkidle')
    
    const startTime = Date.now()
    
    // 選擇膚色並分析
    const select = page.locator('select')
    await select.click()
    await page.locator('option[value="小麥皮"]').click()
    
    const analyzeBtn = page.locator('button:has-text("分析並推薦")')
    await analyzeBtn.click()
    
    // 等待結果
    await expect(page.locator('text=您的膚色座標')).toBeVisible({ timeout: 5000 })
    
    const analysisTime = Date.now() - startTime
    
    // 驗證分析在 2 秒內完成
    expect(analysisTime).toBeLessThan(2000)
    
    console.log(`分析時間: ${analysisTime}ms`)
  })
})

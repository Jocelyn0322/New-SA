# Playwright E2E 測試最佳實踐

## 📚 目錄
1. [選擇器策略](#選擇器策略)
2. [等待時間](#等待時間)
3. [測試結構](#測試結構)
4. [調試技巧](#調試技巧)
5. [性能優化](#性能優化)
6. [常見問題](#常見問題)

## 選擇器策略

### ✅ 推薦做法

```javascript
// 1. 使用 data-testid 屬性（最推薦）
await page.locator('[data-testid="skin-tone-select"]').click()

// 2. 使用精確的文本匹配
await page.locator('button:has-text("分析並推薦")').click()

// 3. 使用角色 (role-based) 選擇器
await page.locator('role=button[name="啟動相機"]').click()

// 4. 使用 CSS 選擇器 + text
await page.locator('select:has(option[value="冷白皮"])').click()
```

### ❌ 應避免的做法

```javascript
// 不要使用絕對 XPath
await page.locator('/html/body/div[1]/div/button[1]').click()

// 避免依賴 class 名稱（容易變動）
await page.locator('.btn-primary-123').click()

// 避免過度複雜的選擇器
await page.locator('div > div > div > p > span').click()
```

### 改進現有代碼的建議

在 `SkinToneDetector.vue` 中添加 `data-testid`:

```vue
<select v-model="skinTone" class="w-full p-3 border rounded-lg" data-testid="skin-tone-select">
  <option value="">選擇膚色類型</option>
  <option value="冷白皮">冷白皮</option>
  <!-- ... -->
</select>

<button @click="startCamera" class="..." data-testid="start-camera-btn">
  啟動相機
</button>
```

然後在測試中使用：

```javascript
// 更可靠的選擇器
await page.locator('[data-testid="skin-tone-select"]').selectOption('冷白皮')
await page.locator('[data-testid="start-camera-btn"]').click()
```

## 等待時間

### ✅ 推薦：隱式等待

```javascript
// 自動等待元素出現（最高 30 秒）
await expect(page.locator('text=您的膚色座標')).toBeVisible()

// 等待特定狀態
await expect(page.locator('button:has-text("分析並推薦")')).toBeEnabled()
```

### ⚠️ 使用顯式等待的情況

```javascript
// 等待網絡請求完成
await page.waitForLoadState('networkidle')

// 等待特定函數完成
await page.waitForFunction(() => {
  return document.querySelectorAll('[data-product]').length > 0
})

// 自定義超時
await expect(page.locator('text=推薦產品')).toBeVisible({ timeout: 10000 })
```

### ❌ 避免

```javascript
// 不要使用硬編碼延遲
await page.waitForTimeout(2000)  // ❌ 不可靠，使測試變慢

// 不要無限等待
await page.waitForTimeout(999999)
```

## 測試結構

### 推薦的測試組織方式

```javascript
import { test, expect } from '@playwright/test'

test.describe('功能模組名稱', () => {
  // 共享的 setup
  test.beforeEach(async ({ page }) => {
    await page.goto('/story1.php')
    await page.waitForLoadState('networkidle')
  })

  // 共享的 cleanup
  test.afterEach(async ({ page }) => {
    // 清理資源
  })

  test('應該完成一個具體的用戶操作', async ({ page }) => {
    // Arrange - 準備測試條件
    const select = page.locator('[data-testid="skin-tone-select"]')
    
    // Act - 執行測試動作
    await select.click()
    await page.locator('option[value="冷白皮"]').click()
    
    // Assert - 驗證結果
    await expect(select).toHaveValue('冷白皮')
  })
})
```

### 避免的反面模式

```javascript
// ❌ 一個測試做太多事情
test('測試整個應用流程', async ({ page }) => {
  // 50+ 行代碼...
})

// ❌ 測試之間的依賴
test('step 1', async ({ page }) => { /* ... */ })
test('step 2', async ({ page }) => { /* 依賴 step 1 的結果 */ })

// ❌ 使用全局狀態
let sharedValue
test('test 1', () => { sharedValue = 'value' })
test('test 2', () => { expect(sharedValue).toBe('value') })
```

## 調試技巧

### 1. 使用 Playwright Inspector

```bash
npm run test:e2e:debug
```

在 Inspector 中：
- 逐步執行測試
- 查看選擇器匹配的元素
- 檢查 DOM 和網絡請求

### 2. 查看截圖和視頻

失敗時自動保存在 `test-results/` 目錄：
- `*.png` - 失敗時的截圖
- `*.webm` - 完整的視頻記錄

### 3. 添加調試語句

```javascript
test('調試示例', async ({ page }) => {
  // 截圖
  await page.screenshot({ path: 'debug.png' })

  // 打印 HTML
  console.log(await page.content())

  // 打印特定元素
  console.log(await page.locator('select').inputValue())

  // 交互式暫停
  await page.pause()
})
```

### 4. 使用 Trace 查看器

```bash
npx playwright show-trace trace.zip
```

Trace 包含：
- 所有操作的時間線
- DOM 快照
- 網絡請求
- 視頻幀

### 5. 本地調試

```javascript
// 在測試中檢查具體值
const value = await page.locator('select').inputValue()
console.log('選中的膚色:', value)

// 驗證 CSS 樣式
const styles = await page.locator('button').evaluate(el => 
  window.getComputedStyle(el).color
)
console.log('按鈕顏色:', styles)
```

## 性能優化

### 1. 並行運行測試

```bash
# 自動並行（默認）
npm run test:e2e

# 禁用並行
npm run test:e2e -- --workers=1
```

### 2. 減少等待時間

```javascript
// ❌ 不必要的等待
await page.waitForLoadState('networkidle')
await page.waitForTimeout(1000)

// ✅ 只等待必要的元素
await page.locator('[data-testid="result"]').waitFor({ state: 'visible' })
```

### 3. 優化選擇器

```javascript
// ❌ 複雜的選擇器
await page.locator('div > div > div > button:has-text("提交")').click()

// ✅ 簡單、直接的選擇器
await page.locator('[data-testid="submit-btn"]').click()
```

## 常見問題

### Q1: 測試在本地通過但在 CI 中失敗

**原因**: 時序問題或環境差異

**解決方案**:
```javascript
// 使用可靠的等待方式
await expect(page.locator('text=結果')).toBeVisible()

// 避免硬編碼延遲
// await page.waitForTimeout(1000)  ❌

// 等待具體狀態
await page.waitForLoadState('networkidle')
```

### Q2: 選擇器找不到元素

**原因**: 元素不存在、隱藏或尚未加載

**解決方案**:
```javascript
// 1. 驗證元素是否存在
const count = await page.locator('button:has-text("分析")').count()
console.log('找到按鈕數:', count)

// 2. 檢查元素是否可見
const isVisible = await page.locator('button').isVisible()

// 3. 等待元素
await page.locator('button').waitFor({ state: 'visible', timeout: 5000 })
```

### Q3: 相機權限測試失敗

**原因**: jsdom 環境限制

**解決方案**: 
```javascript
// 跳過需要真實瀏覽器的測試
test.skip('需要相機權限', async ({ page, browserName }) => {
  // 只在本地運行，CI 中跳過
  if (process.env.CI) test.skip()
})

// 或模擬權限拒絕
await context.grantPermissions([])
```

### Q4: 檢測到多個元素，不知道選擇哪個

**解決方案**:
```javascript
// 使用過濾器
await page.locator('button').filter({ hasText: '分析並推薦' }).click()

// 使用索引
await page.locator('button').nth(0).click()

// 使用父級上下文
const card = page.locator('.product-card').first()
await card.locator('button').click()
```

## 測試模板

### 基礎功能測試模板

```javascript
import { test, expect } from '@playwright/test'

test.describe('新功能模組', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/story1.php')
  })

  test('應該完成特定操作', async ({ page }) => {
    // 準備
    const element = page.locator('[data-testid="target"]')
    
    // 執行
    await element.click()
    
    // 驗證
    await expect(page.locator('text=成功')).toBeVisible()
  })

  test('應該顯示錯誤信息', async ({ page }) => {
    // 監聽對話框
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('錯誤')
      await dialog.accept()
    })
    
    // 觸發錯誤
    await page.locator('[data-testid="invalid-btn"]').click()
  })
})
```

## 快速檢查清單

在提交測試前檢查：

- [ ] 測試名稱清晰，說明了測試內容
- [ ] 使用了 `data-testid` 或角色選擇器
- [ ] 沒有硬編碼延遲 (`waitForTimeout`)
- [ ] 每個測試都是獨立的，不依賴其他測試
- [ ] 使用了 `beforeEach` 而不是重複的設置代碼
- [ ] 測試在本地通過
- [ ] 添加了合理的註釋和文檔
- [ ] 運行了 `npm run test:e2e` 確保所有測試都通過

## 資源連結

- [Playwright 官方文檔](https://playwright.dev)
- [選擇器文檔](https://playwright.dev/docs/locators)
- [調試指南](https://playwright.dev/docs/debug)
- [最佳實踐](https://playwright.dev/docs/best-practices)

祝你測試編寫順利！🎭✨

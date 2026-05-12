# 🚀 E2E 測試快速開始

## 已完成的設置 ✅

你已經有了一個**完整的 E2E 測試環境**！以下是所有的組件：

### 📦 已安裝
- Playwright Test Framework
- 所有必要的依賴

### 📁 已創建的文件
```
/Applications/XAMPP/xamppfiles/htdocs/web/SA/
├── playwright.config.js                    # Playwright 配置
├── tests/e2e/
│   ├── skinToneDetector.spec.js           # 主要功能測試 (9 個測試)
│   └── advanced.spec.js                    # 進階測試 (8 個測試)
├── test.sh                                 # 便捷命令腳本
├── E2E_TEST_GUIDE.md                      # 詳細指南
└── PLAYWRIGHT_BEST_PRACTICES.md           # 最佳實踐文檔
```

### 📊 測試統計
- **總測試數**: 17 個
- **測試類別**:
  - ✅ 主要功能: 9 個測試
  - ✅ 相機功能: 3 個測試
  - ✅ 響應式設計: 3 個測試
  - ✅ 性能測試: 2 個測試

---

## 🎯 立即開始

### 選項 1：使用便捷腳本（推薦用於開發）

```bash
# 進入項目目錄
cd /Applications/XAMPP/xamppfiles/htdocs/web/SA

# 在 UI 模式下運行測試（互動式，推薦！）
./test.sh ui

# 或使用 npm 命令
npm run test:e2e:ui
```

**UI 模式的優勢**:
- 🖥️ 可視化查看測試執行
- ⏸️ 逐步執行測試
- 🔍 實時調試
- 🎯 選擇器生成器

### 選項 2：標準命令

```bash
# 運行所有 E2E 測試
npm run test:e2e

# 運行並生成報告
npm run test:e2e && npm run test:e2e:report

# 調試模式
npm run test:e2e:debug
```

### 選項 3：僅測試特定功能

```bash
# 僅在 Chromium 中運行
./test.sh chromium

# 運行匹配特定名稱的測試
./test.sh match "應能選擇膚色"

# 僅在 Firefox 中運行
./test.sh firefox
```

---

## 📋 完整命令參考

| 命令 | 說明 | 適用場景 |
|------|------|--------|
| `npm run test:e2e` | 運行所有 E2E 測試 | CI/CD、完整驗證 |
| `npm run test:e2e:ui` | UI 模式（推薦） | 本地開發、調試 |
| `npm run test:e2e:debug` | 逐步調試 | 調試失敗的測試 |
| `npm run test:e2e:report` | 查看 HTML 報告 | 查看測試結果 |
| `./test.sh run` | 快捷運行 | 任何時候 |
| `./test.sh match "測試名"` | 搜索特定測試 | 快速測試單個功能 |

---

## 🎭 測試覆蓋的功能

### 主要功能測試
- ✅ 膚色檢測 UI 界面渲染
- ✅ 膚色類型選擇和分析
- ✅ 所有膚色類型的產品推薦
- ✅ RGB 和 HEX 顏色值驗證
- ✅ 警告信息提示
- ✅ 多次分析
- ✅ 響應式設計

### 進階測試
- 🎥 相機功能
- 📱 行動設備適配
- 💻 桌面適配
- ⌨️ 鍵盤導航
- ⚡ 性能指標

---

## 🔧 配置細節

### Playwright 配置
- **瀏覽器**: Chromium、Firefox、WebKit
- **基礎 URL**: `http://localhost:8000`
- **Web 服務器**: PHP 內置服務器
- **失敗時**:
  - 自動截圖
  - 錄製視頻
  - 記錄 Trace
  - 自動重試 (CI 環境)

### 支持的瀏覽器
- ✅ Chrome/Chromium (最佳相容性)
- ✅ Firefox (性能測試)
- ✅ Safari/WebKit (跨瀏覽器驗證)

---

## 💡 常見任務

### 任務 1：開發時不斷運行測試

```bash
npm run test:e2e:ui
```

在 UI 中：
1. 點擊「Run」執行所有測試
2. 修改代碼
3. 自動重新運行受影響的測試

### 任務 2：調試特定失敗的測試

```bash
npm run test:e2e:debug
```

在 Inspector 中：
1. 設置斷點
2. 逐步執行
3. 檢查 DOM 和狀態

### 任務 3：查看失敗詳情

```bash
npm run test:e2e  # 運行測試
npm run test:e2e:report  # 查看報告
```

報告包含：
- 失敗的測試和原因
- 失敗時的截圖
- 視頻錄像
- Trace 日誌

### 任務 4：在 CI/CD 中運行

```bash
npm run test:e2e
```

在 CI 環境中自動：
- ✅ 禁用並行（確保穩定性）
- ✅ 失敗重試 2 次
- ✅ 收集失敗詳情
- ✅ 生成報告

---

## 📝 編寫新的 E2E 測試

### 基本模板

```javascript
import { test, expect } from '@playwright/test'

test.describe('新功能', () => {
  test.beforeEach(async ({ page }) => {
    // 每個測試前都會執行
    await page.goto('/story1.php')
  })

  test('應該完成某個操作', async ({ page }) => {
    // 查找元素
    const button = page.locator('button:has-text("按鈕文字")')
    
    // 執行操作
    await button.click()
    
    // 驗證結果
    await expect(page.locator('text=結果')).toBeVisible()
  })
})
```

### 添加到現有測試

1. 在 `tests/e2e/` 創建 `.spec.js` 文件
2. 遵循上面的模板
3. 運行 `npm run test:e2e` 驗證

---

## 🐛 故障排除

### 問題：測試找不到元素

```javascript
// 調試：打印頁面內容
console.log(await page.content())

// 或截圖查看
await page.screenshot({ path: 'debug.png' })
```

### 問題：測試超時

```javascript
// 檢查元素是否加載
await page.waitForLoadState('networkidle')

// 增加超時時間
await expect(page.locator('text=結果')).toBeVisible({ timeout: 10000 })
```

### 問題：相機權限失敗

這是正常的！相機需要真實瀏覽器和用戶許可。測試已為此做了處理。

---

## 📚 更多信息

- 📖 [詳細指南](./E2E_TEST_GUIDE.md) - 完整配置和使用說明
- 🎯 [最佳實踐](./PLAYWRIGHT_BEST_PRACTICES.md) - 測試編寫指南
- 🔗 [Playwright 官方文檔](https://playwright.dev)

---

## ✨ 下一步

1. **立即嘗試**:
   ```bash
   npm run test:e2e:ui
   ```

2. **查看測試代碼**:
   - `tests/e2e/skinToneDetector.spec.js` - 主要功能
   - `tests/e2e/advanced.spec.js` - 進階測試

3. **根據需要擴展**:
   - 添加 `data-testid` 到 Vue 組件（見最佳實踐）
   - 編寫更多 E2E 測試
   - 集成到 CI/CD 流程

4. **持續改進**:
   - 監控測試覆蓋率
   - 添加新功能的測試
   - 根據失敗調整測試

---

## 🎉 完成！

你現在有了：
- ✅ 完整的 E2E 測試套件
- ✅ 17 個覆蓋主要功能的測試
- ✅ 便捷命令和腳本
- ✅ 詳細文檔和最佳實踐
- ✅ UI 調試工具
- ✅ HTML 報告生成

**開始測試吧！** 🚀

有問題？查看 `E2E_TEST_GUIDE.md` 或 `PLAYWRIGHT_BEST_PRACTICES.md`。

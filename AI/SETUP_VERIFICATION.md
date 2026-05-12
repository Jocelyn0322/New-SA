# ✅ E2E 測試設置驗證清單

## 安裝驗證

- [x] Playwright 已安裝 (`npm list @playwright/test`)
- [x] Node.js 已安裝 (`node --version`)
- [x] npm 已安裝 (`npm --version`)

## 文件結構驗證

```
✅ /Applications/XAMPP/xamppfiles/htdocs/web/SA/
├── ✅ playwright.config.js                    配置文件
├── ✅ test.sh                                 便捷腳本
├── ✅ .gitignore                              Git 配置
├── ✅ package.json                            更新了 scripts
├── ✅ tests/
│   └── ✅ e2e/
│       ├── ✅ skinToneDetector.spec.js       主要功能測試
│       └── ✅ advanced.spec.js               進階測試
├── ✅ QUICK_START.md                         快速開始
├── ✅ E2E_TEST_GUIDE.md                      詳細指南
├── ✅ PLAYWRIGHT_BEST_PRACTICES.md           最佳實踐
└── ✅ E2E_SETUP_SUMMARY.txt                  本總結
```

## package.json 驗證

```json
✅ 已添加的 scripts:
  "test:e2e": "playwright test"
  "test:e2e:ui": "playwright test --ui"
  "test:e2e:debug": "playwright test --debug"
  "test:e2e:report": "playwright show-report"

✅ 已添加的 devDependencies:
  "@playwright/test": "^1.59.1"
```

## 測試覆蓋驗證

### 主要功能測試 (skinToneDetector.spec.js)
- [x] 膚色檢測 UI 界面
- [x] 膚色選擇和分析
- [x] 多膚色類型推薦
- [x] RGB 和 HEX 值驗證
- [x] 產品推薦信息
- [x] 警告提示
- [x] 膚色選項渲染
- [x] 多次分析
- [x] 響應式設計

### 進階測試 (advanced.spec.js)
- [x] 相機控制按鈕
- [x] 相機權限處理
- [x] 行動設備適配 (375×667)
- [x] 平板適配 (768×1024)
- [x] 桌面適配 (1920×1080)
- [x] 鍵盤導航支持
- [x] 頁面加載性能
- [x] 膚色分析性能

## 配置驗證

### playwright.config.js
```javascript
✅ testDir: './tests/e2e'           指向測試目錄
✅ baseURL: 'http://localhost:8000' 設置基礎 URL
✅ 瀏覽器: Chromium, Firefox, WebKit 多瀏覽器支持
✅ webServer: PHP 內置服務器       自動啟動 Web 服務器
✅ reporter: 'html'                生成 HTML 報告
✅ trace: 'on-first-retry'         失敗時記錄 Trace
✅ screenshot: 'only-on-failure'   失敗時截圖
✅ video: 'retain-on-failure'      失敗時錄製視頻
```

## 命令驗證

執行以下命令驗證設置：

```bash
# 1. 驗證 npm scripts 已添加
npm run

# 2. 列出測試
npm run test:e2e -- --list

# 3. 運行一個測試
npm run test:e2e -- --grep "應正確渲染膚色檢測界面" --project=chromium

# 4. UI 模式（推薦）
npm run test:e2e:ui
```

## 快速檢查

### 驗證所有文件都在正確位置

```bash
# 檢查配置文件
test -f playwright.config.js && echo "✅ playwright.config.js" || echo "❌ missing"
test -f test.sh && echo "✅ test.sh" || echo "❌ missing"
test -f QUICK_START.md && echo "✅ QUICK_START.md" || echo "❌ missing"

# 檢查測試文件
test -f tests/e2e/skinToneDetector.spec.js && echo "✅ skinToneDetector.spec.js" || echo "❌ missing"
test -f tests/e2e/advanced.spec.js && echo "✅ advanced.spec.js" || echo "❌ missing"

# 檢查 node_modules
test -d node_modules/@playwright && echo "✅ @playwright installed" || echo "❌ missing"
```

### 驗證依賴

```bash
# 檢查 @playwright/test 是否安裝
npm list @playwright/test

# 預期輸出:
# ├── @playwright/test@1.59.1
# └── ...
```

## 首次運行步驟

1. **進入項目目錄**
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/web/SA
   ```

2. **驗證安裝**
   ```bash
   npm list | grep playwright
   ```

3. **運行 UI 模式（推薦用於首次）**
   ```bash
   npm run test:e2e:ui
   ```

4. **在 UI 中**
   - 點擊「Install」安裝瀏覽器（首次）
   - 等待 2-3 分鐘（取決於網速）
   - 點擊「Run」執行測試

5. **查看結果**
   - ✅ 綠色 = 測試通過
   - ❌ 紅色 = 測試失敗
   - ⏭️ 藍色 = 測試跳過

## 故障排除

### 問題：找不到 playwright 命令

**解決方案**：
```bash
# 重新安裝依賴
rm -rf node_modules package-lock.json
npm install

# 驗證安裝
npm list @playwright/test
```

### 問題：Playwright 瀏覽器未安裝

**解決方案**：
```bash
# 安裝瀏覽器
npx playwright install

# 或在 UI 模式中點擊 Install
npm run test:e2e:ui
```

### 問題：測試超時

**解決方案**：
1. 確保 Web 服務器在運行
2. 檢查 baseURL 是否正確（localhost:8000）
3. 增加超時時間在 playwright.config.js

### 問題：權限被拒絕

**macOS 上**：
```bash
# 給腳本執行權限
chmod +x test.sh

# 驗證
ls -la test.sh  # 應該看到 rwx
```

## 下一步

- [ ] 閱讀 QUICK_START.md 了解快速開始
- [ ] 查看 E2E_TEST_GUIDE.md 了解詳細配置
- [ ] 學習 PLAYWRIGHT_BEST_PRACTICES.md 編寫最佳實踐
- [ ] 運行 `npm run test:e2e:ui` 首次執行測試
- [ ] 查看測試代碼並理解結構
- [ ] 根據需要添加新的測試

## 支持的命令速查表

| 命令 | 用途 | 適用情況 |
|------|------|--------|
| `npm run test:e2e` | 標準運行 | CI/CD、完整驗證 |
| `npm run test:e2e:ui` | UI 模式 | **推薦開發** |
| `npm run test:e2e:debug` | 調試模式 | 調試失敗 |
| `npm run test:e2e:report` | 查看報告 | 查看結果 |
| `./test.sh ui` | 腳本 UI | 快捷使用 |
| `./test.sh match "名稱"` | 搜索測試 | 運行特定測試 |

## 資源

- 🔗 [Playwright 官方文檔](https://playwright.dev)
- 📖 本項目文檔：
  - QUICK_START.md
  - E2E_TEST_GUIDE.md
  - PLAYWRIGHT_BEST_PRACTICES.md

## 驗證完成

當你能運行以下命令時，表示設置完成：

```bash
npm run test:e2e:ui
```

🎉 設置完成！享受 E2E 測試！

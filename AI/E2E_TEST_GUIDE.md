# E2E 測試配置指南

## 安裝完成 ✅

已為你的項目配置了完整的 E2E 測試套件：

### 📦 安裝的套件
- `@playwright/test` - Playwright E2E 測試框架

### 📁 創建的文件結構
```
/tests/e2e/
  ├── skinToneDetector.spec.js     # 主要功能測試
  └── advanced.spec.js             # 進階測試（響應式、性能等）

playwright.config.js              # Playwright 配置文件
```

## 🚀 運行 E2E 測試

### 基本命令

```bash
# 運行所有 E2E 測試
npm run test:e2e

# 用 UI 模式運行測試（推薦用於開發）
npm run test:e2e:ui

# 調試模式（逐步執行）
npm run test:e2e:debug

# 查看測試報告
npm run test:e2e:report
```

## 📋 測試覆蓋範圍

### `skinToneDetector.spec.js` 主要功能測試
1. ✅ 膚色檢測界面渲染
2. ✅ 膚色類型選擇和分析
3. ✅ 所有膚色類型的產品推薦
4. ✅ RGB 和 HEX 值的格式驗證
5. ✅ 產品推薦信息完整性
6. ✅ 未選擇膚色時的警告
7. ✅ 膚色選項渲染
8. ✅ 多次分析功能
9. ✅ UI 響應式設計

### `advanced.spec.js` 進階測試
1. **相機功能測試**
   - 相機控制按鈕
   - 相機權限處理
   - 拍攝和停止功能

2. **響應式測試**
   - 行動設備視圖 (375×667)
   - 平板視圖 (768×1024)
   - 桌面視圖 (1920×1080)
   - 鍵盤導航

3. **性能測試**
   - 頁面加載時間 (<3000ms)
   - 膚色分析速度 (<2000ms)

## 🌐 測試環境

項目配置為在以下瀏覽器中運行測試：
- ✅ Chromium
- ✅ Firefox
- ✅ WebKit (Safari)

## ⚙️ 配置詳情

### playwright.config.js 設置
- **測試目錄**: `./tests/e2e`
- **基礎 URL**: `http://localhost:8000`
- **Web 服務器**: PHP 內置服務器 (`php -S localhost:8000`)
- **超時**: 默認 30 秒
- **失敗時的截圖**: 啟用
- **失敗時的視頻記錄**: 啟用
- **Trace 記錄**: 第一次失敗時啟用

## 📊 測試報告

運行測試後，你可以查看：
- HTML 測試報告
- 失敗截圖和視頻
- Trace 日誌（用於調試）

## 💡 使用提示

### 本地開發
```bash
# 在 UI 模式下開發測試（推薦）
npm run test:e2e:ui
```

UI 模式提供：
- 實時測試執行
- 可視化調試
- 時間旅行調試
- 測試選擇器生成

### 調試特定測試
```bash
# 只運行特定文件的測試
npm run test:e2e -- tests/e2e/skinToneDetector.spec.js

# 只運行匹配名稱的測試
npm run test:e2e -- --grep "應能選擇膚色類型"
```

### CI/CD 集成
配置已支持 CI 環境變數：
- 在 CI 中會自動禁用並行
- 失敗測試會重試 2 次
- 視頻和截圖用於失敗調試

## 🔧 後續自定義

### 添加新的 E2E 測試
在 `tests/e2e/` 目錄下創建 `.spec.js` 文件，使用相同的測試結構。

### 修改測試配置
編輯 `playwright.config.js`：
- 改變基礎 URL
- 添加新的瀏覽器
- 調整超時設置
- 配置報告生成

### 集成到 PHP 服務器
如果使用 XAMPP：
```bash
# 修改 playwright.config.js 中的 webServer 配置
# 從當前的 localhost:8000 改為你的 XAMPP 端口
```

## ✨ 已完成的配置
- ✅ Playwright 安裝
- ✅ 完整的測試套件
- ✅ 配置文件
- ✅ NPM 腳本
- ✅ 多瀏覽器支持
- ✅ 報告和截圖設置

## 🎯 下一步

1. 確認你的應用在 `http://localhost:8000` 或 XAMPP 服務器上運行
2. 根據需要修改 `playwright.config.js` 中的 `baseURL`
3. 運行 `npm run test:e2e:ui` 開始測試
4. 根據測試結果進行應用調整

祝你測試順利！🚀

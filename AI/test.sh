#!/bin/bash

# E2E 測試快速命令集

# 顏色定義
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}🎭 膚色檢測 E2E 測試工具${NC}\n"

case "$1" in
  "run")
    echo -e "${GREEN}運行所有 E2E 測試...${NC}"
    npm run test:e2e
    ;;
  "ui")
    echo -e "${GREEN}在 UI 模式下運行測試...${NC}"
    npm run test:e2e:ui
    ;;
  "debug")
    echo -e "${GREEN}以調試模式運行測試...${NC}"
    npm run test:e2e:debug
    ;;
  "report")
    echo -e "${GREEN}查看測試報告...${NC}"
    npm run test:e2e:report
    ;;
  "chromium")
    echo -e "${GREEN}僅在 Chromium 中運行測試...${NC}"
    npm run test:e2e -- --project=chromium
    ;;
  "firefox")
    echo -e "${GREEN}僅在 Firefox 中運行測試...${NC}"
    npm run test:e2e -- --project=firefox
    ;;
  "webkit")
    echo -e "${GREEN}僅在 WebKit 中運行測試...${NC}"
    npm run test:e2e -- --project=webkit
    ;;
  "match")
    if [ -z "$2" ]; then
      echo -e "${YELLOW}用法: ./test.sh match \"測試名稱\"${NC}"
    else
      echo -e "${GREEN}運行匹配 '$2' 的測試...${NC}"
      npm run test:e2e -- --grep "$2"
    fi
    ;;
  *)
    echo -e "${BLUE}可用命令:${NC}"
    echo -e "  ${GREEN}./test.sh run${NC}         - 運行所有 E2E 測試"
    echo -e "  ${GREEN}./test.sh ui${NC}          - 在 UI 模式下運行（推薦開發用）"
    echo -e "  ${GREEN}./test.sh debug${NC}       - 調試模式運行"
    echo -e "  ${GREEN}./test.sh report${NC}      - 查看最後的測試報告"
    echo -e "  ${GREEN}./test.sh chromium${NC}    - 僅在 Chromium 中運行"
    echo -e "  ${GREEN}./test.sh firefox${NC}     - 僅在 Firefox 中運行"
    echo -e "  ${GREEN}./test.sh webkit${NC}      - 僅在 WebKit 中運行"
    echo -e "  ${GREEN}./test.sh match \"名稱\"${NC}  - 運行匹配名稱的測試"
    ;;
esac

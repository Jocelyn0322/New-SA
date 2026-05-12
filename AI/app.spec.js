import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import App from './App.vue'

describe('Sprint 1 核心功能驗收', () => {
  
  it('當勾選「乾性肌」時，應正確過濾含酒精產品 (Story 1.2)', async () => {
    const wrapper = mount(App)
    
    // 1. 初始狀態（假設預設為乾肌）
    expect(wrapper.text()).toContain('Dior')
    expect(wrapper.text()).not.toContain('YSL') // YSL 含酒精，應被過濾
    
    // 2. 取消勾選乾肌
    const checkbox = wrapper.find('input[type="checkbox"]')
    await checkbox.setChecked(false)
    
    // 3. 應顯示所有產品
    expect(wrapper.text()).toContain('YSL')
    expect(wrapper.text()).toContain('Dior')
  })

  it('應顯示與使用者膚色座標匹配度最高的產品 (Story 2.1)', () => {
    const wrapper = mount(App)
    // 驗證 Mock 數據中的匹配度百分比是否正確渲染
    expect(wrapper.text()).toContain('95%')
    expect(wrapper.text()).toContain('匹配度')
  })
})

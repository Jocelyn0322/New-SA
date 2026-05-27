/* interactions.js — 全站互動動畫
   ① Hero 視差  ② 數字計數  ③ 按鈕漣漪  ④ 卡片 3D 傾斜 */
(function(){ 'use strict';

  /* ══════════════════════════════════════
     ① Hero 視差 — 游標移動時文字微微偏移
  ══════════════════════════════════════ */
  document.querySelectorAll('.hero, .ail-hero').forEach(hero => {
    const layer = hero.querySelector('.hero-content, .ail-hero__inner');
    if(!layer) return;

    let raf = null;
    let tx = 0, ty = 0;

    hero.addEventListener('mousemove', e => {
      const r  = hero.getBoundingClientRect();
      const dx = (e.clientX - r.left - r.width  / 2) / r.width;
      const dy = (e.clientY - r.top  - r.height / 2) / r.height;
      tx = dx * -14; ty = dy * -9;
      if(!raf) raf = requestAnimationFrame(applyParallax);
    });

    hero.addEventListener('mouseleave', () => {
      tx = 0; ty = 0;
      if(!raf) raf = requestAnimationFrame(applyParallax);
    });

    function applyParallax(){
      layer.style.transform = `translate(${tx}px,${ty}px)`;
      raf = null;
    }
    layer.style.transition = 'transform .12s ease';
  });


  /* ══════════════════════════════════════
     ② 數字計數動畫 — 捲動到畫面才開始跑
  ══════════════════════════════════════ */
  document.querySelectorAll('.hero-stat-num').forEach(el => {
    const raw = parseInt(el.textContent.replace(/\D/g,''));
    if(isNaN(raw)) return;
    const suffix = el.textContent.replace(/[\d]/g,'').trim();
    el.textContent = '0' + suffix;

    const io = new IntersectionObserver(entries => {
      if(!entries[0].isIntersecting) return;
      io.disconnect();
      const t0 = performance.now();
      const dur = 1400;
      (function tick(ts){
        const p = Math.min((ts - t0) / dur, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(raw * ease) + suffix;
        p < 1 && requestAnimationFrame(tick);
      })(performance.now());
    }, { threshold: .6 });
    io.observe(el);
  });


  /* ══════════════════════════════════════
     ③ 按鈕點擊漣漪
  ══════════════════════════════════════ */
  if(!document.getElementById('ia-ripple-kf')){
    const s = document.createElement('style');
    s.id = 'ia-ripple-kf';
    s.textContent = '@keyframes iaRipple{to{transform:scale(1);opacity:0}}';
    document.head.appendChild(s);
  }

  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn, .btn-submit');
    if(!btn) return;
    const r = btn.getBoundingClientRect();
    const sz = Math.max(r.width, r.height) * 2.2;
    const rip = document.createElement('span');
    rip.style.cssText =
      `position:absolute;border-radius:50%;pointer-events:none;` +
      `width:${sz}px;height:${sz}px;` +
      `left:${e.clientX - r.left - sz/2}px;top:${e.clientY - r.top - sz/2}px;` +
      `background:rgba(255,255,255,.28);transform:scale(0);opacity:1;` +
      `animation:iaRipple .5s ease-out forwards;`;
    const pos = getComputedStyle(btn).position;
    if(pos === 'static') btn.style.position = 'relative';
    btn.style.overflow = 'hidden';
    btn.appendChild(rip);
    setTimeout(() => rip.remove(), 550);
  });


  /* ══════════════════════════════════════
     ④ 產品卡片 3D 傾斜
  ══════════════════════════════════════ */
  function applyTilt(card){
    if(card._tilt) return;
    card._tilt = true;
    card.addEventListener('mousemove', e => {
      const r  = card.getBoundingClientRect();
      const rx = ((e.clientY - r.top  - r.height/2) / (r.height/2)) * -6;
      const ry = ((e.clientX - r.left - r.width /2) / (r.width /2)) *  6;
      card.style.transform = `perspective(700px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-4px) scale(1.01)`;
      card.style.transition = 'transform .08s ease';
      card.style.zIndex = '2';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
      card.style.transition = 'transform .4s ease';
      card.style.zIndex = '';
    });
  }

  document.querySelectorAll('.product-card').forEach(applyTilt);

  new MutationObserver(muts => {
    muts.forEach(m => m.addedNodes.forEach(n => {
      if(!n.querySelectorAll) return;
      if(n.classList && n.classList.contains('product-card')) applyTilt(n);
      n.querySelectorAll('.product-card').forEach(applyTilt);
    }));
  }).observe(document.body, { childList:true, subtree:true });

})();

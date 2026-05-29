/* fireworks.js — 白色煙火 canvas 動畫
   用法：在 hero 裡放 <canvas class="fw-canvas"></canvas>
   並引入此檔案即可，支援同頁多個 hero */
(function(){
  function initCanvas(canvas){
    const container = canvas.parentElement;
    const ctx = canvas.getContext('2d');

    function resize(){
      canvas.width  = container.offsetWidth;
      canvas.height = container.offsetHeight;
    }
    resize();
    new ResizeObserver(resize).observe(container);

    /* ── Particle ── */
    class Particle{
      constructor(x,y,color){
        this.x=x; this.y=y;
        const angle=Math.random()*Math.PI*2;
        const spd=1.8+Math.random()*3.8;
        this.vx=Math.cos(angle)*spd;
        this.vy=Math.sin(angle)*spd;
        this.alpha=1;
        this.decay=.013+Math.random()*.017;
        this.r=1.4+Math.random()*2;
        this.color=color;
        this.gravity=.055;
        this.tail=[];
      }
      update(){
        this.tail.push({x:this.x,y:this.y,a:this.alpha});
        if(this.tail.length>6) this.tail.shift();
        this.vy+=this.gravity;
        this.x+=this.vx; this.y+=this.vy;
        this.vx*=.97;
        this.alpha-=this.decay;
      }
      draw(){
        this.tail.forEach((t,i)=>{
          const a=t.a*(i/this.tail.length)*.35;
          ctx.beginPath();
          ctx.arc(t.x,t.y,this.r*.55,0,Math.PI*2);
          ctx.fillStyle=this.color.replace('__A__',a.toFixed(2));
          ctx.fill();
        });
        ctx.beginPath();
        ctx.arc(this.x,this.y,this.r,0,Math.PI*2);
        ctx.fillStyle=this.color.replace('__A__',this.alpha.toFixed(2));
        ctx.shadowBlur=8; ctx.shadowColor='rgba(255,255,255,.55)';
        ctx.fill(); ctx.shadowBlur=0;
      }
    }

    /* ── Rocket ── */
    class Rocket{
      constructor(){
        this.x=canvas.width*(.12+Math.random()*.76);
        this.y=canvas.height;
        this.tx=this.x+(Math.random()-.5)*120;
        this.ty=canvas.height*(.08+Math.random()*.52);
        const dx=this.tx-this.x, dy=this.ty-this.y;
        const dist=Math.sqrt(dx*dx+dy*dy);
        this.vx=(dx/dist)*7.5;
        this.vy=(dy/dist)*7.5;
        this.trail=[];
        this.done=false;
      }
      update(){
        this.trail.push({x:this.x,y:this.y});
        if(this.trail.length>10) this.trail.shift();
        this.x+=this.vx; this.y+=this.vy;
        if(this.y<=this.ty) this.done=true;
      }
      draw(){
        this.trail.forEach((p,i)=>{
          const a=(i/this.trail.length)*.65;
          ctx.beginPath();
          ctx.arc(p.x,p.y,2,0,Math.PI*2);
          ctx.fillStyle=`rgba(255,235,180,${a})`;
          ctx.fill();
        });
      }
    }

    /* 白 / 淡粉 / 淡金 / 淡紫 / 淡藍 */
    const COLORS=[
      'rgba(255,255,255,__A__)',
      'rgba(255,228,228,__A__)',
      'rgba(255,245,195,__A__)',
      'rgba(235,220,255,__A__)',
      'rgba(200,238,255,__A__)',
    ];

    const particles=[], rockets=[];

    function explode(x,y){
      const col=COLORS[Math.floor(Math.random()*COLORS.length)];
      const n=55+Math.floor(Math.random()*45);
      for(let i=0;i<n;i++) particles.push(new Particle(x,y,col));
    }

    function launch(){ rockets.push(new Rocket()); }

    launch();
    const t1=setInterval(()=>launch(), 1800+Math.random()*1400);
    const t2=setInterval(()=>launch(), 2700+Math.random()*1100);

    let running=true;
    function loop(){
      if(!running) return;
      ctx.clearRect(0,0,canvas.width,canvas.height);
      for(let i=rockets.length-1;i>=0;i--){
        const r=rockets[i]; r.update(); r.draw();
        if(r.done){ explode(r.x,r.y); rockets.splice(i,1); }
      }
      for(let i=particles.length-1;i>=0;i--){
        const p=particles[i]; p.update(); p.draw();
        if(p.alpha<=0) particles.splice(i,1);
      }
      requestAnimationFrame(loop);
    }
    loop();

    /* pause when tab hidden */
    document.addEventListener('visibilitychange',()=>{
      running=!document.hidden;
      if(running) loop();
    });
  }

  document.querySelectorAll('.fw-canvas').forEach(initCanvas);
})();

<!-- bg_canvas.php — animated background, include before </body> on every page -->
<!-- Pass $bg_mode = 'login' | 'dashboard' (default) before including -->
<?php $bg_mode = $bg_mode ?? 'dashboard'; ?>

<canvas id="bgCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:0;opacity:<?php echo $bg_mode === 'login' ? '1' : '0.55'; ?>;"></canvas>

<script>
(function() {
  const canvas = document.getElementById('bgCanvas');
  const ctx    = canvas.getContext('2d');
  const MODE   = '<?php echo $bg_mode; ?>';
  const IS_LOGIN = MODE === 'login';

  // ── Config ──────────────────────────────────────────────────
  const CFG = {
    nodeCount:    IS_LOGIN ? 55 : 38,
    speed:        IS_LOGIN ? 0.42 : 0.22,
    lineDistance: IS_LOGIN ? 160 : 130,
    nodeRadius:   IS_LOGIN ? [1.5, 3.5] : [1.2, 2.8],
    hexCount:     IS_LOGIN ? 7 : 4,
    hexSpeed:     IS_LOGIN ? 0.008 : 0.004,
    hexOpacity:   IS_LOGIN ? 0.10 : 0.055,
    ringCount:    IS_LOGIN ? 3 : 2,
    pulseSpeed:   IS_LOGIN ? 0.012 : 0.007,
    // Colors
    nodeColor:    IS_LOGIN ? 'rgba(212,168,67,'  : 'rgba(100,140,255,',   // gold login, blue dash
    lineColor:    IS_LOGIN ? 'rgba(212,168,67,'  : 'rgba(100,140,220,',
    hexColor:     IS_LOGIN ? 'rgba(212,168,67,'  : 'rgba(26,64,153,',
    ringColor:    IS_LOGIN ? 'rgba(212,168,67,'  : 'rgba(26,64,153,',
    bgBase:       IS_LOGIN ? null : null, // body handles bg
  };

  let W, H, nodes, hexagons, pulseRings, raf;

  // ── Resize ───────────────────────────────────────────────────
  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  // ── Node class ───────────────────────────────────────────────
  class Node {
    constructor() { this.reset(true); }
    reset(init) {
      this.x  = Math.random() * W;
      this.y  = init ? Math.random() * H : (Math.random() < 0.5 ? -10 : H + 10);
      this.vx = (Math.random() - 0.5) * CFG.speed;
      this.vy = (Math.random() - 0.5) * CFG.speed;
      this.r  = CFG.nodeRadius[0] + Math.random() * (CFG.nodeRadius[1] - CFG.nodeRadius[0]);
      this.opacity = 0.25 + Math.random() * 0.55;
      this.twinkle = Math.random() * Math.PI * 2;
      this.twinkleSpeed = 0.008 + Math.random() * 0.015;
    }
    update() {
      this.x += this.vx;
      this.y += this.vy;
      this.twinkle += this.twinkleSpeed;
      if (this.x < -20 || this.x > W + 20 || this.y < -20 || this.y > H + 20) this.reset(false);
    }
    draw() {
      const op = this.opacity * (0.7 + 0.3 * Math.sin(this.twinkle));
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
      ctx.fillStyle = CFG.nodeColor + op + ')';
      ctx.fill();
    }
  }

  // ── Hexagon class ────────────────────────────────────────────
  class Hexagon {
    constructor() {
      this.x       = Math.random() * W;
      this.y       = Math.random() * H;
      this.size    = 40 + Math.random() * 80;
      this.angle   = Math.random() * Math.PI * 2;
      this.rotSpeed= (Math.random() - 0.5) * CFG.hexSpeed;
      this.opacity = CFG.hexOpacity * (0.5 + Math.random() * 0.5);
      this.drift   = { x: (Math.random() - 0.5) * 0.15, y: (Math.random() - 0.5) * 0.15 };
    }
    update() {
      this.angle += this.rotSpeed;
      this.x += this.drift.x;
      this.y += this.drift.y;
      if (this.x < -100) this.x = W + 100;
      if (this.x > W + 100) this.x = -100;
      if (this.y < -100) this.y = H + 100;
      if (this.y > H + 100) this.y = -100;
    }
    draw() {
      ctx.save();
      ctx.translate(this.x, this.y);
      ctx.rotate(this.angle);
      ctx.beginPath();
      for (let i = 0; i < 6; i++) {
        const a = (Math.PI / 3) * i;
        i === 0 ? ctx.moveTo(Math.cos(a) * this.size, Math.sin(a) * this.size)
                : ctx.lineTo(Math.cos(a) * this.size, Math.sin(a) * this.size);
      }
      ctx.closePath();
      ctx.strokeStyle = CFG.hexColor + this.opacity + ')';
      ctx.lineWidth = 1;
      ctx.stroke();
      ctx.restore();
    }
  }

  // ── Pulse ring class ─────────────────────────────────────────
  class PulseRing {
    constructor() { this.reset(); }
    reset() {
      this.x      = Math.random() * W;
      this.y      = Math.random() * H;
      this.radius = 0;
      this.maxR   = 120 + Math.random() * 160;
      this.speed  = CFG.pulseSpeed * (0.5 + Math.random());
      this.delay  = Math.random() * 300;
    }
    update() {
      if (this.delay > 0) { this.delay--; return; }
      this.radius += this.speed * this.maxR * 0.012;
      if (this.radius > this.maxR) this.reset();
    }
    draw() {
      if (this.delay > 0 || this.radius <= 0) return;
      const op = (1 - this.radius / this.maxR) * 0.12;
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
      ctx.strokeStyle = CFG.ringColor + op + ')';
      ctx.lineWidth = 1.5;
      ctx.stroke();
    }
  }

  // ── Draw connecting lines ─────────────────────────────────────
  function drawLines() {
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x;
        const dy = nodes[i].y - nodes[j].y;
        const d  = Math.sqrt(dx * dx + dy * dy);
        if (d < CFG.lineDistance) {
          const op = (1 - d / CFG.lineDistance) * 0.35;
          ctx.beginPath();
          ctx.moveTo(nodes[i].x, nodes[i].y);
          ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = CFG.lineColor + op + ')';
          ctx.lineWidth = 0.6;
          ctx.stroke();
        }
      }
    }
  }

  // ── Init ──────────────────────────────────────────────────────
  function init() {
    resize();
    nodes     = Array.from({ length: CFG.nodeCount }, () => new Node());
    hexagons  = Array.from({ length: CFG.hexCount },  () => new Hexagon());
    pulseRings= Array.from({ length: CFG.ringCount }, () => new PulseRing());
  }

  // ── Render loop ───────────────────────────────────────────────
  function draw() {
    ctx.clearRect(0, 0, W, H);

    hexagons.forEach(h => { h.update(); h.draw(); });
    pulseRings.forEach(r => { r.update(); r.draw(); });
    drawLines();
    nodes.forEach(n => { n.update(); n.draw(); });

    raf = requestAnimationFrame(draw);
  }

  // ── Boot ──────────────────────────────────────────────────────
  window.addEventListener('resize', () => { resize(); });
  init();
  draw();
})();
</script>
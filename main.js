// ── Particle Network Background ──
const canvas = document.getElementById("canvas");
const ctx = canvas.getContext("2d");
let W,
  H,
  particles = [];

function resize() {
  W = canvas.width = window.innerWidth;
  H = canvas.height = window.innerHeight;
}
resize();
window.addEventListener("resize", resize);

class Particle {
  constructor() {
    this.reset();
  }

  reset() {
    this.x = Math.random() * W;
    this.y = Math.random() * H;
    this.size = Math.random() * 1.4 + 0.3;
    this.vx = (Math.random() - 0.5) * 0.28;
    this.vy = (Math.random() - 0.5) * 0.28;
    this.op = Math.random() * 0.35 + 0.05;
    this.life = 0;
    this.max = Math.random() * 220 + 80;
  }

  update() {
    this.x += this.vx;
    this.y += this.vy;
    this.life++;
    if (
      this.life > this.max ||
      this.x < 0 ||
      this.x > W ||
      this.y < 0 ||
      this.y > H
    ) {
      this.reset();
    }
  }

  draw() {
    ctx.save();
    ctx.globalAlpha = this.op * (1 - this.life / this.max);
    ctx.fillStyle = "#60a5fa";
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }
}

for (let i = 0; i < 110; i++) particles.push(new Particle());

function drawConnections() {
  for (let i = 0; i < particles.length; i++) {
    for (let j = i + 1; j < particles.length; j++) {
      const dx = particles[i].x - particles[j].x;
      const dy = particles[i].y - particles[j].y;
      const d = Math.sqrt(dx * dx + dy * dy);
      if (d < 95) {
        ctx.save();
        ctx.globalAlpha = (1 - d / 95) * 0.055;
        ctx.strokeStyle = "#3b82f6";
        ctx.lineWidth = 0.5;
        ctx.beginPath();
        ctx.moveTo(particles[i].x, particles[i].y);
        ctx.lineTo(particles[j].x, particles[j].y);
        ctx.stroke();
        ctx.restore();
      }
    }
  }
}

function animate() {
  ctx.clearRect(0, 0, W, H);
  particles.forEach((p) => {
    p.update();
    p.draw();
  });
  drawConnections();
  requestAnimationFrame(animate);
}
animate();

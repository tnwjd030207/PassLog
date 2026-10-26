/* SQLD 합격 훈련 — Canvas 2D 차트 모음 (외부 라이브러리 없음)
 * 사용: <canvas data-chart="trend|gauge|radar|ring" ...> + [data-count] 숫자 카운트업 + [data-confetti] 축하 효과
 * 최신 Canvas API: roundRect, createConicGradient, Path2D, ResizeObserver, devicePixelRatio, rAF
 */
(function () {
  'use strict';
  document.documentElement.classList.add('js');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var TAU = Math.PI * 2;

  function css(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }
  function pal() {
    return {
      ink: css('--ink', '#e8edf6'), muted: css('--muted', '#8d98b0'), line: css('--line', 'rgba(255,255,255,.12)'),
      a: css('--accent', '#5eead4'), b: css('--accent2', '#8b5cf6'), c: css('--accent3', '#f472b6'),
      ok: css('--ok', '#34d399'), bad: css('--bad', '#fb7185'), warn: css('--warn', '#fbbf24')
    };
  }
  function ease(t) { return 1 - Math.pow(1 - t, 3); }
  function animate(ms, step, done) {
    if (reduce) { step(1); if (done) done(); return; }
    var t0 = performance.now();
    (function f(now) {
      var t = Math.min(1, (now - t0) / ms);
      step(ease(t));
      if (t < 1) requestAnimationFrame(f); else if (done) done();
    })(t0);
  }
  /** 캔버스를 CSS 크기 × devicePixelRatio 로 맞추고 2D 컨텍스트와 CSS 크기를 돌려준다 */
  function fit(cv, fixedW, fixedH) {
    var dpr = Math.min(window.devicePixelRatio || 1, 3);
    var w = fixedW || cv.clientWidth, h = fixedH || cv.clientHeight;
    if (!w || !h) return null;
    if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(h * dpr)) {
      cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr);
    }
    var ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    return { ctx: ctx, w: w, h: h };
  }
  function conic(ctx, cx, cy, start, stops) {
    if (ctx.createConicGradient) {
      var g = ctx.createConicGradient(start, cx, cy);
      stops.forEach(function (s) { g.addColorStop(s[0], s[1]); });
      return g;
    }
    return stops[0][1];
  }

  // ---- 링(타이머용): 남은 비율 0~1 ----------------------------------------
  function drawRing(cv, ratio, opts) {
    opts = opts || {};
    var W = parseInt(cv.getAttribute('width'), 10) || 84;
    var f = fit(cv, cv._cssW || (cv._cssW = cv.clientWidth || W), cv._cssH || (cv._cssH = cv.clientHeight || W));
    if (!f) return;
    var ctx = f.ctx, P = pal(), s = Math.min(f.w, f.h), cx = f.w / 2, cy = f.h / 2, r = s / 2 - 7;
    ctx.lineWidth = 7; ctx.lineCap = 'round';
    ctx.strokeStyle = 'rgba(255,255,255,.08)';
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, TAU); ctx.stroke();
    if (ratio > 0) {
      var col = opts.warn ? [[0, P.warn], [1, P.bad]] : [[0, P.a], [1, P.b]];
      ctx.strokeStyle = conic(ctx, cx, cy, -Math.PI / 2, col.map(function (c, i) { return [i * ratio, c[1]]; }).concat([[1, col[1][1]]]));
      ctx.shadowColor = opts.warn ? P.bad : P.a; ctx.shadowBlur = 10;
      ctx.beginPath(); ctx.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + TAU * ratio); ctx.stroke();
      ctx.shadowBlur = 0;
    }
  }

  // ---- 게이지(점수/정답률) ------------------------------------------------
  function gauge(cv) {
    var val = Math.max(0, Math.min(100, parseFloat(cv.dataset.value) || 0));
    var line = parseFloat(cv.dataset.line) || 0;
    var label = cv.dataset.label || '', sub = cv.dataset.sub || '', big = cv.dataset.big === '1';
    var num = parseFloat(label);
    var counting = /^\d+$/.test(label);
    function render(p) {
      var f = fit(cv, cv._w || (cv._w = cv.clientWidth || parseInt(cv.getAttribute('width'), 10)), cv._h || (cv._h = cv.clientHeight || parseInt(cv.getAttribute('height'), 10)));
      if (!f) return;
      var ctx = f.ctx, P = pal(), s = Math.min(f.w, f.h), cx = f.w / 2, cy = f.h / 2;
      var lw = s * (big ? 0.085 : 0.095), r = s / 2 - lw * 1.1 - 6;
      var bad = line && val < line;
      // 트랙
      ctx.lineWidth = lw; ctx.lineCap = 'round';
      ctx.strokeStyle = 'rgba(255,255,255,.08)';
      ctx.beginPath(); ctx.arc(cx, cy, r, 0, TAU); ctx.stroke();
      // 값 호 (원뿔 그라디언트)
      var a = val / 100 * p;
      if (a > 0.004) {
        var c1 = bad ? P.warn : P.a, c2 = bad ? P.bad : P.b;
        ctx.strokeStyle = conic(ctx, cx, cy, -Math.PI / 2, [[0, c1], [Math.max(0.01, a), c2], [1, c2]]);
        ctx.shadowColor = bad ? P.bad : P.a; ctx.shadowBlur = lw * 1.4;
        ctx.beginPath(); ctx.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + TAU * a); ctx.stroke();
        ctx.shadowBlur = 0;
      }
      // 기준선 눈금
      if (line) {
        var ang = -Math.PI / 2 + TAU * line / 100, ro = r + lw / 2 + 3, ri = r - lw / 2 - 3;
        ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.lineWidth = 2; ctx.lineCap = 'butt';
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(ang) * ri, cy + Math.sin(ang) * ri);
        ctx.lineTo(cx + Math.cos(ang) * ro, cy + Math.sin(ang) * ro);
        ctx.stroke();
      }
      // 가운데 글자
      ctx.fillStyle = P.ink; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      var shown = counting ? String(Math.round(num * p)) : label;
      ctx.font = '800 ' + Math.round(s * (big ? 0.3 : 0.2)) + 'px Pretendard, "Noto Sans KR", system-ui, sans-serif';
      ctx.fillText(shown, cx, cy - (sub ? s * 0.02 : 0));
      if (sub) {
        ctx.fillStyle = P.muted; ctx.font = '600 ' + Math.round(s * 0.09) + 'px Pretendard, "Noto Sans KR", system-ui, sans-serif';
        ctx.fillText(sub, cx, cy + s * 0.17);
      }
    }
    onVisible(cv, function () { animate(1100, render); });
    watchResize(cv, function () { cv._w = cv._h = 0; render(1); });
  }

  // ---- 점수 추이 (선 + 면적 + 호버 툴팁) ------------------------------------
  function trend(cv) {
    var pts;
    try { pts = JSON.parse(cv.dataset.points || '[]'); } catch (e) { pts = []; }
    if (!pts.length) return;
    var href = cv.dataset.href || '';
    var hover = -1, prog = 0, geo = [];
    function render() {
      var f = fit(cv);
      if (!f) return;
      var ctx = f.ctx, P = pal(), W = f.w, H = f.h;
      var L = 34, R = 14, T = 16, B = 26, n = pts.length;
      var X = function (i) { return n === 1 ? (L + W - R) / 2 : L + (W - L - R) * i / (n - 1); };
      var Y = function (v) { return T + (H - T - B) * (1 - v / 100); };
      ctx.font = '600 10.5px Pretendard, "Noto Sans KR", system-ui, sans-serif';
      ctx.textBaseline = 'middle';
      // 격자
      [0, 20, 40, 60, 80, 100].forEach(function (g) {
        ctx.strokeStyle = g === 60 ? 'transparent' : 'rgba(255,255,255,.07)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(L, Y(g)); ctx.lineTo(W - R, Y(g)); ctx.stroke();
        ctx.fillStyle = P.muted; ctx.textAlign = 'right'; ctx.fillText(g, L - 8, Y(g));
      });
      // 합격선
      ctx.save(); ctx.setLineDash([5, 5]); ctx.strokeStyle = P.warn; ctx.globalAlpha = .75; ctx.lineWidth = 1.3;
      ctx.beginPath(); ctx.moveTo(L, Y(60)); ctx.lineTo(W - R, Y(60)); ctx.stroke(); ctx.restore();
      ctx.fillStyle = P.warn; ctx.textAlign = 'left'; ctx.fillText('합격선', L + 4, Y(60) - 8);
      // 선·면적은 progress 만큼만 (왼→오 그려지는 효과)
      geo = pts.map(function (p, i) { return { x: X(i), y: Y(p.s) }; });
      ctx.save();
      ctx.beginPath(); ctx.rect(0, 0, L + (W - L) * prog, H); ctx.clip();
      if (n > 1) {
        var path = new Path2D();
        geo.forEach(function (g, i) { if (!i) path.moveTo(g.x, g.y); else path.lineTo(g.x, g.y); });
        var area = new Path2D(path);
        area.lineTo(geo[n - 1].x, Y(0)); area.lineTo(geo[0].x, Y(0)); area.closePath();
        var gr = ctx.createLinearGradient(0, T, 0, H - B);
        gr.addColorStop(0, 'rgba(94,234,212,.34)'); gr.addColorStop(1, 'rgba(139,92,246,0)');
        ctx.fillStyle = gr; ctx.fill(area);
        var lg = ctx.createLinearGradient(L, 0, W - R, 0);
        lg.addColorStop(0, P.a); lg.addColorStop(1, P.b);
        ctx.strokeStyle = lg; ctx.lineWidth = 2.6; ctx.lineJoin = 'round'; ctx.shadowColor = P.a; ctx.shadowBlur = 8;
        ctx.stroke(path); ctx.shadowBlur = 0;
      }
      // 점 (모의고사는 마름모)
      pts.forEach(function (p, i) {
        var g = geo[i], col = p.p ? P.ok : P.bad, rr = i === hover ? 7 : 5;
        ctx.fillStyle = col; ctx.strokeStyle = 'rgba(7,10,20,.9)'; ctx.lineWidth = 2;
        ctx.beginPath();
        if (p.m) { ctx.moveTo(g.x, g.y - rr - 1); ctx.lineTo(g.x + rr + 1, g.y); ctx.lineTo(g.x, g.y + rr + 1); ctx.lineTo(g.x - rr - 1, g.y); ctx.closePath(); }
        else ctx.arc(g.x, g.y, rr, 0, TAU);
        ctx.fill(); ctx.stroke();
      });
      ctx.restore();
      // x 라벨
      ctx.fillStyle = P.muted; ctx.textAlign = 'center';
      var step = Math.ceil(n / Math.max(2, Math.floor((W - L) / 46)));
      pts.forEach(function (p, i) { if (i % step === 0) ctx.fillText(p.l, geo[i].x, H - 9); });
      // 툴팁
      if (hover >= 0 && prog >= 1) {
        var g = geo[hover], p = pts[hover], text = p.s + '점' + (p.m ? ' · 모의고사' : '') + ' · ' + p.l;
        ctx.font = '700 12px Pretendard, "Noto Sans KR", system-ui, sans-serif';
        var tw = ctx.measureText(text).width + 18, tx = Math.min(W - tw - 4, Math.max(4, g.x - tw / 2)), ty = g.y - 40;
        if (ty < 2) ty = g.y + 14;
        ctx.fillStyle = 'rgba(20,26,46,.96)'; ctx.strokeStyle = 'rgba(255,255,255,.18)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.roundRect(tx, ty, tw, 26, 8); ctx.fill(); ctx.stroke();
        ctx.fillStyle = P.ink; ctx.textAlign = 'center'; ctx.fillText(text, tx + tw / 2, ty + 13.5);
      }
    }
    function nearest(e) {
      var rc = cv.getBoundingClientRect(), x = e.clientX - rc.left, y = e.clientY - rc.top, best = -1, bd = 22;
      geo.forEach(function (g, i) { var d = Math.hypot(g.x - x, g.y - y); if (d < bd) { bd = d; best = i; } });
      return best;
    }
    cv.addEventListener('pointermove', function (e) {
      var i = nearest(e);
      cv.style.cursor = i >= 0 && href ? 'pointer' : 'default';
      if (i !== hover) { hover = i; render(); }
    });
    cv.addEventListener('pointerleave', function () { hover = -1; render(); });
    cv.addEventListener('click', function (e) {
      var i = nearest(e);
      if (i >= 0 && href) location.href = href + pts[i].id;
    });
    onVisible(cv, function () { animate(1000, function (p) { prog = p; render(); }); });
    watchResize(cv, render);
  }

  // ---- 레이더(단원별 정답률) ----------------------------------------------
  function radar(cv) {
    var pts;
    try { pts = JSON.parse(cv.dataset.points || '[]'); } catch (e) { pts = []; }
    if (pts.length < 3) { cv.hidden = true; return; }
    var prog = 0;
    function render() {
      var f = fit(cv);
      if (!f) return;
      var ctx = f.ctx, P = pal(), n = pts.length, cx = f.w / 2, cy = f.h / 2, R = Math.min(f.w, f.h) / 2 - 46;
      var ang = function (i) { return -Math.PI / 2 + TAU * i / n; };
      ctx.lineWidth = 1;
      [0.25, 0.5, 0.75, 1].forEach(function (k) {
        ctx.strokeStyle = 'rgba(255,255,255,' + (k === 0.4 ? .3 : .09) + ')';
        ctx.beginPath();
        for (var i = 0; i < n; i++) { var x = cx + Math.cos(ang(i)) * R * k, y = cy + Math.sin(ang(i)) * R * k; if (!i) ctx.moveTo(x, y); else ctx.lineTo(x, y); }
        ctx.closePath(); ctx.stroke();
      });
      ctx.strokeStyle = 'rgba(255,255,255,.07)';
      for (var i = 0; i < n; i++) { ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(ang(i)) * R, cy + Math.sin(ang(i)) * R); ctx.stroke(); }
      // 40% 과락선(점선)
      ctx.save(); ctx.setLineDash([4, 4]); ctx.strokeStyle = P.warn; ctx.globalAlpha = .7;
      ctx.beginPath();
      for (i = 0; i < n; i++) { var x2 = cx + Math.cos(ang(i)) * R * .4, y2 = cy + Math.sin(ang(i)) * R * .4; if (!i) ctx.moveTo(x2, y2); else ctx.lineTo(x2, y2); }
      ctx.closePath(); ctx.stroke(); ctx.restore();
      // 값 다각형
      var path = new Path2D();
      pts.forEach(function (p, i) {
        var k = Math.max(0.03, p.v / 100) * prog, x = cx + Math.cos(ang(i)) * R * k, y = cy + Math.sin(ang(i)) * R * k;
        if (!i) path.moveTo(x, y); else path.lineTo(x, y);
      });
      path.closePath();
      var g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R);
      g.addColorStop(0, 'rgba(139,92,246,.45)'); g.addColorStop(1, 'rgba(94,234,212,.35)');
      ctx.fillStyle = g; ctx.fill(path);
      ctx.strokeStyle = P.a; ctx.lineWidth = 2; ctx.shadowColor = P.a; ctx.shadowBlur = 10; ctx.stroke(path); ctx.shadowBlur = 0;
      // 점 + 라벨
      ctx.font = '600 11px Pretendard, "Noto Sans KR", system-ui, sans-serif'; ctx.textBaseline = 'middle';
      pts.forEach(function (p, i) {
        var k = Math.max(0.03, p.v / 100) * prog, a = ang(i);
        ctx.fillStyle = p.v < 40 ? P.bad : P.ok;
        ctx.beginPath(); ctx.arc(cx + Math.cos(a) * R * k, cy + Math.sin(a) * R * k, 3.5, 0, TAU); ctx.fill();
        var lx = cx + Math.cos(a) * (R + 14), ly = cy + Math.sin(a) * (R + 14), c = Math.cos(a);
        ctx.fillStyle = P.muted; ctx.textAlign = Math.abs(c) < .2 ? 'center' : (c > 0 ? 'left' : 'right');
        ctx.fillText(p.l.length > 8 ? p.l.slice(0, 8) + '…' : p.l, lx, ly + (Math.sin(a) > .5 ? 6 : Math.sin(a) < -.5 ? -6 : 0));
      });
    }
    onVisible(cv, function () { animate(1000, function (p) { prog = p; render(); }); });
    watchResize(cv, render);
  }

  // ---- 공통: 보일 때 시작 / 크기 변화 감지 ----------------------------------
  function onVisible(el, fn) {
    if (!('IntersectionObserver' in window)) { fn(); return; }
    var io = new IntersectionObserver(function (es) {
      if (es[0].isIntersecting) { io.disconnect(); fn(); }
    }, { threshold: 0.15 });
    io.observe(el);
  }
  function watchResize(el, fn) {
    if (!('ResizeObserver' in window)) return;
    var last = el.clientWidth, t;
    new ResizeObserver(function () {
      if (el.clientWidth === last) return;
      last = el.clientWidth; clearTimeout(t); t = setTimeout(fn, 60);
    }).observe(el);
  }

  // ---- 숫자 카운트업 -------------------------------------------------------
  function countUps() {
    document.querySelectorAll('[data-count]').forEach(function (el) {
      var to = parseFloat(el.dataset.count) || 0;
      if (reduce || to === 0) { el.textContent = Math.round(to); return; }
      onVisible(el, function () { animate(900, function (p) { el.textContent = Math.round(to * p); }); });
    });
  }

  // ---- 카드: 스크롤 등장 + 마우스 기울임 ---------------------------------------
  function cardFx() {
    var cards = document.querySelectorAll('.card:not(.qcard), .hero');
    cards.forEach(function (c, i) { c.style.setProperty('--d', Math.min(i, 8) * 60 + 'ms'); });
    if (!reduce && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
      }, { threshold: 0.05 });
      cards.forEach(function (c) { c.classList.add('reveal'); io.observe(c); });
    }
    if (reduce || !matchMedia('(hover: hover)').matches) return;
    document.querySelectorAll('.tilt').forEach(function (c) {
      c.addEventListener('pointermove', function (e) {
        var r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
        c.style.setProperty('--rx', (-y * 4).toFixed(2) + 'deg');
        c.style.setProperty('--ry', (x * 5).toFixed(2) + 'deg');
        c.style.setProperty('--mx', ((x + .5) * 100).toFixed(1) + '%');
        c.style.setProperty('--my', ((y + .5) * 100).toFixed(1) + '%');
      });
      c.addEventListener('pointerleave', function () { c.style.setProperty('--rx', '0deg'); c.style.setProperty('--ry', '0deg'); });
    });
  }

  // ---- 합격 축하 컨페티 ------------------------------------------------------
  function confetti() {
    var host = document.querySelector('[data-confetti="1"]');
    if (!host || reduce) return;
    var cv = document.createElement('canvas');
    cv.className = 'confetti'; document.body.appendChild(cv);
    var dpr = Math.min(window.devicePixelRatio || 1, 2), W = innerWidth, H = innerHeight;
    cv.width = W * dpr; cv.height = H * dpr;
    var ctx = cv.getContext('2d'); ctx.scale(dpr, dpr);
    var P = pal(), cols = [P.a, P.b, P.c, P.ok, P.warn, '#ffffff'];
    var rc = host.getBoundingClientRect(), ox = rc.left + 110, oy = rc.top + 110;
    var ps = [];
    for (var i = 0; i < 150; i++) {
      var a = Math.random() * TAU, sp = 4 + Math.random() * 9;
      ps.push({ x: ox, y: oy, vx: Math.cos(a) * sp, vy: Math.sin(a) * sp - 5, w: 5 + Math.random() * 6, h: 3 + Math.random() * 5,
        r: Math.random() * TAU, vr: (Math.random() - .5) * .4, c: cols[i % cols.length], life: 1 });
    }
    var t0 = performance.now();
    (function f(now) {
      ctx.clearRect(0, 0, W, H);
      var alive = false;
      ps.forEach(function (p) {
        p.vy += .22; p.vx *= .992; p.x += p.vx; p.y += p.vy; p.r += p.vr;
        p.life = Math.max(0, 1 - (now - t0) / 3600);
        if (p.life > 0 && p.y < H + 20) alive = true; else return;
        ctx.save(); ctx.globalAlpha = p.life; ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.fillStyle = p.c;
        ctx.beginPath(); ctx.roundRect(-p.w / 2, -p.h / 2, p.w, p.h, 1.5); ctx.fill(); ctx.restore();
      });
      if (alive) requestAnimationFrame(f); else cv.remove();
    })(t0);
  }

  window.SQLDCharts = { drawRing: drawRing };

  function init() {
    document.querySelectorAll('canvas[data-chart]').forEach(function (cv) {
      var k = cv.dataset.chart;
      if (k === 'gauge') gauge(cv);
      else if (k === 'trend') trend(cv);
      else if (k === 'radar') radar(cv);
    });
    countUps(); cardFx(); confetti();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

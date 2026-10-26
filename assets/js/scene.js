// SQLD 합격 훈련 — Three.js 장면
//  1) #bg3d   : 모든 페이지 뒤에 깔리는 파티클 + 와이어프레임 배경 (마우스·스크롤 시차)
//  2) #hero3d : 대시보드 히어로의 3D 데이터 막대(물결 + 마우스 파동)
// WebGL 이 없거나 '동작 줄이기' 설정이면 정지 화면 한 장만 그린다.
import * as THREE from 'three';

const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const css = (n, f) => getComputedStyle(document.documentElement).getPropertyValue(n).trim() || f;
const C = {
  a: new THREE.Color(css('--accent', '#5eead4')),
  b: new THREE.Color(css('--accent2', '#8b5cf6')),
  c: new THREE.Color(css('--accent3', '#f472b6')),
};
const page = document.body.dataset.page || 'default';
const intensity = { home: 1, result: 0.7, exam: 0.38, default: 0.8 }[page] ?? 0.8;
const mobile = matchMedia('(max-width: 760px)').matches;
const pointer = { x: 0, y: 0, sx: 0, sy: 0 };
addEventListener('pointermove', (e) => {
  pointer.x = (e.clientX / innerWidth) * 2 - 1;
  pointer.y = (e.clientY / innerHeight) * 2 - 1;
}, { passive: true });

function makeRenderer(canvas) {
  try {
    const r = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
    r.setPixelRatio(Math.min(devicePixelRatio || 1, 2));
    r.setClearColor(0x000000, 0);
    return r;
  } catch (e) {
    canvas.hidden = true;
    return null;
  }
}

const tickers = [];   // 매 프레임 호출할 함수들 (t: 초)

// ---------------------------------------------------------------- 배경
function backgroundScene() {
  const canvas = document.getElementById('bg3d');
  if (!canvas) return;
  const renderer = makeRenderer(canvas);
  if (!renderer) return;

  const scene = new THREE.Scene();
  scene.fog = new THREE.FogExp2(0x070a14, 0.028);
  const camera = new THREE.PerspectiveCamera(55, 1, 0.1, 200);
  camera.position.set(0, 0, 16);

  // 둥근 점 스프라이트 (Canvas 2D 로 그린 텍스처)
  const sp = document.createElement('canvas');
  sp.width = sp.height = 64;
  const sx = sp.getContext('2d');
  const g = sx.createRadialGradient(32, 32, 0, 32, 32, 32);
  g.addColorStop(0, 'rgba(255,255,255,1)');
  g.addColorStop(0.35, 'rgba(255,255,255,.55)');
  g.addColorStop(1, 'rgba(255,255,255,0)');
  sx.fillStyle = g;
  sx.fillRect(0, 0, 64, 64);
  const sprite = new THREE.CanvasTexture(sp);

  // 파티클 성운
  const N = mobile ? 380 : 950;
  const pos = new Float32Array(N * 3);
  const col = new Float32Array(N * 3);
  const tmp = new THREE.Color();
  for (let i = 0; i < N; i++) {
    const r = 6 + Math.pow(Math.random(), 0.7) * 30;
    const th = Math.random() * Math.PI * 2;
    const ph = Math.acos(2 * Math.random() - 1);
    pos[i * 3] = r * Math.sin(ph) * Math.cos(th) * 1.5;
    pos[i * 3 + 1] = r * Math.sin(ph) * Math.sin(th) * 0.9;
    pos[i * 3 + 2] = r * Math.cos(ph) - 6;
    const k = Math.random();
    tmp.copy(k < 0.5 ? C.a : C.b).lerp(C.c, Math.max(0, k - 0.75) * 3);
    col.set([tmp.r, tmp.g, tmp.b], i * 3);
  }
  const pg = new THREE.BufferGeometry();
  pg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  pg.setAttribute('color', new THREE.BufferAttribute(col, 3));
  const pm = new THREE.PointsMaterial({
    size: 0.32, map: sprite, vertexColors: true, transparent: true, opacity: 0.85 * intensity,
    depthWrite: false, blending: THREE.AdditiveBlending, sizeAttenuation: true,
  });
  const cloud = new THREE.Points(pg, pm);
  scene.add(cloud);

  // 떠다니는 와이어프레임 도형들
  const shapes = [];
  const defs = [
    [new THREE.IcosahedronGeometry(1.6, 0), C.a, [-13, 4.5, -5]],
    [new THREE.OctahedronGeometry(1.3, 0), C.c, [9.5, -3, -6]],
    [new THREE.TorusKnotGeometry(1.1, 0.32, 90, 12), C.b, [7, 5.5, -9]],
    [new THREE.TetrahedronGeometry(1.2, 0), C.a, [-7, -5, -7]],
    [new THREE.TorusGeometry(1.4, 0.05, 8, 48), C.c, [0, 7, -12]],
  ];
  if (mobile) defs.length = 3;
  defs.forEach(([geo, color, p], i) => {
    const m = new THREE.LineSegments(
      new THREE.EdgesGeometry(geo),
      new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0.5 * intensity, blending: THREE.AdditiveBlending })
    );
    m.position.set(...p);
    m.userData = { base: new THREE.Vector3(...p), sp: 0.15 + i * 0.05, ph: i * 1.7 };
    shapes.push(m);
    scene.add(m);
  });

  // 데이터베이스 격자 바닥 느낌의 얇은 그리드
  const grid = new THREE.GridHelper(80, 40, C.b, C.b);
  grid.material.transparent = true;
  grid.material.opacity = 0.10 * intensity;
  grid.position.set(0, -9, -10);
  scene.add(grid);

  function resize() {
    const w = innerWidth, h = innerHeight;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }
  addEventListener('resize', resize);
  resize();

  const speed = reduce ? 0 : intensity;
  tickers.push((t) => {
    cloud.rotation.y = t * 0.018 * speed;
    cloud.rotation.x = Math.sin(t * 0.05) * 0.08 * speed;
    shapes.forEach((m) => {
      const u = m.userData;
      m.rotation.x = t * u.sp * speed;
      m.rotation.y = t * u.sp * 1.3 * speed;
      m.position.y = u.base.y + Math.sin(t * 0.4 + u.ph) * 0.6 * speed;
    });
    // 마우스 시차 + 스크롤 시차
    const sc = scrollY / Math.max(1, document.body.scrollHeight - innerHeight);
    camera.position.x += (pointer.x * 1.6 - camera.position.x) * 0.04;
    camera.position.y += (-pointer.y * 1.0 - sc * 4 - camera.position.y) * 0.04;
    camera.lookAt(0, -sc * 3, 0);
    renderer.render(scene, camera);
  });
}

// ---------------------------------------------------------------- 히어로 막대
function heroScene() {
  const canvas = document.getElementById('hero3d');
  if (!canvas) return;
  const renderer = makeRenderer(canvas);
  if (!renderer) return;
  renderer.setClearColor(0x000000, 0);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(36, 1, 0.1, 100);
  camera.position.set(0, 11.5, 18);
  camera.lookAt(0, 0.4, 0);

  scene.add(new THREE.AmbientLight(0xffffff, 0.55));
  const key = new THREE.DirectionalLight(0xffffff, 1.6);
  key.position.set(5, 10, 6);
  scene.add(key);
  const rim = new THREE.PointLight(C.c, 40, 30);
  rim.position.set(-7, 4, -3);
  scene.add(rim);
  const rim2 = new THREE.PointLight(C.a, 30, 30);
  rim2.position.set(7, 3, 4);
  scene.add(rim2);

  const G = mobile ? 8 : 11, gap = 1.12;
  const mesh = new THREE.InstancedMesh(
    new THREE.BoxGeometry(0.86, 1, 0.86),
    new THREE.MeshStandardMaterial({ roughness: 0.35, metalness: 0.35 }),
    G * G
  );
  mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
  const group = new THREE.Group();
  group.add(mesh);
  scene.add(group);

  // 위에 떠 있는 링
  const ring = new THREE.Mesh(
    new THREE.TorusGeometry(5.4, 0.035, 8, 120),
    new THREE.MeshBasicMaterial({ color: C.a, transparent: true, opacity: 0.7 })
  );
  ring.rotation.x = Math.PI / 2;
  ring.position.y = 4.2;
  group.add(ring);

  const dummy = new THREE.Object3D();
  const color = new THREE.Color();
  const ray = new THREE.Raycaster();
  const ndc = new THREE.Vector2(2, 2);
  const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
  const hit = new THREE.Vector3(999, 0, 999);
  const half = (G - 1) / 2;

  canvas.addEventListener('pointermove', (e) => {
    const r = canvas.getBoundingClientRect();
    ndc.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
  });
  canvas.addEventListener('pointerleave', () => { ndc.set(2, 2); hit.set(999, 0, 999); });

  function resize() {
    const w = canvas.clientWidth || 300, h = canvas.clientHeight || 300;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
  }
  new ResizeObserver(resize).observe(canvas);
  resize();

  let visible = true;
  new IntersectionObserver((es) => { visible = es[0].isIntersecting; }).observe(canvas);

  const speed = reduce ? 0 : 1;
  const draw = (t) => {
    if (!visible && t > 1) return;
    if (ndc.x <= 1) {
      ray.setFromCamera(ndc, camera);
      group.updateMatrixWorld();
      const inv = group.matrixWorld.clone().invert();
      const r2 = ray.ray.clone().applyMatrix4(inv);
      if (r2.intersectPlane(plane, hit) === null) hit.set(999, 0, 999);
    }
    for (let ix = 0; ix < G; ix++) {
      for (let iz = 0; iz < G; iz++) {
        const x = (ix - half) * gap, z = (iz - half) * gap;
        const d = Math.hypot(x, z);
        let hgt = 1.2 + Math.sin(d * 0.9 - t * 1.6 * speed) * 0.7 + Math.sin(x * 0.5 + t * 0.8 * speed) * 0.35;
        const dp = Math.hypot(x - hit.x, z - hit.z);
        hgt += Math.max(0, 1 - dp / 3.2) * 2.4;
        hgt = Math.max(0.25, hgt);
        const i = ix * G + iz;
        dummy.position.set(x, hgt / 2, z);
        dummy.scale.set(1, hgt, 1);
        dummy.updateMatrix();
        mesh.setMatrixAt(i, dummy.matrix);
        const k = Math.min(1, hgt / 3.4);
        color.copy(C.b).lerp(C.a, k).lerp(C.c, Math.max(0, k - 0.8) * 4);
        mesh.setColorAt(i, color);
      }
    }
    mesh.instanceMatrix.needsUpdate = true;
    if (mesh.instanceColor) mesh.instanceColor.needsUpdate = true;
    group.rotation.y = t * 0.12 * speed + pointer.x * 0.35;
    group.rotation.x = pointer.y * 0.08;
    ring.position.y = 4.2 + Math.sin(t * 0.9 * speed) * 0.25;
    ring.scale.setScalar(1 + Math.sin(t * 0.7 * speed) * 0.04);
    renderer.render(scene, camera);
  };
  tickers.push(draw);
}

backgroundScene();
heroScene();

// ---------------------------------------------------------------- 루프
if (tickers.length) {
  let t0 = performance.now();
  if (reduce) {
    tickers.forEach((f) => f(2));   // 정지 화면 한 장
    addEventListener('resize', () => tickers.forEach((f) => f(2)));
  } else {
    let running = true;
    document.addEventListener('visibilitychange', () => {
      running = !document.hidden;
      if (running) { loop(); }
    });
    const loop = () => {
      if (!running) return;
      const t = (performance.now() - t0) / 1000;
      tickers.forEach((f) => f(t));
      requestAnimationFrame(loop);
    };
    loop();
  }
}

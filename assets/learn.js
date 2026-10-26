// 강의 읽기: 읽은 위치(%) 저장 → 다음에 열면 그 위치로 이어서, 상단 읽기 진행 막대, 학습 완료 버튼
(function () {
  'use strict';
  var root = document.getElementById('lesson');
  if (!root) return;
  var id = root.dataset.id;
  var member = root.dataset.member === '1';
  var bar = document.getElementById('read-bar');
  var body = document.getElementById('md');
  var toastEl = document.getElementById('toast');
  var lastSent = -1;
  var curPct = 0;

  function toast(msg) {
    toastEl.textContent = msg; toastEl.hidden = false;
    clearTimeout(toast._t); toast._t = setTimeout(function () { toastEl.hidden = true; }, 2600);
  }

  // 본문 기준 읽은 비율(%)
  function readPct() {
    var r = body.getBoundingClientRect();
    var total = r.height - window.innerHeight * 0.6;
    if (total <= 0) return 100;
    var done = -r.top + window.innerHeight * 0.4;
    return Math.max(0, Math.min(100, Math.round(done / total * 100)));
  }
  function scrollToPct(p) {
    var r = body.getBoundingClientRect();
    var top = window.scrollY + r.top;
    var total = r.height - window.innerHeight * 0.6;
    window.scrollTo(0, Math.max(0, top + total * p / 100 - window.innerHeight * 0.4));
  }

  function post(data) {
    data.lesson_id = id;
    return fetch('api/progress.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString()
    }).then(function (r) { return r.json(); });
  }
  function save(force) {
    if (!member) return;
    if (!force && Math.abs(curPct - lastSent) < 3) return;
    lastSent = curPct;
    post({ action: 'pos', pct: curPct }).catch(function () {});
  }
  function beacon() {
    if (!member || curPct === lastSent || !navigator.sendBeacon) return;
    var fd = new URLSearchParams({ lesson_id: id, action: 'pos', pct: curPct });
    navigator.sendBeacon('api/progress.php', new Blob([fd.toString()], { type: 'application/x-www-form-urlencoded' }));
    lastSent = curPct;
  }

  function onScroll() {
    curPct = readPct();
    bar.style.width = curPct + '%';
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  setInterval(function () { save(false); }, 4000);
  document.addEventListener('visibilitychange', function () { if (document.hidden) beacon(); });
  window.addEventListener('pagehide', beacon);

  // 이어 읽기: 저장된 위치로 이동 (끝까지 읽었거나 처음이면 맨 위)
  var last = parseInt(root.dataset.last, 10) || 0;
  if (member && last > 3 && last < 97 && !location.hash) {
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';   // 브라우저가 맨 위로 되돌리지 않게
    var restore = function () {
      scrollToPct(last);
      toast('지난번에 읽던 곳(' + last + '%)부터 이어서 보여줄게.');
      onScroll(); lastSent = curPct;
    };
    if (document.readyState === 'complete') setTimeout(restore, 30);
    else window.addEventListener('load', function () { setTimeout(restore, 30); });
  } else {
    onScroll();
  }

  var btn = document.getElementById('done-btn');
  if (btn) {
    btn.addEventListener('click', function () {
      var isDone = root.dataset.done === '1';
      btn.disabled = true;
      post({ action: isDone ? 'undone' : 'done' }).then(function (res) {
        btn.disabled = false;
        if (!res.ok) { toast(res.error || '저장하지 못했어.'); return; }
        var nowDone = res.status === 'done';
        root.dataset.done = nowDone ? '1' : '0';
        btn.className = 'btn ' + (nowDone ? 'ok-btn' : 'primary glow');
        btn.textContent = nowDone ? '✓ 학습 완료됨 (취소하려면 누르기)' : '학습 완료로 표시';
        var cp = document.getElementById('cert-pct'), cd = document.getElementById('cert-done');
        if (cp) cp.textContent = res.cert_pct;
        if (cd) cd.textContent = res.done;
        toast(nowDone ? '완료! 전체 진도 ' + res.cert_pct + '%' : '완료 표시를 취소했어.');
        var nx = document.getElementById('next-lesson');
        if (nowDone && nx) nx.classList.add('glow');
      }).catch(function () { btn.disabled = false; toast('네트워크 오류야.'); });
    });
  }
})();

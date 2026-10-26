// 시간 제한 시험 화면: 서버가 알려주는 현재 문제와 남은 시간으로만 진행한다.
// - 문제마다 제한 시간. 0초가 되면 고른 답(없으면 무응답)으로 자동 제출하고 다음 문제
// - 일시정지: 서버에 기록(횟수/시간), 그동안 문제는 가려짐
(function () {
  'use strict';
  var root = document.getElementById('timed');
  if (!root) return;

  var testId = root.dataset.test;
  var limit = parseInt(root.dataset.limit, 10) || 60;
  var subjects = JSON.parse(root.dataset.subjects || '{}');
  var qcard = document.getElementById('qcard');
  var veil = document.getElementById('veil');
  var pauseBtn = document.getElementById('pause');
  var resumeBtn = document.getElementById('resume');
  var nextBtn = document.getElementById('next');
  var skipBtn = document.getElementById('skip');
  var ring = document.getElementById('ring');
  var remainEl = document.getElementById('remain');
  var toastEl = document.getElementById('toast');
  var hintNote = document.getElementById('t-hint-note');

  var state = null;
  var deadline = 0;        // 현재 문제 마감 시각(내 컴퓨터 시계 기준으로 환산)
  var busy = false;
  var selected = 0;
  var CIRCLED = ['', '①', '②', '③', '④'];

  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toastEl.hidden = true; }, 2600);
  }

  function api(action, extra) {
    var data = { test_id: testId, action: action };
    for (var k in extra || {}) data[k] = extra[k];
    return fetch('api/exam.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.login) { location.href = 'login.php'; return j; }
      if (!j.ok && j.error) toast(j.error);
      if (j.state) apply(j.state);
      return j;
    });
  }

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined && text !== null) e.textContent = text;
    return e;
  }

  function fmtSec(s) {
    s = Math.max(0, Math.round(s));
    if (s < 60) return s + '초';
    return Math.floor(s / 60) + '분 ' + (s % 60) + '초';
  }

  function renderItem(it) {
    qcard.innerHTML = '';
    selected = 0;
    nextBtn.disabled = true;
    var meta = el('div', 'qmeta');
    meta.appendChild(el('span', 'qno', 'Q' + it.pos));
    var st = el('span', 'tag s' + it.subject, it.subject + '과목');
    st.title = it.subject_name;
    meta.appendChild(st);
    meta.appendChild(el('span', 'tag topic', it.topic));
    meta.appendChild(el('span', 'stars', '★★★'.slice(0, it.difficulty) + '☆☆☆'.slice(0, 3 - it.difficulty)));
    qcard.appendChild(meta);
    var qt = el('p', 'qtext');
    String(it.question).split('\n').forEach(function (line, i) {
      if (i) qt.appendChild(document.createElement('br'));
      qt.appendChild(document.createTextNode(line));
    });
    qcard.appendChild(qt);
    if (it.code) {
      var pre = el('pre', 'sql');
      pre.appendChild(el('code', '', it.code));
      qcard.appendChild(pre);
    }
    var box = el('div', 'choices' + (it.mono ? ' mono' : ''));
    box.setAttribute('role', 'radiogroup');
    it.choices.forEach(function (text, i) {
      var n = i + 1;
      var lab = el('label', 'choice');
      var inp = document.createElement('input');
      inp.type = 'radio'; inp.name = 'tq'; inp.value = n;
      inp.addEventListener('change', function () { selected = n; nextBtn.disabled = false; });
      lab.appendChild(inp);
      lab.appendChild(el('span', 'cnum', CIRCLED[n]));
      lab.appendChild(el('span', 'ctext', text));
      box.appendChild(lab);
    });
    qcard.appendChild(box);
    if (it.can_hint) {
      var ha = el('div', 'hint-area');
      var hb = el('button', 'btn hint-btn', '힌트 보기');
      hb.type = 'button';
      var hbox = el('div', 'hint-box');
      hbox.appendChild(el('b', '', '힌트'));
      var hp = el('p', '', it.hint || '');
      hbox.appendChild(hp);
      hbox.hidden = !it.hint_used;
      hb.hidden = !!it.hint_used;
      hb.addEventListener('click', function () {
        hb.disabled = true;
        api('hint', { pos: it.pos }).then(function () { hb.disabled = false; });
      });
      ha.appendChild(hb); ha.appendChild(hbox);
      qcard.appendChild(ha);
    }
    qcard.classList.remove('qin'); void qcard.offsetWidth; qcard.classList.add('qin');
    hintNote.textContent = it.pos === state.total ? '마지막 문제야' : '';
    nextBtn.textContent = it.pos === state.total ? '답 제출 · 끝내기' : '답 제출 · 다음 →';
    window.scrollTo({ top: 0 });
  }

  function apply(s) {
    if (s.status === 'done') {
      if (s.url) location.href = s.url;
      return;
    }
    var prevPos = state ? state.pos : null;
    var prevPaused = state ? state.paused : null;
    var prevSel = selected;
    state = s;
    deadline = Date.now() + s.left * 1000;
    document.getElementById('t-pos').textContent = 'Q' + s.pos;
    document.getElementById('t-bar').style.setProperty('--w', ((s.pos - 1) / s.total * 100) + '%');
    document.getElementById('t-pc').textContent = s.pause_count;
    document.getElementById('t-pt').textContent = fmtSec(s.pause_total);
    document.getElementById('v-pc').textContent = s.pause_count;
    document.getElementById('v-pt').textContent = fmtSec(s.pause_total);
    veil.hidden = !s.paused;
    if (s.paused) {   // 일시정지 중엔 문제를 화면에서 아예 지운다 (다시 시작하면 서버에서 다시 받음)
      qcard.innerHTML = '<p class="muted">일시정지 중이야.</p>';
    }
    pauseBtn.setAttribute('aria-pressed', s.paused ? 'true' : 'false');
    pauseBtn.textContent = s.paused ? '다시 시작' : '일시정지';
    nextBtn.disabled = s.paused || !selected;
    skipBtn.disabled = s.paused;
    if (s.item) {
      document.getElementById('t-subj').textContent = s.item.subject + '과목 ' + (s.item.subject_name || '');
      if (s.item.pos !== prevPos || prevPaused) {
        renderItem(s.item);
        if (s.item.pos === prevPos && prevSel) {   // 일시정지 후 같은 문제면 고른 답 복원
          var r = qcard.querySelector('input[value="' + prevSel + '"]');
          if (r) { r.checked = true; selected = prevSel; nextBtn.disabled = false; }
        }
      } else if (s.item.hint_used) {
        var hb = qcard.querySelector('.hint-box'), btn = qcard.querySelector('.hint-btn');
        if (hb) { hb.querySelector('p').textContent = s.item.hint || ''; hb.hidden = false; }
        if (btn) btn.hidden = true;
      }
      if (prevPos !== null && s.item.pos > prevPos + 1) toast('시간이 지나서 ' + (s.item.pos - prevPos - 1) + '문제가 넘어갔어.');
    }
    tick();
  }

  function submit(answer, auto) {
    if (busy || !state || state.paused) return;
    busy = true;
    nextBtn.disabled = true; skipBtn.disabled = true;
    api('answer', { pos: state.pos, answer: answer, auto: auto ? 1 : 0 }).then(function () {
      if (auto) toast(answer ? '시간 종료! 고른 답으로 제출했어.' : '시간 종료! 다음 문제로 넘어가.');
    }).catch(function () {
      toast('네트워크 오류야. 다시 시도할게.');
      setTimeout(function () { api('state'); }, 1500);
    }).then(function () { busy = false; if (state) skipBtn.disabled = state.paused; });
  }

  function tick() {
    if (!state) return;
    var leftMs = state.paused ? state.left * 1000 : Math.max(0, deadline - Date.now());
    var s = Math.ceil(leftMs / 1000);
    remainEl.textContent = s >= 60 ? Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') : s + '초';
    var warn = s <= Math.max(5, Math.round(limit * 0.2));
    root.classList.toggle('late', warn && !state.paused);
    if (ring && window.SQLDCharts) window.SQLDCharts.drawRing(ring, leftMs / (limit * 1000), { warn: warn });
    if (!state.paused && leftMs <= 0 && !busy) submit(selected, true);
  }

  nextBtn.addEventListener('click', function () { if (selected) submit(selected, false); });
  skipBtn.addEventListener('click', function () {
    if (window.confirm('이 문제를 답 없이 넘길까? 다시 돌아올 수 없어.')) submit(0, false);
  });
  pauseBtn.addEventListener('click', function () { api(state && state.paused ? 'resume' : 'pause'); });
  resumeBtn.addEventListener('click', function () { api('resume'); });

  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || !state) return;
    var k = e.key;
    if (k === 'p' || k === 'P') { pauseBtn.click(); return; }
    if (state.paused) return;
    if (k >= '1' && k <= '4') {
      var r = qcard.querySelectorAll('input[type=radio]')[parseInt(k, 10) - 1];
      if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
    } else if (k === 'Enter' && selected && !nextBtn.disabled) {
      e.preventDefault(); nextBtn.click();
    } else if (k === 'h' || k === 'H') {
      var b = qcard.querySelector('.hint-btn');
      if (b && !b.hidden && !b.disabled) b.click();
    }
  });

  // 다른 탭에 갔다 오면 서버 시간으로 다시 맞춘다
  document.addEventListener('visibilitychange', function () { if (!document.hidden) api('state'); });
  setInterval(tick, 250);
  setInterval(function () { if (!busy) api('state'); }, 30000);
  api('state');
})();

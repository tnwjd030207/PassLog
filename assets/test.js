// 연습 화면 동작: 한 문제씩 보기, 답 자동 저장, 힌트 버튼, 경과 시간, 제출
(function () {
  'use strict';
  var exam = document.getElementById('exam');
  if (!exam) return;

  var testId = exam.dataset.test;
  var startTs = parseInt(exam.dataset.start, 10);
  var cards = Array.prototype.slice.call(exam.querySelectorAll('.qcard'));
  var dots = Array.prototype.slice.call(exam.querySelectorAll('.dot'));
  var prevBtn = document.getElementById('prev');
  var nextBtn = document.getElementById('next');
  var submitBtn = document.getElementById('submit');
  var progress = document.getElementById('progress');
  var dialog = document.getElementById('confirm');
  var toastEl = document.getElementById('toast');
  var cur = 0;

  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toastEl.hidden = true; }, 2600);
  }

  function post(url, data) {
    var body = new URLSearchParams(data).toString();
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.login) { location.href = 'login.php'; }   // 로그인이 풀렸으면 로그인 화면으로
      return j;
    });
  }

  function show(i) {
    cur = Math.max(0, Math.min(cards.length - 1, i));
    cards.forEach(function (c, idx) { c.hidden = idx !== cur; });
    dots.forEach(function (d, idx) { d.classList.toggle('cur', idx === cur); });
    progress.textContent = (cur + 1) + ' / ' + cards.length;
    prevBtn.disabled = cur === 0;
    var last = cur === cards.length - 1;
    nextBtn.hidden = last;
    submitBtn.hidden = !last;
    window.scrollTo({ top: 0 });
  }

  function answeredCount() {
    return cards.filter(function (c) { return c.querySelector('input:checked'); }).length;
  }

  // 답 선택 → 바로 저장
  cards.forEach(function (card, idx) {
    var itemId = card.dataset.item;
    card.querySelectorAll('input[type=radio]').forEach(function (r) {
      r.addEventListener('change', function () {
        dots[idx].classList.add('done');
        post('api/answer.php', { item_id: itemId, answer: r.value }).then(function (res) {
          if (!res.ok) toast(res.error || '저장에 실패했어.');
        }).catch(function () { toast('네트워크 오류로 저장하지 못했어. 다시 눌러줘.'); });
      });
    });

    // 힌트: 버튼을 눌렀을 때만 서버에 요청해서 받아온다
    var hintBtn = card.querySelector('.hint-btn');
    var hintBox = card.querySelector('.hint-box');
    if (!hintBtn) return;   // 모의고사에는 힌트가 없다
    hintBtn.addEventListener('click', function () {
      hintBtn.disabled = true;
      post('api/hint.php', { item_id: itemId }).then(function (res) {
        if (res.ok) {
          hintBox.querySelector('p').textContent = res.hint;
          hintBox.hidden = false;
          hintBtn.hidden = true;
        } else {
          toast(res.error || '힌트를 불러오지 못했어.');
          hintBtn.disabled = false;
        }
      }).catch(function () { toast('네트워크 오류야.'); hintBtn.disabled = false; });
    });
  });

  dots.forEach(function (d) {
    d.addEventListener('click', function () { show(parseInt(d.dataset.go, 10)); });
  });
  prevBtn.addEventListener('click', function () { show(cur - 1); });
  nextBtn.addEventListener('click', function () { show(cur + 1); });

  // 제출
  submitBtn.addEventListener('click', function () {
    var left = cards.length - answeredCount();
    document.getElementById('confirm-msg').textContent = left > 0
      ? '아직 안 푼 문제가 ' + left + '개 있어. 안 푼 문제는 틀린 걸로 채점돼.'
      : '모든 문제에 답했어. 제출하면 바로 채점하고, 이후엔 답을 바꿀 수 없어.';
    if (dialog.showModal) dialog.showModal(); else if (window.confirm('제출할까?')) doSubmit();
  });
  dialog.addEventListener('close', function () {
    if (dialog.returnValue === 'ok') doSubmit();
  });
  var submitting = false;
  function doSubmit() {
    if (submitting) return;
    submitting = true;
    submitBtn.disabled = true;
    post('api/finish.php', { test_id: testId }).then(function (res) {
      if (res.ok) location.href = res.url;
      else { toast(res.error || '제출에 실패했어.'); submitBtn.disabled = false; submitting = false; }
    }).catch(function () { toast('네트워크 오류야. 다시 눌러줘.'); submitBtn.disabled = false; submitting = false; });
  }

  // 키보드 단축키
  document.addEventListener('keydown', function (e) {
    if (dialog.open || e.ctrlKey || e.metaKey || e.altKey) return;
    var k = e.key;
    if (k >= '1' && k <= '4') {
      var r = cards[cur].querySelectorAll('input[type=radio]')[parseInt(k, 10) - 1];
      if (r) { r.checked = true; r.dispatchEvent(new Event('change')); }
    } else if (k === 'ArrowRight') show(cur + 1);
    else if (k === 'ArrowLeft') show(cur - 1);
    else if (k === 'h' || k === 'H') {
      var b = cards[cur].querySelector('.hint-btn');
      if (b && !b.hidden && !b.disabled) b.click();
    }
  });

  // 시간: 데일리는 경과 시간, 모의고사는 90분 카운트다운 (서버가 알려준 남은 시간 기준 → 내 컴퓨터 시계가 틀려도 정확)
  var isMock = exam.dataset.kind === 'mock';
  var limit = parseInt(exam.dataset.limit, 10) || 5400;
  var deadline = Date.now() + parseInt(exam.dataset.left, 10) * 1000;
  var elapsedEl = document.getElementById('elapsed');
  var remainEl = document.getElementById('remain');
  var ring = document.getElementById('ring');
  function fmt(s) {
    var m = Math.floor(s / 60);
    return (m >= 60 ? Math.floor(m / 60) + ':' : '') + String(m % 60).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  }
  function tick() {
    if (!isMock) {
      elapsedEl.textContent = fmt(Math.max(0, Math.floor(Date.now() / 1000) - startTs));
      return;
    }
    var leftMs = Math.max(0, deadline - Date.now());
    var s = Math.ceil(leftMs / 1000);
    remainEl.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    var ratio = leftMs / (limit * 1000);
    exam.classList.toggle('late', s <= 600);
    if (ring && window.SQLDCharts) window.SQLDCharts.drawRing(ring, ratio, { warn: s <= 600 });
    if (leftMs <= 0 && !submitting) {
      toast('시간 종료! 자동으로 제출할게.');
      if (dialog.open) dialog.close('cancel');
      doSubmit();
    }
  }
  tick();
  setInterval(tick, 1000);
  window.addEventListener('load', tick);

  // 이어 풀 때는 안 푼 첫 문제부터 시작
  var firstOpen = cards.findIndex(function (c) { return !c.querySelector('input:checked'); });
  show(firstOpen === -1 ? 0 : firstOpen);
})();

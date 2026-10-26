<?php
// 시험 설정 화면 + 시험 만들기/그만두기 처리
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';

$cert = currentCert();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'abandon') {
        abandonTest((int)($_POST['test_id'] ?? 0));
        flash('시험을 그만뒀어.');
        redirect('index.php');
    }
    if ($action === 'new') {
        $kind = ($_POST['kind'] ?? 'practice') === 'exam' ? 'exam' : 'practice';
        if ($kind === 'exam' && isGuest()) {
            redirect('kakao_login.php?next=' . urlencode('start.php'));   // 실전은 로그인 필요 → 바로 카카오 로그인
        }
        if ($cur = currentTest()) {
            flash('풀던 시험이 있어서 그걸 먼저 열었어. 새로 풀려면 그만두기를 눌러줘.');
            redirect(testUrl($cur));
        }
        try {
            $t = createTest([
                'cert_id' => $cert['id'], 'kind' => $kind,
                'count' => (int)($_POST['count'] ?? 10), 'subject' => (int)($_POST['subject'] ?? 0),
                'topic' => (string)($_POST['topic'] ?? ''), 'difficulty' => (int)($_POST['difficulty'] ?? 0),
                'mode' => (string)($_POST['mode'] ?? 'smart'), 'timed' => !empty($_POST['timed']),
            ]);
            redirect(testUrl($t));
        } catch (Throwable $e) {
            flash($e->getMessage());
            redirect('start.php');
        }
    }
    redirect('start.php');
}

$cur = currentTest();
$topics = certTopics($cert['id']);
$bank = bankCounts($cert['id']);
$preTopic = (string)($_GET['topic'] ?? '');
$guest = isGuest();

$pageTitle = '문제 풀기';
$active = 'test';
$page = 'exam';
require __DIR__ . '/includes/header.php';
?>
<?php if ($cur): $cc = certById((int)$cur['cert_id']); ?>
    <section class="card glass resume-test">
        <p class="eyebrow">진행 중인 시험</p>
        <h2 class="card-h"><?= h($cc['name'] ?? '') ?> · <?= h(kindLabel($cur)) ?> <?= (int)$cur['total_cnt'] ?>문제</h2>
        <div class="start-row">
            <a class="btn primary" href="<?= testUrl($cur) ?>">이어서 풀기</a>
            <form method="post" onsubmit="return confirm('그만두면 이 시험 기록이 지워져. 그만둘까?');">
                <input type="hidden" name="action" value="abandon"><input type="hidden" name="test_id" value="<?= (int)$cur['id'] ?>">
                <button class="btn" type="submit">그만두고 새로 풀기</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<div class="grid-2 start-grid">
    <section class="card glass">
        <p class="eyebrow"><span class="cert-dot" style="--cc:<?= h($cert['color']) ?>"></span><?= h($cert['name']) ?> · 문제은행 <?= $bank['total'] ?>문제</p>
        <h1 class="card-h">연습 문제</h1>
        <p class="muted small">힌트를 볼 수 있고, 원하면 문제별 시간 제한도 켤 수 있어.<?= $guest ? ' 비회원은 ' . GUEST_MAX_QUESTIONS . '문제까지 풀 수 있고 결과는 이 브라우저에서만 잠깐 보여.' : '' ?></p>
        <form method="post" class="setup-form">
            <input type="hidden" name="action" value="new"><input type="hidden" name="kind" value="practice"><input type="hidden" name="cert" value="<?= h($cert['code']) ?>">
            <fieldset><legend>난이도</legend><?= diffPicker('s') ?></fieldset>
            <fieldset><legend>문제 수</legend><?= countPicker(10, $guest ? GUEST_MAX_QUESTIONS : null) ?></fieldset>
            <fieldset>
                <legend>범위</legend>
                <select name="topic" aria-label="과목 또는 단원">
                    <option value="">전체 과목 (실제 시험 비율대로)</option>
                    <?php foreach ($cert['subjects'] as $no => $s): ?>
                        <optgroup label="<?= $no ?>과목 <?= h($s['name']) ?>">
                            <?php foreach ($topics[$no] ?? [] as $t): ?>
                                <option value="<?= h($t['name']) ?>" <?= $preTopic === $t['name'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <div class="seg small subj-seg" role="radiogroup" aria-label="과목">
                    <label class="seg-opt"><input type="radio" name="subject" value="0" checked><span>전체</span></label>
                    <?php foreach ($cert['subjects'] as $no => $s): ?>
                        <label class="seg-opt" title="<?= h($s['name']) ?>"><input type="radio" name="subject" value="<?= $no ?>"><span><?= $no ?>과목</span></label>
                    <?php endforeach; ?>
                </div>
                <p class="muted small-note">단원을 고르면 그 단원 문제만 나와. (단원이 과목보다 우선)</p>
            </fieldset>
            <fieldset class="opt-row">
                <legend>출제 방식</legend>
                <label class="check"><input type="radio" name="mode" value="smart" checked> 맞춤 (틀린 문제 복습 + 약한 단원 위주)</label>
                <label class="check"><input type="radio" name="mode" value="random"> 랜덤</label>
                <label class="check"><input type="checkbox" name="timed" value="1"> 문제별 시간 제한 켜기 (문제당 <?= (int)$cert['q_seconds'] ?>초, 일시정지 가능)</label>
            </fieldset>
            <button class="btn primary glow" type="submit" <?= $cur ? 'disabled' : '' ?>>연습 시작</button>
        </form>
    </section>

    <section class="card glass mock-card">
        <p class="eyebrow">실전 모의고사 <?= $guest ? '<span class="badge soft">로그인 필요</span>' : '' ?></p>
        <h2 class="card-h"><?= (int)$cert['exam_total'] ?>문제 · <?= (int)$cert['minutes'] ?>분</h2>
        <ul class="plain small">
            <li><?= implode(' / ', array_map(fn($s) => h($s['name']) . ' ' . $s['count'] . '문제', $cert['subjects'])) ?></li>
            <li>문제마다 <b><?= (int)$cert['q_seconds'] ?>초</b> 제한 (전체 <?= (int)$cert['minutes'] ?>분 ÷ <?= (int)$cert['exam_total'] ?>문제). 시간이 끝나면 고른 답으로 자동 제출하고 다음 문제로 넘어가.</li>
            <li>한 번 넘긴 문제는 다시 못 돌아가. 힌트 없음.</li>
            <li><b>일시정지</b>를 누르면 시계가 멈추고 문제가 가려져. 결과표에 일시정지 횟수와 총 시간이 기록돼.</li>
            <li>합격 기준: <?= h($cert['exam_note']) ?></li>
        </ul>
        <?php if ($guest): ?>
            <div class="start-row"><?= kakaoButton('start.php', '카카오로 로그인하고 응시') ?></div>
        <?php else: ?>
            <form method="post" class="setup-form" onsubmit="return confirm('실전 모의고사를 시작할까? 시작하면 첫 문제부터 시간이 흘러.');">
                <input type="hidden" name="action" value="new"><input type="hidden" name="kind" value="exam"><input type="hidden" name="cert" value="<?= h($cert['code']) ?>">
                <fieldset><legend>난이도</legend><?= diffPicker('x') ?><p class="muted small-note">혼합이 실제 시험과 가장 비슷해. 고른 난이도가 모자라면 가까운 난이도로 채워.</p></fieldset>
                <button class="btn primary glow" type="submit" <?= $cur ? 'disabled' : '' ?>>실전 모의고사 시작</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="card glass">
    <h2>문제은행 현황 <small class="muted">과목 × 난이도</small></h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>과목</th><th>하</th><th>중</th><th>상</th><th>합계</th><th>실전 문항</th></tr></thead>
        <tbody>
        <?php foreach ($cert['subjects'] as $no => $s): $b = $bank['by'][$no] ?? []; ?>
            <tr><td><?= $no ?>. <?= h($s['name']) ?></td><td><?= $b[1] ?? 0 ?></td><td><?= $b[2] ?? 0 ?></td><td><?= $b[3] ?? 0 ?></td>
                <td><b><?= array_sum($b) ?></b></td><td><?= $s['count'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<script>
// 단원을 고르면 과목 선택은 비활성 (단원이 우선)
(function () {
  var sel = document.querySelector('select[name=topic]');
  var seg = document.querySelector('.subj-seg');
  if (!sel || !seg) return;
  function sync() { seg.classList.toggle('dim', sel.value !== ''); }
  sel.addEventListener('change', sync); sync();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

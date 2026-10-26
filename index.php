<?php
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';

try {
    $certs = allCerts();
    $cert = currentCert();
} catch (Throwable $e) {
    $pageTitle = '준비 필요';
    require __DIR__ . '/includes/header.php';
    echo '<section class="card glass"><h1>아직 준비가 안 됐어</h1><p>' . h($e->getMessage()) . '</p>'
       . '<p><a class="btn primary" href="install.php">install.php 실행하기</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// 시험일 저장 (회원)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'exam_date' && uid()) {
    $d = trim((string)($_POST['exam_date'] ?? ''));
    if ($d === '') setUserCert($cert['id'], ['exam_date' => null]);
    elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false) setUserCert($cert['id'], ['exam_date' => $d]);
    redirect('index.php');
}

$cur = currentTest();
$curCert = $cur ? certById((int)$cur['cert_id']) : null;
$counts = [];
foreach ($certs as $c) {
    $counts[$c['code']] = [
        'q' => (int)qv('SELECT COUNT(*) FROM questions WHERE cert_id = ? AND active = 1', [$c['id']]),
        'l' => (int)qv('SELECT COUNT(*) FROM lessons WHERE cert_id = ? AND active = 1', [$c['id']]),
    ];
}

// ========================= 비회원: 소개 + 바로 풀어보기 =========================
if (isGuest()):
    $pageTitle = BRAND_TAGLINE;
    $active = 'home';
    $page = 'home';
    $showCert = false;
    require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span class="badge accent">IT 자격증 학습</span> SQLD · 정보처리기사 · ADsP · 리눅스마스터 · 네트워크관리사</p>
        <h1 class="hero-title">합격까지,<br><em>모든 기록</em></h1>
        <p class="hero-sub">개념 강의로 공부하고, 실제 시험처럼 문제별 제한 시간 안에 풀고, 약한 단원은 다시 풀게 해줄게.
            로그인 없이도 <b><?= GUEST_MAX_QUESTIONS ?>문제</b>까지 바로 풀어보고 결과표를 받아볼 수 있어.</p>
        <div class="start-row">
            <a class="btn primary glow" href="#try">로그인 없이 풀어보기</a>
            <?= kakaoButton('index.php', '카카오로 시작하고 기록 남기기') ?>
        </div>
    </div>
    <div class="hero-3d">
        <canvas id="hero3d" aria-label="3D 데이터 막대 장식"></canvas>
        <div class="hero-badges">
            <div class="chip3"><span class="num" data-count="<?= count($certs) ?>">0</span><span class="lbl">개 자격증</span></div>
            <div class="chip3"><span class="num" data-count="<?= array_sum(array_column($counts, 'q')) ?>">0</span><span class="lbl">문제</span></div>
            <div class="chip3"><span class="num" data-count="<?= array_sum(array_column($counts, 'l')) ?>">0</span><span class="lbl">강의</span></div>
        </div>
    </div>
</section>

<?php if ($cur && $curCert): ?>
    <section class="card glass resume-test">
        <p class="eyebrow">풀던 시험이 있어</p>
        <h2 class="card-h"><?= h($curCert['name']) ?> · <?= h(kindLabel($cur)) ?> <?= (int)$cur['total_cnt'] ?>문제</h2>
        <div class="start-row"><a class="btn primary" href="<?= testUrl($cur) ?>">이어서 풀기</a></div>
    </section>
<?php endif; ?>

<h2 class="sec-title" id="try">자격증을 고르고 바로 풀어봐</h2>
<div class="cert-grid">
    <?php foreach ($certs as $c): ?>
        <section class="card glass tilt cert-card" style="--cc:<?= h($c['color']) ?>">
            <p class="eyebrow"><span class="cert-dot"></span><?= h($c['org']) ?></p>
            <h3><?= h($c['name']) ?></h3>
            <p class="muted small"><?= h($c['description']) ?></p>
            <p class="small exam-note"><?= h($c['exam_note']) ?></p>
            <p class="muted small">문제 <?= $counts[$c['code']]['q'] ?>개 · 강의 <?= $counts[$c['code']]['l'] ?>개</p>
            <form method="post" action="start.php" class="quick-form">
                <input type="hidden" name="action" value="new">
                <input type="hidden" name="cert" value="<?= h($c['code']) ?>">
                <input type="hidden" name="kind" value="practice">
                <?= diffPicker('g-' . $c['code']) ?>
                <?= countPicker(10, GUEST_MAX_QUESTIONS) ?>
                <div class="start-row">
                    <button class="btn primary" type="submit" <?= $cur ? 'disabled title="풀던 시험을 먼저 끝내줘"' : '' ?>>바로 풀기</button>
                    <a class="btn small" href="learn.php?cert=<?= h($c['code']) ?>">강의 보기</a>
                </div>
            </form>
        </section>
    <?php endforeach; ?>
</div>

<h2 class="sec-title">가입하면 이런 걸 할 수 있어</h2>
<div class="feature-grid">
    <div class="card glass feature"><b>실전과 같은 시험 진행</b><p class="muted">자격증별 실제 문항 수 · 시간 그대로. 문제마다 제한 시간이 흐르고, 일시정지하면 시계가 멈춰. 결과표에 일시정지 횟수와 시간까지 남아.</p></div>
    <div class="card glass feature"><b>강의 진도율 · 이어서 공부</b><p class="muted">과목 → 단원별 강의 노트. 어디까지 읽었는지 저장해서 다음에 들어오면 그 자리부터 이어서 읽어.</p></div>
    <div class="card glass feature"><b>약점 분석 · 코멘트</b><p class="muted">단원별 정답률을 분석해서 무엇을 먼저 공부해야 하는지 알려줘. 틀린 문제는 다음 시험에 다시 섞여 나와.</p></div>
    <div class="card glass feature"><b>오답노트 · 시험 기록</b><p class="muted">자격증별로 모든 시험 기록과 오답이 쌓여. D-day 를 정해두면 남은 기간도 보여줘.</p></div>
</div>
<div class="start-row center kakao-cta"><?= kakaoButton('index.php', '카카오로 3초 만에 시작하기') ?></div>
<?php
    require __DIR__ . '/includes/footer.php';
    exit;
endif;

// ========================= 회원: 자격증별 대시보드 =========================
$uc = userCert($cert['id']);
$dd = dday($uc['exam_date']);
$psum = summaryStats($cert['id'], 'practice');
$esum = summaryStats($cert['id'], 'exam');
$subj = subjectStats($cert);
$topics = topicStats($cert['id']);
$recent = recentTests($cert['id'], 14, 'practice');
$erecent = recentTests($cert['id'], 10, 'exam');
$streak = streak();
$bank = bankSeen($cert['id']);
$lt = lessonTree($cert);
$resume = resumeLesson($cert, $lt['flat']);
$answered = $cur ? (int)qv('SELECT COUNT(*) FROM test_items WHERE test_id = ? AND user_answer IS NOT NULL', [(int)$cur['id']]) : 0;
$lastE = $erecent ? $erecent[count($erecent) - 1] : null;

$trendPts = fn(array $rows) => array_map(fn($t) => [
    's' => (int)$t['score'], 'p' => (int)$t['passed'], 'l' => date('n/j', strtotime($t['test_date'])), 'id' => (int)$t['id'],
], $rows);
$radar = array_map(fn($t) => ['l' => $t['topic'], 'v' => pct($t['c'], $t['t'])], array_slice($topics, 0, 12));

$pageTitle = $cert['short_name'] . ' 대시보드';
$active = 'home';
$page = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow"><span class="cert-dot" style="--cc:<?= h($cert['color']) ?>"></span><?= h($cert['org']) ?> · <?= h(date('n월 j일')) ?></p>
        <?php if ($cur && $curCert): ?>
            <h1 class="hero-title"><?= h($curCert['short_name']) ?> <?= h(kindLabel($cur)) ?> <em>진행 중</em></h1>
            <p class="hero-sub"><b data-count="<?= $answered ?>">0</b> / <?= (int)$cur['total_cnt'] ?> 문제 답함.
                <?= (int)$cur['q_seconds'] > 0 ? '문제마다 ' . (int)$cur['q_seconds'] . '초 제한 · 일시정지 가능.' : '선택한 답은 자동 저장돼.' ?></p>
            <div class="start-row">
                <a class="btn primary glow" href="<?= testUrl($cur) ?>">이어서 풀기</a>
                <form method="post" action="start.php" onsubmit="return confirm('그만두면 이 시험 기록이 지워져. 그만둘까?');">
                    <input type="hidden" name="action" value="abandon"><input type="hidden" name="test_id" value="<?= (int)$cur['id'] ?>">
                    <button class="btn" type="submit">그만두기</button>
                </form>
            </div>
        <?php else: ?>
            <h1 class="hero-title"><?= h($cert['name']) ?> <em>연습</em></h1>
            <p class="hero-sub"><?= h($cert['exam_note']) ?></p>
            <form method="post" action="start.php" class="quick-form">
                <input type="hidden" name="action" value="new"><input type="hidden" name="cert" value="<?= h($cert['code']) ?>">
                <input type="hidden" name="kind" value="practice">
                <?= diffPicker('p') ?>
                <?= countPicker(10) ?>
                <div class="opt-row">
                    <label class="check"><input type="radio" name="mode" value="smart" checked> 맞춤 출제</label>
                    <label class="check"><input type="radio" name="mode" value="random"> 랜덤</label>
                    <label class="check"><input type="checkbox" name="timed" value="1"> 문제별 시간 제한 (<?= (int)$cert['q_seconds'] ?>초)</label>
                </div>
                <div class="start-row">
                    <button class="btn primary glow" type="submit">연습 시작</button>
                    <a class="btn small" href="start.php">과목 · 단원 골라서 풀기</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="hero-3d">
        <canvas id="hero3d" aria-label="3D 데이터 막대 장식"></canvas>
        <div class="hero-badges">
            <div class="chip3"><span class="num" data-count="<?= $streak ?>">0</span><span class="lbl">연속 학습일</span></div>
            <div class="chip3"><span class="num" data-count="<?= $bank['seen'] ?>">0</span><span class="lbl">/ <?= $bank['total'] ?> 문제 만남</span></div>
            <div class="chip3"><span class="num" data-count="<?= $lt['sum']['pct'] ?>">0</span><span class="lbl">% 강의 진도</span></div>
        </div>
    </div>
</section>

<div class="grid-top">
    <section class="card glass tilt learn-card">
        <p class="eyebrow">이어서 공부하기 <span class="badge soft"><?= $lt['sum']['done'] ?>/<?= $lt['sum']['total'] ?> 강의 완료</span></p>
        <div class="learn-row">
            <canvas width="120" height="120" data-chart="gauge" data-value="<?= $lt['sum']['pct'] ?>" data-label="<?= $lt['sum']['pct'] ?>%" aria-label="강의 진도율"></canvas>
            <div>
                <?php if ($resume): ?>
                    <h2 class="card-h"><?= h($resume['title']) ?></h2>
                    <p class="muted small"><?= h(subjectName($cert, (int)$resume['subject'])) ?> › <?= h($resume['topic']) ?> · 약 <?= (int)$resume['minutes'] ?>분
                        <?= !empty($resume['resume']) && (int)$resume['last_pct'] > 0 ? ' · ' . (int)$resume['last_pct'] . '%까지 읽음' : '' ?></p>
                    <div class="start-row">
                        <a class="btn primary" href="lesson.php?id=<?= (int)$resume['id'] ?>"><?= !empty($resume['resume']) ? '이어서 읽기' : ($lt['sum']['done'] ? '다음 강의' : '첫 강의 시작') ?></a>
                        <a class="btn small" href="learn.php">전체 목차</a>
                    </div>
                <?php elseif ($lt['sum']['total']): ?>
                    <h2 class="card-h">강의를 모두 끝냈어!</h2>
                    <p class="muted">이제 실전 모의고사로 실력을 확인해봐.</p>
                    <a class="btn small" href="learn.php">목차 보기</a>
                <?php else: ?>
                    <p class="muted">아직 강의가 없어.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="subj-progress">
            <?php foreach ($cert['subjects'] as $no => $s): $bs = $lt['sum']['by_subject'][$no] ?? ['total' => 0, 'done' => 0]; $p = pct($bs['done'], $bs['total']); ?>
                <div class="topic-row">
                    <span class="tag s<?= $no ?>"><?= $no ?>과목</span><span class="tname"><?= h($s['name']) ?></span>
                    <div class="track"><i class="ok" style="--w:<?= $p ?>%"></i></div><span class="tnum"><?= $p ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card glass tilt dday">
        <p class="eyebrow"><?= h($cert['short_name']) ?> 시험까지</p>
        <?php if ($dd === null): ?>
            <h2 class="big">D-?</h2><p class="muted">시험 날짜를 넣어두면 D-day가 떠.</p>
        <?php elseif ($dd > 0): ?>
            <h2 class="big grad">D-<span data-count="<?= $dd ?>">0</span></h2><p class="muted"><?= h($uc['exam_date']) ?></p>
        <?php elseif ($dd === 0): ?>
            <h2 class="big grad">D-Day</h2><p class="muted">오늘이 시험일이야. 파이팅.</p>
        <?php else: ?>
            <h2 class="big grad">D+<?= abs($dd) ?></h2><p class="muted">시험일이 지났어. 다음 시험 날짜로 바꿔줘.</p>
        <?php endif; ?>
        <form method="post" class="inline-form">
            <input type="hidden" name="action" value="exam_date">
            <input type="date" name="exam_date" value="<?= h($uc['exam_date'] ?? '') ?>" aria-label="시험 날짜">
            <button class="btn small" type="submit">저장</button>
        </form>
        <div class="stats4 mini stack">
            <div><span class="num" data-count="<?= $psum['n'] ?>">0</span><span class="lbl">연습 횟수</span></div>
            <div><span class="num"><?= $psum['avg'] ?? '-' ?></span><span class="lbl">연습 평균</span></div>
            <div><span class="num"><?= $psum['acc'] ?? '-' ?><?= $psum['acc'] !== null ? '%' : '' ?></span><span class="lbl">정답률</span></div>
        </div>
    </section>
</div>

<section class="card glass mock-card">
    <p class="eyebrow">실전 모의고사 <span class="badge accent"><?= (int)$cert['exam_total'] ?>문제 · <?= (int)$cert['minutes'] ?>분</span>
        <span class="badge soft">문제당 <?= (int)$cert['q_seconds'] ?>초 · 힌트 없음 · 일시정지 기록</span></p>
    <div class="mock-grid">
        <div>
            <h2 class="card-h"><?= implode(' + ', array_map(fn($s) => h($s['name']) . ' ' . $s['count'], $cert['subjects'])) ?></h2>
            <?php if ($esum['n']): ?>
                <div class="stats4 mini">
                    <div><span class="num" data-count="<?= $esum['n'] ?>">0</span><span class="lbl">응시</span></div>
                    <div><span class="num" data-count="<?= (int)$esum['best'] ?>">0</span><span class="lbl">최고점</span></div>
                    <div><span class="num" data-count="<?= (int)$esum['avg'] ?>">0</span><span class="lbl">평균</span></div>
                    <div><span class="num"><?= $esum['pass'] ?>/<?= $esum['n'] ?></span><span class="lbl">합격 기준 충족</span></div>
                </div>
                <?php if ($lastE): ?><p class="muted small-note">최근 <?= (int)$lastE['score'] ?>점 · <a href="result.php?id=<?= (int)$lastE['id'] ?>">결과 보기</a> · <a href="history.php?kind=exam">전체 기록</a></p><?php endif; ?>
            <?php else: ?>
                <p class="muted">실제 시험과 같은 문항 수 · 시간으로 한 번 쳐보고 실력을 확인해봐. 기록은 연습과 따로 저장돼.</p>
            <?php endif; ?>
            <?php if (!$cur): ?>
                <form method="post" action="start.php" class="quick-form" onsubmit="return confirm('실전 모의고사를 시작할까? <?= (int)$cert['exam_total'] ?>문제, 문제당 <?= (int)$cert['q_seconds'] ?>초 제한이야.');">
                    <input type="hidden" name="action" value="new"><input type="hidden" name="cert" value="<?= h($cert['code']) ?>"><input type="hidden" name="kind" value="exam">
                    <?= diffPicker('e') ?>
                    <button class="btn primary glow" type="submit">실전 모의고사 시작</button>
                </form>
            <?php endif; ?>
        </div>
        <div><?php if ($erecent): ?><canvas class="chart-canvas short" data-chart="trend" data-href="result.php?id=" data-points="<?= h(json_encode($trendPts($erecent), JSON_UNESCAPED_UNICODE)) ?>" aria-label="모의고사 점수 추이"></canvas><?php endif; ?></div>
    </div>
</section>

<div class="grid-2">
    <section class="card glass tilt">
        <h2>연습 점수 추이</h2>
        <?php if (!$recent): ?>
            <p class="muted">아직 끝낸 연습이 없어. 첫 연습을 풀어보자.</p>
        <?php else: ?>
            <canvas class="chart-canvas" data-chart="trend" data-href="result.php?id=" data-points="<?= h(json_encode($trendPts($recent), JSON_UNESCAPED_UNICODE)) ?>" aria-label="연습 점수 추이"></canvas>
            <p class="muted small-note">점선은 합격선 <?= (int)$cert['pass_total'] ?>점 · 점을 누르면 결과로 가.</p>
        <?php endif; ?>
    </section>
    <section class="card glass tilt">
        <h2>과목별 정답률 <small class="muted">전체 시험 합산</small></h2>
        <div class="rings">
            <?php foreach ($cert['subjects'] as $no => $s): $st = $subj[$no]; $p = pct($st['c'], $st['t']); ?>
                <figure class="ring-fig">
                    <canvas width="120" height="120" data-chart="gauge" data-value="<?= $st['t'] ? $p : 0 ?>" data-line="<?= (int)($cert['pass_subject'] ?? 0) ?>"
                            data-label="<?= $st['t'] ? $p . '%' : '-' ?>" aria-label="<?= $no ?>과목 정답률"></canvas>
                    <figcaption><b><?= $no ?>과목</b><span><?= h($s['name']) ?></span><small class="muted"><?= $st['c'] ?> / <?= $st['t'] ?></small></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php if ($cert['pass_subject'] !== null): ?><p class="muted small-note">링의 흰 눈금은 과락 기준 <?= (int)$cert['pass_subject'] ?>%.</p><?php endif; ?>
    </section>
</div>

<section class="card glass">
    <h2>단원별 약점 <small class="muted">정답률이 낮은 순</small></h2>
    <?php if (!$topics): ?>
        <p class="muted">시험을 한 번이라도 끝내면 단원별 분석이 여기 나와.</p>
    <?php else: ?>
        <div class="topic-wrap">
            <canvas class="radar" data-chart="radar" data-points="<?= h(json_encode($radar, JSON_UNESCAPED_UNICODE)) ?>" aria-label="단원별 정답률 레이더"></canvas>
            <div class="topics">
                <?php foreach ($topics as $t): $p = pct($t['c'], $t['t']); ?>
                    <div class="topic-row">
                        <span class="tag s<?= $t['subject'] ?>"><?= $t['subject'] ?>과목</span>
                        <a class="tname" href="start.php?topic=<?= urlencode($t['topic']) ?>" title="이 단원만 풀기"><?= h($t['topic']) ?></a>
                        <div class="track"><i class="<?= $p < 40 ? 'bad' : ($p < 70 ? 'warn' : 'ok') ?>" style="--w:<?= $p ?>%"></i></div>
                        <span class="tnum"><?= $p ?>% <small>(<?= $t['c'] ?>/<?= $t['t'] ?>)</small></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

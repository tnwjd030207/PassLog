<?php
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/analysis.php';

$cert = currentCert();
$topics = topicAnalysis($cert);
$subj = subjectStats($cert);
$msgs = overallComments($cert, $topics, $subj);
$repeat = repeatWrong($cert, 5);
$last = qv("SELECT id FROM tests WHERE user_id = ? AND cert_id = ? AND status = 'done' ORDER BY id DESC LIMIT 1", [uid(), $cert['id']]);
$radar = array_map(fn($t) => ['l' => $t['topic'], 'v' => $t['p']], array_slice($topics, 0, 12));
$lt = lessonTree($cert);

$pageTitle = $cert['short_name'] . ' 결과 분석';
$active = 'analysis';
$page = 'result';
require __DIR__ . '/includes/header.php';
?>
<section class="card glass">
    <h1><?= h($cert['short_name']) ?> 약점 분석 <small class="muted">끝낸 시험 전체(연습 + 실전) 기준</small></h1>
    <?php foreach ($msgs as [$kind, $text]): ?>
        <div class="callout <?= h($kind) ?>"><?= h($text) ?></div>
    <?php endforeach; ?>
    <div class="start-row">
        <?php if ($last): ?><a class="btn small" href="result.php?id=<?= (int)$last ?>">가장 최근 결과</a><?php endif; ?>
        <a class="btn small" href="wrong.php">오답노트</a>
        <a class="btn small" href="history.php">시험 기록</a>
        <a class="btn small" href="learn.php">강의 (진도 <?= $lt['sum']['pct'] ?>%)</a>
    </div>
</section>

<section class="card glass">
    <h2>과목별 정답률</h2>
    <div class="rings">
        <?php foreach ($cert['subjects'] as $no => $s): $st = $subj[$no]; $p = pct($st['c'], $st['t']); ?>
            <figure class="ring-fig">
                <canvas width="120" height="120" data-chart="gauge" data-value="<?= $st['t'] ? $p : 0 ?>" data-line="<?= (int)($cert['pass_subject'] ?? 0) ?>" data-label="<?= $st['t'] ? $p . '%' : '-' ?>" aria-label="<?= $no ?>과목 정답률"></canvas>
                <figcaption><b><?= $no ?>과목</b><span><?= h($s['name']) ?></span><small class="muted"><?= $st['c'] ?> / <?= $st['t'] ?></small></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($topics): ?>
<section class="card glass">
    <h2>자주 틀리는 유형 <small class="muted">정답률이 낮은 순 · 코멘트</small></h2>
    <div class="topic-wrap">
        <canvas class="radar" data-chart="radar" data-points="<?= h(json_encode($radar, JSON_UNESCAPED_UNICODE)) ?>" aria-label="단원별 정답률 레이더"></canvas>
        <div class="an-list"><?php renderTopicCards($cert, $topics); ?></div>
    </div>
</section>

<?php if ($repeat): ?>
<section class="card glass">
    <h2>반복해서 틀리는 문제</h2>
    <ul class="plain">
        <?php foreach ($repeat as $r): ?>
            <li><span class="tag s<?= (int)$r['subject'] ?>"><?= (int)$r['subject'] ?>과목</span>
                <b><?= h($r['topic']) ?></b> · <?= (int)$r['wrong_n'] ?>번 틀림 / <?= (int)$r['tries'] ?>번 풂 —
                <span class="muted"><?= h(mb_strimwidth($r['question'], 0, 70, '…', 'UTF-8')) ?></span></li>
        <?php endforeach; ?>
    </ul>
    <p class="muted small-note">이 문제들은 <a href="wrong.php">오답노트</a>에서 해설을 다시 읽고, 같은 단원 문제를 이어서 풀어봐.</p>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

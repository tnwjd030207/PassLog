<?php
// 학습: 자격증 → 과목 → 단원 → 강의 목차, 진도율, 이어서 공부하기
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';

$cert = currentCert();
$lt = lessonTree($cert);
$sum = $lt['sum'];
$resume = uid() ? resumeLesson($cert, $lt['flat']) : ($lt['flat'][0] ?? null);
$topics = certTopics($cert['id']);
$tipOf = [];
foreach ($topics as $list) foreach ($list as $t) $tipOf[$t['name']] = $t['tip'];

$pageTitle = $cert['short_name'] . ' 학습';
$active = 'learn';
$page = 'result';
require __DIR__ . '/includes/header.php';
?>
<section class="card glass learn-hero">
    <div class="learn-row">
        <canvas width="140" height="140" data-chart="gauge" data-value="<?= $sum['pct'] ?>" data-label="<?= $sum['pct'] ?>%" aria-label="강의 진도율 <?= $sum['pct'] ?>%"></canvas>
        <div>
            <p class="eyebrow"><span class="cert-dot" style="--cc:<?= h($cert['color']) ?>"></span><?= h($cert['name']) ?> 강의 노트</p>
            <h1 class="card-h">진도 <?= $sum['done'] ?> / <?= $sum['total'] ?> 강의 <small class="muted">· 전체 약 <?= (int)round($sum['minutes'] / 60, 0) ?>시간 <?= $sum['minutes'] % 60 ?>분</small></h1>
            <?php if (isGuest()): ?>
                <p class="muted">로그인 없이도 강의를 읽을 수 있어. <a href="kakao_login.php?next=learn.php">카카오로 로그인</a>하면 진도율이 저장되고, 다음에 들어오면 읽던 곳부터 이어서 보여줘.</p>
            <?php endif; ?>
            <div class="start-row">
                <?php if ($resume): ?>
                    <a class="btn primary glow" href="lesson.php?id=<?= (int)$resume['id'] ?>"><?= !empty($resume['resume']) ? '이어서 공부하기' : ($sum['done'] ? '다음 강의로' : '처음부터 공부하기') ?></a>
                    <span class="muted small"><?= h($resume['title']) ?><?= !empty($resume['resume']) && (int)$resume['last_pct'] > 0 ? ' · ' . (int)$resume['last_pct'] . '%까지 읽음' : '' ?></span>
                <?php elseif ($sum['total']): ?>
                    <span class="badge ok">모든 강의 완료!</span> <a class="btn small" href="start.php?cert=<?= h($cert['code']) ?>">실전 모의고사 보러 가기</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="subj-progress">
        <?php foreach ($cert['subjects'] as $no => $s): $bs = $sum['by_subject'][$no] ?? ['total' => 0, 'done' => 0]; $p = pct($bs['done'], $bs['total']); ?>
            <div class="topic-row">
                <span class="tag s<?= $no ?>"><?= $no ?>과목</span><a class="tname" href="#s-<?= $no ?>"><?= h($s['name']) ?></a>
                <div class="track"><i class="ok" style="--w:<?= $p ?>%"></i></div><span class="tnum"><?= $bs['done'] ?>/<?= $bs['total'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php foreach ($cert['subjects'] as $no => $s): ?>
    <section class="card glass lesson-subject" id="s-<?= $no ?>">
        <h2><span class="tag s<?= $no ?>"><?= $no ?>과목</span> <?= h($s['name']) ?> <small class="muted">실전 <?= $s['count'] ?>문제</small></h2>
        <?php foreach ($lt['tree'][$no] ?? [] as $topic => $lessons): ?>
            <div class="lesson-topic" id="t-<?= h(md5($topic)) ?>">
                <div class="lt-head">
                    <b><?= h($topic) ?></b>
                    <a class="small" href="start.php?cert=<?= h($cert['code']) ?>&amp;topic=<?= urlencode($topic) ?>">이 단원 문제 풀기 →</a>
                </div>
                <?php if (!empty($tipOf[$topic])): ?><p class="muted small lt-tip"><?= h($tipOf[$topic]) ?></p><?php endif; ?>
                <ul class="lesson-list">
                    <?php foreach ($lessons as $l):
                        $st = $l['status'] === 'done' ? 'done' : ($l['status'] === 'started' ? 'started' : 'new'); ?>
                        <li class="ls-<?= $st ?>">
                            <a href="lesson.php?id=<?= (int)$l['id'] ?>">
                                <span class="ls-icon" aria-hidden="true"><?= $st === 'done' ? '✓' : ($st === 'started' ? '◐' : '○') ?></span>
                                <span class="ls-title"><?= h($l['title']) ?></span>
                                <span class="ls-meta"><?= $st === 'done' ? '완료' : ($st === 'started' ? (int)$l['read_pct'] . '% 읽음' : '') ?> · <?= (int)$l['minutes'] ?>분</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

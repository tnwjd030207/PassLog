<?php
// 강의 노트 읽기 — 읽은 위치 자동 저장, 학습 완료 표시, 이전/다음 강의
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/markdown.php';

$lesson = loadLesson((int)($_GET['id'] ?? 0));
if (!$lesson) { flash('강의를 찾을 수 없어.'); redirect('learn.php'); }
startSession();
$_SESSION['cert'] = $lesson['cert_code'];
$cert = certById((int)$lesson['cert_id']);
touchLesson($lesson);
$prog = lessonProgress((int)$lesson['id']);
$nb = lessonNeighbors($lesson);
$toc = [];
$html = markdown((string)$lesson['body'], $toc);
$done = ($prog['status'] ?? '') === 'done';
$sum = uid() ? lessonTree($cert)['sum'] : null;

$pageTitle = $lesson['title'];
$active = 'learn';
$page = 'result';
require __DIR__ . '/includes/header.php';
?>
<div class="read-bar" aria-hidden="true"><i id="read-bar"></i></div>
<div class="lesson-wrap" id="lesson" data-id="<?= (int)$lesson['id'] ?>" data-member="<?= uid() ? 1 : 0 ?>"
     data-last="<?= (int)($prog['last_pct'] ?? 0) ?>" data-done="<?= $done ? 1 : 0 ?>">
    <aside class="lesson-side card glass">
        <p class="eyebrow"><a href="learn.php">← <?= h($cert['short_name']) ?> 목차</a></p>
        <p class="small"><span class="tag s<?= (int)$lesson['subject'] ?>"><?= (int)$lesson['subject'] ?>과목</span> <?= h(subjectName($cert, (int)$lesson['subject'])) ?></p>
        <p class="small muted"><?= h($lesson['topic']) ?> · <?= $nb['index'] ?> / <?= $nb['count'] ?>번째 강의</p>
        <?php if ($toc): ?>
            <nav class="toc" aria-label="이 강의 목차">
                <?php foreach ($toc as $t): ?><a href="#<?= h($t['id']) ?>"><?= h($t['title']) ?></a><?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <?php if ($sum): ?><p class="small muted">전체 진도 <b id="cert-pct"><?= $sum['pct'] ?></b>% (<span id="cert-done"><?= $sum['done'] ?></span>/<?= $sum['total'] ?>)</p><?php endif; ?>
    </aside>

    <article class="card glass lesson-body">
        <p class="eyebrow"><?= h($cert['name']) ?> · 약 <?= (int)$lesson['minutes'] ?>분</p>
        <h1><?= h($lesson['title']) ?></h1>
        <div class="md" id="md"><?= $html ?></div>

        <div class="lesson-end">
            <?php if (uid()): ?>
                <button type="button" class="btn <?= $done ? 'ok-btn' : 'primary glow' ?>" id="done-btn"><?= $done ? '✓ 학습 완료됨 (취소하려면 누르기)' : '학습 완료로 표시' ?></button>
            <?php else: ?>
                <p class="muted">진도를 저장하려면 <a href="kakao_login.php?next=<?= urlencode('lesson.php?id=' . (int)$lesson['id']) ?>">카카오로 로그인</a>해줘.</p>
            <?php endif; ?>
            <a class="btn" href="start.php?cert=<?= h($cert['code']) ?>&amp;topic=<?= urlencode($lesson['topic']) ?>">이 단원 문제 풀기</a>
        </div>
        <div class="lesson-nav">
            <?php if ($nb['prev']): ?><a class="btn small" href="lesson.php?id=<?= (int)$nb['prev'] ?>">← 이전 강의</a><?php else: ?><span></span><?php endif; ?>
            <?php if ($nb['next']): ?><a class="btn small primary" href="lesson.php?id=<?= (int)$nb['next'] ?>" id="next-lesson">다음 강의 →</a><?php else: ?><a class="btn small" href="learn.php">목차로</a><?php endif; ?>
        </div>
    </article>
</div>
<div class="toast" id="toast" hidden></div>
<script src="assets/learn.js?v=2"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>

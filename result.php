<?php
// 시험 결과표 + 문제별 해설 (비회원도 자기 시험 결과는 볼 수 있음)
declare(strict_types=1);
define('GUEST_OK', true);
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/analysis.php';

$id = (int)($_GET['id'] ?? 0);
$test = loadTest($id);
if (!$test) { http_response_code(404); flash('시험 기록을 찾을 수 없어.'); redirect('index.php'); }
if ($test['status'] !== 'done') redirect(testUrl($test));
$cert = certById((int)$test['cert_id']);
$_SESSION['cert'] = $cert['code'];   // 머리말의 자격증 표시를 이 시험에 맞춤

$items = qall('SELECT ti.*, q.code, q.subject, q.topic, q.difficulty, q.question, q.code_block, q.choices, q.answer, q.explanation, q.mono
                 FROM test_items ti JOIN questions q ON q.id = ti.question_id
                WHERE ti.test_id = ? ORDER BY ti.position', [$id]);
$subj = testSubjects($test);
$j = judge($cert, $subj);
$noHint = 0;
foreach ($items as $it) if ((int)$it['is_correct'] && !(int)$it['hint_used']) $noHint++;
$timed = (int)$test['q_seconds'] > 0;
$answeredSpent = array_filter($items, fn($it) => $it['spent_sec'] !== null && !(int)$it['timed_out']);
$avgSpent = $answeredSpent ? (int)round(array_sum(array_column($answeredSpent, 'spent_sec')) / count($answeredSpent)) : null;
$isExam = $test['kind'] === 'exam';
$guest = isGuest();

$an = topicAnalysis($cert, $id);
$mine = array_values(array_filter($an, fn($x) => $x['wrong'] > 0));
$allAn = $guest ? [] : topicAnalysis($cert);
$allMap = array_column($allAn, null, 'topic');

$pageTitle = $cert['short_name'] . ' ' . kindLabel($test) . ' 결과';
$active = $guest ? 'test' : 'history';
$page = 'result';
require __DIR__ . '/includes/header.php';
?>
<section class="card glass result-top <?= $j['pass'] ? 'pass' : 'fail' ?>" data-confetti="<?= $j['pass'] ? 1 : 0 ?>">
    <div class="result-main">
        <canvas width="220" height="220" data-chart="gauge" data-value="<?= (int)$test['score'] ?>" data-line="<?= (int)$cert['pass_total'] ?>" data-big="1"
                data-label="<?= (int)$test['score'] ?>" data-sub="점" aria-label="점수 <?= (int)$test['score'] ?>점"></canvas>
        <div>
            <p class="eyebrow"><span class="badge <?= $isExam ? 'accent' : 'soft' ?>"><?= h(kindLabel($test)) ?></span>
                <?= h($cert['name']) ?> · <?= h($test['test_date']) ?> <span class="muted">(<?= h(date('H:i', strtotime((string)$test['finished_at']))) ?> 제출)</span>
                <?php if ($test['difficulty']): ?><span class="badge soft">난이도 <?= h(DIFF_LABEL[(int)$test['difficulty']]) ?></span><?php endif; ?></p>
            <h1 class="big"><span class="badge lg <?= $j['pass'] ? 'ok' : 'bad' ?>"><?= $j['pass'] ? '합격 기준 충족' : '합격 기준 미달' ?></span></h1>
            <p class="muted"><?= $j['pass'] ? '합격 기준(' . h($cert['exam_note']) . ')을 모두 넘겼어.' : '이유: ' . h(implode(' · ', $j['reasons'])) ?></p>
            <?php if ($guest): ?>
                <div class="callout info">비회원 결과는 저장되지 않고 이 브라우저에서 잠깐만 볼 수 있어. <a href="kakao_login.php">카카오로 로그인</a>하면 기록 · 오답노트 · 약점 분석 · 실전 모의고사를 쓸 수 있어.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats4 inner">
        <div><span class="num"><?= (int)$test['correct_cnt'] ?>/<?= (int)$test['total_cnt'] ?></span><span class="lbl">정답</span></div>
        <div><span class="num"><?= h(fmtDuration($test['duration_sec'] === null ? null : (int)$test['duration_sec'])) ?></span><span class="lbl"><?= $timed ? '푼 시간(일시정지 제외)' : '걸린 시간' ?></span></div>
        <?php if ($timed): ?>
            <div><span class="num"><?= (int)$test['pause_count'] ?>회</span><span class="lbl">일시정지 횟수</span></div>
            <div><span class="num"><?= h(fmtDuration((int)$test['pause_total_sec'])) ?></span><span class="lbl">총 일시정지 시간</span></div>
            <div><span class="num"><?= (int)$test['timeouts'] ?></span><span class="lbl">시간 초과 문제</span></div>
            <div><span class="num"><?= $avgSpent === null ? '-' : $avgSpent . '초' ?></span><span class="lbl">평균 풀이 (제한 <?= (int)$test['q_seconds'] ?>초)</span></div>
        <?php else: ?>
            <div><span class="num"><?= (int)$test['hints_used'] ?></span><span class="lbl">힌트 사용</span></div>
            <div><span class="num"><?= $noHint ?></span><span class="lbl">힌트 없이 정답</span></div>
        <?php endif; ?>
    </div>
</section>

<section class="card glass">
    <h2>과목별 결과표</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>과목</th><th>정답 / 문항</th><th>정답률</th><th>판정</th></tr></thead>
        <tbody>
        <?php foreach ($subj as $no => $s): $p = pct($s['c'], $s['t']); $fail = $cert['pass_subject'] !== null && $s['c'] * 100 < $cert['pass_subject'] * $s['t']; ?>
            <tr>
                <td><?= $no ?>. <?= h(subjectName($cert, $no)) ?></td>
                <td><?= $s['c'] ?> / <?= $s['t'] ?></td>
                <td><div class="track inline"><i class="<?= $p < 40 ? 'bad' : ($p < 60 ? 'warn' : 'ok') ?>" style="--w:<?= $p ?>%"></i></div> <?= $p ?>%</td>
                <td><?php if ($cert['pass_subject'] === null): ?><span class="muted">과락 없음</span><?php else: ?><span class="badge <?= $fail ? 'bad' : 'ok' ?>"><?= $fail ? '과락' : '통과' ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="sum"><td><b>합계</b></td><td><b><?= (int)$test['correct_cnt'] ?> / <?= (int)$test['total_cnt'] ?></b></td><td><b><?= (int)$test['score'] ?>점</b> <span class="muted">(합격 <?= (int)$cert['pass_total'] ?>점)</span></td>
                <td><span class="badge <?= $j['pass'] ? 'ok' : 'bad' ?>"><?= $j['pass'] ? '합격' : '불합격' ?></span></td></tr>
        </tbody>
    </table></div>
</section>

<section class="card glass">
    <h2>이번 시험에서 많이 틀린 유형 <small class="muted">그리고 공부 코멘트</small></h2>
    <?php if (!$mine): ?>
        <div class="callout ok">틀린 문제가 없어! 이 상태를 실전 모의고사에서도 유지해보자.</div>
    <?php else: usort($mine, fn($a, $b) => [$b['wrong'], $a['p']] <=> [$a['wrong'], $b['p']]); ?>
        <div class="an-list">
        <?php foreach (array_slice($mine, 0, 6) as $m): $tot = $allMap[$m['topic']] ?? $m; ?>
            <div class="an-row lv-<?= h($tot['level']) ?>">
                <div class="an-head">
                    <span class="tag s<?= (int)$m['subject'] ?>"><?= (int)$m['subject'] ?>과목</span>
                    <b><?= h($m['topic']) ?></b>
                    <span class="lv lv-<?= h($tot['level']) ?>"><?= h(LEVEL_LABEL[$tot['level']]) ?></span>
                    <span class="an-num">이번 <?= $m['wrong'] ?>/<?= $m['t'] ?>문제 틀림<?= $guest ? '' : ' <small>· 누적 정답률 ' . $tot['p'] . '%</small>' ?></span>
                </div>
                <p class="an-comment"><?= h($tot['comment']) ?></p>
                <p class="an-links small"><a href="learn.php?cert=<?= h($cert['code']) ?>#t-<?= h(md5($m['topic'])) ?>">이 단원 강의 보기</a></p>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!$guest): ?><p class="small-note"><a href="analysis.php">내 약점 전체 분석 보기 →</a></p><?php endif; ?>
</section>

<h2 class="sec-title">문제별 해설</h2>
<?php foreach ($items as $it):
    $choices = json_decode($it['choices'], true);
    $ok = (int)$it['is_correct'] === 1;
    $pick = $it['user_answer'] === null ? null : (int)$it['user_answer'];
    $ans = (int)$it['answer']; ?>
    <article class="card glass review <?= $ok ? 'right' : 'wrong' ?>">
        <div class="qmeta">
            <span class="qno">Q<?= (int)$it['position'] ?></span>
            <span class="verdict <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '정답' : ((int)$it['timed_out'] ? '시간 초과' : ($pick === null ? '안 풂' : '오답')) ?></span>
            <?= subjTag($cert, (int)$it['subject']) ?>
            <span class="tag topic"><?= h($it['topic']) ?></span>
            <?= stars((int)$it['difficulty']) ?>
            <?php if ((int)$it['hint_used']): ?><span class="tag hinted">힌트 사용</span><?php endif; ?>
            <?php if ($it['spent_sec'] !== null): ?><span class="tag">⏱ <?= (int)$it['spent_sec'] ?>초</span><?php endif; ?>
        </div>
        <p class="qtext"><?= nl2br(h($it['question'])) ?></p>
        <?php if (!empty($it['code_block'])): ?><pre class="sql"><code><?= h($it['code_block']) ?></code></pre><?php endif; ?>
        <ul class="opts <?= (int)$it['mono'] ? 'mono' : '' ?>">
            <?php foreach ($choices as $n => $text): $n++; ?>
                <li class="<?= $n === $ans ? 'is-answer' : '' ?> <?= $n === $pick && $n !== $ans ? 'is-mine-wrong' : '' ?>">
                    <span class="cnum"><?= CIRCLED[$n] ?></span><span class="ctext"><?= h($text) ?></span>
                    <?php if ($n === $ans): ?><em>정답</em><?php endif; ?>
                    <?php if ($n === $pick): ?><em class="mine">내 선택</em><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="explain"><b>해설</b><p><?= nl2br(h($it['explanation'])) ?></p></div>
    </article>
<?php endforeach; ?>
<div class="start-row">
    <a class="btn primary glow" href="start.php?cert=<?= h($cert['code']) ?>">다시 풀기</a>
    <?php if ($guest): ?>
        <?= kakaoButton('index.php', '카카오로 시작하고 기록 남기기') ?>
    <?php else: ?>
        <a class="btn" href="history.php?kind=<?= h($test['kind']) ?>">시험 기록</a>
        <a class="btn" href="wrong.php">오답노트</a>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

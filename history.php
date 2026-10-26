<?php
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';

$cert = currentCert();
$kind = (string)($_GET['kind'] ?? 'all');
if (!in_array($kind, ['all', 'practice', 'exam'], true)) $kind = 'all';
$allRows = qall('SELECT * FROM tests WHERE user_id = ? AND cert_id = ? ORDER BY id DESC', [uid(), $cert['id']]);
$seq = []; $cnt = [];
foreach (array_reverse($allRows) as $r) {
    $k = $r['test_date'] . $r['kind'];
    $cnt[$k] = ($cnt[$k] ?? 0) + 1;
    $seq[$r['id']] = $cnt[$k];
}
$rows = array_values(array_filter($allRows, fn($r) => $kind === 'all' || $r['kind'] === $kind));
$done = array_values(array_filter(array_reverse($rows), fn($r) => $r['status'] === 'done'));
$trend = array_map(fn($t) => [
    's' => (int)$t['score'], 'p' => (int)$t['passed'], 'id' => (int)$t['id'],
    'l' => date('n/j', strtotime($t['test_date'])), 'm' => $t['kind'] === 'exam' ? 1 : 0,
], $done);
$tab = fn(string $k, string $label) => '<a class="chip ' . ($kind === $k ? 'on' : '') . '" href="history.php' . ($k === 'all' ? '' : '?kind=' . $k) . '">' . $label . '</a>';

$pageTitle = $cert['short_name'] . ' 시험 기록';
$active = 'history';
$page = 'result';
require __DIR__ . '/includes/header.php';
?>
<section class="card glass">
    <h1><?= h($cert['short_name']) ?> 시험 기록</h1>
    <div class="filters"><?= $tab('all', '전체') ?><?= $tab('practice', '연습') ?><?= $tab('exam', '실전 모의고사') ?></div>
    <?php if (!$trend): ?>
        <p class="muted">아직 끝낸 시험이 없어.</p>
    <?php else: ?>
        <canvas class="chart-canvas tall" data-chart="trend" data-href="result.php?id=" data-points="<?= h(json_encode($trend, JSON_UNESCAPED_UNICODE)) ?>" aria-label="시험 점수 추이"></canvas>
        <p class="muted small-note">점선은 합격 기준 <?= (int)$cert['pass_total'] ?>점. 초록 점은 기준 충족, 빨간 점은 미달(과락 포함). 실전 모의고사는 다이아몬드 모양.</p>
    <?php endif; ?>
</section>

<section class="card glass">
    <h2>전체 기록</h2>
    <?php if (!$rows): ?>
        <p class="muted">기록이 아직 없어.</p>
    <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>종류</th><th>날짜</th><th>점수</th><th>정답</th><th>과목별</th><th>난이도</th><th>시간</th><th>일시정지</th><th>판정</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $t): $isDone = $t['status'] === 'done'; ?>
                <tr>
                    <td><span class="badge <?= $t['kind'] === 'exam' ? 'accent' : 'soft' ?>"><?= h(kindLabel($t)) ?></span></td>
                    <td><?= h($t['test_date']) ?><?php if ($cnt[$t['test_date'] . $t['kind']] > 1): ?> <small class="muted"><?= $seq[$t['id']] ?>회차</small><?php endif; ?></td>
                    <?php if ($isDone): ?>
                        <td><b><?= (int)$t['score'] ?></b></td>
                        <td><?= (int)$t['correct_cnt'] ?>/<?= (int)$t['total_cnt'] ?></td>
                        <td class="small"><?= implode(' · ', array_map(fn($no, $s) => $no . '과목 ' . $s['c'] . '/' . $s['t'], array_keys(testSubjects($t)), testSubjects($t))) ?></td>
                        <td><?= $t['difficulty'] ? h(DIFF_LABEL[(int)$t['difficulty']]) : '혼합' ?></td>
                        <td><?= h(fmtDuration($t['duration_sec'] === null ? null : (int)$t['duration_sec'])) ?></td>
                        <td><?= (int)$t['q_seconds'] > 0 ? (int)$t['pause_count'] . '회 · ' . h(fmtDuration((int)$t['pause_total_sec'])) : '-' ?></td>
                        <td><span class="badge <?= $t['passed'] ? 'ok' : 'bad' ?>"><?= $t['passed'] ? '충족' : '미달' ?></span></td>
                        <td><a href="result.php?id=<?= (int)$t['id'] ?>">결과</a></td>
                    <?php else: ?>
                        <td colspan="7" class="muted"><?= $t['test_date'] >= activeSince() ? '진행 중' : '끝내지 못한 시험' ?></td>
                        <td><?php if ($t['test_date'] >= activeSince()): ?><a href="<?= testUrl($t) ?>">이어서</a><?php endif; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

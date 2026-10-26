<?php
// 설치/업데이트: 테이블 생성·변경(마이그레이션) + 자격증/문제/강의 데이터 등록.
// 몇 번을 다시 실행해도 안전해 (같은 코드는 덮어쓰기, 회원 기록은 그대로).
//   브라우저:  http://서버주소/sqld/install.php      터미널:  php install.php
declare(strict_types=1);
define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/functions.php';

$cli = PHP_SAPI === 'cli';
$msgs = [];
$ok = true;

function hasColumn(string $table, string $col): bool
{
    return (bool)qv('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $col]);
}
function hasIndex(string $table, string $idx): bool
{
    return (bool)qv('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$table, $idx]);
}
function columnType(string $table, string $col): string
{
    return (string)qv('SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $col]);
}
/** 컬럼이 없을 때만 추가 (DDL 은 바인딩이 안 되므로 여기 적힌 고정 문자열만 실행) */
function addColumn(string $table, string $col, string $ddl, array &$msgs): void
{
    if (hasColumn($table, $col)) return;
    pdo()->exec("ALTER TABLE `$table` ADD COLUMN $ddl");
    $msgs[] = "$table.$col 컬럼 추가";
}

try {
    // 1) 테이블 만들기 (없는 것만)
    $sql = (string)file_get_contents(__DIR__ . '/sql/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (preg_match('/^(CREATE DATABASE|GRANT|FLUSH|USE)\b/i', $stmt)) continue;
        pdo()->exec($stmt);
    }
    $msgs[] = '테이블 준비 완료';

    // 2) 예전 구조 → 새 구조 (이미 바뀐 건 건너뜀)
    if (hasIndex('tests', 'uq_date')) pdo()->exec('ALTER TABLE tests DROP INDEX uq_date');
    addColumn('users', 'nickname', 'nickname VARCHAR(20) NULL AFTER username', $msgs);
    addColumn('users', 'email', 'email VARCHAR(190) NULL AFTER nickname', $msgs);
    addColumn('users', 'email_verified_at', 'email_verified_at DATETIME NULL AFTER email', $msgs);
    addColumn('users', 'plan', "plan ENUM('free','premium') NOT NULL DEFAULT 'free' AFTER pass_hash", $msgs);
    addColumn('users', 'plan_expires_at', 'plan_expires_at DATETIME NULL AFTER plan', $msgs);
    if (!hasIndex('users', 'uq_nickname')) pdo()->exec('ALTER TABLE users ADD UNIQUE KEY uq_nickname (nickname)');
    if (!hasIndex('users', 'uq_email')) pdo()->exec('ALTER TABLE users ADD UNIQUE KEY uq_email (email)');
    foreach (['certs', 'topics', 'questions', 'lessons'] as $tb) {   // 관리자 수정본 보호 표시
        addColumn($tb, 'admin_edited', 'admin_edited TINYINT NOT NULL DEFAULT 0', $msgs);
    }
    // 첫 관리자 계정 (관리자가 하나도 없을 때만): admin / 121903 — 로그인 후 바로 비밀번호 변경 안내
    if (!(int)qv('SELECT COUNT(*) FROM admins')) {
        q('INSERT INTO admins (username, pass_hash, pw_changed) VALUES (?, ?, 0)', ['admin', password_hash('121903', PASSWORD_DEFAULT)]);
        $msgs[] = '관리자 계정 admin 생성 (초기 비밀번호는 로그인 후 바로 바꿔줘)';
    }
    addColumn('users', 'kakao_id', 'kakao_id VARCHAR(20) NULL AFTER pass_hash', $msgs);   // 카카오 로그인
    if (!hasIndex('users', 'uq_kakao')) pdo()->exec('ALTER TABLE users ADD UNIQUE KEY uq_kakao (kakao_id)');

    addColumn('questions', 'cert_id', 'cert_id INT NOT NULL DEFAULT 0 AFTER id', $msgs);
    if (!hasIndex('questions', 'idx_cert_subject')) pdo()->exec('ALTER TABLE questions ADD KEY idx_cert_subject (cert_id, subject, difficulty)');
    if (!hasIndex('questions', 'idx_cert_topic')) pdo()->exec('ALTER TABLE questions ADD KEY idx_cert_topic (cert_id, topic)');

    addColumn('tests', 'user_id', 'user_id INT NULL AFTER id', $msgs);
    addColumn('tests', 'guest_key', 'guest_key CHAR(64) NULL AFTER user_id', $msgs);
    addColumn('tests', 'cert_id', 'cert_id INT NOT NULL DEFAULT 0 AFTER guest_key', $msgs);
    if (str_contains(columnType('tests', 'kind'), "'daily'")) {
        pdo()->exec("ALTER TABLE tests MODIFY kind ENUM('daily','mock','practice','exam') NOT NULL DEFAULT 'practice'");
        pdo()->exec("UPDATE tests SET kind = 'practice' WHERE kind = 'daily'");
        pdo()->exec("UPDATE tests SET kind = 'exam' WHERE kind = 'mock'");
        pdo()->exec("ALTER TABLE tests MODIFY kind ENUM('practice','exam') NOT NULL DEFAULT 'practice'");
        $msgs[] = '시험 종류 이름 변경 (데일리→연습, 모의고사→실전 모의고사)';
    }
    addColumn('tests', 'difficulty', 'difficulty TINYINT NULL AFTER kind', $msgs);
    addColumn('tests', 'subject_filter', 'subject_filter TINYINT NULL AFTER difficulty', $msgs);
    addColumn('tests', 'topic_filter', 'topic_filter VARCHAR(40) NULL AFTER subject_filter', $msgs);
    addColumn('tests', 'q_seconds', 'q_seconds SMALLINT NULL AFTER duration_sec', $msgs);
    addColumn('tests', 'cur_pos', 'cur_pos SMALLINT NOT NULL DEFAULT 1 AFTER q_seconds', $msgs);
    addColumn('tests', 'cur_started_ts', 'cur_started_ts INT NULL AFTER cur_pos', $msgs);
    addColumn('tests', 'paused_ts', 'paused_ts INT NULL AFTER cur_started_ts', $msgs);
    addColumn('tests', 'pause_count', 'pause_count SMALLINT NOT NULL DEFAULT 0 AFTER paused_ts', $msgs);
    addColumn('tests', 'pause_total_sec', 'pause_total_sec INT NOT NULL DEFAULT 0 AFTER pause_count', $msgs);
    addColumn('tests', 'subject_json', 'subject_json TEXT NULL AFTER score', $msgs);
    addColumn('tests', 'timeouts', 'timeouts SMALLINT NULL AFTER hints_used', $msgs);
    if (str_starts_with(columnType('tests', 'total_cnt'), 'tinyint')) {
        pdo()->exec('ALTER TABLE tests MODIFY total_cnt SMALLINT NOT NULL DEFAULT 0, MODIFY correct_cnt SMALLINT NULL, MODIFY hints_used SMALLINT NULL');
        pdo()->exec('ALTER TABLE test_items MODIFY position SMALLINT NOT NULL');
        $msgs[] = '문항 수 컬럼 확장 (최대 100문제 시험 대응)';
    }
    if (!hasIndex('tests', 'idx_user')) pdo()->exec('ALTER TABLE tests ADD KEY idx_user (user_id, status)');
    if (!hasIndex('tests', 'idx_user_cert')) pdo()->exec('ALTER TABLE tests ADD KEY idx_user_cert (user_id, cert_id, status)');
    if (!hasIndex('tests', 'idx_guest')) pdo()->exec('ALTER TABLE tests ADD KEY idx_guest (guest_key)');
    addColumn('test_items', 'spent_sec', 'spent_sec SMALLINT NULL AFTER answered_at', $msgs);
    addColumn('test_items', 'timed_out', 'timed_out TINYINT NOT NULL DEFAULT 0 AFTER spent_sec', $msgs);

    // 3) 자격증 · 단원 · 문제 · 강의 등록
    foreach (seedCerts(__DIR__ . '/data') as $m) $msgs[] = $m;

    // 4) 예전 SQLD 데이터 연결
    $sqldId = (int)qv("SELECT id FROM certs WHERE code = 'sqld'");
    $n = q('UPDATE questions SET cert_id = ? WHERE cert_id = 0', [$sqldId])->rowCount();
    if ($n) $msgs[] = "기존 문제 {$n}개를 SQLD 에 연결";
    $n = q('UPDATE tests SET cert_id = ? WHERE cert_id = 0', [$sqldId])->rowCount();
    if ($n) $msgs[] = "기존 시험 기록 {$n}건을 SQLD 에 연결";
    foreach (qall("SELECT k, v FROM settings WHERE k LIKE 'u%:exam_date'") as $s) {
        if (preg_match('/^u(\d+):exam_date$/', $s['k'], $m) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['v'])) {
            q('INSERT INTO user_certs (user_id, cert_id, exam_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE exam_date = COALESCE(exam_date, VALUES(exam_date))',
                [(int)$m[1], $sqldId, $s['v']]);
            q('DELETE FROM settings WHERE k = ?', [$s['k']]);
            $msgs[] = "회원 {$m[1]} 의 SQLD 시험일을 새 구조로 이동";
        }
    }
    $fixed = q('UPDATE tests SET duration_sec = NULL WHERE duration_sec > 14400 AND q_seconds IS NULL')->rowCount();
    if ($fixed) $msgs[] = "비정상적인 걸린 시간 {$fixed}건 정리";

    foreach (qall('SELECT c.name, COUNT(q.id) n FROM certs c LEFT JOIN questions q ON q.cert_id = c.id AND q.active = 1 GROUP BY c.id ORDER BY c.sort') as $r) {
        $msgs[] = "· {$r['name']}: 문제은행 {$r['n']}문제";
    }
} catch (Throwable $e) {
    $ok = false;
    $msgs[] = '실패: ' . $e->getMessage();
    $msgs[] = 'DB(sqld)가 없거나 계정 권한이 없을 수 있어. sql/schema.sql 을 root 로 먼저 실행했는지 확인해줘.';
}

if ($cli) {
    echo implode(PHP_EOL, $msgs), PHP_EOL;
    exit($ok ? 0 : 1);
}
$pageTitle = '설치';
$showCert = false;
require __DIR__ . '/includes/header.php';
?>
<section class="card glass">
    <h1>설치 <?= $ok ? '완료' : '실패' ?></h1>
    <ul class="plain">
        <?php foreach ($msgs as $m): ?><li><?= h($m) ?></li><?php endforeach; ?>
    </ul>
    <?php if ($ok): ?><p><a class="btn primary" href="index.php">홈으로 가기</a></p><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

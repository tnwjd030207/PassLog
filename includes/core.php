<?php
// 공통 기반: 브랜드 상수, PDO 헬퍼, 출력 헬퍼, 세션/로그인
declare(strict_types=1);

date_default_timezone_set('Asia/Seoul');
require_once __DIR__ . '/../config.php';

// ---- 브랜드 (이름을 바꾸려면 여기만 고치면 돼) ------------------------------
const BRAND_NAME    = 'PassLog';
const BRAND_KO      = '패스로그';
const BRAND_TAGLINE = 'IT 자격증, 합격까지의 모든 기록';

const CIRCLED = ['', '①', '②', '③', '④'];
const DIFF_LABEL = [0 => '혼합', 1 => '하', 2 => '중', 3 => '상'];
const GUEST_MAX_QUESTIONS = 20;          // 비회원 1회 최대 문제 수
const PRACTICE_COUNTS = [10, 20, 30, 50];

// ---- DB: 모든 쿼리는 이 함수로 (진짜 prepared statement + 값 바인딩) ----------
function pdo(): PDO
{
    static $ready = false;
    $pdo = db();
    if (!$ready) {
        // 에뮬레이션 끄기: SQL 과 값이 DB 서버에 따로 전달돼서 SQL 인젝션이 원천적으로 불가능해짐
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET time_zone = '+09:00'");   // DB 의 NOW() 와 PHP 시간(한국)을 맞춤
        $ready = true;
    }
    return $pdo;
}

/** prepared statement 실행. 값은 무조건 바인딩 (정수는 PARAM_INT 로) */
function q(string $sql, array $params = []): PDOStatement
{
    $st = pdo()->prepare($sql);
    foreach (array_values($params) as $i => $v) {
        $type = match (true) {
            is_int($v)  => PDO::PARAM_INT,
            is_bool($v) => PDO::PARAM_BOOL,
            $v === null => PDO::PARAM_NULL,
            default     => PDO::PARAM_STR,
        };
        $st->bindValue($i + 1, $v, $type);
    }
    $st->execute();
    return $st;
}

function q1(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function qv(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function qall(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** IN (?, ?, ?) 자리 만들기 */
function inPlaceholders(array $vals): string
{
    return $vals ? implode(',', array_fill(0, count($vals), '?')) : 'NULL';
}

// ---- 출력 헬퍼 ------------------------------------------------------------
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function today(): string
{
    return date('Y-m-d');
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function fmtDuration(?int $sec): string
{
    if ($sec === null) return '-';
    if ($sec >= 3600) return sprintf('%d시간 %02d분', intdiv($sec, 3600), intdiv($sec % 3600, 60));
    return sprintf('%d분 %02d초', intdiv($sec, 60), $sec % 60);
}

function pct(int $c, int $t): int
{
    return $t > 0 ? (int)round($c / $t * 100) : 0;
}

function dday(?string $examDate): ?int
{
    if (!$examDate) return null;
    $a = new DateTimeImmutable(today());
    $b = new DateTimeImmutable($examDate);
    return (int)$a->diff($b)->format('%r%a');
}

/** 한 번만 보여줄 알림 메시지 */
function flash(?string $msg = null): ?string
{
    startSession();
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

// ---- 세션 / 로그인 ---------------------------------------------------------
const SESSION_DAYS = 30;

function startSession(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.gc_maxlifetime', (string)(SESSION_DAYS * 86400));
    ini_set('session.use_strict_mode', '1');
    session_name('sqldsid');
    session_set_cookie_params([
        'lifetime' => SESSION_DAYS * 86400,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function currentUser(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    if (PHP_SAPI === 'cli') return $user = null;
    startSession();
    $id = (int)($_SESSION['uid'] ?? 0);
    $user = null;
    if ($id > 0) {
        $user = q1('SELECT id, username, nickname, plan, kakao_id FROM users WHERE id = ? AND kakao_id IS NOT NULL', [$id]);   // 카카오 회원만 (예전 아이디 계정은 폐지)
        if (!$user) unset($_SESSION['uid']);
    }
    return $user;
}

function uid(): int
{
    $u = currentUser();
    return $u ? (int)$u['id'] : 0;
}

function isGuest(): bool
{
    return currentUser() === null;
}

/** 비회원 브라우저를 구분하는 값 (세션에 원본, DB 에는 sha256 만 저장) */
function guestKey(): string
{
    startSession();
    if (empty($_SESSION['gk'])) $_SESSION['gk'] = bin2hex(random_bytes(32));
    return hash('sha256', (string)$_SESSION['gk']);
}

/**
 * 시험 주인 조건. [SQL 조각, 바인딩 값] 을 돌려줌.
 * 회원: t.user_id = ?   비회원: t.user_id IS NULL AND t.guest_key = ?
 */
function ownerWhere(string $alias = 't'): array
{
    $a = $alias === '' ? '' : $alias . '.';
    if ($u = uid()) return ["{$a}user_id = ?", [$u]];
    return ["{$a}user_id IS NULL AND {$a}guest_key = ?", [guestKey()]];
}

function loginAs(int $userId): void
{
    startSession();
    // 비회원일 때 푼 시험은 이 계정 기록으로 옮겨 준다
    if (!empty($_SESSION['gk'])) {
        q('UPDATE tests SET user_id = ?, guest_key = NULL WHERE user_id IS NULL AND guest_key = ?', [$userId, hash('sha256', (string)$_SESSION['gk'])]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    unset($_SESSION['gk']);
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$userId]);
}

function logout(): void
{
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function clientIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function tooManyAttempts(string $username): bool
{
    $r = q1(
        'SELECT SUM(ip = ?) a, SUM(username = ?) b FROM login_attempts WHERE at > NOW() - INTERVAL 10 MINUTE',
        [clientIp(), mb_strtolower(mb_substr($username, 0, 40))]
    );
    return (int)($r['a'] ?? 0) >= 20 || (int)($r['b'] ?? 0) >= 8;
}

function recordFailedAttempt(string $username): void
{
    q('INSERT INTO login_attempts (ip, username) VALUES (?, ?)', [clientIp(), mb_strtolower(mb_substr($username, 0, 40))]);
    q('DELETE FROM login_attempts WHERE at < NOW() - INTERVAL 1 DAY');
}

/** POST 요청이 우리 사이트에서 온 게 맞는지 (다른 사이트에서 몰래 보낸 요청 차단) */
function sameOriginOrDie(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $src = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($src === '') return;
    $host = parse_url($src, PHP_URL_HOST);
    $mine = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!$host || !$mine || strcasecmp($host, $mine) !== 0) {
        http_response_code(403);
        exit('잘못된 요청이야.');
    }
}

function isApi(): bool
{
    return str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
}

/** 로그인 필요 페이지/API */
function requireLogin(): void
{
    if (currentUser()) return;
    if (isApi()) json_out(['ok' => false, 'error' => '로그인이 필요해. 다시 로그인해줘.', 'login' => true], 401);
    $self = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('kakao_login.php?next=' . urlencode($self . ($qs !== '' ? '?' . $qs : '')));   // 바로 카카오 로그인
}

/** 닉네임이 없는 회원은 닉네임부터 */
function requireNickname(): void
{
    $u = currentUser();
    if (!$u || ($u['nickname'] ?? '') !== '' || defined('NICKNAME_PAGE')) return;
    if (isApi()) json_out(['ok' => false, 'error' => '닉네임을 먼저 정해줘.', 'login' => true], 403);
    redirect('nickname.php');
}

/** 로그인 후 돌아갈 곳 — 우리 사이트의 페이지(+쿼리스트링)만 허용 */
function safeNext(?string $next): string
{
    $next = (string)$next;
    if (!preg_match('/^([a-z_]+\.php)(\?[A-Za-z0-9_=&%.\-]*)?$/', $next, $m)) return 'index.php';
    return is_file(__DIR__ . '/../' . $m[1]) ? $next : 'index.php';
}

// ---- 가입 규칙 -------------------------------------------------------------
const USERNAME_RULE = '아이디는 영문과 숫자만, 4~20자로 만들어줘.';
const PASSWORD_RULE = '비밀번호는 영문, 숫자, 특수문자만 써서 8~64자로 만들어줘. (한글, 공백 불가)';
const NICKNAME_RULE = '닉네임은 한글만, 2~10자로 정해줘. (영문, 숫자, 자음/모음만 따로 쓰기, 공백 불가)';

function validUsername(string $s): bool { return (bool)preg_match('/^[A-Za-z0-9]{4,20}$/', $s); }
function validPassword(string $s): bool { return (bool)preg_match('/^[\x21-\x7E]{8,64}$/', $s); }
function validNickname(string $s): bool { return (bool)preg_match('/^[가-힣]{2,10}$/u', $s); }

function nicknameTaken(string $nick, int $exceptUserId = 0): bool
{
    return (bool)qv('SELECT 1 FROM users WHERE nickname = ? AND id <> ?', [$nick, $exceptUserId]);
}

function displayName(?array $u): string
{
    if (!$u) return '';
    return ($u['nickname'] ?? '') !== '' ? (string)$u['nickname'] : '닉네임 설정 중';   // 내부 아이디(kakao…)는 화면에 안 보임
}

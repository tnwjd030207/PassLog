<?php
// 카카오 로그인 (REST API 방식) 도우미
// 흐름: kakao_login.php → 카카오 로그인/동의 화면 → kakao_callback.php(인가 코드) → 토큰 발급 → 사용자 정보 → 로그인/가입
// 키는 config.php 의 KAKAO_REST_KEY / KAKAO_CLIENT_SECRET / KAKAO_REDIRECT_URI 를 쓴다.
declare(strict_types=1);

/** config.php 에 카카오 키가 다 들어 있어야 버튼을 보여 준다 */
function kakaoEnabled(): bool
{
    return defined('KAKAO_REST_KEY') && KAKAO_REST_KEY !== ''
        && defined('KAKAO_REDIRECT_URI') && KAKAO_REDIRECT_URI !== '';
}

// 테스트할 때만 환경변수로 가짜 서버 주소를 넣을 수 있게 함 (평소에는 진짜 카카오 주소)
function kakaoAuthBase(): string { return rtrim(getenv('KAKAO_AUTH_BASE') ?: 'https://kauth.kakao.com', '/'); }
function kakaoApiBase(): string  { return rtrim(getenv('KAKAO_API_BASE') ?: 'https://kapi.kakao.com', '/'); }

/** 카카오 로그인 화면 주소 (state: 위조 요청 방지용 1회용 값) */
function kakaoAuthorizeUrl(string $state): string
{
    return kakaoAuthBase() . '/oauth/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => KAKAO_REST_KEY,
        'redirect_uri'  => KAKAO_REDIRECT_URI,
        'state'         => $state,
    ]);
}

/** 카카오 서버에 HTTP 요청 → [상태코드, JSON 배열] (curl 이 없으면 file_get_contents 로) */
function kakaoHttp(string $method, string $url, array $form = [], array $headers = []): array
{
    $body = $form ? http_build_query($form) : '';
    $headers[] = 'Content-Type: application/x-www-form-urlencoded;charset=utf-8';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body,
            'timeout' => 10, 'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
    }
    if ($raw === false || $raw === null) throw new RuntimeException('카카오 서버에 연결하지 못했어.');
    $json = json_decode((string)$raw, true);
    return [$code, is_array($json) ? $json : []];
}

/** 인가 코드 → 액세스 토큰 */
function kakaoToken(string $code): string
{
    $form = [
        'grant_type'   => 'authorization_code',
        'client_id'    => KAKAO_REST_KEY,
        'redirect_uri' => KAKAO_REDIRECT_URI,
        'code'         => $code,
    ];
    if (defined('KAKAO_CLIENT_SECRET') && KAKAO_CLIENT_SECRET !== '') $form['client_secret'] = KAKAO_CLIENT_SECRET;
    [$status, $res] = kakaoHttp('POST', kakaoAuthBase() . '/oauth/token', $form);
    if ($status !== 200 || empty($res['access_token'])) {
        // 자세한 원인은 서버 로그에만 (화면에는 키 관련 정보를 안 보여 줌)
        error_log('[kakao] token error ' . $status . ' ' . ($res['error'] ?? '') . ' ' . ($res['error_code'] ?? '') . ' ' . ($res['error_description'] ?? ''));
        throw new RuntimeException('카카오 로그인 확인에 실패했어. 잠시 후 다시 해줘.');
    }
    return (string)$res['access_token'];
}

/** 액세스 토큰 → ['id' => 카카오 회원번호, 'nickname' => 카카오 닉네임] */
function kakaoProfile(string $accessToken): array
{
    [$status, $res] = kakaoHttp('GET', kakaoApiBase() . '/v2/user/me', [], ['Authorization: Bearer ' . $accessToken]);
    if ($status !== 200 || empty($res['id'])) {
        error_log('[kakao] profile error ' . $status . ' ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        throw new RuntimeException('카카오 회원 정보를 가져오지 못했어.');
    }
    $nick = $res['kakao_account']['profile']['nickname'] ?? ($res['properties']['nickname'] ?? '');
    return ['id' => (string)$res['id'], 'nickname' => trim((string)$nick)];
}

/**
 * 카카오 회원번호로 로그인. 처음이면 계정을 만든다.
 * 돌려주는 값: ['user_id' => int, 'new' => bool, 'suggest' => 닉네임 칸에 미리 채울 카카오 닉네임(쓸 수 없으면 '')]
 * 새 회원은 닉네임 없이 만들어서, 처음 들어오면 닉네임을 직접 정하게 한다.
 */
function kakaoSignIn(array $profile): array
{
    $kid = $profile['id'];
    if (!preg_match('/^\d{1,20}$/', $kid)) throw new RuntimeException('카카오 회원번호가 올바르지 않아.');

    $u = q1('SELECT id FROM users WHERE kakao_id = ?', [$kid]);
    if ($u) return ['user_id' => (int)$u['id'], 'new' => false, 'suggest' => ''];

    // 새 회원: 아이디는 자동(kakao + 회원번호, 내부 식별용), 비밀번호 칸은 아무도 모르는 임의 값 (로그인은 카카오로만)
    $username = strlen('kakao' . $kid) <= 20 ? 'kakao' . $kid : 'k' . substr($kid, -19);
    $nick = $profile['nickname'];
    $suggest = validNickname($nick) && !nicknameTaken($nick) ? $nick : '';

    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO users (username, nickname, pass_hash, kakao_id) VALUES (?, NULL, ?, ?)', [
            $username, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $kid,
        ]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // 같은 카카오 계정이 동시에 두 번 들어온 경우 → 이미 만들어진 계정으로 로그인
        $u = q1('SELECT id FROM users WHERE kakao_id = ?', [$kid]);
        if ($u) return ['user_id' => (int)$u['id'], 'new' => false, 'suggest' => ''];
        throw $e;
    }
    return ['user_id' => $newId, 'new' => true, 'suggest' => $suggest];
}

/** 로그인/회원가입 화면에 넣는 카카오 버튼 (카카오 디자인 가이드: 노란 배경 #FEE500, 말풍선 심볼) */
function kakaoButton(string $next = 'index.php', string $label = '카카오 로그인'): string
{
    if (!kakaoEnabled()) return '';
    $href = 'kakao_login.php' . ($next !== 'index.php' ? '?next=' . urlencode($next) : '');
    return '<a class="btn btn-kakao" href="' . h($href) . '">'
        . '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#000" d="M12 3C6.48 3 2 6.58 2 11c0 2.85 1.86 5.35 4.66 6.76l-.95 3.48c-.08.3.26.54.52.37l4.16-2.76c.53.06 1.07.1 1.61.1 5.52 0 10-3.58 10-8S17.52 3 12 3z"/></svg>'
        . '<span>' . h($label) . '</span></a>';
}

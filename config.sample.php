<?php
// 이 파일을 config.php 로 복사해서 비밀번호를 채워 쓰면 돼. (config.php 는 .gitignore 로 깃에 안 올라감)
define('DB_HOST', getenv('SQLD_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('SQLD_DB_NAME') ?: 'sqld');
define('DB_USER', getenv('SQLD_DB_USER') ?: 'sj');
define('DB_PASS', getenv('SQLD_DB_PASS') !== false ? getenv('SQLD_DB_PASS') : '여기에_비밀번호');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

-- 예전 아이디/비밀번호 계정과 그 기록을 모두 지우는 1회용 정리 스크립트 (2026-10-17, 카카오 로그인으로 일원화)
-- ⚠️ 되돌릴 수 없어. 실행 전에 꼭 백업:  mysqldump -u sj -p sqld > ~/sqld_backup_$(date +%F).sql
-- 실행:  mysql -u sj -p sqld < /var/www/html/sqld/sql/cleanup_legacy_accounts.sql
-- 카카오로 가입한 계정(users.kakao_id 있음)과 문제·강의 데이터는 그대로 남아.

START TRANSACTION;

-- 지울 계정 목록 (카카오 연결이 없는 회원)
CREATE TEMPORARY TABLE legacy_users AS SELECT id FROM users WHERE kakao_id IS NULL;

DELETE FROM lesson_progress WHERE user_id IN (SELECT id FROM legacy_users);
DELETE FROM user_certs      WHERE user_id IN (SELECT id FROM legacy_users);
-- 시험(test_items 는 FK CASCADE 로 함께 삭제) + 주인 없는 회원제 이전 기록
DELETE FROM tests WHERE user_id IN (SELECT id FROM legacy_users) OR (user_id IS NULL AND guest_key IS NULL);
DELETE FROM settings WHERE k LIKE 'u%:%';
DELETE FROM login_attempts;
DELETE FROM users WHERE id IN (SELECT id FROM legacy_users);

DROP TEMPORARY TABLE legacy_users;
COMMIT;

SELECT COUNT(*) AS remaining_users FROM users;
SELECT COUNT(*) AS remaining_tests FROM tests;

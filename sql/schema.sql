-- PassLog(패스로그) DB 스키마 — IT 자격증 학습 서비스
-- 사용법 (root 계정으로 처음 한 번만 DB/권한 생성):
--   mysql --default-character-set=utf8mb4 -u root -p < sql/schema.sql
-- 그 다음 테이블 생성/변경/문제 등록은 install.php 가 알아서 해 (몇 번 실행해도 안전).
-- 자세한 설명은 docs/DB.md 참고.

CREATE DATABASE IF NOT EXISTS sqld
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON sqld.* TO 'sj'@'localhost';
FLUSH PRIVILEGES;

USE sqld;

-- 회원
CREATE TABLE IF NOT EXISTS users (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  username          VARCHAR(20)  NOT NULL,                 -- 로그인 아이디 (영문/숫자 4~20자)
  nickname          VARCHAR(20)  NULL,                     -- 화면 표시 이름 (한글 2~10자)
  email             VARCHAR(190) NULL,                     -- [추후] 이메일 로그인/인증용 (지금은 비어 있음)
  email_verified_at DATETIME     NULL,                     -- [추후] 이메일 인증 완료 시각
  pass_hash         VARCHAR(255) NOT NULL,                 -- password_hash() 결과 (카카오 가입자는 아무도 모르는 임의 값)
  kakao_id          VARCHAR(20)  NULL,                     -- 카카오 회원번호 (카카오 로그인 연결)
  plan              ENUM('free','premium') NOT NULL DEFAULT 'free',   -- [추후] 결제 연동용 요금제
  plan_expires_at   DATETIME     NULL,                     -- [추후] 유료 이용 만료 시각
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login        DATETIME     NULL,
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_nickname (nickname),
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_kakao (kakao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 관리자 계정 (일반 회원과 별개, admin/ 페이지 전용)
CREATE TABLE IF NOT EXISTS admins (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(30)  NOT NULL,
  pass_hash   VARCHAR(255) NOT NULL,
  pw_changed  TINYINT      NOT NULL DEFAULT 0,             -- 0 이면 초기 비밀번호 사용 중 → 변경 안내
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login  DATETIME     NULL,
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 로그인 실패 기록 (무차별 대입 방지)
CREATE TABLE IF NOT EXISTS login_attempts (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  ip        VARCHAR(45)  NOT NULL,
  username  VARCHAR(40)  NOT NULL,
  at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_at (ip, at),
  KEY idx_user_at (username, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 자격증
CREATE TABLE IF NOT EXISTS certs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(20)  NOT NULL,                     -- sqld, eip, adsp, linux2, net2
  name          VARCHAR(60)  NOT NULL,                     -- 화면 이름 (예: 정보처리기사 필기)
  short_name    VARCHAR(20)  NOT NULL,                     -- 짧은 이름 (예: 정처기)
  org           VARCHAR(60)  NOT NULL DEFAULT '',          -- 시행 기관
  description   VARCHAR(255) NOT NULL DEFAULT '',
  exam_note     VARCHAR(255) NOT NULL DEFAULT '',          -- 시험 구성 안내 문구
  minutes       SMALLINT     NOT NULL,                     -- 실제 시험 시간(분)
  pass_total    TINYINT      NOT NULL DEFAULT 60,          -- 합격 총점(100점 환산)
  pass_subject  TINYINT      NULL,                         -- 과목별 과락 기준 % (NULL 이면 과락 없음)
  color         VARCHAR(9)   NOT NULL DEFAULT '#5eead4',
  sort          TINYINT      NOT NULL DEFAULT 0,
  active        TINYINT      NOT NULL DEFAULT 1,
  admin_edited TINYINT NOT NULL DEFAULT 0,            -- 1 이면 관리자 수정본 → install.php 재실행 때 덮어쓰지 않음
  UNIQUE KEY uq_cert_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 자격증의 과목 (실전 문항수 포함)
CREATE TABLE IF NOT EXISTS cert_subjects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cert_id     INT          NOT NULL,
  no          TINYINT      NOT NULL,                       -- 과목 번호 1~
  name        VARCHAR(60)  NOT NULL,
  exam_count  SMALLINT     NOT NULL,                       -- 실전 모의고사 문항수
  UNIQUE KEY uq_cert_subject (cert_id, no),
  CONSTRAINT fk_subj_cert FOREIGN KEY (cert_id) REFERENCES certs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 과목 아래 단원(하부 카테고리) + 약할 때 공부 팁
CREATE TABLE IF NOT EXISTS topics (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cert_id     INT          NOT NULL,
  subject     TINYINT      NOT NULL,
  name        VARCHAR(40)  NOT NULL,
  tip         VARCHAR(500) NOT NULL DEFAULT '',
  sort        SMALLINT     NOT NULL DEFAULT 0,
  admin_edited TINYINT NOT NULL DEFAULT 0,            -- 1 이면 관리자 수정본 → install.php 재실행 때 덮어쓰지 않음
  UNIQUE KEY uq_topic (cert_id, name),
  CONSTRAINT fk_topic_cert FOREIGN KEY (cert_id) REFERENCES certs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 문제은행 (data/*.json 을 install.php 가 넣어줌)
CREATE TABLE IF NOT EXISTS questions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cert_id     INT          NOT NULL,
  code        VARCHAR(20)  NOT NULL,                       -- S1-001, EIP-1-001 처럼 고정된 문제 코드
  subject     TINYINT      NOT NULL,                       -- 자격증 안에서의 과목 번호
  topic       VARCHAR(40)  NOT NULL,                       -- 단원
  difficulty  TINYINT      NOT NULL DEFAULT 2,             -- 1 하 / 2 중 / 3 상
  question    TEXT         NOT NULL,
  code_block  TEXT         NULL,
  choices     TEXT         NOT NULL,                       -- 보기 4개 (JSON 배열)
  answer      TINYINT      NOT NULL,                       -- 정답 번호 1~4
  hint        TEXT         NOT NULL,
  explanation TEXT         NOT NULL,
  mono        TINYINT      NOT NULL DEFAULT 0,
  active      TINYINT      NOT NULL DEFAULT 1,
  admin_edited TINYINT NOT NULL DEFAULT 0,            -- 1 이면 관리자 수정본 → install.php 재실행 때 덮어쓰지 않음
  UNIQUE KEY uq_code (code),
  KEY idx_cert_subject (cert_id, subject, difficulty),
  KEY idx_cert_topic (cert_id, topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 시험 (회원 또는 비회원 1회 응시)
CREATE TABLE IF NOT EXISTS tests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT          NULL,                       -- 회원 (비회원이면 NULL)
  guest_key       CHAR(64)     NULL,                       -- 비회원 세션 식별값(sha256)
  cert_id         INT          NOT NULL,
  test_date       DATE         NOT NULL,
  kind            ENUM('practice','exam') NOT NULL DEFAULT 'practice',   -- 연습 / 실전 모의고사
  difficulty      TINYINT      NULL,                       -- 고른 난이도 (NULL=혼합, 1하 2중 3상)
  subject_filter  TINYINT      NULL,                       -- 연습에서 고른 과목 (NULL=전체)
  topic_filter    VARCHAR(40)  NULL,                       -- 연습에서 고른 단원
  status          ENUM('in_progress','done') NOT NULL DEFAULT 'in_progress',
  started_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at     DATETIME     NULL,
  duration_sec    INT          NULL,                       -- 실제 푼 시간 (일시정지 시간 제외)
  q_seconds       SMALLINT     NULL,                       -- 문제별 제한 시간(초). NULL 이면 시간 제한 없음
  cur_pos         SMALLINT     NOT NULL DEFAULT 1,         -- 시간 제한 시험: 지금 풀고 있는 문제 번호
  cur_started_ts  INT          NULL,                       -- 시간 제한 시험: 현재 문제 시작 시각(unix)
  paused_ts       INT          NULL,                       -- 일시정지 시작 시각(unix). NULL 이면 진행 중
  pause_count     SMALLINT     NOT NULL DEFAULT 0,
  pause_total_sec INT          NOT NULL DEFAULT 0,
  total_cnt       SMALLINT     NOT NULL DEFAULT 0,
  correct_cnt     SMALLINT     NULL,
  score           TINYINT      NULL,                       -- 100점 환산
  subject_json    TEXT         NULL,                       -- 과목별 결과 {"1":{"t":10,"c":7},...}
  s1_total        TINYINT      NOT NULL DEFAULT 0,         -- (예전 SQLD 기록 호환용)
  s1_correct      TINYINT      NULL,
  s2_total        TINYINT      NOT NULL DEFAULT 0,
  s2_correct      TINYINT      NULL,
  hints_used      SMALLINT     NULL,
  timeouts        SMALLINT     NULL,                       -- 시간 초과로 넘어간 문제 수
  passed          TINYINT      NULL,
  KEY idx_date (test_date),
  KEY idx_user (user_id, status),
  KEY idx_user_cert (user_id, cert_id, status),
  KEY idx_guest (guest_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 시험에 나온 문제와 내 답
CREATE TABLE IF NOT EXISTS test_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  test_id      INT        NOT NULL,
  question_id  INT        NOT NULL,
  position     SMALLINT   NOT NULL,
  user_answer  TINYINT    NULL,
  is_correct   TINYINT    NULL,
  hint_used    TINYINT    NOT NULL DEFAULT 0,
  hint_used_at DATETIME   NULL,
  answered_at  DATETIME   NULL,
  spent_sec    SMALLINT   NULL,                            -- 이 문제에 쓴 시간(초, 시간 제한 시험)
  timed_out    TINYINT    NOT NULL DEFAULT 0,              -- 시간 초과로 넘어갔는지
  UNIQUE KEY uq_test_pos (test_id, position),
  KEY idx_question (question_id),
  CONSTRAINT fk_items_test     FOREIGN KEY (test_id)     REFERENCES tests(id)     ON DELETE CASCADE,
  CONSTRAINT fk_items_question FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 학습 콘텐츠(강의 노트)
CREATE TABLE IF NOT EXISTS lessons (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  cert_id   INT          NOT NULL,
  code      VARCHAR(30)  NOT NULL,
  subject   TINYINT      NOT NULL,
  topic     VARCHAR(40)  NOT NULL,
  sort      SMALLINT     NOT NULL DEFAULT 0,
  title     VARCHAR(150) NOT NULL,
  minutes   SMALLINT     NOT NULL DEFAULT 10,
  body      MEDIUMTEXT   NOT NULL,                          -- 마크다운
  active    TINYINT      NOT NULL DEFAULT 1,
  admin_edited TINYINT NOT NULL DEFAULT 0,            -- 1 이면 관리자 수정본 → install.php 재실행 때 덮어쓰지 않음
  UNIQUE KEY uq_lesson_code (code),
  KEY idx_lesson_cert (cert_id, subject, sort),
  CONSTRAINT fk_lesson_cert FOREIGN KEY (cert_id) REFERENCES certs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 회원별 강의 진도
CREATE TABLE IF NOT EXISTS lesson_progress (
  user_id      INT       NOT NULL,
  lesson_id    INT       NOT NULL,
  status       ENUM('started','done') NOT NULL DEFAULT 'started',
  read_pct     TINYINT   NOT NULL DEFAULT 0,               -- 가장 많이 읽은 위치(%)
  last_pct     TINYINT   NOT NULL DEFAULT 0,               -- 마지막으로 보던 위치(%) → 이어 읽기
  started_at   DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME  NULL,
  PRIMARY KEY (user_id, lesson_id),
  KEY idx_lp_user (user_id, updated_at),
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 회원 x 자격증 상태 (시험일 D-day, 마지막으로 본 강의)
CREATE TABLE IF NOT EXISTS user_certs (
  user_id         INT      NOT NULL,
  cert_id         INT      NOT NULL,
  exam_date       DATE     NULL,
  last_lesson_id  INT      NULL,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, cert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 기타 설정 (key-value)
CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

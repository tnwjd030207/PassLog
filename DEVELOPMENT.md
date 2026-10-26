> 📌 **2026-10-10 부터 개발 문서는 `docs/` 폴더의 세 파일로 관리해.**
> - `docs/SPEC.md` — 개발 스펙 (유저 플로우, 화면/API, 규칙, 보안, 추후 계획)
> - `docs/DB.md` — DB 설계 (ERD, 테이블 명세, 마이그레이션 이력)
> - `docs/DEVLOG.md` — 날짜별 개발 일지 요약
> 이 파일은 v1~v2(SQLD 전용 시절) 기록 보관용이야.

# SQLD 합격 훈련 — 개발 기록

> 이 문서는 **업데이트할 때마다 맨 아래 "변경 이력"에 요약을 추가**한다. (규칙: 개발 관련 사항은 전부 여기에 정리)

## 1. 한 줄 소개
SQLD(SQL 개발자) 시험 합격용 연습 사이트. 매일 10문제를(원하면 몇 번이든) 풀고, 힌트는 버튼 눌렀을 때만 보이고, 결과가 DB에 저장돼 약점 단원을 분석해준다.
기술 스택: HTML / CSS / 바닐라 JS / PHP 8 (PDO) / MySQL(MariaDB).

## 2. 폴더 구조
```
sqld/
├─ index.php          대시보드 (오늘 상태, D-day, 연속 학습일, 최근 점수, 과목·단원별 정답률)
├─ test.php           진행 중인 10문제 (한 문제씩, 답 자동 저장, 힌트 버튼, 제출). 새 시험은 POST 로만 생성
├─ result.php         채점 결과 + 문제별 해설 (id 로 과거 결과도 조회)
├─ analysis.php       결과 분석 (자주 틀리는 유형 순위 + 단원별 공부 코멘트, 항상 메뉴에서 접근)
├─ history.php        시험 기록 표 + 점수 추이 그래프(SVG)
├─ wrong.php          오답노트 (최근에 틀린 문제 / 한 번이라도 틀린 문제, 과목·단원 필터)
├─ install.php        테이블 생성 + 문제은행 등록 (여러 번 실행해도 안전)
├─ config.php         DB 접속 설정 (기존 connect.php 와 같은 계정, DB 이름만 sqld)
├─ api/
│  ├─ answer.php      답 선택 저장 (POST item_id, answer)
│  ├─ hint.php        힌트 요청 (POST item_id) → 힌트 내려주고 사용 기록
│  └─ finish.php      제출·채점 (POST test_id)
├─ includes/
│  ├─ functions.php   출제 알고리즘, 채점, 통계, 헬퍼 (핵심 로직 전부)
│  ├─ header.php / footer.php
├─ assets/
│  ├─ style.css       전체 스타일 (다크모드, 모바일 대응)
│  └─ test.js         시험 화면 동작
├─ data/questions.json  문제은행 (문제 추가/수정은 여기서)
├─ sql/schema.sql     DB 스키마 (root 로 1회 실행)
└─ DEVELOPMENT.md     이 문서
```

## 3. 처음 실행하는 방법
1. **DB 만들기 (root 로 1회)**: `mysql --default-character-set=utf8mb4 -u root -p < sql/schema.sql`
   - `sqld` DB 생성 + `sj@localhost` 계정에 권한 부여까지 해준다. (계정이 다르면 schema.sql 의 GRANT 줄과 config.php 수정)
2. **설치**: 브라우저에서 `/sqld/install.php` 열기 (또는 터미널에서 `php install.php`) → 테이블 생성 + 문제 110개 등록.
3. **사용**: `/sqld/index.php`
4. 로컬 테스트만 할 때는 `php -S 127.0.0.1:8080 -t .` 로 띄우면 된다 (PHP 의 mysql 확장 `pdo_mysql` 필요).

## 4. 동작 규칙
- **하루에 여러 번 가능**: 대시보드(또는 결과 화면)의 `맞춤 10문제 시작` / `랜덤 10문제 시작` 버튼(POST)을 누를 때만 새 시험이 만들어진다. 진행 중인 시험이 있으면 새로 못 만들고 그 시험을 이어 푼다(중복 생성 방지). 오늘 이미 나온 문제는 되도록 다시 안 나온다.
- **구성**: 과목1 2문제 + 과목2 8문제 (실제 시험 비율 10:40 과 동일). 문제 번호도 과목1 → 과목2 순서.
- **출제 방식 2가지** (`pickQuestions`): `random` = 문제은행에서 완전 무작위. `smart`(맞춤) = ① 지난번에 틀린 문제는 복습용으로 최대 30% ② 한 번도 안 나온 문제 — 정답률 낮은 단원일수록 더 자주 뽑힘(가중 랜덤) ③ 부족하면 맞힌 지 오래된 문제 순.
- **힌트**: 페이지 HTML 에는 힌트 텍스트가 **들어있지 않다**. `힌트 보기` 버튼을 누르면 `api/hint.php` 가 내려주고 `test_items.hint_used = 1` 로 기록. 새로고침해도 이미 본 힌트만 다시 보인다. 정답·해설도 제출 전에는 서버가 화면에 내려주지 않는다.
- **채점·합격 기준**(`judge`): 총점 60점 이상 **그리고** 과목별 40% 이상. 10문제 = 100점(1문제 10점). 이 시험에서는 과목1 1문제 이상, 과목2 4문제 이상 맞혀야 과락이 아니다. 안 푼 문제는 오답 처리.
- **저장 항목**: 시험별 점수·과목별 정답·힌트 사용 수·걸린 시간·합격 기준 충족 여부, 문제별 내 답·정오·힌트 사용 여부.

## 5. DB 테이블
| 테이블 | 용도 |
|---|---|
| `questions` | 문제은행. `code`(S1-001 …)가 UNIQUE 키라서 install 을 다시 돌리면 같은 코드는 덮어쓴다 |
| `tests` | 시험 1회분 (날짜는 중복 가능, 상태 in_progress/done, 점수, 과목별 정답 수 등) |
| `test_items` | 시험에 출제된 문제와 내 답, 정오, 힌트 사용 여부 |
| `settings` | 설정값 (현재는 시험일 `exam_date`) |

## 6. 문제은행
- 총 110문제: 과목1 21 / 과목2 89 (과목2 비중을 높임 — 지난 시험에서 과락 났던 과목).
- 과목1: 모델링, 엔터티, 속성, 관계, 식별자, 정규화, 반정규화, 성능 데이터 모델링.
- 과목2: SELECT·WHERE, NULL·함수, CASE·DECODE, GROUP BY·집계, ORDER BY, JOIN, 서브쿼리, 집합 연산자, ROLLUP·CUBE, 윈도우 함수, TOP-N·계층형, DML·TCL, DDL·제약조건.
- 모든 문제는 **직접 새로 쓴 문제**(기출 복사 아님)이고, 힌트(정답을 직접 말하지 않음)와 쉬운 말 해설이 붙어 있다.
- 결과를 계산하는 문제(집계, 조인 건수, 집합 연산, 윈도우 함수, NTILE 등)는 MariaDB 에서 실제로 돌려 정답을 검증했다. Oracle 전용 동작(ADD_MONTHS 말일 처리, DECODE 의 NULL 비교, ROWNUM, FETCH FIRST WITH TIES, CONNECT BY, `||` 의 NULL 처리 등)은 Oracle 문서 기준 지식으로 작성했다.
- 정답 번호는 ①~④ 가 고르게 분포하도록 보기 순서를 섞어서 저장했다 (숫자 오름차순 보기 등 순서가 의미 있는 문제는 그대로 둠).
- **문제 추가 방법**: `data/questions.json` 끝에 항목 추가 (`code` 는 안 겹치게) → `install.php` 다시 실행. 항목 형식: `code, subject(1|2), topic, difficulty(1~3), question, code_block, choices(4개), answer(1~4), hint, explanation, mono(보기를 코드 글꼴로: 0|1)`.

## 7. 확인한 것 (테스트 기록)
- MariaDB 10.11 + PHP 8.3 에서 전체 흐름 확인: 시험 생성 → 답 저장(잘못된 값·다른 문항 거부) → 힌트 요청(2번 눌러도 1회로 기록) → 제출(2번 눌러도 1회 채점) → 결과 → 기록 → 오답노트.
- 제출된 시험에는 답을 더 저장할 수 없음, 제출 전에는 test.php HTML 에 힌트·정답·해설이 없음.
- 브라우저(모바일 폭 포함)에서 선택·힌트·키보드 단축키·제출 팝업·결과 이동까지 클릭 테스트, JS 오류 없음.

## 8. 알아둘 점 / 다음에 할 수 있는 것
- 이 사이트에는 **로그인이 없다** (혼자 쓰는 용도). EC2 에 올려서 외부에 열려 있다면 `.htaccess` 기본 인증이나 접근 IP 제한을 걸어두는 걸 추천.
- `install.php` 는 설치 후에도 남아 있어도 해가 없지만(문제은행 덮어쓰기만 함), 신경 쓰이면 서버에서 지워도 된다.
- 하루 한 번만 푸는 구조라 "한 번 더 풀기"는 아직 없음. 필요하면 `tests` 의 날짜 UNIQUE 를 풀고 "추가 연습" 모드를 만들 수 있다.
- 문제를 더 늘리고 싶으면 과목2(JOIN·서브쿼리·윈도우 함수·계층형)부터 추가하는 걸 추천.

## 변경 이력
### 2026-10-03 — 최초 제작 (v1.0)
- 매일 10문제(과목1 2 + 과목2 8), 힌트 버튼(누를 때만 표시·기록), 자동 저장, 제출·채점, 결과 저장 구현.
- 대시보드(D-day, 연속 학습일, 최근 점수, 과목·단원별 정답률), 시험 기록(추이 그래프), 오답노트 구현.
- 문제은행 110문제 작성·검증, 출제 알고리즘(오답 복습 + 미출제 + 약점 단원 가중) 적용.
- 합격 기준: 총점 60점 이상 + 과목별 40% 이상.

### 2026-10-03 — 깃 연결
- `rsj` 폴더 전체를 GitHub 비공개 저장소 `tnwjd030207/my` 의 `main` 브랜치에 연결하고 첫 커밋을 올림. (원격: `origin`)
- 비밀번호가 들어있는 파일은 `.gitignore` 로 제외: 모든 `connect.php`, `dogstagram/config/database.php`, `portfolio/config.php`, `sqld/config.php`, `.idea/`, `.claude/`, 업로드 파일.
- `sqld/config.sample.php` 추가: 새 PC/서버에서는 이 파일을 `config.php` 로 복사하고 비밀번호만 채우면 됨.
- 접근 토큰은 저장소 설정(`.git/config`)에 저장하지 않았음. 앞으로 push 할 때는 PhpStorm(Git → Push) 에서 GitHub 로그인하거나 새 토큰을 입력해야 함.

### 2026-10-03 — 비공개 저장소라서 전부 업로드
- 비밀번호 파일(`connect.php`들, `config.php`들), `.idea/`, `.claude/` 까지 `.gitignore` 없이 전부 올림. 저장소가 **비공개(private)** 인 것을 전제로 한 선택이니, 저장소를 공개로 바꾸기 전에는 비밀번호를 바꾸고 민감 파일을 빼야 함.

### 2026-10-03 — 서버 자동 배포(GitHub Actions) 추가
- `main` 에 push 하면 `.github/workflows/deploy.yml` 이 EC2 `/var/www/html` 로 파일을 rsync(SSH) 로 동기화. 서버에 깃 토큰을 둘 필요 없음. 파일 삭제는 하지 않음(`--delete` 없음).
- `.deploy-exclude` 로 제외: `.git`, `.idea`, `.claude`, `*.md`, DB 접속 설정 파일들(서버 값 유지), 업로드 폴더.
- 필요한 설정(1회): GitHub 저장소 Settings → Secrets and variables → Actions 에 `EC2_HOST`, `EC2_USER`(ec2-user), `EC2_SSH_KEY`(pem 파일 내용 전체) 등록. 서버에 rsync 설치(`sudo dnf install -y rsync`), 보안그룹에서 22번 포트가 GitHub Actions 에서 접속 가능해야 함.
- sqld 첫 배포 후 서버에서 `sqld/config.php` 가 없으면 `config.sample.php` 를 복사해 비밀번호를 채움.

### 2026-10-03 — 서버 자동 배포 방식 변경 (Actions 제거 → 서버가 직접 당겨오기)
- GitHub Actions 배포(`.github/workflows/deploy.yml`, `.deploy-exclude`)는 서버 22번 포트를 열어야 해서 **삭제**함.
- 대신 EC2 서버(`/var/www/html`)의 `~/deploy.sh` 를 cron(`crond`)이 **1분마다** 실행: `git fetch` → 새 커밋이 있으면 바뀐 파일만 서버에 반영 → `~/deploy.log` 에 기록.
- 반영에서 제외: `.idea/`, `.github/`, `.claude/`, `*.md`, `connect.php`/`config.php`/`database.php` (서버의 접속 설정 유지). 파일 삭제는 하지 않음.
- 서버의 GitHub 인증: 읽기 전용(Contents: Read-only) 세분화 토큰을 `~/.git-credentials` 에 저장(credential.helper store). 토큰이 만료되면 `git fetch origin main` 을 직접 실행해 새 토큰으로 다시 입력.
- 서버 DB: root 로 `sql/schema.sql` 실행 후 `php install.php` 로 문제 110개 등록 완료. 서버에서 업데이트 확인: `tail ~/deploy.log`.
- 알아둘 점: 로그인이 없는 사이트라 서버 주소를 아는 사람은 누구나 접속 가능 (비밀번호 보호 권장).

### 2026-10-03 — 걸린 시간 버그 수정
- 증상: 결과 화면의 '걸린 시간'이 수백 분으로 나옴. 원인: 시험 행이 대시보드 접속 때 만들어지는데 시간 기준이 그 생성 시각이었음.
- 수정: `test.php` 를 열 때, 아직 답/힌트 기록이 없으면 `started_at` 을 그 시점으로 갱신. (이미 답을 한 뒤 이어 푸는 경우는 첫 시작 시각 유지)
- 이미 저장된 잘못된 시간은 자동으로 안 고쳐짐 → 필요하면 서버 DB 에서 `UPDATE tests SET duration_sec = NULL WHERE id = 1;`
- `install.php` 에 정리 추가: `duration_sec` 이 4시간(14400초) 초과인 값은 이전 버그로 생긴 값이라 NULL 로 비움 (여러 번 실행해도 안전).

### 2026-10-03 — 하루에 여러 번, 랜덤으로 풀기
- `tests.test_date` 의 UNIQUE 제약을 풀고 일반 인덱스(`idx_date`)로 변경. 기존 DB 는 `install.php` 를 한 번 실행하면 자동으로 변경됨(ALTER TABLE). **서버에 새 코드가 반영된 뒤 `/sqld/install.php` 를 한 번 열어야 함.**
- `getOrCreateTodayTest()` 제거 → `currentTest()`(오늘 진행 중인 시험), `createTest($mode)`(새 시험 생성), `todayDoneTests()` 추가.
- 출제 모드: `맞춤`(smart, 기존 알고리즘) / `랜덤`(random). 오늘 이미 나온 문제는 제외하고 뽑고, 문제가 모자라면 다시 섞음.
- 화면: 대시보드에 두 개의 시작 버튼, 결과 화면에 '한 번 더' 버튼, 시험 기록에 같은 날 N회차 표시.
- 새 시험은 GET 접근으로는 만들어지지 않음(새로고침/주소 입력으로 시험이 쌓이는 것 방지).

### 2026-10-03 — 실전 모의고사 + 전체 리디자인 + 결과 분석
- **실전 모의고사**: 과목1 10 + 과목2 40 = 50문제(문제당 2점), 제한 90분 카운트다운(서버 기준 남은 시간, 끝나면 자동 제출), 힌트 없음, 랜덤 출제. `tests.kind`('daily'|'mock') 컬럼 추가 → **서버에 반영 후 `/sqld/install.php` 를 한 번 열어야 함**(ALTER 자동 실행).
- 기록 분리: 대시보드에 모의고사 전용 카드(응시·최고·평균·추이), 시험 기록 페이지에 전체/데일리/모의고사 탭과 종류 배지. 데일리 통계(`summaryStats('daily')`, `recentTests`)와 모의고사 통계는 따로 계산.
- 진행 중 시험은 어제 이후 것까지 인정(`activeSince()`), 시간이 지난 모의고사는 `currentTest()` 에서 자동 제출 처리. 시험 그만두기(`abandonTest`, 기록 삭제) 추가.
- **디자인 v2**: 다크 글래스 UI. Three.js(v0.186, `assets/vendor/three/` 에 로컬 포함, importmap)로 `scene.js` — 전 페이지 파티클·와이어프레임 배경(마우스/스크롤 시차)과 대시보드 히어로 3D 막대(물결 + 마우스 파동). Canvas 2D API로 `charts.js` — 점수 추이(호버 툴팁, 클릭 시 해설), 원뿔 그라디언트 게이지, 단원 레이더, 모의고사 타이머 링, 합격 컨페티, 숫자 카운트업. `prefers-reduced-motion` 이면 정지 화면, WebGL 없으면 배경만 생략.
- **결과 분석**(`analysis.php`, `includes/analysis.php`): 단원별 정답률·오답 수·힌트 의존도·최근 추세를 계산해 '위험/보강 필요/무난/탄탄' 등급과 단원별 공부 코멘트(`STUDY_TIPS`)를 생성. 과목 과락 위험 경고, 우선순위 TOP3, 반복 오답 문제 목록. 결과 화면에도 '이번 시험에서 많이 틀린 유형 + 코멘트' 섹션 추가. 상단 메뉴 '결과 분석'.
- 변경 파일: functions.php, install.php, schema.sql, index/test/result/history/wrong.php, api/*.php, test.js, style.css, header/footer, 신규 analysis.php·analysis 헬퍼·charts.js·scene.js.

## 2026-10-04 — 문제 은행 확장 (110 → 239문제)
- 개념정리 PDF(2024 개정판)의 강조 개념과 SQLD 기출 경향(NULL 함정, 윈도우 함수 기본 프레임, 계층형 질의, ROLLUP/CUBE, 서브쿼리 NOT IN+NULL 등)을 바탕으로 **직접 새로 작성한 문제 129개** 추가 (과목1 28개, 과목2 101개). 실제 기출 문제를 그대로 옮긴 것이 아님.
- 코드 `S1-101~`, `S2-101~` 부여. 정답 위치는 1~4번에 고르게 분산.
- 계산 가능한 SQL 문제(31개)는 MariaDB로 실행해 정답 검증. Oracle 전용 문법(MINUS, NVL2, DECODE, ROWNUM, CONNECT BY, RATIO_TO_REPORT 등)은 개념 기준으로 작성.
- 변경 파일: `data/questions.json` (배포 후 install.php 실행으로 DB 반영)

## 2026-10-10 — 회원제 (로그인/회원가입) 추가
- **회원별 분리**: 시험 기록(`tests.user_id`), 시험 문항/답/힌트 사용, 오답노트, 약점 분석, 통계, 연속 학습일, D-day(시험일) 모두 계정마다 따로 저장. 문제은행(`questions`)만 공용.
- **새 파일**: `login.php`, `register.php`, `logout.php` (로그아웃은 POST 전용). **새 테이블**: `users`(아이디, 비밀번호 해시), `login_attempts`(로그인 실패 기록).
- **DB 변경**: `tests.user_id` 컬럼 추가 → `install.php` 실행 시 자동 마이그레이션. `settings` 키는 `u{회원번호}:exam_date` 형태로 회원별 저장.
- **기존 기록 이전**: 회원제 이전에 쌓인 시험 기록·시험일 설정은 **가장 먼저 가입한 계정**이 자동으로 가져감 (배포 직후 본인이 바로 가입할 것).
- **보안**: 비밀번호는 `password_hash`(bcrypt)로만 저장, 세션 쿠키 HttpOnly + SameSite=Lax, 로그인 성공 시 세션 ID 재발급, 로그인 실패 제한(10분간 같은 아이디 8회 / 같은 IP 20회), POST 요청의 Origin 검사(다른 사이트에서 몰래 보낸 요청 차단), 모든 조회·수정 쿼리에 `user_id` 조건 (남의 시험 열기/제출/답 저장/힌트 시도는 거부).
- **로그인 자동 요구**: `includes/functions.php` 를 불러오는 모든 페이지/API 는 로그인이 필요 (`login/register/logout/install` 만 `PUBLIC_PAGE` 예외). API 는 로그인 풀리면 401 JSON, 시험 화면 JS 는 로그인 화면으로 이동.
- **한계**: 비밀번호 찾기/재설정은 없음(이메일 수집 안 함). 현재 사이트가 http 라서 공용 와이파이에서는 비밀번호가 평문으로 오갈 수 있음 → https 적용 권장.
- 변경 파일: `includes/functions.php`, `includes/header.php`, `install.php`, `sql/schema.sql`, `index.php`, `history.php`, `result.php`, `test.php`, `wrong.php`, `analysis.php`, `includes/analysis.php`, `api/*.php`, `assets/test.js`, `assets/style.css`, 신규 3개

## 2026-10-10 — 가입 규칙 변경 + 닉네임
- **아이디**: 영문·숫자만 4~20자 (`validUsername`). 밑줄·한글 불가.
- **비밀번호**: 영문·숫자·키보드 특수문자(`!`~`~`, ASCII 0x21~0x7E)만 8~64자 (`validPassword`). 한글·공백 불가.
- **닉네임(신규)**: 완성형 한글(가~힣)만 2~10자, 중복 불가 (`users.nickname`, UNIQUE). 상단 오른쪽에 아이디 대신 닉네임 표시, 클릭하면 `nickname.php` 에서 변경.
- **예전 가입자**: 닉네임이 없으면 로그인 후 `nickname.php` 로 먼저 이동해서 정해야 다른 페이지 이용 가능 (API 는 403). 아이디/비밀번호 규칙은 새로 가입할 때만 적용되고, 예전 계정은 그대로 로그인 가능.
- 규칙 문구는 `USERNAME_RULE / PASSWORD_RULE / NICKNAME_RULE` 상수로 한곳에서 관리. 브라우저 입력칸에도 같은 `pattern` 적용(서버에서도 다시 검사).
- `install.php` 실행 시 `users.nickname` 컬럼 자동 추가.

## 2026-10-10 — v3.0 PassLog (IT 자격증 플랫폼) — 자세한 내용은 docs/DEVLOG.md
- 자격증 5종, 난이도 선택, 비회원 20문제, 실전 프로세스(문제별 타이머·일시정지 기록), 강의·진도율·이어서 공부, 브랜딩(PassLog), PDO 전면 prepared statement, 문서 3종 체계.

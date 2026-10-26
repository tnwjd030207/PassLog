<?php
// 강의 노트용 작은 마크다운 렌더러.
// 모든 글자는 먼저 HTML 이스케이프한 뒤 정해진 문법만 태그로 바꾸므로, 본문에 HTML/스크립트가 있어도 그대로 글자로 보여.
// 지원: # ~ ###### 제목, 문단, - / * 목록(들여쓰기 2칸 = 하위 목록), 1. 번호 목록, > 인용(팁), ``` 코드블록, | 표 |, **굵게**, *기울임*, `코드`
declare(strict_types=1);

function md_inline(string $s): string
{
    $s = h($s);
    // 인라인 코드를 먼저 빼 두고(안쪽은 다른 문법 적용 안 함) 나중에 복원
    $codes = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x01" . (count($codes) - 1) . "\x01";
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?![\*\w])/u', '<em>$1</em>', $s);
    return preg_replace_callback("/\x01(\d+)\x01/", fn($m) => $codes[(int)$m[1]], $s);
}

function md_table(array $lines): string
{
    $rows = array_map(function ($l) {
        $l = trim($l);
        $l = preg_replace('/^\||\|$/', '', $l);
        return array_map('trim', explode('|', $l));
    }, $lines);
    $head = array_shift($rows);
    if ($rows && preg_match('/^:?-{2,}:?$/', $rows[0][0] ?? '')) array_shift($rows);   // 구분줄
    $html = '<div class="md-table"><table><thead><tr>';
    foreach ($head as $c) $html .= '<th>' . md_inline($c) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>';
        foreach ($r as $c) $html .= '<td>' . md_inline($c) . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** 마크다운 → 안전한 HTML. $toc 에는 목차(h2) 가 담긴다. */
function markdown(string $md, array &$toc = []): string
{
    $lines = preg_split("/\r\n|\n|\r/", $md);
    $out = [];
    $para = [];
    $n = count($lines);
    $flushPara = function () use (&$para, &$out) {
        if ($para) { $out[] = '<p>' . implode('<br>', array_map('md_inline', $para)) . '</p>'; $para = []; }
    };
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        $t = trim($line);
        if ($t === '') { $flushPara(); continue; }

        // 코드블록
        if (str_starts_with($t, '```')) {
            $flushPara();
            $lang = trim(substr($t, 3));
            $buf = [];
            for ($i++; $i < $n && !str_starts_with(trim($lines[$i]), '```'); $i++) $buf[] = $lines[$i];
            $out[] = '<pre class="sql md-code"' . ($lang !== '' ? ' data-lang="' . h($lang) . '"' : '') . '><code>' . h(implode("\n", $buf)) . '</code></pre>';
            continue;
        }
        // 제목
        if (preg_match('/^(#{1,6})\s+(.*)$/u', $t, $m)) {
            $flushPara();
            $lv = max(2, min(4, strlen($m[1]) + ($m[1] === '#' ? 1 : 0)));
            $id = 'sec-' . (count($toc) + 1);
            if ($lv === 2) $toc[] = ['id' => $id, 'title' => strip_tags(md_inline($m[2]))];
            $out[] = "<h{$lv}" . ($lv === 2 ? " id=\"{$id}\"" : '') . '>' . md_inline($m[2]) . "</h{$lv}>";
            continue;
        }
        // 표
        if (str_starts_with($t, '|')) {
            $flushPara();
            $buf = [];
            for (; $i < $n && str_starts_with(trim($lines[$i]), '|'); $i++) $buf[] = $lines[$i];
            $i--;
            $out[] = md_table($buf);
            continue;
        }
        // 인용(팁)
        if (str_starts_with($t, '>')) {
            $flushPara();
            $buf = [];
            for (; $i < $n && str_starts_with(trim($lines[$i]), '>'); $i++) $buf[] = ltrim(substr(trim($lines[$i]), 1));
            $i--;
            $out[] = '<blockquote class="md-tip">' . implode('<br>', array_map('md_inline', $buf)) . '</blockquote>';
            continue;
        }
        // 목록
        if (preg_match('/^(\s*)([-*]|\d+\.)\s+/', $line)) {
            $flushPara();
            $ordered = (bool)preg_match('/^\s*\d+\./', $line);
            $tag = $ordered ? 'ol' : 'ul';
            $html = "<$tag>";
            for (; $i < $n; $i++) {
                $l = $lines[$i];
                if (trim($l) === '') break;
                if (!preg_match('/^(\s*)([-*]|\d+\.)\s+(.*)$/', $l, $m)) {
                    // 목록 항목의 이어지는 줄
                    if (preg_match('/^\s{2,}\S/', $l)) { $html = preg_replace('/<\/li>$/', '<br>' . md_inline(trim($l)) . '</li>', $html); continue; }
                    break;
                }
                $nested = strlen($m[1]) >= 2;
                $html .= '<li' . ($nested ? ' class="sub"' : '') . '>' . md_inline($m[3]) . '</li>';
            }
            $i--;
            $out[] = $html . "</$tag>";
            continue;
        }
        $para[] = $t;
    }
    $flushPara();
    return implode("\n", $out);
}

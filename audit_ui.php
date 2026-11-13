<?php
// Ad-hoc UI audit: inventory Tailwind tokens and decoration patterns.
$files = array_merge(
    glob("views/*/*.php") ?: [],
    ["src/Helpers.php", "assets/js/app.js", "assets/js/documents.js", "assets/js/uploader.js", "assets/js/preview.js"]
);
$tokens = [];
$classAttrs = 0;
$q = "(\"|')";
foreach ($files as $f) {
    $s = file_get_contents($f);
    $clean = preg_replace("/[<][?]php.*?[?][>]/s", " ", $s);
    $clean = preg_replace("/[<][?].*?[?][>]/s", " ", $clean);
    if (preg_match_all("/class\s*=\s*([\"'])(.*?)\\1/s", $clean, $m)) {
        $classAttrs += count($m[1]);
        foreach ($m[2] as $attr) {
            foreach (preg_split("/\s+/", trim($attr)) as $t) {
                if ($t === "" || strpos($t, "{") !== false || strpos($t, "$") !== false) continue;
                $tokens[$t][] = $f;
            }
        }
    }
}
$HUE = "slate|gray|zinc|neutral|red|rose|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink";
$groups = [];
foreach ($tokens as $t => $fs) {
    $row = ["token" => $t, "n" => count($fs), "files" => array_values(array_unique($fs))];
    if (preg_match("/^rounded/", $t)) $g = "rounded";
    elseif (preg_match("/^shadow/", $t)) $g = "shadow";
    elseif (preg_match("/gradient/", $t)) $g = "gradient";
    elseif (preg_match("/^bg-($HUE)-\d+/", $t)) $g = "bgHue";
    elseif (preg_match("/^text-(?:$HUE)-\d+/", $t)) $g = "textHue";
    elseif (preg_match("/^border-(?:$HUE)-\d+/", $t)) $g = "borderHue";
    elseif (preg_match("/^z-/", $t)) $g = "zIndex";
    elseif (preg_match("/^(animate|transition|duration|ease)/", $t)) $g = "animation";
    elseif (preg_match("/^(p|m|gap)-/", $t) || preg_match("/^(px|py|pt|pb|pl|pr|mx|my|mt|mb|ml|mr|space[xy]?)-/", $t)) $g = "spacing";
    elseif (preg_match("/[\[\]]/", $t)) $g = "arbitrary";
    else $g = "other";
    $groups[$g][] = $row;
}
echo "files scanned: " . count($files) . " | class attributes: {$classAttrs} | unique tokens: " . count($tokens) . "\n";
foreach (["rounded", "shadow", "gradient", "bgHue", "textHue", "borderHue", "zIndex", "animation", "arbitrary", "spacing", "other"] as $g) {
    if (empty($groups[$g])) continue;
    $rows = $groups[$g];
    usort($rows, fn($a, $b) => $b["n"] - $a["n"]);
    $total = array_sum(array_column($rows, "n"));
    echo "\n### {$g}: " . count($rows) . " unique / {$total} usages\n";
    foreach (array_slice($rows, 0, 14) as $r) {
        $fl = implode(", ", array_map(fn($x) => str_replace(["views/", "assets/js/"], "", $x), array_slice($r["files"], 0, 3)));
        echo sprintf("  %-30s x%-4d %s\n", $r["token"], $r["n"], $fl);
    }
    if (count($rows) > 14) echo "  ... " . (count($rows) - 14) . " more\n";
}

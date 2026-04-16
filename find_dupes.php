<?php
// Find all duplicate route names in web.php
$content = file_get_contents('routes/web.php');
$lines = explode("\n", $content);

$names = [];
foreach ($lines as $i => $line) {
    if (preg_match("/->name\(['\"]([^'\"]+)['\"]\)/", $line, $m)) {
        $name = $m[1];
        $names[$name][] = $i + 1; // 1-based line number
    }
}

foreach ($names as $name => $lineNums) {
    if (count($lineNums) > 1) {
        echo "$name: lines " . implode(', ', $lineNums) . "\n";
    }
}

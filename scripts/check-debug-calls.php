<?php

declare(strict_types=1);

$roots = array_slice($argv, 1);

if ($roots === []) {
    fwrite(STDERR, "Usage: php scripts/check-debug-calls.php <path> [path ...]\n");
    exit(2);
}

$debugFunctions = ['dd', 'dump', 'var_dump'];
$excludedPredecessors = [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON];

if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
    $excludedPredecessors[] = T_NULLSAFE_OBJECT_OPERATOR;
}

$files = [];

foreach ($roots as $root) {
    if (is_file($root)) {
        if (str_ends_with(strtolower($root), '.php')) {
            $files[] = $root;
        }
        continue;
    }

    if (! is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$findings = [];

foreach ($files as $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$file}\n");
        exit(2);
    }

    $tokens = token_get_all($source);
    $previous = null;

    foreach ($tokens as $index => $token) {
        if (is_array($token)) {
            [$type, $text, $line] = $token;

            if ($type === T_STRING && in_array(strtolower($text), $debugFunctions, true)) {
                $next = nextSignificantToken($tokens, $index + 1);
                $previousType = is_array($previous) ? $previous[0] : $previous;

                if ($next === '(' && ! in_array($previousType, $excludedPredecessors, true)) {
                    $findings[] = sprintf('%s:%d: %s()', str_replace('\\', '/', $file), $line, $text);
                }
            }

            if (! in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $previous = $token;
            }
            continue;
        }

        if (trim($token) !== '') {
            $previous = $token;
        }
    }
}

if ($findings !== []) {
    fwrite(STDERR, implode(PHP_EOL, $findings).PHP_EOL);
    fwrite(STDERR, "Found executable dd(), dump(), or var_dump() calls.\n");
    exit(1);
}

fwrite(STDOUT, "No executable dd(), dump(), or var_dump() calls found.\n");

function nextSignificantToken(array $tokens, int $start): array|string|null
{
    for ($index = $start, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        if (is_string($token) && trim($token) === '') {
            continue;
        }

        return $token;
    }

    return null;
}

<?php
// Normalize whitespace safely:
// - Collapse multiple blank lines to max 1
// - Remove trailing spaces
// - Preserve leading indentation characters exactly as-is

if ($argc < 2) {
    fwrite(STDERR, "Usage: php normalize_whitespace.php <target-file> [max-blank-lines]\n");
    exit(1);
}

$file = $argv[1];
if (!is_file($file)) {
    fwrite(STDERR, "File not found: $file\n");
    exit(1);
}

$maxBlank = isset($argv[2]) ? max(0, (int)$argv[2]) : 1; // default collapse to 1

$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "Failed to read: $file\n");
    exit(1);
}

// Split by lines to avoid changing indentation characters
$lines = preg_split("/\r\n|\r|\n/", $content);
$normalized = [];
$blankCount = 0;

foreach ($lines as $line) {
    // Remove trailing whitespace only
    $line = rtrim($line, " \t\0\x0B\xC2\xA0");

    if ($line === '') {
        $blankCount++;
        if ($blankCount > $maxBlank) {
            continue; // collapse extra blank lines
        }
    } else {
        $blankCount = 0;
    }

    $normalized[] = $line;
}

$result = implode(PHP_EOL, $normalized);
if ($result !== $content) {
    file_put_contents($file, $result);
}

echo "Whitespace normalized: $file\n";
?>



<?php
/**
 * Analyze PHP file dependencies
 * 
 * Scans a PHP file to identify:
 * - Functions it defines
 * - Functions it calls
 * - Classes it defines
 * - Classes it uses
 * - Constants it uses
 */

if ($argc < 2) {
    echo "Usage: php analyze_dependencies.php <file.php>\n";
    exit(1);
}

$file = $argv[1];
if (!file_exists($file)) {
    echo "File not found: $file\n";
    exit(1);
}

$content = file_get_contents($file);

// Extract function definitions
preg_match_all('/function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/', $content, $definedFunctions);

// Extract class definitions  
preg_match_all('/class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $content, $definedClasses);

// Extract function calls (heuristic)
preg_match_all('/([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/', $content, $allCalls);

// Extract constants (defined and used)
preg_match_all('/define\s*\(\s*[\'"]([A-Z_]+)[\'"]/', $content, $definedConstants);
preg_match_all('/\b([A-Z_]{2,})\b/', $content, $allConstants);

// Extract class instantiations
preg_match_all('/new\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $content, $classInstances);

$defined = array_unique($definedFunctions[1]);
$called = array_diff(array_unique($allCalls[1]), $defined, ['if', 'while', 'for', 'foreach', 'switch', 'isset', 'empty', 'echo', 'print', 'return', 'array', 'list', 'die', 'exit']);

// Filter to likely WP functions
$wpFunctions = array_filter($called, function($f) {
    return str_starts_with($f, 'wp_') || 
           str_starts_with($f, 'get_') || 
           str_starts_with($f, 'is_') ||
           str_starts_with($f, 'esc_') ||
           str_starts_with($f, 'sanitize_') ||
           str_starts_with($f, 'add_') ||
           str_starts_with($f, 'apply_') ||
           str_starts_with($f, 'do_') ||
           in_array($f, ['__', '_e', '_x', '_n', 'absint']);
});

sort($defined);
sort($wpFunctions);

echo "\n";
echo "File: " . basename($file) . "\n";
echo str_repeat("=", 70) . "\n\n";

echo "DEFINES (" . count($defined) . " functions):\n";
foreach (array_slice($defined, 0, 20) as $func) {
    echo "  - $func()\n";
}
if (count($defined) > 20) {
    echo "  ... and " . (count($defined) - 20) . " more\n";
}

echo "\nNEEDS FROM STUBS (" . count($wpFunctions) . " WP functions):\n";
$needed = array_slice($wpFunctions, 0, 30);
foreach ($needed as $func) {
    echo "  - $func()\n";
}
if (count($wpFunctions) > 30) {
    echo "  ... and " . (count($wpFunctions) - 30) . " more\n";
}

if (!empty($definedClasses[1])) {
    echo "\nDEFINES CLASSES:\n";
    foreach (array_unique($definedClasses[1]) as $class) {
        echo "  - $class\n";
    }
}

if (!empty($classInstances[1])) {
    $instances = array_unique($classInstances[1]);
    echo "\nINSTANTIATES CLASSES:\n";
    foreach (array_slice($instances, 0, 10) as $class) {
        echo "  - $class\n";
    }
    if (count($instances) > 10) {
        echo "  ... and " . (count($instances) - 10) . " more\n";
    }
}

echo "\n";

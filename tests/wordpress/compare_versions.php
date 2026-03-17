<?php
/**
 * Compare Original vs Migrated Files - Execution-Based Testing
 * 
 * Tests both original and LLM-migrated versions of files, comparing results.
 * This validates that migrations preserve functional behavior.
 */

declare(strict_types=1);

require_once __DIR__ . '/run_one.php';

class ComparisonRunner {
    private array $results = [];
    private string $originalBase;
    private string $migratedBase;
    private string $modelName;
    
    public function __construct(
        string $originalBase,
        string $migratedBase,
        string $modelName
    ) {
        $this->originalBase = $originalBase;
        $this->migratedBase = $migratedBase;
        $this->modelName = $modelName;
    }
    
    public function compareFile(string $fileId): array {
        $originalFile = $this->findFile($this->originalBase, $fileId);
        $migratedFile = $this->migratedBase . '/' . $fileId;
        
        if (!$originalFile) {
            echo "⊘ Original file not found: $fileId\n";
            return ['status' => 'skipped', 'reason' => 'original not found'];
        }
        
        if (!file_exists($migratedFile)) {
            echo "⊘ Migrated file not found: $migratedFile\n";
            return ['status' => 'skipped', 'reason' => 'migrated not found'];
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "Comparing: $fileId\n";
        echo "Original: " . basename($originalFile) . "\n";
        echo "Migrated: {$this->modelName}\n";
        echo str_repeat("=", 70) . "\n\n";
        
        // Test original in separate process to avoid function redeclaration
        echo "[ORIGINAL VERSION]\n";
        $originalResult = $this->runInSubprocess($originalFile);
        
        // Test migrated in separate process
        echo "\n[MIGRATED VERSION - {$this->modelName}]\n";
        $migratedResult = $this->runInSubprocess($migratedFile);
        
        // Compare results
        $comparison = $this->compareResults($originalResult, $migratedResult);
        
        echo "\n" . str_repeat("-", 70) . "\n";
        echo "COMPARISON RESULT:\n";
        echo "  Status: " . $comparison['status'] . "\n";
        
        if ($comparison['status'] === 'REGRESSION') {
            echo "  ⚠ REGRESSION: Migrated version has new failures\n";
            foreach ($comparison['new_errors'] as $error) {
                echo "    - {$error}\n";
            }
        } elseif ($comparison['status'] === 'IMPROVED') {
            echo "  ↑ IMPROVED: Migrated version fixed issues\n";
        } elseif ($comparison['status'] === 'BOTH_PASS') {
            echo "  ✓ BOTH PASS: Migration preserved behavior\n";
        } elseif ($comparison['status'] === 'BOTH_FAIL') {
            echo "  ⊗ BOTH FAIL: Both versions have issues\n";
        }
        
        echo str_repeat("=", 70) . "\n";
        
        return [
            'file' => $fileId,
            'original' => $originalResult,
            'migrated' => $migratedResult,
            'comparison' => $comparison
        ];
    }
    
    private function runInSubprocess(string $file): array {
        // Run test in separate PHP process to avoid function redeclaration
        $cmd = sprintf(
            'php %s -f %s 2>&1',
            escapeshellarg(__DIR__ . '/run_one.php'),
            escapeshellarg($file)
        );
        
        $output = shell_exec($cmd);
        
        // Parse output to extract result
        // Look for status line
        if (preg_match('/Status: (✓ PASSED|✗ FAILED)/', $output, $matches)) {
            $success = $matches[1] === '✓ PASSED';
        } else {
            $success = false;
        }
        
        // Extract errors
        $errors = [];
        if (preg_match_all('/\[(FATAL|ERROR|WARNING|OUTPUT)\] (.+)/m', $output, $errorMatches)) {
            for ($i = 0; $i < count($errorMatches[0]); $i++) {
                $errors[] = [
                    'type' => $errorMatches[1][$i],
                    'message' => $errorMatches[2][$i]
                ];
            }
        }
        
        // Extract metrics
        preg_match('/Duration: ([\d.]+)ms/', $output, $durationMatch);
        
        echo $output; // Show the output
        
        return [
            'file' => $file,
            'success' => $success,
            'duration' => isset($durationMatch[1]) ? floatval($durationMatch[1]) / 1000 : 0,
            'errors' => $errors
        ];
    }
    
    private function findFile(string $base, string $fileId): ?string {
        // Try direct path
        if (file_exists($base . '/' . $fileId)) {
            return $base . '/' . $fileId;
        }
        
        // Search in subdirectories (small_1_200, medium_201_500, etc.)
        $dirs = ['small_1_200', 'medium_201_500', 'large_500_1000', 'extra_large_1000_plus'];
        foreach ($dirs as $dir) {
            $path = $base . '/' . $dir . '/' . $fileId;
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    private function compareResults(array $original, array $migrated): array {
        $origSuccess = $original['success'];
        $migrSuccess = $migrated['success'];
        
        if ($origSuccess && $migrSuccess) {
            $status = 'BOTH_PASS';
        } elseif (!$origSuccess && !$migrSuccess) {
            $status = 'BOTH_FAIL';
        } elseif ($origSuccess && !$migrSuccess) {
            $status = 'REGRESSION';
        } else {
            $status = 'IMPROVED';
        }
        
        // Find new errors in migrated version
        $newErrors = [];
        if ($status === 'REGRESSION') {
            foreach ($migrated['errors'] as $error) {
                $found = false;
                foreach ($original['errors'] as $origError) {
                    if ($error['type'] === $origError['type'] && 
                        $error['message'] === $origError['message']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $newErrors[] = "[{$error['type']}] {$error['message']}";
                }
            }
        }
        
        return [
            'status' => $status,
            'original_success' => $origSuccess,
            'migrated_success' => $migrSuccess,
            'original_errors' => count($original['errors']),
            'migrated_errors' => count($migrated['errors']),
            'new_errors' => $newErrors
        ];
    }
    
    public function compareMultiple(array $fileIds): array {
        $allResults = [];
        
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════╗\n";
        echo "║         COMPARISON RUNNER - Original vs Migrated Files           ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════╝\n";
        echo "\nConfiguration:\n";
        echo "  Files: " . count($fileIds) . "\n";
        echo "  Model: {$this->modelName}\n";
        echo "  Original: {$this->originalBase}\n";
        echo "  Migrated: {$this->migratedBase}\n";
        echo "\n";
        
        $startTime = microtime(true);
        
        foreach ($fileIds as $index => $fileId) {
            echo "\n[" . ($index + 1) . "/" . count($fileIds) . "] ";
            try {
                $result = $this->compareFile($fileId);
                $allResults[] = $result;
            } catch (Throwable $e) {
                echo "\n✗ CRASHED: {$e->getMessage()}\n";
                $allResults[] = [
                    'file' => $fileId,
                    'comparison' => ['status' => 'CRASH', 'error' => $e->getMessage()]
                ];
            }
        }
        
        $totalDuration = microtime(true) - $startTime;
        
        $this->printSummary($allResults, $totalDuration);
        $this->saveReport($allResults);
        
        return $allResults;
    }
    
    private function printSummary(array $results, float $duration): void {
        $bothPass = array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'BOTH_PASS');
        $regressions = array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'REGRESSION');
        $improved = array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'IMPROVED');
        $bothFail = array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'BOTH_FAIL');
        $skipped = array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'skipped');
        
        echo "\n\n";
        echo "╔═══════════════════════════════════════════════════════════════════╗\n";
        echo "║                    COMPARISON SUMMARY REPORT                      ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Model: {$this->modelName}\n";
        echo "Duration: " . number_format($duration, 2) . "s\n\n";
        
        echo "Results:\n";
        echo "  Total Files:       " . count($results) . "\n";
        echo "  ✓ Both Pass:       " . count($bothPass) . " (" . $this->percentage(count($bothPass), count($results)) . "%)\n";
        echo "  ⚠ Regressions:     " . count($regressions) . " (" . $this->percentage(count($regressions), count($results)) . "%)\n";
        echo "  ↑ Improved:        " . count($improved) . " (" . $this->percentage(count($improved), count($results)) . "%)\n";
        echo "  ⊗ Both Fail:       " . count($bothFail) . " (" . $this->percentage(count($bothFail), count($results)) . "%)\n";
        echo "  ⊘ Skipped:         " . count($skipped) . "\n";
        echo "\n";
        
        if (!empty($regressions)) {
            echo "⚠ REGRESSIONS (migrated version broke):\n";
            foreach ($regressions as $result) {
                echo "  ✗ {$result['file']}\n";
                if (!empty($result['comparison']['new_errors'])) {
                    foreach (array_slice($result['comparison']['new_errors'], 0, 2) as $error) {
                        echo "      " . substr($error, 0, 60) . "...\n";
                    }
                }
            }
            echo "\n";
        }
        
        // Calculate success rate (both pass / total tested)
        $tested = count($results) - count($skipped);
        $successRate = $tested > 0 ? $this->percentage(count($bothPass), $tested) : '0.0';
        
        echo "Migration Success Rate: {$successRate}%\n";
        
        if ($successRate >= 90) {
            echo "Status: ✓ EXCELLENT - Migration preserved behavior\n";
        } elseif ($successRate >= 75) {
            echo "Status: ⚠ GOOD - Most behavior preserved\n";
        } elseif ($successRate >= 50) {
            echo "Status: ⚠ FAIR - Significant regressions\n";
        } else {
            echo "Status: ✗ POOR - Many regressions\n";
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
    }
    
    private function percentage(int $part, int $total): string {
        if ($total === 0) return '0.0';
        return number_format(($part / $total) * 100, 1);
    }
    
    private function saveReport(array $results): void {
        $reportDir = __DIR__ . '/results';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        $modelSlug = preg_replace('/[^a-z0-9_]/', '_', strtolower($this->modelName));
        $reportFile = $reportDir . '/comparison_' . $modelSlug . '.json';
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'model' => $this->modelName,
            'original_base' => $this->originalBase,
            'migrated_base' => $this->migratedBase,
            'total_files' => count($results),
            'both_pass' => count(array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'BOTH_PASS')),
            'regressions' => count(array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'REGRESSION')),
            'improved' => count(array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'IMPROVED')),
            'both_fail' => count(array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'BOTH_FAIL')),
            'results' => $results
        ];
        
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\nReport saved to: $reportFile\n";
    }
}

// CLI interface
if (php_sapi_name() === 'cli' && realpath($_SERVER['PHP_SELF']) === realpath(__FILE__)) {
    $opts = getopt('m:f:o:h', ['model:', 'file-list:', 'original-base:', 'help']);
    
    if (isset($opts['h']) || isset($opts['help']) || empty($opts)) {
        echo <<<HELP
Usage: php compare_versions.php -m <model> [options]

Options:
  -m, --model <name>          Model name (required)
                              Options: claude_sonnet_4_20250514, gemini_2_5_flash,
                                       gemini_2_5_pro, gpt_5_codex, 
                                       meta_llama_llama_3_3_70b_instruct,
                                       Rector_Baseline
  -f, --file-list <path>      File containing list of file IDs (one per line)
  -o, --original-base <path>  Base path to original files (default: ../dataset/wordpress/selected_100_files)
  -h, --help                  Show this help

Examples:
  # Compare all files in selected_files.txt for Claude Sonnet
  php compare_versions.php -m claude_sonnet_4_20250514 -f selected_files.txt

  # Compare with Rector baseline
  php compare_versions.php -m Rector_Baseline -f selected_files.txt

  # Compare specific files
  php compare_versions.php -m gemini_2_5_pro -f my_files.txt

HELP;
        exit(0);
    }
    
    $model = $opts['m'] ?? $opts['model'] ?? null;
    $fileList = $opts['f'] ?? $opts['file-list'] ?? null;
    $originalBase = $opts['o'] ?? $opts['original-base'] ?? '../../dataset/wordpress/selected_100_files';
    
    if (!$model) {
        echo "Error: -m/--model is required\n";
        exit(1);
    }
    
    if (!$fileList) {
        echo "Error: -f/--file-list is required\n";
        exit(1);
    }
    
    $migratedBase = "../../LLM_Migration/wordpress/outputs/new-version/$model";
    
    if (!is_dir($migratedBase)) {
        echo "Error: Migrated directory not found: $migratedBase\n";
        echo "Available models:\n";
        $modelsDir = "../../LLM_Migration/wordpress/outputs/new-version";
        if (is_dir($modelsDir)) {
            foreach (scandir($modelsDir) as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($modelsDir . '/' . $dir)) {
                    echo "  - $dir\n";
                }
            }
        }
        exit(1);
    }
    
    // Read file list
    if (!file_exists($fileList)) {
        echo "Error: File list not found: $fileList\n";
        exit(1);
    }
    
    $files = file($fileList, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $fileIds = [];
    foreach ($files as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }
        // Extract just the filename from path
        $fileIds[] = basename($line);
    }
    
    if (empty($fileIds)) {
        echo "Error: No files found in list\n";
        exit(1);
    }
    
    $runner = new ComparisonRunner($originalBase, $migratedBase, $model);
    $results = $runner->compareMultiple($fileIds);
    
    // Exit with error code if any regressions
    $regressions = array_filter($results, fn($r) => ($r['comparison']['status'] ?? '') === 'REGRESSION');
    exit(empty($regressions) ? 0 : 1);
}

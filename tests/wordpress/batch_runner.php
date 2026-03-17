<?php
/**
 * Batch Test Runner - Execute tests on multiple files
 * 
 * Runs execution-based slice tests on a collection of files and generates
 * a summary report.
 */

declare(strict_types=1);

require_once __DIR__ . '/run_one.php';

class BatchTestRunner {
    private array $files = [];
    private array $results = [];
    
    public function __construct() {
    }
    
    public function addFile(string $filePath): void {
        $this->files[] = $filePath;
    }
    
    public function addDirectory(string $dirPath, string $pattern = '*.php'): void {
        $files = glob($dirPath . '/' . $pattern);
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }
    
    public function run(): array {
        if (empty($this->files)) {
            echo "\nError: No files to test!\n";
            return [];
        }
        
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════════╗\n";
        echo "║            BATCH TEST RUNNER - Execution-Based Slice             ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════╝\n";
        echo "\nConfiguration:\n";
        echo "  Files: " . count($this->files) . "\n";
        echo "\n";
        
        $startTime = microtime(true);
        
        foreach ($this->files as $index => $file) {
            echo "\n[" . ($index + 1) . "/" . count($this->files) . "] ";
            
            // Run each test in a separate process to isolate fatal errors
            $result = $this->runTestInProcess($file);
            $this->results[] = $result;
        }
        
        $totalDuration = microtime(true) - $startTime;
        
        $this->printSummary($totalDuration);
        $this->saveReport();
        
        return $this->results;
    }
    
    private function printSummary(float $duration): void {
        $passed = array_filter($this->results, fn($r) => $r['success']);
        $failed = array_filter($this->results, fn($r) => !$r['success']);
        
        echo "\n\n";
        echo "╔═══════════════════════════════════════════════════════════════════╗\n";
        echo "║                          SUMMARY REPORT                           ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Overall Results:\n";
        echo "  Total Files:       " . count($this->results) . "\n";
        echo "  ✓ Passed:          " . count($passed) . " (" . $this->percentage(count($passed), count($this->results)) . "%)\n";
        echo "  ✗ Failed:          " . count($failed) . " (" . $this->percentage(count($failed), count($this->results)) . "%)\n";
        echo "  Duration:          " . number_format($duration, 2) . "s\n";
        echo "\n";
        
        if (!empty($failed)) {
            echo "Failed Files:\n";
            foreach ($failed as $result) {
                $filename = basename($result['file']);
                $errorCount = count($result['errors']);
                echo "  ✗ $filename ($errorCount errors)\n";
                
                // Show first few errors
                foreach (array_slice($result['errors'], 0, 2) as $error) {
                    echo "      [{$error['type']}] " . substr($error['message'], 0, 200) . "...\n";
                }
            }
            echo "\n";
        }
        
        // Success rate
        $successRate = $this->percentage(count($passed), count($this->results));
        echo "Success Rate: {$successRate}%\n";
        
        if ($successRate >= 80) {
            echo "Status: ✓ EXCELLENT\n";
        } elseif ($successRate >= 60) {
            echo "Status: ⚠ GOOD\n";
        } elseif ($successRate >= 40) {
            echo "Status: ⚠ FAIR\n";
        } else {
            echo "Status: ✗ NEEDS WORK\n";
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
    }
    
    private function runTestInProcess(string $file): array {
        // Build command to run test in separate process
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/run_one.php');
        $cmd .= ' -f ' . escapeshellarg($file);
        $cmd .= ' --json'; // Request JSON output
        
        // Execute in separate process
        $output = shell_exec($cmd . ' 2>&1');
        
        // Try to parse JSON output between markers
        if ($output && preg_match('/__JSON_START__\s*(\{.*?\})\s*__JSON_END__/s', $output, $matches)) {
            $result = json_decode($matches[1], true);
            if ($result && isset($result['file'])) {
                return $result;
            }
        }
        
        // If JSON parsing failed, create error result
        return [
            'file' => $file,
            'success' => false,
            'duration' => 0,
            'errors' => [
                ['type' => 'PROCESS_ERROR', 'message' => 'Failed to parse test results. Output: ' . substr($output ?? 'No output', 0, 1000)]
            ]
        ];
    }
    
    private function percentage(int $part, int $total): string {
        if ($total === 0) return '0.0';
        return number_format(($part / $total) * 100, 1);
    }
    
    private function saveReport(): void {
        $reportDir = __DIR__ . '/results';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        $reportFile = $reportDir . '/batch_report.json';
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_files' => count($this->results),
            'passed' => count(array_filter($this->results, fn($r) => $r['success'])),
            'failed' => count(array_filter($this->results, fn($r) => !$r['success'])),
            'results' => $this->results
        ];
        
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "\nReport saved to: $reportFile\n";
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $opts = getopt('d:p:f:h', ['dir:', 'pattern:', 'file-list:', 'help']);
    
    if (isset($opts['h']) || isset($opts['help']) || empty($opts)) {
        echo <<<HELP
Usage: php batch_runner.php [options]

Options:
  -d, --dir <path>        Directory containing files to test
  -p, --pattern <glob>    File pattern (default: *.php)
  -f, --file-list <path>  File containing list of files to test (one per line)
  -h, --help              Show this help

Examples:
  # Test all files in organized dataset
  php batch_runner.php -d ../dataset/wordpress/organized_dataset_All

  # Test specific pattern
  php batch_runner.php -d ../LLM_Migration/wordpress/model_output -p "006_*.php"

  # Test from file list
  php batch_runner.php -f selected_files.txt

HELP;
        exit(0);
    }
    
    $dir = $opts['d'] ?? $opts['dir'] ?? null;
    $pattern = $opts['p'] ?? $opts['pattern'] ?? '*.php';
    $fileList = $opts['f'] ?? $opts['file-list'] ?? null;
    
    $runner = new BatchTestRunner();
    
    if ($fileList) {
        if (!file_exists($fileList)) {
            echo "Error: File list not found: $fileList\n";
            exit(1);
        }
        
        echo "Reading file list: $fileList\n";
        $files = file($fileList, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $addedCount = 0;
        foreach ($files as $file) {
            $file = trim($file);
            // Skip comments and empty lines
            if (empty($file) || str_starts_with($file, '#')) {
                continue;
            }
            
            // Resolve relative paths from test directory
            $originalFile = $file;
            if (!file_exists($file)) {
                $file = __DIR__ . '/' . $file;
            }
            
            if (file_exists($file)) {
                $runner->addFile($file);
                $addedCount++;
            } else {
                echo "Warning: File not found, skipping: $originalFile\n";
            }
        }
        echo "Added $addedCount files from list\n\n";
    } elseif ($dir) {
        if (!is_dir($dir)) {
            echo "Error: Directory not found: $dir\n";
            exit(1);
        }
        
        $runner->addDirectory($dir, $pattern);
    } else {
        echo "Error: Either -d/--dir or -f/--file-list is required\n";
        exit(1);
    }
    
    $results = $runner->run();
    
    // Exit with error code if any tests failed
    $failed = array_filter($results, fn($r) => !$r['success']);
    exit(empty($failed) ? 0 : 1);
}

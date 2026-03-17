<?php
/**
 * Single File Test Runner - Execution-Based Slice
 * 
 * Loads a PHP file in a minimal WordPress stub environment and checks for loadability.
 * Tests whether the file loads without fatal errors under isolated conditions.
 */

declare(strict_types=1);

class TestRunner {
    private array $results = [];
    private array $errors = [];
    private array $warnings = []; // All warnings/notices, not just from target
    private bool $strictOutput = false; // Treat output as error?
    
    public function __construct(
        private string $targetFile,
        bool $strictOutput = false
    ) {
        $this->strictOutput = $strictOutput;
    }
    
    public function run(): array {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "Testing: {$this->targetFile}\n";
        echo str_repeat("=", 70) . "\n\n";
        
        // Start timer
        $startTime = microtime(true);
        
        try {
            // Load stubs first
            $this->loadStubs();
            
            // Try to load the file
            $this->loadFile();
            
        } catch (Throwable $e) {
            $this->recordError("FATAL", $e->getMessage(), $e);
        }
        
        $duration = microtime(true) - $startTime;
        
        return $this->formatResults($duration);
    }
    
    private function loadStubs(): void {
        $stubFile = __DIR__ . '/stubs/wp_core.php';
        if (!file_exists($stubFile)) {
            throw new Exception("Stub file not found: $stubFile");
        }
        require_once $stubFile;
        $this->log("✓ Loaded WordPress stubs");
    }
    
    private function loadFile(): void {
        $this->log("\nLoading file...");
        
        // Install error handler to catch ALL warnings/notices (including from stubs)
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $errorType = match($errno) {
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
                E_NOTICE, E_USER_NOTICE => 'NOTICE',
                E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
                E_STRICT => 'STRICT',
                default => 'ERROR'
            };
            
            $location = basename($errfile) . ":$errline";
            $this->warnings[] = "[$errorType] $errstr at $location";
            
            // Only record as error if from target file
            if (strpos($errfile, $this->targetFile) !== false) {
                $this->recordError("WARNING", "$errstr (line $errline)");
            }
            
            return true; // Suppress default PHP handler
        });
        
        // Capture output
        ob_start();
        
        try {
            if (!file_exists($this->targetFile)) {
                throw new Exception("Target file not found: {$this->targetFile}");
            }
            
            require_once $this->targetFile;
            
            $output = ob_get_clean();
            restore_error_handler();
            
            // Check for output (configurable strictness)
            if (!empty(trim($output))) {
                if ($this->strictOutput) {
                    $this->recordError("OUTPUT", "File produced output during include: " . substr($output, 0, 200));
                    $this->log("⚠ File produced output (strict mode): " . substr($output, 0, 100));
                } else {
                    $this->log("ℹ File produced output (permissive mode): " . substr($output, 0, 100));
                }
            }
            
            $this->log("✓ File loaded successfully");
            
        } catch (Throwable $e) {
            ob_end_clean();
            restore_error_handler();
            throw $e;
        }
    }
    
    private function log(string $message): void {
        echo $message . "\n";
    }
    
    private function recordError(string $type, string $message, ?Throwable $e = null): void {
        $this->errors[] = [
            'type' => $type,
            'message' => $message,
            'trace' => $e ? $e->getTraceAsString() : null
        ];
    }
    
    private function formatResults(float $duration): array {
        // Success only if NO errors
        $success = (count($this->errors) === 0);
        
        echo "\n" . str_repeat("-", 70) . "\n";
        echo "Results:\n";
        echo "  Duration: " . number_format($duration * 1000, 2) . "ms\n";
        echo "  Errors: " . count($this->errors) . "\n";
        echo "  Warnings: " . count($this->warnings) . "\n";
        
        if (!empty($this->errors)) {
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo "  [{$error['type']}] {$error['message']}\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo "\nAll Warnings/Notices (including from stubs):\n";
            foreach ($this->warnings as $warning) {
                echo "  $warning\n";
            }
        }
        
        echo "\nStatus: " . ($success ? "✓ PASSED" : "✗ FAILED") . "\n";
        echo str_repeat("=", 70) . "\n";
        
        return [
            'file' => $this->targetFile,
            'success' => $success,
            'duration' => $duration,
            'errors' => $this->errors
        ];
    }
}

// CLI interface - only run if this is the main script
if (php_sapi_name() === 'cli' && realpath($_SERVER['PHP_SELF']) === realpath(__FILE__)) {
    $opts = getopt('f:shj', ['file:', 'strict-output', 'help', 'json']);
    
    if (isset($opts['h']) || isset($opts['help']) || empty($opts)) {
        echo <<<HELP
Usage: php run_one.php -f <file> [-s] [-j]

Options:
  -f, --file <path>      Target PHP file to test (required)
  -s, --strict-output    Treat output during include as error (default: permissive)
  -j, --json             Output results as JSON (for batch processing)
  -h, --help             Show this help

Example:
  php run_one.php -f ../dataset/wordpress/organized_dataset_All/006_formatting.php

HELP;
        exit(0);
    }
    
    $file = $opts['f'] ?? $opts['file'] ?? null;
    $strictOutput = isset($opts['s']) || isset($opts['strict-output']);
    $jsonOutput = isset($opts['j']) || isset($opts['json']);
    
    if (!$file) {
        echo "Error: -f/--file is required\n";
        exit(1);
    }
    
    // Resolve relative paths
    if (!file_exists($file)) {
        $file = __DIR__ . '/' . $file;
    }
    
    $runner = new TestRunner($file, $strictOutput);
    $results = $runner->run();
    
    // Output JSON if requested (for batch runner to parse)
    if ($jsonOutput) {
        echo "\n__JSON_START__\n" . json_encode($results) . "\n__JSON_END__\n";
    }
    
    exit($results['success'] ? 0 : 1);
}

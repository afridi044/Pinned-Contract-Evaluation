# Execution-Based Slice Testing for PHP Migration

This directory implements a **micro-harness approach** for testing isolated WordPress PHP files without requiring a full WordPress runtime. It complements the paper's static analysis tiers (lint + structural + Rector + PHPCompatibility) with runtime validation.

## Overview

The test system provides three levels of testing fidelity:

- **Level 1**: "Loads cleanly" - ensures files parse and can be included without fatal errors
- **Level 2**: "Symbol-stub + smoke assertions" - validates declarations and basic API surface
- **Level 3**: "Behavior checks" - verifies observable behavior via golden snapshots and differential testing

## Architecture

```
tests/
├── run_one.php           # Single-file test runner
├── batch_runner.php      # Multi-file test orchestrator
├── stubs/
│   └── wp_core.php      # Minimal WordPress stub environment
├── cases/
│   ├── _template.test.php    # Template for new test cases
│   ├── formatting.test.php    # Example: formatting.php tests
│   ├── deprecated.test.php    # Example: deprecated.php tests
│   └── pluggable.test.php     # Example: pluggable.php tests
└── results/              # JSON reports from batch runs
```

## Quick Start

### 1. Test a Single File (Level 1)

The simplest test - just check if the file loads:

```bash
php run_one.php -f ../dataset/organized_dataset_All/006_formatting.php
```

### 2. Test with Assertions (Level 2)

Check that declarations and surface API are intact:

```bash
php run_one.php -f ../dataset/organized_dataset_All/006_formatting.php \
                -t cases/formatting.test.php \
                -l 2
```

### 3. Behavioral Testing (Level 3)

Run full behavioral checks including differential testing:

```bash
php run_one.php -f ../dataset/organized_dataset_All/006_formatting.php \
                -t cases/formatting.test.php \
                -l 3
```

### 4. Batch Testing

Test multiple files at once:

```bash
# Test all files in directory at Level 1
php batch_runner.php -d ../dataset/organized_dataset_All -l 1

# Test specific pattern at Level 2
php batch_runner.php -d ../LLM_Migration/model_output -p "006_*.php" -l 2

# Test from file list
php batch_runner.php -f selected_files.txt -l 2
```

## Creating Test Cases

### Step 1: Copy the Template

```bash
cp cases/_template.test.php cases/your_file.test.php
```

### Step 2: Define Level 2 Assertions (Surface Checks)

```php
'level2' => [
    'function_name exists' => function() {
        return function_exists('function_name');
    },
    
    'ClassName exists' => function() {
        return class_exists('ClassName');
    },
    
    'ClassName has method_name' => function() {
        return class_exists('ClassName') && 
               method_exists('ClassName', 'method_name');
    },
]
```

### Step 3: Add Level 3 Behavioral Checks

```php
'level3' => [
    'function returns expected type' => function() {
        if (!function_exists('target_func')) return 'not defined';
        
        $result = target_func('input');
        return is_string($result);
    },
    
    'differential check against original' => function() {
        if (!function_exists('target_func')) return 'not defined';
        
        $input = 'test';
        $expected = 'expected_output'; // From original
        $actual = target_func($input);
        
        return $actual === $expected ? true : 
               "expected '$expected', got '$actual'";
    },
]
```

## The Stub Environment

The `stubs/wp_core.php` file provides minimal WordPress definitions:

- **Constants**: ABSPATH, WPINC, WP_DEBUG, etc.
- **Globals**: $wpdb, $wp_filter, etc.
- **I18N**: __, _e, _x, _n, esc_html__, etc.
- **Hooks**: add_action, do_action, add_filter, apply_filters
- **Sanitization**: esc_html, esc_attr, sanitize_text_field
- **Error Handling**: WP_Error, is_wp_error
- **Options**: get_option, update_option
- **Utilities**: wp_parse_args, absint, wp_unslash

### Adding Missing Symbols

When tests fail with "undefined function/class/constant" errors:

1. Identify the missing symbol from the error message
2. Add a minimal stub to `stubs/wp_core.php`
3. Re-run the test

Example:

```php
if (!function_exists('wp_new_function')) {
    function wp_new_function($arg) {
        // Minimal implementation
        return $arg;
    }
}
```

## Understanding Results

### Single File Output

```
======================================================================
Testing: dataset/organized_dataset_All/006_formatting.php
Level: 2
======================================================================

✓ Loaded WordPress stubs

[LEVEL 1] Loading file...
✓ File loaded successfully

[LEVEL 2] Running surface assertions...
  ✓ wptexturize function exists
  ✓ wpautop function exists
  ✓ esc_html function exists
  ✗ sanitize_text_field function exists - returned false

----------------------------------------------------------------------
Results:
  Duration: 45.23ms
  Assertions: 4
  Passed: 3
  Failed: 1
  Errors: 1

Errors:
  [ASSERTION] sanitize_text_field function exists failed

Status: ✗ FAILED
======================================================================
```

### Batch Run Output

```
╔═══════════════════════════════════════════════════════════════════╗
║                          SUMMARY REPORT                           ║
╚═══════════════════════════════════════════════════════════════════╝

Overall Results:
  Total Files:       30
  ✓ Passed:          24 (80.0%)
  ✗ Failed:          6 (20.0%)
  Duration:          12.34s

Assertion Statistics:
  Total Assertions:  150
  ✓ Passed:          132 (88.0%)
  ✗ Failed:          18 (12.0%)

Level 2 Success Rate: 80.0%
Status: ✓ EXCELLENT
```



### Key Value Propositions

1. **Catches runtime issues** static analysis misses:
   - Type coercions in PHP 8
   - Null dereferences
   - Wrong `$this` usage in context

2. **Validates transformation correctness**:
   - API surface preserved (Level 2)
   - Behavior preserved (Level 3)

3. **Provides empirical data**:
   - "X% of files load cleanly after migration"
   - "Y% maintain API surface integrity"
   - "Z% pass behavioral validation"

## Common Pitfalls & Solutions

### Files That Assume Global Bootstrap

**Problem**: File expects full WP initialization

**Solution**: 
- Skip these files or
- Add heavier stubs to `wp_core.php`
- Mark as "integration-only" in results

### Conditional Declarations

**Problem**: Functions/classes declared only if certain conditions are met

**Solution**: 
- Control conditions via stubs
- Test both branches
- Use `function_exists()` guards in tests

### Output During Include

**Problem**: File echoes content during include

**Solution**:
- Test harness captures output with `ob_start()`
- Assert buffer is empty (or contains expected output)

### Database Access

**Problem**: File tries to query database

**Solution**:
- Mock `$wpdb` class minimally
- Return empty arrays for queries
- Or skip files with heavy DB usage

## Expected Success Rates

Based on preliminary analysis of dataset:

| Level | Target Files | Expected Pass Rate |
|-------|--------------|-------------------|
| Level 1 | 40-60 files | 70-85% |
| Level 2 | 20-30 files | 60-75% |
| Level 3 | 8-12 files | 50-65% |

Files unsuitable for isolated testing: ~20 files (admin screens, heavy integration)

## Output Files

### JSON Report Structure

```json
{
  "timestamp": "2026-01-21 10:30:00",
  "level": 2,
  "total_files": 30,
  "passed": 24,
  "failed": 6,
  "results": [
    {
      "file": "path/to/file.php",
      "level": 2,
      "success": true,
      "duration": 0.045,
      "assertions": 5,
      "passed": 5,
      "failed": 0,
      "errors": []
    }
  ]
}
```

Reports are saved to `results/batch_report_level.json`


"""
Utility Functions
Helper functions and shared utilities for the LLM migration tool.
"""

import re
import subprocess
import json
from pathlib import Path
from typing import List, Dict, Any, Optional


def normalize_model_name(model_name: str) -> str:
    """Convert model name to filesystem-safe format."""
    return model_name.replace('/', '_').replace('-', '_').replace(':', '_').replace('.', '_').lower()


def load_test_files(directory_path: str) -> Dict[str, str]:
    """Load PHP files from a directory."""
    test_files = {}
    path = Path(directory_path)
    
    if not path.exists():
        print(f"❌ Directory {directory_path} not found")
        return test_files
    
    # Recursively find all PHP files
    for php_file in path.rglob('*.php'):
        try:
            with open(php_file, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                if content.strip():
                    test_files[php_file.name] = content
        except Exception as e:
            print(f"⚠️  Could not load {php_file.name}: {e}")
    
    if test_files:
        print(f"📁 Loaded {len(test_files)} PHP files:")
        for filename in sorted(test_files.keys()):
            size = len(test_files[filename])
            print(f"   📄 {filename} ({size:,} chars)")
    else:
        print(f"❌ No PHP files found in {directory_path}")
    
    return test_files


def analyze_file_sizes(test_files: Dict[str, str], chunk_threshold: int = 500):
    """Analyze file sizes to see chunking requirements."""
    if not test_files:
        print("❌ No test files loaded")
        return
    
    # Categorize files
    small_files, large_files = [], []
    for filename, content in test_files.items():
        line_count = len(content.split('\n'))
        char_count = len(content)
        file_info = (filename, line_count, char_count)
        
        if line_count <= chunk_threshold:
            small_files.append(file_info)
        else:
            large_files.append(file_info)
    
    print("📊 File Size Analysis")
    print("=" * 40)
    
    # Small files summary
    print(f"📄 Small files (≤{chunk_threshold} lines): {len(small_files)}")
    for filename, lines, chars in sorted(small_files, key=lambda x: x[1], reverse=True)[:10]:
        print(f"   {filename}: {lines:,} lines, {chars:,} chars")
    if len(small_files) > 10:
        print(f"   ... and {len(small_files) - 10} more")
    
    # Large files summary
    if large_files:
        print(f"\n📦 Large files (>{chunk_threshold} lines): {len(large_files)}")
        total_lines = sum(lines for _, lines, _ in large_files)
        total_chunks = sum((lines + chunk_threshold - 1) // chunk_threshold for _, lines, _ in large_files)
        
        for filename, lines, chars in sorted(large_files, key=lambda x: x[1], reverse=True):
            chunks = (lines + chunk_threshold - 1) // chunk_threshold
            print(f"   {filename}: {lines:,} lines, {chars:,} chars → {chunks} chunks")
        
        print(f"\n📊 Large files summary: {total_lines:,} total lines → {total_chunks} chunks")


def find_function_boundaries(code: str, lines: List[str]) -> List[Dict[str, Any]]:
    """Find function boundaries using PHP tokenizer if available, else regex."""
    # Try PHP tokenizer first
    php_functions = try_php_tokenizer(code, lines)
    if php_functions:
        return php_functions
    
    # Fallback to regex-based parsing
    return find_functions_with_regex(lines)


def try_php_tokenizer(code: str, lines: List[str]) -> List[Dict[str, Any]]:
    """Try to use PHP's built-in tokenizer for accurate parsing."""
    try:
        php_script = f'''<?php
$code = <<<'EOD'
{code}
EOD;

$tokens = token_get_all($code);
$functions = [];
$current_function = null;
$brace_level = 0;
$in_function = false;

foreach ($tokens as $token) {{
    if (is_array($token)) {{
        if ($token[0] === T_FUNCTION) {{
            $in_function = true;
            $current_function = [
                'start_line' => $token[2] - 1,
                'end_line' => null,
                'name' => null
            ];
        }}
        
        if ($in_function && $token[0] === T_STRING && $current_function['name'] === null) {{
            $current_function['name'] = $token[1];
        }}
    }} else {{
        if ($token === '{{' && $in_function) {{
            $brace_level++;
        }} elseif ($token === '}}' && $in_function) {{
            $brace_level--;
            if ($brace_level === 0) {{
                $current_function['end_line'] = find_closing_brace($current_function['start_line']);
                $functions[] = $current_function;
                $current_function = null;
                $in_function = false;
            }}
        }}
    }}
}}

function find_closing_brace($start_line) {{
    global $code;
    $lines = explode("\\n", $code);
    $brace_count = 0;
    $function_started = false;
    
    for ($i = $start_line; $i < count($lines); $i++) {{
        $line = trim($lines[$i]);
        if (empty($line) || strpos($line, '//') === 0 || strpos($line, '#') === 0) continue;
        
        for ($j = 0; $j < strlen($line); $j++) {{
            $char = $line[$j];
            if ($char === '{{') {{
                $brace_count++;
                $function_started = true;
            }} elseif ($char === '}}' && $function_started) {{
                $brace_count--;
                if ($brace_count === 0) return $i;
            }}
        }}
    }}
    return $start_line;
}}

echo json_encode($functions);
?>'''
        
        temp_php = Path('temp_parser.php')
        with open(temp_php, 'w', encoding='utf-8') as f:
            f.write(php_script)
        
        result = subprocess.run(['php', str(temp_php)], 
                              capture_output=True, text=True, timeout=10)
        temp_php.unlink()
        
        if result.returncode == 0 and result.stdout.strip():
            return json.loads(result.stdout.strip())
            
    except (subprocess.TimeoutExpired, subprocess.CalledProcessError, 
            FileNotFoundError, json.JSONDecodeError):
        pass
    
    return []


def find_functions_with_regex(lines: List[str]) -> List[Dict[str, Any]]:
    """Regex-based function detection with proper closing brace detection."""
    functions = []
    function_patterns = [
        r'^\s*(?:(?:public|private|protected)\s+)?(?:static\s+)?function\s+(\w+)\s*\(',
        r'^\s*(?:abstract\s+)?(?:final\s+)?function\s+(\w+)\s*\(',
        r'^\s*function\s+(\w+)\s*\('
    ]
    
    i = 0
    while i < len(lines):
        line = lines[i].strip()
        
        if not line or line.startswith(('//','#')):
            i += 1
            continue
        
        # Check for function start
        function_match = None
        for pattern in function_patterns:
            match = re.match(pattern, line, re.IGNORECASE)
            if match:
                function_match = match
                break
        
        if function_match:
            function_name = function_match.group(1)
            closing_brace_line = find_function_closing_brace(i, lines)
            
            if closing_brace_line is not None:
                functions.append({
                    'start_line': i,
                    'end_line': closing_brace_line,
                    'name': function_name
                })
                i = closing_brace_line + 1
            else:
                i += 1
        else:
            i += 1
    
    return functions


def find_function_closing_brace(start_line: int, lines: List[str]) -> Optional[int]:
    """Find the closing brace line for a function."""
    brace_count = 0
    function_started = False
    
    for i in range(start_line, len(lines)):
        line = lines[i].strip()
        
        if not line or line.startswith(('//','#')):
            continue
        
        # Simple brace counting (could be enhanced to handle strings/comments)
        for char in line:
            if char == '{':
                brace_count += 1
                function_started = True
            elif char == '}' and function_started:
                brace_count -= 1
                if brace_count == 0:
                    return i
    
    return None


def create_smart_chunks(lines: List[str], function_boundaries: List[Dict[str, Any]], 
                       target_chunk_size: int, total_lines: int) -> List[Dict[str, Any]]:
    """Create chunks that respect function boundaries."""
    chunks = []
    current_pos = 0
    functions = sorted(function_boundaries, key=lambda f: f['start_line'])
    
    while current_pos < total_lines:
        # Calculate chunk end position
        initial_end_pos = min(current_pos + target_chunk_size - 1, total_lines - 1)
        
        # Find functions that would be split by this chunk boundary
        relevant_functions = [f for f in functions 
                            if (current_pos <= f['start_line'] <= initial_end_pos) or
                               (f['start_line'] < current_pos and f['end_line'] and f['end_line'] >= current_pos)]
        
        # Extend chunk to complete functions if reasonable
        final_end_pos = initial_end_pos
        if relevant_functions:
            for func in relevant_functions:
                if func['end_line'] and func['end_line'] <= initial_end_pos + 300:  # Max extension
                    final_end_pos = max(final_end_pos, func['end_line'])
        
        # Create chunk
        chunk = {
            'start_line': current_pos + 1,  # Convert to 1-based
            'end_line': final_end_pos + 1,  # Convert to 1-based
            'actual_size': final_end_pos - current_pos + 1,
            'total_lines': total_lines,
            'code': '\n'.join(lines[current_pos:final_end_pos + 1])
        }
        chunks.append(chunk)
        
        current_pos = final_end_pos + 1
    
    return chunks


def chunk_code(code: str, chunk_size: int = 500) -> List[Dict[str, Any]]:
    """Smart PHP-aware chunking using PHP tokenizer for accurate function detection."""
    lines = code.split('\n')
    total_lines = len(lines)
    
    if total_lines <= chunk_size:
        return [{
            'start_line': 1,
            'end_line': total_lines,
            'actual_size': total_lines,
            'total_lines': total_lines,
            'code': code
        }]
    
    # Get function boundaries using smart parsing
    function_boundaries = find_function_boundaries(code, lines)
    
    # Create chunks based on function boundaries
    return create_smart_chunks(lines, function_boundaries, chunk_size, total_lines)


def ensure_directory(path: Path) -> Path:
    """Ensure directory exists, create if it doesn't."""
    path.mkdir(parents=True, exist_ok=True)
    return path

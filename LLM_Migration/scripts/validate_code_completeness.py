"""
Code Completeness & Structural Preservation Validation (Secondary Robustness Diagnostic)

Part of the PCE (Pinned Contract Evaluation) framework's anti-gaming diagnostics.
Validates structural integrity of LLM-migrated PHP code against originals.

Secondary metric (Table 10, §Structural Preservation) that measures code preservation
dimensions: Line/Function/Class retention rates, body preservation, signature stability.
Demonstrates that higher obligation discharge (primary PCE metric) does not guarantee
code structure preservation—critical signal that models don't achieve correctness by
deletion or aggressive rewriting.

Metrics computed:
1. Retention rates: Lines, Functions, Classes (%)
2. Body preservation: Token-level body content fidelity (%)
3. Signature stability: Declaration signature matching
4. Anti-gaming signals: Trivial stubs, empty bodies (rates)

Usage:
    python scripts/validate_code_completeness.py <model_name>
    python scripts/validate_code_completeness.py gpt_5_codex
    python scripts/validate_code_completeness.py all
"""

import os
import sys
import subprocess
import json
import re
from pathlib import Path
from typing import Dict, List, Tuple, Set, Optional, Any
from datetime import datetime
from collections import Counter
import csv
import math
import difflib

# Add parent directories to path for config import
sys.path.insert(0, str(Path(__file__).parent.parent.parent))
from config import SELECTED_100_FILES_DIR, LLM_NEW_VERSION_DIR, LLM_MIGRATION_SUBDIR, DATASET_NAME


class CompletenessValidator:
    """ code completeness validator (token-based, namespace-aware)."""

    def __init__(self, model_name: str):
        self.model_name = model_name
        self.original_base = SELECTED_100_FILES_DIR
        self.migrated_base = LLM_NEW_VERSION_DIR / model_name
        self.output_dir = LLM_MIGRATION_SUBDIR / "outputs" / "validation_reports" / model_name
        self.output_dir.mkdir(parents=True, exist_ok=True)

        self.categories = [
            "extra_large_1000_plus",
            "large_500_1000",
            "medium_201_500",
            "small_1_200",
        ]

        # Index migrated files by filename (recursive) for robustness (some pipelines nest outputs).
        self._migrated_index: Dict[str, Path] = self._build_migrated_index(self.migrated_base)

        self.results = {
            "model": model_name,
            "timestamp": datetime.now().isoformat(),
            "methodology": {
                "token_analysis": {
                    "token_source": "PHP token_get_all() via CLI",
                    "distribution_similarity": "Cosine similarity on token-type frequencies (excluding whitespace/comments)",
                    "sequence_similarity": "Normalized token-sequence similarity (difflib SequenceMatcher ratio)",
                    "normalization": "Identifiers/vars/literals normalized; whitespace/comments removed",
                },
                "structure_analysis": {
                    "inventory_extraction": "Token-based parser (namespace + class scope aware)",
                    "inventories": "Functions, classes/interfaces/traits, methods (with basic modifiers)",
                    "set_similarity": "Jaccard; also precision/recall/F1",
                },
                "metrics": {
                    "loc": "Token-derived code/comment/blank lines + total lines",
                    "complexity": "Approx cyclomatic indicators via decision-point token counts",
                    "body_preservation": "Per function/method body token counts aligned by fully-qualified name (excludes procedural files with no functions)",
                    "signature_stability": "Parameter count and modifier preservation (excludes procedural files with no functions)",
                },
                "similarity": {
                    "jaccard": "|A∩B|/|A∪B|",
                    "cosine": "Vector cosine for token distributions",
                    "sequence": "SequenceMatcher ratio on normalized token stream",
                },
                "notes": [
                    "Coverage requires a runnable project + test suite; this script reports body preservation as a proxy when tests are unavailable.",
                    "All failures are recorded per-file in an 'errors' field; no silent fallbacks.",
                ],
            },
            "summary": {
                "total_files": 0,
                "files_analyzed": 0,
                "missing_files": 0,
                "avg_token_preservation": 0.0,
                "avg_function_preservation": 0.0,
                "avg_class_preservation": 0.0,
                "avg_line_preservation": 0.0,
                "avg_structural_similarity": 0.0,
                # Anti-gaming metrics (excludes procedural files with no functions)
                "avg_signature_stability": 0.0,
                "avg_body_preservation": 0.0,
                "avg_trivial_stub_rate": 0.0,
                "avg_empty_body_rate": 0.0,
                "files_with_functions": 0,  # Count of non-procedural files
            },
            "files": [],
        }

    # ---------------------------
    # Migration indexing
    # ---------------------------
    def _build_migrated_index(self, migrated_base: Path) -> Dict[str, Path]:
        index: Dict[str, Path] = {}
        if not migrated_base.exists():
            return index
        for p in migrated_base.rglob("*.php"):
            # Prefer the shallowest match if duplicates exist
            if p.name not in index:
                index[p.name] = p
            else:
                try:
                    if len(p.parts) < len(index[p.name].parts):
                        index[p.name] = p
                except Exception:
                    pass
        return index

    def _resolve_migrated_path(self, filename: str) -> Optional[Path]:
        direct = self.migrated_base / filename
        if direct.exists():
            return direct
        return self._migrated_index.get(filename)

    def _resolve_migrated_path_by_rel(self, original_path: Path) -> Optional[Path]:
        """
        Resolve migrated file by relative path first (safer), then fallback to basename.
        Returns (path, used_fallback)
        """
        try:
            rel = original_path.relative_to(self.original_base)
        except Exception:
            rel = Path(original_path.name)

        candidate = self.migrated_base / rel
        if candidate.exists():
            return candidate

        # fallback to filename index
        return self._resolve_migrated_path(original_path.name)

    # ---------------------------
    # PHP token extraction
    # ---------------------------
    def extract_php_tokens(self, file_path: Path) -> List[Dict]:
        """
        Extract PHP tokens using php -r with token_get_all().

        Returns:
            List of token dicts: {'type': ..., 'content': ..., 'line': int}
        """
        if not file_path.exists():
            return []

        # Use argv[1] to avoid path-escaping issues (quotes, backslashes, etc).
        php_script = r"""
$path = $argv[1];
$content = @file_get_contents($path);
if ($content === false) { echo "[]"; exit(0); }
$tokens = token_get_all($content);
$result = [];
foreach ($tokens as $token) {
    if (is_array($token)) {
        $result[] = ['type' => token_name($token[0]), 'content' => $token[1], 'line' => $token[2]];
    } else {
        // Single-char tokens don't carry line info; approximate using -1 (we handle later where needed)
        $result[] = ['type' => 'CHAR', 'content' => $token, 'line' => -1];
    }
}
echo json_encode($result, JSON_UNESCAPED_SLASHES);
"""
        try:
            result = subprocess.run(
                ["php", "-r", php_script, str(file_path)],
                capture_output=True,
                text=True,
                timeout=30,
            )
            if result.returncode == 0 and result.stdout:
                return json.loads(result.stdout)
            return []
        except Exception:
            return []

    # ---------------------------
    # Token utilities / normalization
    # ---------------------------
    _IGNORE_TOKEN_TYPES = {"T_WHITESPACE", "T_COMMENT", "T_DOC_COMMENT"}

    def analyze_token_distribution(self, tokens: List[Dict]) -> Dict[str, int]:
        token_types = [
            t["type"]
            for t in tokens
            if t.get("type") not in self._IGNORE_TOKEN_TYPES
        ]
        return dict(Counter(token_types))

    def calculate_token_similarity(self, orig_tokens: List[Dict], mig_tokens: List[Dict]) -> float:
        """Cosine similarity of token-type distributions (0..1)."""
        orig_dist = self.analyze_token_distribution(orig_tokens)
        mig_dist = self.analyze_token_distribution(mig_tokens)

        if not orig_dist and not mig_dist:
            return 1.0
        if not orig_dist or not mig_dist:
            return 0.0

        all_types = set(orig_dist.keys()) | set(mig_dist.keys())
        vec1 = [orig_dist.get(t, 0) for t in all_types]
        vec2 = [mig_dist.get(t, 0) for t in all_types]

        dot_product = sum(a * b for a, b in zip(vec1, vec2))
        magnitude1 = math.sqrt(sum(a * a for a in vec1))
        magnitude2 = math.sqrt(sum(b * b for b in vec2))
        if magnitude1 == 0 or magnitude2 == 0:
            return 0.0
        return dot_product / (magnitude1 * magnitude2)

    def _normalized_token_stream(self, tokens: List[Dict]) -> List[str]:
        """
        Normalize token stream for sequence similarity:
          - Remove whitespace/comments
          - Normalize identifiers/variables/literals
          - Keep keywords/operators as token types or chars
        """
        norm: List[str] = []
        for t in tokens:
            tt = t.get("type")
            if tt in self._IGNORE_TOKEN_TYPES:
                continue
            content = t.get("content", "")
            if tt in {"T_STRING", "T_NAME_QUALIFIED", "T_NAME_FULLY_QUALIFIED", "T_NAME_RELATIVE"}:
                norm.append("NAME")
            elif tt == "T_VARIABLE":
                norm.append("VAR")
            elif tt in {"T_LNUMBER", "T_DNUMBER"}:
                norm.append("NUM")
            elif tt == "T_CONSTANT_ENCAPSED_STRING":
                norm.append("STR")
            elif tt == "T_ENCAPSED_AND_WHITESPACE":
                norm.append("STR_PART")
            elif tt == "CHAR":
                # Keep structural chars; normalize stringy chars minimally
                if content.strip() == "":
                    continue
                norm.append(content)
            else:
                # Keep token types for PHP keywords/operators
                norm.append(tt)
        return norm

    def _is_trivial_stub(self, body_token_stream: List[str]) -> bool:
        """
        Detect trivial/stub bodies that are non-empty but semantically suspicious.
        Examples: return null/false/[]; throw Exception("TODO"); die/exit;
        """
        if len(body_token_stream) <= 3:
            return True

        # Common trivial patterns: return literal/var; throw; die/exit
        s = body_token_stream
        # "return X ;" - very short return statements
        if len(s) <= 8 and "T_RETURN" in s:
            return True
        # throw/die/exit with minimal content
        if len(s) <= 10 and ("T_THROW" in s or "T_EXIT" in s):
            return True
        return False

    def calculate_token_sequence_similarity(self, orig_tokens: List[Dict], mig_tokens: List[Dict]) -> float:
        """Sequence similarity on normalized token stream (difflib ratio; 0..1)."""
        a = self._normalized_token_stream(orig_tokens)
        b = self._normalized_token_stream(mig_tokens)
        if not a and not b:
            return 1.0
        if not a or not b:
            return 0.0
        return difflib.SequenceMatcher(a=a, b=b, autojunk=False).ratio()

    # ---------------------------
    # Token-based PHP structure parsing
    # ---------------------------
    def _iter_sig_tokens(self, tokens: List[Dict]):
        """Yield tokens ignoring whitespace/comments."""
        for t in tokens:
            if t.get("type") in self._IGNORE_TOKEN_TYPES:
                continue
            yield t

    def _parse_php_structure(self, tokens: List[Dict]) -> Dict[str, Any]:
        """
        Parse namespace, classes, functions, methods, and body token sizes from token stream.

        Returns dict:
          - namespace: str
          - classes: set[str] (FQCN)
          - functions: dict[fqname] -> info
          - methods: dict[fqmethod] -> info
          - complexity_points: int
          - code_lines: set[int] (token-derived executable-ish lines)
        """
        namespace = ""
        classes: Set[str] = set()
        functions: Dict[str, Dict[str, Any]] = {}
        methods: Dict[str, Dict[str, Any]] = {}

        # Complexity points (decision points)
        decision_types = {
            "T_IF", "T_ELSEIF", "T_FOR", "T_FOREACH", "T_WHILE", "T_DO",
            "T_CASE", "T_CATCH", "T_COALESCE", "T_BOOLEAN_AND", "T_BOOLEAN_OR",
            "T_LOGICAL_AND", "T_LOGICAL_OR", "T_LOGICAL_XOR", "T_MATCH",
        }

        # For code line derivation
        code_lines: Set[int] = set()

        # For scope tracking
        class_stack: List[Dict[str, Any]] = []  # {name, fqcn, brace_depth_at_start}
        brace_depth = 0

        sig_tokens = list(self._iter_sig_tokens(tokens))
        n = len(sig_tokens)

        def current_fqcn() -> Optional[str]:
            return class_stack[-1]["fqcn"] if class_stack else None

        def qualify_name(name: str) -> str:
            if namespace:
                return namespace + "\\" + name
            return name

        def prev_nontrivia_index(i: int) -> int:
            return i - 1

        def scan_modifiers_before(i: int) -> Dict[str, bool]:
            """
            Scan backwards from token index i (where sig_tokens[i] is T_FUNCTION)
            within the same "statement-ish" region to find modifiers.
            """
            mods = {"public": False, "protected": False, "private": False, "static": False, "abstract": False, "final": False}
            j = i - 1
            # Stop at common statement boundaries
            stop_chars = {";", "{", "}", "(", ")", ","}
            stop_types = {"T_FUNCTION", "T_CLASS", "T_INTERFACE", "T_TRAIT", "T_NAMESPACE"}
            while j >= 0:
                t = sig_tokens[j]
                tt = t["type"]
                c = t.get("content", "")
                if tt == "CHAR" and c in stop_chars:
                    break
                if tt in stop_types:
                    break
                if tt == "T_PUBLIC":
                    mods["public"] = True
                elif tt == "T_PROTECTED":
                    mods["protected"] = True
                elif tt == "T_PRIVATE":
                    mods["private"] = True
                elif tt == "T_STATIC":
                    mods["static"] = True
                elif tt == "T_ABSTRACT":
                    mods["abstract"] = True
                elif tt == "T_FINAL":
                    mods["final"] = True
                j -= 1
            return mods

        def visibility_label(mods: Dict[str, bool]) -> str:
            if mods["public"]:
                return "public"
            if mods["protected"]:
                return "protected"
            if mods["private"]:
                return "private"
            return ""

        def count_params(start_idx: int) -> Tuple[int, int]:
            """
            Count parameters from '(' at start_idx until matching ')'.
            Returns (param_count, end_idx).
            """
            depth = 0
            params = 0
            i2 = start_idx
            seen_var = False
            while i2 < n:
                t = sig_tokens[i2]
                tt = t["type"]
                c = t.get("content", "")
                if tt == "CHAR" and c == "(":
                    depth += 1
                elif tt == "CHAR" and c == ")":
                    depth -= 1
                    if depth == 0:
                        return params, i2
                elif depth == 1:
                    if tt == "T_VARIABLE":
                        # A reasonably robust param signal
                        params += 1
                i2 += 1
            return params, start_idx

        def measure_body_tokens(open_brace_idx: int) -> Tuple[int, int, List[str]]:
            """
            Measure non-trivia tokens within a function/method body starting at '{' index.
            Returns (body_token_count, end_brace_idx, normalized_body_stream).
            """
            depth = 0
            body_count = 0
            body_stream: List[str] = []
            i2 = open_brace_idx
            while i2 < n:
                t = sig_tokens[i2]
                tt = t["type"]
                c = t.get("content", "")
                if tt == "CHAR" and c == "{":
                    depth += 1
                    if depth == 1:
                        i2 += 1
                        continue
                elif tt == "CHAR" and c == "}":
                    depth -= 1
                    if depth == 0:
                        return body_count, i2, body_stream
                else:
                    if depth >= 1:
                        # inside body
                        body_count += 1
                        # Normalize for stub detection (same logic as _normalized_token_stream)
                        if tt in {"T_STRING", "T_NAME_QUALIFIED", "T_NAME_FULLY_QUALIFIED", "T_NAME_RELATIVE"}:
                            body_stream.append("NAME")
                        elif tt == "T_VARIABLE":
                            body_stream.append("VAR")
                        elif tt in {"T_LNUMBER", "T_DNUMBER"}:
                            body_stream.append("NUM")
                        elif tt == "T_CONSTANT_ENCAPSED_STRING":
                            body_stream.append("STR")
                        elif tt == "CHAR":
                            if c.strip():
                                body_stream.append(c)
                        else:
                            body_stream.append(tt)
                i2 += 1
            return body_count, open_brace_idx, body_stream

        # ---- Parse structure + complexity + code_lines ----
        namespace = ""
        classes = set()
        functions = {}
        methods = {}
        code_lines = set()
        class_stack = []
        brace_depth = 0
        complexity_points = 0

        i = 0
        while i < n:
            t = sig_tokens[i]
            tt = t["type"]
            c = t.get("content", "")
            line = t.get("line", -1)

            # code lines: any non-trivia token that isn't just PHP open/close tags counts as code-ish
            if line and line > 0:
                if tt not in {"T_OPEN_TAG", "T_CLOSE_TAG"}:
                    code_lines.add(line)

            # complexity
            if tt in decision_types:
                complexity_points += 1
            # ternary '?'
            if tt == "CHAR" and c == "?":
                complexity_points += 1

            # braces and class stack management
            if tt == "CHAR":
                if c == "{":
                    brace_depth += 1
                elif c == "}":
                    brace_depth -= 1
                    while class_stack and brace_depth < class_stack[-1]["brace_depth_at_start"]:
                        class_stack.pop()

            # namespace parsing
            if tt == "T_NAMESPACE":
                # gather tokens until ';' or '{'
                j = i + 1
                parts: List[str] = []
                while j < n:
                    tj = sig_tokens[j]
                    ttj = tj["type"]
                    cj = tj.get("content", "")
                    if ttj == "CHAR" and cj in {";", "{"}:
                        break
                    if ttj in {"T_STRING", "T_NAME_QUALIFIED", "T_NAME_FULLY_QUALIFIED", "T_NAME_RELATIVE"}:
                        parts.append(cj)
                    elif ttj == "T_NS_SEPARATOR":
                        parts.append("\\")
                    j += 1
                ns = "".join(parts).strip()
                namespace = ns.lstrip("\\")
                i = j
                continue

            # class/interface/trait parsing
            if tt in {"T_CLASS", "T_INTERFACE", "T_TRAIT"}:
                # next T_STRING is the name (skip anonymous class: "new class")
                j = i + 1
                class_name = None
                while j < n:
                    tj = sig_tokens[j]
                    ttj = tj["type"]
                    cj = tj.get("content", "")
                    # Skip "T_WHITESPACE" already removed
                    if ttj == "T_STRING":
                        class_name = cj
                        break
                    # Anonymous class: "new class" -> token stream has T_CLASS then '{' without a T_STRING sometimes.
                    if ttj == "CHAR" and cj == "{":
                        break
                    j += 1

                if class_name:
                    fqcn = (namespace + "\\" + class_name) if namespace else class_name
                    classes.add(fqcn)

                    # Find opening brace '{' to determine scope start depth
                    k = j
                    while k < n:
                        tk = sig_tokens[k]
                        ttk = tk["type"]
                        ck = tk.get("content", "")
                        if ttk == "CHAR" and ck == "{":
                            brace_depth_at_start = brace_depth + 1  # after '{' is processed
                            # Push and then let brace tracking handle pops
                            class_stack.append({"name": class_name, "fqcn": fqcn, "brace_depth_at_start": brace_depth_at_start})
                            break
                        elif ttk == "CHAR" and ck == ";":
                            break
                        k += 1
                i = j
                continue

            # function/method parsing
            if tt == "T_FUNCTION":
                mods = scan_modifiers_before(i)
                vis = visibility_label(mods)
                is_static = mods["static"]

                # Determine if named or anonymous (closure)
                j = i + 1
                byref = False
                # skip possible '&'
                if j < n and sig_tokens[j]["type"] == "CHAR" and sig_tokens[j].get("content") == "&":
                    byref = True
                    j += 1

                # next significant token:
                name = None
                if j < n and sig_tokens[j]["type"] == "T_STRING":
                    name = sig_tokens[j].get("content")

                # If anonymous, skip
                if not name:
                    i = j
                    continue

                # find '('
                k = j + 1
                while k < n:
                    tk = sig_tokens[k]
                    if tk["type"] == "CHAR" and tk.get("content") == "(":
                        break
                    k += 1

                param_count, end_paren_idx = (0, k)
                if k < n and sig_tokens[k]["type"] == "CHAR" and sig_tokens[k].get("content") == "(":
                    param_count, end_paren_idx = count_params(k)

                # Find body start '{' or ';' after signature
                m = end_paren_idx + 1
                has_body = False
                body_tokens = 0
                body_stream: List[str] = []
                start_line = t.get("line", -1)
                end_line = start_line
                while m < n:
                    tm = sig_tokens[m]
                    ttm = tm["type"]
                    cm = tm.get("content", "")
                    if ttm == "CHAR" and cm == "{":
                        has_body = True
                        body_tokens, end_brace_idx, body_stream = measure_body_tokens(m)
                        # Estimate end line from closing brace token if it has line info
                        end_line = sig_tokens[end_brace_idx].get("line", end_line)
                        m = end_brace_idx
                        break
                    if ttm == "CHAR" and cm == ";":
                        has_body = False
                        break
                    m += 1

                fqcn = current_fqcn()
                if fqcn:
                    # Method
                    fqmethod = f"{fqcn}::{name}"
                    methods[fqmethod] = {
                        "name": name,
                        "class": fqcn,
                        "visibility": vis,
                        "static": bool(is_static),
                        "byref": bool(byref),
                        "params": int(param_count),
                        "has_body": bool(has_body),
                        "body_tokens": int(body_tokens),
                        "body_stream": body_stream,
                        "start_line": int(start_line) if start_line else -1,
                        "end_line": int(end_line) if end_line else -1,
                    }
                else:
                    # Global function (namespace-qualified)
                    fqfn = qualify_name(name)
                    functions[fqfn] = {
                        "name": name,
                        "namespace": namespace,
                        "byref": bool(byref),
                        "params": int(param_count),
                        "has_body": bool(has_body),
                        "body_tokens": int(body_tokens),
                        "body_stream": body_stream,
                        "start_line": int(start_line) if start_line else -1,
                        "end_line": int(end_line) if end_line else -1,
                    }

                i = m
                continue

            i += 1

        # cyclomatic-ish complexity: classic cyclomatic = 1 + decisions
        cyclomatic_approx = 1 + complexity_points if (n > 0) else 0

        return {
            "namespace": namespace,
            "classes": classes,
            "functions": functions,
            "methods": methods,
            "complexity_points": complexity_points,
            "cyclomatic_approx": cyclomatic_approx,
            "code_lines": code_lines,
        }

    # ---------------------------
    # Backwards-compatible API methods (now token-based)
    # ---------------------------
    def extract_functions(self, file_path: Path) -> Set[str]:
        if not file_path.exists():
            return set()
        tokens = self.extract_php_tokens(file_path)
        st = self._parse_php_structure(tokens)
        return set(st["functions"].keys())

    def extract_classes(self, file_path: Path) -> Set[str]:
        if not file_path.exists():
            return set()
        tokens = self.extract_php_tokens(file_path)
        st = self._parse_php_structure(tokens)
        return set(st["classes"])

    def extract_methods(self, file_path: Path) -> Set[str]:
        """
        Returns:
            Set of method names with visibility (public/private/protected) where available.

        Note: Kept compatible with prior output shape by including 'vis::name' where possible,
        but now uses fully-qualified 'Class::method' for uniqueness.
        """
        if not file_path.exists():
            return set()
        tokens = self.extract_php_tokens(file_path)
        st = self._parse_php_structure(tokens)
        out: Set[str] = set()
        for fqmethod, info in st["methods"].items():
            vis = info.get("visibility", "")
            if vis:
                out.add(f"{vis}::{fqmethod}")
            else:
                out.add(f"{fqmethod}")
        return out

    # ---------------------------
    # Line counting (token-derived for accuracy)
    # ---------------------------
    def count_lines(self, file_path: Path) -> Dict[str, int]:
        """
        Count different types of lines (more accurate than prefix heuristics):
          - total lines: by reading file
          - blank lines: empty/whitespace-only
          - comment lines: lines containing only comment/doc-comment tokens
          - code lines: lines containing at least one non-trivia token (excluding open/close tags)
        """
        if not file_path.exists():
            return {"total": 0, "code": 0, "comment": 0, "blank": 0}

        try:
            content = file_path.read_text(encoding="utf-8", errors="ignore")
            lines = content.split("\n")
            total = len(lines)
            blank = sum(1 for line in lines if line.strip() == "")

            tokens = self.extract_php_tokens(file_path)
            if not tokens:
                # fallback: naive
                return {"total": total, "code": max(total - blank, 0), "comment": 0, "blank": blank}

            # Build per-line flags
            line_has_code: Dict[int, bool] = {}
            line_has_comment: Dict[int, bool] = {}

            for t in tokens:
                line = t.get("line", -1)
                if not line or line < 1:
                    continue
                tt = t.get("type")
                if tt in {"T_OPEN_TAG", "T_CLOSE_TAG"}:
                    continue
                if tt in {"T_COMMENT", "T_DOC_COMMENT"}:
                    line_has_comment[line] = True
                    continue
                if tt == "T_WHITESPACE":
                    continue
                # Any other token => code-ish
                line_has_code[line] = True

            # comment-only lines = has_comment and not has_code
            comment_only = 0
            code_lines = 0
            for ln in range(1, total + 1):
                hc = line_has_comment.get(ln, False)
                hcode = line_has_code.get(ln, False)
                if hcode:
                    code_lines += 1
                elif hc:
                    comment_only += 1

            return {
                "total": total,
                "code": code_lines,
                "comment": comment_only,
                "blank": blank,
            }
        except Exception:
            return {"total": 0, "code": 0, "comment": 0, "blank": 0}

    # ---------------------------
    # Similarity / preservation helpers
    # ---------------------------
    def calculate_jaccard_similarity(self, set1: Set, set2: Set) -> float:
        if not set1 and not set2:
            return 1.0
        if not set1 or not set2:
            return 0.0
        intersection = len(set1 & set2)
        union = len(set1 | set2)
        return intersection / union if union > 0 else 0.0

    def calculate_preservation_rate(self, original_count: int, migrated_count: int) -> float:
        if original_count == 0:
            return 100.0 if migrated_count == 0 else 0.0
        return (migrated_count / original_count) * 100.0

    def _precision_recall_f1(self, gold: Set[str], pred: Set[str]) -> Dict[str, float]:
        if not gold and not pred:
            return {"precision": 1.0, "recall": 1.0, "f1": 1.0}
        if not pred:
            return {"precision": 0.0, "recall": 0.0, "f1": 0.0}
        tp = len(gold & pred)
        precision = tp / len(pred) if pred else 0.0
        recall = tp / len(gold) if gold else 0.0
        f1 = (2 * precision * recall / (precision + recall)) if (precision + recall) else 0.0
        return {"precision": precision, "recall": recall, "f1": f1}

    def _body_preservation(self, orig_map: Dict[str, Dict[str, Any]], mig_map: Dict[str, Dict[str, Any]]) -> Dict[str, Any]:
        """
        Compute body token preservation for matching declarations.
        Returns:
                    - matched: int (matched declarations by name)
                    - matched_bodies: int (matched declarations where original has a body)
          - avg_ratio: float (0..100)
          - empty_body_in_migrated: int
          - empty_body_rate: float (0..1)
          - trivial_stub_count: int
          - trivial_stub_rate: float (0..1)
        """
        common = set(orig_map.keys()) & set(mig_map.keys())
        if not common:
            return {"matched": 0, "matched_bodies": 0, "avg_ratio": 0.0, "empty_body_in_migrated": 0, "empty_body_rate": 0.0, "trivial_stub_count": 0, "trivial_stub_rate": 0.0}

        ratios: List[float] = []
        empty = 0
        trivial_stubs = 0
        for k in common:
            o = orig_map[k]
            m = mig_map[k]
            o_bt = int(o.get("body_tokens", 0)) if o.get("has_body") else 0
            m_bt = int(m.get("body_tokens", 0)) if m.get("has_body") else 0
            # If original has no body (abstract/interface), skip from ratio (not meaningful)
            if not o.get("has_body"):
                continue
            if m.get("has_body") and m_bt <= 1:
                empty += 1
            # Check for trivial stubs
            if m.get("has_body") and m_bt > 1:
                body_stream = m.get("body_stream", [])
                if self._is_trivial_stub(body_stream):
                    trivial_stubs += 1
            if o_bt == 0:
                ratios.append(100.0 if m_bt == 0 else 0.0)
            else:
                ratios.append(min((m_bt / o_bt) * 100.0, 200.0))  # cap to reduce outlier blowups

        if not ratios:
            # Only abstract/interface matches
            return {"matched": len(common), "matched_bodies": 0, "avg_ratio": 100.0, "empty_body_in_migrated": 0, "empty_body_rate": 0.0, "trivial_stub_count": 0, "trivial_stub_rate": 0.0}

        avg_ratio = sum(ratios) / len(ratios)
        empty_rate = empty / len(ratios) if ratios else 0.0
        stub_rate = trivial_stubs / len(ratios) if ratios else 0.0
        return {"matched": len(common), "matched_bodies": len(ratios), "avg_ratio": avg_ratio, "empty_body_in_migrated": empty, "empty_body_rate": empty_rate, "trivial_stub_count": trivial_stubs, "trivial_stub_rate": stub_rate}

    def _signature_stability(self, orig_map: Dict[str, Dict[str, Any]], mig_map: Dict[str, Dict[str, Any]], is_method: bool) -> Dict[str, float]:
        """
        Analyze signature stability for matched functions/methods.
        Returns rates for: params unchanged, modifiers unchanged, overall stability.
        """
        common = set(orig_map.keys()) & set(mig_map.keys())
        if not common:
            return {"matched": 0, "params_unchanged": 0.0, "mods_unchanged": 0.0, "overall": 0.0}

        params_same = 0
        mods_same = 0
        for k in common:
            o = orig_map[k]
            m = mig_map[k]
            if int(o.get("params", 0)) == int(m.get("params", 0)):
                params_same += 1

            if is_method:
                same = (
                    str(o.get("visibility", "")) == str(m.get("visibility", "")) and
                    bool(o.get("static", False)) == bool(m.get("static", False)) and
                    bool(o.get("byref", False)) == bool(m.get("byref", False))
                )
            else:
                same = bool(o.get("byref", False)) == bool(m.get("byref", False))
            if same:
                mods_same += 1

        n = len(common)
        params_rate = params_same / n
        mods_rate = mods_same / n
        overall = 0.7 * params_rate + 0.3 * mods_rate
        return {"matched": n, "params_unchanged": params_rate, "mods_unchanged": mods_rate, "overall": overall}

    # ---------------------------
    # Validation
    # ---------------------------
    def validate_file_pair(self, category: str, original_path: Path) -> Dict:
        filename = original_path.name
        migrated_path = self._resolve_migrated_path_by_rel(original_path)

        result = {
            "filename": filename,
            "category": category,
            "original_path": str(original_path),
            "migrated_path": str(migrated_path) if migrated_path and migrated_path.exists() else None,
            "exists": bool(migrated_path and migrated_path.exists()),
            "metrics": {},
            "errors": [],
        }

        if not migrated_path or not migrated_path.exists():
            return result

        # Check if relative path matched or fallback was used (collision risk)
        try:
            rel = original_path.relative_to(self.original_base)
            rel_candidate = self.migrated_base / rel
            if not rel_candidate.exists():
                result["errors"].append("Migrated file matched by basename fallback (possible collision).")
        except Exception:
            pass

        # Extract tokens once per file
        try:
            orig_tokens = self.extract_php_tokens(original_path)
            mig_tokens = self.extract_php_tokens(migrated_path)
            if not orig_tokens:
                result["errors"].append("Failed to tokenize original with PHP token_get_all().")
            if not mig_tokens:
                result["errors"].append("Failed to tokenize migrated with PHP token_get_all().")
        except Exception as e:
            result["errors"].append(f"Tokenization exception: {e}")
            return result

        # Parse structure from tokens
        try:
            orig_struct = self._parse_php_structure(orig_tokens)
            mig_struct = self._parse_php_structure(mig_tokens)
        except Exception as e:
            result["errors"].append(f"Structure parsing exception: {e}")
            return result

        # Inventories
        orig_functions = set(orig_struct["functions"].keys())
        mig_functions = set(mig_struct["functions"].keys())

        orig_classes = set(orig_struct["classes"])
        mig_classes = set(mig_struct["classes"])

        orig_methods_fq = set(orig_struct["methods"].keys())
        mig_methods_fq = set(mig_struct["methods"].keys())

        # For backwards compatible "methods" set (vis::Class::method)
        def method_display_set(st: Dict[str, Any]) -> Set[str]:
            out = set()
            for fqmethod, info in st["methods"].items():
                vis = info.get("visibility", "")
                if vis:
                    out.add(f"{vis}::{fqmethod}")
                else:
                    out.add(fqmethod)
            return out

        orig_methods_disp = method_display_set(orig_struct)
        mig_methods_disp = method_display_set(mig_struct)

        # Lines and code-lines
        orig_lines = self.count_lines(original_path)
        mig_lines = self.count_lines(migrated_path)

        # Body preservation and anti-gaming metrics
        fn_body = self._body_preservation(orig_struct["functions"], mig_struct["functions"])
        m_body = self._body_preservation(orig_struct["methods"], mig_struct["methods"])
        # Combine (weighted by number of matched *bodies*)
        fn_bodies = int(fn_body.get("matched_bodies", 0))
        m_bodies = int(m_body.get("matched_bodies", 0))
        body_denom = fn_bodies + m_bodies
        
        # Determine if file is truly procedural (N/A) vs has bodies but none matched (failure)
        procedural_file = (len(orig_functions) == 0 and len(orig_methods_fq) == 0)
        orig_has_any_body = any(info.get("has_body") for info in orig_struct["functions"].values()) or \
                            any(info.get("has_body") for info in orig_struct["methods"].values())
        
        # Body preservation average
        if procedural_file or not orig_has_any_body:
            body_avg_ratio = None  # Truly N/A (procedural or all abstract/interface)
            migrated_empty_body_rate = None
            migrated_trivial_stub_rate = None
        elif body_denom > 0:
            body_avg_ratio = (
                fn_body["avg_ratio"] * fn_bodies +
                m_body["avg_ratio"] * m_bodies
            ) / body_denom
            migrated_empty_body_rate = (
                fn_body["empty_body_rate"] * fn_bodies +
                m_body["empty_body_rate"] * m_bodies
            ) / body_denom
            migrated_trivial_stub_rate = (
                fn_body["trivial_stub_rate"] * fn_bodies +
                m_body["trivial_stub_rate"] * m_bodies
            ) / body_denom
        else:
            # Original had bodies but none matched → deletion/renaming → 0% preservation
            body_avg_ratio = 0.0
            migrated_empty_body_rate = 0.0
            migrated_trivial_stub_rate = 0.0

        # Signature stability (based on matched declarations, not bodies)
        fn_sig = self._signature_stability(orig_struct["functions"], mig_struct["functions"], is_method=False)
        m_sig = self._signature_stability(orig_struct["methods"], mig_struct["methods"], is_method=True)
        
        # Compute overall signature stability weighted by matches
        sig_matched = int(fn_sig.get("matched", 0)) + int(m_sig.get("matched", 0))
        if sig_matched > 0:
            signature_stability = (
                fn_sig["overall"] * fn_sig["matched"] +
                m_sig["overall"] * m_sig["matched"]
            ) / sig_matched
        else:
            signature_stability = None

        # Similarities
        token_distribution_similarity = self.calculate_token_similarity(orig_tokens, mig_tokens)
        token_sequence_similarity = self.calculate_token_sequence_similarity(orig_tokens, mig_tokens)

        fn_jacc = self.calculate_jaccard_similarity(orig_functions, mig_functions)
        cls_jacc = self.calculate_jaccard_similarity(orig_classes, mig_classes)
        mth_jacc = self.calculate_jaccard_similarity(orig_methods_fq, mig_methods_fq)

        fn_prf = self._precision_recall_f1(orig_functions, mig_functions)
        cls_prf = self._precision_recall_f1(orig_classes, mig_classes)
        mth_prf = self._precision_recall_f1(orig_methods_fq, mig_methods_fq)

        # Complexity preservation (approx)
        orig_cyc = int(orig_struct.get("cyclomatic_approx", 0))
        mig_cyc = int(mig_struct.get("cyclomatic_approx", 0))
        complexity_preservation = self.calculate_preservation_rate(orig_cyc, mig_cyc) if orig_cyc > 0 else (100.0 if mig_cyc == 0 else 0.0)

        # Calculate metrics (keep original keys + add research upgrades)
        metrics = {
            # Token metrics
            "token_count_original": len(orig_tokens),
            "token_count_migrated": len(mig_tokens),
            "token_preservation_rate": self.calculate_preservation_rate(len(orig_tokens), len(mig_tokens)),
            "token_distribution_similarity": token_distribution_similarity,
            "token_sequence_similarity": token_sequence_similarity,

            # Function metrics
            "function_count_original": len(orig_functions),
            "function_count_migrated": len(mig_functions),
            "function_preservation_rate": self.calculate_preservation_rate(len(orig_functions), len(mig_functions)),
            "function_jaccard_similarity": fn_jacc,
            "function_precision": fn_prf["precision"],
            "function_recall": fn_prf["recall"],
            "function_f1": fn_prf["f1"],
            "missing_functions": sorted(list(orig_functions - mig_functions)),
            "added_functions": sorted(list(mig_functions - orig_functions)),

            # Class metrics
            "class_count_original": len(orig_classes),
            "class_count_migrated": len(mig_classes),
            "class_preservation_rate": self.calculate_preservation_rate(len(orig_classes), len(mig_classes)),
            "class_jaccard_similarity": cls_jacc,
            "class_precision": cls_prf["precision"],
            "class_recall": cls_prf["recall"],
            "class_f1": cls_prf["f1"],
            "missing_classes": sorted(list(orig_classes - mig_classes)),
            "added_classes": sorted(list(mig_classes - orig_classes)),

            # Method metrics
            # (Count based on display-set for compatibility; similarity based on fully-qualified set)
            "method_count_original": len(orig_methods_disp),
            "method_count_migrated": len(mig_methods_disp),
            "method_preservation_rate": self.calculate_preservation_rate(len(orig_methods_disp), len(mig_methods_disp)),
            "method_jaccard_similarity": mth_jacc,
            "method_precision": mth_prf["precision"],
            "method_recall": mth_prf["recall"],
            "method_f1": mth_prf["f1"],

            # Line metrics (token-derived)
            "lines_total_original": orig_lines["total"],
            "lines_total_migrated": mig_lines["total"],
            "lines_code_original": orig_lines["code"],
            "lines_code_migrated": mig_lines["code"],
            "line_preservation_rate": self.calculate_preservation_rate(orig_lines["code"], mig_lines["code"]),

            # Complexity metrics (approx)
            "cyclomatic_approx_original": orig_cyc,
            "cyclomatic_approx_migrated": mig_cyc,
            "complexity_preservation_rate": complexity_preservation,

            # Body preservation metrics (None for procedural/abstract-only, 0.0 for deletion)
            "function_body_preservation_avg": fn_body["avg_ratio"] if fn_bodies > 0 else (None if procedural_file else 0.0),
            "method_body_preservation_avg": m_body["avg_ratio"] if m_bodies > 0 else (None if procedural_file else 0.0),
            "body_preservation_avg": body_avg_ratio,
            "migrated_empty_body_rate": float(migrated_empty_body_rate) if migrated_empty_body_rate is not None else None,
            "migrated_trivial_stub_rate": float(migrated_trivial_stub_rate) if migrated_trivial_stub_rate is not None else None,

            # Signature stability metrics (None if no matched declarations)
            "function_signature_stability": fn_sig["overall"],
            "method_signature_stability": m_sig["overall"],
            "signature_stability": signature_stability,

            # Structural similarity (weighted average of Jaccard similarities)
            "structural_similarity": (fn_jacc * 0.4 + cls_jacc * 0.3 + mth_jacc * 0.3),
        }

        result["metrics"] = metrics
        return result

    def get_all_original_files(self) -> List[Tuple[str, Path]]:
        files: List[Tuple[str, Path]] = []
        
        # Check if original_base exists
        if not self.original_base.exists():
            print(f"Error: Original files directory not found: {self.original_base}")
            return files
        
        # Categories for file size classification
        categories = [
            "extra_large_1000_plus",
            "large_500_1000",
            "medium_201_500",
            "small_1_200",
        ]
        
        # Look for PHP files in category subdirectories
        # Only include numbered files (001-100 benchmark files), exclude stubs like admin.php
        for category in categories:
            category_path = self.original_base / category
            if category_path.exists():
                for file_path in sorted(category_path.glob("*.php")):
                    # Only include files that start with digits (001_*, 002_*, etc)
                    if file_path.name[0].isdigit():
                        files.append((category, file_path))
        
        return files

    def validate_all(self):
        print(f"\n{'='*80}")
        print(f"Code Completeness Validation: {self.model_name}")
        print(f"{'='*80}\n")

        # Check if migrated directory exists
        if not self.migrated_base.exists():
            print(f"Error: Migrated files directory not found: {self.migrated_base}")
            print(f"Please ensure LLM migration has been run for model: {self.model_name}")
            return

        print(f"Original files: {self.original_base}")
        print(f"Migrated files: {self.migrated_base}\n")

        original_files = self.get_all_original_files()
        if not original_files:
            print(f"Error: No original PHP files found in: {self.original_base}")
            return

        self.results["summary"]["total_files"] = len(original_files)

        print(f"Analyzing {len(original_files)} file pairs...\n")
        print(f"{'File':<50} {'Status':<12}")
        print(f"{'-'*62}")

        for category, original_path in original_files:
            print(f"{original_path.name:<50}", end="")
            result = self.validate_file_pair(category, original_path)
            self.results["files"].append(result)

            if result["exists"]:
                self.results["summary"]["files_analyzed"] += 1
                print(f" {'OK':<12}")
            else:
                self.results["summary"]["missing_files"] += 1
                print(f" {'MISSING':<12}")

        self.calculate_summary_statistics()
        print(f"\n{'='*80}")
        self.print_summary()
        self.save_results()

    def calculate_summary_statistics(self):
        analyzed_files = [f for f in self.results["files"] if f["exists"]]
        if not analyzed_files:
            return

        self.results["summary"]["avg_token_preservation"] = sum(
            f["metrics"]["token_preservation_rate"] for f in analyzed_files
        ) / len(analyzed_files)

        self.results["summary"]["avg_function_preservation"] = sum(
            f["metrics"]["function_preservation_rate"] for f in analyzed_files
        ) / len(analyzed_files)

        self.results["summary"]["avg_class_preservation"] = sum(
            f["metrics"]["class_preservation_rate"] for f in analyzed_files
        ) / len(analyzed_files)

        self.results["summary"]["avg_line_preservation"] = sum(
            f["metrics"]["line_preservation_rate"] for f in analyzed_files
        ) / len(analyzed_files)

        self.results["summary"]["avg_structural_similarity"] = (
            sum(f["metrics"]["structural_similarity"] for f in analyzed_files) / len(analyzed_files) * 100.0
        )

        # Anti-gaming metrics (exclude ONLY files where ORIGINAL has no functions/methods)
        files_with_functions = [
            f for f in analyzed_files 
            if f["metrics"]["function_count_original"] > 0 or f["metrics"]["method_count_original"] > 0
        ]
        
        self.results["summary"]["files_with_functions"] = len(files_with_functions)
        
        if files_with_functions:
            # Signature stability: conditional average (excludes N/A)
            sig_vals = [f["metrics"]["signature_stability"] for f in files_with_functions 
                       if f["metrics"]["signature_stability"] is not None]
            self.results["summary"]["avg_signature_stability"] = sum(sig_vals) / len(sig_vals) if sig_vals else None
            self.results["summary"]["signature_stability_coverage_pct"] = 100.0 * len(sig_vals) / len(files_with_functions)
            
            # Body preservation: conditional average (excludes N/A, includes 0.0 for deletions)
            body_vals = [f["metrics"]["body_preservation_avg"] for f in files_with_functions 
                        if f["metrics"]["body_preservation_avg"] is not None]
            self.results["summary"]["avg_body_preservation"] = sum(body_vals) / len(body_vals) if body_vals else None
            self.results["summary"]["body_preservation_coverage_pct"] = 100.0 * len(body_vals) / len(files_with_functions)
            
            # Trivial stub rate: conditional average
            stub_vals = [f["metrics"]["migrated_trivial_stub_rate"] for f in files_with_functions 
                        if f["metrics"]["migrated_trivial_stub_rate"] is not None]
            self.results["summary"]["avg_trivial_stub_rate"] = sum(stub_vals) / len(stub_vals) if stub_vals else None
            self.results["summary"]["trivial_stub_coverage_pct"] = 100.0 * len(stub_vals) / len(files_with_functions)
            
            # Empty body rate: conditional average
            empty_vals = [f["metrics"]["migrated_empty_body_rate"] for f in files_with_functions 
                         if f["metrics"]["migrated_empty_body_rate"] is not None]
            self.results["summary"]["avg_empty_body_rate"] = sum(empty_vals) / len(empty_vals) if empty_vals else None
            self.results["summary"]["empty_body_coverage_pct"] = 100.0 * len(empty_vals) / len(files_with_functions)
        else:
            self.results["summary"]["avg_signature_stability"] = None
            self.results["summary"]["signature_stability_coverage_pct"] = 0.0
            self.results["summary"]["avg_body_preservation"] = None
            self.results["summary"]["body_preservation_coverage_pct"] = 0.0
            self.results["summary"]["avg_trivial_stub_rate"] = None
            self.results["summary"]["trivial_stub_coverage_pct"] = 0.0
            self.results["summary"]["avg_empty_body_rate"] = None
            self.results["summary"]["empty_body_coverage_pct"] = 0.0

    def print_summary(self):
        summary = self.results["summary"]

        print(f"\nCOMPLETENESS VALIDATION SUMMARY")
        print(f"{'-'*80}")
        print(f"Total files:                 {summary['total_files']}")
        print(f"Files analyzed:              {summary['files_analyzed']}")
        print(f"Missing files:               {summary['missing_files']}")

        print(f"\nAVERAGE PRESERVATION RATES:")
        print(f"  Token preservation:        {summary['avg_token_preservation']:.2f}%")
        print(f"  Function preservation:     {summary['avg_function_preservation']:.2f}%")
        print(f"  Class preservation:        {summary['avg_class_preservation']:.2f}%")
        print(f"  Line preservation:         {summary['avg_line_preservation']:.2f}%")
        body_pres = summary.get('avg_body_preservation')
        body_cov = summary.get('body_preservation_coverage_pct', 0.0)
        print(f"  Body preservation:         {body_pres:.2f}% (coverage: {body_cov:.1f}%)" if body_pres is not None else f"  Body preservation:         N/A (coverage: {body_cov:.1f}%)")

        print(f"\nANTI-GAMING METRICS (excluding {summary['files_analyzed'] - summary['files_with_functions']} procedural files):")
        print(f"  Files with functions:      {summary['files_with_functions']}")
        
        sig_stab = summary.get('avg_signature_stability')
        sig_cov = summary.get('signature_stability_coverage_pct', 0.0)
        print(f"  Signature stability:       {sig_stab:.4f} (coverage: {sig_cov:.1f}%)" if sig_stab is not None else f"  Signature stability:       N/A (coverage: {sig_cov:.1f}%)")
        
        stub_rate = summary.get('avg_trivial_stub_rate')
        stub_cov = summary.get('trivial_stub_coverage_pct', 0.0)
        print(f"  Trivial stub rate:         {stub_rate:.4f} (coverage: {stub_cov:.1f}%)" if stub_rate is not None else f"  Trivial stub rate:         N/A (coverage: {stub_cov:.1f}%)")
        
        empty_rate = summary.get('avg_empty_body_rate')
        empty_cov = summary.get('empty_body_coverage_pct', 0.0)
        print(f"  Empty body rate:           {empty_rate:.4f} (coverage: {empty_cov:.1f}%)" if empty_rate is not None else f"  Empty body rate:           N/A (coverage: {empty_cov:.1f}%)")

        print(f"\nOVERALL METRICS:")
        print(f"  Structural similarity:     {summary['avg_structural_similarity']:.2f}%")

    def save_results(self):
        json_path = self.output_dir / f"completeness_validation_{self.model_name}.json"
        with open(json_path, "w", encoding="utf-8") as f:
            json.dump(self.results, f, indent=2)
        print(f"\nDetailed JSON report: {json_path}")

        csv_path = self.output_dir / f"completeness_summary_{self.model_name}.csv"
        with open(csv_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(
                f,
                fieldnames=[
                    "filename",
                    "category",
                    "token_preservation",
                    "token_sequence_similarity",
                    "function_preservation",
                    "class_preservation",
                    "structural_similarity",
                    "body_preservation_avg",
                    "signature_stability",
                    "trivial_stub_rate",
                    "missing_functions",
                    "missing_classes",
                    "errors",
                ],
            )
            writer.writeheader()
            for file_result in self.results["files"]:
                if file_result["exists"]:
                    m = file_result["metrics"]
                    writer.writerow(
                        {
                            "filename": file_result["filename"],
                            "category": file_result["category"],
                            "token_preservation": f"{m['token_preservation_rate']:.2f}",
                            "token_sequence_similarity": f"{m.get('token_sequence_similarity', 0.0):.4f}",
                            "function_preservation": f"{m['function_preservation_rate']:.2f}",
                            "class_preservation": f"{m['class_preservation_rate']:.2f}",
                            "structural_similarity": f"{m['structural_similarity']*100:.2f}",
                            "body_preservation_avg": "N/A" if m.get('body_preservation_avg') is None else f"{m.get('body_preservation_avg', 0.0):.2f}",
                            "signature_stability": "N/A" if m.get('signature_stability') is None else f"{m.get('signature_stability', 0.0):.4f}",
                            "trivial_stub_rate": "N/A" if m.get('migrated_trivial_stub_rate') is None else f"{m.get('migrated_trivial_stub_rate', 0.0):.4f}",
                            "missing_functions": ", ".join(m["missing_functions"]),
                            "missing_classes": ", ".join(m["missing_classes"]),
                            "errors": "; ".join(file_result.get("errors", [])),
                        }
                    )
        print(f"CSV summary: {csv_path}")

        summary_path = self.output_dir / f"completeness_summary_{self.model_name}.json"
        with open(summary_path, "w", encoding="utf-8") as f:
            json.dump(
                {
                    "model": self.model_name,
                    "last_updated": self.results["timestamp"],
                    "methodology": self.results["methodology"],
                    "summary": self.results["summary"],
                },
                f,
                indent=2,
            )
        print(f"Summary report: {summary_path}")


def validate_model(model_name: str):
    validator = CompletenessValidator(model_name)
    validator.validate_all()


def validate_all_models():
    migrated_base = LLM_NEW_VERSION_DIR  # Already points to new-version directory

    if not migrated_base.exists():
        print(f"Error: Migrated files directory not found: {migrated_base}")
        return

    models = [d.name for d in migrated_base.iterdir() if d.is_dir()]

    if not models:
        print("No model directories found!")
        return

    print(f"Found {len(models)} models: {', '.join(models)}\n")

    for i, model in enumerate(models, 1):
        print(f"\n[{i}/{len(models)}] Processing {model}...")
        validate_model(model)
        print("\n" + "=" * 80 + "\n")


def main():
    if len(sys.argv) < 2:
        print("Usage: python validate_code_completeness.py <model_name|all>")
        print("\nAvailable models:")
        migrated_base = LLM_NEW_VERSION_DIR  # Already points to new-version directory
        if migrated_base.exists():
            models = [d.name for d in migrated_base.iterdir() if d.is_dir()]
            for model in models:
                print(f"  - {model}")
        print("\nOr use 'all' to validate all models")
        sys.exit(1)

    model_name = sys.argv[1]

    if model_name.lower() == "all":
        validate_all_models()
    else:
        validate_model(model_name)


if __name__ == "__main__":
    main()

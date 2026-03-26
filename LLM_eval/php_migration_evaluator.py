"""PHP migration model evaluation using PCE scoring."""

import sys
from pathlib import Path
import json
import pandas as pd
import numpy as np
import random
import re
from typing import Dict, List, Any, Optional, Iterator
from dataclasses import dataclass
import warnings

warnings.filterwarnings("ignore")

SEED = 42
np.random.seed(SEED)
random.seed(SEED)

sys.path.insert(0, str(Path(__file__).parent.parent))
from config import (  # noqa: E402
    LLM_CODE_ANALYSIS_DIR,
    LLM_EVAL_SUBDIR,
    SELECTED_100_FILES_DIR,
)

UPGRADE_RULE_PREFIXES = ("Rector\\Php",)


def is_upgrade_rule(rule_name: Any) -> bool:
    """Return True if a rule belongs to the scored Rector PHP upgrade families."""
    return isinstance(rule_name, str) and rule_name.startswith(UPGRADE_RULE_PREFIXES)


def normalize_rule_set(rules: Any) -> set[str]:
    """Convert a rule collection into a normalized set of strings."""
    if rules is None or not isinstance(rules, (list, tuple, set)):
        return set()
    return {r for r in rules if isinstance(r, str)}


def normalize_upgrade_rule_set(rules: Any) -> set[str]:
    """Convert a rule collection into a normalized set of scored upgrade rules."""
    return {r for r in normalize_rule_set(rules) if is_upgrade_rule(r)}


@dataclass
class MigrationResult:
    """Per-file PCE-aligned result."""

    file_id: int
    original_file_id: int
    filename: str
    original_obligations: int      # O_i = |𝒪_i|
    discharged_obligations: int    # S_i = |𝒪_i \ 𝓡_i|
    introduced_obligations: int    # I_i = |𝓡_i \ 𝒪_i|
    remaining_obligations: int     # R_i = |𝓡_i|
    net_change: int                # Δ_i = S_i - I_i
    discharge_rate: float          # ρ_i = S_i / O_i * 100
    size_category: str


class PHPMigrationEvaluator:
    """Evaluates PHP migration model outputs using PCE-aligned metrics."""

    def __init__(self, model_folder: Optional[str] = None):
        self.migration_reports_dir = LLM_CODE_ANALYSIS_DIR

        if model_folder is None:
            model_folders = sorted(
                [
                    d for d in self.migration_reports_dir.iterdir()
                    if d.is_dir() and not d.name.startswith(".") and d.name != "__pycache__"
                ],
                key=lambda p: p.name.lower(),
            )
            if not model_folders:
                raise ValueError(f"No model folder found in: {self.migration_reports_dir}")
            self.model_dir = model_folders[0]
        else:
            self.model_dir = self.migration_reports_dir / model_folder

        if not self.model_dir.exists():
            raise ValueError(f"Model folder not found: {self.model_dir}")

        self.model_name = self._sanitize_model_name(self.model_dir.name)
        self.model_folder_name = self.model_dir.name

        self.output_dir = LLM_EVAL_SUBDIR / self.model_folder_name
        self.output_dir.mkdir(parents=True, exist_ok=True)

        self.selection_data: Optional[pd.DataFrame] = None
        self.selection_data_detailed: List[Dict[str, Any]] = []
        self.selection_data_by_id: Dict[int, Dict[str, Any]] = {}
        self.model_data: Optional[pd.DataFrame] = None
        self.model_metadata: Dict[int, Dict[str, Any]] = {}
        self.individual_files: Dict[int, Dict[str, Any]] = {}
        self.evaluation_results: List[MigrationResult] = []
        self.skipped_error_files: List[Dict[str, Any]] = []

    def _sanitize_model_name(self, folder_name: str) -> str:
        """Convert a model folder name into a readable display name."""
        sanitized = folder_name.replace("_", " ")

        if "meta llama" in sanitized.lower():
            sanitized = sanitized.replace("meta llama llama", "Meta-Llama")
            sanitized = sanitized.replace("meta llama", "Meta-Llama")

        sanitized = sanitized.replace("llama", "Llama")
        sanitized = sanitized.replace("instruct", "Instruct")
        sanitized = sanitized.replace("gpt", "GPT")
        sanitized = sanitized.replace("claude", "Claude")
        sanitized = sanitized.replace("gemini", "Gemini")

        sanitized = re.sub(r"(\d+)\s+(\d+)\s+(\d+)([a-z])", r"\1.\2 \3\4", sanitized)
        sanitized = re.sub(r"(\d+)([a-z])", r"\1\2", sanitized.upper())

        words = sanitized.split()
        capitalized_words = []
        for word in words:
            if "B" in word.upper() and any(c.isdigit() for c in word):
                capitalized_words.append(word.upper())
            elif word.replace(".", "").isdigit():
                capitalized_words.append(word)
            else:
                capitalized_words.append(word.capitalize())

        return " ".join(capitalized_words)

    def load_data(self) -> None:
        """Load all required input artifacts."""
        selection_file = SELECTED_100_FILES_DIR / "selection_summary.csv"
        self.selection_data = pd.read_csv(selection_file)

        selection_json_file = SELECTED_100_FILES_DIR / "selection_data.json"
        with open(selection_json_file, "r", encoding="utf-8") as f:
            self.selection_data_detailed = json.load(f)

        self.selection_data_by_id = {
            int(item["file_id"]): item
            for item in self.selection_data_detailed
            if "file_id" in item
        }

        model_file = self.model_dir / "summary.csv"
        self.model_data = pd.read_csv(model_file)
        if "analysis_status" not in self.model_data.columns:
            self.model_data["analysis_status"] = "success"
        if "error_message" not in self.model_data.columns:
            self.model_data["error_message"] = ""
        print(f"Loaded {len(self.model_data)} model output files")

        metadata_file = self.model_dir / "metadata.json"
        if metadata_file.exists():
            with open(metadata_file, "r", encoding="utf-8") as f:
                metadata = json.load(f)
                for file_entry in metadata.get("files", []):
                    try:
                        file_id = int(file_entry.get("file_id"))
                    except Exception:
                        continue
                    self.model_metadata[file_id] = file_entry

        individual_dir = self.model_dir / "individual_files"
        if individual_dir.exists():
            for json_file in individual_dir.glob("*.json"):
                with open(json_file, "r", encoding="utf-8") as f:
                    data = json.load(f)
                    file_id = int(json_file.stem.split("_")[0])
                    self.individual_files[file_id] = data

        print(f"Loaded {len(self.individual_files)} individual evaluation files")

    def _iter_analyzable_file_payloads(
        self,
        collect_skips: bool = False,
    ) -> Iterator[Dict[str, Any]]:
        """
        Yield only files that are analyzable under the same exclusion logic used
        for primary scoring.

        This is the single source of truth for model-level inclusion. Panel A/B,
        Panel C, and downstream grouped summaries should all be derived from this
        same analyzable subset.
        """
        if self.model_data is None:
            raise RuntimeError("Model data not loaded")
        if not self.selection_data_detailed:
            raise RuntimeError("Selection data not loaded")

        for orig_file_data in self.selection_data_detailed:
            original_file_id = int(orig_file_data["file_id"])
            new_file_id = int(orig_file_data.get("new_file_id", original_file_id))

            model_row = self.model_data[self.model_data["file_id"] == new_file_id]
            if model_row.empty:
                if collect_skips:
                    self.skipped_error_files.append(
                        {
                            "file_id": new_file_id,
                            "filename": orig_file_data.get(
                                "new_filename",
                                orig_file_data.get("filename", str(new_file_id)),
                            ),
                            "error_message": "Missing model data row",
                        }
                    )
                continue

            row = model_row.iloc[0]
            analysis_status = str(row.get("analysis_status", "success")).strip().lower()
            error_message = str(row.get("error_message", "")).strip()

            if analysis_status == "error":
                if collect_skips:
                    self.skipped_error_files.append(
                        {
                            "file_id": new_file_id,
                            "filename": orig_file_data.get(
                                "new_filename",
                                orig_file_data.get("filename", str(new_file_id)),
                            ),
                            "error_message": error_message,
                        }
                    )
                continue

            metadata_entry = self.model_metadata.get(new_file_id)
            if metadata_entry:
                metadata_status = str(
                    metadata_entry.get("analysis_metadata", {}).get("status", "success")
                ).strip().lower()
                if metadata_status == "error":
                    if collect_skips:
                        self.skipped_error_files.append(
                            {
                                "file_id": new_file_id,
                                "filename": metadata_entry.get("filename", str(new_file_id)),
                                "error_message": metadata_entry.get(
                                    "analysis_metadata", {}
                                ).get("error_message", ""),
                            }
                        )
                    continue

            migrated_file_data = self.individual_files.get(new_file_id)
            if not migrated_file_data:
                if collect_skips:
                    self.skipped_error_files.append(
                        {
                            "file_id": new_file_id,
                            "filename": orig_file_data.get(
                                "new_filename",
                                orig_file_data.get("filename", str(new_file_id)),
                            ),
                            "error_message": "Missing individual migrated data",
                        }
                    )
                continue

            if "error" in migrated_file_data:
                if collect_skips:
                    self.skipped_error_files.append(
                        {
                            "file_id": new_file_id,
                            "filename": orig_file_data.get(
                                "new_filename",
                                orig_file_data.get("filename", str(new_file_id)),
                            ),
                            "error_message": str(
                                migrated_file_data.get("error", "Rector analysis error")
                            ),
                        }
                    )
                continue

            orig_rules_all = normalize_rule_set(orig_file_data.get("rules_triggered", []))
            rem_rules_all = normalize_rule_set(
                migrated_file_data.get("rector_analysis", {}).get("rules_triggered", [])
            )

            orig_upgrade_rules = {r for r in orig_rules_all if is_upgrade_rule(r)}
            rem_upgrade_rules = {r for r in rem_rules_all if is_upgrade_rule(r)}

            yield {
                "original_file_id": original_file_id,
                "new_file_id": new_file_id,
                "filename": orig_file_data.get(
                    "new_filename",
                    orig_file_data.get("filename", str(new_file_id)),
                ),
                "size_category": orig_file_data.get("size_category", "unknown"),
                "orig_file_data": orig_file_data,
                "migrated_file_data": migrated_file_data,
                "orig_rules_all": orig_rules_all,
                "rem_rules_all": rem_rules_all,
                "orig_upgrade_rules": orig_upgrade_rules,
                "rem_upgrade_rules": rem_upgrade_rules,
            }

    def analyze_migration_effectiveness(self) -> List[MigrationResult]:
        """
        Analyze migration effectiveness using PCE-aligned primary scoring.

        Scoring scope is restricted to Rector\\Php* rules.
        """
        results: List[MigrationResult] = []
        self.skipped_error_files = []

        for item in self._iter_analyzable_file_payloads(collect_skips=True):
            original_file_id = item["original_file_id"]
            new_file_id = item["new_file_id"]
            filename = item["filename"]
            size_category = item["size_category"]
            orig_obligation_set = item["orig_upgrade_rules"]
            rem_obligation_set = item["rem_upgrade_rules"]

            discharged_rules = orig_obligation_set - rem_obligation_set
            introduced_rules = rem_obligation_set - orig_obligation_set

            original_obligations = len(orig_obligation_set)
            discharged_obligations = len(discharged_rules)
            introduced_obligations = len(introduced_rules)
            remaining_obligations = len(rem_obligation_set)
            net_change = discharged_obligations - introduced_obligations

            if original_obligations == 0:
                raise ValueError(
                    "Unexpected clean benchmark file after Rector\\Php* filtering: "
                    f"file_id={new_file_id}, filename={filename}"
                )

            discharge_rate = (discharged_obligations / original_obligations) * 100.0

            results.append(
                MigrationResult(
                    file_id=new_file_id,
                    original_file_id=original_file_id,
                    filename=filename,
                    original_obligations=original_obligations,
                    discharged_obligations=discharged_obligations,
                    introduced_obligations=introduced_obligations,
                    remaining_obligations=remaining_obligations,
                    net_change=net_change,
                    discharge_rate=discharge_rate,
                    size_category=size_category,
                )
            )

        self.evaluation_results = results
        return results

    def analyze_php_version_patterns(self) -> Dict[str, Any]:
        """Aggregate scored rule behavior by PHP family and rule type."""
        print("Analyzing PHP version migration patterns...")

        version_analysis: Dict[str, Dict[str, int]] = {}
        rule_effectiveness: Dict[str, Dict[str, float]] = {}

        php_version_pattern = re.compile(r"\\Php(\d+)\\")
        filtered_out = {
            "original_non_upgrade_rules": 0,
            "remaining_non_upgrade_rules": 0,
        }

        def get_php_version_from_rule(rule_name: str) -> Optional[str]:
            match = php_version_pattern.search(rule_name)
            if not match:
                return None
            return f"php_{match.group(1)}"

        def ensure_version(version_key: str) -> None:
            if version_key not in version_analysis:
                version_analysis[version_key] = {
                    "original": 0,
                    "remaining": 0,
                    "discharged": 0,
                    "introduced": 0,
                    "net_change": 0,
                    "discharge_rate": 0.0,
                }

        def ensure_rule(rule_name: str) -> None:
            if rule_name not in rule_effectiveness:
                rule_effectiveness[rule_name] = {
                    "original_count": 0,
                    "remaining_count": 0,
                    "discharged_count": 0,
                    "introduced_count": 0,
                    "net_change": 0,
                    "discharge_rate": 0.0,
                }

        # IMPORTANT: use the same analyzable subset as primary scoring.
        for item in self._iter_analyzable_file_payloads(collect_skips=False):
            orig_rules_all = item["orig_rules_all"]
            rem_rules_all = item["rem_rules_all"]
            orig_rules = item["orig_upgrade_rules"]
            rem_rules = item["rem_upgrade_rules"]

            filtered_out["original_non_upgrade_rules"] += len(orig_rules_all - orig_rules)
            filtered_out["remaining_non_upgrade_rules"] += len(rem_rules_all - rem_rules)

            discharged_rules = orig_rules - rem_rules
            introduced_rules = rem_rules - orig_rules

            for rule in orig_rules:
                ensure_rule(rule)
                rule_effectiveness[rule]["original_count"] += 1

                version = get_php_version_from_rule(rule)
                if version:
                    ensure_version(version)
                    version_analysis[version]["original"] += 1

            for rule in rem_rules:
                ensure_rule(rule)
                rule_effectiveness[rule]["remaining_count"] += 1

                version = get_php_version_from_rule(rule)
                if version:
                    ensure_version(version)
                    version_analysis[version]["remaining"] += 1

            for rule in discharged_rules:
                ensure_rule(rule)
                rule_effectiveness[rule]["discharged_count"] += 1

                version = get_php_version_from_rule(rule)
                if version:
                    ensure_version(version)
                    version_analysis[version]["discharged"] += 1

            for rule in introduced_rules:
                ensure_rule(rule)
                rule_effectiveness[rule]["introduced_count"] += 1

                version = get_php_version_from_rule(rule)
                if version:
                    ensure_version(version)
                    version_analysis[version]["introduced"] += 1

        for version, data in version_analysis.items():
            discharged = int(data["discharged"])
            introduced = int(data["introduced"])
            original = int(data["original"])
            data["net_change"] = discharged - introduced
            data["discharge_rate"] = (discharged / original * 100.0) if original > 0 else 0.0

        for rule, data in rule_effectiveness.items():
            discharged = int(data["discharged_count"])
            introduced = int(data["introduced_count"])
            original = int(data["original_count"])
            data["net_change"] = discharged - introduced
            data["discharge_rate"] = (discharged / original * 100.0) if original > 0 else 0.0

        family_analysis = self._group_versions_by_family(version_analysis)

        return {
            "version_analysis": version_analysis,
            "family_analysis": family_analysis,
            "rule_effectiveness": rule_effectiveness,
            "filtered_out": filtered_out,
            "unit_note": (
                "Counts are computed from per-file rule-set differences using "
                "Rector\\Php* rule types only on the analyzable migrated subset."
            ),
        }

    @staticmethod
    def _group_versions_by_family(
        version_analysis: Dict[str, Dict[str, Any]]
    ) -> Dict[str, Dict[str, Any]]:
        """
        Aggregate fine-grained php_NN keys into PHP major families:
          PHP 5.x  -> php_50..59
          PHP 7.x  -> php_70..79
          PHP 8.x  -> php_80..89

        Returns a dict keyed by 'PHP 5.x', 'PHP 7.x', 'PHP 8.x' with the
        same sub-fields as version_analysis (original, discharged, remaining,
        introduced, net_change, discharge_rate).
        """
        families: Dict[str, Dict[str, Any]] = {
            "PHP 5.x": {"original": 0, "discharged": 0, "remaining": 0, "introduced": 0},
            "PHP 7.x": {"original": 0, "discharged": 0, "remaining": 0, "introduced": 0},
            "PHP 8.x": {"original": 0, "discharged": 0, "remaining": 0, "introduced": 0},
        }
        family_map = {"5": "PHP 5.x", "7": "PHP 7.x", "8": "PHP 8.x"}

        for key, data in version_analysis.items():
            digits = key.replace("php_", "")
            if not digits:
                continue
            major = digits[0]
            family = family_map.get(major)
            if family is None:
                continue
            for field in ("original", "discharged", "remaining", "introduced"):
                families[family][field] += int(data.get(field, 0))

        for fam, data in families.items():
            original = data["original"]
            discharged = data["discharged"]
            introduced = data["introduced"]
            data["net_change"] = discharged - introduced
            data["discharge_rate"] = (
                discharged / original * 100.0 if original > 0 else 0.0
            )

        return families

    def _assert_family_totals_match_metrics(
        self,
        metrics: Dict[str, Any],
        family_analysis: Dict[str, Dict[str, Any]],
    ) -> None:
        """
        Reconciliation guardrail: grouped family totals must exactly match the
        analyzable results totals computed from self.evaluation_results.
        
        Note: Family analysis is computed from analyzable files only; it should
        not match total_original_obligations_all (which includes non-analyzable files).
        """
        fam_original = sum(int(v.get("original", 0)) for v in family_analysis.values())
        fam_discharged = sum(int(v.get("discharged", 0)) for v in family_analysis.values())
        fam_remaining = sum(int(v.get("remaining", 0)) for v in family_analysis.values())
        fam_introduced = sum(int(v.get("introduced", 0)) for v in family_analysis.values())
        
        # Use analyzable obligations (not all-files) for family verification
        analyzable_original = sum(r.original_obligations for r in self.evaluation_results)

        if fam_original != int(analyzable_original):
            raise ValueError(
                f"Family original mismatch: {fam_original} != "
                f"{analyzable_original}"
            )
        if fam_discharged != int(metrics["total_discharged_obligations"]):
            raise ValueError(
                f"Family discharged mismatch: {fam_discharged} != "
                f"{metrics['total_discharged_obligations']}"
            )
        if fam_remaining != int(metrics["total_remaining_obligations"]):
            raise ValueError(
                f"Family remaining mismatch: {fam_remaining} != "
                f"{metrics['total_remaining_obligations']}"
            )
        if fam_introduced != int(metrics["total_introduced_obligations"]):
            raise ValueError(
                f"Family introduced mismatch: {fam_introduced} != "
                f"{metrics['total_introduced_obligations']}"
            )

    def calculate_aggregate_metrics(self) -> Dict[str, Any]:
        """Calculate model-level PCE-aligned summary metrics."""
        print("Calculating aggregate performance metrics...")

        if not self.evaluation_results:
            return {
                "total_files_attempted": 0,
                "total_files_analyzed": 0,
                "total_files_with_errors": 0,
                "total_original_obligations": 0,
                "total_discharged_obligations": 0,
                "total_remaining_obligations": 0,
                "total_introduced_obligations": 0,
                "total_net_change": 0,
                "weighted_discharge_rate": 0.0,
                "average_discharge_rate": 0.0,
                "median_discharge_rate": 0.0,
                "contract_clean_files": 0,
                "contract_clean_rate": 0.0,
                "files_with_introduced_obligations": 0,
                "files_with_introduced_obligations_rate": 0.0,
                "net_negative_files": 0,
                "net_negative_files_rate": 0.0,
                "low_compliance_files": 0,
                "low_compliance_rate": 0.0,
                "size_category_metrics": {},
            }

        total_files = len(self.evaluation_results)
        total_error_files = len(self.skipped_error_files)
        total_attempted_files = total_files + total_error_files
        
        # For all-files scoring:
        # - Numerator: discharged obligations from analyzable files only
        # - Denominator: original obligations from all 100 files
        total_discharged_obligations = sum(r.discharged_obligations for r in self.evaluation_results)
        total_remaining_obligations = sum(r.remaining_obligations for r in self.evaluation_results)
        total_introduced_obligations = sum(r.introduced_obligations for r in self.evaluation_results)
        total_net_change = sum(r.net_change for r in self.evaluation_results)
        
        # Total original obligations from analyzable files (for diagnostics)
        total_original_obligations_analyzable = sum(r.original_obligations for r in self.evaluation_results)
        
        # Total original obligations from ALL 100 benchmark files (the all-files denominator)
        total_original_obligations_all = 0
        if self.selection_data_detailed:
            for orig_file_data in self.selection_data_detailed:
                rules_triggered = normalize_upgrade_rule_set(
                    orig_file_data.get("rules_triggered", [])
                )
                total_original_obligations_all += len(rules_triggered)
        
        # Use all-files denominator for weighted_discharge_rate
        total_original_obligations = total_original_obligations_all

        nonzero_original = [r for r in self.evaluation_results if r.original_obligations > 0]
        discharge_rates = [r.discharge_rate for r in nonzero_original]

        size_metrics: Dict[str, Dict[str, Any]] = {}
        for category in ["small", "medium", "large", "extra_large"]:
            category_results = [r for r in self.evaluation_results if r.size_category == category]
            if not category_results:
                continue

            total_cat_original = sum(r.original_obligations for r in category_results)
            total_cat_discharged = sum(r.discharged_obligations for r in category_results)

            size_metrics[category] = {
                "files": len(category_results),
                "avg_discharge_rate": float(np.mean([r.discharge_rate for r in category_results])),
                "median_discharge_rate": float(np.median([r.discharge_rate for r in category_results])),
                "total_obligations_discharged": total_cat_discharged,
                "total_original_obligations": total_cat_original,
                "weighted_discharge_rate": (
                    total_cat_discharged / total_cat_original * 100.0
                    if total_cat_original > 0 else float("nan")
                ),
            }

        contract_clean_files = len([r for r in self.evaluation_results if r.remaining_obligations == 0])
        files_with_introduced_obligations = len(
            [r for r in self.evaluation_results if r.introduced_obligations > 0]
        )
        net_negative_files = len([r for r in self.evaluation_results if r.net_change < 0])
        low_compliance_files = len([r for r in nonzero_original if r.discharge_rate < 50.0])

        return {
            "total_files_attempted": total_attempted_files,
            "total_files_analyzed": total_files,
            "total_files_with_errors": total_error_files,
            "total_original_obligations": total_original_obligations,
            "total_discharged_obligations": total_discharged_obligations,
            "total_remaining_obligations": total_remaining_obligations,
            "total_introduced_obligations": total_introduced_obligations,
            "total_net_change": total_net_change,
            "weighted_discharge_rate": (
                total_discharged_obligations / total_original_obligations * 100.0
                if total_original_obligations > 0 else float("nan")
            ),
            "average_discharge_rate": float(np.mean(discharge_rates)) if discharge_rates else float("nan"),
            "median_discharge_rate": float(np.median(discharge_rates)) if discharge_rates else float("nan"),
            "contract_clean_files": contract_clean_files,
            "contract_clean_rate": contract_clean_files / total_files * 100.0,
            "files_with_introduced_obligations": files_with_introduced_obligations,
            "files_with_introduced_obligations_rate": files_with_introduced_obligations / total_files * 100.0,
            "net_negative_files": net_negative_files,
            "net_negative_files_rate": net_negative_files / total_files * 100.0,
            "low_compliance_files": low_compliance_files,
            "low_compliance_rate": (
                low_compliance_files / len(nonzero_original) * 100.0
                if nonzero_original else float("nan")
            ),
            "size_category_metrics": size_metrics,
        }

    def generate_detailed_report(self) -> str:
        """Generate a markdown report with PCE-aligned summaries."""
        print("Generating detailed evaluation report...")

        metrics = self.calculate_aggregate_metrics()
        version_patterns = self.analyze_php_version_patterns()
        self._assert_family_totals_match_metrics(
            metrics, version_patterns["family_analysis"]
        )

        report = f"""# PHP Code Migration Model Evaluation Report
## {self.model_name} Model Performance Analysis

## Executive Summary

This report presents an evaluation of the {self.model_name} model's PHP migration outputs using PCE-unified (all-files) scoring over `Rector\\Php*` rules. The weighted discharge rate is computed over all 100 benchmark files, assigning zero discharge to non-analyzable files. The run attempted {metrics['total_files_attempted']} files, analyzed {metrics['total_files_analyzed']} successfully, and skipped {metrics['total_files_with_errors']} Rector-error files.

### Primary Metrics (All-Files Scoring: ρ_w^all)

- **Weighted discharge rate (all-files):** {metrics['weighted_discharge_rate']:.2f}%
- **Total discharged obligations:** {metrics['total_discharged_obligations']}/{metrics['total_original_obligations']}
- **Mean discharge rate (analyzable files only):** {metrics['average_discharge_rate']:.2f}%
- **Median discharge rate (analyzable files only):** {metrics['median_discharge_rate']:.2f}%
- **Files attempted:** {metrics['total_files_attempted']}
- **Files analyzed successfully:** {metrics['total_files_analyzed']}
- **Files skipped due to Rector errors:** {metrics['total_files_with_errors']}
- **Total remaining obligations:** {metrics['total_remaining_obligations']}
- **Total introduced obligations:** {metrics['total_introduced_obligations']}
- **Total net change:** {metrics['total_net_change']}
- **Contract-clean files (R_i = 0):** {metrics['contract_clean_files']} ({metrics['contract_clean_rate']:.2f}%)
- **Files with introduced obligations (I_i > 0):** {metrics['files_with_introduced_obligations']} ({metrics['files_with_introduced_obligations_rate']:.2f}%)
- **Net-negative files (Δ_i < 0):** {metrics['net_negative_files']} ({metrics['net_negative_files_rate']:.2f}%)
- **Low-compliance files (ρ_i < 50%, analyzable only):** {metrics['low_compliance_files']} ({metrics['low_compliance_rate']:.2f}%)

## Detailed Analysis

### 1. Discharge Distribution
"""

        nonzero_original = [
            r for r in self.evaluation_results
            if r.original_obligations > 0 and not np.isnan(r.discharge_rate)
        ]

        success_ranges = {
            "90-100%": len([r for r in nonzero_original if r.discharge_rate >= 90]),
            "70-89%": len([r for r in nonzero_original if 70 <= r.discharge_rate < 90]),
            "50-69%": len([r for r in nonzero_original if 50 <= r.discharge_rate < 70]),
            "<50%": len([r for r in nonzero_original if r.discharge_rate < 50]),
        }

        for label, count in success_ranges.items():
            pct = (count / len(nonzero_original) * 100.0) if nonzero_original else 0.0
            report += f"- **{label}:** {count} files ({pct:.1f}%)\n"

        report += "\n### 2. Performance by File Size Category\n\n"

        for category, data in metrics["size_category_metrics"].items():
            report += (
                f"#### {category.replace('_', ' ').title()} Files\n"
                f"- **Files analyzed:** {data['files']}\n"
                f"- **Mean discharge rate:** {data['avg_discharge_rate']:.2f}%\n"
                f"- **Median discharge rate:** {data['median_discharge_rate']:.2f}%\n"
                f"- **Weighted discharge rate:** {data['weighted_discharge_rate']:.2f}%\n"
                f"- **Discharged obligations:** {data['total_obligations_discharged']}/{data['total_original_obligations']}\n\n"
            )

        top_performers = sorted(self.evaluation_results, key=lambda x: x.discharge_rate, reverse=True)[:10]
        challenging_files = sorted(self.evaluation_results, key=lambda x: x.discharge_rate)[:10]

        report += """### 3. Top Performing Files

| File | Discharge Rate | Discharged | Original |
|------|----------------|------------|----------|
"""
        for result in top_performers:
            report += (
                f"| {result.filename} | {result.discharge_rate:.1f}% | "
                f"{result.discharged_obligations} | {result.original_obligations} |\n"
            )

        report += """

### 4. Most Challenging Files

| File | Discharge Rate | Remaining | Introduced | Original |
|------|----------------|-----------|------------|----------|
"""
        for result in challenging_files:
            report += (
                f"| {result.filename} | {result.discharge_rate:.1f}% | "
                f"{result.remaining_obligations} | {result.introduced_obligations} | "
                f"{result.original_obligations} |\n"
            )

        if self.skipped_error_files:
            report += "\n### 5.1 Files Skipped Due to Rector Errors\n\n"
            report += "| File ID | File | Error |\n"
            report += "|---------|------|-------|\n"
            for skipped in sorted(self.skipped_error_files, key=lambda x: x["file_id"]):
                err = str(skipped.get("error_message", "")).replace("\n", " ")
                report += f"| {skipped['file_id']} | {skipped['filename']} | {err} |\n"

        report += "\n### 6. PHP Family Analysis\n\n"

        family_data = version_patterns["family_analysis"]
        for family in ["PHP 5.x", "PHP 7.x", "PHP 8.x"]:
            data = family_data.get(family, {})
            original = int(data.get("original", 0))
            remaining = int(data.get("remaining", 0))
            if original == 0 and remaining == 0:
                continue

            discharged = int(data.get("discharged", 0))
            introduced = int(data.get("introduced", 0))
            net = int(data.get("net_change", 0))
            rate = float(data.get("discharge_rate", 0.0))

            report += (
                f"- **{family}:** original={original}, discharged={discharged}, "
                f"remaining={remaining}, introduced={introduced}, net={net}, "
                f"discharge_rate={rate:.2f}%\n"
            )

        report += "\n### 7. PHP Version Analysis\n\n"

        version_data = version_patterns["version_analysis"]

        def version_sort_key(version_key: str) -> int:
            try:
                return int(version_key.split("_", 1)[1])
            except Exception:
                return 10**9

        for version in sorted(version_data.keys(), key=version_sort_key):
            data = version_data[version]
            original = int(data.get("original", 0))
            remaining = int(data.get("remaining", 0))
            if original == 0 and remaining == 0:
                continue

            version_name = version.replace("_", ".").upper()
            discharged = int(data.get("discharged", 0))
            introduced = int(data.get("introduced", 0))
            net = int(data.get("net_change", 0))
            rate = float(data.get("discharge_rate", 0.0))

            report += (
                f"- **{version_name}:** original={original}, discharged={discharged}, "
                f"remaining={remaining}, introduced={introduced}, net={net}, "
                f"discharge_rate={rate:.2f}%\n"
            )

        report += """

---
This report summarizes PCE-aligned obligation discharge metrics over `Rector\\Php*` rules based on the available analysis artifacts.
"""
        return report

    def save_detailed_csv(self, filename: str = "evaluation_results.csv") -> pd.DataFrame:
        """Save per-file evaluation results to CSV."""
        print(f"Saving detailed results to {filename}...")

        if self.model_data is None:
            raise RuntimeError("Model data not loaded")

        detailed_data = []

        for result in self.evaluation_results:
            model_row = self.model_data[self.model_data["file_id"] == result.file_id].iloc[0]
            orig_row = next(
                (
                    item for item in self.selection_data_detailed
                    if int(item.get("new_file_id", item["file_id"])) == result.file_id
                ),
                None,
            )

            if not orig_row:
                continue

            individual_data = self.individual_files.get(result.file_id, {})
            rector_analysis = individual_data.get("rector_analysis", {})

            detailed_data.append(
                {
                    "file_id": result.file_id,
                    "original_file_id": result.original_file_id,
                    "filename": result.filename,
                    "original_path": orig_row.get("original_path"),
                    "lines_of_code_original": orig_row.get("lines_of_code"),
                    "lines_of_code_generated": model_row.get("lines_of_code"),
                    "size_category": result.size_category,
                    "original_obligations": result.original_obligations,
                    "discharged_obligations": result.discharged_obligations,
                    "introduced_obligations": result.introduced_obligations,
                    "remaining_obligations": result.remaining_obligations,
                    "net_change": result.net_change,
                    "discharge_rate": result.discharge_rate,
                    "has_diff": rector_analysis.get("has_diff", False),
                }
            )

        df = pd.DataFrame(detailed_data)
        output_path = self.output_dir / filename
        df.to_csv(output_path, index=False)
        print(f"Detailed CSV saved to: {output_path}")
        return df

    def generate_summary_statistics(self) -> Dict[str, Any]:
        """Generate summary statistics suitable for downstream reporting."""
        metrics = self.calculate_aggregate_metrics()
        version_patterns = self.analyze_php_version_patterns()
        self._assert_family_totals_match_metrics(
            metrics, version_patterns["family_analysis"]
        )

        discharge_rates = [
            r.discharge_rate
            for r in self.evaluation_results
            if r.original_obligations > 0 and not np.isnan(r.discharge_rate)
        ]

        stats: Dict[str, Any] = {
            "run_coverage": {
                "total_files_attempted": metrics["total_files_attempted"],
                "total_files_analyzed": metrics["total_files_analyzed"],
                "total_files_with_errors": metrics["total_files_with_errors"],
                "skipped_error_files": self.skipped_error_files,
            },
            "descriptive_statistics": {
                "discharge_rate_mean": float(np.mean(discharge_rates)) if discharge_rates else float("nan"),
                "discharge_rate_median": float(np.median(discharge_rates)) if discharge_rates else float("nan"),
                "discharge_rate_std": float(np.std(discharge_rates)) if discharge_rates else float("nan"),
                "discharge_rate_min": float(np.min(discharge_rates)) if discharge_rates else float("nan"),
                "discharge_rate_max": float(np.max(discharge_rates)) if discharge_rates else float("nan"),
            },
            "performance_metrics": metrics,
            "version_analysis": version_patterns,
            "correlation_analysis": {
                "loc_vs_discharge": float("nan"),
                "original_obligations_vs_discharge": float("nan"),
            },
        }

        try:
            if self.selection_data is not None:
                loc_map = {
                    int(row["file_id"]): int(row["lines_of_code"])
                    for _, row in self.selection_data.iterrows()
                }
                valid = [
                    r for r in self.evaluation_results
                    if r.original_obligations > 0
                    and not np.isnan(r.discharge_rate)
                    and r.original_file_id in loc_map
                ]
                if len(valid) >= 2:
                    locs = [loc_map[r.original_file_id] for r in valid]
                    rates = [r.discharge_rate for r in valid]
                    obligations = [r.original_obligations for r in valid]

                    stats["correlation_analysis"]["loc_vs_discharge"] = float(
                        np.corrcoef(locs, rates)[0, 1]
                    )
                    stats["correlation_analysis"]["original_obligations_vs_discharge"] = float(
                        np.corrcoef(obligations, rates)[0, 1]
                    )
        except Exception:
            pass

        return stats

    def run_complete_evaluation(self) -> Dict[str, Any]:
        """Run the complete evaluation workflow."""
        print("=== PHP Migration Model Evaluation ===")
        print("Starting analysis...")

        self.load_data()
        self.analyze_migration_effectiveness()

        report = self.generate_detailed_report()
        summary_stats = self.generate_summary_statistics()

        report_path = self.output_dir / "migration_evaluation_report.md"
        with open(report_path, "w", encoding="utf-8") as f:
            f.write(report)
        print(f"Evaluation report saved to: {report_path}")

        stats_path = self.output_dir / "summary_statistics.json"
        with open(stats_path, "w", encoding="utf-8") as f:
            json.dump(summary_stats, f, indent=2, default=str)
        print(f"Summary statistics saved to: {stats_path}")

        self.save_detailed_csv()

        print("\n=== Evaluation Complete ===")
        print(f"Files attempted: {summary_stats['performance_metrics']['total_files_attempted']}")
        print(f"Files analyzed: {len(self.evaluation_results)}")
        print(f"Files skipped due to Rector errors: {summary_stats['performance_metrics']['total_files_with_errors']}")
        print(f"Mean discharge rate: {summary_stats['performance_metrics']['average_discharge_rate']:.2f}%")
        print(f"Weighted discharge rate: {summary_stats['performance_metrics']['weighted_discharge_rate']:.2f}%")

        return summary_stats


def main() -> None:
    """Main entry point."""
    evaluator = PHPMigrationEvaluator()
    evaluator.run_complete_evaluation()


if __name__ == "__main__":
    main()
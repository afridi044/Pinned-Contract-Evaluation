"""
Generate Table 19: Most resistant Rector rules across models (PCE Framework).

This script automatically identifies the most resistant rules by analyzing
obligation discharge metrics from all models and outputs results as JSON.
PCE = Pinned Contract Evaluation framework using obligation discharge rates.
"""

import json
from pathlib import Path
from typing import Dict, Any, List, Tuple
import statistics


def load_model_stats(model_dir: Path) -> Dict[str, Any]:
    """Load summary_statistics.json for a model."""
    stats_file = model_dir / "summary_statistics.json"
    if not stats_file.exists():
        print(f"Warning: {stats_file} not found")
        return {}
    
    with open(stats_file, 'r') as f:
        return json.load(f)


def get_rule_data(stats: Dict[str, Any], rule_name: str) -> Dict[str, Any]:
    """Extract data for a specific rule from summary statistics."""
    if not stats:
        return {}
    
    rule_effectiveness = stats.get("version_analysis", {}).get("rule_effectiveness", {})
    return rule_effectiveness.get(rule_name, {})


def extract_rule_info(full_rule_name: str) -> Dict[str, str]:
    """Extract PHP version and short name from full rule name."""
    # Example: Rector\\Php81\\Rector\\FuncCall\\NullToStrictStringFuncCallArgRector
    parts = full_rule_name.split("\\")
    
    php_version = "unknown"
    short_name = full_rule_name
    
    for part in parts:
        if part.startswith("Php"):
            # Extract version like "81" from "Php81"
            version_num = part[3:]
            if len(version_num) == 2:
                php_version = f"{version_num[0]}.{version_num[1]}"
            elif len(version_num) == 3:
                php_version = f"{version_num[0]}.{version_num[1:]}"
    
    # Get short name (last part without "Rector" suffix)
    if parts:
        short_name = parts[-1].replace("Rector", "")
    
    return {"php_version": php_version, "short_name": short_name}


def categorize_rule(full_rule_name: str, short_name: str) -> str:
    """Automatically categorize a rule based on its name and behavior."""
    name_lower = short_name.lower()
    full_lower = full_rule_name.lower()
    
    # Type strictness & null-safety
    if "null" in name_lower or "stringable" in name_lower or "strict" in name_lower:
        return "type_strictness"
    
    # Modern callable & attribute syntax
    if "callable" in name_lower or "attribute" in name_lower or "override" in name_lower or "typetoconst" in name_lower:
        return "callable_attribute"
    
    # Dataflow-dependent
    if "promotion" in name_lower or "readonly" in name_lower or "constructor" in name_lower:
        return "dataflow"
    
    # Legacy syntactic
    if any(x in name_lower for x in ["array", "ternary", "string", "str", "coalesce"]):
        return "legacy_syntax"
    
    return "other"


def calculate_resistance_score(rule_name: str, model_stats: Dict[str, Dict]) -> Dict[str, Any]:
    """Calculate various resistance metrics for a rule across all models using discharge rates."""
    discharge_rates = []
    original_counts = []
    introduced_counts = []
    
    for model_name, stats in model_stats.items():
        rule_data = get_rule_data(stats, rule_name)
        if not rule_data:
            continue
        
        original = rule_data.get("original_count", 0)
        original_counts.append(original)
        
        if original > 0:
            discharged = rule_data.get("discharged_count", 0)
            rate = (discharged / original) * 100
            discharge_rates.append(rate)
        
        introduced = rule_data.get("introduced_count", 0)
        introduced_counts.append(introduced)
    
    if not original_counts or max(original_counts) == 0:
        # Rules not in original benchmark - measure by introduced issues
        return {
            "avg_discharge_rate": None,
            "min_discharge_rate": None,
            "max_discharge_rate": None,
            "variance": None,
            "total_introduced": sum(introduced_counts),
            "max_original": 0,
            "resistance_type": "introduced"
        }
    
    if not discharge_rates:
        return None
    
    return {
        "avg_discharge_rate": statistics.mean(discharge_rates),
        "min_discharge_rate": min(discharge_rates),
        "max_discharge_rate": max(discharge_rates),
        "variance": statistics.variance(discharge_rates) if len(discharge_rates) > 1 else 0,
        "total_introduced": sum(introduced_counts),
        "max_original": max(original_counts),
        "resistance_type": "low_discharge"
    }


def main():
    # Model directories - files are in wordpress subdirectory
    base_dir = Path(__file__).parent / "wordpress"
    models = {
        "Pro": "gemini_2_5_pro",
        "Flash": "gemini_2_5_flash",
        "GPT-5": "gpt_5_codex",
        "Claude": "claude_sonnet_4_20250514",
        "LLaMA": "meta_llama_llama_3_3_70b_instruct"
    }
    
    # Load all model statistics
    model_stats = {}
    for name, dir_name in models.items():
        model_dir = base_dir / dir_name
        model_stats[name] = load_model_stats(model_dir)
        print(f"Loaded stats for {name}")
    
    # Collect all unique rules across all models
    all_rules = set()
    for stats in model_stats.values():
        if stats:
            rule_effectiveness = stats.get("version_analysis", {}).get("rule_effectiveness", {})
            all_rules.update(rule_effectiveness.keys())
    
    print(f"\nTotal unique rules found: {len(all_rules)}")
    
    # Calculate resistance metrics for each rule
    rule_metrics = []
    for rule_name in all_rules:
        metrics = calculate_resistance_score(rule_name, model_stats)
        if metrics:
            rule_info = extract_rule_info(rule_name)
            short_name = rule_info["short_name"]
            category = categorize_rule(rule_name, short_name)
            
            rule_metrics.append({
                "rule_name": rule_name,
                "short_name": short_name,
                "php_version": rule_info["php_version"],
                "category": category,
                **metrics
            })
    
    # Sort rules by resistance (lowest avg discharge rate first, then by introduced issues)
    resistant_rules = []
    introduced_rules = []
    
    for rule in rule_metrics:
        if rule["resistance_type"] == "introduced":
            if rule["total_introduced"] >= 3:  # Threshold for significant introduction
                introduced_rules.append(rule)
        elif rule["avg_discharge_rate"] is not None:
            resistant_rules.append(rule)
    
    # Sort by average discharge rate (ascending)
    resistant_rules.sort(key=lambda x: x["avg_discharge_rate"])
    
    # Sort introduced rules by total introduced (descending)
    introduced_rules.sort(key=lambda x: x["total_introduced"], reverse=True)
    
    # Select top resistant rules
    top_low_discharge = resistant_rules[:15]  # Top 15 with lowest discharge
    top_introduced = introduced_rules[:5]  # Top 5 with most introduced
    
    # Also include high-variance rules (variable performance across models)
    high_variance_rules = [r for r in resistant_rules if r.get("variance", 0) > 500]
    high_variance_rules.sort(key=lambda x: x["variance"], reverse=True)
    top_high_variance = high_variance_rules[:5]
    
    # Combine and deduplicate
    selected_rules = {}
    for rule in top_low_discharge + top_introduced + top_high_variance:
        if rule["rule_name"] not in selected_rules:
            selected_rules[rule["rule_name"]] = rule
    
    print(f"Selected {len(selected_rules)} most resistant rules")
    
    # Build output structure with detailed model data
    output = {
        "metadata": {
            "description": "Most resistant Rector rules across models",
            "models": list(models.keys()),
            "total_rules_analyzed": len(all_rules),
            "selection_criteria": {
                "low_discharge": "Rules with lowest average discharge rates",
                "introduced_issues": "Rules that introduce new violations (not in original)",
                "high_variance": "Rules with high variance in performance across models"
            }
        },
        "resistant_rules": []
    }
    
    # For each selected rule, gather detailed data from all models
    for rule_name, rule_info in selected_rules.items():
        rule_entry = {
            "rule_name": rule_name,
            "short_name": rule_info["short_name"],
            "php_version": rule_info["php_version"],
            "category": rule_info["category"],
            "resistance_metrics": {
                "avg_discharge_rate": rule_info["avg_discharge_rate"],
                "min_discharge_rate": rule_info["min_discharge_rate"],
                "max_discharge_rate": rule_info["max_discharge_rate"],
                "variance": rule_info["variance"],
                "total_introduced": rule_info["total_introduced"],
                "original_trigger_count": rule_info["max_original"],
                "resistance_type": rule_info["resistance_type"]
            },
            "model_performance": {}
        }
        
        # Add per-model data
        for model_name in ["Pro", "Flash", "GPT-5", "Claude", "LLaMA"]:
            rule_data = get_rule_data(model_stats[model_name], rule_name)
            if rule_data:
                original = rule_data.get("original_count", 0)
                discharged = rule_data.get("discharged_count", 0)
                introduced = rule_data.get("introduced_count", 0)
                
                model_entry = {
                    "original_count": original,
                    "discharged_count": discharged,
                    "remaining_count": rule_data.get("remaining_count", 0),
                    "introduced_count": introduced,
                    "discharge_rate": round((discharged / original * 100), 2) if original > 0 else None
                }
                rule_entry["model_performance"][model_name] = model_entry
        
        output["resistant_rules"].append(rule_entry)
    
    # Sort output by category and then by resistance
    output["resistant_rules"].sort(
        key=lambda x: (
            x["category"],
            x["resistance_metrics"]["avg_discharge_rate"] if x["resistance_metrics"]["avg_discharge_rate"] is not None else 999
        )
    )
    
    # Save to JSON file
    output_file = base_dir / "resistant_rules_analysis.json"
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(output, f, indent=2, ensure_ascii=False)
    
    print(f"\n✓ Analysis saved to: {output_file}")
    
    # Print summary
    print("\n" + "="*80)
    print("Top Resistant Rules Summary")
    print("="*80)
    
    categories = {}
    for rule in output["resistant_rules"]:
        cat = rule["category"]
        if cat not in categories:
            categories[cat] = []
        categories[cat].append(rule)
    
    for category, rules in sorted(categories.items()):
        print(f"\n{category.upper().replace('_', ' ')}:")
        for rule in rules[:5]:  # Show top 5 per category
            metrics = rule["resistance_metrics"]
            print(f"  • {rule['short_name']} (PHP {rule['php_version']})")
            if metrics["avg_discharge_rate"] is not None:
                print(f"    Avg discharge: {metrics['avg_discharge_rate']:.1f}% "
                      f"(range: {metrics['min_discharge_rate']:.0f}%-{metrics['max_discharge_rate']:.0f}%)")        
            if metrics["total_introduced"] > 0:
                print(f"    Introduced issues: {metrics['total_introduced']}")
            print(f"    Original triggers: {metrics['original_trigger_count']}")


if __name__ == "__main__":
    main()

# Rector Rules Analysis Report - Comprehensive

*Generated on 2025-11-13 04:29:43*

## Dataset Overview
- **Total Files Analyzed**: 482
- **Total Unique Rules Triggered**: 48
- **Total Rule Applications**: 1331
- **PHP Versions Covered**: 18

# Top 20 Most Frequently Triggered Rector Rules

## Executive Summary
- **Total Unique Rules**: 48
- **Most Common Rule**: LongArrayToShortArrayRector (triggered in 327 files)
- **Analysis Date**: 2025-11-13 04:29:43

## Top 20 Rules by Frequency

| Rank | Rule Name | PHP Version | Files Affected |
|------|-----------|-------------|----------------|
| 1 | `LongArrayToShortArrayRector` | Php54 | 327 |
| 2 | `NullToStrictStringFuncCallArgRector` | Php81 | 210 |
| 3 | `DirNameFileConstantToDirConstantRector` | Php53 | 107 |
| 4 | `TernaryToNullCoalescingRector` | Php70 | 100 |
| 5 | `StrContainsRector` | Php80 | 83 |
| 6 | `StrStartsWithRector` | Php80 | 81 |
| 7 | `ListToArrayDestructRector` | Php71 | 64 |
| 8 | `VarToPublicPropertyRector` | Php52 | 41 |
| 9 | `ClassPropertyAssignToConstructorPromotionRector` | Php80 | 34 |
| 10 | `ChangeSwitchToMatchRector` | Php80 | 28 |
| 11 | `FirstClassCallableRector` | Php81 | 28 |
| 12 | `StrEndsWithRector` | Php80 | 26 |
| 13 | `TernaryToElvisRector` | Php53 | 24 |
| 14 | `MultiDirnameRector` | Php70 | 20 |
| 15 | `ClassOnObjectRector` | Php80 | 14 |
| 16 | `StringableForToStringRector` | Php80 | 13 |
| 17 | `CurlyToSquareBracketArrayStringRector` | Php74 | 12 |
| 18 | `Php4ConstructorRector` | Php70 | 10 |
| 19 | `IfIssetToCoalescingRector` | Php70 | 9 |
| 20 | `ClassOnThisVariableObjectRector` | Php80 | 8 |

## PHP Version Migration Analysis

### Rule Distribution by PHP Version

| PHP Version | Files Affected | Unique Rules | Total Rule Triggers |
|-------------|----------------|--------------|---------------------|
| PHP 54 | 327 | 1 | 327 |
| PHP 80 | 168 | 10 | 291 |
| PHP 81 | 217 | 2 | 238 |
| PHP 70 | 137 | 10 | 158 |
| PHP 53 | 126 | 2 | 131 |
| PHP 71 | 65 | 3 | 68 |
| PHP 52 | 41 | 1 | 41 |
| PHP 74 | 17 | 3 | 18 |
| PHP 73 | 13 | 4 | 13 |
| PHP 72 | 7 | 4 | 8 |
| PHP 83 | 8 | 1 | 8 |
| TypeDeclaration | 6 | 1 | 6 |
| PHP 56 | 5 | 1 | 5 |
| CodingStyle | 5 | 1 | 5 |
| PHP 82 | 4 | 1 | 4 |
| PHP 55 | 4 | 1 | 4 |
| DeadCode | 4 | 1 | 4 |
| CodeQuality | 2 | 1 | 2 |

## Rule Category Analysis

### Most Common Rule Categories

| Category | Files Impacted | Unique Rules | Description |
|----------|-----------------|--------------|-------------|
| `Array_` | 329 | 2 | Array syntax and function improvements |
| `FuncCall` | 290 | 16 | Function call transformations and modernizations |
| `Ternary` | 113 | 2 | Ternary operator improvements |
| `Identical` | 87 | 2 | Code structure improvements |
| `NotIdentical` | 83 | 1 | Code structure improvements |
| `List_` | 64 | 1 | Code structure improvements |
| `Property` | 41 | 1 | Property declaration improvements |
| `Class_` | 39 | 3 | Class structure modifications |
| `Switch_` | 28 | 1 | Code structure improvements |
| `ClassMethod` | 22 | 5 | Class method signature changes |
| `ArrayDimFetch` | 12 | 1 | Code structure improvements |
| `StmtsAwareInterface` | 9 | 1 | Code structure improvements |
| `Assign` | 8 | 2 | Assignment operator enhancements |
| `ClassConstFetch` | 8 | 1 | Code structure improvements |
| `StaticCall` | 4 | 1 | Code structure improvements |
| `ConstFetch` | 3 | 1 | Code structure improvements |
| `MethodCall` | 3 | 1 | Code structure improvements |
| `Catch_` | 3 | 1 | Code structure improvements |
| `While_` | 3 | 1 | Code structure improvements |
| `Break_` | 2 | 1 | Code structure improvements |
| `If_` | 2 | 1 | Conditional statement optimizations |
| `Variable` | 1 | 1 | Variable handling updates |
| `Closure` | 1 | 1 | Code structure improvements |

## Migration Pattern Analysis

### Files Requiring Multi-Version Updates

| Pattern Type | File Count | Percentage | Migration Complexity |
|--------------|------------|------------|---------------------|
| Single PHP Version | 110 | 27.7% | 🟢 Simple |
| Multiple PHP Versions | 287 | 72.3% | 🔴 Complex |

### Most Common Version Combinations

- **PHP 54 + PHP 81**: 21 files
- **PHP 53 + PHP 54**: 21 files
- **PHP 54 + PHP 80 + PHP 81**: 19 files
- **PHP 54 + PHP 70 + PHP 80 + PHP 81**: 17 files
- **PHP 54 + PHP 70 + PHP 71 + PHP 80 + PHP 81**: 13 files
- **PHP 53 + PHP 54 + PHP 81**: 13 files
- **PHP 54 + PHP 70 + PHP 81**: 9 files
- **PHP 52 + PHP 80**: 9 files
- **PHP 53 + PHP 54 + PHP 80 + PHP 81**: 8 files
- **PHP 54 + PHP 70**: 7 files


## File Size Category vs Migration Opportunities

### Rule Distribution by File Size Category

| File Size Category | Files | Avg Rules per File | Avg PHP Versions |
|--------------------|-------|-------------------|------------------|
| Small (1-200) | 155 | 1.6 | 1.6 |
| Medium (201-500) | 118 | 3.4 | 3.1 |
| Large (501-1000) | 56 | 4.2 | 3.8 |
| Extra Large (1000+) | 68 | 6.5 | 4.9 |

### Key Insights
- **File size categories** help organize files by complexity
- **Rule distribution** shows migration patterns across different file sizes
- **Version coverage** indicates how many PHP versions each category touches


## Complete Rule Reference

### All Triggered Rules (Alphabetical)

| Rule Name | PHP Version | Files | Full Class Name |
|-----------|-------------|-------|-----------------|
| `AddOverrideAttributeToOverriddenMethodsRector` | Php83 | 8 | `Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector` |
| `ArrayKeyFirstLastRector` | Php73 | 1 | `Rector\Php73\Rector\FuncCall\ArrayKeyFirstLastRector` |
| `AssignArrayToStringRector` | Php71 | 3 | `Rector\Php71\Rector\Assign\AssignArrayToStringRector` |
| `BreakNotInLoopOrSwitchToReturnRector` | Php70 | 2 | `Rector\Php70\Rector\Break_\BreakNotInLoopOrSwitchToReturnRector` |
| `ChangeSwitchToMatchRector` | Php80 | 28 | `Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector` |
| `ClassConstantToSelfClassRector` | Php55 | 4 | `Rector\Php55\Rector\Class_\ClassConstantToSelfClassRector` |
| `ClassOnObjectRector` | Php80 | 14 | `Rector\Php80\Rector\FuncCall\ClassOnObjectRector` |
| `ClassOnThisVariableObjectRector` | Php80 | 8 | `Rector\Php80\Rector\ClassConstFetch\ClassOnThisVariableObjectRector` |
| `ClassPropertyAssignToConstructorPromotionRector` | Php80 | 34 | `Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector` |
| `ClosureToArrowFunctionRector` | Php74 | 1 | `Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector` |
| `ConsistentImplodeRector` | CodingStyle | 5 | `Rector\CodingStyle\Rector\FuncCall\ConsistentImplodeRector` |
| `CreateFunctionToAnonymousFunctionRector` | Php72 | 2 | `Rector\Php72\Rector\FuncCall\CreateFunctionToAnonymousFunctionRector` |
| `CurlyToSquareBracketArrayStringRector` | Php74 | 12 | `Rector\Php74\Rector\ArrayDimFetch\CurlyToSquareBracketArrayStringRector` |
| `DirNameFileConstantToDirConstantRector` | Php53 | 107 | `Rector\Php53\Rector\FuncCall\DirNameFileConstantToDirConstantRector` |
| `EregToPregMatchRector` | Php70 | 5 | `Rector\Php70\Rector\FuncCall\EregToPregMatchRector` |
| `FinalPrivateToPrivateVisibilityRector` | Php80 | 1 | `Rector\Php80\Rector\ClassMethod\FinalPrivateToPrivateVisibilityRector` |
| `FirstClassCallableRector` | Php81 | 28 | `Rector\Php81\Rector\Array_\FirstClassCallableRector` |
| `IfIssetToCoalescingRector` | Php70 | 9 | `Rector\Php70\Rector\StmtsAwareInterface\IfIssetToCoalescingRector` |
| `IfToSpaceshipRector` | Php70 | 2 | `Rector\Php70\Rector\If_\IfToSpaceshipRector` |
| `ListToArrayDestructRector` | Php71 | 64 | `Rector\Php71\Rector\List_\ListToArrayDestructRector` |
| `LongArrayToShortArrayRector` | Php54 | 327 | `Rector\Php54\Rector\Array_\LongArrayToShortArrayRector` |
| `MultiDirnameRector` | Php70 | 20 | `Rector\Php70\Rector\FuncCall\MultiDirnameRector` |
| `NullCoalescingOperatorRector` | Php74 | 5 | `Rector\Php74\Rector\Assign\NullCoalescingOperatorRector` |
| `NullToStrictStringFuncCallArgRector` | Php81 | 210 | `Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector` |
| `OptionalParametersAfterRequiredRector` | CodeQuality | 2 | `Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector` |
| `Php4ConstructorRector` | Php70 | 10 | `Rector\Php70\Rector\ClassMethod\Php4ConstructorRector` |
| `PowToExpRector` | Php56 | 5 | `Rector\Php56\Rector\FuncCall\PowToExpRector` |
| `RandomFunctionRector` | Php70 | 6 | `Rector\Php70\Rector\FuncCall\RandomFunctionRector` |
| `RemoveExtraParametersRector` | Php71 | 1 | `Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector` |
| `RemoveParentCallWithoutParentRector` | DeadCode | 4 | `Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector` |
| `RemoveUnusedVariableInCatchRector` | Php80 | 3 | `Rector\Php80\Rector\Catch_\RemoveUnusedVariableInCatchRector` |
| `ReturnNeverTypeRector` | TypeDeclaration | 6 | `Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector` |
| `SensitiveConstantNameRector` | Php73 | 3 | `Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector` |
| `SetCookieRector` | Php73 | 4 | `Rector\Php73\Rector\FuncCall\SetCookieRector` |
| `StrContainsRector` | Php80 | 83 | `Rector\Php80\Rector\NotIdentical\StrContainsRector` |
| `StrEndsWithRector` | Php80 | 26 | `Rector\Php80\Rector\Identical\StrEndsWithRector` |
| `StrStartsWithRector` | Php80 | 81 | `Rector\Php80\Rector\Identical\StrStartsWithRector` |
| `StringableForToStringRector` | Php80 | 13 | `Rector\Php80\Rector\Class_\StringableForToStringRector` |
| `StringifyDefineRector` | Php72 | 1 | `Rector\Php72\Rector\FuncCall\StringifyDefineRector` |
| `StringifyStrNeedlesRector` | Php73 | 5 | `Rector\Php73\Rector\FuncCall\StringifyStrNeedlesRector` |
| `StringsAssertNakedRector` | Php72 | 2 | `Rector\Php72\Rector\FuncCall\StringsAssertNakedRector` |
| `TernaryToElvisRector` | Php53 | 24 | `Rector\Php53\Rector\Ternary\TernaryToElvisRector` |
| `TernaryToNullCoalescingRector` | Php70 | 100 | `Rector\Php70\Rector\Ternary\TernaryToNullCoalescingRector` |
| `ThisCallOnStaticMethodToStaticCallRector` | Php70 | 3 | `Rector\Php70\Rector\MethodCall\ThisCallOnStaticMethodToStaticCallRector` |
| `Utf8DecodeEncodeToMbConvertEncodingRector` | Php82 | 4 | `Rector\Php82\Rector\FuncCall\Utf8DecodeEncodeToMbConvertEncodingRector` |
| `VarToPublicPropertyRector` | Php52 | 41 | `Rector\Php52\Rector\Property\VarToPublicPropertyRector` |
| `WhileEachToForeachRector` | Php72 | 3 | `Rector\Php72\Rector\While_\WhileEachToForeachRector` |
| `WrapVariableVariableNameInCurlyBracesRector` | Php70 | 1 | `Rector\Php70\Rector\Variable\WrapVariableVariableNameInCurlyBracesRector` |
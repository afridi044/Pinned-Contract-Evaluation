# LLM Migration Report - claude_sonnet_4_20250514

*Generated on 2026-03-17 04:20:24*

## Summary

- **Files analyzed**: 100
- **Rector errors**: 3
- **Files analyzed successfully**: 97
- **Perfect migrations** (0 changes needed): 6
- **Files needing work**: 91
- **Total remaining changes**: 247
- **Average changes per file**: 2.5

### Migration Statistics

- **Files with no changes**: 6 (6.2%)
- **Files with 1-3 changes**: 68
- **Files with 4-8 changes**: 21
- **Files with 9+ changes**: 2
- **Files with Rector errors**: 3

## File-by-File Results

### 📄 `002_module.audio-video.asf.php`

- **Status**: success
- **Changes needed**: 10
- **PHP versions affected**: PHP 70, PHP 71, PHP 72, PHP 73, PHP 74, PHP 80, PHP 81
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, StringifyDefineRector, SensitiveConstantNameRector, NullCoalescingOperatorRector, StrEndsWithRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `003_wp-db.php`

- **Status**: success
- **Changes needed**: 9
- **PHP versions affected**: PHP 53, PHP 54, PHP 55, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, LongArrayToShortArrayRector, ClassConstantToSelfClassRector, ClassOnThisVariableObjectRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, ChangeSwitchToMatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `014_module.tag.id3v2.php`

- **Status**: success
- **Changes needed**: 8
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81, PHP 82
- **Triggered rules**: LongArrayToShortArrayRector, ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector, StrStartsWithRector, StrContainsRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `030_class-json.php`

- **Status**: success
- **Changes needed**: 8
- **PHP versions affected**: PHP 54, PHP 71, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, BinaryOpBetweenNumberAndStringRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, ChangeSwitchToMatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `001_getid3.lib.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 53, PHP 54, PHP 56, PHP 80, PHP 81, PHP 82
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, PowToExpRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `004_class-IXR.php`

- **Status**: success
- **Changes needed**: 6
- **PHP versions affected**: PHP 52, PHP 54, PHP 80, PHP 81, PHP 83
- **Triggered rules**: VarToPublicPropertyRector, LongArrayToShortArrayRector, ClassPropertyAssignToConstructorPromotionRector, StrContainsRector, NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `006_widgets.php`

- **Status**: success
- **Changes needed**: 6
- **PHP versions affected**: PHP 54, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ClassOnThisVariableObjectRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `007_class-wp.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ClassPropertyAssignToConstructorPromotionRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `010_class-wp-theme.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: CodingStyle, PHP 80, PHP 81
- **Triggered rules**: ConsistentImplodeRector, ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `012_module.audio-video.riff.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 73, PHP 80, PHP 81
- **Triggered rules**: SensitiveConstantNameRector, StrEndsWithRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `013_file.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 73, PHP 80, PHP 81
- **Triggered rules**: StringifyStrNeedlesRector, StrEndsWithRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `022_class-wp-customize-setting.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 80, PHP 81, PHP 83
- **Triggered rules**: TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector, ChangeSwitchToMatchRector, FirstClassCallableRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `024_edit-form-advanced.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 70, PHP 73, PHP 74, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ArrayKeyFirstLastRector, NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `032_class-wp-comments-list-table.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: DeadCode, PHP 54, PHP 80, PHP 83
- **Triggered rules**: RemoveParentCallWithoutParentRector, LongArrayToShortArrayRector, ClassOnThisVariableObjectRector, ClassOnObjectRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `042_image-edit.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, RandomFunctionRector, TernaryToNullCoalescingRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `005_class-snoopy.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 71, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, AssignArrayToStringRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `009_getid3.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 70, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, TernaryToNullCoalescingRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `017_press-this.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 54, PHP 71, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `020_class-ftp.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: RemoveExtraParametersRector, ClassPropertyAssignToConstructorPromotionRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `043_image.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 71, PHP 80, PHP 81, PHP 82
- **Triggered rules**: ListToArrayDestructRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `047_update-core.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 54, PHP 71, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `054_nav-menu.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: CodeQuality, PHP 70, PHP 81
- **Triggered rules**: OptionalParametersAfterRequiredRector, IfIssetToCoalescingRector, TernaryToNullCoalescingRector, NullToStrictStringFuncCallArgRector

### 📄 `066_class.wp-dependencies.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 70, PHP 71, PHP 81
- **Triggered rules**: IfIssetToCoalescingRector, RemoveExtraParametersRector, ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `008_wp-login.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 54, PHP 73, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, SetCookieRector, NullToStrictStringFuncCallArgRector

### 📄 `016_module.audio.ac3.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 56, PHP 81, PHP 83
- **Triggered rules**: PowToExpRector, NullToStrictStringFuncCallArgRector, AddTypeToConstRector

### 📄 `018_streams.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, ReadOnlyPropertyRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `019_atomlib.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `026_Source.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, NullToStrictStringFuncCallArgRector

### 📄 `029_module.audio.ogg.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 56, PHP 70, PHP 81
- **Triggered rules**: PowToExpRector, ThisCallOnStaticMethodToStaticCallRector, NullToStrictStringFuncCallArgRector

### 📄 `031_class-wp-plugins-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: TernaryToElvisRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `039_class-wp-upgrader-skins.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 81, PHP 83
- **Triggered rules**: TernaryToNullCoalescingRector, NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `044_class-wp-image-editor.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 80, PHP 81
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector

### 📄 `046_module.audio-video.flv.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 54, PHP 70, PHP 80
- **Triggered rules**: LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector

### 📄 `052_class-wp-ms-themes-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: ListToArrayDestructRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `053_class-wp-themes-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassOnThisVariableObjectRector, ClassOnObjectRector, NullToStrictStringFuncCallArgRector

### 📄 `055_rss.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 54, PHP 71, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `057_class-wp-customize-manager.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 81
- **Triggered rules**: IfToSpaceshipRector, IfIssetToCoalescingRector, FirstClassCallableRector

### 📄 `064_wp-diff.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `065_class-wp-image-editor-imagick.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: RemoveUnusedVariableInCatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `011_translations.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 83
- **Triggered rules**: DirNameFileConstantToDirConstantRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `023_module.tag.id3v1.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector

### 📄 `027_upload.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `035_wp-trackback.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `037_class-wp-ms-sites-list-table.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `041_module.tag.lyrics3.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: NullToStrictStringFuncCallArgRector, AddTypeToConstRector

### 📄 `045_Locator.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector

### 📄 `050_class-oembed.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 73, PHP 81
- **Triggered rules**: StringifyStrNeedlesRector, NullToStrictStringFuncCallArgRector

### 📄 `056_class.akismet.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70, PHP 81
- **Triggered rules**: WrapVariableVariableNameInCurlyBracesRector, NullToStrictStringFuncCallArgRector

### 📄 `058_session.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80
- **Triggered rules**: FinalPrivateToPrivateVisibilityRector, ClassPropertyAssignToConstructorPromotionRector

### 📄 `060_themes.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: TernaryToElvisRector, NullToStrictStringFuncCallArgRector

### 📄 `061_class.wp-scripts.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `068_revision.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 54, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, NullToStrictStringFuncCallArgRector

### 📄 `071_plugins.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `073_site-settings.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `080_site-info.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `081_site-new.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `084_class-wp-ajax-response.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassOnObjectRector, NullToStrictStringFuncCallArgRector

### 📄 `086_user-new.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `094_class.akismet-widget.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `100_wp-links-opml.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 53, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, NullToStrictStringFuncCallArgRector

### 📄 `015_Diff.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 53
- **Triggered rules**: DirNameFileConstantToDirConstantRector

### 📄 `021_class-pop3.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 54
- **Triggered rules**: LongArrayToShortArrayRector

### 📄 `025_widgets.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `028_themes.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `033_ms-settings.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `034_inline.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `036_module.tag.apetag.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `038_class.wp-styles.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `040_class-wp-media-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `048_about.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `049_Parser.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `051_ms-deprecated.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `062_string.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `067_user.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `069_class-wp-image-editor-gd.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `070_File.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `072_class-wp-users-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `077_post-thumbnail-template.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 74
- **Triggered rules**: NullCoalescingOperatorRector

### 📄 `079_load-styles.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `082_import.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `087_Restriction.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `088_Credit.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `089_Category.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `090_Author.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `091_Rating.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `092_Copyright.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `093_vars.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `096_class-ftp-pure.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `097_class-wp-customize-section.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `098_wp-load.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `099_ms-files.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `059_credits.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected '(', Syntax error, unexpected '(', Syntax error, unexpected '(' (line 137)

### 📄 `063_module.audio.dts.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_VARIABLE, Syntax error, unexpected '}', expecting EOF (line 70)

### 📄 `074_site-themes.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `075_my-sites.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `076_upgrade.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected '}' (line 49)

### 📄 `078_media.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `083_shell.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `085_async-upload.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `095_class-wp-http-ixr-client.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None


## Most Common Rules Triggered

| Rule Name | PHP Version | Files Affected |
|-----------|-------------|----------------|
| `NullToStrictStringFuncCallArgRector` | PHP 81 | 72 |
| `LongArrayToShortArrayRector` | PHP 54 | 18 |
| `ClassPropertyAssignToConstructorPromotionRector` | PHP 80 | 16 |
| `DirNameFileConstantToDirConstantRector` | PHP 53 | 15 |
| `FirstClassCallableRector` | PHP 81 | 13 |
| `TernaryToNullCoalescingRector` | PHP 70 | 10 |
| `ListToArrayDestructRector` | PHP 71 | 8 |
| `ChangeSwitchToMatchRector` | PHP 80 | 8 |
| `StringableForToStringRector` | PHP 80 | 8 |
| `StrStartsWithRector` | PHP 80 | 6 |

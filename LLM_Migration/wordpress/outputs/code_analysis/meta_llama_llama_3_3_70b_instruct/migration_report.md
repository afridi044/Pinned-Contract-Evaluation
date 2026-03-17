# LLM Migration Report - meta_llama_llama_3_3_70b_instruct

*Generated on 2026-03-17 04:42:49*

## Summary

- **Files analyzed**: 100
- **Rector errors**: 5
- **Files analyzed successfully**: 95
- **Perfect migrations** (0 changes needed): 2
- **Files needing work**: 93
- **Total remaining changes**: 352
- **Average changes per file**: 3.7

### Migration Statistics

- **Files with no changes**: 2 (2.1%)
- **Files with 1-3 changes**: 51
- **Files with 4-8 changes**: 38
- **Files with 9+ changes**: 4
- **Files with Rector errors**: 5

## File-by-File Results

### 📄 `001_getid3.lib.php`

- **Status**: success
- **Changes needed**: 11
- **PHP versions affected**: PHP 53, PHP 54, PHP 56, PHP 70, PHP 71, PHP 74, PHP 80, PHP 81, PHP 82
- **Triggered rules**: DirNameFileConstantToDirConstantRector, TernaryToElvisRector, LongArrayToShortArrayRector, PowToExpRector, BreakNotInLoopOrSwitchToReturnRector, ListToArrayDestructRector, CurlyToSquareBracketArrayStringRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `002_module.audio-video.asf.php`

- **Status**: success
- **Changes needed**: 11
- **PHP versions affected**: PHP 54, PHP 70, PHP 71, PHP 72, PHP 73, PHP 74, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, BreakNotInLoopOrSwitchToReturnRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, StringifyDefineRector, SensitiveConstantNameRector, CurlyToSquareBracketArrayStringRector, NullCoalescingOperatorRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `003_wp-db.php`

- **Status**: success
- **Changes needed**: 10
- **PHP versions affected**: PHP 53, PHP 54, PHP 55, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, LongArrayToShortArrayRector, ClassConstantToSelfClassRector, ClassOnThisVariableObjectRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, StrStartsWithRector, StrContainsRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `006_widgets.php`

- **Status**: success
- **Changes needed**: 10
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81, TypeDeclaration
- **Triggered rules**: LongArrayToShortArrayRector, Php4ConstructorRector, TernaryToNullCoalescingRector, ClassOnThisVariableObjectRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, StrStartsWithRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReturnNeverTypeRector

### 📄 `009_getid3.php`

- **Status**: success
- **Changes needed**: 8
- **PHP versions affected**: PHP 53, PHP 54, PHP 70, PHP 71, PHP 74, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, CurlyToSquareBracketArrayStringRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `012_module.audio-video.riff.php`

- **Status**: success
- **Changes needed**: 8
- **PHP versions affected**: PHP 53, PHP 54, PHP 70, PHP 71, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, StrEndsWithRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `004_class-IXR.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81, PHP 83
- **Triggered rules**: LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `007_class-wp.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, ClassPropertyAssignToConstructorPromotionRector, StrStartsWithRector, StrContainsRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `008_wp-login.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 54, PHP 70, PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, SetCookieRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `010_class-wp-theme.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 53, PHP 54, PHP 70, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `013_file.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 70, PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: TernaryToNullCoalescingRector, ListToArrayDestructRector, StringifyStrNeedlesRector, StrEndsWithRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `017_press-this.php`

- **Status**: success
- **Changes needed**: 6
- **PHP versions affected**: PHP 53, PHP 54, PHP 71, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, ListToArrayDestructRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `029_module.audio.ogg.php`

- **Status**: success
- **Changes needed**: 6
- **PHP versions affected**: PHP 54, PHP 56, PHP 70, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, PowToExpRector, ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `022_class-wp-customize-setting.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 80, PHP 81, PHP 83
- **Triggered rules**: TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector, ChangeSwitchToMatchRector, FirstClassCallableRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `024_edit-form-advanced.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 73, PHP 74, PHP 80, PHP 81
- **Triggered rules**: TernaryToNullCoalescingRector, ArrayKeyFirstLastRector, NullCoalescingOperatorRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `025_widgets.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 53, PHP 70, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, TernaryToNullCoalescingRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `033_ms-settings.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 53, PHP 71, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, ListToArrayDestructRector, StrEndsWithRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `041_module.tag.lyrics3.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, TernaryToNullCoalescingRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `042_image-edit.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, RandomFunctionRector, TernaryToNullCoalescingRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `045_Locator.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: CodeQuality, PHP 74, PHP 80, PHP 81
- **Triggered rules**: OptionalParametersAfterRequiredRector, RestoreDefaultNullToNullableTypePropertyRector, ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `047_update-core.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 53, PHP 54, PHP 70, PHP 71, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `054_nav-menu.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: CodeQuality, PHP 54, PHP 70, PHP 81
- **Triggered rules**: OptionalParametersAfterRequiredRector, LongArrayToShortArrayRector, IfIssetToCoalescingRector, TernaryToNullCoalescingRector, NullToStrictStringFuncCallArgRector

### 📄 `060_themes.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, TernaryToElvisRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `015_Diff.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 54, PHP 80, TypeDeclaration
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, ClassOnObjectRector, ReturnNeverTypeRector

### 📄 `016_module.audio.ac3.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 56, PHP 80, PHP 81
- **Triggered rules**: PowToExpRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `019_atomlib.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector, AddTypeToConstRector

### 📄 `020_class-ftp.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 73, PHP 74, PHP 80, PHP 81
- **Triggered rules**: SensitiveConstantNameRector, CurlyToSquareBracketArrayStringRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `021_class-pop3.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StringifyStrNeedlesRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `027_upload.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 70, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, TernaryToNullCoalescingRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `031_class-wp-plugins-list-table.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 71, PHP 81
- **Triggered rules**: TernaryToElvisRector, ListToArrayDestructRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `032_class-wp-comments-list-table.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: DeadCode, PHP 80, PHP 83
- **Triggered rules**: RemoveParentCallWithoutParentRector, ClassOnThisVariableObjectRector, ClassOnObjectRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `036_module.tag.apetag.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 71, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `050_class-oembed.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StringifyStrNeedlesRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `051_ms-deprecated.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `055_rss.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 71, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `056_class.akismet.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 70, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, TernaryToNullCoalescingRector, WrapVariableVariableNameInCurlyBracesRector, NullToStrictStringFuncCallArgRector

### 📄 `058_session.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 70, PHP 80
- **Triggered rules**: LongArrayToShortArrayRector, IfIssetToCoalescingRector, FinalPrivateToPrivateVisibilityRector, ClassPropertyAssignToConstructorPromotionRector

### 📄 `059_credits.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `061_class.wp-scripts.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, StrStartsWithRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `062_string.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrEndsWithRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `064_wp-diff.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 71, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, ListToArrayDestructRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `065_class-wp-image-editor-imagick.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, RemoveUnusedVariableInCatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `011_translations.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 71, PHP 83
- **Triggered rules**: DirNameFileConstantToDirConstantRector, ListToArrayDestructRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `023_module.tag.id3v1.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 80
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, StrStartsWithRector, ChangeSwitchToMatchRector

### 📄 `026_Source.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, NullToStrictStringFuncCallArgRector

### 📄 `028_themes.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `035_wp-trackback.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `037_class-wp-ms-sites-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `039_class-wp-upgrader-skins.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `040_class-wp-media-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `043_image.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81, PHP 82
- **Triggered rules**: ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `049_Parser.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

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

### 📄 `057_class-wp-customize-manager.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 81
- **Triggered rules**: IfToSpaceshipRector, TernaryToNullCoalescingRector, FirstClassCallableRector

### 📄 `066_class.wp-dependencies.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: RemoveExtraParametersRector, ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector

### 📄 `070_File.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 74, PHP 80, PHP 81
- **Triggered rules**: ClosureToArrowFunctionRector, ClassPropertyAssignToConstructorPromotionRector, ReadOnlyPropertyRector

### 📄 `071_plugins.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 54, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, NullToStrictStringFuncCallArgRector

### 📄 `073_site-settings.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `079_load-styles.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 80, PHP 81
- **Triggered rules**: MultiDirnameRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `080_site-info.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 54, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, NullToStrictStringFuncCallArgRector

### 📄 `082_import.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 54, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, LongArrayToShortArrayRector, NullToStrictStringFuncCallArgRector

### 📄 `087_Restriction.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, ReadOnlyPropertyRector

### 📄 `089_Category.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, ReadOnlyPropertyRector

### 📄 `090_Author.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, ReadOnlyPropertyRector

### 📄 `091_Rating.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, ReadOnlyPropertyRector

### 📄 `092_Copyright.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, ReadOnlyPropertyRector

### 📄 `093_vars.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrEndsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `098_wp-load.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `099_ms-files.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 80, PHP 81
- **Triggered rules**: MultiDirnameRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `034_inline.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `038_class.wp-styles.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `044_class-wp-image-editor.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70, PHP 80
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, ClassPropertyAssignToConstructorPromotionRector

### 📄 `048_about.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `067_user.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `068_revision.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `069_class-wp-image-editor-gd.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `072_class-wp-users-list-table.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `077_post-thumbnail-template.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 74
- **Triggered rules**: NullCoalescingOperatorRector, ClosureToArrowFunctionRector

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

### 📄 `088_Credit.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector

### 📄 `094_class.akismet-widget.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `018_streams.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 83
- **Triggered rules**: AddOverrideAttributeToOverriddenMethodsRector

### 📄 `046_module.audio-video.flv.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `074_site-themes.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 53
- **Triggered rules**: DirNameFileConstantToDirConstantRector

### 📄 `075_my-sites.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 53
- **Triggered rules**: DirNameFileConstantToDirConstantRector

### 📄 `076_upgrade.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 53
- **Triggered rules**: DirNameFileConstantToDirConstantRector

### 📄 `083_shell.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 72
- **Triggered rules**: StringsAssertNakedRector

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

### 📄 `100_wp-links-opml.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `005_class-snoopy.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected '}', expecting T_ENDIF (line 1138)

### 📄 `014_module.tag.id3v2.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_FOREACH, Syntax error, unexpected '}', expecting EOF (line 3343)

### 📄 `030_class-json.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_BOOLEAN_AND, Syntax error, unexpected ';', Syntax error, unexpected '}', expecting EOF (line 428)

### 📄 `063_module.audio.dts.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `078_media.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ';', expecting ',' or ']' or ')', Syntax error, unexpected '}', expecting EOF (line 20)

### 📄 `085_async-upload.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ')', expecting ']', Syntax error, unexpected ']', Syntax error, unexpected ';', expecting ',' or ']' or ')', Syntax error, unexpected ')', expecting ']', Syntax error, unexpected ';', expecting ',' or ']' or ')', Syntax error, unexpected '}', expecting EOF (line 65)

### 📄 `095_class-wp-http-ixr-client.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None


## Most Common Rules Triggered

| Rule Name | PHP Version | Files Affected |
|-----------|-------------|----------------|
| `NullToStrictStringFuncCallArgRector` | PHP 81 | 70 |
| `StrStartsWithRector` | PHP 80 | 31 |
| `StrContainsRector` | PHP 80 | 29 |
| `LongArrayToShortArrayRector` | PHP 54 | 24 |
| `ListToArrayDestructRector` | PHP 71 | 24 |
| `DirNameFileConstantToDirConstantRector` | PHP 53 | 23 |
| `ClassPropertyAssignToConstructorPromotionRector` | PHP 80 | 20 |
| `TernaryToNullCoalescingRector` | PHP 70 | 19 |
| `FirstClassCallableRector` | PHP 81 | 13 |
| `ReadOnlyPropertyRector` | PHP 81 | 9 |

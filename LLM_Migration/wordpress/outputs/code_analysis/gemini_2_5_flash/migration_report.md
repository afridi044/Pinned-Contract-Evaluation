# LLM Migration Report - gemini_2_5_flash

*Generated on 2026-03-17 08:03:19*

## Summary

- **Files analyzed**: 100
- **Rector errors**: 9
- **Files analyzed successfully**: 91
- **Perfect migrations** (0 changes needed): 13
- **Files needing work**: 78
- **Total remaining changes**: 204
- **Average changes per file**: 2.2

### Migration Statistics

- **Files with no changes**: 13 (14.3%)
- **Files with 1-3 changes**: 57
- **Files with 4-8 changes**: 19
- **Files with 9+ changes**: 2
- **Files with Rector errors**: 9

## File-by-File Results

### 📄 `002_module.audio-video.asf.php`

- **Status**: success
- **Changes needed**: 9
- **PHP versions affected**: PHP 54, PHP 70, PHP 72, PHP 73, PHP 74, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector, StringifyDefineRector, SensitiveConstantNameRector, NullCoalescingOperatorRector, StrEndsWithRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `014_module.tag.id3v2.php`

- **Status**: success
- **Changes needed**: 9
- **PHP versions affected**: PHP 54, PHP 70, PHP 71, PHP 80, PHP 81, PHP 82
- **Triggered rules**: LongArrayToShortArrayRector, ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, StrStartsWithRector, StrContainsRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `012_module.audio-video.riff.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 53, PHP 70, PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, TernaryToNullCoalescingRector, ListToArrayDestructRector, SensitiveConstantNameRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `001_getid3.lib.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 56, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, PowToExpRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `006_widgets.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassOnThisVariableObjectRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `007_class-wp.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ClassPropertyAssignToConstructorPromotionRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `008_wp-login.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 73, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, SetCookieRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `013_file.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, StringifyStrNeedlesRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `016_module.audio.ac3.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 56, PHP 80, PHP 81, PHP 83
- **Triggered rules**: PowToExpRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, AddTypeToConstRector

### 📄 `017_press-this.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 71, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `020_class-ftp.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: RemoveExtraParametersRector, ClassPropertyAssignToConstructorPromotionRector, StrContainsRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `022_class-wp-customize-setting.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 80, PHP 81, PHP 83
- **Triggered rules**: TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector, ChangeSwitchToMatchRector, FirstClassCallableRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `032_class-wp-comments-list-table.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: DeadCode, PHP 54, PHP 80, PHP 83
- **Triggered rules**: RemoveParentCallWithoutParentRector, LongArrayToShortArrayRector, ClassOnThisVariableObjectRector, ClassOnObjectRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `055_rss.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 71, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, StrContainsRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `004_class-IXR.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: AddParamBasedOnParentClassMethodRector, ClassPropertyAssignToConstructorPromotionRector, ReadOnlyPropertyRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `009_getid3.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 71, PHP 80, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `024_edit-form-advanced.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 73, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ArrayKeyFirstLastRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `031_class-wp-plugins-list-table.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, ChangeSwitchToMatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `042_image-edit.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 70, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, RandomFunctionRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `053_class-wp-themes-list-table.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ClassOnThisVariableObjectRector, ClassOnObjectRector, NullToStrictStringFuncCallArgRector

### 📄 `069_class-wp-image-editor-gd.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 54, PHP 71, PHP 74, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ListToArrayDestructRector, NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `019_atomlib.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `025_widgets.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `026_Source.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, NullToStrictStringFuncCallArgRector

### 📄 `030_class-json.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassOnObjectRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `039_class-wp-upgrader-skins.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `043_image.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 54, PHP 81, PHP 82
- **Triggered rules**: LongArrayToShortArrayRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `050_class-oembed.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 54, PHP 73, PHP 80
- **Triggered rules**: LongArrayToShortArrayRector, StringifyStrNeedlesRector, StrContainsRector

### 📄 `052_class-wp-ms-themes-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: ListToArrayDestructRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `056_class.akismet.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 70, PHP 81
- **Triggered rules**: WrapVariableVariableNameInCurlyBracesRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `061_class.wp-scripts.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `065_class-wp-image-editor-imagick.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: RemoveUnusedVariableInCatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `018_streams.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 83
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `023_module.tag.id3v1.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70, PHP 80
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, StrStartsWithRector

### 📄 `028_themes.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `035_wp-trackback.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `036_module.tag.apetag.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `038_class.wp-styles.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `041_module.tag.lyrics3.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `045_Locator.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector

### 📄 `048_about.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `058_session.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80
- **Triggered rules**: FinalPrivateToPrivateVisibilityRector, ClassPropertyAssignToConstructorPromotionRector

### 📄 `060_themes.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `064_wp-diff.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `067_user.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 54, PHP 80
- **Triggered rules**: LongArrayToShortArrayRector, StrContainsRector

### 📄 `073_site-settings.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `093_vars.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrEndsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `098_wp-load.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `099_ms-files.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `011_translations.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 83
- **Triggered rules**: AddOverrideAttributeToOverriddenMethodsRector

### 📄 `015_Diff.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassOnObjectRector

### 📄 `027_upload.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `033_ms-settings.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `037_class-wp-ms-sites-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StrContainsRector

### 📄 `040_class-wp-media-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `046_module.audio-video.flv.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `051_ms-deprecated.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `059_credits.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `068_revision.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `071_plugins.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `072_class-wp-users-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 54
- **Triggered rules**: LongArrayToShortArrayRector

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

### 📄 `080_site-info.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `081_site-new.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `082_import.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `084_class-wp-ajax-response.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassOnObjectRector

### 📄 `086_user-new.php`

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

### 📄 `094_class.akismet-widget.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector

### 📄 `095_class-wp-http-ixr-client.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `096_class-ftp-pure.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `100_wp-links-opml.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `003_wp-db.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected '*', Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',' (line 501)

### 📄 `005_class-snoopy.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_ENDIF, expecting '}' (line 1248)

### 📄 `010_class-wp-theme.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_ARRAY, expecting T_PAAMAYIM_NEKUDOTAYIM, Syntax error, unexpected T_ARRAY, expecting T_PAAMAYIM_NEKUDOTAYIM (line 318)

### 📄 `021_class-pop3.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `029_module.audio.ogg.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_STATIC, Syntax error, unexpected T_VARIABLE, expecting ')', Syntax error, unexpected T_ARRAY, expecting T_PAAMAYIM_NEKUDOTAYIM (line 353)

### 📄 `034_inline.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `044_class-wp-image-editor.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `047_update-core.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_STRING, expecting ';', Syntax error, unexpected '"', Syntax error, unexpected '(', expecting T_VARIABLE or '{' or '$', Syntax error, unexpected '.', expecting '[' or T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR or '{', Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected '}', Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected '}', expecting EOF (line 124)

### 📄 `049_Parser.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected '?', expecting T_PAAMAYIM_NEKUDOTAYIM, Syntax error, unexpected '?', expecting T_PAAMAYIM_NEKUDOTAYIM (line 141)

### 📄 `054_nav-menu.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_ARRAY, expecting T_PAAMAYIM_NEKUDOTAYIM (line 562)

### 📄 `057_class-wp-customize-manager.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_VARIABLE, expecting '[' or T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR or '{', Syntax error, unexpected T_VARIABLE, expecting '[' or T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR or '{', Syntax error, unexpected T_VARIABLE, expecting '[' or T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR or '{', Syntax error, unexpected EOF (line 442)

### 📄 `062_string.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `063_module.audio.dts.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `066_class.wp-dependencies.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `070_File.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected ',', Syntax error, unexpected '(', Syntax error, unexpected '(', Syntax error, unexpected '(', Syntax error, unexpected T_ELSE, expecting '}', Syntax error, unexpected '}', expecting EOF (line 237)

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

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

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

### 📄 `097_class-wp-customize-section.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None


## Most Common Rules Triggered

| Rule Name | PHP Version | Files Affected |
|-----------|-------------|----------------|
| `NullToStrictStringFuncCallArgRector` | PHP 81 | 55 |
| `StrContainsRector` | PHP 80 | 19 |
| `LongArrayToShortArrayRector` | PHP 54 | 17 |
| `StrStartsWithRector` | PHP 80 | 16 |
| `FirstClassCallableRector` | PHP 81 | 12 |
| `ClassPropertyAssignToConstructorPromotionRector` | PHP 80 | 11 |
| `ListToArrayDestructRector` | PHP 71 | 8 |
| `ChangeSwitchToMatchRector` | PHP 80 | 7 |
| `StringableForToStringRector` | PHP 80 | 7 |
| `AddOverrideAttributeToOverriddenMethodsRector` | PHP 83 | 6 |

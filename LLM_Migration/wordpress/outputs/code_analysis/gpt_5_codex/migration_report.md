# LLM Migration Report - gpt_5_codex

*Generated on 2026-03-17 08:12:52*

## Summary

- **Files analyzed**: 100
- **Rector errors**: 12
- **Files analyzed successfully**: 88
- **Perfect migrations** (0 changes needed): 15
- **Files needing work**: 73
- **Total remaining changes**: 154
- **Average changes per file**: 1.8

### Migration Statistics

- **Files with no changes**: 15 (17.0%)
- **Files with 1-3 changes**: 61
- **Files with 4-8 changes**: 12
- **Files with 9+ changes**: 0
- **Files with Rector errors**: 12

## File-by-File Results

### 📄 `002_module.audio-video.asf.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 70, PHP 72, PHP 73, PHP 74, PHP 80, PHP 81
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, TernaryToNullCoalescingRector, StringifyDefineRector, SensitiveConstantNameRector, NullCoalescingOperatorRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `003_wp-db.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 54, PHP 55, PHP 80, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, ClassConstantToSelfClassRector, ClassPropertyAssignToConstructorPromotionRector, StrStartsWithRector, StrContainsRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `006_widgets.php`

- **Status**: success
- **Changes needed**: 6
- **PHP versions affected**: PHP 70, PHP 80, PHP 81, TypeDeclaration
- **Triggered rules**: Php4ConstructorRector, ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReturnNeverTypeRector

### 📄 `005_class-snoopy.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 54, PHP 71, PHP 74, PHP 81
- **Triggered rules**: LongArrayToShortArrayRector, AssignArrayToStringRector, IsIterableRector, NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `013_file.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 71, PHP 73, PHP 80, PHP 81
- **Triggered rules**: TernaryToNullCoalescingRector, ListToArrayDestructRector, StringifyStrNeedlesRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `022_class-wp-customize-setting.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 80, PHP 81, PHP 83
- **Triggered rules**: TernaryToNullCoalescingRector, ClassPropertyAssignToConstructorPromotionRector, ChangeSwitchToMatchRector, FirstClassCallableRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `030_class-json.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `007_class-wp.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `010_class-wp-theme.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: CodingStyle, PHP 80
- **Triggered rules**: ConsistentImplodeRector, ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, ChangeSwitchToMatchRector

### 📄 `016_module.audio.ac3.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 56, PHP 80, PHP 81
- **Triggered rules**: PowToExpRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `031_class-wp-plugins-list-table.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 80, PHP 81
- **Triggered rules**: TernaryToElvisRector, ChangeSwitchToMatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `032_class-wp-comments-list-table.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: DeadCode, PHP 80, PHP 83
- **Triggered rules**: RemoveParentCallWithoutParentRector, ClassOnThisVariableObjectRector, ClassOnObjectRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `024_edit-form-advanced.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 73, PHP 74, PHP 81
- **Triggered rules**: ArrayKeyFirstLastRector, NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `026_Source.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector, NullToStrictStringFuncCallArgRector

### 📄 `052_class-wp-ms-themes-list-table.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ChangeSwitchToMatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `055_rss.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 74, PHP 81
- **Triggered rules**: NullCoalescingOperatorRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `062_string.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrEndsWithRector, StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `065_class-wp-image-editor-imagick.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: RemoveUnusedVariableInCatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `008_wp-login.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 73, PHP 81
- **Triggered rules**: SetCookieRector, NullToStrictStringFuncCallArgRector

### 📄 `017_press-this.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: ListToArrayDestructRector, NullToStrictStringFuncCallArgRector

### 📄 `018_streams.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 83
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `025_widgets.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StrStartsWithRector, NullToStrictStringFuncCallArgRector

### 📄 `029_module.audio.ogg.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70, PHP 81
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, NullToStrictStringFuncCallArgRector

### 📄 `039_class-wp-upgrader-skins.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `042_image-edit.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 74, PHP 81
- **Triggered rules**: NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `043_image.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 82
- **Triggered rules**: NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `044_class-wp-image-editor.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70, PHP 80
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, ClassPropertyAssignToConstructorPromotionRector

### 📄 `045_Locator.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector

### 📄 `046_module.audio-video.flv.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, ReadOnlyPropertyRector

### 📄 `056_class.akismet.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

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

### 📄 `064_wp-diff.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `069_class-wp-image-editor-gd.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `088_Credit.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector

### 📄 `089_Category.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector

### 📄 `091_Rating.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StringableForToStringRector, ReadOnlyPropertyRector

### 📄 `092_Copyright.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, StringableForToStringRector

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

### 📄 `019_atomlib.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector

### 📄 `021_class-pop3.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 83
- **Triggered rules**: AddTypeToConstRector

### 📄 `023_module.tag.id3v1.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StrStartsWithRector

### 📄 `028_themes.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `034_inline.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StrStartsWithRector

### 📄 `035_wp-trackback.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `036_module.tag.apetag.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `037_class-wp-ms-sites-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 74
- **Triggered rules**: ClosureToArrowFunctionRector

### 📄 `040_class-wp-media-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `047_update-core.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `048_about.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `053_class-wp-themes-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `059_credits.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 54
- **Triggered rules**: LongArrayToShortArrayRector

### 📄 `066_class.wp-dependencies.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `070_File.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `071_plugins.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `073_site-settings.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `074_site-themes.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 74
- **Triggered rules**: NullCoalescingOperatorRector

### 📄 `076_upgrade.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 54
- **Triggered rules**: LongArrayToShortArrayRector

### 📄 `077_post-thumbnail-template.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 74
- **Triggered rules**: NullCoalescingOperatorRector

### 📄 `078_media.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 74
- **Triggered rules**: NullCoalescingOperatorRector

### 📄 `079_load-styles.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `081_site-new.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `084_class-wp-ajax-response.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassOnObjectRector

### 📄 `087_Restriction.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `090_Author.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: StringableForToStringRector

### 📄 `093_vars.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `094_class.akismet-widget.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector

### 📄 `096_class-ftp-pure.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 70
- **Triggered rules**: Php4ConstructorRector

### 📄 `097_class-wp-customize-section.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector

### 📄 `099_ms-files.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `100_wp-links-opml.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `001_getid3.lib.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected '}', expecting EOF (line 151)

### 📄 `004_class-IXR.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected '$', expecting T_VARIABLE, Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected '}', expecting EOF (line 182)

### 📄 `009_getid3.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_ELSE, Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected '}', expecting EOF (line 1079)

### 📄 `012_module.audio-video.riff.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, expecting ']', Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected T_STRING, expecting ')', Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected ':', Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected T_CONSTANT_ENCAPSED_STRING, Syntax error, unexpected T_CASE, Syntax error, unexpected T_DEFAULT, Syntax error, unexpected T_DEFAULT, Syntax error, unexpected T_IF (line 601)

### 📄 `014_module.tag.id3v2.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_PUBLIC, Syntax error, unexpected '`', Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, expecting '-' or T_STRING or T_VARIABLE or T_NUM_STRING, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE (line 519)

### 📄 `020_class-ftp.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_STRING, expecting ')', Syntax error, unexpected T_STRING, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, expecting '-' or T_STRING or T_VARIABLE or T_NUM_STRING, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected T_ENCAPSED_AND_WHITESPACE, Syntax error, unexpected ')', Syntax error, unexpected T_ELSE (line 954)

### 📄 `027_upload.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `033_ms-settings.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected '<' (line 221)

### 📄 `038_class.wp-styles.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `041_module.tag.lyrics3.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected EOF, expecting '[' or T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR or '{' (line 117)

### 📄 `049_Parser.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected '?', expecting T_PAAMAYIM_NEKUDOTAYIM, Syntax error, unexpected T_ARRAY, expecting T_PAAMAYIM_NEKUDOTAYIM, Syntax error, unexpected '?', expecting T_PAAMAYIM_NEKUDOTAYIM (line 132)

### 📄 `050_class-oembed.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected '?', expecting T_PAAMAYIM_NEKUDOTAYIM (line 609)

### 📄 `051_ms-deprecated.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `054_nav-menu.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `057_class-wp-customize-manager.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_PUBLIC, Syntax error, unexpected '}', expecting EOF (line 1219)

### 📄 `063_module.audio.dts.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `067_user.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `068_revision.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',' (line 320)

### 📄 `072_class-wp-users-list-table.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `075_my-sites.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `080_site-info.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `082_import.php`

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

### 📄 `086_user-new.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `095_class-wp-http-ixr-client.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `098_wp-load.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None


## Most Common Rules Triggered

| Rule Name | PHP Version | Files Affected |
|-----------|-------------|----------------|
| `NullToStrictStringFuncCallArgRector` | PHP 81 | 42 |
| `ClassPropertyAssignToConstructorPromotionRector` | PHP 80 | 18 |
| `FirstClassCallableRector` | PHP 81 | 13 |
| `NullCoalescingOperatorRector` | PHP 74 | 8 |
| `StrStartsWithRector` | PHP 80 | 8 |
| `StringableForToStringRector` | PHP 80 | 8 |
| `ChangeSwitchToMatchRector` | PHP 80 | 7 |
| `ClassOnObjectRector` | PHP 80 | 5 |
| `AddOverrideAttributeToOverriddenMethodsRector` | PHP 83 | 5 |
| `LongArrayToShortArrayRector` | PHP 54 | 4 |

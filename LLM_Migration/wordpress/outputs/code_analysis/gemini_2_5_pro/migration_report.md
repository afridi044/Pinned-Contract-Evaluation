# LLM Migration Report - gemini_2_5_pro

*Generated on 2026-03-17 04:32:20*

## Summary

- **Files analyzed**: 100
- **Rector errors**: 3
- **Files analyzed successfully**: 97
- **Perfect migrations** (0 changes needed): 18
- **Files needing work**: 79
- **Total remaining changes**: 141
- **Average changes per file**: 1.5

### Migration Statistics

- **Files with no changes**: 18 (18.6%)
- **Files with 1-3 changes**: 70
- **Files with 4-8 changes**: 9
- **Files with 9+ changes**: 0
- **Files with Rector errors**: 3

## File-by-File Results

### 📄 `014_module.tag.id3v2.php`

- **Status**: success
- **Changes needed**: 7
- **PHP versions affected**: PHP 54, PHP 70, PHP 71, PHP 80, PHP 81, PHP 82
- **Triggered rules**: LongArrayToShortArrayRector, ThisCallOnStaticMethodToStaticCallRector, ListToArrayDestructRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `002_module.audio-video.asf.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 70, PHP 72, PHP 73, PHP 74, PHP 81
- **Triggered rules**: ThisCallOnStaticMethodToStaticCallRector, StringifyDefineRector, SensitiveConstantNameRector, NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `004_class-IXR.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: AddParamBasedOnParentClassMethodRector, ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `012_module.audio-video.riff.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 73, PHP 80, PHP 81
- **Triggered rules**: SensitiveConstantNameRector, StrEndsWithRector, StrStartsWithRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector

### 📄 `030_class-json.php`

- **Status**: success
- **Changes needed**: 5
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: ClassOnObjectRector, ChangeSwitchToMatchRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector, AddTypeToConstRector

### 📄 `001_getid3.lib.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 53, PHP 56, PHP 81, PHP 82
- **Triggered rules**: DirNameFileConstantToDirConstantRector, PowToExpRector, NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `006_widgets.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, ClassOnObjectRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `007_class-wp.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `019_atomlib.php`

- **Status**: success
- **Changes needed**: 4
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector, AddTypeToConstRector

### 📄 `003_wp-db.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 55, PHP 80, PHP 81
- **Triggered rules**: ClassConstantToSelfClassRector, StrContainsRector, NullToStrictStringFuncCallArgRector

### 📄 `008_wp-login.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 53, PHP 73, PHP 81
- **Triggered rules**: DirNameFileConstantToDirConstantRector, SetCookieRector, NullToStrictStringFuncCallArgRector

### 📄 `013_file.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 71, PHP 73, PHP 81
- **Triggered rules**: ListToArrayDestructRector, StringifyStrNeedlesRector, NullToStrictStringFuncCallArgRector

### 📄 `018_streams.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, ReadOnlyPropertyRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `020_class-ftp.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `022_class-wp-customize-setting.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81, PHP 83
- **Triggered rules**: ClassPropertyAssignToConstructorPromotionRector, FirstClassCallableRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `024_edit-form-advanced.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 73, PHP 74, PHP 81
- **Triggered rules**: ArrayKeyFirstLastRector, NullCoalescingOperatorRector, NullToStrictStringFuncCallArgRector

### 📄 `065_class-wp-image-editor-imagick.php`

- **Status**: success
- **Changes needed**: 3
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: RemoveUnusedVariableInCatchRector, FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `005_class-snoopy.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 71, PHP 81
- **Triggered rules**: AssignArrayToStringRector, NullToStrictStringFuncCallArgRector

### 📄 `016_module.audio.ac3.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: NullToStrictStringFuncCallArgRector, AddTypeToConstRector

### 📄 `026_Source.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 80, PHP 81
- **Triggered rules**: StringableForToStringRector, NullToStrictStringFuncCallArgRector

### 📄 `029_module.audio.ogg.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: NullToStrictStringFuncCallArgRector, AddTypeToConstRector

### 📄 `031_class-wp-plugins-list-table.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `039_class-wp-upgrader-skins.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 83
- **Triggered rules**: NullToStrictStringFuncCallArgRector, AddOverrideAttributeToOverriddenMethodsRector

### 📄 `043_image.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81, PHP 82
- **Triggered rules**: NullToStrictStringFuncCallArgRector, Utf8DecodeEncodeToMbConvertEncodingRector

### 📄 `049_Parser.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `052_class-wp-ms-themes-list-table.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector, ReadOnlyPropertyRector

### 📄 `054_nav-menu.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 70, PHP 81
- **Triggered rules**: IfToSpaceshipRector, NullToStrictStringFuncCallArgRector

### 📄 `056_class.akismet.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `061_class.wp-scripts.php`

- **Status**: success
- **Changes needed**: 2
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector, NullToStrictStringFuncCallArgRector

### 📄 `011_translations.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 83
- **Triggered rules**: AddOverrideAttributeToOverriddenMethodsRector

### 📄 `017_press-this.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `025_widgets.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `027_upload.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `028_themes.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `032_class-wp-comments-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: DeadCode
- **Triggered rules**: RemoveParentCallWithoutParentRector

### 📄 `033_ms-settings.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

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
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `040_class-wp-media-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `041_module.tag.lyrics3.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `042_image-edit.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `044_class-wp-image-editor.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `045_Locator.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `046_module.audio-video.flv.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 83
- **Triggered rules**: AddTypeToConstRector

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

### 📄 `050_class-oembed.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 73
- **Triggered rules**: StringifyStrNeedlesRector

### 📄 `051_ms-deprecated.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `053_class-wp-themes-list-table.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `057_class-wp-customize-manager.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector

### 📄 `058_session.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 80
- **Triggered rules**: FinalPrivateToPrivateVisibilityRector

### 📄 `059_credits.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `060_themes.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `064_wp-diff.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: FirstClassCallableRector

### 📄 `068_revision.php`

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

### 📄 `071_plugins.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `078_media.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 53
- **Triggered rules**: DirNameFileConstantToDirConstantRector

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

### 📄 `100_wp-links-opml.php`

- **Status**: success
- **Changes needed**: 1
- **PHP versions affected**: PHP 81
- **Triggered rules**: NullToStrictStringFuncCallArgRector

### 📄 `009_getid3.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_COALESCE_EQUAL, expecting ';', Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_ELSE, Syntax error, unexpected T_DOUBLE_ARROW, Syntax error, unexpected ',', Syntax error, unexpected ',' (line 1111)

### 📄 `010_class-wp-theme.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_PUBLIC, Syntax error, unexpected T_PUBLIC, Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',', Syntax error, unexpected ',' (line 297)

### 📄 `015_Diff.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `021_class-pop3.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `023_module.tag.id3v1.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `034_inline.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `038_class.wp-styles.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `055_rss.php`

- **Status**: error
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

- **Rector error**: Syntax error, unexpected T_CONCAT_EQUAL, Syntax error, unexpected T_CONCAT_EQUAL, Syntax error, unexpected T_CONCAT_EQUAL, Syntax error, unexpected T_CONCAT_EQUAL (line 193)

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

### 📄 `067_user.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `072_class-wp-users-list-table.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

### 📄 `073_site-settings.php`

- **Status**: success
- **Changes needed**: 0
- **PHP versions affected**: None
- **Triggered rules**: None

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

### 📄 `077_post-thumbnail-template.php`

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
| `NullToStrictStringFuncCallArgRector` | PHP 81 | 59 |
| `FirstClassCallableRector` | PHP 81 | 11 |
| `ClassPropertyAssignToConstructorPromotionRector` | PHP 80 | 8 |
| `ReadOnlyPropertyRector` | PHP 81 | 8 |
| `StringableForToStringRector` | PHP 80 | 7 |
| `AddOverrideAttributeToOverriddenMethodsRector` | PHP 83 | 5 |
| `AddTypeToConstRector` | PHP 83 | 5 |
| `DirNameFileConstantToDirConstantRector` | PHP 53 | 3 |
| `Utf8DecodeEncodeToMbConvertEncodingRector` | PHP 82 | 3 |
| `ClassOnObjectRector` | PHP 80 | 3 |

# Rector Professional Analysis Report

## Executive Summary

This report provides detailed analysis of 100 WordPress 4.0 PHP files using Rector professional migration tool.

## PHP Version Migration Analysis

### Version-Specific Upgrade Opportunities

**PHP.52 Features**
- Files ready for upgrade: 41
- Total upgrade opportunities: 41
- Average per file: 1.0

**PHP.53 Features**
- Files ready for upgrade: 126
- Total upgrade opportunities: 131
- Average per file: 1.0

**PHP.54 Features**
- Files ready for upgrade: 327
- Total upgrade opportunities: 327
- Average per file: 1.0

**PHP.55 Features**
- Files ready for upgrade: 4
- Total upgrade opportunities: 4
- Average per file: 1.0

**PHP.56 Features**
- Files ready for upgrade: 5
- Total upgrade opportunities: 5
- Average per file: 1.0

**PHP.70 Features**
- Files ready for upgrade: 137
- Total upgrade opportunities: 158
- Average per file: 1.2

**PHP.71 Features**
- Files ready for upgrade: 65
- Total upgrade opportunities: 68
- Average per file: 1.0

**PHP.72 Features**
- Files ready for upgrade: 7
- Total upgrade opportunities: 8
- Average per file: 1.1

**PHP.73 Features**
- Files ready for upgrade: 13
- Total upgrade opportunities: 13
- Average per file: 1.0

**PHP.74 Features**
- Files ready for upgrade: 17
- Total upgrade opportunities: 18
- Average per file: 1.1

**PHP.80 Features**
- Files ready for upgrade: 168
- Total upgrade opportunities: 291
- Average per file: 1.7

**PHP.81 Features**
- Files ready for upgrade: 217
- Total upgrade opportunities: 238
- Average per file: 1.1

**PHP.82 Features**
- Files ready for upgrade: 4
- Total upgrade opportunities: 4
- Average per file: 1.0

**PHP.83 Features**
- Files ready for upgrade: 8
- Total upgrade opportunities: 8
- Average per file: 1.0


## File Size Distribution Analysis

### Dataset Categorization by Lines of Code

| Category | LOC Range | File Count | Percentage | Avg LOC | Avg Changes | Change Density |
|----------|-----------|------------|------------|---------|-------------|----------------|
| **Small** | 1-200 | 237 | 49.2% | 72 | 1.1 | 1.49% |
| **Medium** | 201-500 | 120 | 24.9% | 333 | 3.3 | 1.00% |
| **Large** | 501-1000 | 56 | 11.6% | 696 | 4.2 | 0.61% |
| **Extra Large** | 1000+ | 69 | 14.3% | 2202 | 6.4 | 0.29% |

### Category Analysis Insights

**Small Files (1-200 LOC)**
- Count: 237 files
- Purpose: Utility functions, simple configurations, focused components
- Migration Pattern: Quick fixes, minimal context required
- Research Value: Testing LLM accuracy on simple, well-defined tasks

**Medium Files (201-500 LOC)**
- Count: 120 files
- Purpose: Standard WordPress components, moderate complexity
- Migration Pattern: Balanced mix of upgrades and quality improvements
- Research Value: Optimal for comparing LLM vs professional tool performance

**Large Files (501-1000 LOC)**
- Count: 56 files
- Purpose: Core WordPress functionality, complex business logic
- Migration Pattern: Higher change density, multiple migration opportunities
- Research Value: Testing LLM capability with substantial context requirements

**Extra Large Files (1000+ LOC)**
- Count: 69 files
- Purpose: Major WordPress core files, comprehensive functionality
- Migration Pattern: Highest absolute change counts, complex migrations
- Research Value: Maximum context window testing, real-world complexity assessment

## File-by-File Analysis Summary - Version Specific

| File ID | Filename | LOC | PHP Version Changes |
|---------|----------|-----|-------------------|
| 001 | 001_class-pclzip.php | 5687 | 7 |
| 002 | 002_post.php | 5840 | 9 |
| 003 | 003_class-wp-xmlrpc-server.php | 5982 | 6 |
| 004 | 004_functions.php | 4630 | 9 |
| 005 | 005_module.tag.id3v2.php | 3414 | 9 |
| 006 | 006_formatting.php | 3994 | 8 |
| 007 | 007_taxonomy.php | 4002 | 6 |
| 008 | 008_query.php | 4659 | 7 |
| 009 | 009_module.audio-video.asf.php | 2019 | 12 |
| 010 | 010_module.audio-video.quicktime.php | 2221 | 5 |
| 011 | 011_media.php | 3319 | 6 |
| 012 | 012_module.audio-video.riff.php | 2435 | 9 |
| 013 | 013_class-phpmailer.php | 3265 | 8 |
| 014 | 014_module.audio-video.matroska.php | 1765 | 5 |
| 015 | 015_deprecated.php | 3501 | 7 |
| 016 | 016_module.audio.mp3.php | 2009 | 7 |
| 017 | 017_general-template.php | 2982 | 5 |
| 018 | 018_Item.php | 2964 | 7 |
| 019 | 019_media.php | 2988 | 6 |
| 020 | 020_link-template.php | 3139 | 6 |
| 021 | 021_class-wp-upgrader.php | 2676 | 8 |
| 022 | 022_class-simplepie.php | 3119 | 10 |
| 023 | 023_comment.php | 2561 | 7 |
| 024 | 024_ms-functions.php | 2474 | 6 |
| 025 | 025_template.php | 2157 | 4 |
| 026 | 026_comment-template.php | 2264 | 3 |
| 027 | 027_ajax-actions.php | 2761 | 7 |
| 028 | 028_pluggable.php | 2277 | 8 |
| 029 | 029_class-http.php | 2173 | 8 |
| 030 | 030_upgrade.php | 2216 | 4 |
| 031 | 031_user.php | 2247 | 6 |
| 032 | 032_plugin.php | 1878 | 5 |
| 033 | 033_rewrite.php | 2180 | 8 |
| 034 | 034_getid3.php | 1776 | 8 |
| 035 | 035_theme.php | 2014 | 7 |
| 036 | 036_wp-db.php | 2186 | 12 |
| 037 | 037_post-template.php | 1766 | 7 |
| 038 | 038_post.php | 1661 | 6 |
| 039 | 039_nav-menu.php | 1328 | 2 |
| 040 | 040_Misc.php | 2247 | 8 |
| 041 | 041_default-widgets.php | 1423 | 5 |
| 042 | 042_class-wp-editor.php | 1433 | 0 |
| 043 | 043_class-wp-customize-widgets.php | 1556 | 5 |
| 044 | 044_script-loader.php | 1042 | 3 |
| 045 | 045_widgets.php | 1514 | 10 |
| 046 | 046_dashboard.php | 1333 | 6 |
| 047 | 047_meta-boxes.php | 1119 | 4 |
| 048 | 048_update-core.php | 1151 | 6 |
| 049 | 049_file.php | 1150 | 8 |
| 050 | 050_category-template.php | 1407 | 3 |
| 051 | 051_custom-header.php | 1366 | 6 |
| 052 | 052_getid3.lib.php | 1342 | 12 |
| 053 | 053_option.php | 1440 | 4 |
| 054 | 054_media-template.php | 1208 | 3 |
| 055 | 055_kses.php | 1518 | 4 |
| 056 | 056_class-wp-posts-list-table.php | 1301 | 5 |
| 057 | 057_class-json.php | 936 | 6 |
| 058 | 058_class-wp-theme.php | 1235 | 8 |
| 059 | 059_capabilities.php | 1539 | 5 |
| 060 | 060_nav-menus.php | 798 | 3 |
| 061 | 061_class-snoopy.php | 1256 | 5 |
| 062 | 062_meta.php | 1221 | 4 |
| 063 | 063_class.akismet.php | 933 | 4 |
| 064 | 064_class.akismet-admin.php | 862 | 4 |
| 065 | 065_schema.php | 1037 | 2 |
| 066 | 066_wp-login.php | 952 | 8 |
| 067 | 067_class-IXR.php | 1100 | 10 |
| 068 | 068_class-wp-customize-manager.php | 1272 | 4 |
| 069 | 069_screen.php | 1179 | 8 |
| 070 | 070_plugin.php | 920 | 4 |
| 071 | 071_module.audio.ogg.php | 671 | 6 |
| 072 | 072_image-edit.php | 828 | 5 |
| 073 | 073_edit-form-advanced.php | 636 | 6 |
| 074 | 074_class-wp-customize-control.php | 1124 | 6 |
| 075 | 075_deprecated.php | 1190 | 5 |
| 076 | 076_l10n.php | 901 | 4 |
| 077 | 077_ms.php | 814 | 2 |
| 078 | 078_nav-menu.php | 895 | 5 |
| 079 | 079_class-wp-upgrader-skins.php | 767 | 5 |
| 080 | 080_class-smtp.php | 943 | 4 |
| 081 | 081_IRI.php | 1238 | 9 |
| 082 | 082_class-wp-list-table.php | 1080 | 7 |
| 083 | 083_update-core.php | 649 | 5 |
| 084 | 084_Enclosure.php | 1380 | 5 |
| 085 | 085_class-ftp.php | 907 | 7 |
| 086 | 086_press-this.php | 691 | 7 |
| 087 | 087_network.php | 561 | 4 |
| 088 | 088_wp-signup.php | 749 | 4 |
| 089 | 089_ms-blogs.php | 939 | 3 |
| 090 | 090_canonical.php | 586 | 3 |
| 091 | 091_nav-menu-template.php | 678 | 3 |
| 092 | 092_load.php | 828 | 4 |
| 093 | 093_misc.php | 845 | 3 |
| 094 | 094_admin-bar.php | 868 | 2 |
| 095 | 095_class-wp-plugins-list-table.php | 605 | 6 |
| 096 | 096_class-wp.php | 782 | 9 |
| 097 | 097_update.php | 674 | 4 |
| 098 | 098_module.audio-video.flv.php | 729 | 5 |
| 099 | 099_rss.php | 936 | 5 |
| 100 | 100_class-wp-filesystem-base.php | 815 | 2 |
| 101 | 101_class-oembed.php | 579 | 5 |
| 102 | 102_plugin-install.php | 544 | 2 |
| 103 | 103_class-wp-comments-list-table.php | 636 | 6 |
| 104 | 104_user-edit.php | 557 | 3 |
| 105 | 105_class-pop3.php | 652 | 7 |
| 106 | 106_Source.php | 611 | 6 |
| 107 | 107_edit-tags.php | 592 | 3 |
| 108 | 108_plugins.php | 455 | 4 |
| 110 | 110_image.php | 598 | 5 |
| 111 | 111_cache.php | 704 | 1 |
| 112 | 112_module.audio.ac3.php | 473 | 7 |
| 113 | 113_user-new.php | 439 | 4 |
| 114 | 114_class-wp-media-list-table.php | 564 | 5 |
| 115 | 115_revision.php | 657 | 4 |
| 116 | 116_export.php | 508 | 3 |
| 117 | 117_module.audio.flac.php | 442 | 3 |
| 118 | 118_continents-cities.php | 493 | 0 |
| 119 | 119_feed.php | 659 | 4 |
| 120 | 120_functions.php | 531 | 2 |
| 121 | 121_functions.php | 499 | 1 |
| 122 | 122_Entities.php | 617 | 4 |
| 123 | 123_custom-background.php | 482 | 3 |
| 124 | 124_date.php | 452 | 2 |
| 125 | 125_theme.php | 457 | 3 |
| 126 | 126_themes.php | 374 | 5 |
| 127 | 127_functions.php | 515 | 1 |
| 128 | 128_update.php | 432 | 3 |
| 129 | 129_http.php | 551 | 2 |
| 130 | 130_module.tag.apetag.php | 370 | 5 |
| 131 | 131_users.php | 460 | 2 |
| 132 | 132_native.php | 436 | 4 |
| 133 | 133_Sanitize.php | 554 | 3 |
| 134 | 134_widgets.php | 442 | 6 |
| 135 | 135_ms-load.php | 458 | 3 |
| 136 | 136_settings.php | 347 | 2 |
| 137 | 137_class-wp-ms-sites-list-table.php | 402 | 5 |
| 138 | 138_options-permalink.php | 294 | 3 |
| 139 | 139_featured-content.php | 533 | 4 |
| 140 | 140_class-wp-plugin-install-list-table.php | 488 | 4 |
| 141 | 141_class-wp-admin-bar.php | 517 | 3 |
| 142 | 142_class-wp-ms-themes-list-table.php | 459 | 5 |
| 143 | 143_author-template.php | 471 | 2 |
| 144 | 144_user.php | 442 | 4 |
| 145 | 145_default-filters.php | 306 | 1 |
| 146 | 146_class-wp-terms-list-table.php | 466 | 3 |
| 147 | 147_edit.php | 330 | 4 |
| 148 | 148_class-wp-customize-setting.php | 554 | 6 |
| 149 | 149_options-general.php | 355 | 3 |
| 150 | 150_cron.php | 468 | 3 |
| 151 | 151_wp-diff.php | 523 | 5 |
| 152 | 152_class-wp-theme-install-list-table.php | 431 | 4 |
| 153 | 153_options-discussion.php | 273 | 2 |
| 154 | 154_class-wp-image-editor-imagick.php | 511 | 5 |
| 155 | 155_template.php | 505 | 1 |
| 156 | 156_bookmark.php | 416 | 2 |
| 157 | 157_class-wp-users-list-table.php | 459 | 4 |
| 158 | 158_class-wp-walker.php | 471 | 2 |
| 159 | 159_setup-config.php | 345 | 4 |
| 160 | 160_menu.php | 255 | 2 |
| 161 | 161_class-wp-image-editor-gd.php | 459 | 4 |
| 162 | 162_class.wp-dependencies.php | 509 | 5 |
| 163 | 163_locale.php | 368 | 3 |
| 164 | 164_MySQL.php | 438 | 3 |
| 165 | 165_upload.php | 292 | 6 |
| 166 | 166_class-wp-filesystem-ssh2.php | 392 | 2 |
| 167 | 167_Diff.php | 450 | 8 |
| 168 | 168_install.php | 305 | 2 |
| 169 | 169_Parser.php | 407 | 5 |
| 170 | 170_site-users.php | 319 | 3 |
| 171 | 171_shortcodes.php | 410 | 3 |
| 172 | 172_edit-comments.php | 254 | 4 |
| 173 | 173_class-wp-embed.php | 373 | 3 |
| 174 | 174_class-wp-image-editor.php | 471 | 5 |
| 175 | 175_module.tag.id3v1.php | 359 | 6 |
| 176 | 176_bookmark-template.php | 298 | 3 |
| 177 | 177_po.php | 384 | 0 |
| 178 | 178_plugin-editor.php | 279 | 3 |
| 179 | 179_class-wp-filesystem-ftpext.php | 415 | 3 |
| 180 | 180_category.php | 343 | 1 |
| 181 | 181_Locator.php | 372 | 5 |
| 182 | 182_wp-settings.php | 374 | 1 |
| 183 | 183_theme-install.php | 278 | 3 |
| 184 | 184_module.tag.lyrics3.php | 294 | 5 |
| 185 | 185_users.php | 296 | 2 |
| 186 | 186_themes.php | 266 | 6 |
| 187 | 187_atomlib.php | 352 | 8 |
| 188 | 188_Parser.php | 500 | 4 |
| 189 | 189_functions.wp-scripts.php | 258 | 1 |
| 190 | 190_options.php | 265 | 4 |
| 191 | 191_module.audio.dts.php | 290 | 4 |
| 192 | 192_admin.php | 347 | 2 |
| 193 | 193_theme-editor.php | 243 | 3 |
| 194 | 194_update.php | 272 | 4 |
| 195 | 195_class-wp-ms-users-list-table.php | 303 | 4 |
| 196 | 196_session.php | 425 | 4 |
| 197 | 197_notice.php | 102 | 1 |
| 198 | 198_options-writing.php | 194 | 2 |
| 199 | 199_widgets.php | 269 | 2 |
| 200 | 200_functions.wp-styles.php | 245 | 2 |
| 201 | 201_class-wp-filesystem-direct.php | 384 | 2 |
| 202 | 202_wrapper.php | 293 | 1 |
| 203 | 203_File.php | 292 | 4 |
| 204 | 204_class-wp-themes-list-table.php | 279 | 5 |
| 205 | 205_customize.php | 278 | 4 |
| 206 | 206_options-reading.php | 184 | 2 |
| 207 | 207_comment.php | 299 | 4 |
| 208 | 208_ms-deprecated.php | 347 | 5 |
| 209 | 209_sites.php | 275 | 3 |
| 210 | 210_post.php | 318 | 4 |
| 211 | 211_bookmark.php | 305 | 2 |
| 212 | 212_gzdecode.php | 371 | 3 |
| 213 | 213_class-ftp-sockets.php | 250 | 4 |
| 214 | 214_ms-settings.php | 213 | 6 |
| 215 | 215_config.php | 174 | 1 |
| 216 | 216_widgets.php | 245 | 3 |
| 217 | 217_class-wp-filesystem-ftpsockets.php | 352 | 2 |
| 218 | 218_string.php | 248 | 5 |
| 219 | 219_export.php | 243 | 2 |
| 220 | 220_revision.php | 221 | 2 |
| 221 | 221_wp-mail.php | 260 | 4 |
| 222 | 222_menu.php | 322 | 4 |
| 223 | 223_translation-install.php | 240 | 1 |
| 224 | 224_Sniffer.php | 332 | 4 |
| 225 | 225_default-constants.php | 323 | 2 |
| 226 | 226_site-info.php | 178 | 3 |
| 227 | 227_menu-header.php | 227 | 4 |
| 228 | 228_revision.php | 228 | 2 |
| 229 | 229_about.php | 193 | 5 |
| 230 | 230_mo.php | 262 | 4 |
| 231 | 231_IPv6.php | 276 | 4 |
| 232 | 232_translations.php | 275 | 8 |
| 233 | 233_site-settings.php | 173 | 4 |
| 234 | 234_site-themes.php | 185 | 3 |
| 235 | 235_Parser.php | 362 | 2 |
| 236 | 236_taxonomy.php | 284 | 2 |
| 237 | 237_class-phpass.php | 260 | 4 |
| 238 | 238_edit-tag-form.php | 204 | 1 |
| 239 | 239_post-formats.php | 243 | 2 |
| 240 | 240_class-wp-importer.php | 302 | 4 |
| 241 | 241_credits.php | 192 | 5 |
| 242 | 242_Renderer.php | 235 | 3 |
| 243 | 243_admin-header.php | 243 | 3 |
| 244 | 244_start.php | 95 | 1 |
| 245 | 245_custom-header.php | 227 | 1 |
| 246 | 246_site-new.php | 153 | 3 |
| 247 | 247_class.wp-scripts.php | 247 | 5 |
| 248 | 248_theme-install.php | 205 | 1 |
| 249 | 249_edit-form-comment.php | 160 | 2 |
| 250 | 250_import.php | 206 | 2 |
| 251 | 251_template-tags.php | 203 | 1 |
| 252 | 252_class-wp-links-list-table.php | 207 | 3 |
| 253 | 253_edit-link-form.php | 150 | 1 |
| 254 | 254_Registry.php | 225 | 1 |
| 255 | 255_index.php | 131 | 2 |
| 256 | 256_class-wp-error.php | 261 | 2 |
| 257 | 257_options-media.php | 136 | 2 |
| 258 | 258_pluggable-deprecated.php | 192 | 1 |
| 259 | 259_class.wp-styles.php | 210 | 5 |
| 260 | 260_inline.php | 206 | 6 |
| 261 | 261_repair.php | 124 | 2 |
| 262 | 262_class-ftp-pure.php | 190 | 3 |
| 263 | 263_install-helper.php | 199 | 1 |
| 264 | 264_vars.php | 144 | 3 |
| 265 | 265_import.php | 132 | 3 |
| 266 | 266_comments-popup.php | 128 | 0 |
| 267 | 267_media.php | 146 | 3 |
| 268 | 268_customizer.php | 109 | 1 |
| 269 | 269_shell.php | 162 | 3 |
| 270 | 270_class-wp-ajax-response.php | 199 | 3 |
| 271 | 271_Memcache.php | 183 | 1 |
| 272 | 272_feed-atom-comments.php | 115 | 0 |
| 273 | 273_wp-activate.php | 131 | 1 |
| 274 | 274_comment.php | 171 | 1 |
| 275 | 275_wp-comments-post.php | 164 | 2 |
| 276 | 276_wp-mce-help.php | 145 | 1 |
| 277 | 277_DB.php | 137 | 1 |
| 278 | 278_my-sites.php | 145 | 3 |
| 279 | 279_custom-header.php | 165 | 1 |
| 280 | 280_class-wp-customize-panel.php | 200 | 2 |
| 281 | 281_streams.php | 209 | 7 |
| 282 | 282_plugin-install.php | 115 | 2 |
| 283 | 283_Caption.php | 210 | 3 |
| 284 | 284_upgrade.php | 120 | 3 |
| 285 | 285_custom-header.php | 147 | 1 |
| 286 | 286_ms-default-constants.php | 153 | 0 |
| 287 | 287_File.php | 173 | 1 |
| 288 | 288_image.php | 116 | 1 |
| 289 | 289_Cache.php | 133 | 1 |
| 290 | 290_class-wp-customize-section.php | 196 | 2 |
| 291 | 291_menu.php | 63 | 1 |
| 292 | 292_upgrade.php | 116 | 2 |
| 293 | 293_tools.php | 75 | 2 |
| 294 | 294_async-upload.php | 114 | 3 |
| 295 | 295_wp-trackback.php | 127 | 5 |
| 296 | 296_comments.php | 101 | 1 |
| 297 | 297_class-feed.php | 140 | 2 |
| 298 | 298_post-thumbnail-template.php | 142 | 3 |
| 299 | 299_sidebar.php | 83 | 1 |
| 300 | 300_user-new.php | 106 | 3 |
| 301 | 301_Restriction.php | 155 | 3 |
| 302 | 302_Credit.php | 156 | 3 |
| 303 | 303_Category.php | 157 | 3 |
| 304 | 304_feed-rss2-comments.php | 97 | 0 |
| 305 | 305_ms-delete-site.php | 91 | 1 |
| 306 | 306_ms-default-filters.php | 82 | 1 |
| 307 | 307_feed-rss2.php | 115 | 0 |
| 308 | 308_Author.php | 157 | 3 |
| 309 | 309_link-manager.php | 99 | 2 |
| 310 | 310_Base.php | 114 | 0 |
| 311 | 311_Rating.php | 129 | 3 |
| 312 | 312_admin-ajax.php | 98 | 2 |
| 313 | 313_freedoms.php | 57 | 3 |
| 314 | 314_Copyright.php | 130 | 3 |
| 315 | 315_compat.php | 125 | 2 |
| 316 | 316_list-table.php | 113 | 2 |
| 317 | 317_load-styles.php | 153 | 3 |
| 318 | 318_media-upload.php | 100 | 1 |
| 319 | 319_media-new.php | 84 | 2 |
| 320 | 320_image.php | 82 | 1 |
| 321 | 321_content.php | 73 | 1 |
| 322 | 322_xmlrpc.php | 101 | 1 |
| 323 | 323_feed-atom.php | 87 | 0 |
| 324 | 324_wp-cron.php | 115 | 2 |
| 325 | 325_index.php | 79 | 2 |
| 326 | 326_load-scripts.php | 162 | 2 |
| 327 | 327_class-wp-http-ixr-client.php | 97 | 3 |
| 328 | 328_template-loader.php | 76 | 0 |
| 329 | 329_wp-config-sample.php | 80 | 1 |
| 330 | 330_class.akismet-widget.php | 110 | 3 |
| 331 | 331_wp-load.php | 73 | 3 |
| 332 | 332_image.php | 79 | 1 |
| 333 | 333_feed-rdf.php | 81 | 0 |
| 334 | 334_link.php | 117 | 2 |
| 335 | 335_ms-files.php | 82 | 3 |
| 336 | 336_author.php | 84 | 0 |
| 337 | 337_entry.php | 78 | 2 |
| 338 | 338_post-new.php | 74 | 2 |
| 339 | 339_admin.php | 74 | 0 |
| 340 | 340_akismet.php | 59 | 1 |
| 341 | 341_wp-links-opml.php | 80 | 3 |
| 342 | 342_taxonomy-post_format.php | 85 | 0 |
| 343 | 343_admin-footer.php | 99 | 0 |
| 344 | 344_comments.php | 66 | 1 |
| 345 | 345_header.php | 65 | 1 |
| 346 | 346_Core.php | 57 | 0 |
| 347 | 347_hello.php | 82 | 0 |
| 348 | 348_header.php | 53 | 1 |
| 349 | 349_xdiff.php | 64 | 1 |
| 350 | 350_back-compat.php | 63 | 1 |
| 351 | 351_Exception.php | 52 | 0 |
| 352 | 352_content.php | 66 | 1 |
| 353 | 353_back-compat.php | 63 | 1 |
| 354 | 354_content-gallery.php | 57 | 1 |
| 355 | 355_content-aside.php | 57 | 1 |
| 356 | 356_content-audio.php | 57 | 1 |
| 357 | 357_content-image.php | 57 | 1 |
| 358 | 358_content-quote.php | 57 | 1 |
| 359 | 359_content-video.php | 57 | 1 |
| 360 | 360_comments.php | 60 | 1 |
| 361 | 361_content.php | 57 | 1 |
| 362 | 362_content-link.php | 57 | 1 |
| 363 | 363_archive.php | 74 | 0 |
| 364 | 364_archive.php | 63 | 0 |
| 365 | 365_index.php | 66 | 0 |
| 366 | 366_ms-deprecated.php | 78 | 0 |
| 367 | 367_link-parse-opml.php | 84 | 2 |
| 368 | 368_header.php | 51 | 1 |
| 369 | 369_author.php | 74 | 0 |
| 370 | 370_comments.php | 59 | 1 |
| 371 | 371_content-gallery.php | 45 | 1 |
| 372 | 372_archive.php | 55 | 0 |
| 373 | 373_header.php | 49 | 0 |
| 374 | 374_author.php | 62 | 0 |
| 375 | 375_content-image.php | 41 | 1 |
| 376 | 376_content-video.php | 41 | 1 |
| 377 | 377_admin-post.php | 71 | 1 |
| 378 | 378_page.php | 50 | 1 |
| 379 | 379_tag.php | 60 | 0 |
| 380 | 380_index.php | 61 | 0 |
| 381 | 381_content-status.php | 42 | 0 |
| 382 | 382_category.php | 58 | 0 |
| 383 | 383_category.php | 51 | 0 |
| 384 | 384_tag.php | 52 | 0 |
| 385 | 385_content-audio.php | 37 | 1 |
| 386 | 386_content-link.php | 36 | 1 |
| 387 | 387_search.php | 49 | 0 |
| 388 | 388_contributors.php | 52 | 0 |
| 389 | 389_content-aside.php | 28 | 0 |
| 390 | 390_search.php | 49 | 0 |
| 391 | 391_content-aside.php | 31 | 1 |
| 392 | 392_content-quote.php | 27 | 1 |
| 393 | 393_footer.php | 30 | 0 |
| 394 | 394_feed-rss.php | 46 | 0 |
| 395 | 395_page.php | 48 | 0 |
| 396 | 396_taxonomy-post_format.php | 41 | 0 |
| 397 | 397_content-link.php | 26 | 0 |
| 398 | 398_tag.php | 43 | 0 |
| 399 | 399_content-image.php | 28 | 0 |
| 400 | 400_category.php | 41 | 0 |
| 401 | 401_wp-tinymce.php | 39 | 2 |
| 402 | 402_content-featured-post.php | 34 | 0 |
| 403 | 403_author-bio.php | 34 | 0 |
| 404 | 404_content-quote.php | 25 | 0 |
| 405 | 405_content-chat.php | 31 | 1 |
| 406 | 406_single.php | 33 | 0 |
| 407 | 407_single.php | 40 | 0 |
| 408 | 408_index.php | 38 | 0 |
| 409 | 409_content-status.php | 25 | 1 |
| 410 | 410_front-page.php | 35 | 0 |
| 411 | 411_sidebar-front.php | 35 | 0 |
| 412 | 412_content-none.php | 31 | 0 |
| 413 | 413_content-none.php | 31 | 0 |
| 414 | 414_admin.php | 32 | 2 |
| 415 | 415_featured-content.php | 39 | 0 |
| 416 | 416_full-width.php | 42 | 0 |
| 417 | 417_edit.php | 42 | 1 |
| 418 | 418_content-page.php | 31 | 1 |
| 419 | 419_search.php | 36 | 0 |
| 420 | 420_content-page.php | 26 | 1 |
| 421 | 421_sidebar.php | 29 | 1 |
| 422 | 422_404.php | 31 | 0 |
| 423 | 423_admin.php | 32 | 1 |
| 424 | 424_strict.php | 7 | 0 |
| 425 | 425_full-width.php | 30 | 0 |
| 426 | 426_footer.php | 26 | 0 |
| 427 | 427_404.php | 29 | 0 |
| 428 | 428_footer.php | 23 | 0 |
| 429 | 429_footer.php | 28 | 0 |
| 430 | 430_page.php | 29 | 0 |
| 431 | 431_404.php | 32 | 0 |
| 432 | 432_link-add.php | 29 | 2 |
| 433 | 433_menu.php | 22 | 1 |
| 434 | 434_version.php | 35 | 0 |
| 435 | 435_single.php | 28 | 0 |
| 436 | 436_content-none.php | 20 | 0 |
| 437 | 437_sidebar.php | 22 | 0 |
| 438 | 438_stats.php | 4 | 0 |
| 439 | 439_update.php | 19 | 2 |
| 440 | 440_get.php | 5 | 1 |
| 441 | 441_options-head.php | 18 | 1 |
| 442 | 442_sidebar-main.php | 18 | 0 |
| 443 | 443_plugin-install.php | 19 | 1 |
| 444 | 444_theme-install.php | 19 | 1 |
| 445 | 445_index.php | 17 | 1 |
| 446 | 446_sidebar.php | 17 | 0 |
| 447 | 447_admin-functions.php | 15 | 0 |
| 448 | 448_sidebar-footer.php | 19 | 0 |
| 449 | 449_plugin-editor.php | 16 | 1 |
| 450 | 450_theme-editor.php | 16 | 1 |
| 451 | 451_profile.php | 16 | 1 |
| 452 | 452_update-core.php | 16 | 1 |
| 453 | 453_user-edit.php | 16 | 1 |
| 454 | 454_freedoms.php | 16 | 1 |
| 455 | 455_credits.php | 16 | 1 |
| 456 | 456_plugins.php | 16 | 1 |
| 457 | 457_setup.php | 16 | 1 |
| 458 | 458_about.php | 16 | 1 |
| 459 | 459_sidebar-content.php | 16 | 0 |
| 460 | 460_upgrade-functions.php | 12 | 0 |
| 461 | 461_moderation.php | 12 | 1 |
| 462 | 462_profile.php | 18 | 1 |
| 463 | 463_freedoms.php | 13 | 1 |
| 464 | 464_credits.php | 13 | 1 |
| 465 | 465_about.php | 13 | 1 |
| 466 | 466_wp-blog-header.php | 18 | 1 |
| 467 | 467_ms-options.php | 12 | 1 |
| 468 | 468_ms-upgrade-network.php | 13 | 1 |
| 469 | 469_ms-edit.php | 13 | 1 |
| 470 | 470_ms-themes.php | 13 | 1 |
| 471 | 471_ms-sites.php | 13 | 1 |
| 472 | 472_ms-users.php | 13 | 1 |
| 473 | 473_profile.php | 12 | 1 |
| 474 | 474_index.php | 12 | 1 |
| 475 | 475_user-edit.php | 12 | 1 |
| 476 | 476_ms-admin.php | 13 | 1 |
| 477 | 477_rss-functions.php | 9 | 0 |
| 478 | 478_registration-functions.php | 7 | 0 |
| 479 | 479_registration.php | 7 | 0 |
| 480 | 480_index.php | 2 | 0 |
| 481 | 481_index.php | 2 | 0 |
| 482 | 482_index.php | 2 | 0 |
| 483 | 483_index.php | 2 | 0 |

## Total Analysis Summary - Version Specific Focus

### Dataset Overview
- **Total Files**: 482
- **Total Lines of Code**: 247,942
- **Total PHP Version Changes**: 1,331

### Statistical Insights - Version Migration Focus
- **Average LOC per file**: 514 lines
- **Average version changes per file**: 2.8
- **Change density**: 0.54% (version changes per LOC)
- **Files with no changes**: 85 (18%)
- **Files with 10+ changes**: 6 (1%)

### Version Migration Impact
- **Pure PHP version upgrade focus**: 100% of changes are version-specific
- **Files most impacted by version changes**: Large core files (2000+ LOC)
- **Change-to-code ratio**: Higher version change density in smaller utility files

## Research Validation - Version Migration Focus

### Professional Tool Credibility
- **Industry Standard**: Rector is widely used in professional PHP development
- **Version-Specific Rules**: Each change backed by specific PHP version transformation rules
- **Version Accuracy**: Targets exact PHP version features and syntax improvements
- **Community Validated**: Open source with extensive community testing and version-specific rule sets

### Data Integrity - Version Focus
- Complete version migration diff information preserved for verification
- Individual file reports enable detailed version analysis
- Categorization based on Rector's internal PHP version rule organization
- No subjective assessments or code quality estimations included
- Pure focus on PHP version evolution (5.x → 8.3)

---

*Version-specific analysis completed on 2025-11-13 04:19:02*

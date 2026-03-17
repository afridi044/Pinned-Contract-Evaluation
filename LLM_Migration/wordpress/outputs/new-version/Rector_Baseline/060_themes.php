<?php
/**
 * Themes administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( __DIR__ . '/admin.php' );

if ( !current_user_can('switch_themes') && !current_user_can('edit_theme_options') )
	wp_die( __() );

if ( current_user_can( 'switch_themes' ) && isset($_GET['action'] ) ) {
	if ( 'activate' == $_GET['action'] ) {
		check_admin_referer('switch-theme_' . $_GET['stylesheet']);
		$theme = wp_get_theme( $_GET['stylesheet'] );
		if ( ! $theme->exists() || ! $theme->is_allowed() )
			wp_die( __() );
		switch_theme( $theme->get_stylesheet() );
		wp_redirect( admin_url() );
		exit;
	} elseif ( 'delete' == $_GET['action'] ) {
		check_admin_referer('delete-theme_' . $_GET['stylesheet']);
		$theme = wp_get_theme( $_GET['stylesheet'] );
		if ( !current_user_can('delete_themes') || ! $theme->exists() )
			wp_die( __() );
		delete_theme($_GET['stylesheet']);
		wp_redirect( admin_url() );
		exit;
	}
}

$title = __();
$parent_file = 'themes.php';

// Help tab: Overview
if ( current_user_can( 'switch_themes' ) ) {
	$help_overview  = '<p>' . __() . '</p>' .
		'<p>' . __() . '</p>' .
		'<ul><li>' . __() . '</li>' .
		'<li>' . __() . '</li>' .
		'<li>' . __() . '</li></ul>' .
		'<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'      => 'overview',
		'title'   => __(),
		'content' => $help_overview
	] );
} // switch_themes

// Help tab: Adding Themes
if ( current_user_can( 'install_themes' ) ) {
	if ( is_multisite() ) {
		$help_install = '<p>' . __() . '</p>';
	} else {
		$help_install = '<p>' . sprintf( __(), 'https://wordpress.org/themes/' ) . '</p>';
	}

	get_current_screen()->add_help_tab( [
		'id'      => 'adding-themes',
		'title'   => __(),
		'content' => $help_install
	] );
} // install_themes

// Help tab: Previewing and Customizing
if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
	$help_customize =
		'<p>' . __() . '</p>'.
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'		=> 'customize-preview-themes',
		'title'		=> __(),
		'content'	=> $help_customize
	] );
} // edit_theme_options && customize

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

if ( current_user_can( 'switch_themes' ) ) {
	$themes = wp_prepare_themes_for_js();
} else {
	$themes = wp_prepare_themes_for_js( [ wp_get_theme() ] );
}
wp_reset_vars( [ 'theme', 'search' ] );

wp_localize_script( 'theme', '_wpThemeSettings', [
	'themes'   => $themes,
	'settings' => [
		'canInstall'    => ( ! is_multisite() && current_user_can( 'install_themes' ) ),
		'installURI'    => ( ! is_multisite() && current_user_can( 'install_themes' ) ) ? admin_url() : null,
		'confirmDelete' => __(),
		'adminUrl'      => parse_url( (string) admin_url(), PHP_URL_PATH ),
	],
 	'l10n' => [
 		'addNew' => __(),
 		'search'  => __(),
 		'searchPlaceholder' => __(), // placeholder (no ellipsis)
  	],
] );

add_thickbox();
wp_enqueue_script( 'theme' );
wp_enqueue_script( 'customize-loader' );

require_once( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
	<h2><?php esc_html_e( 'Themes' ); ?>
		<span class="title-count theme-count"><?php echo count( $themes ); ?></span>
	<?php if ( ! is_multisite() && current_user_can( 'install_themes' ) ) : ?>
		<a href="<?php echo admin_url(); ?>" class="hide-if-no-js add-new-h2"><?php echo esc_html( _x() ); ?></a>
	<?php endif; ?>
	</h2>
<?php
if ( ! validate_current_theme() || isset( $_GET['broken'] ) ) : ?>
<div id="message1" class="updated"><p><?php _e('The active theme is broken. Reverting to the default theme.'); ?></p></div>
<?php elseif ( isset($_GET['activated']) ) :
		if ( isset( $_GET['previewed'] ) ) { ?>
		<div id="message2" class="updated"><p><?php printf( __(), home_url( '/' ) ); ?></p></div>
		<?php } else { ?>
<div id="message2" class="updated"><p><?php printf( __(), home_url( '/' ) ); ?></p></div><?php
		}
	elseif ( isset($_GET['deleted']) ) : ?>
<div id="message3" class="updated"><p><?php _e('Theme deleted.') ?></p></div>
<?php
endif;

$ct = wp_get_theme();

if ( $ct->errors() && ( ! is_multisite() || current_user_can( 'manage_network_themes' ) ) ) {
	echo '<div class="error"><p>' . sprintf( __(), $ct->errors()->get_error_message() ) . '</p></div>';
}

/*
// Certain error codes are less fatal than others. We can still display theme information in most cases.
if ( ! $ct->errors() || ( 1 == count( $ct->errors()->get_error_codes() )
	&& in_array( $ct->errors()->get_error_code(), array( 'theme_no_parent', 'theme_parent_invalid', 'theme_no_index' ) ) ) ) : ?>
*/

	// Pretend you didn't see this.
	$current_theme_actions = [];
	if ( is_array( $submenu ) && isset( $submenu['themes.php'] ) ) {
		foreach ( (array) $submenu['themes.php'] as $item) {
			$class = '';
			if ( 'themes.php' == $item[2] || 'theme-editor.php' == $item[2] || str_starts_with((string) $item[2], 'customize.php') )
				continue;
			// 0 = name, 1 = capability, 2 = file
			if ( ( strcmp((string) $self, (string) $item[2]) == 0 && empty($parent_file)) || ($parent_file && ($item[2] == $parent_file)) )
				$class = ' class="current"';
			if ( !empty($submenu[$item[2]]) ) {
				$submenu[$item[2]] = array_values($submenu[$item[2]]); // Re-index.
				$menu_hook = get_plugin_page_hook($submenu[$item[2]][0][2], $item[2]);
				if ( file_exists(WP_PLUGIN_DIR . "/{$submenu[$item[2]][0][2]}") || !empty($menu_hook))
					$current_theme_actions[] = "<a class='button button-secondary' href='admin.php?page={$submenu[$item[2]][0][2]}'$class>{$item[0]}</a>";
				else
					$current_theme_actions[] = "<a class='button button-secondary' href='{$submenu[$item[2]][0][2]}'$class>{$item[0]}</a>";
			} else if ( current_user_can($item[1]) ) {
				$menu_file = $item[2];
				if ( false !== ( $pos = strpos( (string) $menu_file, '?' ) ) )
					$menu_file = substr( (string) $menu_file, 0, $pos );
				if ( file_exists( ABSPATH . "wp-admin/$menu_file" ) ) {
					$current_theme_actions[] = "<a class='button button-secondary' href='{$item[2]}'$class>{$item[0]}</a>";
				} else {
					$current_theme_actions[] = "<a class='button button-secondary' href='themes.php?page={$item[2]}'$class>{$item[0]}</a>";
				}
			}
		}
	}

?>

<div class="theme-browser">
	<div class="themes">

<?php
/*
 * This PHP is synchronized with the tmpl-theme template below!
 */

foreach ( $themes as $theme ) :
	$aria_action = esc_attr();
	$aria_name   = esc_attr();
	?>
<div class="theme<?php if ( $theme['active'] ) echo ' active'; ?>" tabindex="0" aria-describedby="<?php echo $aria_action . ' ' . $aria_name; ?>">
	<?php if ( ! empty( $theme['screenshot'][0] ) ) { ?>
		<div class="theme-screenshot">
			<img src="<?php echo $theme['screenshot'][0]; ?>" alt="" />
		</div>
	<?php } else { ?>
		<div class="theme-screenshot blank"></div>
	<?php } ?>
	<span class="more-details" id="<?php echo $aria_action; ?>"><?php _e( 'Theme Details' ); ?></span>
	<div class="theme-author"><?php printf( __(), $theme['author'] ); ?></div>

	<?php if ( $theme['active'] ) { ?>
		<h3 class="theme-name" id="<?php echo $aria_name; ?>"><span><?php _ex( 'Active:', 'theme' ); ?></span> <?php echo $theme['name']; ?></h3>
	<?php } else { ?>
		<h3 class="theme-name" id="<?php echo $aria_name; ?>"><?php echo $theme['name']; ?></h3>
	<?php } ?>

	<div class="theme-actions">

	<?php if ( $theme['active'] ) { ?>
		<?php if ( $theme['actions']['customize'] && current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) { ?>
			<a class="button button-primary customize load-customize hide-if-no-customize" href="<?php echo $theme['actions']['customize']; ?>"><?php _e( 'Customize' ); ?></a>
		<?php } ?>
	<?php } else { ?>
		<a class="button button-primary activate" href="<?php echo $theme['actions']['activate']; ?>"><?php _e( 'Activate' ); ?></a>
		<?php if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) { ?>
			<a class="button button-secondary load-customize hide-if-no-customize" href="<?php echo $theme['actions']['customize']; ?>"><?php _e( 'Live Preview' ); ?></a>
			<a class="button button-secondary hide-if-customize" href="<?php echo $theme['actions']['preview']; ?>"><?php _e( 'Preview' ); ?></a>
		<?php } ?>
	<?php } ?>

	</div>

	<?php if ( $theme['hasUpdate'] ) { ?>
		<div class="theme-update"><?php _e( 'Update Available' ); ?></div>
	<?php } ?>
</div>
<?php endforeach; ?>
	<br class="clear" />
	</div>
</div>
<div class="theme-overlay"></div>

<p class="no-themes"><?php _e( 'No themes found. Try a different search.' ); ?></p>

<?php
// List broken themes, if any.
if ( ! is_multisite() && current_user_can('edit_themes') && $broken_themes = wp_get_themes( [ 'errors' => true ] ) ) {
?>

<div class="broken-themes">
<h3><?php _e('Broken Themes'); ?></h3>
<p><?php _e('The following themes are installed but incomplete. Themes must have a stylesheet and a template.'); ?></p>

<table>
	<tr>
		<th><?php _ex('Name', 'theme name'); ?></th>
		<th><?php _e('Description'); ?></th>
	</tr>
<?php
	foreach ( $broken_themes as $broken_theme ) {
		echo "
		<tr>
			 <td>" . ( $broken_theme->get( 'Name' ) ?: $broken_theme->get_stylesheet() ) . "</td>
			 <td>" . $broken_theme->errors()->get_error_message() . "</td>
		</tr>";
	}
?>
</table>
</div>

<?php
}
?>
</div><!-- .wrap -->

<?php
/*
 * The tmpl-theme template is synchronized with PHP above!
 */
?>
<script id="tmpl-theme" type="text/template">
	<# if ( data.screenshot[0] ) { #>
		<div class="theme-screenshot">
			<img src="{{ data.screenshot[0] }}" alt="" />
		</div>
	<# } else { #>
		<div class="theme-screenshot blank"></div>
	<# } #>
	<span class="more-details" id="{{ data.id }}-action"><?php _e( 'Theme Details' ); ?></span>
	<div class="theme-author"><?php printf( __(), '{{{ data.author }}}' ); ?></div>

	<# if ( data.active ) { #>
		<h3 class="theme-name" id="{{ data.id }}-name"><span><?php _ex( 'Active:', 'theme' ); ?></span> {{{ data.name }}}</h3>
	<# } else { #>
		<h3 class="theme-name" id="{{ data.id }}-name">{{{ data.name }}}</h3>
	<# } #>

	<div class="theme-actions">

	<# if ( data.active ) { #>
		<# if ( data.actions.customize ) { #>
			<a class="button button-primary customize load-customize hide-if-no-customize" href="{{ data.actions.customize }}"><?php _e( 'Customize' ); ?></a>
		<# } #>
	<# } else { #>
		<a class="button button-primary activate" href="{{{ data.actions.activate }}}"><?php _e( 'Activate' ); ?></a>
		<a class="button button-secondary load-customize hide-if-no-customize" href="{{{ data.actions.customize }}}"><?php _e( 'Live Preview' ); ?></a>
		<a class="button button-secondary hide-if-customize" href="{{{ data.actions.preview }}}"><?php _e( 'Preview' ); ?></a>
	<# } #>

	</div>

	<# if ( data.hasUpdate ) { #>
		<div class="theme-update"><?php _e( 'Update Available' ); ?></div>
	<# } #>
</script>

<script id="tmpl-theme-single" type="text/template">
	<div class="theme-backdrop"></div>
	<div class="theme-wrap">
		<div class="theme-header">
			<button class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme' ); ?></span></button>
			<button class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme' ); ?></span></button>
			<button class="close dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Close overlay' ); ?></span></button>
		</div>
		<div class="theme-about">
			<div class="theme-screenshots">
			<# if ( data.screenshot[0] ) { #>
				<div class="screenshot"><img src="{{ data.screenshot[0] }}" alt="" /></div>
			<# } else { #>
				<div class="screenshot blank"></div>
			<# } #>
			</div>

			<div class="theme-info">
				<# if ( data.active ) { #>
					<span class="current-label"><?php _e( 'Current Theme' ); ?></span>
				<# } #>
				<h3 class="theme-name">{{{ data.name }}}<span class="theme-version"><?php printf( __(), '{{{ data.version }}}' ); ?></span></h3>
				<h4 class="theme-author"><?php printf( __(), '{{{ data.authorAndUri }}}' ); ?></h4>

				<# if ( data.hasUpdate ) { #>
				<div class="theme-update-message">
					<h4 class="theme-update"><?php _e( 'Update Available' ); ?></h4>
					{{{ data.update }}}
				</div>
				<# } #>
				<p class="theme-description">{{{ data.description }}}</p>

				<# if ( data.parent ) { #>
					<p class="parent-theme"><?php printf( __(), '<strong>{{{ data.parent }}}</strong>' ); ?></p>
				<# } #>

				<# if ( data.tags ) { #>
					<p class="theme-tags"><span><?php _e( 'Tags:' ); ?></span> {{{ data.tags }}}</p>
				<# } #>
			</div>
		</div>

		<div class="theme-actions">
			<div class="active-theme">
				<a href="{{{ data.actions.customize }}}" class="button button-primary customize load-customize hide-if-no-customize"><?php _e( 'Customize' ); ?></a>
				<?php echo implode( ' ', $current_theme_actions ); ?>
			</div>
			<div class="inactive-theme">
				<# if ( data.actions.activate ) { #>
					<a href="{{{ data.actions.activate }}}" class="button button-primary activate"><?php _e( 'Activate' ); ?></a>
				<# } #>
				<a href="{{{ data.actions.customize }}}" class="button button-secondary load-customize hide-if-no-customize"><?php _e( 'Live Preview' ); ?></a>
				<a href="{{{ data.actions.preview }}}" class="button button-secondary hide-if-customize"><?php _e( 'Preview' ); ?></a>
			</div>

			<# if ( ! data.active && data.actions['delete'] ) { #>
				<a href="{{{ data.actions['delete'] }}}" class="button button-secondary delete-theme"><?php _e( 'Delete' ); ?></a>
			<# } #>
		</div>
	</div>
</script>

<?php require( ABSPATH . 'wp-admin/admin-footer.php' );

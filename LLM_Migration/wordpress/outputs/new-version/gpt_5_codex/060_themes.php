<?php
/**
 * Themes administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'switch_themes' ) && ! current_user_can( 'edit_theme_options' ) ) {
	wp_die( __( 'Cheatin&#8217; uh?' ) );
}

$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

if ( current_user_can( 'switch_themes' ) && $action ) {
	$stylesheet = isset( $_GET['stylesheet'] ) ? sanitize_text_field( wp_unslash( $_GET['stylesheet'] ) ) : '';

	if ( 'activate' === $action ) {
		check_admin_referer( 'switch-theme_' . $stylesheet );

		$theme = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() || ! $theme->is_allowed() ) {
			wp_die( __( 'Cheatin&#8217; uh?' ) );
		}

		switch_theme( $theme->get_stylesheet() );
		wp_safe_redirect( admin_url( 'themes.php?activated=true' ) );
		exit;
	}

	if ( 'delete' === $action ) {
		check_admin_referer( 'delete-theme_' . $stylesheet );

		$theme = wp_get_theme( $stylesheet );

		if ( ! current_user_can( 'delete_themes' ) || ! $theme->exists() ) {
			wp_die( __( 'Cheatin&#8217; uh?' ) );
		}

		delete_theme( $stylesheet );
		wp_safe_redirect( admin_url( 'themes.php?deleted=true' ) );
		exit;
	}
}

global $submenu, $self, $parent_file;

$title       = __( 'Manage Themes' );
$parent_file = 'themes.php';

// Help tab: Overview.
if ( current_user_can( 'switch_themes' ) ) {
	$help_overview  = '<p>' . __( 'This screen is used for managing your installed themes. Aside from the default theme(s) included with your WordPress installation, themes are designed and developed by third parties.' ) . '</p>' .
		'<p>' . __( 'From this screen you can:' ) . '</p>' .
		'<ul><li>' . __( 'Hover or tap to see Activate and Live Preview buttons' ) . '</li>' .
		'<li>' . __( 'Click on the theme to see the theme name, version, author, description, tags, and the Delete link' ) . '</li>' .
		'<li>' . __( 'Click Customize for the current theme or Live Preview for any other theme to see a live preview' ) . '</li></ul>' .
		'<p>' . __( 'The current theme is displayed highlighted as the first theme.' ) . '</p>';

	get_current_screen()->add_help_tab(
		[
			'id'      => 'overview',
			'title'   => __( 'Overview' ),
			'content' => $help_overview,
		]
	);
}

// Help tab: Adding Themes.
if ( current_user_can( 'install_themes' ) ) {
	if ( is_multisite() ) {
		$help_install = '<p>' . __( 'Installing themes on Multisite can only be done from the Network Admin section.' ) . '</p>';
	} else {
		$help_install = '<p>' . sprintf( __( 'If you would like to see more themes to choose from, click on the &#8220;Add New&#8221; button and you will be able to browse or search for additional themes from the <a href="%s" target="_blank">WordPress.org Theme Directory</a>. Themes in the WordPress.org Theme Directory are designed and developed by third parties, and are compatible with the license WordPress uses. Oh, and they&#8217;re free!' ), 'https://wordpress.org/themes/' ) . '</p>';
	}

	get_current_screen()->add_help_tab(
		[
			'id'      => 'adding-themes',
			'title'   => __( 'Adding Themes' ),
			'content' => $help_install,
		]
	);
}

// Help tab: Previewing and Customizing.
if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
	$help_customize =
		'<p>' . __( 'Tap or hover on any theme then click the Live Preview button to see a live preview of that theme and change theme options in a separate, full-screen view. You can also find a Live Preview button at the bottom of the theme details screen. Any installed theme can be previewed and customized in this way.' ) . '</p>' .
		'<p>' . __( 'The theme being previewed is fully interactive &mdash; navigate to different pages to see how the theme handles posts, archives, and other page templates. The settings may differ depending on what theme features the theme being previewed supports. To accept the new settings and activate the theme all in one step, click the Save &amp; Activate button above the menu.' ) . '</p>' .
		'<p>' . __( 'When previewing on smaller monitors, you can use the collapse icon at the bottom of the left-hand pane. This will hide the pane, giving you more room to preview your site in the new theme. To bring the pane back, click on the collapse icon again.' ) . '</p>';

	get_current_screen()->add_help_tab(
		[
			'id'      => 'customize-preview-themes',
			'title'   => __( 'Previewing and Customizing' ),
			'content' => $help_customize,
		]
	);
}

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="http://codex.wordpress.org/Using_Themes" target="_blank">Documentation on Using Themes</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>' ) . '</p>'
);

$themes = current_user_can( 'switch_themes' )
	? wp_prepare_themes_for_js()
	: wp_prepare_themes_for_js( [ wp_get_theme() ] );

if ( ! is_array( $themes ) ) {
	$themes = [];
}

$theme_count = is_countable( $themes ) ? count( $themes ) : 0;

wp_reset_vars( [ 'theme', 'search' ] );

wp_localize_script(
	'theme',
	'_wpThemeSettings',
	[
		'themes'   => $themes,
		'settings' => [
			'canInstall'    => ! is_multisite() && current_user_can( 'install_themes' ),
			'installURI'    => ! is_multisite() && current_user_can( 'install_themes' ) ? admin_url( 'theme-install.php' ) : null,
			'confirmDelete' => __( "Are you sure you want to delete this theme?\n\nClick 'Cancel' to go back, 'OK' to confirm the delete." ),
			'adminUrl'      => parse_url( admin_url(), PHP_URL_PATH ),
		],
		'l10n'     => [
			'addNew'            => __( 'Add New Theme' ),
			'search'            => __( 'Search Installed Themes' ),
			'searchPlaceholder' => __( 'Search installed themes...' ),
		],
	]
);

add_thickbox();
wp_enqueue_script( 'theme' );
wp_enqueue_script( 'customize-loader' );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h2>
		<?php esc_html_e( 'Themes' ); ?>
		<span class="title-count theme-count"><?php echo esc_html( (string) $theme_count ); ?></span>
		<?php if ( ! is_multisite() && current_user_can( 'install_themes' ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'theme-install.php' ) ); ?>" class="hide-if-no-js add-new-h2"><?php echo esc_html( _x( 'Add New', 'Add new theme' ) ); ?></a>
		<?php endif; ?>
	</h2>
	<?php if ( ! validate_current_theme() || isset( $_GET['broken'] ) ) : ?>
		<div id="message1" class="updated"><p><?php _e( 'The active theme is broken. Reverting to the default theme.' ); ?></p></div>
	<?php elseif ( isset( $_GET['activated'] ) ) : ?>
		<?php if ( isset( $_GET['previewed'] ) ) : ?>
			<div id="message2" class="updated"><p><?php printf( __( 'Settings saved and theme activated. <a href="%s">Visit site</a>' ), esc_url( home_url( '/' ) ) ); ?></p></div>
		<?php else : ?>
			<div id="message2" class="updated"><p><?php printf( __( 'New theme activated. <a href="%s">Visit site</a>' ), esc_url( home_url( '/' ) ) ); ?></p></div>
		<?php endif; ?>
	<?php elseif ( isset( $_GET['deleted'] ) ) : ?>
		<div id="message3" class="updated"><p><?php _e( 'Theme deleted.' ); ?></p></div>
	<?php endif; ?>
	<?php
	$ct = wp_get_theme();

	if ( $ct->errors() && ( ! is_multisite() || current_user_can( 'manage_network_themes' ) ) ) {
		echo '<div class="error"><p>' . wp_kses_post( sprintf( __( 'ERROR: %s' ), $ct->errors()->get_error_message() ) ) . '</p></div>';
	}

	$current_theme_actions = [];

	if ( is_array( $submenu ) && isset( $submenu['themes.php'] ) ) {
		foreach ( (array) $submenu['themes.php'] as $item ) {
			if ( in_array( $item[2], [ 'themes.php', 'theme-editor.php' ], true ) || str_starts_with( (string) $item[2], 'customize.php' ) ) {
				continue;
			}

			$classes = 'button button-secondary';

			if ( ( strcmp( $self, $item[2] ) === 0 && empty( $parent_file ) ) || ( $parent_file && $item[2] === $parent_file ) ) {
				$classes .= ' current';
			}

			if ( ! empty( $submenu[ $item[2] ] ) ) {
				$submenu[ $item[2] ] = array_values( (array) $submenu[ $item[2] ] );
				$submenu_entry       = $submenu[ $item[2] ][0] ?? null;

				if ( $submenu_entry ) {
					$submenu_file = $submenu_entry[2] ?? '';
					$menu_hook    = $submenu_file ? get_plugin_page_hook( $submenu_file, $item[2] ) : null;

					if ( $submenu_file ) {
						$href = ( ! empty( $menu_hook ) || file_exists( WP_PLUGIN_DIR . '/' . $submenu_file ) )
							? "admin.php?page={$submenu_file}"
							: $submenu_file;

						$current_theme_actions[] = sprintf(
							'<a class="%1$s" href="%2$s">%3$s</a>',
							esc_attr( $classes ),
							esc_url( $href ),
							esc_html( $item[0] )
						);
					}
				}
			} elseif ( current_user_can( $item[1] ) ) {
				$menu_file      = $item[2];
				$menu_file_base = $menu_file;
				$pos            = strpos( $menu_file_base, '?' );

				if ( false !== $pos ) {
					$menu_file_base = substr( $menu_file_base, 0, $pos );
				}

				$href = file_exists( ABSPATH . "wp-admin/{$menu_file_base}" )
					? $item[2]
					: "themes.php?page={$item[2]}";

				$current_theme_actions[] = sprintf(
					'<a class="%1$s" href="%2$s">%3$s</a>',
					esc_attr( $classes ),
					esc_url( $href ),
					esc_html( $item[0] )
				);
			}
		}
	}
	?>
	<div class="theme-browser">
		<div class="themes">
			<?php foreach ( $themes as $theme ) : ?>
				<?php
				$theme_classes = 'theme';
				if ( ! empty( $theme['active'] ) ) {
					$theme_classes .= ' active';
				}
				$aria_action = esc_attr( "{$theme['id']}-action" );
				$aria_name   = esc_attr( "{$theme['id']}-name" );
				?>
				<div class="<?php echo esc_attr( $theme_classes ); ?>" tabindex="0" aria-describedby="<?php echo $aria_action . ' ' . $aria_name; ?>">
					<?php if ( ! empty( $theme['screenshot'][0] ) ) : ?>
						<div class="theme-screenshot">
							<img src="<?php echo esc_url( $theme['screenshot'][0] ); ?>" alt="" />
						</div>
					<?php else : ?>
						<div class="theme-screenshot blank"></div>
					<?php endif; ?>
					<span class="more-details" id="<?php echo $aria_action; ?>"><?php _e( 'Theme Details' ); ?></span>
					<div class="theme-author"><?php printf( __( 'By %s' ), $theme['author'] ); ?></div>

					<?php if ( ! empty( $theme['active'] ) ) : ?>
						<h3 class="theme-name" id="<?php echo $aria_name; ?>"><span><?php _ex( 'Active:', 'theme' ); ?></span> <?php echo esc_html( $theme['name'] ); ?></h3>
					<?php else : ?>
						<h3 class="theme-name" id="<?php echo $aria_name; ?>"><?php echo esc_html( $theme['name'] ); ?></h3>
					<?php endif; ?>

					<div class="theme-actions">
						<?php if ( ! empty( $theme['active'] ) ) : ?>
							<?php if ( ! empty( $theme['actions']['customize'] ) && current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) : ?>
								<a class="button button-primary customize load-customize hide-if-no-customize" href="<?php echo esc_url( $theme['actions']['customize'] ); ?>"><?php _e( 'Customize' ); ?></a>
							<?php endif; ?>
						<?php else : ?>
							<?php if ( ! empty( $theme['actions']['activate'] ) ) : ?>
								<a class="button button-primary activate" href="<?php echo esc_url( $theme['actions']['activate'] ); ?>"><?php _e( 'Activate' ); ?></a>
							<?php endif; ?>
							<?php if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) : ?>
								<?php if ( ! empty( $theme['actions']['customize'] ) ) : ?>
									<a class="button button-secondary load-customize hide-if-no-customize" href="<?php echo esc_url( $theme['actions']['customize'] ); ?>"><?php _e( 'Live Preview' ); ?></a>
								<?php endif; ?>
								<?php if ( ! empty( $theme['actions']['preview'] ) ) : ?>
									<a class="button button-secondary hide-if-customize" href="<?php echo esc_url( $theme['actions']['preview'] ); ?>"><?php _e( 'Preview' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $theme['hasUpdate'] ) ) : ?>
						<div class="theme-update"><?php _e( 'Update Available' ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<br class="clear" />
		</div>
	</div>
	<div class="theme-overlay"></div>

	<p class="no-themes"><?php _e( 'No themes found. Try a different search.' ); ?></p>

	<?php
	// List broken themes, if any.
	$broken_themes = ! is_multisite() && current_user_can( 'edit_themes' ) ? wp_get_themes( [ 'errors' => true ] ) : [];

	if ( ! empty( $broken_themes ) ) :
		?>
		<div class="broken-themes">
			<h3><?php _e( 'Broken Themes' ); ?></h3>
			<p><?php _e( 'The following themes are installed but incomplete. Themes must have a stylesheet and a template.' ); ?></p>

			<table>
				<tr>
					<th><?php _ex( 'Name', 'theme name' ); ?></th>
					<th><?php _e( 'Description' ); ?></th>
				</tr>
				<?php foreach ( $broken_themes as $broken_theme ) : ?>
					<?php
					$name = $broken_theme->get( 'Name' ) ? $broken_theme->get( 'Name' ) : $broken_theme->get_stylesheet();
					?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td><?php echo wp_kses_post( $broken_theme->errors()->get_error_message() ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>
	<?php endif; ?>
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
	<div class="theme-author"><?php printf( __( 'By %s' ), '{{{ data.author }}}' ); ?></div>

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
				<h3 class="theme-name">{{{ data.name }}}<span class="theme-version"><?php printf( __( 'Version: %s' ), '{{{ data.version }}}' ); ?></span></h3>
				<h4 class="theme-author"><?php printf( __( 'By %s' ), '{{{ data.authorAndUri }}}' ); ?></h4>

				<# if ( data.hasUpdate ) { #>
				<div class="theme-update-message">
					<h4 class="theme-update"><?php _e( 'Update Available' ); ?></h4>
					{{{ data.update }}}
				</div>
				<# } #>
				<p class="theme-description">{{{ data.description }}}</p>

				<# if ( data.parent ) { #>
					<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.' ), '<strong>{{{ data.parent }}}</strong>' ); ?></p>
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

<?php require ABSPATH . 'wp-admin/admin-footer.php'; ?>
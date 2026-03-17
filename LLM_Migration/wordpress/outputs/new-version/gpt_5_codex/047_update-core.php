<?php
/**
 * Update Core administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

wp_enqueue_style( 'plugin-install' );
wp_enqueue_script( 'plugin-install' );
wp_enqueue_script( 'updates' );
add_thickbox();

if ( is_multisite() && ! is_network_admin() ) {
	wp_safe_redirect( network_admin_url( 'update-core.php' ) );
	exit();
}

if ( ! current_user_can( 'update_core' ) && ! current_user_can( 'update_themes' ) && ! current_user_can( 'update_plugins' ) ) {
	wp_die( __( 'You do not have sufficient permissions to update this site.' ) );
}

function list_core_update( $update ): void {
	global $wp_local_package, $wpdb, $wp_version;

	static $first_pass = true;

	$update_locale   = $update->locale ?? 'en_US';
	$version_string  = '';
	$core_updates    = [];
	$current_locale  = get_locale();
	$update_response = $update->response ?? 'latest';

	if ( 'en_US' === $update_locale && 'en_US' === $current_locale ) {
		$version_string = $update->current;
	} elseif (
		'en_US' === $update_locale
		&& ! empty( $update->packages->partial )
		&& isset( $update->partial_version )
		&& $wp_version === $update->partial_version
	) {
		$core_updates = get_core_updates();
		if ( $core_updates && 1 === count( $core_updates ) ) {
			$version_string = $update->current;
		} else {
			$version_string = sprintf( '%s&ndash;<strong>%s</strong>', $update->current, $update_locale );
		}
	} else {
		$version_string = sprintf( '%s&ndash;<strong>%s</strong>', $update->current, $update_locale );
	}

	$current      = ! isset( $update->response ) || 'latest' === $update_response;
	$submit       = __( 'Update Now' );
	$form_action  = 'update-core.php?action=do-core-upgrade';
	$php_version  = PHP_VERSION;
	$mysql_version = $wpdb->db_version();
	$show_buttons = true;

	if ( 'development' === $update_response ) {
		$message  = __( 'You are using a development version of WordPress. You can update to the latest nightly build automatically or download the nightly build and install it manually:' );
		$download = __( 'Download nightly build' );
	} else {
		if ( $current ) {
			$message     = sprintf( __( 'If you need to re-install version %s, you can do so here or download the package and re-install manually:' ), $version_string );
			$submit      = __( 'Re-install Now' );
			$form_action = 'update-core.php?action=do-core-reinstall';
		} else {
			$php_compat   = version_compare( $php_version, $update->php_version, '>=' );
			$mysql_compat = true;

			if ( ! ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) ) ) {
				$mysql_compat = version_compare( $mysql_version, $update->mysql_version, '>=' );
			}

			if ( ! $mysql_compat && ! $php_compat ) {
				$message = sprintf(
					__( 'You cannot update because <a href="http://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires PHP version %2$s or higher and MySQL version %3$s or higher. You are running PHP version %4$s and MySQL version %5$s.' ),
					$update->current,
					$update->php_version,
					$update->mysql_version,
					$php_version,
					$mysql_version
				);
			} elseif ( ! $php_compat ) {
				$message = sprintf(
					__( 'You cannot update because <a href="http://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires PHP version %2$s or higher. You are running version %3$s.' ),
					$update->current,
					$update->php_version,
					$php_version
				);
			} elseif ( ! $mysql_compat ) {
				$message = sprintf(
					__( 'You cannot update because <a href="http://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> requires MySQL version %2$s or higher. You are running version %3$s.' ),
					$update->current,
					$update->mysql_version,
					$mysql_version
				);
			} else {
				$message = sprintf(
					__( 'You can update to <a href="http://codex.wordpress.org/Version_%1$s">WordPress %2$s</a> automatically or download the package and install it manually:' ),
					$update->current,
					$version_string
				);
			}

			if ( ! $mysql_compat || ! $php_compat ) {
				$show_buttons = false;
			}
		}

		$download = sprintf( __( 'Download %s' ), $version_string );
	}

	echo '<p>' . wp_kses_post( $message ) . '</p>';
	echo '<form method="post" action="' . esc_url( $form_action ) . '" name="upgrade" class="upgrade">';
	wp_nonce_field( 'upgrade-core' );
	echo '<p>';
	echo '<input name="version" value="' . esc_attr( $update->current ) . '" type="hidden" />';
	echo '<input name="locale" value="' . esc_attr( $update_locale ) . '" type="hidden" />';

	if ( $show_buttons ) {
		if ( $first_pass ) {
			submit_button( $submit, $current ? 'button' : 'primary regular', 'upgrade', false );
			$first_pass = false;
		} else {
			submit_button( $submit, 'button', 'upgrade', false );
		}

		echo '&nbsp;<a href="' . esc_url( $update->download ) . '" class="button">' . esc_html( $download ) . '</a>&nbsp;';
	}

	if ( 'en_US' !== $update_locale ) {
		if ( ! isset( $update->dismissed ) || ! $update->dismissed ) {
			submit_button( __( 'Hide this update' ), 'button', 'dismiss', false );
		} else {
			submit_button( __( 'Bring back this update' ), 'button', 'undismiss', false );
		}
	}

	echo '</p>';

	if ( 'en_US' !== $update_locale && ( ! isset( $wp_local_package ) || $wp_local_package !== $update_locale ) ) {
		echo '<p class="hint">' . esc_html__( 'This localized version contains both the translation and various other localization fixes. You can skip upgrading if you want to keep your current translation.' ) . '</p>';
	} elseif (
		'en_US' === $update_locale
		&& 'en_US' !== $current_locale
		&& empty( $update->packages->partial )
		&& isset( $update->partial_version )
		&& $wp_version === $update->partial_version
	) {
		echo '<p class="hint">' . sprintf(
			/* translators: %s: WordPress version. */
			__( 'You are about to install WordPress %s <strong>in English (US).</strong> There is a chance this update will break your translation. You may prefer to wait for the localized version to be released.' ),
			'development' !== $update_response ? $update->current : ''
		) . '</p>';
	}

	echo '</form>';
}

function dismissed_updates(): void {
	$dismissed = get_core_updates(
		[
			'dismissed' => true,
			'available' => false,
		]
	);

	if ( $dismissed ) {
		$show_text = esc_js( __( 'Show hidden updates' ) );
		$hide_text = esc_js( __( 'Hide hidden updates' ) );
		?>
		<script type="text/javascript">
		jQuery(
			function($) {
				$('dismissed-updates').show();
				$('#show-dismissed').toggle(
					function() {
						$(this).text('<?php echo $hide_text; ?>');
					},
					function() {
						$(this).text('<?php echo $show_text; ?>');
					}
				);
				$('#show-dismissed').on('click', function() {
					$('#dismissed-updates').toggle('slow');
				});
			}
		);
		</script>
		<?php
		echo '<p class="hide-if-no-js"><a id="show-dismissed" href="#">' . esc_html__( 'Show hidden updates' ) . '</a></p>';
		echo '<ul id="dismissed-updates" class="core-updates dismissed">';

		foreach ( (array) $dismissed as $update ) {
			echo '<li>';
			list_core_update( $update );
			echo '</li>';
		}

		echo '</ul>';
	}
}

/**
 * Display upgrade WordPress for downloading latest or upgrading automatically form.
 *
 * @since 2.7.0
 */
function core_upgrade_preamble(): void {
	global $wp_version, $required_php_version, $required_mysql_version;

	$updates = get_core_updates();

	if ( ! isset( $updates[0]->response ) || 'latest' === $updates[0]->response ) {
		echo '<h3>';
		_e( 'You have the latest version of WordPress.' );

		if ( wp_http_supports( [ 'ssl' ] ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$upgrader            = new WP_Automatic_Updater();
			$future_minor_update = (object) [
				'current'       => $wp_version . '.1.next.minor',
				'version'       => $wp_version . '.1.next.minor',
				'php_version'   => $required_php_version,
				'mysql_version' => $required_mysql_version,
			];

			if ( $upgrader->should_update( 'core', $future_minor_update, ABSPATH ) ) {
				echo ' ' . __( 'Future security updates will be applied automatically.' );
			}
		}

		echo '</h3>';
	} else {
		echo '<div class="updated inline"><p>';
		_e( '<strong>Important:</strong> before updating, please <a href="http://codex.wordpress.org/WordPress_Backups">back up your database and files</a>. For help with updates, visit the <a href="http://codex.wordpress.org/Updating_WordPress">Updating WordPress</a> Codex page.' );
		echo '</p></div>';

		echo '<h3 class="response">';
		_e( 'An updated version of WordPress is available.' );
		echo '</h3>';
	}

	if ( isset( $updates[0] ) && 'development' === $updates[0]->response ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$upgrader = new WP_Automatic_Updater();

		if ( wp_http_supports( 'ssl' ) && $upgrader->should_update( 'core', $updates[0], ABSPATH ) ) {
			echo '<div class="updated inline"><p>';
			echo '<strong>' . __( 'BETA TESTERS:' ) . '</strong> ' . __( 'This site is set up to install updates of future beta versions automatically.' );
			echo '</p></div>';
		}
	}

	echo '<ul class="core-updates">';

	foreach ( (array) $updates as $update ) {
		echo '<li>';
		list_core_update( $update );
		echo '</li>';
	}

	echo '</ul>';

	if ( $updates && ( count( $updates ) > 1 || 'latest' !== $updates[0]->response ) ) {
		echo '<p>' . __( 'While your site is being updated, it will be in maintenance mode. As soon as your updates are complete, your site will return to normal.' ) . '</p>';
	} elseif ( empty( $updates ) ) {
		[ $normalized_version ] = explode( '-', $wp_version );
		echo '<p>' . sprintf( __( '<a href="%s">Learn more about WordPress %s</a>.' ), esc_url( self_admin_url( 'about.php' ) ), esc_html( $normalized_version ) ) . '</p>';
	}

	dismissed_updates();
}

function list_plugin_updates(): void {
	global $wp_version;

	$cur_wp_version = preg_replace( '/-.*$/', '', $wp_version );

	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	$plugins = get_plugin_updates();

	if ( empty( $plugins ) ) {
		echo '<h3>' . esc_html__( 'Plugins' ) . '</h3>';
		echo '<p>' . esc_html__( 'Your plugins are all up to date.' ) . '</p>';
		return;
	}

	$form_action = 'update-core.php?action=do-plugin-upgrade';

	$core_updates = get_core_updates();

	if (
		! isset( $core_updates[0]->response )
		|| 'latest' === $core_updates[0]->response
		|| 'development' === $core_updates[0]->response
		|| 0 === version_compare( $core_updates[0]->current, $cur_wp_version )
	) {
		$core_update_version = false;
	} else {
		$core_update_version = $core_updates[0]->current;
	}
	?>
	<h3><?php esc_html_e( 'Plugins' ); ?></h3>
	<p><?php esc_html_e( 'The following plugins have new versions available. Check the ones you want to update and then click &#8220;Update Plugins&#8221;.' ); ?></p>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-plugins" class="upgrade">
		<?php wp_nonce_field( 'upgrade-core' ); ?>
		<p><input id="upgrade-plugins" class="button" type="submit" value="<?php esc_attr_e( 'Update Plugins' ); ?>" name="upgrade" /></p>
		<table class="widefat" id="update-plugins-table">
			<thead>
				<tr>
					<th scope="col" class="manage-column check-column"><input type="checkbox" id="plugins-select-all" /></th>
					<th scope="col" class="manage-column"><label for="plugins-select-all"><?php esc_html_e( 'Select All' ); ?></label></th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<th scope="col" class="manage-column check-column"><input type="checkbox" id="plugins-select-all-2" /></th>
					<th scope="col" class="manage-column"><label for="plugins-select-all-2"><?php esc_html_e( 'Select All' ); ?></label></th>
				</tr>
			</tfoot>
			<tbody class="plugins">
			<?php
			foreach ( (array) $plugins as $plugin_file => $plugin_data ) {
				$info = plugins_api(
					'plugin_information',
					[
						'slug' => $plugin_data->update->slug,
					]
				);

				if ( is_wp_error( $info ) ) {
					$info = new stdClass();
				}

				$compat = '';

				if ( isset( $info->tested ) && version_compare( $info->tested, $cur_wp_version, '>=' ) ) {
					$compat = '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)' ), esc_html( $cur_wp_version ) );
				} elseif (
					isset( $info->compatibility[ $cur_wp_version ][ $plugin_data->update->new_version ] )
					&& is_array( $info->compatibility[ $cur_wp_version ][ $plugin_data->update->new_version ] )
				) {
					$compatibility = $info->compatibility[ $cur_wp_version ][ $plugin_data->update->new_version ];
					$compat        = '<br />' . sprintf(
						__( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' ),
						esc_html( $cur_wp_version ),
						(int) $compatibility[0],
						(int) $compatibility[2],
						(int) $compatibility[1]
					);
				} else {
					$compat = '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), esc_html( $cur_wp_version ) );
				}

				if ( $core_update_version ) {
					if (
						isset( $info->compatibility[ $core_update_version ][ $plugin_data->update->new_version ] )
						&& is_array( $info->compatibility[ $core_update_version ][ $plugin_data->update->new_version ] )
					) {
						$update_compat = $info->compatibility[ $core_update_version ][ $plugin_data->update->new_version ];
						$compat       .= '<br />' . sprintf(
							__( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' ),
							esc_html( $core_update_version ),
							(int) $update_compat[0],
							(int) $update_compat[2],
							(int) $update_compat[1]
						);
					} else {
						$compat .= '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), esc_html( $core_update_version ) );
					}
				}

				$upgrade_notice = '';
				if ( isset( $plugin_data->update->upgrade_notice ) ) {
					$upgrade_notice = '<br />' . wp_strip_all_tags( $plugin_data->update->upgrade_notice );
				}

				$details_url  = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_data->update->slug . '&section=changelog&TB_iframe=true&width=640&height=662' );
				$details_text = sprintf( __( 'View version %1$s details' ), esc_html( $plugin_data->update->new_version ) );
				$details      = sprintf(
					'<a href="%1$s" class="thickbox" title="%2$s">%3$s</a>.',
					esc_url( $details_url ),
					esc_attr( $plugin_data->Name ),
					esc_html( $details_text )
				);
				?>
				<tr>
					<th scope="row" class="check-column"><input type="checkbox" name="checked[]" value="<?php echo esc_attr( $plugin_file ); ?>" /></th>
					<td>
						<p>
							<strong><?php echo esc_html( $plugin_data->Name ); ?></strong><br />
							<?php
							printf(
								/* translators: 1: Current plugin version, 2: New plugin version. */
								__( 'You have version %1$s installed. Update to %2$s.' ),
								esc_html( $plugin_data->Version ),
								esc_html( $plugin_data->update->new_version )
							);
							echo ' ' . wp_kses_post( $details . $compat . $upgrade_notice );
							?>
						</p>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<p><input id="upgrade-plugins-2" class="button" type="submit" value="<?php esc_attr_e( 'Update Plugins' ); ?>" name="upgrade" /></p>
	</form>
	<?php
}

function list_theme_updates(): void {
	$themes = get_theme_updates();

	if ( empty( $themes ) ) {
		echo '<h3>' . esc_html__( 'Themes' ) . '</h3>';
		echo '<p>' . esc_html__( 'Your themes are all up to date.' ) . '</p>';
		return;
	}

	$form_action = 'update-core.php?action=do-theme-upgrade';
	?>
	<h3><?php esc_html_e( 'Themes' ); ?></h3>
	<p><?php esc_html_e( 'The following themes have new versions available. Check the ones you want to update and then click &#8220;Update Themes&#8221;.' ); ?></p>
	<p><?php printf( __( '<strong>Please Note:</strong> Any customizations you have made to theme files will be lost. Please consider using <a href="%s">child themes</a> for modifications.' ), esc_url( __( 'http://codex.wordpress.org/Child_Themes' ) ) ); ?></p>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-themes" class="upgrade">
		<?php wp_nonce_field( 'upgrade-core' ); ?>
		<p><input id="upgrade-themes" class="button" type="submit" value="<?php esc_attr_e( 'Update Themes' ); ?>" name="upgrade" /></p>
		<table class="widefat" id="update-themes-table">
			<thead>
				<tr>
					<th scope="col" class="manage-column check-column"><input type="checkbox" id="themes-select-all" /></th>
					<th scope="col" class="manage-column"><label for="themes-select-all"><?php esc_html_e( 'Select All' ); ?></label></th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<th scope="col" class="manage-column check-column"><input type="checkbox" id="themes-select-all-2" /></th>
					<th scope="col" class="manage-column"><label for="themes-select-all-2"><?php esc_html_e( 'Select All' ); ?></label></th>
				</tr>
			</tfoot>
			<tbody class="plugins">
			<?php
			foreach ( $themes as $stylesheet => $theme ) {
				$screenshot = $theme->get_screenshot();
				?>
				<tr>
					<th scope="row" class="check-column"><input type="checkbox" name="checked[]" value="<?php echo esc_attr( $stylesheet ); ?>" /></th>
					<td class="plugin-title">
						<?php if ( $screenshot ) : ?>
							<img src="<?php echo esc_url( $screenshot ); ?>" width="85" height="64" style="float:left; padding: 0 5px 5px" alt="" />
						<?php endif; ?>
						<strong><?php echo esc_html( $theme->display( 'Name' ) ); ?></strong>
						<?php
						printf(
							/* translators: 1: Current theme version, 2: New theme version. */
							__( 'You have version %1$s installed. Update to %2$s.' ),
							esc_html( $theme->display( 'Version' ) ),
							esc_html( $theme->update['new_version'] )
						);
						?>
					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<p><input id="upgrade-themes-2" class="button" type="submit" value="<?php esc_attr_e( 'Update Themes' ); ?>" name="upgrade" /></p>
	</form>
	<?php
}

function list_translation_updates(): void {
	$updates = wp_get_translation_updates();

	if ( ! $updates ) {
		if ( 'en_US' !== get_locale() ) {
			echo '<h3>' . esc_html__( 'Translations' ) . '</h3>';
			echo '<p>' . esc_html__( 'Your translations are all up to date.' ) . '</p>';
		}
		return;
	}

	$form_action = 'update-core.php?action=do-translation-upgrade';
	?>
	<h3><?php esc_html_e( 'Translations' ); ?></h3>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-translations" class="upgrade">
		<p><?php esc_html_e( 'Some of your translations are out of date.' ); ?></p>
		<?php wp_nonce_field( 'upgrade-translations' ); ?>
		<p><input class="button" type="submit" value="<?php esc_attr_e( 'Update Translations' ); ?>" name="upgrade" /></p>
	</form>
	<?php
}

/**
 * Upgrade WordPress core display.
 *
 * @since 2.7.0
 */
function do_core_upgrade( bool $reinstall = false ): void {
	global $wp_filesystem;

	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$url = $reinstall
		? 'update-core.php?action=do-core-reinstall'
		: 'update-core.php?action=do-core-upgrade';

	$url     = wp_nonce_url( $url, 'upgrade-core' );
	$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : false;
	$locale  = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';
	$update  = find_core_update( $version, $locale );

	if ( ! $update ) {
		return;
	}
	?>
	<div class="wrap">
	<h2><?php esc_html_e( 'Update WordPress' ); ?></h2>
	<?php

	$credentials = request_filesystem_credentials( $url, '', false, ABSPATH );

	if ( false === $credentials ) {
		echo '</div>';
		return;
	}

	if ( ! WP_Filesystem( $credentials, ABSPATH ) ) {
		request_filesystem_credentials( $url, '', true, ABSPATH );
		echo '</div>';
		return;
	}

	if ( isset( $wp_filesystem->errors ) && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
		foreach ( $wp_filesystem->errors->get_error_messages() as $message ) {
			show_message( $message );
		}
		echo '</div>';
		return;
	}

	if ( $reinstall ) {
		$update->response = 'reinstall';
	}

	add_filter( 'update_feedback', 'show_message' );

	$upgrader = new Core_Upgrader();
	$result   = $upgrader->upgrade( $update );

	if ( is_wp_error( $result ) ) {
		show_message( $result );
		if ( 'up_to_date' !== $result->get_error_code() ) {
			show_message( __( 'Installation Failed' ) );
		}
		echo '</div>';
		return;
	}

	show_message( __( 'WordPress updated successfully' ) );
	show_message(
		'<span class="hide-if-no-js">' . sprintf(
			__( 'Welcome to WordPress %1$s. You will be redirected to the About WordPress screen. If not, click <a href="%2$s">here</a>.' ),
			$result,
			esc_url( self_admin_url( 'about.php?updated' ) )
		) . '</span>'
	);
	show_message(
		'<span class="hide-if-js">' . sprintf(
			__( 'Welcome to WordPress %1$s. <a href="%2$s">Learn more</a>.' ),
			$result,
			esc_url( self_admin_url( 'about.php?updated' ) )
		) . '</span>'
	);
	?>
	</div>
	<script type="text/javascript">
	window.location = '<?php echo esc_url( self_admin_url( 'about.php?updated' ) ); ?>';
	</script>
	<?php
}

function do_dismiss_core_update(): void {
	$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : false;
	$locale  = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';
	$update  = find_core_update( $version, $locale );

	if ( ! $update ) {
		return;
	}

	dismiss_core_update( $update );
	wp_safe_redirect( wp_nonce_url( 'update-core.php?action=upgrade-core', 'upgrade-core' ) );
	exit;
}

function do_undismiss_core_update(): void {
	$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : false;
	$locale  = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';
	$update  = find_core_update( $version, $locale );

	if ( ! $update ) {
		return;
	}

	undismiss_core_update( $version, $locale );
	wp_safe_redirect( wp_nonce_url( 'update-core.php?action=upgrade-core', 'upgrade-core' ) );
	exit;
}

$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'upgrade-core';

$upgrade_error = false;

if ( ( 'do-theme-upgrade' === $action || ( 'do-plugin-upgrade' === $action && ! isset( $_GET['plugins'] ) ) ) && ! isset( $_POST['checked'] ) ) {
	$upgrade_error = 'do-theme-upgrade' === $action ? 'themes' : 'plugins';
	$action        = 'upgrade-core';
}

$title       = __( 'WordPress Updates' );
$parent_file = 'index.php';

$updates_overview  = '<p>' . __( 'On this screen, you can update to the latest version of WordPress, as well as update your themes and plugins from the WordPress.org repositories.' ) . '</p>';
$updates_overview .= '<p>' . __( 'If an update is available, you&#8127;ll see a notification appear in the Toolbar and navigation menu.' ) . ' ' . __( 'Keeping your site updated is important for security. It also makes the internet a safer place for you and your readers.' ) . '</p>';

$current_screen = get_current_screen();

$current_screen->add_help_tab(
	[
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $updates_overview,
	]
);

$updates_howto  = '<p>' . __( '<strong>WordPress</strong> &mdash; Updating your WordPress installation is a simple one-click procedure: just <strong>click on the &#8220;Update Now&#8221; button</strong> when you are notified that a new version is available.' ) . ' ' . __( 'In most cases, WordPress will automatically apply maintenance and security updates in the background for you.' ) . '</p>';
$updates_howto .= '<p>' . __( '<strong>Themes and Plugins</strong> &mdash; To update individual themes or plugins from this screen, use the checkboxes to make your selection, then <strong>click on the appropriate &#8220;Update&#8221; button</strong>. To update all of your themes or plugins at once, you can check the box at the top of the section to select all before clicking the update button.' ) . '</p>';

if ( 'en_US' !== get_locale() ) {
	$updates_howto .= '<p>' . __( '<strong>Translations</strong> &mdash; The files translating WordPress into your language are updated for you whenever any other updates occur. But if these files are out of date, you can <strong>click the &#8220;Update Translations&#8221;</strong> button.' ) . '</p>';
}

$current_screen->add_help_tab(
	[
		'id'      => 'how-to-update',
		'title'   => __( 'How to Update' ),
		'content' => $updates_howto,
	]
);

$current_screen->set_help_sidebar(
	'<p><strong>' . esc_html__( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="http://codex.wordpress.org/Dashboard_Updates_Screen" target="_blank">Documentation on Updating WordPress</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>' ) . '</p>'
);

if ( 'upgrade-core' === $action ) {
	$force_check = isset( $_GET['force-check'] ) && (int) $_GET['force-check'] === 1;

	wp_version_check( [], $force_check );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	?>
	<div class="wrap">
	<h2><?php esc_html_e( 'WordPress Updates' ); ?></h2>
	<?php
	if ( $upgrade_error ) {
		echo '<div class="error"><p>';
		if ( 'themes' === $upgrade_error ) {
			esc_html_e( 'Please select one or more themes to update.' );
		} else {
			esc_html_e( 'Please select one or more plugins to update.' );
		}
		echo '</p></div>';
	}

	echo '<p>';
	printf(
		/* translators: 1: Date, 2: Time. */
		__( 'Last checked on %1$s at %2$s.' ),
		esc_html( date_i18n( get_option( 'date_format' ) ) ),
		esc_html( date_i18n( get_option( 'time_format' ) ) )
	);
	echo ' &nbsp; <a class="button" href="' . esc_url( self_admin_url( 'update-core.php?force-check=1' ) ) . '">' . esc_html__( 'Check Again' ) . '</a>';
	echo '</p>';

	$core    = current_user_can( 'update_core' );
	$plugins = current_user_can( 'update_plugins' );
	$themes  = current_user_can( 'update_themes' );

	if ( $core ) {
		core_upgrade_preamble();
	}
	if ( $plugins ) {
		list_plugin_updates();
	}
	if ( $themes ) {
		list_theme_updates();
	}
	if ( $core || $plugins || $themes ) {
		list_translation_updates();
	}

	do_action( 'core_upgrade_preamble' );
	echo '</div>';
	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-core-upgrade' === $action || 'do-core-reinstall' === $action ) {

	if ( ! current_user_can( 'update_core' ) ) {
		wp_die( __( 'You do not have sufficient permissions to update this site.' ) );
	}

	check_admin_referer( 'upgrade-core' );

	if ( isset( $_POST['dismiss'] ) ) {
		do_dismiss_core_update();
	} elseif ( isset( $_POST['undismiss'] ) ) {
		do_undismiss_core_update();
	}

	require_once ABSPATH . 'wp-admin/admin-header.php';

	$reinstall = 'do-core-reinstall' === $action;

	if ( isset( $_POST['upgrade'] ) ) {
		do_core_upgrade( $reinstall );
	}

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-plugin-upgrade' === $action ) {

	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( __( 'You do not have sufficient permissions to update this site.' ) );
	}

	check_admin_referer( 'upgrade-core' );

	if ( isset( $_GET['plugins'] ) ) {
		$plugins = array_map( 'trim', explode( ',', wp_unslash( $_GET['plugins'] ) ) );
	} elseif ( isset( $_POST['checked'] ) ) {
		$plugins = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['checked'] ) );
	} else {
		wp_safe_redirect( admin_url( 'update-core.php' ) );
		exit;
	}

	$url = 'update.php?action=update-selected&plugins=' . urlencode( implode( ',', $plugins ) );
	$url = wp_nonce_url( $url, 'bulk-update-plugins' );

	$title = __( 'Update Plugins' );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	echo '<div class="wrap">';
	echo '<h2>' . esc_html__( 'Update Plugins' ) . '</h2>';
	echo '<iframe src="' . esc_url( $url ) . '" style="width: 100%; height: 100%; min-height: 750px;" frameborder="0"></iframe>';
	echo '</div>';
	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-theme-upgrade' === $action ) {

	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( __( 'You do not have sufficient permissions to update this site.' ) );
	}

	check_admin_referer( 'upgrade-core' );

	if ( isset( $_GET['themes'] ) ) {
		$themes = array_map( 'trim', explode( ',', wp_unslash( $_GET['themes'] ) ) );
	} elseif ( isset( $_POST['checked'] ) ) {
		$themes = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['checked'] ) );
	} else {
		wp_safe_redirect( admin_url( 'update-core.php' ) );
		exit;
	}

	$url = 'update.php?action=update-selected-themes&themes=' . urlencode( implode( ',', $themes ) );
	$url = wp_nonce_url( $url, 'bulk-update-themes' );

	$title = __( 'Update Themes' );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Update Themes' ); ?></h2>
		<iframe src="<?php echo esc_url( $url ); ?>" style="width: 100%; height: 100%; min-height: 750px;" frameborder="0"></iframe>
	</div>
	<?php
	require_once ABSPATH . 'wp-admin/admin-footer.php';

} elseif ( 'do-translation-upgrade' === $action ) {

	if ( ! current_user_can( 'update_core' ) && ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
		wp_die( __( 'You do not have sufficient permissions to update this site.' ) );
	}

	check_admin_referer( 'upgrade-translations' );

	require_once ABSPATH . 'wp-admin/admin-header.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$url     = 'update-core.php?action=do-translation-upgrade';
	$nonce   = 'upgrade-translations';
	$title   = __( 'Update Translations' );
	$context = WP_LANG_DIR;

	$upgrader = new Language_Pack_Upgrader(
		new Language_Pack_Upgrader_Skin(
			compact( 'url', 'nonce', 'title', 'context' )
		)
	);

	$upgrader->bulk_upgrade();

	require_once ABSPATH . 'wp-admin/admin-footer.php';

} else {
	/**
	 * Fires for each custom update action on the WordPress Updates screen.
	 *
	 * The dynamic portion of the hook name, $action, refers to the
	 * passed update action. The hook fires in lieu of all available
	 * default update actions.
	 *
	 * @since 3.2.0
	 */
	do_action( "update-core-custom_{$action}" );
}
?>
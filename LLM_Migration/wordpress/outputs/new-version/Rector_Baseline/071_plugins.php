<?php
/**
 * Plugins administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( __DIR__ . '/admin.php' );

if ( ! current_user_can('activate_plugins') )
	wp_die( __() );

$wp_list_table = _get_list_table('WP_Plugins_List_Table');
$pagenum = $wp_list_table->get_pagenum();

$action = $wp_list_table->current_action();

$plugin = $_REQUEST['plugin'] ?? '';
$s = isset($_REQUEST['s']) ? urlencode((string) $_REQUEST['s']) : '';

// Clean up request URI from temporary args for screen options/paging uri's to work as expected.
$_SERVER['REQUEST_URI'] = remove_query_arg(['error', 'deleted', 'activate', 'activate-multi', 'deactivate', 'deactivate-multi', '_error_nonce'], $_SERVER['REQUEST_URI']);

if ( $action ) {

	switch ( $action ) {
		case 'activate':
			if ( ! current_user_can('activate_plugins') )
				wp_die(__());

			if ( is_multisite() && ! is_network_admin() && is_network_only_plugin( $plugin ) ) {
				wp_redirect( self_admin_url("plugins.php?plugin_status=$status&paged=$page&s=$s") );
				exit;
			}

			check_admin_referer('activate-plugin_' . $plugin);

			$result = activate_plugin($plugin, self_admin_url('plugins.php?error=true&plugin=' . $plugin), is_network_admin() );
			if ( is_wp_error( $result ) ) {
				if ( 'unexpected_output' == $result->get_error_code() ) {
					$redirect = self_admin_url('plugins.php?error=true&charsout=' . strlen((string) $result->get_error_data()) . '&plugin=' . $plugin . "&plugin_status=$status&paged=$page&s=$s");
					wp_redirect(add_query_arg('_error_nonce', wp_create_nonce('plugin-activation-error_' . $plugin), $redirect));
					exit;
				} else {
					wp_die($result);
				}
			}

			if ( ! is_network_admin() ) {
				$recent = (array) get_option();
				unset( $recent[ $plugin ] );
				update_option( 'recently_activated', $recent );
			}

			if ( isset($_GET['from']) && 'import' == $_GET['from'] ) {
				wp_redirect( self_admin_url("import.php?import=" . str_replace('-importer', '', dirname((string) $plugin))) ); // overrides the ?error=true one above and redirects to the Imports page, stripping the -importer suffix
			} else {
				wp_redirect( self_admin_url("plugins.php?activate=true&plugin_status=$status&paged=$page&s=$s") ); // overrides the ?error=true one above
			}
			exit;

		case 'activate-selected':
			if ( ! current_user_can('activate_plugins') )
				wp_die(__());

			check_admin_referer('bulk-plugins');

			$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : [];

			if ( is_network_admin() ) {
				foreach ( $plugins as $i => $plugin ) {
					// Only activate plugins which are not already network activated.
					if ( is_plugin_active_for_network( $plugin ) ) {
						unset( $plugins[ $i ] );
					}
				}
			} else {
				foreach ( $plugins as $i => $plugin ) {
					// Only activate plugins which are not already active and are not network-only when on Multisite.
					if ( is_plugin_active( $plugin ) || ( is_multisite() && is_network_only_plugin( $plugin ) ) ) {
						unset( $plugins[ $i ] );
					}
				}
			}

			if ( empty($plugins) ) {
				wp_redirect( self_admin_url("plugins.php?plugin_status=$status&paged=$page&s=$s") );
				exit;
			}

			activate_plugins($plugins, self_admin_url('plugins.php?error=true'), is_network_admin() );

			if ( ! is_network_admin() ) {
				$recent = (array) get_option();
				foreach ( $plugins as $plugin )
					unset( $recent[ $plugin ] );
				update_option( 'recently_activated', $recent );
			}

			wp_redirect( self_admin_url("plugins.php?activate-multi=true&plugin_status=$status&paged=$page&s=$s") );
			exit;

		case 'update-selected' :

			check_admin_referer( 'bulk-plugins' );

			if ( isset( $_GET['plugins'] ) )
				$plugins = explode( ',', (string) $_GET['plugins'] );
			elseif ( isset( $_POST['checked'] ) )
				$plugins = (array) $_POST['checked'];
			else
				$plugins = [];

			$title = __();
			$parent_file = 'plugins.php';

			wp_enqueue_script( 'updates' );
			require_once(ABSPATH . 'wp-admin/admin-header.php');

			echo '<div class="wrap">';
			echo '<h2>' . esc_html( $title ) . '</h2>';

			$url = self_admin_url('update.php?action=update-selected&amp;plugins=' . urlencode( join(',', $plugins) ));
			$url = wp_nonce_url($url, 'bulk-update-plugins');

			echo "<iframe src='$url' style='width: 100%; height:100%; min-height:850px;'></iframe>";
			echo '</div>';
			require_once(ABSPATH . 'wp-admin/admin-footer.php');
			exit;

		case 'error_scrape':
			if ( ! current_user_can('activate_plugins') )
				wp_die(__());

			check_admin_referer('plugin-activation-error_' . $plugin);

			$valid = validate_plugin($plugin);
			if ( is_wp_error($valid) )
				wp_die($valid);

			if ( ! WP_DEBUG ) {
				error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
			}

			@ini_set('display_errors', true); //Ensure that Fatal errors are displayed.
			// Go back to "sandbox" scope so we get the same errors as before
			function plugin_sandbox_scrape( $plugin ) {
				wp_register_plugin_realpath( WP_PLUGIN_DIR . '/' . $plugin );
				include( WP_PLUGIN_DIR . '/' . $plugin );
			}
			plugin_sandbox_scrape( $plugin );
			/** This action is documented in wp-admin/includes/plugins.php */
			do_action( "activate_{$plugin}" );
			exit;

		case 'deactivate':
			if ( ! current_user_can('activate_plugins') )
				wp_die(__());

			check_admin_referer('deactivate-plugin_' . $plugin);

			if ( ! is_network_admin() && is_plugin_active_for_network( $plugin ) ) {
				wp_redirect( self_admin_url("plugins.php?plugin_status=$status&paged=$page&s=$s") );
				exit;
			}

			deactivate_plugins( $plugin, false, is_network_admin() );
			if ( ! is_network_admin() )
				update_option( 'recently_activated', [ $plugin => time() ] + (array) get_option() );
			if ( headers_sent() )
				echo "<meta http-equiv='refresh' content='" . esc_attr() . "' />";
			else
				wp_redirect( self_admin_url("plugins.php?deactivate=true&plugin_status=$status&paged=$page&s=$s") );
			exit;

		case 'deactivate-selected':
			if ( ! current_user_can('activate_plugins') )
				wp_die(__());

			check_admin_referer('bulk-plugins');

			$plugins = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : [];
			// Do not deactivate plugins which are already deactivated.
			if ( is_network_admin() ) {
				$plugins = array_filter( $plugins, is_plugin_active_for_network(...) );
			} else {
				$plugins = array_filter( $plugins, is_plugin_active(...) );
				$plugins = array_diff( $plugins, array_filter( $plugins, is_plugin_active_for_network(...) ) );
			}
			if ( empty($plugins) ) {
				wp_redirect( self_admin_url("plugins.php?plugin_status=$status&paged=$page&s=$s") );
				exit;
			}

			deactivate_plugins( $plugins, false, is_network_admin() );

			if ( ! is_network_admin() ) {
				$deactivated = [];
				foreach ( $plugins as $plugin )
					$deactivated[ $plugin ] = time();
				update_option( 'recently_activated', $deactivated + (array) get_option() );
			}

			wp_redirect( self_admin_url("plugins.php?deactivate-multi=true&plugin_status=$status&paged=$page&s=$s") );
			exit;

		case 'delete-selected':
			if ( ! current_user_can('delete_plugins') )
				wp_die(__());

			check_admin_referer('bulk-plugins');

			//$_POST = from the plugin form; $_GET = from the FTP details screen.
			$plugins = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : [];
			if ( empty( $plugins ) ) {
				wp_redirect( self_admin_url("plugins.php?plugin_status=$status&paged=$page&s=$s") );
				exit;
			}

			$plugins = array_filter($plugins, is_plugin_inactive(...)); // Do not allow to delete Activated plugins.
			if ( empty( $plugins ) ) {
				wp_redirect( self_admin_url( "plugins.php?error=true&main=true&plugin_status=$status&paged=$page&s=$s" ) );
				exit;
			}

			include(ABSPATH . 'wp-admin/update.php');

			$parent_file = 'plugins.php';

			if ( ! isset($_REQUEST['verify-delete']) ) {
				wp_enqueue_script('jquery');
				require_once(ABSPATH . 'wp-admin/admin-header.php');
				?>
			<div class="wrap">
				<?php
					$files_to_delete = $plugin_info = [];
					$have_non_network_plugins = false;
					foreach ( (array) $plugins as $plugin ) {
						if ( '.' == dirname((string) $plugin) ) {
							$files_to_delete[] = WP_PLUGIN_DIR . '/' . $plugin;
							if( $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin) ) {
								$plugin_info[ $plugin ] = $data;
								$plugin_info[ $plugin ]['is_uninstallable'] = is_uninstallable_plugin( $plugin );
								if ( ! $plugin_info[ $plugin ]['Network'] )
									$have_non_network_plugins = true;
							}
						} else {
							// Locate all the files in that folder
							$files = list_files( WP_PLUGIN_DIR . '/' . dirname((string) $plugin) );
							if ( $files ) {
								$files_to_delete = array_merge($files_to_delete, $files);
							}
							// Get plugins list from that folder
							if ( $folder_plugins = get_plugins( '/' . dirname((string) $plugin)) ) {
								foreach( $folder_plugins as $plugin_file => $data ) {
									$plugin_info[ $plugin_file ] = _get_plugin_data_markup_translate( $plugin_file, $data );
									$plugin_info[ $plugin_file ]['is_uninstallable'] = is_uninstallable_plugin( $plugin );
									if ( ! $plugin_info[ $plugin_file ]['Network'] )
										$have_non_network_plugins = true;
								}
							}
						}
					}
					$plugins_to_delete = count( $plugin_info );
					echo '<h2>' . _n( 'Delete Plugin', 'Delete Plugins', $plugins_to_delete ) . '</h2>';
				?>
				<?php if ( $have_non_network_plugins && is_network_admin() ) : ?>
				<div class="error"><p><strong><?php _e( 'Caution:' ); ?></strong> <?php echo _n( 'This plugin may be active on other sites in the network.', 'These plugins may be active on other sites in the network.', $plugins_to_delete ); ?></p></div>
				<?php endif; ?>
				<p><?php echo _n( 'You are about to remove the following plugin:', 'You are about to remove the following plugins:', $plugins_to_delete ); ?></p>
					<ul class="ul-disc">
						<?php
						$data_to_delete = false;
						foreach ( $plugin_info as $plugin ) {
							if ( $plugin['is_uninstallable'] ) {
								/* translators: 1: plugin name, 2: plugin author */
								echo '<li>', sprintf( __(), esc_html($plugin['Name']), esc_html($plugin['AuthorName']) ), '</li>';
								$data_to_delete = true;
							} else {
								/* translators: 1: plugin name, 2: plugin author */
								echo '<li>', sprintf( __(), esc_html($plugin['Name']), esc_html($plugin['AuthorName']) ), '</li>';
							}
						}
						?>
					</ul>
				<p><?php
				if ( $data_to_delete )
					_e('Are you sure you wish to delete these files and data?');
				else
					_e('Are you sure you wish to delete these files?');
				?></p>
				<form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" style="display:inline;">
					<input type="hidden" name="verify-delete" value="1" />
					<input type="hidden" name="action" value="delete-selected" />
					<?php
						foreach ( (array) $plugins as $plugin )
							echo '<input type="hidden" name="checked[]" value="' . esc_attr() . '" />';
					?>
					<?php wp_nonce_field('bulk-plugins') ?>
					<?php submit_button( $data_to_delete ? __() : __(), 'button', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url(wp_get_referer()); ?>" style="display:inline;">
					<?php submit_button( __(), 'button', 'submit', false ); ?>
				</form>

				<p><a href="#" onclick="jQuery('#files-list').toggle(); return false;"><?php _e('Click to view entire list of files which will be deleted'); ?></a></p>
				<div id="files-list" style="display:none;">
					<ul class="code">
					<?php
						foreach ( (array)$files_to_delete as $file )
							echo '<li>' . esc_html(str_replace(WP_PLUGIN_DIR, '', $file)) . '</li>';
					?>
					</ul>
				</div>
			</div>
				<?php
				require_once(ABSPATH . 'wp-admin/admin-footer.php');
				exit;
			} //Endif verify-delete
			$delete_result = delete_plugins($plugins);

			set_transient('plugins_delete_result_' . $user_ID, $delete_result); //Store the result in a cache rather than a URL param due to object type & length
			wp_redirect( self_admin_url("plugins.php?deleted=true&plugin_status=$status&paged=$page&s=$s") );
			exit;

		case 'clear-recent-list':
			if ( ! is_network_admin() )
				update_option( 'recently_activated', [] );
			break;
	}
}

$wp_list_table->prepare_items();

wp_enqueue_script('plugin-install');
add_thickbox();

add_screen_option( 'per_page', ['label' => _x(), 'default' => 999 ] );

get_current_screen()->add_help_tab( [
'id'		=> 'overview',
'title'		=> __(),
'content'	=>
	'<p>' . __() . '</p>' .
	'<p>' . sprintf(__(), 'plugin-install.php', 'https://wordpress.org/plugins/') . '</p>'
] );
get_current_screen()->add_help_tab( [
'id'		=> 'compatibility-problems',
'title'		=> __(),
'content'	=>
	'<p>' . __() . '</p>' .
	'<p>' . sprintf( __(), WP_PLUGIN_DIR) . '</p>'
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

$title = __();
$parent_file = 'plugins.php';

require_once(ABSPATH . 'wp-admin/admin-header.php');

$invalid = validate_active_plugins();
if ( !empty($invalid) )
	foreach ( $invalid as $plugin_file => $error )
		echo '<div id="message" class="error"><p>' . sprintf(__(), esc_html($plugin_file), $error->get_error_message()) . '</p></div>';
?>

<?php if ( isset($_GET['error']) ) :

	if ( isset( $_GET['main'] ) )
		$errmsg = __();
	elseif ( isset($_GET['charsout']) )
		$errmsg = sprintf(__(), $_GET['charsout']);
	else
		$errmsg = __();
	?>
	<div id="message" class="error"><p><?php echo $errmsg; ?></p>
	<?php
		if ( !isset( $_GET['main'] ) && !isset($_GET['charsout']) && wp_verify_nonce($_GET['_error_nonce'], 'plugin-activation-error_' . $plugin) ) { ?>
	<iframe style="border:0" width="100%" height="70px" src="<?php echo 'plugins.php?action=error_scrape&amp;plugin=' . esc_attr() . '&amp;_wpnonce=' . esc_attr(); ?>"></iframe>
	<?php
		}
	?>
	</div>
<?php elseif ( isset($_GET['deleted']) ) :
		$delete_result = get_transient( 'plugins_delete_result_' . $user_ID );
		// Delete it once we're done.
		delete_transient( 'plugins_delete_result_' . $user_ID );

		if ( is_wp_error($delete_result) ) : ?>
		<div id="message" class="error"><p><?php printf( __(), $delete_result->get_error_message() ); ?></p></div>
		<?php else : ?>
		<div id="message" class="updated"><p><?php _e('The selected plugins have been <strong>deleted</strong>.'); ?></p></div>
		<?php endif; ?>
<?php elseif ( isset($_GET['activate']) ) : ?>
	<div id="message" class="updated"><p><?php _e('Plugin <strong>activated</strong>.') ?></p></div>
<?php elseif (isset($_GET['activate-multi'])) : ?>
	<div id="message" class="updated"><p><?php _e('Selected plugins <strong>activated</strong>.'); ?></p></div>
<?php elseif ( isset($_GET['deactivate']) ) : ?>
	<div id="message" class="updated"><p><?php _e('Plugin <strong>deactivated</strong>.') ?></p></div>
<?php elseif (isset($_GET['deactivate-multi'])) : ?>
	<div id="message" class="updated"><p><?php _e('Selected plugins <strong>deactivated</strong>.'); ?></p></div>
<?php elseif ( 'update-selected' == $action ) : ?>
	<div id="message" class="updated"><p><?php _e('No out of date plugins were selected.'); ?></p></div>
<?php endif; ?>

<div class="wrap">
<h2><?php echo esc_html( $title );
if ( ( ! is_multisite() || is_network_admin() ) && current_user_can('install_plugins') ) { ?>
 <a href="<?php echo self_admin_url( 'plugin-install.php' ); ?>" class="add-new-h2"><?php echo esc_html_x('Add New', 'plugin'); ?></a>
<?php }
if ( $s )
	printf( '<span class="subtitle">' . __() . '</span>', esc_html( $s ) ); ?>
</h2>

<?php
/**
 * Fires before the plugins list table is rendered.
 *
 * This hook also fires before the plugins list table is rendered in the Network Admin.
 *
 * Please note: The 'active' portion of the hook name does not refer to whether the current
 * view is for active plugins, but rather all plugins actively-installed.
 *
 * @since 3.0.0
 *
 * @param array $plugins_all An array containing all installed plugins.
 */
do_action( 'pre_current_active_plugins', $plugins['all'] );
?>

<?php $wp_list_table->views(); ?>

<form method="get" action="">
<?php $wp_list_table->search_box( __(), 'plugin' ); ?>
</form>

<form method="post" action="">

<input type="hidden" name="plugin_status" value="<?php echo esc_attr() ?>" />
<input type="hidden" name="paged" value="<?php echo esc_attr() ?>" />

<?php $wp_list_table->display(); ?>
</form>

</div>

<?php
include(ABSPATH . 'wp-admin/admin-footer.php');

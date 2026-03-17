<?php
/**
 * Import WordPress Administration Screen
 *
 * @package WordPress
 * @subpackage Administration
 */

define('WP_LOAD_IMPORTERS', true);

/** Load WordPress Bootstrap */
require_once( __DIR__ . '/admin.php' );

if ( !current_user_can('import') )
	wp_die(__());

$title = __();

get_current_screen()->add_help_tab( [
	'id'      => 'overview',
	'title'   => __(),
	'content' => '<p>' . __() . '</p>' .
		'<p>' . __() . '</p>',
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

if ( current_user_can( 'install_plugins' ) )
	$popular_importers = wp_get_popular_importers();
else
	$popular_importers = [];

// Detect and redirect invalid importers like 'movabletype', which is registered as 'mt'
if ( ! empty( $_GET['invalid'] ) && isset( $popular_importers[ $_GET['invalid'] ] ) ) {
	$importer_id = $popular_importers[ $_GET['invalid'] ]['importer-id'];
	if ( $importer_id != $_GET['invalid'] ) { // Prevent redirect loops.
		wp_redirect( admin_url() );
		exit;
	}
	unset( $importer_id );
}

add_thickbox();
wp_enqueue_script( 'plugin-install' );

require_once( ABSPATH . 'wp-admin/admin-header.php' );
$parent_file = 'tools.php';
?>

<div class="wrap">
<h2><?php echo esc_html( $title ); ?></h2>
<?php if ( ! empty( $_GET['invalid'] ) ) : ?>
	<div class="error"><p><strong><?php _e('ERROR:')?></strong> <?php printf( __(), esc_html( $_GET['invalid'] ) ); ?></p></div>
<?php endif; ?>
<p><?php _e('If you have posts or comments in another system, WordPress can import those into this site. To get started, choose a system to import from below:'); ?></p>

<?php

$importers = get_importers();

// If a popular importer is not registered, create a dummy registration that links to the plugin installer.
foreach ( $popular_importers as $pop_importer => $pop_data ) {
	if ( isset( $importers[ $pop_importer ] ) )
		continue;
	if ( isset( $importers[ $pop_data['importer-id'] ] ) )
		continue;
	$importers[ $pop_data['importer-id'] ] = [ $pop_data['name'], $pop_data['description'], 'install' => $pop_data['plugin-slug'] ];
}

if ( empty( $importers ) ) {
	echo '<p>' . __() . '</p>'; // TODO: make more helpful
} else {
	uasort( $importers, _usort_by_first_member(...) );
?>
<table class="widefat importers">

<?php
	$alt = '';
	foreach ($importers as $importer_id => $data) {
		$action = '';
		if ( isset( $data['install'] ) ) {
			$plugin_slug = $data['install'];
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug ) ) {
				// Looks like Importer is installed, But not active
				$plugins = get_plugins( '/' . $plugin_slug );
				if ( !empty($plugins) ) {
					$keys = array_keys($plugins);
					$plugin_file = $plugin_slug . '/' . $keys[0];
					$action = '<a href="' . esc_url(wp_nonce_url(admin_url(), 'activate-plugin_' . $plugin_file)) .
											'"title="' . esc_attr__('Activate importer') . '"">' . $data[0] . '</a>';
				}
			}
			if ( empty($action) ) {
				if ( is_main_site() ) {
					$action = '<a href="' . esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug .
										'&from=import&TB_iframe=true&width=600&height=550' ) ) . '" class="thickbox" title="' .
										esc_attr__('Install importer') . '">' . $data[0] . '</a>';
				} else {
					$action = $data[0];
					$data[1] = sprintf( __(), get_admin_url( $current_site->blog_id, 'import.php' ) );
				}
			}
		} else {
			$action = "<a href='" . esc_url( "admin.php?import=$importer_id" ) . "' title='" . esc_attr() ."'>{$data[0]}</a>";
		}

		$alt = $alt ? '' : ' class="alternate"';
		echo "
			<tr$alt>
				<td class='import-system row-title'>$action</td>
				<td class='desc'>{$data[1]}</td>
			</tr>";
	}
?>

</table>
<?php
}

if ( current_user_can('install_plugins') )
	echo '<p>' . sprintf( __(), esc_url( network_admin_url( 'plugin-install.php?tab=search&type=tag&s=importer' ) ) ) . '</p>';
?>

</div>

<?php

include( ABSPATH . 'wp-admin/admin-footer.php' );

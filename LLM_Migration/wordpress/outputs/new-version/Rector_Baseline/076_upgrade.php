<?php
/**
 * Multisite upgrade administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once( __DIR__ . '/admin.php' );

if ( ! is_multisite() )
	wp_die( __() );

require_once( ABSPATH . WPINC . '/http.php' );

$title = __();
$parent_file = 'upgrade.php';

get_current_screen()->add_help_tab( [
	'id'      => 'overview',
	'title'   => __(),
	'content' =>
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>'
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );

if ( ! current_user_can( 'manage_network' ) )
	wp_die( __() );

echo '<div class="wrap">';
echo '<h2>' . __() . '</h2>';

$action = $_GET['action'] ?? 'show';

switch ( $action ) {
	case "upgrade":
		$n = ( isset($_GET['n']) ) ? intval($_GET['n']) : 0;

		if ( $n < 5 ) {
			global $wp_db_version;
			update_site_option( 'wpmu_upgrade_site', $wp_db_version );
		}

		$blogs = $wpdb->get_results( "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' AND spam = '0' AND deleted = '0' AND archived = '0' ORDER BY registered DESC LIMIT {$n}, 5", ARRAY_A );
		if ( empty( $blogs ) ) {
			echo '<p>' . __() . '</p>';
			break;
		}
		echo "<ul>";
		foreach ( (array) $blogs as $details ) {
			switch_to_blog( $details['blog_id'] );
			$siteurl = site_url();
			$upgrade_url = admin_url();
			restore_current_blog();
			echo "<li>$siteurl</li>";
			$response = wp_remote_get( $upgrade_url, [ 'timeout' => 120, 'httpversion' => '1.1' ] );
			if ( is_wp_error( $response ) )
				wp_die( sprintf( __(), $siteurl, $response->get_error_message() ) );
			/**
			 * Fires after the Multisite DB upgrade for each site is complete.
			 *
			 * @since MU
			 *
			 * @param array|WP_Error $response The upgrade response array or WP_Error on failure.
			 */
			do_action( 'after_mu_upgrade', $response );
			/**
			 * Fires after each site has been upgraded.
			 *
			 * @since MU
			 *
			 * @param int $blog_id The id of the blog.
			 */
			do_action( 'wpmu_upgrade_site', $details[ 'blog_id' ] );
		}
		echo "</ul>";
		?><p><?php _e( 'If your browser doesn&#8217;t start loading the next page automatically, click this link:' ); ?> <a class="button" href="upgrade.php?action=upgrade&amp;n=<?php echo ($n + 5) ?>"><?php _e("Next Sites"); ?></a></p>
		<script type="text/javascript">
		<!--
		function nextpage() {
			location.href = "upgrade.php?action=upgrade&n=<?php echo ($n + 5) ?>";
		}
		setTimeout( "nextpage()", 250 );
		//-->
		</script><?php
	break;
	case 'show':
	default:
		if ( get_site_option( 'wpmu_upgrade_site' ) != $GLOBALS['wp_db_version'] ) :
		?>
		<h3><?php _e( 'Database Upgrade Required' ); ?></h3>
		<p><?php _e( 'WordPress has been updated! Before we send you on your way, we need to individually upgrade the sites in your network.' ); ?></p>
		<?php endif; ?>

		<p><?php _e( 'The database upgrade process may take a little while, so please be patient.' ); ?></p>
		<p><a class="button" href="upgrade.php?action=upgrade"><?php _e( 'Upgrade Network' ); ?></a></p>
		<?php
		/**
		 * Fires before the footer on the network upgrade screen.
		 *
		 * @since MU
		 */
		do_action( 'wpmu_upgrade_page' );
	break;
}
?>
</div>

<?php include( ABSPATH . 'wp-admin/admin-footer.php' ); ?>

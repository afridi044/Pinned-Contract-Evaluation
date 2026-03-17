<?php
/**
 * Multisite upgrade administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! is_multisite() ) {
	wp_die( __( 'Multisite support is not enabled.' ) );
}

require_once ABSPATH . WPINC . '/http.php';

global $wpdb, $wp_db_version;

$title       = __( 'Upgrade Network' );
$parent_file = 'upgrade.php';

$current_screen = get_current_screen();

$current_screen->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => '<p>' . __( 'Only use this screen once you have updated to a new version of WordPress through Updates/Available Updates (via the Network Administration navigation menu or the Toolbar). Clicking the Upgrade Network button will step through each site in the network, five at a time, and make sure any database updates are applied.' ) . '</p>' .
			'<p>' . __( 'If a version update to core has not happened, clicking this button won&#8217;t affect anything.' ) . '</p>' .
			'<p>' . __( 'If this process fails for any reason, users logging in to their sites will force the same update.' ) . '</p>',
	)
);

$current_screen->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="http://codex.wordpress.org/Network_Admin_Updates_Screen" target="_blank">Documentation on Upgrade Network</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>' ) . '</p>'
);

require_once ABSPATH . 'wp-admin/admin-header.php';

if ( ! current_user_can( 'manage_network' ) ) {
	wp_die( __( 'You do not have permission to access this page.' ) );
}

echo '<div class="wrap">';
echo '<h2>' . esc_html__( 'Upgrade Network' ) . '</h2>';

$raw_action = $_GET['action'] ?? 'show';
$action     = is_string( $raw_action ) ? sanitize_key( wp_unslash( $raw_action ) ) : 'show';

switch ( $action ) {
	case 'upgrade':
		$requested_n = $_GET['n'] ?? 0;
		$n           = max( 0, (int) ( is_string( $requested_n ) || is_numeric( $requested_n ) ? wp_unslash( $requested_n ) : 0 ) );

		if ( $n < 5 ) {
			update_site_option( 'wpmu_upgrade_site', $wp_db_version );
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->blogs} WHERE site_id = %d AND spam = '0' AND deleted = '0' AND archived = '0' ORDER BY registered DESC LIMIT %d, %d",
			(int) $wpdb->siteid,
			$n,
			5
		);

		$blogs = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $blogs ) ) {
			echo '<p>' . esc_html__( 'All done!' ) . '</p>';
			break;
		}

		echo '<ul>';

		foreach ( $blogs as $details ) {
			switch_to_blog( (int) $details['blog_id'] );

			$site_url    = site_url();
			$upgrade_url = admin_url( 'upgrade.php?step=upgrade_db' );

			restore_current_blog();

			printf( '<li>%s</li>', esc_html( $site_url ) );

			$response = wp_remote_get(
				$upgrade_url,
				array(
					'timeout'     => 120,
					'httpversion' => '1.1',
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_die(
					sprintf(
						__( 'Warning! Problem updating %1$s. Your server may not be able to connect to sites running on it. Error message: <em>%2$s</em>' ),
						esc_html( $site_url ),
						esc_html( $response->get_error_message() )
					)
				);
			}

			do_action( 'after_mu_upgrade', $response );
			do_action( 'wpmu_upgrade_site', (int) $details['blog_id'] );
		}

		echo '</ul>';

		$next_n   = $n + 5;
		$next_url = add_query_arg(
			array(
				'action' => 'upgrade',
				'n'      => $next_n,
			),
			'upgrade.php'
		);

		printf(
			'<p>%s <a class="button" href="%s">%s</a></p>',
			esc_html__( 'If your browser doesn&#8217;t start loading the next page automatically, click this link:' ),
			esc_url( $next_url ),
			esc_html__( 'Next Sites' )
		);

		printf(
			'<script type="text/javascript">
(function() {
	const nextPage = function () {
		window.location.href = "%s";
	};
	window.setTimeout(nextPage, 250);
}());
</script>',
			esc_js( $next_url )
		);
		break;

	case 'show':
	default:
		if ( (string) get_site_option( 'wpmu_upgrade_site' ) !== (string) $wp_db_version ) {
			echo '<h3>' . esc_html__( 'Database Upgrade Required' ) . '</h3>';
			echo '<p>' . esc_html__( 'WordPress has been updated! Before we send you on your way, we need to individually upgrade the sites in your network.' ) . '</p>';
		}

		echo '<p>' . esc_html__( 'The database upgrade process may take a little while, so please be patient.' ) . '</p>';
		echo '<p><a class="button" href="upgrade.php?action=upgrade">' . esc_html__( 'Upgrade Network' ) . '</a></p>';

		do_action( 'wpmu_upgrade_page' );
		break;
}

echo '</div>';

require_once ABSPATH . 'wp-admin/admin-footer.php';
?>
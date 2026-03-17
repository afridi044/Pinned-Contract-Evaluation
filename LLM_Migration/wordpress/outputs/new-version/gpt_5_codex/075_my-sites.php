<?php
/**
 * My Sites dashboard.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

require_once __DIR__ . '/admin.php';

if ( ! is_multisite() ) {
	wp_die( __( 'Multisite support is not enabled.' ) );
}

if ( ! current_user_can( 'read' ) ) {
	wp_die( __( 'You do not have sufficient permissions to view this page.' ) );
}

global $current_user;

$action = $_POST['action'] ?? 'splash';

$blogs   = get_blogs_of_user( $current_user->ID );
$updated = false;

if ( $action === 'updateblogsettings' && isset( $_POST['primary_blog'] ) ) {
	check_admin_referer( 'update-my-sites' );

	$blog = get_blog_details( (int) $_POST['primary_blog'] );

	if ( $blog && isset( $blog->domain ) ) {
		update_user_option( $current_user->ID, 'primary_blog', (int) $_POST['primary_blog'], true );
		$updated = true;
	} else {
		wp_die( __( 'The primary site you chose does not exist.' ) );
	}
}

$title       = __( 'My Sites' );
$parent_file = 'index.php';

$current_screen = get_current_screen();

$current_screen->add_help_tab( [
	'id'      => 'overview',
	'title'   => __( 'Overview' ),
	'content' => '<p>' . __( 'This screen shows an individual user all of their sites in this network, and also allows that user to set a primary site. They can use the links under each site to visit either the frontend or the dashboard for that site.' ) . '</p>' .
		'<p>' . __( 'Up until WordPress version 3.0, what is now called a Multisite Network had to be installed separately as WordPress MU (multi-user).' ) . '</p>',
] );

$current_screen->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="http://codex.wordpress.org/Dashboard_My_Sites_Screen" target="_blank">Documentation on My Sites</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>' ) . '</p>'
);

require_once ABSPATH . 'wp-admin/admin-header.php';

if ( $updated ) : ?>
	<div id="message" class="updated"><p><strong><?php esc_html_e( 'Settings saved.' ); ?></strong></p></div>
<?php endif; ?>

<div class="wrap">
	<h2><?php echo esc_html( $title ); ?></h2>
	<?php if ( empty( $blogs ) ) : ?>
		<p><?php esc_html_e( 'You must be a member of at least one site to use this page.' ); ?></p>
	<?php else : ?>
		<form id="myblogs" action="" method="post">
			<?php
			choose_primary_blog();
			/**
			 * Fires before the sites table on the My Sites screen.
			 *
			 * @since 3.0.0
			 */
			do_action( 'myblogs_allblogs_options' );
			?>
			<br clear="all" />
			<table class="widefat fixed">
				<?php
				/**
				 * Enable the Global Settings section on the My Sites screen.
				 *
				 * By default, the Global Settings section is hidden. Passing a non-empty
				 * string to this filter will enable the section, and allow new settings
				 * to be added, either globally or for specific sites.
				 *
				 * @since MU
				 *
				 * @param string $settings_html The settings HTML markup. Default empty.
				 * @param object $context       Context of the setting (global or site-specific). Default 'global'.
				 */
				$settings_html = apply_filters( 'myblogs_options', '', 'global' );

				if ( $settings_html !== '' ) {
					echo '<tr><td><h3>' . esc_html__( 'Global Settings' ) . '</h3></td><td>';
					echo $settings_html;
					echo '</td></tr>';
				}

				$blogs_list = array_values( $blogs );
				$num        = count( $blogs_list );
				$cols       = 1;

				if ( $num >= 20 ) {
					$cols = 4;
				} elseif ( $num >= 10 ) {
					$cols = 2;
				}

				$rows = array_chunk( $blogs_list, $cols );

				foreach ( $rows as $row_index => $row ) {
					$row_class = $row_index % 2 === 0 ? 'alternate' : '';
					echo '<tr class="' . esc_attr( $row_class ) . '">';

					foreach ( $row as $column_index => $user_blog ) {
						$style = $column_index === array_key_last( $row ) ? '' : 'border-right: 1px solid #ccc;';

						echo '<td style="' . esc_attr( $style ) . '">';
						echo '<h3>' . esc_html( $user_blog->blogname ) . '</h3>';

						/**
						 * Filter the row links displayed for each site on the My Sites screen.
						 *
						 * @since MU
						 *
						 * @param string $string    The HTML site link markup.
						 * @param object $user_blog An object containing the site data.
						 */
						$links = sprintf(
							"<a href='%s'>%s</a> | <a href='%s'>%s</a>",
							esc_url( get_home_url( $user_blog->userblog_id ) ),
							__( 'Visit' ),
							esc_url( get_admin_url( $user_blog->userblog_id ) ),
							__( 'Dashboard' )
						);

						echo '<p>' . apply_filters( 'myblogs_blog_actions', $links, $user_blog ) . '</p>';

						/** This filter is documented in wp-admin/my-sites.php */
						echo apply_filters( 'myblogs_options', '', $user_blog );
						echo '</td>';
					}

					echo '</tr>';
				}
				?>
			</table>
			<input type="hidden" name="action" value="updateblogsettings" />
			<?php wp_nonce_field( 'update-my-sites' ); ?>
			<?php submit_button(); ?>
		</form>
	<?php endif; ?>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
?>
<?php
/**
 * Edit Site Info Administration Screen
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

require_once __DIR__ . '/admin.php';

if ( ! is_multisite() ) {
	wp_die( __( 'Multisite support is not enabled.' ) );
}

if ( ! current_user_can( 'manage_sites' ) ) {
	wp_die( __( 'You do not have sufficient permissions to edit this site.' ) );
}

$screen = get_current_screen();

$screen->add_help_tab(
	[
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . __( 'The menu is for editing information specific to individual sites, particularly if the admin area of a site is unavailable.' ) . '</p>' .
			'<p>' . __( '<strong>Info</strong> - The domain and path are rarely edited as this can cause the site to not work properly. The Registered date and Last Updated date are displayed. Network admins can mark a site as archived, spam, deleted and mature, to remove from public listings or disable.' ) . '</p>' .
			'<p>' . __( '<strong>Users</strong> - This displays the users associated with this site. You can also change their role, reset their password, or remove them from the site. Removing the user from the site does not remove the user from the network.' ) . '</p>' .
			'<p>' . sprintf( __( '<strong>Themes</strong> - This area shows themes that are not already enabled across the network. Enabling a theme in this menu makes it accessible to this site. It does not activate the theme, but allows it to show in the site&#8217;s Appearance menu. To enable a theme for the entire network, see the <a href="%s">Network Themes</a> screen.' ), esc_url( network_admin_url( 'themes.php' ) ) ) . '</p>' .
			'<p>' . __( '<strong>Settings</strong> - This page shows a list of all settings associated with this site. Some are created by WordPress and others are created by plugins you activate. Note that some fields are grayed out and say Serialized Data. You cannot modify these values due to the way the setting is stored in the database.' ) . '</p>',
	]
);

$screen->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="http://codex.wordpress.org/Network_Admin_Sites_Screen" target="_blank">Documentation on Site Management</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/forum/multisite/" target="_blank">Support Forums</a>' ) . '</p>'
);

$raw_id = $_REQUEST['id'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$id     = is_scalar( $raw_id ) ? absint( wp_unslash( (string) $raw_id ) ) : 0;

if ( 0 === $id ) {
	wp_die( __( 'Invalid site ID.' ) );
}

$details = get_blog_details( $id );

if ( ! $details ) {
	wp_die( __( 'The requested site does not exist.' ) );
}

if ( ! can_edit_network( (int) $details->site_id ) ) {
	wp_die( __( 'You do not have permission to access this page.' ) );
}

$parsed       = wp_parse_url( (string) $details->siteurl );
$scheme       = $parsed['scheme'] ?? 'http';
$is_main_site = is_main_site( $id );
$action       = $_REQUEST['action'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( 'update-site' === $action ) {
	check_admin_referer( 'edit-site' );

	switch_to_blog( $id );

	$update_home_url = isset( $_POST['update_home_url'] ) && 'update' === sanitize_text_field( wp_unslash( (string) $_POST['update_home_url'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$blog_input      = isset( $_POST['blog'] ) && is_array( $_POST['blog'] ) ? wp_unslash( $_POST['blog'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( $update_home_url ) {
		$domain       = (string) ( $blog_input['domain'] ?? '' );
		$path         = (string) ( $blog_input['path'] ?? '' );
		$blog_address = esc_url_raw( $domain . $path );

		if ( get_option( 'siteurl' ) !== $blog_address ) {
			update_option( 'siteurl', $blog_address );
		}

		if ( get_option( 'home' ) !== $blog_address ) {
			update_option( 'home', $blog_address );
		}
	}

	// Rewrite rules can't be flushed during switch to blog.
	delete_option( 'rewrite_rules' );

	// Update blogs table.
	$blog_data          = $blog_input;
	$existing_details   = get_blog_details( $id, false );
	$checkbox_fields    = [ 'public', 'archived', 'spam', 'mature', 'deleted' ];
	$blog_data_defaults = is_object( $existing_details ) ? $existing_details : new stdClass();

	foreach ( $checkbox_fields as $field ) {
		if ( ! isset( $blog_data_defaults->$field ) || ! in_array( $blog_data_defaults->$field, [ 0, 1 ], true ) ) {
			$blog_data[ $field ] = $blog_data_defaults->$field ?? 0;
		} else {
			$blog_data[ $field ] = isset( $blog_input[ $field ] ) ? 1 : 0;
		}
	}

	update_blog_details( $id, $blog_data );

	restore_current_blog();

	wp_redirect(
		add_query_arg(
			[
				'update' => 'updated',
				'id'     => $id,
			],
			'site-info.php'
		)
	);
	exit;
}

$messages     = [];
$get_update   = $_GET['update'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$get_update   = is_scalar( $get_update ) ? sanitize_text_field( wp_unslash( (string) $get_update ) ) : '';
$site_address = get_blogaddress_by_id( $id );

if ( 'updated' === $get_update ) {
	$messages[] = __( 'Site info updated.' );
}

$site_url_no_http      = preg_replace( '#^https?://#', '', $site_address );
$title_site_url_linked = sprintf(
	__( 'Edit Site: <a href="%1$s">%2$s</a>' ),
	esc_url( $site_address ),
	esc_html( $site_url_no_http )
);
$title                 = sprintf( __( 'Edit Site: %s' ), $site_url_no_http );

$parent_file  = 'sites.php';
$submenu_file = 'sites.php';

require ABSPATH . 'wp-admin/admin-header.php';

global $pagenow;
?>
<div class="wrap">
	<h2 id="edit-site"><?php echo wp_kses_post( $title_site_url_linked ); ?></h2>
	<h3 class="nav-tab-wrapper">
		<?php
		$tabs = [
			'site-info'     => [
				'label' => __( 'Info' ),
				'url'   => 'site-info.php',
			],
			'site-users'    => [
				'label' => __( 'Users' ),
				'url'   => 'site-users.php',
			],
			'site-themes'   => [
				'label' => __( 'Themes' ),
				'url'   => 'site-themes.php',
			],
			'site-settings' => [
				'label' => __( 'Settings' ),
				'url'   => 'site-settings.php',
			],
		];

		foreach ( $tabs as $tab ) {
			$tab_url = add_query_arg( 'id', (string) $id, $tab['url'] );

			printf(
				'<a href="%1$s" class="nav-tab%2$s">%3$s</a>',
				esc_url( $tab_url ),
				( $tab['url'] === $pagenow ) ? ' nav-tab-active' : '',
				esc_html( $tab['label'] )
			);
		}
		?>
	</h3>
	<?php if ( ! empty( $messages ) ) : ?>
		<?php foreach ( $messages as $message ) : ?>
			<div id="message" class="updated"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endforeach; ?>
	<?php endif; ?>
	<form method="post" action="site-info.php?action=update-site">
		<?php wp_nonce_field( 'edit-site' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
		<table class="form-table">
			<tr class="form-field form-required">
				<th scope="row"><?php esc_html_e( 'Domain' ); ?></th>
				<?php if ( $is_main_site ) : ?>
					<td><code><?php echo esc_html( "{$scheme}://{$details->domain}" ); ?></code></td>
				<?php else : ?>
					<td>
						<?php echo esc_html( "{$scheme}://" ); ?>
						<input name="blog[domain]" type="text" id="domain" value="<?php echo esc_attr( $details->domain ); ?>" size="33" />
					</td>
				<?php endif; ?>
			</tr>
			<tr class="form-field form-required">
				<th scope="row"><?php esc_html_e( 'Path' ); ?></th>
				<?php if ( $is_main_site ) : ?>
					<td><code><?php echo esc_html( $details->path ); ?></code></td>
				<?php else : ?>
					<?php
					switch_to_blog( $id );
					$blog_address_untrailed = untrailingslashit( get_blogaddress_by_id( $id ) );
					$should_update_home_url = ( get_option( 'siteurl' ) === $blog_address_untrailed ) || ( get_option( 'home' ) === $blog_address_untrailed );
					?>
					<td>
						<input name="blog[path]" type="text" id="path" value="<?php echo esc_attr( $details->path ); ?>" size="40" style="margin-bottom:5px;" />
						<br />
						<label>
							<input type="checkbox" style="width:20px;" name="update_home_url" value="update" <?php checked( $should_update_home_url, true ); ?> />
							<?php esc_html_e( 'Update <code>siteurl</code> and <code>home</code> as well.' ); ?>
						</label>
					</td>
					<?php
					restore_current_blog();
					?>
				<?php endif; ?>
			</tr>
			<tr class="form-field">
				<th scope="row"><?php _ex( 'Registered', 'site' ); ?></th>
				<td><input name="blog[registered]" type="text" id="blog_registered" value="<?php echo esc_attr( $details->registered ); ?>" size="40" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><?php esc_html_e( 'Last Updated' ); ?></th>
				<td><input name="blog[last_updated]" type="text" id="blog_last_updated" value="<?php echo esc_attr( $details->last_updated ); ?>" size="40" /></td>
			</tr>
			<?php
			$attribute_fields = [
				'public' => __( 'Public' ),
			];

			if ( ! $is_main_site ) {
				$attribute_fields['archived'] = __( 'Archived' );
				$attribute_fields['spam']     = _x( 'Spam', 'site' );
				$attribute_fields['deleted']  = __( 'Deleted' );
			}

			$attribute_fields['mature'] = __( 'Mature' );
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Attributes' ); ?></th>
				<td>
					<?php foreach ( $attribute_fields as $field_key => $field_label ) : ?>
						<label>
							<input
								type="checkbox"
								name="blog[<?php echo esc_attr( $field_key ); ?>]"
								value="1"
								<?php checked( (bool) $details->$field_key ); ?>
								<?php disabled( ! in_array( $details->$field_key, [ 0, 1 ] ), true ); ?>
							/>
							<?php echo esc_html( $field_label ); ?>
						</label><br />
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
<?php
require ABSPATH . 'wp-admin/admin-footer.php';
?>
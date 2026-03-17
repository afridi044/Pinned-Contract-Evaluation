<?php
/**
 * Add Site Administration Screen
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once( __DIR__ . '/admin.php' );

if ( ! is_multisite() )
	wp_die( __() );

if ( ! current_user_can( 'manage_sites' ) )
	wp_die( __() );

	get_current_screen()->add_help_tab( [
		'id'      => 'overview',
		'title'   => __(),
		'content' =>
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>'
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

if ( isset($_REQUEST['action']) && 'add-site' == $_REQUEST['action'] ) {
	check_admin_referer( 'add-blog', '_wpnonce_add-blog' );

	if ( ! is_array( $_POST['blog'] ) )
		wp_die( __() );

	$blog = $_POST['blog'];
	$domain = '';
	if ( preg_match( '|^([a-zA-Z0-9-])+$|', (string) $blog['domain'] ) )
		$domain = strtolower( (string) $blog['domain'] );

	// If not a subdomain install, make sure the domain isn't a reserved word
	if ( ! is_subdomain_install() ) {
		/** This filter is documented in wp-includes/ms-functions.php */
		$subdirectory_reserved_names = apply_filters();
		if ( in_array( $domain, $subdirectory_reserved_names ) )
			wp_die( sprintf( __(), implode( '</code>, <code>', $subdirectory_reserved_names ) ) );
	}

	$email = sanitize_email( $blog['email'] );
	$title = $blog['title'];

	if ( empty( $domain ) )
		wp_die( __() );
	if ( empty( $email ) )
		wp_die( __() );
	if ( !is_email( $email ) )
		wp_die( __() );

	if ( is_subdomain_install() ) {
		$newdomain = $domain . '.' . preg_replace( '|^www\.|', '', (string) $current_site->domain );
		$path      = $current_site->path;
	} else {
		$newdomain = $current_site->domain;
		$path      = $current_site->path . $domain . '/';
	}

	$password = 'N/A';
	$user_id = email_exists($email);
	if ( !$user_id ) { // Create a new user with a random password
		$password = wp_generate_password( 12, false );
		$user_id = wpmu_create_user( $domain, $password, $email );
		if ( false == $user_id )
			wp_die( __() );
		else
			wp_new_user_notification( $user_id, $password );
	}

	$wpdb->hide_errors();
	$id = wpmu_create_blog( $newdomain, $path, $title, $user_id , [ 'public' => 1 ], $current_site->id );
	$wpdb->show_errors();
	if ( !is_wp_error( $id ) ) {
		if ( !is_super_admin( $user_id ) && !get_user_option( 'primary_blog', $user_id ) )
			update_user_option( $user_id, 'primary_blog', $id, true );
		$content_mail = sprintf( __(), $current_user->user_login , get_site_url( $id ), wp_unslash( $title ) );
		wp_mail( get_site_option('admin_email'), sprintf( __(), $current_site->site_name ), $content_mail, 'From: "Site Admin" <' . get_site_option( 'admin_email' ) . '>' );
		wpmu_welcome_notification( $id, $user_id, $password, $title, [ 'public' => 1 ] );
		wp_redirect( add_query_arg( [ 'update' => 'added', 'id' => $id ], 'site-new.php' ) );
		exit;
	} else {
		wp_die( $id->get_error_message() );
	}
}

if ( isset($_GET['update']) ) {
	$messages = [];
	if ( 'added' == $_GET['update'] )
		$messages[] = sprintf( __(), esc_url( get_admin_url( absint( $_GET['id'] ) ) ), network_admin_url( 'site-info.php?id=' . absint( $_GET['id'] ) ) );
}

$title = __();
$parent_file = 'sites.php';

wp_enqueue_script( 'user-suggest' );

require( ABSPATH . 'wp-admin/admin-header.php' );

?>

<div class="wrap">
<h2 id="add-new-site"><?php _e('Add New Site') ?></h2>
<?php
if ( ! empty( $messages ) ) {
	foreach ( $messages as $msg )
		echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
} ?>
<form method="post" action="<?php echo network_admin_url( 'site-new.php?action=add-site' ); ?>" novalidate="novalidate">
<?php wp_nonce_field( 'add-blog', '_wpnonce_add-blog' ) ?>
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row"><?php _e( 'Site Address' ) ?></th>
			<td>
			<?php if ( is_subdomain_install() ) { ?>
				<input name="blog[domain]" type="text" class="regular-text" title="<?php esc_attr_e( 'Domain' ) ?>"/><span class="no-break">.<?php echo preg_replace( '|^www\.|', '', (string) $current_site->domain ); ?></span>
			<?php } else {
				echo $current_site->domain . $current_site->path ?><input name="blog[domain]" class="regular-text" type="text" title="<?php esc_attr_e( 'Domain' ) ?>"/>
			<?php }
			echo '<p>' . __() . '</p>';
			?>
			</td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row"><?php _e( 'Site Title' ) ?></th>
			<td><input name="blog[title]" type="text" class="regular-text" title="<?php esc_attr_e( 'Title' ) ?>"/></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row"><?php _e( 'Admin Email' ) ?></th>
			<td><input name="blog[email]" type="email" class="regular-text wp-suggest-user" data-autocomplete-type="search" data-autocomplete-field="user_email" title="<?php esc_attr_e( 'Email' ) ?>"/></td>
		</tr>
		<tr class="form-field">
			<td colspan="2"><?php _e( 'A new user will be created if the above email address is not in the database.' ) ?><br /><?php _e( 'The username and password will be mailed to this email address.' ) ?></td>
		</tr>
	</table>
	<?php submit_button( __(), 'primary', 'add-site' ); ?>
	</form>
</div>
<?php
require( ABSPATH . 'wp-admin/admin-footer.php' );

<?php
/**
 * Edit Site Settings Administration Screen
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
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>' .
		'<p>' . sprintf( __(), network_admin_url( 'themes.php' ) ) . '</p>' .
		'<p>' . __() . '</p>'
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

$id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

if ( ! $id )
	wp_die( __() );

$details = get_blog_details( $id );
if ( !can_edit_network( $details->site_id ) )
	wp_die( __() );

$is_main_site = is_main_site( $id );

if ( isset($_REQUEST['action']) && 'update-site' == $_REQUEST['action'] && is_array( $_POST['option'] ) ) {
	check_admin_referer( 'edit-site' );

	switch_to_blog( $id );

	$skip_options = [ 'allowedthemes' ]; // Don't update these options since they are handled elsewhere in the form.
	foreach ( (array) $_POST['option'] as $key => $val ) {
		$key = wp_unslash( $key );
		$val = wp_unslash( $val );
		if ( $key === 0 || is_array( $val ) || in_array($key, $skip_options) )
			continue; // Avoids "0 is a protected WP option and may not be modified" error when edit blog options
		update_option( $key, $val );
	}

/**
 * Fires after the site options are updated.
 *
 * @since 3.0.0
 */
	do_action( 'wpmu_update_blog_options' );
	restore_current_blog();
	wp_redirect( add_query_arg( [ 'update' => 'updated', 'id' => $id ], 'site-settings.php') );
	exit;
}

if ( isset($_GET['update']) ) {
	$messages = [];
	if ( 'updated' == $_GET['update'] )
		$messages[] = __();
}

$site_url_no_http = preg_replace( '#^http(s)?://#', '', get_blogaddress_by_id( $id ) );
$title_site_url_linked = sprintf( __(), get_blogaddress_by_id( $id ), $site_url_no_http );
$title = sprintf( __(), $site_url_no_http );

$parent_file = 'sites.php';
$submenu_file = 'sites.php';

require( ABSPATH . 'wp-admin/admin-header.php' );

?>

<div class="wrap">
<h2 id="edit-site"><?php echo $title_site_url_linked ?></h2>
<h3 class="nav-tab-wrapper">
<?php
$tabs = [
	'site-info'     => [ 'label' => __(),     'url' => 'site-info.php'     ],
	'site-users'    => [ 'label' => __(),    'url' => 'site-users.php'    ],
	'site-themes'   => [ 'label' => __(),   'url' => 'site-themes.php'   ],
	'site-settings' => [ 'label' => __(), 'url' => 'site-settings.php' ],
];
foreach ( $tabs as $tab_id => $tab ) {
	$class = ( $tab['url'] == $pagenow ) ? ' nav-tab-active' : '';
	echo '<a href="' . $tab['url'] . '?id=' . $id .'" class="nav-tab' . $class . '">' . esc_html( $tab['label'] ) . '</a>';
}
?>
</h3>
<?php
if ( ! empty( $messages ) ) {
	foreach ( $messages as $msg )
		echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
} ?>
<form method="post" action="site-settings.php?action=update-site">
	<?php wp_nonce_field( 'edit-site' ); ?>
	<input type="hidden" name="id" value="<?php echo esc_attr() ?>" />
	<table class="form-table">
		<?php
		$blog_prefix = $wpdb->get_blog_prefix( $id );
		$sql = "SELECT * FROM {$blog_prefix}options
			WHERE option_name NOT LIKE %s
			AND option_name NOT LIKE %s";
		$query = $wpdb->prepare( $sql,
			$wpdb->esc_like( '_' ) . '%',
			'%' . $wpdb->esc_like( 'user_roles' )
		);
		$options = $wpdb->get_results( $query );
		foreach ( $options as $option ) {
			if ( $option->option_name == 'default_role' )
				$editblog_default_role = $option->option_value;
			$disabled = false;
			$class = 'all-options';
			if ( is_serialized( $option->option_value ) ) {
				if ( is_serialized_string( $option->option_value ) ) {
					$option->option_value = esc_html( maybe_unserialize( $option->option_value ) );
				} else {
					$option->option_value = 'SERIALIZED DATA';
					$disabled = true;
					$class = 'all-options disabled';
				}
			}
			if ( str_contains( (string) $option->option_value, "\n" ) ) {
			?>
				<tr class="form-field">
					<th scope="row"><?php echo ucwords( str_replace( "_", " ", $option->option_name ) ) ?></th>
					<td><textarea class="<?php echo $class; ?>" rows="5" cols="40" name="option[<?php echo esc_attr() ?>]" id="<?php echo esc_attr() ?>"<?php disabled( $disabled ) ?>><?php echo esc_textarea( $option->option_value ) ?></textarea></td>
				</tr>
			<?php
			} else {
			?>
				<tr class="form-field">
					<th scope="row"><?php echo esc_html( ucwords( str_replace( "_", " ", $option->option_name ) ) ); ?></th>
					<?php if ( $is_main_site && in_array( $option->option_name, [ 'siteurl', 'home' ] ) ) { ?>
					<td><code><?php echo esc_html( $option->option_value ) ?></code></td>
					<?php } else { ?>
					<td><input class="<?php echo $class; ?>" name="option[<?php echo esc_attr() ?>]" type="text" id="<?php echo esc_attr() ?>" value="<?php echo esc_attr() ?>" size="40" <?php disabled( $disabled ) ?> /></td>
					<?php } ?>
				</tr>
			<?php
			}
		} // End foreach
		/**
		 * Fires at the end of the Edit Site form, before the submit button.
		 *
		 * @since 3.0.0
		 *
		 * @param int $id Site ID.
		 */
		do_action( 'wpmueditblogaction', $id );
		?>
	</table>
	<?php submit_button(); ?>
</form>

</div>
<?php
require( ABSPATH . 'wp-admin/admin-footer.php' );

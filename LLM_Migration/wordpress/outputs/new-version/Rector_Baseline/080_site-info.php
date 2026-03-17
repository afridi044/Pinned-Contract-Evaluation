<?php
/**
 * Edit Site Info Administration Screen
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

$parsed = parse_url( (string) $details->siteurl );
$is_main_site = is_main_site( $id );

if ( isset($_REQUEST['action']) && 'update-site' == $_REQUEST['action'] ) {
	check_admin_referer( 'edit-site' );

	switch_to_blog( $id );

	if ( isset( $_POST['update_home_url'] ) && $_POST['update_home_url'] == 'update' ) {
		$blog_address = esc_url_raw( $_POST['blog']['domain'] . $_POST['blog']['path'] );
		if ( get_option() != $blog_address )
			update_option( 'siteurl', $blog_address );

		if ( get_option() != $blog_address )
			update_option( 'home', $blog_address );
	}

	// Rewrite rules can't be flushed during switch to blog.
	delete_option( 'rewrite_rules' );

	// Update blogs table.
	$blog_data = wp_unslash( $_POST['blog'] );
	$existing_details = get_blog_details( $id, false );
	$blog_data_checkboxes = [ 'public', 'archived', 'spam', 'mature', 'deleted' ];
	foreach ( $blog_data_checkboxes as $c ) {
		if ( ! in_array( $existing_details->$c, [ 0, 1 ] ) )
			$blog_data[ $c ] = $existing_details->$c;
		else
			$blog_data[ $c ] = isset( $_POST['blog'][ $c ] ) ? 1 : 0;
	}
	update_blog_details( $id, $blog_data );

	restore_current_blog();
	wp_redirect( add_query_arg( [ 'update' => 'updated', 'id' => $id ], 'site-info.php') );
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
<form method="post" action="site-info.php?action=update-site">
	<?php wp_nonce_field( 'edit-site' ); ?>
	<input type="hidden" name="id" value="<?php echo esc_attr() ?>" />
	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row"><?php _e( 'Domain' ) ?></th>
			<?php if ( $is_main_site ) { ?>
				<td><code><?php echo $parsed['scheme'] . '://' . esc_attr() ?></code></td>
			<?php } else { ?>
				<td><?php echo $parsed['scheme'] . '://'; ?><input name="blog[domain]" type="text" id="domain" value="<?php echo esc_attr() ?>" size="33" /></td>
			<?php } ?>
		</tr>
		<tr class="form-field form-required">
			<th scope="row"><?php _e( 'Path' ) ?></th>
			<?php if ( $is_main_site ) { ?>
			<td><code><?php echo esc_attr() ?></code></td>
			<?php
			} else {
				switch_to_blog( $id );
			?>
			<td><input name="blog[path]" type="text" id="path" value="<?php echo esc_attr() ?>" size="40" style="margin-bottom:5px;" />
			<br /><input type="checkbox" style="width:20px;" name="update_home_url" value="update" <?php if ( get_option() == untrailingslashit( get_blogaddress_by_id ($id ) ) || get_option() == untrailingslashit( get_blogaddress_by_id( $id ) ) ) echo 'checked="checked"'; ?> /> <?php _e( 'Update <code>siteurl</code> and <code>home</code> as well.' ); ?></td>
			<?php
				restore_current_blog();
			} ?>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php _ex( 'Registered', 'site' ) ?></th>
			<td><input name="blog[registered]" type="text" id="blog_registered" value="<?php echo esc_attr() ?>" size="40" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php _e( 'Last Updated' ); ?></th>
			<td><input name="blog[last_updated]" type="text" id="blog_last_updated" value="<?php echo esc_attr() ?>" size="40" /></td>
		</tr>
		<?php
		$attribute_fields = [ 'public' => __() ];
		if ( ! $is_main_site ) {
			$attribute_fields['archived'] = __();
			$attribute_fields['spam']     = _x();
			$attribute_fields['deleted']  = __();
		}
		$attribute_fields['mature'] = __();
		?>
		<tr>
			<th scope="row"><?php _e( 'Attributes' ); ?></th>
			<td>
			<?php foreach ( $attribute_fields as $field_key => $field_label ) : ?>
				<label><input type="checkbox" name="blog[<?php echo $field_key; ?>]" value="1" <?php checked( (bool) $details->$field_key, true ); disabled( ! in_array( $details->$field_key, [ 0, 1 ] ) ); ?> />
				<?php echo $field_label; ?></label><br/>
			<?php endforeach; ?>
			</td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>

</div>
<?php
require( ABSPATH . 'wp-admin/admin-footer.php' );

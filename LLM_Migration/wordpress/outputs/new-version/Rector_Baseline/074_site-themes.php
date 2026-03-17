<?php
/**
 * Edit Site Themes Administration Screen
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

$wp_list_table = _get_list_table('WP_MS_Themes_List_Table');

$action = $wp_list_table->current_action();

$s = $_REQUEST['s'] ?? '';

// Clean up request URI from temporary args for screen options/paging uri's to work as expected.
$temp_args = [ 'enabled', 'disabled', 'error' ];
$_SERVER['REQUEST_URI'] = remove_query_arg( $temp_args, $_SERVER['REQUEST_URI'] );
$referer = remove_query_arg( $temp_args, wp_get_referer() );

if ( ! empty( $_REQUEST['paged'] ) ) {
	$referer = add_query_arg( 'paged', (int) $_REQUEST['paged'], $referer );
}

$id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

if ( ! $id )
	wp_die( __() );

$wp_list_table->prepare_items();

$details = get_blog_details( $id );
if ( !can_edit_network( $details->site_id ) )
	wp_die( __() );

$is_main_site = is_main_site( $id );

if ( $action ) {
	switch_to_blog( $id );
	$allowed_themes = get_option();

	switch ( $action ) {
		case 'enable':
			check_admin_referer( 'enable-theme_' . $_GET['theme'] );
			$theme = $_GET['theme'];
			$action = 'enabled';
			$n = 1;
			if ( !$allowed_themes )
				$allowed_themes = [ $theme => true ];
			else
				$allowed_themes[$theme] = true;
			break;
		case 'disable':
			check_admin_referer( 'disable-theme_' . $_GET['theme'] );
			$theme = $_GET['theme'];
			$action = 'disabled';
			$n = 1;
			if ( !$allowed_themes )
				$allowed_themes = [];
			else
				unset( $allowed_themes[$theme] );
			break;
		case 'enable-selected':
			check_admin_referer( 'bulk-themes' );
			if ( isset( $_POST['checked'] ) ) {
				$themes = (array) $_POST['checked'];
				$action = 'enabled';
				$n = count( $themes );
				foreach( (array) $themes as $theme )
					$allowed_themes[ $theme ] = true;
			} else {
				$action = 'error';
				$n = 'none';
			}
			break;
		case 'disable-selected':
			check_admin_referer( 'bulk-themes' );
			if ( isset( $_POST['checked'] ) ) {
				$themes = (array) $_POST['checked'];
				$action = 'disabled';
				$n = count( $themes );
				foreach( (array) $themes as $theme )
					unset( $allowed_themes[ $theme ] );
			} else {
				$action = 'error';
				$n = 'none';
			}
			break;
	}

	update_option( 'allowedthemes', $allowed_themes );
	restore_current_blog();

	wp_safe_redirect( add_query_arg( [ 'id' => $id, $action => $n ], $referer ) );
	exit;
}

if ( isset( $_GET['action'] ) && 'update-site' == $_GET['action'] ) {
	wp_safe_redirect( $referer );
	exit();
}

add_thickbox();
add_screen_option( 'per_page', [ 'label' => _x() ] );

$site_url_no_http = preg_replace( '#^http(s)?://#', '', get_blogaddress_by_id( $id ) );
$title_site_url_linked = sprintf( __(), get_blogaddress_by_id( $id ), $site_url_no_http );
$title = sprintf( __(), $site_url_no_http );

$parent_file = 'sites.php';
$submenu_file = 'sites.php';

require( ABSPATH . 'wp-admin/admin-header.php' ); ?>

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
</h3><?php

if ( isset( $_GET['enabled'] ) ) {
	$_GET['enabled'] = absint( $_GET['enabled'] );
	echo '<div id="message" class="updated"><p>' . sprintf( _n( 'Theme enabled.', '%s themes enabled.', $_GET['enabled'] ), number_format_i18n( $_GET['enabled'] ) ) . '</p></div>';
} elseif ( isset( $_GET['disabled'] ) ) {
	$_GET['disabled'] = absint( $_GET['disabled'] );
	echo '<div id="message" class="updated"><p>' . sprintf( _n( 'Theme disabled.', '%s themes disabled.', $_GET['disabled'] ), number_format_i18n( $_GET['disabled'] ) ) . '</p></div>';
} elseif ( isset( $_GET['error'] ) && 'none' == $_GET['error'] ) {
	echo '<div id="message" class="error"><p>' . __() . '</p></div>';
} ?>

<p><?php _e( 'Network enabled themes are not shown on this screen.' ) ?></p>

<form method="get" action="">
<?php $wp_list_table->search_box( __(), 'theme' ); ?>
<input type="hidden" name="id" value="<?php echo esc_attr() ?>" />
</form>

<?php $wp_list_table->views(); ?>

<form method="post" action="site-themes.php?action=update-site">
	<input type="hidden" name="id" value="<?php echo esc_attr() ?>" />

<?php $wp_list_table->display(); ?>

</form>

</div>
<?php include(ABSPATH . 'wp-admin/admin-footer.php'); ?>

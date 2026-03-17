<?php
/**
 * Media Library administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( __DIR__ . '/admin.php' );

if ( !current_user_can('upload_files') )
	wp_die( __() );

$mode = get_user_option( 'media_library_mode', get_current_user_id() ) ?: 'grid';
$modes = [ 'grid', 'list' ];

if ( isset( $_GET['mode'] ) && in_array( $_GET['mode'], $modes ) ) {
	$mode = $_GET['mode'];
	update_user_option( get_current_user_id(), 'media_library_mode', $mode );
}

if ( 'grid' === $mode ) {
	wp_enqueue_media();
	wp_enqueue_script( 'media-grid' );
	wp_enqueue_script( 'media' );
	wp_localize_script( 'media-grid', '_wpMediaGridSettings', [
		'adminUrl' => parse_url( self_admin_url(), PHP_URL_PATH ),
	] );

	get_current_screen()->add_help_tab( [
		'id'		=> 'overview',
		'title'		=> __(),
		'content'	=>
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>'
	] );

	get_current_screen()->add_help_tab( [
		'id'		=> 'attachment-details',
		'title'		=> __(),
		'content'	=>
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>'
	] );

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . __() . '</strong></p>' .
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>'
	);

	$title = __();
	$parent_file = 'upload.php';

	require_once( ABSPATH . 'wp-admin/admin-header.php' );
	?>
	<div class="wrap">
		<h2>
		<?php
		echo esc_html( $title );
		if ( current_user_can( 'upload_files' ) ) { ?>
			<a href="media-new.php" class="add-new-h2"><?php echo esc_html_x( 'Add New', 'file' ); ?></a><?php
		}
		?>
		</h2>
		<div class="error hide-if-js">
			<p><?php _e( 'The grid view for the Media Library requires JavaScript. <a href="upload.php?mode=list">Switch to the list view</a>.' ); ?></p>
		</div>
	</div>
	<?php
	include( ABSPATH . 'wp-admin/admin-footer.php' );
	exit;
}

$wp_list_table = _get_list_table('WP_Media_List_Table');
$pagenum = $wp_list_table->get_pagenum();

// Handle bulk actions
$doaction = $wp_list_table->current_action();

if ( $doaction ) {
	check_admin_referer('bulk-media');

	if ( 'delete_all' == $doaction ) {
		$post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type='attachment' AND post_status = 'trash'" );
		$doaction = 'delete';
	} elseif ( isset( $_REQUEST['media'] ) ) {
		$post_ids = $_REQUEST['media'];
	} elseif ( isset( $_REQUEST['ids'] ) ) {
		$post_ids = explode( ',', (string) $_REQUEST['ids'] );
	}

	$location = 'upload.php';
	if ( $referer = wp_get_referer() ) {
		if ( str_contains( $referer, 'upload.php' ) )
			$location = remove_query_arg( [ 'trashed', 'untrashed', 'deleted', 'message', 'ids', 'posted' ], $referer );
	}

	switch ( $doaction ) {
		case 'attach':
			$parent_id = (int) $_REQUEST['found_post_id'];
			if ( !$parent_id )
				return;

			$parent = get_post( $parent_id );
			if ( !current_user_can( 'edit_post', $parent_id ) )
				wp_die( __() );

			$attach = [];
			foreach ( (array) $_REQUEST['media'] as $att_id ) {
				$att_id = (int) $att_id;

				if ( !current_user_can( 'edit_post', $att_id ) )
					continue;

				$attach[] = $att_id;
			}

			if ( ! empty( $attach ) ) {
				$attach_string = implode( ',', $attach );
				$attached = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_parent = %d WHERE post_type = 'attachment' AND ID IN ( $attach_string )", $parent_id ) );
				foreach ( $attach as $att_id ) {
					clean_attachment_cache( $att_id );
				}
			}

			if ( isset( $attached ) ) {
				$location = 'upload.php';
				if ( $referer = wp_get_referer() ) {
					if ( str_contains( $referer, 'upload.php' ) )
						$location = $referer;
				}

				$location = add_query_arg( [ 'attached' => $attached ] , $location );
				wp_redirect( $location );
				exit;
			}
			break;
		case 'trash':
			if ( !isset( $post_ids ) )
				break;
			foreach ( (array) $post_ids as $post_id ) {
				if ( !current_user_can( 'delete_post', $post_id ) )
					wp_die( __() );

				if ( !wp_trash_post( $post_id ) )
					wp_die( __() );
			}
			$location = add_query_arg( [ 'trashed' => count( $post_ids ), 'ids' => join( ',', $post_ids ) ], $location );
			break;
		case 'untrash':
			if ( !isset( $post_ids ) )
				break;
			foreach ( (array) $post_ids as $post_id ) {
				if ( !current_user_can( 'delete_post', $post_id ) )
					wp_die( __() );

				if ( !wp_untrash_post( $post_id ) )
					wp_die( __() );
			}
			$location = add_query_arg( 'untrashed', count( $post_ids ), $location );
			break;
		case 'delete':
			if ( !isset( $post_ids ) )
				break;
			foreach ( (array) $post_ids as $post_id_del ) {
				if ( !current_user_can( 'delete_post', $post_id_del ) )
					wp_die( __() );

				if ( !wp_delete_attachment( $post_id_del ) )
					wp_die( __() );
			}
			$location = add_query_arg( 'deleted', count( $post_ids ), $location );
			break;
	}

	wp_redirect( $location );
	exit;
} elseif ( ! empty( $_GET['_wp_http_referer'] ) ) {
	 wp_redirect( remove_query_arg( [ '_wp_http_referer', '_wpnonce' ], wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	 exit;
}

$wp_list_table->prepare_items();

$title = __();
$parent_file = 'upload.php';

wp_enqueue_script( 'media' );

add_screen_option( 'per_page', ['label' => _x()] );

get_current_screen()->add_help_tab( [
'id'		=> 'overview',
'title'		=> __(),
'content'	=>
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
] );
get_current_screen()->add_help_tab( [
'id'		=> 'actions-links',
'title'		=> __(),
'content'	=>
	'<p>' . __() . '</p>'
] );
get_current_screen()->add_help_tab( [
'id'		=> 'attaching-files',
'title'		=> __(),
'content'	=>
	'<p>' . __() . '</p>'
] );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
);

require_once( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
<h2>
<?php
echo esc_html( $title );
if ( current_user_can( 'upload_files' ) ) { ?>
	<a href="media-new.php" class="add-new-h2"><?php echo esc_html_x('Add New', 'file'); ?></a><?php
}
if ( ! empty( $_REQUEST['s'] ) )
	printf( '<span class="subtitle">' . __() . '</span>', get_search_query() ); ?>
</h2>

<?php
$message = '';
if ( ! empty( $_GET['posted'] ) ) {
	$message = __();
	$_SERVER['REQUEST_URI'] = remove_query_arg(['posted'], $_SERVER['REQUEST_URI']);
}

if ( ! empty( $_GET['attached'] ) && $attached = absint( $_GET['attached'] ) ) {
	$message = sprintf( _n('Reattached %d attachment.', 'Reattached %d attachments.', $attached), $attached );
	$_SERVER['REQUEST_URI'] = remove_query_arg(['attached'], $_SERVER['REQUEST_URI']);
}

if ( ! empty( $_GET['deleted'] ) && $deleted = absint( $_GET['deleted'] ) ) {
	$message = sprintf( _n( 'Media attachment permanently deleted.', '%d media attachments permanently deleted.', $deleted ), number_format_i18n( $_GET['deleted'] ) );
	$_SERVER['REQUEST_URI'] = remove_query_arg(['deleted'], $_SERVER['REQUEST_URI']);
}

if ( ! empty( $_GET['trashed'] ) && $trashed = absint( $_GET['trashed'] ) ) {
	$message = sprintf( _n( 'Media attachment moved to the trash.', '%d media attachments moved to the trash.', $trashed ), number_format_i18n( $_GET['trashed'] ) );
	$message .= ' <a href="' . esc_url( wp_nonce_url( 'upload.php?doaction=undo&action=untrash&ids='.($_GET['ids'] ?? ''), "bulk-media" ) ) . '">' . __() . '</a>';
	$_SERVER['REQUEST_URI'] = remove_query_arg(['trashed'], $_SERVER['REQUEST_URI']);
}

if ( ! empty( $_GET['untrashed'] ) && $untrashed = absint( $_GET['untrashed'] ) ) {
	$message = sprintf( _n( 'Media attachment restored from the trash.', '%d media attachments restored from the trash.', $untrashed ), number_format_i18n( $_GET['untrashed'] ) );
	$_SERVER['REQUEST_URI'] = remove_query_arg(['untrashed'], $_SERVER['REQUEST_URI']);
}

$messages[1] = __();
$messages[2] = __();
$messages[3] = __();
$messages[4] = __() . ' <a href="' . esc_url( wp_nonce_url( 'upload.php?doaction=undo&action=untrash&ids='.($_GET['ids'] ?? ''), "bulk-media" ) ) . '">' . __() . '</a>';
$messages[5] = __();

if ( ! empty( $_GET['message'] ) && isset( $messages[ $_GET['message'] ] ) ) {
	$message = $messages[ $_GET['message'] ];
	$_SERVER['REQUEST_URI'] = remove_query_arg(['message'], $_SERVER['REQUEST_URI']);
}

if ( !empty($message) ) { ?>
<div id="message" class="updated"><p><?php echo $message; ?></p></div>
<?php } ?>

<form id="posts-filter" action="" method="get">

<?php $wp_list_table->views(); ?>

<?php $wp_list_table->display(); ?>

<div id="ajax-response"></div>
<?php find_posts_div(); ?>
</form>
</div>

<?php
include( ABSPATH . 'wp-admin/admin-footer.php' );

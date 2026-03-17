<?php
/**
 * Post advanced form for inclusion in the administration panels.
 *
 * @package WordPress
 * @subpackage Administration
 */

// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

wp_enqueue_script('post');
$_wp_editor_expand = false;

/**
 * Filter whether to enable the 'expand' functionality in the post editor.
 *
 * @since 4.0.0
 *
 * @param bool $expand Whether to enable the 'expand' functionality. Default true.
 */
if ( post_type_supports( $post_type, 'editor' ) && ! wp_is_mobile() &&
	 ! ( $is_IE && preg_match( '/MSIE [5678]/', (string) $_SERVER['HTTP_USER_AGENT'] ) ) &&
	 apply_filters() ) {

	wp_enqueue_script('editor-expand');
	$_wp_editor_expand = ( get_user_setting( 'editor_expand', 'on' ) === 'on' );
}

if ( wp_is_mobile() )
	wp_enqueue_script( 'jquery-touch-punch' );

/**
 * Post ID global
 * @name $post_ID
 * @var int
 */
$post_ID = isset($post_ID) ? (int) $post_ID : 0;
$user_ID = isset($user_ID) ? (int) $user_ID : 0;
$action ??= '';

$thumbnail_support = current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports( $post_type, 'thumbnail' );
if ( ! $thumbnail_support && 'attachment' === $post_type && $post->post_mime_type ) {
	if ( str_starts_with((string) $post->post_mime_type, 'audio/') ) {
		$thumbnail_support = post_type_supports( 'attachment:audio', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:audio' );
	} elseif ( str_starts_with((string) $post->post_mime_type, 'video/') ) {
		$thumbnail_support = post_type_supports( 'attachment:video', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:video' );
	}
}

if ( $thumbnail_support ) {
	add_thickbox();
	wp_enqueue_media( [ 'post' => $post_ID ] );
}

// Add the local autosave notice HTML
add_action();

/*
 * @todo Document the $messages array(s).
 */
$messages = [];
$messages['post'] = [
	 0 => '', // Unused. Messages start at index 1.
	 1 => sprintf( __(), esc_url( get_permalink($post_ID) ) ),
	 2 => __(),
	 3 => __(),
	 4 => __(),
	/* translators: %s: date and time of the revision */
	 5 => isset($_GET['revision']) ? sprintf( __(), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
	 6 => sprintf( __(), esc_url( get_permalink($post_ID) ) ),
	 7 => __(),
	 8 => sprintf( __(), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
	 9 => sprintf( __(),
		/* translators: Publish box date format, see http://php.net/date */
		date_i18n( __(), strtotime( (string) $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
	10 => sprintf( __(), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
];
$messages['page'] = [
	 0 => '', // Unused. Messages start at index 1.
	 1 => sprintf( __(), esc_url( get_permalink($post_ID) ) ),
	 2 => __(),
	 3 => __(),
	 4 => __(),
	 5 => isset($_GET['revision']) ? sprintf( __(), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
	 6 => sprintf( __(), esc_url( get_permalink($post_ID) ) ),
	 7 => __(),
	 8 => sprintf( __(), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
	 9 => sprintf( __(), date_i18n( __(), strtotime( (string) $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
	10 => sprintf( __(), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
];
$messages['attachment'] = array_fill( 1, 10, __() ); // Hack, for now.

/**
 * Filter the post updated messages.
 *
 * @since 3.0.0
 *
 * @param array $messages Post updated messages. For defaults @see $messages declarations above.
 */
$messages = apply_filters();

$message = false;
if ( isset($_GET['message']) ) {
	$_GET['message'] = absint( $_GET['message'] );
	if ( isset($messages[$post_type][$_GET['message']]) )
		$message = $messages[$post_type][$_GET['message']];
	elseif ( !isset($messages[$post_type]) && isset($messages['post'][$_GET['message']]) )
		$message = $messages['post'][$_GET['message']];
}

$notice = false;
$form_extra = '';
if ( 'auto-draft' == $post->post_status ) {
	if ( 'edit' == $action )
		$post->post_title = '';
	$autosave = false;
	$form_extra .= "<input type='hidden' id='auto_draft' name='auto_draft' value='1' />";
} else {
	$autosave = wp_get_post_autosave( $post_ID );
}

$form_action = 'editpost';
$nonce_action = 'update-post_' . $post_ID;
$form_extra .= "<input type='hidden' id='post_ID' name='post_ID' value='" . esc_attr() . "' />";

// Detect if there exists an autosave newer than the post and if that autosave is different than the post
if ( $autosave && mysql2date( 'U', $autosave->post_modified_gmt, false ) > mysql2date( 'U', $post->post_modified_gmt, false ) ) {
	foreach ( _wp_post_revision_fields() as $autosave_field => $_autosave_field ) {
		if ( normalize_whitespace( $autosave->$autosave_field ) != normalize_whitespace( $post->$autosave_field ) ) {
			$notice = sprintf( __(), get_edit_post_link( $autosave->ID ) );
			break;
		}
	}
	// If this autosave isn't different from the current post, begone.
	if ( ! $notice )
		wp_delete_post_revision( $autosave->ID );
	unset($autosave_field, $_autosave_field);
}

$post_type_object = get_post_type_object($post_type);

// All meta boxes should be defined and added before the first do_meta_boxes() call (or potentially during the do_meta_boxes action).
require_once( ABSPATH . 'wp-admin/includes/meta-boxes.php' );


$publish_callback_args = null;
if ( post_type_supports($post_type, 'revisions') && 'auto-draft' != $post->post_status ) {
	$revisions = wp_get_post_revisions( $post_ID );

	// We should aim to show the revisions metabox only when there are revisions.
	if ( count( $revisions ) > 1 ) {
		// Reset pointer for key()
		$publish_callback_args = [ 'revisions_count' => count( $revisions ), 'revision_id' => array_key_first( $revisions ) ];
		add_meta_box('revisionsdiv', __(), 'post_revisions_meta_box', null, 'normal', 'core');
	}
}

if ( 'attachment' == $post_type ) {
	wp_enqueue_script( 'image-edit' );
	wp_enqueue_style( 'imgareaselect' );
	add_meta_box( 'submitdiv', __(), 'attachment_submit_meta_box', null, 'side', 'core' );
	add_action();

	if ( str_starts_with((string) $post->post_mime_type, 'audio/') ) {
		add_meta_box( 'attachment-id3', __(), 'attachment_id3_data_meta_box', null, 'normal', 'core' );
	}
} else {
	add_meta_box( 'submitdiv', __(), 'post_submit_meta_box', null, 'side', 'core', $publish_callback_args );
}

if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post_type, 'post-formats' ) )
	add_meta_box( 'formatdiv', _x(), 'post_format_meta_box', null, 'side', 'core' );

// all taxonomies
foreach ( get_object_taxonomies( $post ) as $tax_name ) {
	$taxonomy = get_taxonomy( $tax_name );
	if ( ! $taxonomy->show_ui || false === $taxonomy->meta_box_cb )
		continue;

	$label = $taxonomy->labels->name;

	if ( ! is_taxonomy_hierarchical( $tax_name ) )
		$tax_meta_box_id = 'tagsdiv-' . $tax_name;
	else
		$tax_meta_box_id = $tax_name . 'div';

	add_meta_box( $tax_meta_box_id, $label, $taxonomy->meta_box_cb, null, 'side', 'core', [ 'taxonomy' => $tax_name ] );
}

if ( post_type_supports($post_type, 'page-attributes') )
	add_meta_box('pageparentdiv', 'page' == $post_type ? __() : __(), 'page_attributes_meta_box', null, 'side', 'core');

if ( $thumbnail_support && current_user_can( 'upload_files' ) )
	add_meta_box('postimagediv', __(), 'post_thumbnail_meta_box', null, 'side', 'low');

if ( post_type_supports($post_type, 'excerpt') )
	add_meta_box('postexcerpt', __(), 'post_excerpt_meta_box', null, 'normal', 'core');

if ( post_type_supports($post_type, 'trackbacks') )
	add_meta_box('trackbacksdiv', __(), 'post_trackback_meta_box', null, 'normal', 'core');

if ( post_type_supports($post_type, 'custom-fields') )
	add_meta_box('postcustom', __(), 'post_custom_meta_box', null, 'normal', 'core');

/**
 * Fires in the middle of built-in meta box registration.
 *
 * @since 2.1.0
 * @deprecated 3.7.0 Use 'add_meta_boxes' instead.
 *
 * @param WP_Post $post Post object.
 */
do_action( 'dbx_post_advanced', $post );

if ( post_type_supports($post_type, 'comments') )
	add_meta_box('commentstatusdiv', __(), 'post_comment_status_meta_box', null, 'normal', 'core');

if ( ( 'publish' == get_post_status( $post ) || 'private' == get_post_status( $post ) ) && post_type_supports($post_type, 'comments') )
	add_meta_box('commentsdiv', __(), 'post_comment_meta_box', null, 'normal', 'core');

if ( ! ( 'pending' == get_post_status( $post ) && ! current_user_can( $post_type_object->cap->publish_posts ) ) )
	add_meta_box('slugdiv', __(), 'post_slug_meta_box', null, 'normal', 'core');

if ( post_type_supports($post_type, 'author') ) {
	if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) )
		add_meta_box('authordiv', __(), 'post_author_meta_box', null, 'normal', 'core');
}

/**
 * Fires after all built-in meta boxes have been added.
 *
 * @since 3.0.0
 *
 * @param string  $post_type Post type.
 * @param WP_Post $post      Post object.
 */
do_action( 'add_meta_boxes', $post_type, $post );

/**
 * Fires after all built-in meta boxes have been added, contextually for the given post type.
 *
 * The dynamic portion of the hook, $post_type, refers to the post type of the post.
 *
 * @since 3.0.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'add_meta_boxes_' . $post_type, $post );

/**
 * Fires after meta boxes have been added.
 *
 * Fires once for each of the default meta box contexts: normal, advanced, and side.
 *
 * @since 3.0.0
 *
 * @param string  $post_type Post type of the post.
 * @param string  $context   string  Meta box context.
 * @param WP_Post $post      Post object.
 */
do_action( 'do_meta_boxes', $post_type, 'normal', $post );
/** This action is documented in wp-admin/edit-form-advanced.php */
do_action( 'do_meta_boxes', $post_type, 'advanced', $post );
/** This action is documented in wp-admin/edit-form-advanced.php */
do_action( 'do_meta_boxes', $post_type, 'side', $post );

add_screen_option('layout_columns', ['max' => 2, 'default' => 2] );

if ( 'post' == $post_type ) {
	$customize_display = '<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'      => 'customize-display',
		'title'   => __(),
		'content' => $customize_display,
	] );

	$title_and_editor  = '<p>' . __() . '</p>';
	$title_and_editor .= '<p>' . __() . '</p>';
	$title_and_editor .= '<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'      => 'title-post-editor',
		'title'   => __(),
		'content' => $title_and_editor,
	] );

	get_current_screen()->set_help_sidebar(
			'<p>' . sprintf(__(), 'options-writing.php') . '</p>' .
			'<p><strong>' . __() . '</strong></p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>'
	);
} elseif ( 'page' == $post_type ) {
	$about_pages = '<p>' . __() . '</p>' .
		'<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'      => 'about-pages',
		'title'   => __(),
		'content' => $about_pages,
	] );

	get_current_screen()->set_help_sidebar(
			'<p><strong>' . __() . '</strong></p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>'
	);
} elseif ( 'attachment' == $post_type ) {
	get_current_screen()->add_help_tab( [
		'id'      => 'overview',
		'title'   => __(),
		'content' =>
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>' .
			'<p>' . __() . '</p>'
	] );

	get_current_screen()->set_help_sidebar(
	'<p><strong>' . __() . '</strong></p>' .
	'<p>' . __() . '</p>' .
	'<p>' . __() . '</p>'
	);
}

if ( 'post' == $post_type || 'page' == $post_type ) {
	$inserting_media = '<p>' . __() . '</p>';
	$inserting_media .= '<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'		=> 'inserting-media',
		'title'		=> __(),
		'content' 	=> $inserting_media,
	] );
}

if ( 'post' == $post_type ) {
	$publish_box = '<p>' . __() . '</p>';
	$publish_box .= '<ul><li>' . __() . '</li>';

	if ( current_theme_supports( 'post-formats' ) && post_type_supports( 'post', 'post-formats' ) ) {
		$publish_box .= '<li>' . __() . '</li>';
	}

	if ( current_theme_supports( 'post-thumbnails' ) && post_type_supports( 'post', 'thumbnail' ) ) {
		$publish_box .= '<li>' . __() . '</li>';
	}

	$publish_box .= '</ul>';

	get_current_screen()->add_help_tab( [
		'id'      => 'publish-box',
		'title'   => __(),
		'content' => $publish_box,
	] );

	$discussion_settings  = '<p>' . __() . '</p>';
	$discussion_settings .= '<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id'      => 'discussion-settings',
		'title'   => __(),
		'content' => $discussion_settings,
	] );
} elseif ( 'page' == $post_type ) {
	$page_attributes = '<p>' . __() . '</p>' .
		'<p>' . __() . '</p>' .
		'<p>' . __() . '</p>';

	get_current_screen()->add_help_tab( [
		'id' => 'page-attributes',
		'title' => __(),
		'content' => $page_attributes,
	] );
}

require_once( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
<h2><?php
echo esc_html( $title );
if ( isset( $post_new_file ) && current_user_can( $post_type_object->cap->create_posts ) )
	echo ' <a href="' . esc_url( admin_url() ) . '" class="add-new-h2">' . esc_html( $post_type_object->labels->add_new ) . '</a>';
?></h2>
<?php if ( $notice ) : ?>
<div id="notice" class="error"><p id="has-newer-autosave"><?php echo $notice ?></p></div>
<?php endif; ?>
<?php if ( $message ) : ?>
<div id="message" class="updated"><p><?php echo $message; ?></p></div>
<?php endif; ?>
<div id="lost-connection-notice" class="error hidden">
	<p><span class="spinner"></span> <?php _e( '<strong>Connection lost.</strong> Saving has been disabled until you&#8217;re reconnected.' ); ?>
	<span class="hide-if-no-sessionstorage"><?php _e( 'We&#8217;re backing up this post in your browser, just in case.' ); ?></span>
	</p>
</div>
<?php
/**
 * Fires inside the post editor <form> tag.
 *
 * @since 3.0.0
 *
 * @param WP_Post $post Post object.
 */
?>
<form name="post" action="post.php" method="post" id="post"<?php do_action( 'post_edit_form_tag', $post ); ?>>
<?php wp_nonce_field($nonce_action); ?>
<input type="hidden" id="user-id" name="user_ID" value="<?php echo (int) $user_ID ?>" />
<input type="hidden" id="hiddenaction" name="action" value="<?php echo esc_attr() ?>" />
<input type="hidden" id="originalaction" name="originalaction" value="<?php echo esc_attr() ?>" />
<input type="hidden" id="post_author" name="post_author" value="<?php echo esc_attr(); ?>" />
<input type="hidden" id="post_type" name="post_type" value="<?php echo esc_attr() ?>" />
<input type="hidden" id="original_post_status" name="original_post_status" value="<?php echo esc_attr() ?>" />
<input type="hidden" id="referredby" name="referredby" value="<?php echo esc_url(wp_get_referer()); ?>" />
<?php if ( ! empty( $active_post_lock ) ) { ?>
<input type="hidden" id="active_post_lock" value="<?php echo esc_attr(); ?>" />
<?php
}
if ( 'draft' != get_post_status( $post ) )
	wp_original_referer_field(true, 'previous');

echo $form_extra;

wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
?>

<?php
/**
 * Fires at the beginning of the edit form.
 *
 * At this point, the required hidden fields and nonces have already been output.
 *
 * @since 3.7.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'edit_form_top', $post ); ?>

<div id="poststuff">
<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">
<div id="post-body-content">

<?php if ( post_type_supports($post_type, 'title') ) { ?>
<div id="titlediv">
<div id="titlewrap">
	<?php
	/**
	 * Filter the title field placeholder text.
	 *
	 * @since 3.1.0
	 *
	 * @param string  $text Placeholder text. Default 'Enter title here'.
	 * @param WP_Post $post Post object.
	 */
	?>
	<label class="screen-reader-text" id="title-prompt-text" for="title"><?php echo apply_filters(); ?></label>
	<input type="text" name="post_title" size="30" value="<?php echo esc_attr(); ?>" id="title" autocomplete="off" />
</div>
<div class="inside">
<?php
$sample_permalink_html = $post_type_object->public ? get_sample_permalink_html($post->ID) : '';
$shortlink = wp_get_shortlink($post->ID, 'post');
$permalink = get_permalink( $post->ID );
if ( !empty( $shortlink ) && $shortlink !== $permalink && $permalink !== home_url('?page_id=' . $post->ID) )
    $sample_permalink_html .= '<input id="shortlink" type="hidden" value="' . esc_attr() . '" /><a href="#" class="button button-small" onclick="prompt(&#39;URL:&#39;, jQuery(\'#shortlink\').val()); return false;">' . __() . '</a>';

if ( $post_type_object->public && ! ( 'pending' == get_post_status( $post ) && !current_user_can( $post_type_object->cap->publish_posts ) ) ) {
	$has_sample_permalink = $sample_permalink_html && 'auto-draft' != $post->post_status;
?>
	<div id="edit-slug-box" class="hide-if-no-js">
	<?php
		if ( $has_sample_permalink )
			echo $sample_permalink_html;
	?>
	</div>
<?php
}
?>
</div>
<?php
wp_nonce_field( 'samplepermalink', 'samplepermalinknonce', false );
?>
</div><!-- /titlediv -->
<?php
}
/**
 * Fires after the title field.
 *
 * @since 3.5.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'edit_form_after_title', $post );

if ( post_type_supports($post_type, 'editor') ) {
?>
<div id="postdivrich" class="postarea<?php if ( $_wp_editor_expand ) { echo ' wp-editor-expand'; } ?>">

<?php wp_editor( $post->post_content, 'content', [
	'dfw' => true,
	'drag_drop_upload' => true,
	'tabfocus_elements' => 'insert-media-button,save-post',
	'editor_height' => 300,
	'tinymce' => [
		'resize' => false,
		'wp_autoresize_on' => $_wp_editor_expand,
		'add_unload_trigger' => false,
	],
] ); ?>
<table id="post-status-info"><tbody><tr>
	<td id="wp-word-count"><?php printf( __(), '<span class="word-count">0</span>' ); ?></td>
	<td class="autosave-info">
	<span class="autosave-message">&nbsp;</span>
<?php
	if ( 'auto-draft' != $post->post_status ) {
		echo '<span id="last-edit">';
		if ( $last_user = get_userdata( get_post_meta( $post_ID, '_edit_last', true ) ) ) {
			printf(__(), esc_html( $last_user->display_name ), mysql2date(get_option(), $post->post_modified), mysql2date(get_option(), $post->post_modified));
		} else {
			printf(__(), mysql2date(get_option(), $post->post_modified), mysql2date(get_option(), $post->post_modified));
		}
		echo '</span>';
	} ?>
	</td>
	<td id="content-resize-handle" class="hide-if-no-js"><br /></td>
</tr></tbody></table>

</div>
<?php }
/**
 * Fires after the content editor.
 *
 * @since 3.5.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'edit_form_after_editor', $post );
?>
</div><!-- /post-body-content -->

<div id="postbox-container-1" class="postbox-container">
<?php

if ( 'page' == $post_type ) {
	/**
	 * Fires before meta boxes with 'side' context are output for the 'page' post type.
	 *
	 * The submitpage box is a meta box with 'side' context, so this hook fires just before it is output.
	 *
	 * @since 2.5.0
	 *
	 * @param WP_Post $post Post object.
	 */
	do_action( 'submitpage_box', $post );
}
else {
	/**
	 * Fires before meta boxes with 'side' context are output for all post types other than 'page'.
	 *
	 * The submitpost box is a meta box with 'side' context, so this hook fires just before it is output.
	 *
	 * @since 2.5.0
	 *
	 * @param WP_Post $post Post object.
	 */
	do_action( 'submitpost_box', $post );
}


do_meta_boxes($post_type, 'side', $post);

?>
</div>
<div id="postbox-container-2" class="postbox-container">
<?php

do_meta_boxes(null, 'normal', $post);

if ( 'page' == $post_type ) {
	/**
	 * Fires after 'normal' context meta boxes have been output for the 'page' post type.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_Post $post Post object.
	 */
	do_action( 'edit_page_form', $post );
}
else {
	/**
	 * Fires after 'normal' context meta boxes have been output for all post types other than 'page'.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_Post $post Post object.
	 */
	do_action( 'edit_form_advanced', $post );
}


do_meta_boxes(null, 'advanced', $post);

?>
</div>
<?php
/**
 * Fires after all meta box sections have been output, before the closing #post-body div.
 *
 * @since 2.1.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'dbx_post_sidebar', $post );

?>
</div><!-- /post-body -->
<br class="clear" />
</div><!-- /poststuff -->
</form>
</div>

<?php
if ( post_type_supports( $post_type, 'comments' ) )
	wp_comment_reply();
?>

<?php if ( post_type_supports( $post_type, 'title' ) && '' === $post->post_title ) : ?>
<script type="text/javascript">
try{document.post.title.focus();}catch(e){}
</script>
<?php endif; ?>

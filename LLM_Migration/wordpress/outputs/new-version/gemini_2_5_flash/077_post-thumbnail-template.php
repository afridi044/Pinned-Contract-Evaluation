<?php
/**
 * WordPress Post Thumbnail Template Functions.
 *
 * Support for post thumbnails
 * Themes function.php must call add_theme_support( 'post-thumbnails' ) to use these.
 *
 * @package WordPress
 * @subpackage Template
 */

// Assuming WP_Query class is available in the global namespace in a WordPress environment.
// If this code were part of a standalone library, a `use WP_Query;` statement might be needed.

/**
 * Check if post has an image attached.
 *
 * @since 2.9.0
 *
 * @param int|null $post_id Optional. Post ID.
 * @return bool Whether post has an image attached.
 */
function has_post_thumbnail( ?int $post_id = null ): bool {
	return (bool) get_post_thumbnail_id( $post_id );
}

/**
 * Retrieve Post Thumbnail ID.
 *
 * @since 2.9.0
 *
 * @param int|null $post_id Optional. Post ID.
 * @return int The post thumbnail ID, or 0 if not found.
 */
function get_post_thumbnail_id( ?int $post_id = null ): int {
	$post_id = $post_id ?? get_the_ID();
	// get_post_meta with $single=true returns the value or false.
	// Casting to int will convert false or empty string to 0, which is appropriate here.
	return (int) get_post_meta( $post_id, '_thumbnail_id', true );
}

/**
 * Display Post Thumbnail.
 *
 * @since 2.9.0
 *
 * @param string|array $size Optional. Image size. Defaults to 'post-thumbnail', which theme sets using set_post_thumbnail_size( $width, $height, $crop_flag );.
 * @param string|array $attr Optional. Query string or array of attributes.
 * @return void
 */
function the_post_thumbnail( string|array $size = 'post-thumbnail', string|array $attr = '' ): void {
	echo get_the_post_thumbnail( null, $size, $attr );
}

/**
 * Update cache for thumbnails in the current loop
 *
 * @since 3.2.0
 *
 * @param WP_Query|null $wp_query Optional. A WP_Query instance. Defaults to the $wp_query global.
 * @return void
 */
function update_post_thumbnail_cache( ?WP_Query $wp_query = null ): void {
	$wp_query ??= $GLOBALS['wp_query'];

	if ( $wp_query->thumbnails_cached ) {
		return;
	}

	$thumb_ids = [];
	foreach ( $wp_query->posts as $post ) {
		// This pattern is common in legacy code and functionally equivalent to:
		// $id = get_post_thumbnail_id( $post->ID );
		// if ( $id ) { $thumb_ids[] = $id; }
		if ( $id = get_post_thumbnail_id( $post->ID ) ) {
			$thumb_ids[] = $id;
		}
	}

	if ( ! empty( $thumb_ids ) ) {
		_prime_post_caches( $thumb_ids, false, true );
	}

	$wp_query->thumbnails_cached = true;
}

/**
 * Retrieve Post Thumbnail.
 *
 * @since 2.9.0
 *
 * @param int|null     $post_id Optional. Post ID.
 * @param string|array $size Optional. Image size. Defaults to 'post-thumbnail'.
 * @param string|array $attr Optional. Query string or array of attributes.
 * @return string The post thumbnail HTML.
 */
function get_the_post_thumbnail( ?int $post_id = null, string|array $size = 'post-thumbnail', string|array $attr = '' ): string {
	$post_id = $post_id ?? get_the_ID();
	$post_thumbnail_id = get_post_thumbnail_id( $post_id );

	/**
	 * Filter the post thumbnail size.
	 *
	 * @since 2.9.0
	 *
	 * @param string|array $size The post thumbnail size.
	 */
	$size = apply_filters( 'post_thumbnail_size', $size );

	if ( $post_thumbnail_id ) {

		/**
		 * Fires before fetching the post thumbnail HTML.
		 *
		 * Provides "just in time" filtering of all filters in wp_get_attachment_image().
		 *
		 * @since 2.9.0
		 *
		 * @param int          $post_id           The post ID.
		 * @param int          $post_thumbnail_id The post thumbnail ID.
		 * @param string|array $size              The post thumbnail size.
		 */
		do_action( 'begin_fetch_post_thumbnail_html', $post_id, $post_thumbnail_id, $size );
		if ( in_the_loop() ) {
			update_post_thumbnail_cache();
		}
		$html = wp_get_attachment_image( $post_thumbnail_id, $size, false, $attr );

		/**
		 * Fires after fetching the post thumbnail HTML.
		 *
		 * @since 2.9.0
		 *
		 * @param int          $post_id           The post ID.
		 * @param int          $post_thumbnail_id The post thumbnail ID.
		 * @param string|array $size              The post thumbnail size.
		 */
		do_action( 'end_fetch_post_thumbnail_html', $post_id, $post_thumbnail_id, $size );

	} else {
		$html = '';
	}
	/**
	 * Filter the post thumbnail HTML.
	 *
	 * @since 2.9.0
	 *
	 * @param string       $html              The post thumbnail HTML.
	 * @param int          $post_id           The post ID.
	 * @param int          $post_thumbnail_id The post thumbnail ID.
	 * @param string|array $size              The post thumbnail size.
	 * @param string|array $attr              Query string of attributes.
	 */
	return apply_filters( 'post_thumbnail_html', $html, $post_id, $post_thumbnail_id, $size, $attr );
}
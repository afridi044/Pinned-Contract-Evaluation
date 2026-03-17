<?php
declare(strict_types=1);

/**
 * Outputs the OPML XML format for getting the links defined in the link
 * administration. This can be used to export links from one blog over to
 * another. Links aren't exported by the WordPress export, so this file handles
 * that.
 *
 * This file is not added by default to WordPress theme pages when outputting
 * feed links. It will have to be added manually for browsers and users to pick
 * up that this file exists.
 *
 * @package WordPress
 */

require_once __DIR__ . '/wp-load.php';

$charset = get_option('blog_charset');
header("Content-Type: text/xml; charset={$charset}", true);

$category_args = [
	'taxonomy'     => 'link_category',
	'hierarchical' => 0,
];

$link_cat_param = $_GET['link_cat'] ?? null;

// A value of '0' or a null/omitted param means get all categories.
// Any other value is passed to the 'include' argument. This logic
// preserves the behavior of the original script for functional equivalence.
if ($link_cat_param !== null && $link_cat_param !== '0') {
	if ($link_cat_param === 'all') {
		// Preserves the original behavior of passing 'all' as an include.
		$category_args['include'] = 'all';
	} else {
		$category_args['include'] = absint(urldecode($link_cat_param));
	}
}

echo '<?xml version="1.0"?>' . PHP_EOL;
?>
<opml version="1.0">
	<head>
		<title><?php printf(__('Links for %s'), esc_attr(get_bloginfo('name', 'display'))); ?></title>
		<dateCreated><?php echo gmdate("D, d M Y H:i:s"); ?> GMT</dateCreated>
		<?php
		/**
		 * Fires in the OPML header.
		 *
		 * @since 3.0.0
		 */
		do_action('opml_head');
		?>
	</head>
	<body>
<?php
$categories = get_categories($category_args);

// The (array) cast is a safeguard against non-iterable return types from legacy functions.
foreach ((array) $categories as $category) {
	/**
	 * Filter the OPML outline link category name.
	 *
	 * @since 2.2.0
	 *
	 * @param string $catname The OPML outline category name.
	 */
	$category_name = apply_filters('link_category', $category->name);
	$escaped_category_name = esc_attr($category_name);

	echo "    <outline type=\"category\" title=\"{$escaped_category_name}\">\n";

	$bookmarks = get_bookmarks(['category' => $category->term_id]);

	foreach ((array) $bookmarks as $bookmark) {
		/**
		 * Filter the OPML outline link title text.
		 *
		 * @since 2.2.0
		 *
		 * @param string $title The OPML outline title text.
		 */
		$title = apply_filters('link_title', $bookmark->link_name);

		$escaped_title = esc_attr($title);
		$escaped_rss_url = esc_attr($bookmark->link_rss);
		$escaped_html_url = esc_attr($bookmark->link_url);
		$updated_date = ('0000-00-00 00:00:00' !== $bookmark->link_updated) ? $bookmark->link_updated : '';

		echo "        <outline text=\"{$escaped_title}\" type=\"link\" xmlUrl=\"{$escaped_rss_url}\" htmlUrl=\"{$escaped_html_url}\" updated=\"{$updated_date}\" />\n";
	}

	echo "    </outline>\n";
}
?>
</body>
</opml>
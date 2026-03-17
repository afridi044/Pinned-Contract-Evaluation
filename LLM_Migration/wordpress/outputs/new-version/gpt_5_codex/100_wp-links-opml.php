<?php

require_once __DIR__ . '/wp-load.php';

header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

$linkCat = $_GET['link_cat'] ?? '';

if ($linkCat !== '') {
    $linkCat = (string) urldecode($linkCat);

    if (!in_array($linkCat, ['all', '0'], true)) {
        $linkCat = absint($linkCat);
    }
}

echo '<?xml version="1.0"?>' . PHP_EOL;
?>
<opml version="1.0">
    <head>
        <title><?php printf(__('Links for %s'), esc_attr(get_bloginfo('name', 'display'))); ?></title>
        <dateCreated><?php echo esc_html(gmdate('D, d M Y H:i:s')); ?> GMT</dateCreated>
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
$categoryArgs = [
    'taxonomy'     => 'link_category',
    'hierarchical' => 0,
];

if (!empty($linkCat)) {
    $categoryArgs['include'] = $linkCat;
}

$cats = get_categories($categoryArgs);

foreach ((array) $cats as $cat) :
    /**
     * Filter the OPML outline link category name.
     *
     * @since 2.2.0
     *
     * @param string $catname The OPML outline category name.
     */
    $catname = apply_filters('link_category', $cat->name);
    ?>
<outline type="category" title="<?php echo esc_attr($catname); ?>">
<?php
    $bookmarks = get_bookmarks(['category' => $cat->term_id]);

    foreach ((array) $bookmarks as $bookmark) :
        /**
         * Filter the OPML outline link title text.
         *
         * @since 2.2.0
         *
         * @param string $title The OPML outline title text.
         */
        $title = apply_filters('link_title', $bookmark->link_name);
        $updated = ('0000-00-00 00:00:00' !== $bookmark->link_updated) ? esc_attr($bookmark->link_updated) : '';
        ?>
    <outline text="<?php echo esc_attr($title); ?>" type="link" xmlUrl="<?php echo esc_attr($bookmark->link_rss); ?>" htmlUrl="<?php echo esc_attr($bookmark->link_url); ?>"<?php echo $updated !== '' ? ' updated="' . $updated . '"' : ''; ?> />
<?php
    endforeach; // $bookmarks
    ?>
</outline>
<?php
endforeach; // $cats
?>
    </body>
</opml>
<?php
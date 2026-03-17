<?php

declare(strict_types=1);

/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the wp-config.php file. The wp-config.php
 * file will then load the wp-settings.php file, which
 * will then set up the WordPress environment.
 *
 * If the wp-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * wp-config.php file.
 *
 * Will also search for wp-config.php in WordPress' parent
 * directory to allow the WordPress directory to remain
 * untouched.
 *
 * @package WordPress
 */

/** Define ABSPATH as this file's directory */
define('ABSPATH', __DIR__ . '/');

// Set error reporting to the modern standard for all errors.
error_reporting(E_ALL);

if (file_exists(ABSPATH . 'wp-config.php')) {
    /** The config file resides in ABSPATH */
    require_once ABSPATH . 'wp-config.php';
} elseif (
    file_exists(dirname(ABSPATH) . '/wp-config.php') &&
    !file_exists(dirname(ABSPATH) . '/wp-settings.php')
) {
    /** The config file resides one level above ABSPATH but is not part of another install */
    require_once dirname(ABSPATH) . '/wp-config.php';
} else {
    // A config file doesn't exist. We're going to try to create it.

    define('WPINC', 'wp-includes');
    require_once ABSPATH . WPINC . '/load.php';

    // Standardize $_SERVER variables across setups.
    wp_fix_server_vars();

    require_once ABSPATH . WPINC . '/functions.php';

    $path = wp_guess_url() . '/wp-admin/setup-config.php';

    /*
     * We're going to redirect to setup-config.php. While this shouldn't result
     * in an infinite loop, that's a silly thing to assume, don't you think? If
     * we're traveling in circles, our last-ditch effort is "Need more help?"
     */
    if (!str_contains($_SERVER['REQUEST_URI'], 'setup-config')) {
        header('Location: ' . $path);
        exit;
    }

    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
    require_once ABSPATH . WPINC . '/version.php';

    wp_check_php_mysql_versions();
    wp_load_translations_early();

    // Die with an error message.
    $die = sprintf(
        "%s</p><p>%s</p><p>%s</p><p><a href=\"%s\" class=\"button button-large\">%s</a>",
        __("There doesn't seem to be a <code>wp-config.php</code> file. I need this before we can get started."),
        __("Need more help? <a href='https://wordpress.org/support/article/editing-wp-config-php/'>We got it</a>."),
        __("You can create a <code>wp-config.php</code> file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file."),
        $path,
        __('Create a Configuration File')
    );

    wp_die($die, __('WordPress &rsaquo; Error'));
}
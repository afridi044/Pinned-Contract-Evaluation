<?php
/**
 * WordPress Core Stubs
 * 
 * Minimal stub definitions for WordPress core functions, classes, and constants.
 * This allows isolated execution of WordPress files without full WP bootstrap.
 * 
 * Add symbols incrementally as test failures reveal dependencies.
 */

declare(strict_types=1);

// ============================================================================
// ADDITIONAL STUB INCLUDES (optional - load if they exist)
// ============================================================================
if (file_exists(__DIR__ . '/Renderer.php')) {
    require_once __DIR__ . '/Renderer.php';
}
if (file_exists(__DIR__ . '/load.php')) {
    require_once __DIR__ . '/load.php';
}
if (file_exists(__DIR__ . '/entry.php')) {
    require_once __DIR__ . '/entry.php';
}

// ============================================================================
// CONSTANTS
// ============================================================================

// Core paths
if (!defined('ABSPATH')) define('ABSPATH', '/fake/wordpress/');
if (!defined('WPINC')) define('WPINC', 'wp-includes');
if (!defined('WP_CONTENT_DIR')) define('WP_CONTENT_DIR', '/fake/wordpress/wp-content');
if (!defined('WP_PLUGIN_DIR')) define('WP_PLUGIN_DIR', '/fake/wordpress/wp-content/plugins');

// Debug and error handling
if (!defined('WP_DEBUG')) define('WP_DEBUG', false);
if (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', false);
if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);

// Database
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'wordpress');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Version
if (!defined('WP_VERSION')) define('WP_VERSION', '4.0');

// Misc
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('WEEK_IN_SECONDS')) define('WEEK_IN_SECONDS', 604800);
if (!defined('MONTH_IN_SECONDS')) define('MONTH_IN_SECONDS', 2592000);
if (!defined('YEAR_IN_SECONDS')) define('YEAR_IN_SECONDS', 31536000);

// ============================================================================
// GLOBAL VARIABLES
// ============================================================================

global $wpdb, $wp_filter, $wp_actions, $wp_current_filter, $wp_query, $post, $wp_rewrite;
global $wp_version, $wp_the_query, $wp_scripts, $wp_styles, $wp_locale;

$wpdb = new stdClass();
$wpdb->prefix = 'wp_';
$wpdb->base_prefix = 'wp_';

$wp_filter = [];
$wp_actions = [];
$wp_current_filter = [];

// ============================================================================
// I18N / TRANSLATION
// ============================================================================

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void {
        // Silent in test harness to prevent false "OUTPUT during include" errors
        // Real WordPress echoes, but that would trigger Level-1 output detection
        // for files that legitimately call _e() during load
    }
}

if (!function_exists('_x')) {
    function _x(string $text, string $context, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('_nx')) {
    function _nx(string $single, string $plural, int $number, string $context, string $domain = 'default'): string {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// ============================================================================
// HOOKS (ACTIONS & FILTERS)
// ============================================================================

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool {
        global $wp_filter;
        
        // Validate array callbacks have proper structure [object/class, method_name]
        if (is_array($callback)) {
            if (count($callback) !== 2) {
                // Silently skip malformed callbacks instead of throwing (WordPress compat)
                return false;
            }
            // Ensure first element is object or string (class name) and second is method name
            $first = $callback[0];
            $second = $callback[1];
            if (!(is_object($first) || is_string($first)) || !is_string($second)) {
                return false;
            }
        }
        
        if (!isset($wp_filter[$hook])) {
            $wp_filter[$hook] = [];
        }
        if (!isset($wp_filter[$hook][$priority])) {
            $wp_filter[$hook][$priority] = [];
        }
        
        $wp_filter[$hook][$priority][] = ['function' => $callback, 'accepted_args' => $acceptedArgs];
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, callable $callback, int $priority = 10): bool {
        global $wp_filter;
        if (isset($wp_filter[$hook][$priority])) {
            foreach ($wp_filter[$hook][$priority] as $key => $filter) {
                if ($filter['function'] === $callback) {
                    unset($wp_filter[$hook][$priority][$key]);
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void {
        global $wp_filter, $wp_actions, $wp_current_filter;
        
        if (!isset($wp_actions[$hook])) {
            $wp_actions[$hook] = 1;
        } else {
            $wp_actions[$hook]++;
        }
        
        $wp_current_filter[] = $hook;
        
        if (isset($wp_filter[$hook])) {
            ksort($wp_filter[$hook]);
            foreach ($wp_filter[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    call_user_func_array($callback['function'], array_slice($args, 0, $callback['accepted_args']));
                }
            }
        }
        
        array_pop($wp_current_filter);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool {
        return add_action($hook, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, callable $callback, int $priority = 10): bool {
        return remove_action($hook, $callback, $priority);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed {
        global $wp_filter, $wp_current_filter;
        
        $wp_current_filter[] = $hook;
        
        if (isset($wp_filter[$hook])) {
            ksort($wp_filter[$hook]);
            foreach ($wp_filter[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $value = call_user_func_array($callback['function'], array_merge([$value], array_slice($args, 0, $callback['accepted_args'] - 1)));
                }
            }
        }
        
        array_pop($wp_current_filter);
        
        return $value;
    }
}

if (!function_exists('has_filter')) {
    function has_filter(string $hook, callable|bool $callback = false): bool|int {
        global $wp_filter;
        
        if ($callback === false) {
            return isset($wp_filter[$hook]);
        }
        
        if (isset($wp_filter[$hook])) {
            foreach ($wp_filter[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $filter) {
                    if ($filter['function'] === $callback) {
                        return $priority;
                    }
                }
            }
        }
        
        return false;
    }
}

if (!function_exists('has_action')) {
    function has_action(string $hook, callable|bool $callback = false): bool|int {
        return has_filter($hook, $callback);
    }
}

if (!function_exists('did_action')) {
    function did_action(string $hook): int {
        global $wp_actions;
        return $wp_actions[$hook] ?? 0;
    }
}

if (!function_exists('current_filter')) {
    function current_filter(): string {
        global $wp_current_filter;
        return end($wp_current_filter) ?: '';
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string|false $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = []): void {
        // Stub
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $object_name, array $l10n): bool {
        return true;
    }
}

if (!function_exists('add_thickbox')) {
    function add_thickbox(): void {
        // Stub
    }
}

// ============================================================================
// ESCAPING / SANITIZATION
// ============================================================================
// NOTE: Many sanitization functions (esc_html, esc_attr, sanitize_text_field,
// sanitize_key, sanitize_title) are defined in WordPress core files themselves.
// Only stub functions that are CALLED but not DEFINED in the target files.

if (!function_exists('esc_url')) {
    function esc_url(string $url, array|null $protocols = null, string $context = 'display'): string {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_js')) {
    function esc_js(string $text): string {
        return addslashes($text);
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql(string|array $data): string|array {
        if (is_array($data)) {
            return array_map('esc_sql', $data);
        }
        return addslashes($data);
    }
}

// ============================================================================
// ERROR HANDLING
// ============================================================================

if (!class_exists('WP_Error')) {
    class WP_Error {
        private array $errors = [];
        private array $error_data = [];
        
        public function __construct(string|int $code = '', string $message = '', mixed $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function add(string|int $code, string $message, mixed $data = ''): void {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
        
        public function get_error_code(): string|int {
            return !empty($this->errors) ? key($this->errors) : '';
        }
        
        public function get_error_message(string|int $code = ''): string {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }
        
        public function get_error_messages(string|int $code = ''): array {
            if (empty($code)) {
                return array_reduce($this->errors, 'array_merge', []);
            }
            return $this->errors[$code] ?? [];
        }
        
        public function get_error_data(string|int $code = ''): mixed {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->error_data[$code] ?? null;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool {
        return $thing instanceof WP_Error;
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

if (!function_exists('wp_parse_args')) {
    function wp_parse_args(string|array|object $args, array $defaults = []): array {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = $args;
        } else {
            parse_str($args, $parsed_args);
        }
        
        return array_merge($defaults, $parsed_args);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('absint')) {
    function absint(mixed $maybeint): int {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(string|array $value): string|array {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value);
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash(string|array $value): string|array {
        return is_array($value) ? array_map('wp_slash', $value) : addslashes($value);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string {
        return strip_tags($data, '<a><b><i><strong><em><p><br><ul><ol><li><div><span>');
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array|string $args = []): never {
        throw new RuntimeException("wp_die called: $message");
    }
}

// Post/Meta functions
if (!function_exists('get_the_ID')) {
    function get_the_ID(): int|false {
        global $post;
        return $post->ID ?? false;
    }
}

if (!function_exists('get_post')) {
    function get_post(int|null $post = null): object|null {
        global $post;
        $global_post = $post;
        if (null === $post) {
            return $global_post;
        }
        return (object)['ID' => $post, 'post_type' => 'post'];
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed {
        // Minimal stub - returns empty
        return $single ? '' : [];
    }
}

// Image/Attachment functions
if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image(int $attachment_id, string|array $size = 'thumbnail', bool $icon = false, string|array $attr = ''): string {
        return "<img src='placeholder.jpg' alt='' />";
    }
}

// Theme support
if (!function_exists('add_theme_support')) {
    function add_theme_support(string $feature, mixed ...$args): bool {
        global $_wp_theme_features;
        if (!isset($_wp_theme_features)) {
            $_wp_theme_features = [];
        }
        $_wp_theme_features[$feature] = $args;
        return true;
    }
}

if (!function_exists('current_theme_supports')) {
    function current_theme_supports(string $feature): bool {
        global $_wp_theme_features;
        return isset($_wp_theme_features[$feature]);
    }
}

// ============================================================================
// OPTION / TRANSIENT
// ============================================================================

$_wp_options = [];
$_wp_transients = [];

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed {
        global $_wp_options;
        return $_wp_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value): bool {
        global $_wp_options;
        $_wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        global $_wp_options;
        unset($_wp_options[$option]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed {
        global $_wp_transients;
        return $_wp_transients[$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool {
        global $_wp_transients;
        $_wp_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool {
        global $_wp_transients;
        unset($_wp_transients[$transient]);
        return true;
    }
}

// ============================================================================
// CONDITIONAL TAGS (return false by default in test env)
// ============================================================================

if (!function_exists('is_admin')) {
    function is_admin(): bool {
        return false;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return false;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return false;
    }
}

if (!function_exists('is_singular')) {
    function is_singular(): bool {
        return false;
    }
}

// ============================================================================
// PLUGIN / THEME
// ============================================================================

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string {
        return 'http://example.com/wp-content/plugins/' . ltrim($path, '/');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_get_popular_importers')) {
    function wp_get_popular_importers(): array {
        return [];
    }
}

// ============================================================================
// DEPRECATED (for backward compat testing)
// ============================================================================

if (!function_exists('_deprecated_function')) {
    function _deprecated_function(string $function, string $version, string $replacement = ''): void {
        // Silent in test environment
    }
}

if (!function_exists('_deprecated_file')) {
    function _deprecated_file(string $file, string $version, string $replacement = '', string $message = ''): void {
        // Silent in test environment
    }
}

if (!function_exists('_deprecated_hook')) {
    function _deprecated_hook(string $hook, string $version, string $replacement = '', string $message = ''): void {
        // Silent in test environment
    }
}

if (!function_exists('_deprecated_argument')) {
    function _deprecated_argument(string $function, string $version, string $message = ''): void {
        // Silent in test environment
    }
}

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function, string $message, string $version): void {
        // Silent in test environment
    }
}

// ============================================================================
// CLASSES - WordPress Core
// ============================================================================

/**
 * Base class for WordPress widgets
 * Minimal stub to allow widget classes to load
 */
if (!class_exists('WP_Widget')) {
    class WP_Widget {
        public $id_base;
        public $name;
        public $widget_options;
        public $control_options;
        
        public function __construct($id_base, $name, $widget_options = [], $control_options = []) {
            $this->id_base = $id_base;
            $this->name = $name;
            $this->widget_options = $widget_options;
            $this->control_options = $control_options;
        }
        
        public function widget($args, $instance) {}
        public function form($instance) {}
        public function update($new_instance, $old_instance) { return $new_instance; }
    }
}

/**
 * Base class for list tables in WordPress admin
 */
if (!class_exists('WP_List_Table')) {
    class WP_List_Table {
        public $items;
        public function __construct($args = []) {}
        public function prepare_items() {}
        public function display() {}
        public function get_columns() { return []; }
    }
}

/**
 * Base class for managing script/style dependencies
 * NOTE: Some files extend this class
 */
if (!class_exists('WP_Dependencies')) {
    class WP_Dependencies {
        public $registered = [];
        public $queue = [];
        public $to_do = [];
        public $done = [];
        public $args = [];
        
        public function add($handle, $src, $deps = [], $ver = false, $args = null) {
            return true;
        }
        
        public function remove($handle) {}
        public function enqueue($handles) {}
        public function dequeue($handles) {}
    }
}

// ============================================================================
// CLASSES - External Libraries
// ============================================================================

/**
 * XML-RPC client base class
 * NOTE: Some files need this as a base class
 */
if (!class_exists('IXR_Client')) {
    class IXR_Client {
        public $server;
        public $port;
        public $path;
        
        public function __construct($server, $path = false, $port = 80, $timeout = 15) {
            $this->server = $server;
            $this->port = $port;
            $this->path = $path ?: '/';
        }
        
        // Note: query() takes variable args but child classes override without params
        public function query() { return false; }
    }
}

/**
 * FTP base class
 */
if (!class_exists('ftp_base')) {
    class ftp_base {
        public $host;
        public $port;
        
        public function __construct($host = '', $port = 21) {
            $this->host = $host;
            $this->port = $port;
        }
        
        public function connect($host = '', $port = 21) { return false; }
        public function quit() {}
    }
}

// ============================================================================
// ADDITIONAL HELPER FUNCTIONS (for test suite expansion)
// ============================================================================

/**
 * Mock screen object for admin pages
 */
if (!class_exists('WP_Screen_Mock')) {
    class WP_Screen_Mock {
        public $id = 'test-screen';
        public $base = 'test';
        
        public function add_help_tab($args) {
            return true;
        }
        
        public function set_help_sidebar($content) {
            return true;
        }
        
        public function remove_help_tab($id) {
            return true;
        }
        
        public function get_help_tabs() {
            return [];
        }
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen(): object|null {
        return new WP_Screen_Mock();
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(string|array $key, string|bool $value = false, string $url = ''): string {
        if (is_array($key)) {
            $query = http_build_query($key);
            $url = $value ?: '';
        } else {
            $query = "$key=$value";
        }
        
        if (empty($url)) {
            $url = 'http://example.com/';
        }
        
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . $query;
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect(string $location, int $status = 302, string $x_redirect_by = 'WordPress'): bool {
        // In test environment, just return true without actual redirect
        return true;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
        return 'test_password_' . bin2hex(random_bytes(6));
    }
}

if (!function_exists('wp_new_user_notification')) {
    function wp_new_user_notification(int $user_id, mixed $deprecated = null, string $notify = ''): void {
        // Silent in test environment
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string|int $action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
        $nonce_field = '<input type="hidden" name="' . $name . '" value="test_nonce" />';
        if ($display) {
            // Silent to avoid output during file include
            return '';
        }
        return $nonce_field;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(string|null $time = null, bool $create_dir = true, bool $refresh_cache = false): array {
        return [
            'path' => '/fake/wordpress/wp-content/uploads/2024/01',
            'url' => 'http://example.com/wp-content/uploads/2024/01',
            'subdir' => '/2024/01',
            'basedir' => '/fake/wordpress/wp-content/uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false,
        ];
    }
}

if (!function_exists('wp_enqueue_media')) {
    function wp_enqueue_media(array $args = []): void {
        // Silent enqueue in test environment
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object {
        $user = new stdClass();
        $user->ID = 1;
        $user->user_login = 'testuser';
        $user->user_email = 'test@example.com';
        $user->display_name = 'Test User';
        return $user;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool {
        // Return true for all capabilities in test environment
        return true;
    }
}

if (!function_exists('get_user_option')) {
    function get_user_option(string $option, int $user = 0, bool $deprecated = false): mixed {
        return false;
    }
}

if (!function_exists('wp_reset_vars')) {
    function wp_reset_vars(array $vars): void {
        foreach ($vars as $var) {
            if (isset($_POST[$var])) {
                $GLOBALS[$var] = $_POST[$var];
            } elseif (isset($_GET[$var])) {
                $GLOBALS[$var] = $_GET[$var];
            } else {
                $GLOBALS[$var] = null;
            }
        }
    }
}

if (!function_exists('wp_check_post_lock')) {
    function wp_check_post_lock(int $post_id): int|false {
        return false; // No lock in test environment
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link(int $post_id = 0, string $context = 'display'): string|null {
        return 'http://example.com/wp-admin/post.php?post=' . $post_id . '&action=edit';
    }
}

if (!function_exists('register_widget')) {
    function register_widget(string $widget_class): void {
        global $_wp_registered_widgets;
        if (!isset($_wp_registered_widgets)) {
            $_wp_registered_widgets = [];
        }
        $_wp_registered_widgets[$widget_class] = true;
    }
}

if (!function_exists('get_template_directory')) {
    function get_template_directory(): string {
        return '/fake/wordpress/wp-content/themes/current-theme';
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory(): string {
        return '/fake/wordpress/wp-content/themes/current-theme';
    }
}

if (!function_exists('wp_prepare_themes_for_js')) {
    function wp_prepare_themes_for_js(array $themes = []): array {
        return [];
    }
}

if (!function_exists('wp_register_sidebar_widget')) {
    function wp_register_sidebar_widget(string $id, string $name, callable $callback, array $options = []): void {
        // Silent registration in test environment
    }
}

if (!function_exists('wp_register_widget_control')) {
    function wp_register_widget_control(string $id, string $name, callable $callback, array $options = []): void {
        // Silent registration in test environment
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string {
        return rtrim($value, '/\\') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string {
        return rtrim($value, '/\\');
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype(string $filename, array|null $mimes = null): array {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return [
            'ext' => $ext,
            'type' => 'application/octet-stream',
            'proper_filename' => false,
        ];
    }
}

if (!function_exists('get_allowed_mime_types')) {
    function get_allowed_mime_types(int|null $user_id = null): array {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];
    }
}

// ============================================================================
// STUB MARKER
// ============================================================================

if (!defined('WP_STUBS_LOADED')) {
    define('WP_STUBS_LOADED', true);
}

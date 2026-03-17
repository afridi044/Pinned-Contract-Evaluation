<?php

declare(strict_types=1);

/**
 * Customize Setting Class.
 *
 * Handles saving and sanitizing of settings.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
class WP_Customize_Setting
{
    /**
     * Setting ID.
     *
     * @var string
     */
    public string $id;

    /**
     * Type of setting.
     *
     * @var string
     */
    public string $type = 'theme_mod';

    /**
     * Capability required to edit this setting.
     *
     * @var string|string[]
     */
    public string|array $capability = 'edit_theme_options';

    /**
     * Feature a theme is required to support to enable this setting.
     *
     * @var string|string[]
     */
    public string|array $theme_supports = '';

    /**
     * Default value for the setting.
     *
     * @var mixed
     */
    public mixed $default = '';

    /**
     * How to transport changes to the preview.
     *
     * @var string
     */
    public string $transport = 'refresh';

    /**
     * Server-side sanitization callback for the setting's value.
     *
     * @var callable|string
     */
    public callable|string $sanitize_callback = '';

    /**
     * Server-side sanitization callback for the setting's value for use in JavaScript.
     *
     * @var callable|string
     */
    public callable|string $sanitize_js_callback = '';

    /**
     * Parsed ID data.
     *
     * @var array{base: string, keys: string[]}
     */
    protected array $id_data = [];

    /**
     * Cached and sanitized $_POST value for the setting.
     *
     * @var mixed
     */
    private mixed $_post_value;

    /**
     * Constructor.
     *
     * Any supplied $args override class property defaults.
     *
     * @since 3.4.0
     *
     * @param WP_Customize_Manager $manager WP_Customize_Manager instance.
     * @param string               $id      An specific ID of the setting. Can be a theme mod or option name.
     * @param array<string, mixed> $args    Setting arguments.
     */
    public function __construct(
        public readonly WP_Customize_Manager $manager,
        string $id,
        array $args = []
    ) {
        $keys = array_keys(get_object_vars($this));
        foreach ($keys as $key) {
            if ('manager' === $key) {
                continue;
            }
            if (array_key_exists($key, $args)) {
                $this->$key = $args[$key];
            }
        }

        $this->id = $id;

        // Parse the ID for array keys.
        $this->id_data['keys'] = preg_split('/\[/', str_replace(']', '', $this->id)) ?: [];
        $this->id_data['base'] = array_shift($this->id_data['keys']) ?? '';

        // Rebuild the ID.
        $this->id = $this->id_data['base'];
        if (!empty($this->id_data['keys'])) {
            $this->id .= '[' . implode('][', $this->id_data['keys']) . ']';
        }

        if ($this->sanitize_callback) {
            add_filter("customize_sanitize_{$this->id}", $this->sanitize_callback, 10, 2);
        }

        if ($this->sanitize_js_callback) {
            add_filter("customize_sanitize_js_{$this->id}", $this->sanitize_js_callback, 10, 2);
        }
    }

    /**
     * Handle previewing the setting.
     *
     * @since 3.4.0
     */
    public function preview(): void
    {
        switch ($this->type) {
            case 'theme_mod':
                add_filter('theme_mod_' . $this->id_data['base'], [$this, '_preview_filter']);
                break;

            case 'option':
                if (empty($this->id_data['keys'])) {
                    add_filter('pre_option_' . $this->id_data['base'], [$this, '_preview_filter']);
                } else {
                    add_filter('option_' . $this->id_data['base'], [$this, '_preview_filter']);
                    add_filter('default_option_' . $this->id_data['base'], [$this, '_preview_filter']);
                }
                break;

            default:
                /**
                 * Fires when the WP_Customize_Setting::preview() method is called for settings
                 * not handled as theme_mods or options.
                 *
                 * @since 3.4.0
                 * @param WP_Customize_Setting $this WP_Customize_Setting instance.
                 */
                do_action('customize_preview_' . $this->id, $this);
        }
    }

    /**
     * Callback function to filter the theme mods and options.
     *
     * @since 3.4.0
     * @param mixed $original Old value.
     * @return mixed New or old value.
     */
    public function _preview_filter(mixed $original): mixed
    {
        return $this->multidimensional_replace($original, $this->id_data['keys'], $this->post_value());
    }

    /**
     * Check user capabilities and theme supports, and then save the value of the setting.
     *
     * @since 3.4.0
     * @return bool False if cap check fails or value isn't set, true on success.
     */
    public final function save(): bool
    {
        $value = $this->post_value();

        if (!$this->check_capabilities() || !isset($value)) {
            return false;
        }

        /**
         * Fires when the WP_Customize_Setting::save() method is called.
         *
         * @since 3.4.0
         * @param WP_Customize_Setting $this WP_Customize_Setting instance.
         */
        do_action('customize_save_' . $this->id_data['base'], $this);

        $this->update($value);
        return true;
    }

    /**
     * Fetch and sanitize the $_POST value for the setting.
     *
     * @since 3.4.0
     * @param mixed|null $default A default value which is used as a fallback.
     * @return mixed The default value on failure, otherwise the sanitized value.
     */
    public final function post_value(mixed $default = null): mixed
    {
        if (isset($this->_post_value)) {
            return $this->_post_value;
        }

        $result = $this->manager->post_value($this);

        if (isset($result)) {
            return $this->_post_value = $result;
        }

        return $default;
    }

    /**
     * Sanitize an input.
     *
     * @since 3.4.0
     * @param mixed $value The value to sanitize.
     * @return mixed Null if an input isn't valid, otherwise the sanitized value.
     */
    public function sanitize(mixed $value): mixed
    {
        $value = wp_unslash($value);

        /**
         * Filter a Customize setting value in un-slashed form.
         *
         * @since 3.4.0
         * @param mixed                $value Value of the setting.
         * @param WP_Customize_Setting $this  WP_Customize_Setting instance.
         */
        return apply_filters("customize_sanitize_{$this->id}", $value, $this);
    }

    /**
     * Save the value of the setting, using the related API.
     *
     * @since 3.4.0
     * @param mixed $value The value to update.
     * @return mixed The result of saving the value.
     */
    protected function update(mixed $value): mixed
    {
        return match ($this->type) {
            'theme_mod' => $this->_update_theme_mod($value),
            'option' => $this->_update_option($value),
            default =>
                /**
                 * Fires when the WP_Customize_Setting::update() method is called for settings
                 * not handled as theme_mods or options.
                 *
                 * @since 3.4.0
                 * @param mixed                $value Value of the setting.
                 * @param WP_Customize_Setting $this  WP_Customize_Setting instance.
                 */
                do_action('customize_update_' . $this->type, $value, $this),
        };
    }

    /**
     * Update the theme mod from the value of the parameter.
     *
     * @since 3.4.0
     * @param mixed $value The value to update.
     */
    protected function _update_theme_mod(mixed $value): void
    {
        if (empty($this->id_data['keys'])) {
            set_theme_mod($this->id_data['base'], $value);
            return;
        }

        $mods = get_theme_mod($this->id_data['base']);
        $mods = $this->multidimensional_replace($mods, $this->id_data['keys'], $value);
        if (isset($mods)) {
            set_theme_mod($this->id_data['base'], $mods);
        }
    }

    /**
     * Update the option from the value of the setting.
     *
     * @since 3.4.0
     * @param mixed $value The value to update.
     * @return bool|null The result of saving the value.
     */
    protected function _update_option(mixed $value): ?bool
    {
        if (empty($this->id_data['keys'])) {
            return update_option($this->id_data['base'], $value);
        }

        $options = get_option($this->id_data['base']);
        $options = $this->multidimensional_replace($options, $this->id_data['keys'], $value);
        if (isset($options)) {
            return update_option($this->id_data['base'], $options);
        }
        return null;
    }

    /**
     * Fetch the value of the setting.
     *
     * @since 3.4.0
     * @return mixed The value.
     */
    public function value(): mixed
    {
        $function = match ($this->type) {
            'theme_mod' => 'get_theme_mod',
            'option' => 'get_option',
            default => null,
        };

        if ($function === null) {
            /**
             * Filter a Customize setting value not handled as a theme_mod or option.
             *
             * @since 3.4.0
             * @param mixed $default The setting default value.
             */
            return apply_filters('customize_value_' . $this->id_data['base'], $this->default);
        }

        if (empty($this->id_data['keys'])) {
            return $function($this->id_data['base'], $this->default);
        }

        $values = $function($this->id_data['base']);
        return $this->multidimensional_get($values, $this->id_data['keys'], $this->default);
    }

    /**
     * Sanitize the setting's value for use in JavaScript.
     *
     * @since 3.4.0
     * @return mixed The requested escaped value.
     */
    public function js_value(): mixed
    {
        /**
         * Filter a Customize setting value for use in JavaScript.
         *
         * @since 3.4.0
         * @param mixed                $value The setting value.
         * @param WP_Customize_Setting $this  WP_Customize_Setting instance.
         */
        $value = apply_filters("customize_sanitize_js_{$this->id}", $this->value(), $this);

        if (is_string($value)) {
            return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }

    /**
     * Validate user capabilities and whether the theme supports the setting.
     *
     * @since 3.4.0
     * @return bool False if theme doesn't support the setting or user can't change setting, otherwise true.
     */
    public final function check_capabilities(): bool
    {
        if ($this->capability && !current_user_can(...(array) $this->capability)) {
            return false;
        }

        if ($this->theme_supports && !current_theme_supports(...(array) $this->theme_supports)) {
            return false;
        }

        return true;
    }

    /**
     * Multidimensional helper function to get a pointer to a specific node.
     *
     * @since 3.4.0
     * @param array<string, mixed>|null &$root
     * @param string[]                  $keys
     * @param bool                      $create Default is false.
     * @return array{root: array, node: array, key: string}|null
     */
    final protected function multidimensional(?array &$root, array $keys, bool $create = false): ?array
    {
        if ($create && $root === null) {
            $root = [];
        }

        if (!isset($root) || $keys === []) {
            return null;
        }

        $last = array_pop($keys);
        $node = &$root;

        foreach ($keys as $key) {
            if ($create && !isset($node[$key])) {
                $node[$key] = [];
            }

            if (!is_array($node) || !array_key_exists($key, $node)) {
                return null;
            }

            $node = &$node[$key];
        }

        if ($create && !isset($node[$last])) {
            $node[$last] = [];
        }

        if (!is_array($node) || !array_key_exists($last, $node)) {
            return null;
        }

        return [
            'root' => &$root,
            'node' => &$node,
            'key' => $last,
        ];
    }

    /**
     * Will attempt to replace a specific value in a multidimensional array.
     *
     * @since 3.4.0
     * @param mixed    $root
     * @param string[] $keys
     * @param mixed    $value The value to update.
     * @return mixed
     */
    final protected function multidimensional_replace(mixed $root, array $keys, mixed $value): mixed
    {
        if (!isset($value)) {
            return $root;
        }

        if ($keys === []) {
            return $value;
        }

        if (!is_array($root)) {
            $root = [];
        }

        $result = $this->multidimensional($root, $keys, true);

        if ($result !== null) {
            $result['node'][$result['key']] = $value;
        }

        return $root;
    }

    /**
     * Will attempt to fetch a specific value from a multidimensional array.
     *
     * @since 3.4.0
     * @param mixed      $root
     * @param string[]   $keys
     * @param mixed|null $default A default value which is used as a fallback.
     * @return mixed The requested value or the default value.
     */
    final protected function multidimensional_get(mixed $root, array $keys, mixed $default = null): mixed
    {
        if ($keys === []) {
            return $root ?? $default;
        }

        if (!is_array($root)) {
            return $default;
        }

        $result = $this->multidimensional($root, $keys);
        return $result['node'][$result['key']] ?? $default;
    }

    /**
     * Will attempt to check if a specific value in a multidimensional array is set.
     *
     * @since 3.4.0
     * @param mixed    $root
     * @param string[] $keys
     * @return bool True if value is set, false if not.
     */
    final protected function multidimensional_isset(mixed $root, array $keys): bool
    {
        if (!is_array($root)) {
            return false;
        }
        $result = $this->multidimensional($root, $keys);
        return $result !== null;
    }
}

/**
 * A setting that is used to filter a value, but will not save the results.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
class WP_Customize_Filter_Setting extends WP_Customize_Setting
{
    /**
     * This setting is a filter and does not save its value.
     *
     * @since 3.4.0
     * @param mixed $value The value to update.
     */
    public function update(mixed $value): void
    {
    }
}

/**
 * A setting that is used to filter a value, but will not save the results.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
final class WP_Customize_Header_Image_Setting extends WP_Customize_Setting
{
    public string $id = 'header_image_data';

    /**
     * @since 3.4.0
     * @param mixed $value
     */
    public function update(mixed $value): void
    {
        global $custom_image_header;

        // If the value doesn't exist (removed or random), use the header_image value.
        if (!$value) {
            $value = $this->manager->get_setting('header_image')->post_value();
        }

        if (is_array($value) && isset($value['choice'])) {
            $custom_image_header->set_header_image($value['choice']);
        } else {
            $custom_image_header->set_header_image($value);
        }
    }
}

/**
 * Class WP_Customize_Background_Image_Setting
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
final class WP_Customize_Background_Image_Setting extends WP_Customize_Setting
{
    public string $id = 'background_image_thumb';

    /**
     * @since 3.4.0
     * @param mixed $value
     */
    public function update(mixed $value): void
    {
        remove_theme_mod('background_image_thumb');
    }
}
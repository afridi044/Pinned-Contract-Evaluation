<?php
/**
 * Customize Setting Class.
 *
 * Handles saving and sanitizing of settings.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
class WP_Customize_Setting {
	/**
	 * @access public
	 * @var object|null
	 */
	public ?object $manager = null;

	/**
	 * @access public
	 * @var string|null
	 */
	public ?string $id = null;

	/**
	 * @access public
	 * @var string
	 */
	public string $type = 'theme_mod';

	/**
	 * Capability required to edit this setting.
	 *
	 * @var string
	 */
	public string $capability = 'edit_theme_options';

	/**
	 * Feature a theme is required to support to enable this setting.
	 *
	 * @access public
	 * @var string|array
	 */
	public string|array $theme_supports = '';

	public mixed $default = '';
	public string $transport = 'refresh';

	/**
	 * Server-side sanitization callback for the setting's value.
	 *
	 * @var callable|string|array|null
	 */
	public $sanitize_callback = null;
	public $sanitize_js_callback = null;

	protected array $id_data = [
		'keys' => [],
		'base' => '',
	];

	/**
	 * Cached and sanitized $_POST value for the setting.
	 *
	 * @access private
	 * @var mixed
	 */
	private mixed $_post_value = null;

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 3.4.0
	 *
	 * @param object $manager
	 * @param string $id      A specific ID of the setting. Can be a theme mod or option name.
	 * @param array  $args    Setting arguments.
	 */
	public function __construct(object $manager, string $id, array $args = []) {
		foreach ($args as $key => $value) {
			if (property_exists($this, $key)) {
				$this->$key = $value;
			}
		}

		$this->manager = $manager;
		$this->id      = $id;

		// Parse the ID for array keys.
		$this->id_data['keys'] = preg_split('/\[/', str_replace(']', '', $this->id)) ?: [];
		$this->id_data['base'] = array_shift($this->id_data['keys']) ?? '';

		// Rebuild the ID.
		$this->id = $this->id_data['base'];
		if (! empty($this->id_data['keys'])) {
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
	public function preview(): void {
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
				 * The dynamic portion of the hook name, $this->id, refers to the setting ID.
				 *
				 * @since 3.4.0
				 *
				 * @param WP_Customize_Setting $this WP_Customize_Setting instance.
				 */
				do_action('customize_preview_' . $this->id, $this);
		}
	}

	/**
	 * Callback function to filter the theme mods and options.
	 *
	 * @since 3.4.0
	 * @uses WP_Customize_Setting::multidimensional_replace()
	 *
	 * @param mixed $original Old value.
	 * @return mixed New or old value.
	 */
	public function _preview_filter($original) {
		return $this->multidimensional_replace($original, $this->id_data['keys'], $this->post_value());
	}

	/**
	 * Check user capabilities and theme supports, and then save
	 * the value of the setting.
	 *
	 * @since 3.4.0
	 *
	 * @return bool False if cap check fails or value isn't set.
	 */
	public final function save(): bool {
		$value = $this->post_value();

		if (! $this->check_capabilities() || ! isset($value)) {
			return false;
		}

		/**
		 * Fires when the WP_Customize_Setting::save() method is called.
		 *
		 * The dynamic portion of the hook name, $this->id_data['base'] refers to
		 * the base slug of the setting name.
		 *
		 * @since 3.4.0
		 *
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
	 *
	 * @param mixed $default A default value which is used as a fallback. Default is null.
	 * @return mixed The default value on failure, otherwise the sanitized value.
	 */
	public final function post_value(mixed $default = null): mixed {
		if ($this->_post_value !== null) {
			return $this->_post_value;
		}

		$result = $this->manager->post_value($this);

		if ($result !== null) {
			$this->_post_value = $result;

			return $this->_post_value;
		}

		return $default;
	}

	/**
	 * Sanitize an input.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed Null if an input isn't valid, otherwise the sanitized value.
	 */
	public function sanitize($value) {
		$value = wp_unslash($value);

		/**
		 * Filter a Customize setting value in un-slashed form.
		 *
		 * @since 3.4.0
		 *
		 * @param mixed                $value Value of the setting.
		 * @param WP_Customize_Setting $this  WP_Customize_Setting instance.
		 */
		return apply_filters("customize_sanitize_{$this->id}", $value, $this);
	}

	/**
	 * Save the value of the setting, using the related API.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value The value to update.
	 * @return mixed The result of saving the value.
	 */
	protected function update($value) {
		switch ($this->type) {
			case 'theme_mod':
				return $this->_update_theme_mod($value);

			case 'option':
				return $this->_update_option($value);

			default:
				/**
				 * Fires when the WP_Customize_Setting::update() method is called for settings
				 * not handled as theme_mods or options.
				 *
				 * The dynamic portion of the hook name, $this->type, refers to the type of setting.
				 *
				 * @since 3.4.0
				 *
				 * @param mixed                $value Value of the setting.
				 * @param WP_Customize_Setting $this  WP_Customize_Setting instance.
				 */
				return do_action('customize_update_' . $this->type, $value, $this);
		}
	}

	/**
	 * Update the theme mod from the value of the parameter.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value The value to update.
	 * @return mixed The result of saving the value.
	 */
	protected function _update_theme_mod($value) {
		if (empty($this->id_data['keys'])) {
			return set_theme_mod($this->id_data['base'], $value);
		}

		$mods = get_theme_mod($this->id_data['base']);
		$mods = $this->multidimensional_replace($mods, $this->id_data['keys'], $value);

		if (isset($mods)) {
			return set_theme_mod($this->id_data['base'], $mods);
		}

		return null;
	}

	/**
	 * Update the option from the value of the setting.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $value The value to update.
	 * @return mixed The result of saving the value.
	 */
	protected function _update_option($value) {
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
	 *
	 * @return mixed The value.
	 */
	public function value() {
		switch ($this->type) {
			case 'theme_mod':
				$function = 'get_theme_mod';
				break;
			case 'option':
				$function = 'get_option';
				break;
			default:
				/**
				 * Filter a Customize setting value not handled as a theme_mod or option.
				 *
				 * The dynamic portion of the hook name, $this->id_data['base'], refers to
				 * the base slug of the setting name.
				 *
				 * For settings handled as theme_mods or options, see those corresponding
				 * functions for available hooks.
				 *
				 * @since 3.4.0
				 *
				 * @param mixed $default The setting default value. Default empty.
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
	 *
	 * @return mixed The requested escaped value.
	 */
	public function js_value() {
		/**
		 * Filter a Customize setting value for use in JavaScript.
		 *
		 * The dynamic portion of the hook name, $this->id, refers to the setting ID.
		 *
		 * @since 3.4.0
		 *
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
	 * Validate user capabilities whether the theme supports the setting.
	 *
	 * @since 3.4.0
	 *
	 * @return bool False if theme doesn't support the setting or user can't change setting, otherwise true.
	 */
	public final function check_capabilities(): bool {
		$capability_args = (array) $this->capability;
		$capability      = array_shift($capability_args);

		if ($capability && ! current_user_can($capability, ...$capability_args)) {
			return false;
		}

		$theme_support_args = (array) $this->theme_supports;
		$theme_support      = array_shift($theme_support_args);

		if ($theme_support && ! current_theme_supports($theme_support, ...$theme_support_args)) {
			return false;
		}

		return true;
	}

	/**
	 * Multidimensional helper function.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $root
	 * @param mixed $keys
	 * @param bool  $create Default is false.
	 * @return array|null Keys are 'root', 'node', and 'key'.
	 */
	final protected function multidimensional(& $root, $keys, bool $create = false): ?array {
		if ($create && empty($root)) {
			$root = [];
		}

		if (! isset($root) || empty($keys)) {
			return null;
		}

		$keys = (array) $keys;
		$last = array_pop($keys);

		if ($last === null) {
			return null;
		}

		$node = &$root;

		foreach ($keys as $key) {
			if (! is_array($node)) {
				return null;
			}

			if ($create && ! isset($node[$key])) {
				$node[$key] = [];
			}

			if (! isset($node[$key])) {
				return null;
			}

			$node = &$node[$key];
		}

		if (! is_array($node)) {
			return null;
		}

		if ($create && ! isset($node[$last])) {
			$node[$last] = [];
		}

		if (! isset($node[$last])) {
			return null;
		}

		return [
			'root' => &$root,
			'node' => &$node,
			'key'  => $last,
		];
	}

	/**
	 * Will attempt to replace a specific value in a multidimensional array.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $root
	 * @param mixed $keys
	 * @param mixed $value The value to update.
	 * @return mixed
	 */
	final protected function multidimensional_replace($root, $keys, $value): mixed {
		if (! isset($value)) {
			return $root;
		}

		if (empty($keys)) {
			return $value;
		}

		$keys   = (array) $keys;
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
	 *
	 * @param mixed $root
	 * @param mixed $keys
	 * @param mixed $default A default value which is used as a fallback. Default is null.
	 * @return mixed The requested value or the default value.
	 */
	final protected function multidimensional_get($root, $keys, $default = null): mixed {
		if (empty($keys)) {
			return isset($root) ? $root : $default;
		}

		$keys   = (array) $keys;
		$result = $this->multidimensional($root, $keys);

		if ($result !== null && isset($result['node'][$result['key']])) {
			return $result['node'][$result['key']];
		}

		return $default;
	}

	/**
	 * Will attempt to check if a specific value in a multidimensional array is set.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $root
	 * @param mixed $keys
	 * @return bool True if value is set, false if not.
	 */
	final protected function multidimensional_isset($root, $keys): bool {
		$result = $this->multidimensional_get($root, $keys);

		return isset($result);
	}
}

/**
 * A setting that is used to filter a value, but will not save the results.
 *
 * Results should be properly handled using another setting or callback.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
class WP_Customize_Filter_Setting extends WP_Customize_Setting {

	/**
	 * @since 3.4.0
	 */
	public function update($value) {}
}

/**
 * A setting that is used to filter a value, but will not save the results.
 *
 * Results should be properly handled using another setting or callback.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
final class WP_Customize_Header_Image_Setting extends WP_Customize_Setting {
	public string $id = 'header_image_data';

	/**
	 * @since 3.4.0
	 *
	 * @param mixed $value
	 */
	public function update($value) {
		global $custom_image_header;

		if (! $value) {
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
final class WP_Customize_Background_Image_Setting extends WP_Customize_Setting {
	public string $id = 'background_image_thumb';

	/**
	 * @since 3.4.0
	 * @uses remove_theme_mod()
	 *
	 * @param mixed $value
	 */
	public function update($value) {
		remove_theme_mod('background_image_thumb');
	}
}
?>
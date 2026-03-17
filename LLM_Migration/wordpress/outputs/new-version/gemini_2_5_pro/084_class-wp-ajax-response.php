<?php
/**
 * Send XML response back to an AJAX request.
 *
 * @package WordPress
 * @since 2.1.0
 */
class WP_Ajax_Response {
	/**
	 * Store XML responses to send.
	 *
	 * @since 2.1.0
	 */
	private array $responses = [];

	/**
	 * Constructor - Passes args to {@see self::add()}.
	 *
	 * @since 2.1.0
	 *
	 * @param string|array $args Optional. Will be passed to add() method.
	 */
	public function __construct(string|array $args = '') {
		if (!empty($args)) {
			$this->add($args);
		}
	}

	/**
	 * Make private properties readable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to get.
	 * @return mixed Property.
	 */
	public function __get(string $name): mixed {
		return $this->$name;
	}

	/**
	 * Make private properties settable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name  Property to set.
	 * @param mixed  $value Property value.
	 * @return mixed Newly-set property.
	 */
	public function __set(string $name, mixed $value): mixed {
		return $this->$name = $value;
	}

	/**
	 * Make private properties checkable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to check if set.
	 * @return bool Whether the property is set.
	 */
	public function __isset(string $name): bool {
		return isset($this->$name);
	}

	/**
	 * Make private properties un-settable for backwards compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param string $name Property to unset.
	 */
	public function __unset(string $name): void {
		unset($this->$name);
	}

	/**
	 * Append to XML response based on given arguments.
	 *
	 * The arguments that can be passed in the $args parameter are below. It is
	 * also possible to pass a WP_Error object in either the 'id' or 'data'
	 * argument. The parameter isn't actually optional; content should be given
	 * in order to send the correct response.
	 *
	 * 'what' argument is a string that is the XMLRPC response type.
	 * 'action' argument is a boolean or string that acts like a nonce.
	 * 'id' argument can be WP_Error or an integer.
	 * 'old_id' argument is false by default or an integer of the previous ID.
	 * 'position' argument is an integer or a string with -1 = top, 1 = bottom,
	 * html ID = after, -html ID = before.
	 * 'data' argument is a string with the content or message.
	 * 'supplemental' argument is an array of strings that will be children of
	 * the supplemental element.
	 *
	 * @since 2.1.0
	 *
	 * @param string|array $args Override defaults.
	 * @return string XML response.
	 */
	public function add(string|array $args = ''): string {
		$defaults = [
			'what' => 'object',
			'action' => false,
			'id' => '0',
			'old_id' => false,
			'position' => 1,
			'data' => '',
			'supplemental' => [],
		];

		$r = wp_parse_args($args, $defaults);

		$position = preg_replace('/[^a-z0-9:_-]/i', '', (string) $r['position']);
		$id = $r['id'];
		$what = $r['what'];
		$old_id = $r['old_id'];
		$data = $r['data'];
		$action = $r['action'] === false ? ($_POST['action'] ?? null) : $r['action'];

		if (is_wp_error($id)) {
			$data = $id;
			$id = 0;
		}

		$response_data_xml = '';
		if (is_wp_error($data)) {
			foreach ((array) $data->get_error_codes() as $code) {
				$response_data_xml .= "<wp_error code='{$code}'><![CDATA[" . $data->get_error_message($code) . ']]></wp_error>';
				if (!$error_data = $data->get_error_data($code)) {
					continue;
				}

				$class_attr = '';
				if (is_object($error_data)) {
					$class_attr = ' class="' . get_class($error_data) . '"';
					$error_data = get_object_vars($error_data);
				}

				$response_data_xml .= "<wp_error_data code='{$code}'{$class_attr}>";

				if (is_scalar($error_data)) {
					$response_data_xml .= "<![CDATA[{$error_data}]]>";
				} elseif (is_array($error_data)) {
					foreach ($error_data as $k => $v) {
						$response_data_xml .= "<{$k}><![CDATA[{$v}]]></{$k}>";
					}
				}

				$response_data_xml .= '</wp_error_data>';
			}
		} else {
			$response_data_xml = "<response_data><![CDATA[{$data}]]></response_data>";
		}

		$supplemental_xml = '';
		if (is_array($r['supplemental'])) {
			$supplemental_data = '';
			foreach ($r['supplemental'] as $k => $v) {
				$supplemental_data .= "<{$k}><![CDATA[{$v}]]></{$k}>";
			}
			$supplemental_xml = "<supplemental>{$supplemental_data}</supplemental>";
		}

		$attributes = ['id' => $id];
		if (false !== $old_id) {
			$attributes['old_id'] = $old_id;
		}
		$attributes['position'] = $position;

		$attr_parts = [];
		foreach ($attributes as $key => $value) {
			$attr_parts[] = "{$key}='{$value}'";
		}
		$attr_string = implode(' ', $attr_parts);

		$xml = "<response action='{$action}_{$id}'>";
		$xml .= "<{$what} {$attr_string}>";
		$xml .= $response_data_xml;
		$xml .= $supplemental_xml;
		$xml .= "</{$what}>";
		$xml .= '</response>';

		$this->responses[] = $xml;
		return $xml;
	}

	/**
	 * Display XML formatted responses.
	 *
	 * Sets the content type header to text/xml.
	 *
	 * @since 2.1.0
	 */
	public function send(): never {
		$charset = get_option('blog_charset');
		header("Content-Type: text/xml; charset={$charset}");

		echo "<?xml version='1.0' encoding='{$charset}' standalone='yes'?>";
		echo '<wp_ajax>';

		foreach ((array) $this->responses as $response) {
			echo $response;
		}

		echo '</wp_ajax>';

		if (defined('DOING_AJAX') && DOING_AJAX) {
			wp_die();
		} else {
			die();
		}
	}
}
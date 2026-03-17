<?php

declare(strict_types=1);

/**
 * BackPress Styles enqueue.
 *
 * These classes were refactored from the WordPress WP_Scripts and WordPress
 * script enqueue API.
 *
 * @package BackPress
 * @since r74
 */

/**
 * BackPress Styles enqueue class.
 *
 * @package BackPress
 * @uses WP_Dependencies
 * @since r74
 */
class WP_Styles extends WP_Dependencies
{
	public ?string $base_url = null;
	public ?string $content_url = null;
	public ?string $default_version = null;
	public string $text_direction = 'ltr';
	public string $concat = '';
	public string $concat_version = '';
	public bool $do_concat = false;
	public string $print_html = '';
	public string $print_code = '';
	public ?array $default_dirs = null;

	public function __construct()
	{
		/**
		 * Fires when the WP_Styles instance is initialized.
		 *
		 * @since 2.6.0
		 *
		 * @param WP_Styles $this WP_Styles instance, passed by reference.
		 */
		do_action_ref_array('wp_default_styles', [&$this]);
	}

	/**
	 * Processes a single style item.
	 *
	 * @param string $handle The style's registered handle.
	 * @return bool True on success, false on failure.
	 */
	public function do_item(string $handle): bool
	{
		if (!parent::do_item($handle)) {
			return false;
		}

		$obj = $this->registered[$handle];

		if ($obj->ver === null) {
			$ver = '';
		} else {
			$ver = $obj->ver ?: $this->default_version;
		}

		if (isset($this->args[$handle])) {
			$ver = $ver ? "{$ver}&amp;{$this->args[$handle]}" : $this->args[$handle];
		}

		if ($this->do_concat) {
			if ($this->in_default_dir($obj->src) && !isset($obj->extra['conditional']) && !isset($obj->extra['alt'])) {
				$this->concat .= "{$handle},";
				$this->concat_version .= "{$handle}{$ver}";

				$inline_style = $this->print_inline_style($handle, false);
				if ($inline_style) {
					$this->print_code .= $inline_style;
				}

				return true;
			}
		}

		$media = esc_attr($obj->args ?? 'all');
		$href = $this->_css_href($obj->src, (string) $ver, $handle);

		if (empty($href)) {
			// Turns out there is nothing to print.
			return true;
		}

		$rel = !empty($obj->extra['alt']) ? 'alternate stylesheet' : 'stylesheet';
		$title_attr = isset($obj->extra['title']) ? sprintf("title='%s'", esc_attr($obj->extra['title'])) : '';

		$tag = sprintf(
			"<link rel='%s' id='%s-css'%s href='%s' type='text/css' media='%s' />\n",
			$rel,
			$handle,
			$title_attr ? ' ' . $title_attr : '',
			$href,
			$media
		);

		if ('rtl' === $this->text_direction && !empty($obj->extra['rtl'])) {
			if (is_bool($obj->extra['rtl']) || 'replace' === $obj->extra['rtl']) {
				$suffix = $obj->extra['suffix'] ?? '';
				$rtl_href = str_replace("{$suffix}.css", "-rtl{$suffix}.css", $this->_css_href($obj->src, (string) $ver, "{$handle}-rtl"));
			} else {
				$rtl_href = $this->_css_href($obj->extra['rtl'], (string) $ver, "{$handle}-rtl");
			}

			$rtl_tag = sprintf(
				"<link rel='%s' id='%s-rtl-css'%s href='%s' type='text/css' media='%s' />\n",
				$rel,
				$handle,
				$title_attr ? ' ' . $title_attr : '',
				$rtl_href,
				$media
			);

			/** This filter is documented in wp-includes/class.wp-styles.php */
			$rtl_tag = apply_filters('style_loader_tag', $rtl_tag, $handle);

			if ($obj->extra['rtl'] === 'replace') {
				$tag = $rtl_tag;
			} else {
				$tag .= $rtl_tag;
			}
		}

		/**
		 * Filter the HTML link tag of an enqueued style.
		 *
		 * @since 2.6.0
		 *
		 * @param string $tag    The link tag for the enqueued style.
		 * @param string $handle The style's registered handle.
		 */
		$tag = apply_filters('style_loader_tag', $tag, $handle);

		if (!empty($obj->extra['conditional'])) {
			$tag = "<!--[if {$obj->extra['conditional']}]>\n" . $tag . "<![endif]-->\n";
		}

		if ($this->do_concat) {
			$this->print_html .= $tag;
			if ($inline_style = $this->print_inline_style($handle, false)) {
				$this->print_html .= sprintf("<style type='text/css'>\n%s\n</style>\n", $inline_style);
			}
		} else {
			echo $tag;
			$this->print_inline_style($handle);
		}

		return true;
	}

	public function add_inline_style(string $handle, string $code): bool
	{
		if (!$code) {
			return false;
		}

		$after = $this->get_data($handle, 'after') ?: [];
		$after[] = $code;

		return $this->add_data($handle, 'after', $after);
	}

	public function print_inline_style(string $handle, bool $echo = true): string|bool
	{
		$output = $this->get_data($handle, 'after');

		if (empty($output)) {
			return false;
		}

		$output_string = implode("\n", $output);

		if (!$echo) {
			return $output_string;
		}

		echo "<style type='text/css'>\n";
		echo "{$output_string}\n";
		echo "</style>\n";

		return true;
	}

	public function all_deps(array|string $handles, bool $recursion = false, bool|int $group = false): array
	{
		$r = parent::all_deps($handles, $recursion, $group);
		if (!$recursion) {
			/**
			 * Filter the array of enqueued styles before processing for output.
			 *
			 * @since 2.6.0
			 *
			 * @param array $to_do The list of enqueued styles about to be processed.
			 */
			$this->to_do = apply_filters('print_styles_array', $this->to_do);
		}
		return $r;
	}

	public function _css_href(string|bool $src, string $ver, string $handle): string
	{
		if (!is_string($src) || $src === '') {
			return '';
		}

		if (!preg_match('|^(https?:)?//|', $src) && !($this->content_url && str_starts_with($src, $this->content_url))) {
			$src = $this->base_url . $src;
		}

		if ($ver !== '') {
			$src = add_query_arg('ver', $ver, $src);
		}

		/**
		 * Filter an enqueued style's fully-qualified URL.
		 *
		 * @since 2.6.0
		 *
		 * @param string $src    The source URL of the enqueued style.
		 * @param string $handle The style's registered handle.
		 */
		$src = apply_filters('style_loader_src', $src, $handle);
		return esc_url($src);
	}

	public function in_default_dir(string $src): bool
	{
		if ($this->default_dirs === null) {
			return true;
		}

		foreach ($this->default_dirs as $test) {
			if (str_starts_with($src, (string) $test)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Process items in the footer.
	 *
	 * HTML 5 allows styles in the body, so this method grabs late-enqueued
	 * items and outputs them in the footer.
	 *
	 * @return string[] Array of handles of processed items.
	 */
	public function do_footer_items(): array
	{
		$this->do_items(false, 1);
		return $this->done;
	}

	/**
	 * Resets the instance properties.
	 */
	public function reset(): void
	{
		$this->do_concat = false;
		$this->concat = '';
		$this->concat_version = '';
		$this->print_html = '';
	}
}
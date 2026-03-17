<?php

declare(strict_types=1);

/**
 * @package Akismet
 */
class Akismet_Widget extends WP_Widget
{
    public function __construct()
    {
        load_plugin_textdomain('akismet');

        parent::__construct(
            'akismet_widget',
            __('Akismet Widget', 'akismet'),
            ['description' => __('Display the number of spam comments Akismet has caught', 'akismet')]
        );

        if (is_active_widget(false, false, $this->id_base)) {
            add_action('wp_head', [$this, 'css']);
        }
    }

    public function css(): void
    {
        // Using NOWDOC for clean CSS output without PHP parsing or escaping.
        echo <<<'CSS'
<style type="text/css">
.a-stats {
	width: auto;
}
.a-stats a {
	background: #7CA821;
	background-image:-moz-linear-gradient(0% 100% 90deg,#5F8E14,#7CA821);
	background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#7CA821),to(#5F8E14));
	border: 1px solid #5F8E14;
	border-radius:3px;
	color: #CFEA93;
	cursor: pointer;
	display: block;
	font-weight: normal;
	height: 100%;
	-moz-border-radius:3px;
	padding: 7px 0 8px;
	text-align: center;
	text-decoration: none;
	-webkit-border-radius:3px;
	width: 100%;
}
.a-stats a:hover {
	text-decoration: none;
	background-image:-moz-linear-gradient(0% 100% 90deg,#6F9C1B,#659417);
	background-image:-webkit-gradient(linear,0% 0,0% 100%,from(#659417),to(#6F9C1B));
}
.a-stats .count {
	color: #FFF;
	display: block;
	font-size: 15px;
	line-height: 16px;
	padding: 0 13px;
	white-space: nowrap;
}
</style>
CSS;
    }

    public function form(array $instance): void
    {
        $title = $instance['title'] ?? __('Spam Blocked', 'akismet');

        $field_id = $this->get_field_id('title');
        $field_name = $this->get_field_name('title');
        $title_label = esc_html__('Title:', 'akismet');
        $title_value = esc_attr($title);

        // Using HEREDOC for cleaner HTML output.
        echo <<<HTML
		<p>
			<label for="{$field_id}">{$title_label}</label>
			<input class="widefat" id="{$field_id}" name="{$field_name}" type="text" value="{$title_value}" />
		</p>
HTML;
    }

    public function update(array $new_instance, array $old_instance): array
    {
        // The $old_instance is unused, but required by the parent method signature.
        return [
            'title' => strip_tags($new_instance['title'] ?? '')
        ];
    }

    public function widget(array $args, array $instance): void
    {
        $count_raw = get_option('akismet_spam_count');
        $count = is_numeric($count_raw) ? (int) $count_raw : 0;

        echo $args['before_widget'];

        if (! empty($instance['title'])) {
            echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        }

        $spam_text = sprintf(
            _n(
                '<strong class="count">%1$s spam</strong> blocked by <strong>Akismet</strong>',
                '<strong class="count">%1$s spam</strong> blocked by <strong>Akismet</strong>',
                $count,
                'akismet'
            ),
            number_format_i18n($count)
        );

        echo <<<HTML
	<div class="a-stats">
		<a href="http://akismet.com" target="_blank" title="">{$spam_text}</a>
	</div>
HTML;

        echo $args['after_widget'];
    }
}

function akismet_register_widgets(): void
{
    register_widget(Akismet_Widget::class);
}

add_action('widgets_init', 'akismet_register_widgets');
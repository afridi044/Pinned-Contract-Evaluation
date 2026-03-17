<?php
declare(strict_types=1);

/**
 * Customize Section Class.
 *
 * A UI container for controls, managed by the WP_Customize_Manager.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */
class WP_Customize_Section
{
    /**
     * Priority of the section which informs load order of sections.
     *
     * @since 3.4.0
     */
    public int $priority = 160;

    /**
     * Panel in which to show the section, making it a sub-section.
     *
     * @since 4.0.0
     */
    public string $panel = '';

    /**
     * Capability required for the section.
     *
     * @since 3.4.0
     */
    public string $capability = 'edit_theme_options';

    /**
     * Theme feature support for the section.
     *
     * @since 3.4.0
     */
    public string|array $theme_supports = '';

    /**
     * Title of the section to show in UI.
     *
     * @since 3.4.0
     */
    public string $title = '';

    /**
     * Description to show in the UI.
     *
     * @since 3.4.0
     */
    public string $description = '';

    /**
     * Customizer controls for this section.
     *
     * @since 3.4.0
     * @var array An array of WP_Customize_Control objects.
     */
    public array $controls;

    /**
     * Constructor.
     *
     * Any supplied $args override class property defaults.
     *
     * @since 3.4.0
     *
     * @param WP_Customize_Manager $manager Customizer bootstrap instance.
     * @param string               $id      An specific ID of the section.
     * @param array<string, mixed> $args    Section arguments.
     */
    public function __construct(
        /**
         * WP_Customize_Manager instance.
         * @since 3.4.0
         */
        public readonly WP_Customize_Manager $manager,
        /**
         * Unique identifier.
         * @since 3.4.0
         */
        public readonly string $id,
        array $args = []
    ) {
        // This loop mimics the original's behavior of setting any existing
        // property from the $args array.
        foreach (array_keys(get_object_vars($this)) as $key) {
            if (array_key_exists($key, $args)) {
                // Skip readonly properties that are already set by promotion.
                if ('manager' === $key || 'id' === $key) {
                    continue;
                }
                $this->$key = $args[$key];
            }
        }

        // Users cannot customize the $controls array via constructor.
        $this->controls = [];
    }

    /**
     * Checks required user capabilities and whether the theme has the
     * feature support required by the section.
     *
     * @since 3.4.0
     *
     * @return bool False if theme doesn't support the section or user doesn't have the capability.
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
     * Check capabilities and render the section.
     *
     * @since 3.4.0
     */
    public final function maybe_render(): void
    {
        if (!$this->check_capabilities()) {
            return;
        }

        /**
         * Fires before rendering a Customizer section.
         *
         * @since 3.4.0
         *
         * @param WP_Customize_Section $this WP_Customize_Section instance.
         */
        do_action('customize_render_section', $this);
        /**
         * Fires before rendering a specific Customizer section.
         *
         * The dynamic portion of the hook name, $this->id, refers to the ID
         * of the specific Customizer section to be rendered.
         *
         * @since 3.4.0
         */
        do_action("customize_render_section_{$this->id}");

        $this->render();
    }

    /**
     * Render the section, and the controls that have been added to it.
     *
     * @since 3.4.0
     */
    protected function render(): void
    {
        $classes = 'control-section accordion-section';
        if ($this->panel) {
            $classes .= ' control-subsection';
        }
        ?>
        <li id="accordion-section-<?= esc_attr($this->id) ?>" class="<?= esc_attr($classes) ?>">
            <h3 class="accordion-section-title" tabindex="0">
                <?= esc_html($this->title) ?>
                <span class="screen-reader-text"><?php _e('Press return or enter to expand'); ?></span>
            </h3>
            <ul class="accordion-section-content">
                <?php if ($this->description) : ?>
                    <li><p class="description customize-section-description"><?= $this->description ?></p></li>
                <?php endif; ?>
                <?php foreach ($this->controls as $control) : ?>
                    <?php $control->maybe_render(); ?>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php
    }
}
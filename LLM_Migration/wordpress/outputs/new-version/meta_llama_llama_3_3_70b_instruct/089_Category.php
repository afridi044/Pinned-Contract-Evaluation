<?php
/**
 * Manages all category-related data
 *
 * Used by {@see SimplePie_Item::get_category()} and {@see SimplePie_Item::get_categories()}
 *
 * This class can be overloaded with {@see SimplePie::set_category_class()}
 *
 * @package SimplePie
 * @subpackage API
 */
class SimplePie_Category
{
    /**
     * Category identifier
     *
     * @var string
     * @see get_term
     */
    private string $term;

    /**
     * Categorization scheme identifier
     *
     * @var string
     * @see get_scheme()
     */
    private string $scheme;

    /**
     * Human readable label
     *
     * @var string
     * @see get_label()
     */
    private string $label;

    /**
     * Constructor, used to input the data
     *
     * @param string|null $term
     * @param string|null $scheme
     * @param string|null $label
     */
    public function __construct(string $term = null, string $scheme = null, string $label = null)
    {
        $this->term = $term;
        $this->scheme = $scheme;
        $this->label = $label;
    }

    /**
     * String-ified version
     *
     * @return string
     */
    public function __toString(): string
    {
        return md5(serialize($this));
    }

    /**
     * Get the category identifier
     *
     * @return string|null
     */
    public function get_term(): ?string
    {
        return $this->term;
    }

    /**
     * Get the categorization scheme identifier
     *
     * @return string|null
     */
    public function get_scheme(): ?string
    {
        return $this->scheme;
    }

    /**
     * Get the human readable label
     *
     * @return string|null
     */
    public function get_label(): ?string
    {
        return $this->label ?? $this->term;
    }
}
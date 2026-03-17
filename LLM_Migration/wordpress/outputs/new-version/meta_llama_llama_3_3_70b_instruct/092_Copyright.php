<?php
/**
 * Manages `<media:copyright>` copyright tags as defined in Media RSS
 *
 * Used by {@see SimplePie_Enclosure::get_copyright()}
 *
 * This class can be overloaded with {@see SimplePie::set_copyright_class()}
 *
 * @package SimplePie
 * @subpackage API
 */
declare(strict_types=1);

class SimplePie_Copyright
{
    /**
     * Copyright URL
     *
     * @var string|null
     * @see get_url()
     */
    private ?string $url;

    /**
     * Attribution
     *
     * @var string|null
     * @see get_attribution()
     */
    private ?string $label;

    /**
     * Constructor, used to input the data
     *
     * For documentation on all the parameters, see the corresponding
     * properties and their accessors
     */
    public function __construct(?string $url = null, ?string $label = null)
    {
        $this->url = $url;
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
     * Get the copyright URL
     *
     * @return string|null URL to copyright information
     */
    public function get_url(): ?string
    {
        return $this->url;
    }

    /**
     * Get the attribution text
     *
     * @return string|null
     */
    public function get_attribution(): ?string
    {
        return $this->label;
    }
}
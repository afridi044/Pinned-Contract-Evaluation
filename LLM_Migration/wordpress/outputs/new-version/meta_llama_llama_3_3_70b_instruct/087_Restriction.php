<?php
/**
 * Handles `<media:restriction>` as defined in Media RSS
 *
 * Used by {@see SimplePie_Enclosure::get_restriction()} and {@see SimplePie_Enclosure::get_restrictions()}
 *
 * This class can be overloaded with {@see SimplePie::set_restriction_class()}
 *
 * @package SimplePie
 * @subpackage API
 */
class SimplePie_Restriction
{
    /**
     * Relationship ('allow'/'deny')
     *
     * @var string
     * @see get_relationship()
     */
    private string $relationship;

    /**
     * Type of restriction
     *
     * @var string
     * @see get_type()
     */
    private string $type;

    /**
     * Restricted values
     *
     * @var string
     * @see get_value()
     */
    private string $value;

    /**
     * Constructor, used to input the data
     *
     * For documentation on all the parameters, see the corresponding
     * properties and their accessors
     */
    public function __construct(string $relationship = null, string $type = null, string $value = null)
    {
        $this->relationship = $relationship;
        $this->type = $type;
        $this->value = $value;
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
     * Get the relationship
     *
     * @return string|null Either 'allow' or 'deny'
     */
    public function get_relationship(): ?string
    {
        return $this->relationship;
    }

    /**
     * Get the type
     *
     * @return string|null
     */
    public function get_type(): ?string
    {
        return $this->type;
    }

    /**
     * Get the list of restricted things
     *
     * @return string|null
     */
    public function get_value(): ?string
    {
        return $this->value;
    }
}
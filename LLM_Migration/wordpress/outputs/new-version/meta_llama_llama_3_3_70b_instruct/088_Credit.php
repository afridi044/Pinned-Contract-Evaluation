<?php
/**
 * Handles `<media:credit>` as defined in Media RSS
 *
 * Used by {@see SimplePie_Enclosure::get_credit()} and {@see SimplePie_Enclosure::get_credits()}
 *
 * This class can be overloaded with {@see SimplePie::set_credit_class()}
 *
 * @package SimplePie
 * @subpackage API
 */
class SimplePie_Credit
{
    /**
     * Credited role
     *
     * @var string
     * @see get_role()
     */
    public string $role;

    /**
     * Organizational scheme
     *
     * @var string
     * @see get_scheme()
     */
    public string $scheme;

    /**
     * Credited name
     *
     * @var string
     * @see get_name()
     */
    public string $name;

    /**
     * Constructor, used to input the data
     *
     * For documentation on all the parameters, see the corresponding
     * properties and their accessors
     */
    public function __construct(string $role = null, string $scheme = null, string $name = null)
    {
        $this->role = $role;
        $this->scheme = $scheme;
        $this->name = $name;
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
     * Get the role of the person receiving credit
     *
     * @return string|null
     */
    public function get_role(): ?string
    {
        return $this->role;
    }

    /**
     * Get the organizational scheme
     *
     * @return string|null
     */
    public function get_scheme(): ?string
    {
        return $this->scheme;
    }

    /**
     * Get the credited person/entity's name
     *
     * @return string|null
     */
    public function get_name(): ?string
    {
        return $this->name;
    }
}
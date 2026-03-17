<?php
/**
 * Manages all author-related data
 *
 * Used by {@see SimplePie_Item::get_author()} and {@see SimplePie::get_authors()}
 *
 * This class can be overloaded with {@see SimplePie::set_author_class()}
 *
 * @package SimplePie
 * @subpackage API
 */
class SimplePie_Author
{
    /**
     * Author's name
     *
     * @var string
     * @see getName()
     */
    private $name;

    /**
     * Author's link
     *
     * @var string
     * @see getLink()
     */
    private $link;

    /**
     * Author's email address
     *
     * @var string
     * @see getEmail()
     */
    private $email;

    /**
     * Constructor, used to input the data
     *
     * @param string|null $name
     * @param string|null $link
     * @param string|null $email
     */
    public function __construct(string $name = null, string $link = null, string $email = null)
    {
        $this->name = $name;
        $this->link = $link;
        $this->email = $email;
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
     * Author's name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Author's link
     *
     * @return string|null
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * Author's email address
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }
}
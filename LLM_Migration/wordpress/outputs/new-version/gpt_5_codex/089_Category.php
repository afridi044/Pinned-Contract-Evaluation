<?php
declare(strict_types=1);

class SimplePie_Category
{
    public ?string $term;
    public ?string $scheme;
    public ?string $label;

    public function __construct(?string $term = null, ?string $scheme = null, ?string $label = null)
    {
        $this->term = $term;
        $this->scheme = $scheme;
        $this->label = $label;
    }

    public function __toString(): string
    {
        return md5(serialize($this));
    }

    public function get_term(): ?string
    {
        return $this->term;
    }

    public function get_scheme(): ?string
    {
        return $this->scheme;
    }

    public function get_label(): ?string
    {
        return $this->label ?? $this->get_term();
    }
}
?>
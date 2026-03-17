<?php
declare(strict_types=1);

class SimplePie_Rating
{
    public function __construct(
        private ?string $scheme = null,
        private ?string $value = null,
    ) {
    }

    public function __toString(): string
    {
        return md5(serialize($this));
    }

    public function get_scheme(): ?string
    {
        return $this->scheme;
    }

    public function get_value(): ?string
    {
        return $this->value;
    }
}
?>
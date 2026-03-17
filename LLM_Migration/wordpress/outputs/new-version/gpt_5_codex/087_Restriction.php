<?php
declare(strict_types=1);

class SimplePie_Restriction
{
    public function __construct(
        public ?string $relationship = null,
        public ?string $type = null,
        public ?string $value = null,
    ) {
    }

    public function __toString(): string
    {
        return md5(serialize($this));
    }

    public function get_relationship(): ?string
    {
        return $this->relationship;
    }

    public function get_type(): ?string
    {
        return $this->type;
    }

    public function get_value(): ?string
    {
        return $this->value;
    }
}
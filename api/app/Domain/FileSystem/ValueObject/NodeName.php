<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\ValueObject;

use App\Domain\FileSystem\Exception\InvalidNodeName;

final readonly class NodeName
{
    public const int MAX_LENGTH = 255;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidNodeName::forField('name', 'A name cannot be blank.');
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            throw InvalidNodeName::forField('name', 'A name cannot exceed '.self::MAX_LENGTH.' characters.');
        }

        if (str_contains($trimmed, '/')) {
            throw InvalidNodeName::forField('name', 'A name cannot contain "/".');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw InvalidNodeName::forField('name', 'A name cannot contain control characters.');
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return mb_strtolower($this->value) === mb_strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

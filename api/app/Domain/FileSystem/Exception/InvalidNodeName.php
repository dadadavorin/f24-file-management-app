<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Exception;

/**
 * Carries the field it relates to so the HTTP layer never hardcodes "name".
 */
final class InvalidNodeName extends FileSystemException
{
    private function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    public static function forField(string $field, string $message): self
    {
        return new self($message, $field);
    }
}

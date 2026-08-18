<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Dto;

/**
 * (sort_rank, lower(name)) — unique within a parent because of the unique
 * index, so no id tiebreaker is needed.
 */
final readonly class Cursor
{
    public function __construct(
        public int $sortRank,
        public string $lowerName,
    ) {}

    public function encode(): string
    {
        return base64_encode(json_encode([
            'sort_rank' => $this->sortRank,
            'lower_name' => $this->lowerName,
        ], JSON_THROW_ON_ERROR));
    }

    public static function decode(string $encoded): self
    {
        $decoded = json_decode(
            base64_decode($encoded, true) ?: '',
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Malformed cursor.');
        }

        $sortRank = $decoded['sort_rank'] ?? null;
        $lowerName = $decoded['lower_name'] ?? null;

        if (! is_int($sortRank) || ! is_string($lowerName)) {
            throw new \InvalidArgumentException('Malformed cursor.');
        }

        return new self(sortRank: $sortRank, lowerName: $lowerName);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Dto;

/**
 * (sort_rank, lower(name), id). The first two are unique within a parent
 * because of the unique index, which is all a children listing needs. Exact-
 * name search reuses this cursor across a *different* result set — every row
 * matching a given name shares its sort_rank (always a file) and lower_name
 * (the searched name) — so id, a total order, is the component that actually
 * discriminates rows there. It is harmless where it isn't needed.
 */
final readonly class Cursor
{
    public function __construct(
        public int $sortRank,
        public string $lowerName,
        public int $id,
    ) {}

    public function encode(): string
    {
        return base64_encode(json_encode([
            'sort_rank' => $this->sortRank,
            'lower_name' => $this->lowerName,
            'id' => $this->id,
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
        $id = $decoded['id'] ?? null;

        if (! is_int($sortRank) || ! is_string($lowerName) || ! is_int($id)) {
            throw new \InvalidArgumentException('Malformed cursor.');
        }

        return new self(sortRank: $sortRank, lowerName: $lowerName, id: $id);
    }
}

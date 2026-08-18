<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\ValueObject;

use App\Domain\FileSystem\Exception\MaxDepthExceeded;

/**
 *         id=1  path='/'        depth=0    ← the one real root row
 *           │
 *           ├── id=7   path='/1/'      depth=1     "Documents"
 *           │     │
 *           │     ├── id=22  path='/1/7/'   depth=2     "Invoices"
 *           │     │     └── id=31  path='/1/7/22/'  depth=3   "march.pdf"
 *           │     │
 *           │     └── id=23  path='/1/7/'   depth=2     "notes.txt"
 *           │
 *           └── id=8   path='/1/'      depth=1     "Photos"
 *
 *   path == the ids of ALL ANCESTORS, in order, slash-delimited, excluding self.
 *
 *   Subtree of node n   ≡   path LIKE (n.path || n.id || '/%')
 *   Subtree of id=7     ≡   path LIKE '/1/7/%'          → 22, 23, 31
 *   Ancestors of id=31  ≡   id IN (1, 7, 22)            → parsed from the string
 *   Depth of node n     ≡   n.depth                     (stored, not computed)
 */
final readonly class NodePath
{
    public const int MAX_DEPTH = 32;

    private function __construct(
        public string $value,
        public int $depth,
    ) {}

    public static function forRoot(): self
    {
        return new self('/', 0);
    }

    /**
     * Reconstructs a path already persisted. Skips the depth check forChild()
     * performs on write, because a stored path was already validated then.
     */
    public static function fromStored(string $value, int $depth): self
    {
        return new self($value, $depth);
    }

    public static function forChild(self $parentPath, int $parentId): self
    {
        $depth = $parentPath->depth + 1;

        if ($depth > self::MAX_DEPTH) {
            throw new MaxDepthExceeded(self::MAX_DEPTH);
        }

        return new self("{$parentPath->value}{$parentId}/", $depth);
    }

    public function subtreePattern(int $selfId): string
    {
        return "{$this->value}{$selfId}/%";
    }

    /**
     * @return int[]
     */
    public function ancestorIds(): array
    {
        $trimmed = trim($this->value, '/');

        if ($trimmed === '') {
            return [];
        }

        return array_map(intval(...), explode('/', $trimmed));
    }
}

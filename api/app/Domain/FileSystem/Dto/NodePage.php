<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Dto;

final readonly class NodePage
{
    /**
     * @param  NodeData[]  $items
     */
    public function __construct(
        public array $items,
        public ?Cursor $nextCursor,
    ) {}
}

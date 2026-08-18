<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Dto;

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodePath;

final readonly class NodeData
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public NodeType $type,
        public string $name,
        public NodePath $path,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}
}

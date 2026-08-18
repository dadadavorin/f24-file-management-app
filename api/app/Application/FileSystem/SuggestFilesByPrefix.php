<?php

declare(strict_types=1);

namespace App\Application\FileSystem;

use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Repository\NodeRepository;

final readonly class SuggestFilesByPrefix
{
    private const int LIMIT = 10;

    public function __construct(private NodeRepository $repository) {}

    /**
     * @return NodeData[]
     */
    public function execute(string $query, ?int $subtreeRootId): array
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return [];
        }

        // Unescaped, a user typing '%' would match every file in the table.
        $escaped = strtr($trimmed, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);

        return $this->repository->suggestFilesByPrefix($escaped, $subtreeRootId, self::LIMIT);
    }
}

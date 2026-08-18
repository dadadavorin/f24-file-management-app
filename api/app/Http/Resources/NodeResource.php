<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Enum\NodeType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NodeResource extends JsonResource
{
    public function __construct(
        private readonly NodeData $node,
        private readonly ?int $childCount = null,
    ) {
        parent::__construct($node);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->node->id,
            'parent_id' => $this->node->parentId,
            'type' => $this->node->type->value,
            'name' => $this->node->name,
            'child_count' => $this->when($this->node->type === NodeType::Folder, $this->childCount),
            'created_at' => $this->node->createdAt->format(DATE_ATOM),
            'updated_at' => $this->node->updatedAt->format(DATE_ATOM),
        ];
    }
}

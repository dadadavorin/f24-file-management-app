<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\FileSystem\CreateNode;
use App\Application\FileSystem\DeleteNode;
use App\Application\FileSystem\ListChildren;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateNodeRequest;
use App\Http\Requests\ListChildrenRequest;
use App\Http\Resources\NodeResource;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class NodeController extends Controller
{
    public function __construct(
        private readonly NodeRepository $repository,
        private readonly ListChildren $listChildren,
        private readonly CreateNode $createNode,
        private readonly DeleteNode $deleteNode,
    ) {}

    public function root(): NodeResource
    {
        return new NodeResource($this->repository->findRoot());
    }

    public function show(int $id): NodeResource
    {
        $node = $this->repository->find($id) ?? throw new NodeNotFound($id);

        return (new NodeResource($node))->additional([
            'breadcrumbs' => NodeResource::collection($this->ancestorsOf($node)),
        ]);
    }

    public function children(int $id, ListChildrenRequest $request): JsonResponse
    {
        $result = $this->listChildren->execute($id, $request->cursor(), $request->limit());

        return response()->json([
            'data' => array_map(
                fn (NodeData $node): array => (new NodeResource($node, $result['childCounts'][$node->id] ?? 0))->resolve(),
                $result['page']->items,
            ),
            'meta' => ['next_cursor' => $result['page']->nextCursor?->encode()],
        ]);
    }

    #[DocumentedResponse(
        422,
        description: '$0 Also returned when the name fails a NodeName business rule — blank, over '
            .NodeName::MAX_LENGTH.' characters, containing "/", or containing a control character — '
            .'which shape validation alone cannot catch.',
    )]
    public function store(CreateNodeRequest $request): JsonResponse
    {
        $node = $this->createNode->execute($request->parentId(), $request->type(), $request->nodeName());

        return (new NodeResource($node))->response()->setStatusCode(201);
    }

    public function destroy(int $id): Response
    {
        $this->deleteNode->execute($id);

        return response()->noContent();
    }

    /**
     * Breadcrumbs come from parsing path, not from walking the tree — one
     * whereIn over the ancestor ids, then reordered to match the path.
     *
     * @return NodeData[]
     */
    private function ancestorsOf(NodeData $node): array
    {
        $byId = [];

        foreach ($this->repository->findByIds($node->path->ancestorIds()) as $ancestor) {
            $byId[$ancestor->id] = $ancestor;
        }

        return array_values(array_filter(array_map(
            static fn (int $ancestorId): ?NodeData => $byId[$ancestorId] ?? null,
            $node->path->ancestorIds(),
        )));
    }
}

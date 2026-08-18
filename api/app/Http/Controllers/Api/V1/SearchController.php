<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\FileSystem\FindFilesByExactName;
use App\Application\FileSystem\SuggestFilesByPrefix;
use App\Domain\FileSystem\Dto\NodeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\NodeResource;
use Illuminate\Http\JsonResponse;

final class SearchController extends Controller
{
    public function __construct(
        private readonly FindFilesByExactName $findFilesByExactName,
        private readonly SuggestFilesByPrefix $suggestFilesByPrefix,
    ) {}

    public function files(SearchRequest $request): JsonResponse
    {
        $result = $this->findFilesByExactName->execute(
            $request->exactName(),
            $request->subtreeRootId(),
            $request->cursor(),
            $request->limit(),
        );

        return response()->json([
            'data' => array_map(
                fn (NodeData $file): array => [
                    ...(new NodeResource($file))->resolve(),
                    'folder' => $this->folderLabel($file, $result['folders']),
                ],
                $result['page']->items,
            ),
            'meta' => ['next_cursor' => $result['page']->nextCursor?->encode()],
        ]);
    }

    public function suggestions(SearchRequest $request): JsonResponse
    {
        $files = $this->suggestFilesByPrefix->execute($request->prefixQuery(), $request->subtreeRootId());

        return response()->json([
            'data' => NodeResource::collection($files)->resolve(),
        ]);
    }

    /**
     * @param  array<int, NodeData>  $folders
     * @return array<array-key, mixed>|null
     */
    private function folderLabel(NodeData $file, array $folders): ?array
    {
        if ($file->parentId === null) {
            return null;
        }

        $folder = $folders[$file->parentId] ?? null;

        return $folder === null ? null : (new NodeResource($folder))->resolve();
    }
}

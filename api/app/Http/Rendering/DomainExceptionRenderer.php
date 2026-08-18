<?php

declare(strict_types=1);

namespace App\Http\Rendering;

use App\Domain\FileSystem\Exception\DuplicateNodeName;
use App\Domain\FileSystem\Exception\FileSystemException;
use App\Domain\FileSystem\Exception\InvalidNodeName;
use App\Domain\FileSystem\Exception\MaxDepthExceeded;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\ParentIsNotAFolder;
use App\Domain\FileSystem\Exception\RootIsImmutable;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;

/**
 * The single place mapping every domain exception to HTTP. InvalidNodeName
 * renders as Laravel's native 422 field-error shape (ADR-0004 part 1);
 * every other FileSystemException renders as RFC 9457 Problem Details
 * (ADR-0004 part 2).
 */
final class DomainExceptionRenderer
{
    private const string PROBLEM_TYPE_BASE = 'https://f24-file-management-app.example/problems/';

    /**
     * @var array<class-string<FileSystemException>, array{status: int, code: string, title: string}>
     */
    public const array PROBLEM_MAP = [
        NodeNotFound::class => ['status' => 404, 'code' => 'NODE_NOT_FOUND', 'title' => 'Node not found'],
        DuplicateNodeName::class => ['status' => 409, 'code' => 'DUPLICATE_NODE_NAME', 'title' => 'Duplicate name'],
        ParentIsNotAFolder::class => ['status' => 422, 'code' => 'PARENT_IS_NOT_A_FOLDER', 'title' => 'Parent is not a folder'],
        RootIsImmutable::class => ['status' => 422, 'code' => 'ROOT_IS_IMMUTABLE', 'title' => 'Root is immutable'],
        MaxDepthExceeded::class => ['status' => 422, 'code' => 'MAX_DEPTH_EXCEEDED', 'title' => 'Maximum depth exceeded'],
    ];

    public function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (FileSystemException $exception): JsonResponse {
            if ($exception instanceof InvalidNodeName) {
                return $this->renderFieldError($exception);
            }

            return $this->renderProblem($exception);
        });
    }

    private function renderFieldError(InvalidNodeName $exception): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                $exception->field => [$exception->getMessage()],
            ],
        ], 422);
    }

    private function renderProblem(FileSystemException $exception): JsonResponse
    {
        $entry = self::PROBLEM_MAP[$exception::class]
            ?? throw new \LogicException('Unmapped domain exception: '.$exception::class);

        return response()->json([
            'type' => self::PROBLEM_TYPE_BASE.strtolower(str_replace('_', '-', $entry['code'])),
            'title' => $entry['title'],
            'status' => $entry['status'],
            'detail' => $exception->getMessage(),
            'code' => $entry['code'],
        ], $entry['status'])->header('Content-Type', 'application/problem+json');
    }
}

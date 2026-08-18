<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\FileSystem\Dto\Cursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Backs both search endpoints. `name` (exact search) and `q` (suggestions)
 * are each validated as plain optional strings — blank/invalid values are
 * NodeName's job, not this class's, exactly as for CreateNodeRequest.
 */
final class SearchRequest extends FormRequest
{
    private const int DEFAULT_LIMIT = 50;

    private const int MAX_LIMIT = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string'],
            'q' => ['sometimes', 'nullable', 'string'],
            'scope' => ['sometimes', 'nullable', Rule::in(['subtree', 'all'])],
            'parent_id' => ['required_if:scope,subtree', 'nullable', 'integer'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    public function exactName(): string
    {
        return $this->string('name')->toString();
    }

    public function prefixQuery(): string
    {
        return $this->string('q')->toString();
    }

    public function subtreeRootId(): ?int
    {
        return $this->string('scope')->toString() === 'subtree' ? $this->integer('parent_id') : null;
    }

    public function cursor(): ?Cursor
    {
        return $this->filled('cursor') ? Cursor::decode($this->string('cursor')->toString()) : null;
    }

    public function limit(): int
    {
        return $this->integer('limit', self::DEFAULT_LIMIT);
    }
}

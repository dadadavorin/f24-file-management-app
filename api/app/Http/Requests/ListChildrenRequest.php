<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\FileSystem\Dto\Cursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListChildrenRequest extends FormRequest
{
    private const int DEFAULT_LIMIT = 50;

    private const int MAX_LIMIT = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
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

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\FileSystem\Enum\NodeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape only — parent_id is an integer, type is one of the enum values, name
 * is a string. Every business rule about what makes a name valid lives in
 * NodeName, not here.
 */
final class CreateNodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer'],
            'type' => ['required', 'string', Rule::in(array_column(NodeType::cases(), 'value'))],
            'name' => ['required', 'string'],
        ];
    }

    public function parentId(): int
    {
        return $this->integer('parent_id');
    }

    public function type(): NodeType
    {
        return NodeType::from($this->string('type')->toString());
    }

    public function nodeName(): string
    {
        return $this->string('name')->toString();
    }
}

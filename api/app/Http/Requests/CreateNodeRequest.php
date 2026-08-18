<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shape only — parent_id is an integer, type is one of the enum values, name
 * is a string. Every business rule about what makes a name valid lives in
 * NodeName, not here.
 *
 * The rules() below can't express NodeName's constraints (ADR-0004 part 1),
 * so Scramble can't infer them either — the BodyParameter attribute documents
 * them explicitly. maxLength is added separately by NameLengthConstraintExtension,
 * reading NodeName::MAX_LENGTH directly so it cannot drift from this text.
 */
#[BodyParameter(
    'name',
    description: 'Trimmed before validation. Must be non-blank, at most '.NodeName::MAX_LENGTH.' characters, '
        .'and must not contain "/" or control characters. An invalid value returns 422 with errors.name.',
    type: 'string',
    required: true,
)]
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

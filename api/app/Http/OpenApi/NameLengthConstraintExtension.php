<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

use App\Domain\FileSystem\ValueObject\NodeName;
use App\Http\Controllers\Api\V1\NodeController;
use App\Http\Requests\CreateNodeRequest;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Scramble has no rule to read a maxLength from — CreateNodeRequest's rules()
 * are shape-only, and the BodyParameter attribute's `type` string can't carry
 * a length constraint. This sets it directly on the generated schema from
 * NodeName::MAX_LENGTH after RequestBodyExtension has built it, so the
 * documented value is read from the constant rather than duplicated.
 */
final class NameLengthConstraintExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if ($routeInfo->className() !== NodeController::class || $routeInfo->methodName() !== 'store') {
            return;
        }

        $components = $this->openApiTransformer->getComponents();

        if (! $components->hasSchema(CreateNodeRequest::class)) {
            return;
        }

        $schema = $components->getSchema(CreateNodeRequest::class);

        if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType) {
            return;
        }

        $nameProperty = $schema->type->getProperty('name');

        if ($nameProperty instanceof StringType) {
            $nameProperty->setMax(NodeName::MAX_LENGTH);
        }
    }
}

<?php

declare(strict_types=1);

use App\Domain\FileSystem\ValueObject\NodeName;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;

test('the documented name maxLength matches NodeName::MAX_LENGTH', function () {
    $document = app(Generator::class)(Scramble::getGeneratorConfig('default'));

    $nameSchema = $document['components']['schemas']['CreateNodeRequest']['properties']['name'];

    expect($nameSchema['maxLength'] ?? null)->toBe(NodeName::MAX_LENGTH);
});

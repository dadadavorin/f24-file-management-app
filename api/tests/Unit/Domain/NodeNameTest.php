<?php

declare(strict_types=1);

use App\Domain\FileSystem\Exception\InvalidNodeName;
use App\Domain\FileSystem\ValueObject\NodeName;

test('trims leading and trailing whitespace', function () {
    expect(NodeName::fromString('  Invoices  ')->value)->toBe('Invoices');
});

test('rejects a blank name', function () {
    NodeName::fromString('');
})->throws(InvalidNodeName::class);

test('rejects a whitespace-only name', function () {
    NodeName::fromString('   ');
})->throws(InvalidNodeName::class);

test('accepts a name at the maximum length', function () {
    $name = str_repeat('a', NodeName::MAX_LENGTH);

    expect(NodeName::fromString($name)->value)->toHaveLength(NodeName::MAX_LENGTH);
});

test('rejects a name longer than the maximum length', function () {
    NodeName::fromString(str_repeat('a', NodeName::MAX_LENGTH + 1));
})->throws(InvalidNodeName::class);

test('rejects a name containing a slash', function () {
    NodeName::fromString('march/2026.pdf');
})->throws(InvalidNodeName::class);

test('rejects a name containing a control character', function () {
    NodeName::fromString("march\n2026.pdf");
})->throws(InvalidNodeName::class);

test('carries the field name on failure so the renderer never hardcodes it', function () {
    try {
        NodeName::fromString('');
    } catch (InvalidNodeName $exception) {
        expect($exception->field)->toBe('name');
    }
});

test('treats names as equal case-insensitively', function () {
    $a = NodeName::fromString('Invoices');
    $b = NodeName::fromString('invoices');
    $c = NodeName::fromString('Photos');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});

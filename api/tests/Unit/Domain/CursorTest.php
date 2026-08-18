<?php

declare(strict_types=1);

use App\Domain\FileSystem\Dto\Cursor;

test('a cursor round-trips through encoding', function () {
    $cursor = new Cursor(sortRank: 1, lowerName: 'invoice.pdf', id: 42);

    $decoded = Cursor::decode($cursor->encode());

    expect($decoded->sortRank)->toBe(1)
        ->and($decoded->lowerName)->toBe('invoice.pdf')
        ->and($decoded->id)->toBe(42);
});

test('decoding a cursor that is not a JSON object throws', function () {
    Cursor::decode(base64_encode(json_encode(['just', 'an', 'array'])));
})->throws(InvalidArgumentException::class);

test('decoding a cursor missing the id throws', function () {
    $encoded = base64_encode(json_encode(['sort_rank' => 1, 'lower_name' => 'x']));

    Cursor::decode($encoded);
})->throws(InvalidArgumentException::class);

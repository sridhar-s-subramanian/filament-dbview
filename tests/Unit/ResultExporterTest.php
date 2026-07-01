<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exports\ResultExporter;
use SridharSSubramanian\FilamentDbview\Support\ResultSet;

function sampleResult(): ResultSet
{
    return new ResultSet(
        columns: ['id', 'title'],
        rows: [
            ['id' => 1, 'title' => 'Hello'],
            ['id' => 2, 'title' => 'Wor,ld'],
        ],
        rowCount: 2,
        truncated: false,
        durationMs: 1.0,
        connection: 'testing',
    );
}

function capture(\Symfony\Component\HttpFoundation\StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('exports a CSV with a header row and escaped values', function (): void {
    $csv = capture(ResultExporter::csv(sampleResult()));

    expect($csv)->toContain('id,title')
        ->and($csv)->toContain('1,Hello')
        ->and($csv)->toContain('"Wor,ld"');
});

it('exports JSON of the rows', function (): void {
    $json = capture(ResultExporter::json(sampleResult()));

    expect(json_decode($json, true))->toBe([
        ['id' => 1, 'title' => 'Hello'],
        ['id' => 2, 'title' => 'Wor,ld'],
    ]);
});

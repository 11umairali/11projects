<?php

use Illuminate\Foundation\Vite;

function roomViewWithOptionalRealtime(): string
{
    return view('meetings.room', [
        'meeting' => ['title' => 'Test meeting', 'code' => 'test', 'pretty' => 'test',
            'url' => '/meet/test', 'kind' => 'meeting', 'host_name' => 'Test host'],
        'ended' => false, 'allowGuests' => true, 'user' => null,
    ])->render();
}

test('room remains usable without optional realtime assets', function (string $exception) {
    $vite = Mockery::mock(Vite::class);
    $vite->shouldReceive('__invoke')->with('resources/js/realtime.js')->andThrow(new $exception('Missing realtime assets'));
    app()->instance(Vite::class, $vite);
    expect(roomViewWithOptionalRealtime())->toContain('id="mt"', '/js/meeting.js');
})->with([Illuminate\Foundation\ViteException::class, Illuminate\Foundation\ViteManifestNotFoundException::class]);

test('room loads realtime when the build is present', function () {
    $vite = Mockery::mock(Vite::class);
    $vite->shouldReceive('__invoke')->with('resources/js/realtime.js')->andReturn(new Illuminate\Support\HtmlString('<script type="module" src="/build/realtime.js"></script>'));
    app()->instance(Vite::class, $vite);
    expect(roomViewWithOptionalRealtime())->toContain('/build/realtime.js', '/js/meeting.js');
});


test('shared brand component renders the canonical tile for light and dark layouts', function () {
    $html = \Illuminate\Support\Facades\Blade::render('<x-brand-logo name="Dahimail" />');
    expect($html)->toContain('images/dahimail-logo.png')->toContain('Dahimail')->toContain('rounded-lg');
});

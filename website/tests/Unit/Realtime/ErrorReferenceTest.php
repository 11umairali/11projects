<?php

use App\Support\ErrorReference;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

test('the displayed failure reference matches the logged exception and changes per request', function () {
    config(['app.debug' => false]);
    $this->withoutVite();
    $request = Request::create('/people', 'GET');
    app()->instance('request', $request);
    Log::spy();
    $exception = new RuntimeException('Synthetic People failure');
    $handler = app(ExceptionHandler::class);
    $handler->report($exception);
    $reference = ErrorReference::current();
    Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Synthetic People failure' && $context['error_reference'] === $reference)->once();
    $response = $handler->render($request, $exception);
    expect($response->getStatusCode())->toBe(500)->and($response->getContent())->toContain($reference)->not->toContain('Synthetic People failure');
    app()->instance('request', Request::create('/people'));
    expect(ErrorReference::current())->not->toBe($reference);
});

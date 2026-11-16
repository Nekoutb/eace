<?php

declare(strict_types=1);

use App\Services\Acf\ClaudeAcfOcr;
use App\Services\Anthropic\AnthropicError;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/*
 * A rejected Anthropic call must log WHY it was rejected, not just "HTTP 400" —
 * production had six unexplained ACF scan failures because only the code was kept.
 */

function anthropicResponse(int $status, mixed $body, array $headers = []): Response
{
    Http::fake(['*' => Http::response($body, $status, $headers)]);

    return Http::post('https://api.anthropic.com/v1/messages');
}

it('includes the error type, message and request id', function () {
    $response = anthropicResponse(400, [
        'type' => 'error',
        'error' => ['type' => 'invalid_request_error', 'message' => 'The PDF specified was not valid.'],
    ], ['request-id' => 'req_123']);

    expect(AnthropicError::describe($response))
        ->toBe('HTTP 400 (invalid_request_error: The PDF specified was not valid.) [request-id req_123]');
});

it('falls back to the status code when the body is not an Anthropic error', function () {
    expect(AnthropicError::describe(anthropicResponse(502, '<html>Bad gateway</html>')))
        ->toBe('HTTP 502');
});

it('truncates an over-long message', function () {
    $response = anthropicResponse(400, ['error' => ['type' => 'invalid_request_error', 'message' => str_repeat('x', 1000)]]);

    expect(mb_strlen(AnthropicError::describe($response)))->toBeLessThan(340);
});

it('reports the reason when a scanned ACF is rejected', function () {
    Exceptions::fake();
    Http::fake(['api.anthropic.com/*' => Http::response([
        'type' => 'error',
        'error' => ['type' => 'invalid_request_error', 'message' => 'Image exceeds 5 MB maximum.'],
    ], 400)]);

    $extract = (new ClaudeAcfOcr('test-key', 'claude-sonnet-4-6'))->read('bytes', 'image/jpeg');

    expect($extract->isUsable())->toBeFalse();
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage()
        === 'Claude ACF extraction failed: HTTP 400 (invalid_request_error: Image exceeds 5 MB maximum.)');
});

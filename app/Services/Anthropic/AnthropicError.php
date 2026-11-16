<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use Illuminate\Http\Client\Response;

/**
 * Turns a failed Anthropic Messages API response into a one-line reason for the
 * log, e.g. "HTTP 400 (invalid_request_error: The PDF specified was not valid.)".
 * Without it a rejected scan only logged "HTTP 400" and the cause was lost.
 * Anthropic's error text describes the request, never the document's content.
 */
final class AnthropicError
{
    private const MAX_MESSAGE = 300;

    public static function describe(Response $response): string
    {
        $summary = 'HTTP '.$response->status();

        $type = $response->json('error.type');
        $message = $response->json('error.message');
        $requestId = $response->header('request-id');

        $parts = [];
        if (is_string($type) && $type !== '') {
            $parts[] = $type;
        }
        if (is_string($message) && trim($message) !== '') {
            $parts[] = mb_strimwidth(trim($message), 0, self::MAX_MESSAGE, '…');
        }
        if ($parts !== []) {
            $summary .= ' ('.implode(': ', $parts).')';
        }
        if ($requestId !== '') {
            $summary .= ' [request-id '.$requestId.']';
        }

        return $summary;
    }
}

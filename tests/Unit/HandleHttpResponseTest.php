<?php

namespace ClarionApp\HttpQueue\Tests\Unit;

use ClarionApp\HttpQueue\Tests\TestCase;
use ClarionApp\HttpQueue\HandleHttpResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Psr7\Response as GuzzleResponse;

class HandleHttpResponseTest extends TestCase
{
    private function makeResponse(string $body, int $status = 200): Response
    {
        $guzzleResponse = new GuzzleResponse($status, [], $body);
        return new Response($guzzleResponse);
    }

    /** T037: HandleHttpResponse logs status code */
    public function test_logs_status_code(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::on(function ($message) {
                return str_contains($message, '200');
            }));

        $handler = new HandleHttpResponse();
        $response = $this->makeResponse('OK');
        $handler->handle($response, null, 1);
    }

    /** T038: HandleHttpResponse truncates response body to 200 characters */
    public function test_truncates_long_response_body(): void
    {
        $longBody = str_repeat('A', 500);

        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::on(function ($message) use ($longBody) {
                // Should NOT contain the full 500-char body
                $this->assertLessThanOrEqual(250, strlen($message));
                // Should contain the first 200 chars
                $this->assertStringContainsString(str_repeat('A', 200), $message);
                return true;
            }));

        $handler = new HandleHttpResponse();
        $response = $this->makeResponse($longBody);
        $handler->handle($response, null, 1);
    }

    /** T039: HandleHttpResponse logs short response body without truncation */
    public function test_logs_short_body_without_truncation(): void
    {
        $shortBody = 'Short response';

        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::on(function ($message) use ($shortBody) {
                return str_contains($message, $shortBody);
            }));

        $handler = new HandleHttpResponse();
        $response = $this->makeResponse($shortBody);
        $handler->handle($response, null, 1);
    }
}

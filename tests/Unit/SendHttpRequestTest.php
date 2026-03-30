<?php

namespace ClarionApp\HttpQueue\Tests\Unit;

use ClarionApp\HttpQueue\Tests\TestCase;
use ClarionApp\HttpQueue\HttpRequest;
use ClarionApp\HttpQueue\HandleHttpResponse;
use ClarionApp\HttpQueue\Jobs\SendHttpRequest;
use Illuminate\Support\Facades\Http;

class ValidCallbackForTest extends HandleHttpResponse
{
    public function handle(\Illuminate\Http\Client\Response $response, $data, $seconds)
    {
        // Valid subclass — does nothing special
    }
}

class InvalidCallbackForTest
{
    // Not a subclass of HandleHttpResponse
}

class SendHttpRequestTest extends TestCase
{
    /** T005: Valid HandleHttpResponse subclass callback is accepted */
    public function test_valid_subclass_callback_is_accepted(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 30;

        $job = new SendHttpRequest($request, ValidCallbackForTest::class);
        // Should not throw
        $job->handle();
        $this->assertTrue(true);
    }

    /** T006: Invalid callback class (not a subclass) throws \InvalidArgumentException */
    public function test_invalid_callback_throws_exception(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 30;

        $this->expectException(\InvalidArgumentException::class);

        $job = new SendHttpRequest($request, InvalidCallbackForTest::class);
        $job->handle();
    }

    /** T007: Non-existent callback class throws \InvalidArgumentException */
    public function test_nonexistent_callback_throws_exception(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 30;

        $this->expectException(\InvalidArgumentException::class);

        $job = new SendHttpRequest($request, 'NonExistent\\ClassName');
        $job->handle();
    }

    /** T008: Null callback falls back to default HandleHttpResponse */
    public function test_null_callback_uses_default(): void
    {
        Http::fake(['*' => Http::response('OK', 200)]);

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 30;

        $job = new SendHttpRequest($request, null);
        // Should not throw — falls back to HandleHttpResponse
        $job->handle();
        $this->assertTrue(true);
    }
}

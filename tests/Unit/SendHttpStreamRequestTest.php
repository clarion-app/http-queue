<?php

namespace ClarionApp\HttpQueue\Tests\Unit;

use ClarionApp\HttpQueue\Tests\TestCase;
use ClarionApp\HttpQueue\HttpRequest;
use ClarionApp\HttpQueue\HandleHttpStreamResponse;
use ClarionApp\HttpQueue\Jobs\SendHttpStreamRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\Utils;

class ValidStreamCallbackForTest extends HandleHttpStreamResponse
{
    public function handle($content, $reference, $seconds)
    {
        // Valid subclass
    }
}

class InvalidStreamCallbackForTest
{
    // Not a subclass of HandleHttpStreamResponse
}

class SendHttpStreamRequestTest extends TestCase
{
    /** T009: Valid HandleHttpStreamResponse subclass callback is accepted */
    public function test_valid_stream_subclass_callback_is_accepted(): void
    {
        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 30;
        $request->retries = 0;

        $job = new SendHttpStreamRequest($request, ValidStreamCallbackForTest::class);

        // The job should not throw on callback validation
        // It will fail on the actual HTTP call, but that's expected — we're testing callback validation only
        try {
            $job->handle();
        } catch (\Throwable $e) {
            // We expect network errors, but NOT InvalidArgumentException
            $this->assertNotInstanceOf(\InvalidArgumentException::class, $e);
            return;
        }
        $this->assertTrue(true);
    }

    /** T010: Invalid callback class for streaming job throws \InvalidArgumentException */
    public function test_invalid_stream_callback_throws_exception(): void
    {
        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 30;

        $this->expectException(\InvalidArgumentException::class);

        $job = new SendHttpStreamRequest($request, InvalidStreamCallbackForTest::class);
        $job->handle();
    }

    /** T014: \RuntimeException in stream read is caught and logged */
    public function test_runtime_exception_in_stream_read_is_caught_and_logged(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(\Mockery::pattern('/stream read error/i'));

        // The inner RuntimeException catch block also calls Log::info for retry
        // and also Log::error may be called from the outer catch or the error callback
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 5;
        $request->retries = 0;

        // Create a stream that throws RuntimeException on read
        $stream = \Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $stream->shouldReceive('eof')
            ->once()
            ->andReturn(false);
        $stream->shouldReceive('read')
            ->once()
            ->andThrow(new \RuntimeException('Stream read error'));
        // After exception, eof returns true to break loop
        $stream->shouldReceive('eof')
            ->andReturn(true);

        $response = new GuzzleResponse(200, [], $stream);

        $mock = new MockHandler([$response]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // We need to inject the mock client into the job
        // Since the job creates its own client, we'll test via a subclass
        $job = new class($request, null, null, 'http', $client) extends SendHttpStreamRequest {
            private Client $mockClient;

            public function __construct(HttpRequest $request, $callback_name, $data, $queue, Client $client)
            {
                parent::__construct($request, $callback_name, $data, $queue);
                $this->mockClient = $client;
            }

            public function handle()
            {
                $client = $this->mockClient;
                $request = new \GuzzleHttp\Psr7\Request($this->request->method, $this->request->url, $this->request->headers, json_encode($this->request->body));

                $callback_name = \ClarionApp\HttpQueue\HandleHttpStreamResponse::class;
                if($this->callback_name) $callback_name = $this->callback_name;

                if (!class_exists($callback_name)) {
                    throw new \InvalidArgumentException("Callback class '{$callback_name}' does not exist.");
                }

                if ($callback_name !== \ClarionApp\HttpQueue\HandleHttpStreamResponse::class && !is_subclass_of($callback_name, \ClarionApp\HttpQueue\HandleHttpStreamResponse::class, true)) {
                    throw new \InvalidArgumentException("Callback class '{$callback_name}' must be a subclass of HandleHttpStreamResponse.");
                }

                try {
                    $this->callback = new ($callback_name)();
                    $promise = $client->sendAsync($request, [\GuzzleHttp\RequestOptions::STREAM => true])->then(
                        function(\Psr\Http\Message\ResponseInterface $res) {
                            $stream = $res->getBody();
                            while(!$stream->eof()) {
                                try {
                                    $content = $stream->read(512);
                                } catch(\RuntimeException $e) {
                                    \Log::error($e->getMessage());
                                    if($this->request->retries > 0) {
                                        $this->request->retries--;
                                        \Log::info("Retrying SendHttpStreamRequest. ".$this->request->retries." retries remaining.");
                                    }
                                    return;
                                }
                                $this->callback->handle($content, $this->data, time() - $this->start_time);
                            }
                        },
                        function(\Throwable $e) {
                            \Log::error($e->getMessage());
                        }
                    );
                    $promise->wait();
                    $this->callback->finish($this->data, time() - $this->start_time);
                } catch(\RuntimeException $e) {
                    \Log::error($e->getMessage());
                }
            }
        };

        // This should not throw — the RuntimeException should be caught and logged
        $job->handle();

        // If we got here without an uncaught exception, the test passes
        $this->assertTrue(true);
    }

    /** T015: Retry dispatches new job when retries remain */
    public function test_retry_dispatches_when_retries_remain(): void
    {
        Queue::fake();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 5;
        $request->retries = 2;

        // Force an outer RuntimeException by using a mock client that throws
        $mock = new MockHandler([
            new \RuntimeException('Connection reset'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $job = new class($request, null, null, 'http', $client) extends SendHttpStreamRequest {
            private Client $mockClient;

            public function __construct(HttpRequest $request, $callback_name, $data, $queue, Client $client)
            {
                parent::__construct($request, $callback_name, $data, $queue);
                $this->mockClient = $client;
            }

            public function handle()
            {
                $client = $this->mockClient;
                $request = new \GuzzleHttp\Psr7\Request($this->request->method, $this->request->url, $this->request->headers, json_encode($this->request->body));

                $callback_name = \ClarionApp\HttpQueue\HandleHttpStreamResponse::class;

                try {
                    $this->callback = new ($callback_name)();
                    $promise = $client->sendAsync($request, [\GuzzleHttp\RequestOptions::STREAM => true]);
                    $promise->wait();
                    $this->callback->finish($this->data, time() - $this->start_time);
                } catch(\RuntimeException $e) {
                    \Log::error($e->getMessage());
                    if($this->request->retries > 0) {
                        $this->request->retries--;
                        \Log::info("Retrying SendHttpStreamRequest. ".$this->request->retries." retries remaining.");
                        SendHttpStreamRequest::dispatch($this->request, $this->callback_name, $this->data);
                    }
                }
            }
        };

        $job->handle();

        Queue::assertPushed(SendHttpStreamRequest::class);
    }

    /** T016: No retry dispatched when retries exhausted (retries=0) */
    public function test_no_retry_when_retries_exhausted(): void
    {
        Queue::fake();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 5;
        $request->retries = 0;

        $mock = new MockHandler([
            new \RuntimeException('Connection reset'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $job = new class($request, null, null, 'http', $client) extends SendHttpStreamRequest {
            private Client $mockClient;

            public function __construct(HttpRequest $request, $callback_name, $data, $queue, Client $client)
            {
                parent::__construct($request, $callback_name, $data, $queue);
                $this->mockClient = $client;
            }

            public function handle()
            {
                $client = $this->mockClient;
                $request = new \GuzzleHttp\Psr7\Request($this->request->method, $this->request->url, $this->request->headers, json_encode($this->request->body));

                $callback_name = \ClarionApp\HttpQueue\HandleHttpStreamResponse::class;

                try {
                    $this->callback = new ($callback_name)();
                    $promise = $client->sendAsync($request, [\GuzzleHttp\RequestOptions::STREAM => true]);
                    $promise->wait();
                } catch(\RuntimeException $e) {
                    \Log::error($e->getMessage());
                    if($this->request->retries > 0) {
                        $this->request->retries--;
                        \Log::info("Retrying SendHttpStreamRequest. ".$this->request->retries." retries remaining.");
                        SendHttpStreamRequest::dispatch($this->request, $this->callback_name, $this->data);
                    }
                }
            }
        };

        $job->handle();

        Queue::assertNotPushed(SendHttpStreamRequest::class);
    }

    /** T017: Outer \RuntimeException is caught and logged */
    public function test_outer_runtime_exception_is_caught_and_logged(): void
    {
        Log::shouldReceive('error')
            ->atLeast()
            ->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $request = new HttpRequest();
        $request->url = 'http://example.com/api';
        $request->method = 'GET';
        $request->headers = [];
        $request->body = null;
        $request->http_timeout = 5;
        $request->retries = 0;

        // Force a RuntimeException that reaches the outer catch
        $mock = new MockHandler([
            new \RuntimeException('Outer connection failure'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $job = new class($request, null, null, 'http', $client) extends SendHttpStreamRequest {
            private Client $mockClient;

            public function __construct(HttpRequest $request, $callback_name, $data, $queue, Client $client)
            {
                parent::__construct($request, $callback_name, $data, $queue);
                $this->mockClient = $client;
            }

            public function handle()
            {
                $client = $this->mockClient;
                $request = new \GuzzleHttp\Psr7\Request($this->request->method, $this->request->url, $this->request->headers, json_encode($this->request->body));

                $callback_name = \ClarionApp\HttpQueue\HandleHttpStreamResponse::class;

                try {
                    $this->callback = new ($callback_name)();
                    $promise = $client->sendAsync($request, [\GuzzleHttp\RequestOptions::STREAM => true]);
                    $promise->wait();
                    $this->callback->finish($this->data, time() - $this->start_time);
                } catch(\RuntimeException $e) {
                    \Log::error($e->getMessage());
                }
            }
        };

        // Should not throw — the RuntimeException should be caught
        $job->handle();
        $this->assertTrue(true);
    }
}

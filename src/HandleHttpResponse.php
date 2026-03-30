<?php

namespace ClarionApp\HttpQueue;
use Illuminate\Http\Client\Response;

class HandleHttpResponse
{
    public function handle(Response $response, $data, $seconds)
    {
        $body = $response->body();
        $truncated = strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body;
        \Log::info("HTTP {$response->status()}: {$truncated}");
    }
}

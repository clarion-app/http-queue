<?php

namespace ClarionApp\HttpQueue;
use Illuminate\Http\Client\Response;

class HandleHttpResponse
{
    public function handle(Response $response, $data, $seconds)
    {
        \Log::info(print_r($response->body(), 1));
    }
}

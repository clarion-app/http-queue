<?php

namespace ClarionApp\HttpQueue;

use Illuminate\Http\Client\Response;
use ClarionApp\HttpQueue\HandleHttpResponse;
use Illuminate\Support\Facades\Log;

class HandleHttpStreamResponse extends HandleHttpResponse
{
    public string $buffer = "";

    public function handle($content, $reference, $seconds)
    {
        $this->buffer .= $content;
    }

    public function finish($reference)
    {
    }
}

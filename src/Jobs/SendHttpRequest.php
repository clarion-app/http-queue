<?php

namespace ClarionApp\HttpQueue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use ClarionApp\HttpQueue\HttpRequest;
use ClarionApp\HttpQueue\HandleHttpResponse;

class SendHttpRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HttpRequest $request;
    protected $callback;
    protected $data;

    public function __construct(HttpRequest $request, $callback = null, $data = null)
    {
        $this->onQueue('http');

        $this->request = $request;
        $this->callback = $callback;
        $this->data = $data;
    }

    public function handle()
    {
        $start_time = time();
        $response = Http::timeout($this->request->http_timeout);
        if(count($this->request->headers))
        {
            $response = $response->withHeaders($this->request->headers);
        }

        switch(strtolower($this->request->method))
        {
            case 'post':
                $response = $response->post($this->request->url, $this->request->body);
                break;
            case 'put':
                $response = $response->put($this->request->url, $this->request->body);
                break;
            case 'patch':
                $response = $response->patch($this->request->url, $this->request->body);
                break;
            case 'delete':
                $response = $response->delete($this->request->url, $this->request->body);
                break;
            default:
                $response = $response->get($this->request->url);
                break;
        }

        $stop_time = time();

        $callback_name = "HandleHttpResponse";
        if($this->callback) $callback_name = $this->callback;

        $c = new ($callback_name)();
        $c->handle($response, $this->data, $stop_time - $start_time);
    }
}

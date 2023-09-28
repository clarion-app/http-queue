<?php

namespace ClarionApp\HttpQueue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use ClarionApp\HttpQueue\HttpRequest;

class SendHttpRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HttpRequest $request;
    protected $callback;
    protected $data;

    public function __construct(HttpRequest $request, $callback = null, $data = null)
    {
        $this->request = $request;
        $this->callback = $callback;
        $this->data = $data;
    }

    public function handle()
    {
        $fullUrl = $this->request->server_url . $this->request->path; 

        $start_time = time();
        switch(strtolower($this->request->method)) {
            case 'post':
                $response = Http::timeout($this->request->http_timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($fullUrl, $this->request->body);
                break;
            case 'put':
                $response = Http::timeout($this->request->http_timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->put($fullUrl, $this->request->body);
                break;
            case 'patch':
                $response = Http::timeout($this->request->http_timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->patch($fullUrl, $this->request->body);
                break;
            case 'delete':
                $response = Http::timeout($this->request->http_timeout)->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->delete($fullUrl, $this->request->body);
                break;
            default:
                $response = Http::timeout($this->request->http_timeout)->withHeaders([
                    'Accept' => 'application/json',
                ])->get($fullUrl);
        }

        $stop_time = time();
        if($this->callback)
        {
            $c = new ($this->callback)();
            $c->handle($response, $this->data, $stop_time - $start_time);
        }
    }
}

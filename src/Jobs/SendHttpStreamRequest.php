<?php

namespace ClarionApp\HttpQueue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use ClarionApp\HttpQueue\HttpRequest;

class SendHttpStreamRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HttpRequest $request;
    protected $callback_name;
    protected $callback;
    protected $data;
    protected $start_time;

    public function __construct(HttpRequest $request, $callback_name = null, $data = null)
    {
        $this->onQueue('http');

        $this->request = $request;
        $this->callback_name = $callback_name;
        $this->data = $data;
        $this->start_time = time();
    }

    public function handle()
    {
        $client = new Client(['timeout'=>$this->request->http_timeout]);
        $request = new Request($this->request->method, $this->request->url, $this->request->headers, json_encode($this->request->body));

        $callback_name = "ClarionApp\HttpQueue\HandleHttpStreamResponse";
        if($this->callback_name) $callback_name = $this->callback_name;

        try
        {
            $this->callback = new ($callback_name)();

            $promise = $client->sendAsync($request, [RequestOptions::STREAM => true])->then(
                function(ResponseInterface $res)
                {
                    $stream = $res->getBody();

                    while(!$stream->eof())
                    {
                        try
                        {
                            $content = $stream->read(512);
                        }
                        catch(RuntimeException $e)
                        {   
                            \Log::error($e->getMessage());
                            if($this->request->retries > 0)
                            {
                                $this->request->retries--;
                                Log::info("Retrying SendHttpStreamRequest. ".$this->request->retries." retries remaining.");
                                SendHttpStreamRequest::dispatch($this->request, $this->callback_name, $this->data);
                            }
                        }
                        $this->callback->handle($content, $this->data, time() - $this->start_time);
                    }
                },
                function(RequestException $e) {
                    \Log::error($e->getMessage());
                }
            );

            $promise->wait();

            $this->callback->finish($this->data);
        }
        catch(RuntimeException $e)
        {
            \Log::error($e->getMessage());
            if($this->request->retries > 0)
            {
                $this->request->retries--;
                Log::info("Retrying SendHttpStreamRequest. ".$this->request->retries." retries remaining.");
                SendHttpStreamRequest::dispatch($this->request, $this->callback_name, $this->data);
            }
        }
    }
}

<?php

namespace ClarionApp\HttpQueue;

class HttpRequest
{
    public $url;
    public $method;
    public array $headers;
    public $body;
    public $http_timeout = 900;
}

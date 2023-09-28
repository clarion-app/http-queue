<?php

namespace ClarionApp\HttpQueue;

class HttpRequest
{
    public $server_url;
    public $path;
    public $method;
    public $body;
    public $http_timeout = 900;
}

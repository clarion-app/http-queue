<?php

namespace ClarionApp\HttpQueue;

class HttpRequest
{
    public string $url = '';
    public string $method = 'GET';
    public array $headers = [];
    public mixed $body = null;
    public int $http_timeout = 120;
    public int $retries = 3;

    public function setUrl(string $url): self
    {
        $trimmed = trim($url);
        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (!$scheme || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new \InvalidArgumentException("URL must use http or https scheme. Got: '{$url}'");
        }

        $this->url = $trimmed;
        return $this;
    }
}

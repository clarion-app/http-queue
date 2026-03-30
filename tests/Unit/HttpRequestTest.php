<?php

namespace ClarionApp\HttpQueue\Tests\Unit;

use ClarionApp\HttpQueue\Tests\TestCase;
use ClarionApp\HttpQueue\HttpRequest;

class HttpRequestTest extends TestCase
{
    /** T021: HttpRequest default http_timeout is 120 */
    public function test_default_http_timeout_is_120(): void
    {
        $request = new HttpRequest();
        $this->assertSame(120, $request->http_timeout);
    }

    /** T022: HttpRequest accepts custom timeout override */
    public function test_custom_timeout_override(): void
    {
        $request = new HttpRequest();
        $request->http_timeout = 60;
        $this->assertSame(60, $request->http_timeout);

        $request2 = new HttpRequest();
        $request2->http_timeout = 0;
        $this->assertSame(0, $request2->http_timeout);
    }

    /** T025: setUrl accepts https:// URL */
    public function test_set_url_accepts_https(): void
    {
        $request = new HttpRequest();
        $request->setUrl('https://api.example.com/v1/data');
        $this->assertSame('https://api.example.com/v1/data', $request->url);
    }

    /** T026: setUrl accepts http://localhost URL */
    public function test_set_url_accepts_http_localhost(): void
    {
        $request = new HttpRequest();
        $request->setUrl('http://localhost:8080/api');
        $this->assertSame('http://localhost:8080/api', $request->url);
    }

    /** T027: setUrl accepts http:// URL with private IP */
    public function test_set_url_accepts_private_ip(): void
    {
        $request = new HttpRequest();
        $request->setUrl('http://192.168.1.1/api');
        $this->assertSame('http://192.168.1.1/api', $request->url);
    }

    /** T028: setUrl rejects file:// scheme URL */
    public function test_set_url_rejects_file_scheme(): void
    {
        $request = new HttpRequest();
        $this->expectException(\InvalidArgumentException::class);
        $request->setUrl('file:///etc/passwd');
    }

    /** T029: setUrl rejects ftp:// scheme URL */
    public function test_set_url_rejects_ftp_scheme(): void
    {
        $request = new HttpRequest();
        $this->expectException(\InvalidArgumentException::class);
        $request->setUrl('ftp://example.com/file.txt');
    }

    /** T030: setUrl rejects URL with no scheme */
    public function test_set_url_rejects_no_scheme(): void
    {
        $request = new HttpRequest();
        $this->expectException(\InvalidArgumentException::class);
        $request->setUrl('example.com/api');
    }

    /** T031: $url property has string type hint */
    public function test_url_property_has_string_type(): void
    {
        $reflection = new \ReflectionProperty(HttpRequest::class, 'url');
        $type = $reflection->getType();
        $this->assertNotNull($type, 'url property should have a type hint');
        $this->assertSame('string', $type->getName());
    }

    /** T032: $method property has string type hint */
    public function test_method_property_has_string_type(): void
    {
        $reflection = new \ReflectionProperty(HttpRequest::class, 'method');
        $type = $reflection->getType();
        $this->assertNotNull($type, 'method property should have a type hint');
        $this->assertSame('string', $type->getName());
    }
}

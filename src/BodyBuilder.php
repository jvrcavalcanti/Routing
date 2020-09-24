<?php

namespace Accolon\Routing;

use React\Http\Message\Response;

class BodyBuilder
{
    private $body;
    private int $status;
    private array $headers;

    public function __construct($body, int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function json($body, int $status = 200, array $headers = [])
    {
        $this->body = json_encode($body);
        $this->status = $status;
        $this->headers = $headers;
        return $this->create();
    }

    public function text(string $body, int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
        return $this->create();
    }

    public function create()
    {
        return new Response(
            $this->status,
            $this->headers,
            $this->body
        );
    }
}

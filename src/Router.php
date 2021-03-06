<?php

namespace Accolon\Routing;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Server;
use React\Socket\Server as Socket;

class Router
{
    private LoopInterface $loop;
    private Server $server;
    private Socket $socket;
    private array $middlewares = [];
    private array $routes = [
        "GET" => [],
        "POST" => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'OPTIONS' => []
    ];

    public function __construct()
    {
        $this->loop = Factory::create();
        $this->server = new Server($this->loop, Closure::fromCallable([$this, 'handler']));
    }

    private function handler(ServerRequestInterface $request)
    {
        $stack = new \SplStack();
        $stack->setIteratorMode(
            \SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_KEEP
        );

        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        
        foreach ($this->routes[$method] as $route) {
            if (preg_match($route->uri, $uri) && $route->method == $method) {
                $stack[] = fn($request) => Closure::fromCallable([$route, 'run'])($request);
                break;
            }
        }

        if (!sizeof($stack)) {
            return response("Not found", 404);
        }

        foreach ($this->middlewares as $middleware) {
            $next = $stack->top();
            $stack->push(fn(
                $request
            ) => $middleware($request, $next));
        }

        $start = $stack->top();
        try {
            $response = $start($request);
            return $response instanceof Response ? $response : response()->text($response);
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            return response("Internal Server Error", 500);
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function use($middleware)
    {
        if (is_string($middleware)) {
            $class = new $middleware();

            if (!$class instanceof IMiddleware) {
                throw new \RuntimeException(
                    $$middleware . " not an instance of " . IMiddleware::class
                );
            }

            $this->middlewares[] = Closure::fromCallable([$class, 'handle']);
        }

        if (is_callable($middleware)) {
            $this->middlewares[] = $middleware;
        }
    }

    private function addRoute(string $method, string $uri, $callback)
    {
        if ($uri == "/") {
            return $this->routes[$method][] = Route::create("/^\/$/", $method, $callback);
        }

        $uri = str_replace("/", "\/", $uri);

        return $this->routes[$method][] = Route::create(
            "/^" . $uri . "[\/]?$/",
            $method,
            $callback
        );
    }

    public function get(string $uri, $callback)
    {
        return $this->addRoute("GET", $uri, $callback);
    }

    public function post(string $uri, $callback)
    {
        return $this->addRoute("POST", $uri, $callback);
    }

    public function put(string $uri, $callback)
    {
        return $this->addRoute("PUT", $uri, $callback);
    }

    public function patch(string $uri, $callback)
    {
        return $this->addRoute("PATCH", $uri, $callback);
    }

    public function delete(string $uri, $callback)
    {
        return $this->addRoute("DELETE", $uri, $callback);
    }
    
    public function options(string $uri, $callback)
    {
        return $this->addRoute("OPTIONS", $uri, $callback);
    }

    public function listen(int $port = 8000, callable $callback = null)
    {
        $this->socket = new Socket($port, $this->loop);
        $this->server->listen($this->socket);
        if (!is_null($callback)) {
            $this->loop->addTimer(0, $callback);
        }
        $this->loop->run();
    }
}

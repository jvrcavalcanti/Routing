<?php

namespace Accolon\Routing;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route
{
    private string $uri;
    private string $method;
    private $callback;
    private \SplStack $stack;

    public function __construct(string $uri, string $method, $callback)
    {
        $this->uri = $uri;
        $this->method = $method;
        $this->callback = $callback;

        $this->stack = new \SplStack();
        $this->stack->setIteratorMode(
            \SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_KEEP
        );
        $this->stack->push($this);
    }

    public static function create(string $uri, string $method, $callback)
    {
        return new Route($uri, $method, $callback);
    }

    public function __get($attr)
    {
        return $this->$attr;
    }

    public function middleware(array $middlewares)
    {
        foreach ($middlewares as $middleware) {
            $next = $this->stack->top();
            
            if (is_string($middleware)) {
                $class = new $middleware();
    
                if (!$class instanceof IMiddleware) {
                    throw new \RuntimeException(
                        $$middleware . " not an instance of " . IMiddleware::class
                    );
                }
    
                $this->stack->push(fn($request) => Closure::fromCallable([$class, 'handle'])($request, $next));
            }
    
            if (is_callable($middleware)) {
                $this->stack->push(fn($request) => $middleware($request, $next));
            }
        }
    }

    public function __invoke(ServerRequestInterface $request)
    {
        $action;

        if (is_array($this->callback) &&
            is_string($this->callback[0]) &&
            is_string($this->callback[1])
        ) {
            $class = new $this->callback[0];

            if (!$class instanceof IController) {
                throw new \RuntimeException(
                    $this->callback[0] . " not an instance of " . IController::class
                );
            }

            $action = Closure::fromCallable([$class, $this->callback[1]]);
        }

        if (is_callable($this->callback)) {
            $action = $this->callback;
        }

        return $action($request);
    }

    public function run(ServerRequestInterface $request)
    {
        $start = $this->stack->top();
        try {
            $response = $start($request);

            if (!$response instanceof ResponseInterface) {
                return response()->text($response);
            }

            return $response;
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            return response("Internal Server Error", 500);
        }
    }
}

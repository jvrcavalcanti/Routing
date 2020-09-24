<?php

namespace Accolon\Routing;

interface IMiddleware
{
    public function handle(ServerRequestInterface $request, $next);
}
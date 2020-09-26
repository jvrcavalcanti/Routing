<?php

use Accolon\Routing\ResponseBuilder;
use Accolon\Routing\Router;

function response($body = "", $status = 200, $headers = [])
{
    return new ResponseBuilder($body, $status, $headers);
}

function router(): Router
{
    if (isset($GLOBALS['app'])) {
        return $app;
    }
    
    if (isset($GLOBALS['router'])) {
        return $router;
    }

    throw new \RuntimeException("Missing \$router/\$app in global scope");
}

function dd($var)
{
    var_dump($var);
    die();
}
<?php

use Psr\Http\Message\ServerRequestInterface;

function laravel(ServerRequestInterface $request): string
{
    require_once __DIR__.'/public/index.php';

    return '';
}

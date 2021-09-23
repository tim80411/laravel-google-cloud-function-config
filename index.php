<?php

use Psr\Http\Message\ServerRequestInterface;

function laravel(ServerRequestInterface $request): string
{
    $app = require __DIR__ . '/bootstrap/app.php';

    $app->run();

    return '';
}

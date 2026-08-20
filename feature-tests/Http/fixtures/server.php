<?php

declare(strict_types=1);

// Router script for the PHP built-in server used by Client feature tests.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
assert(is_string($path));

switch ($path) {
    case '/hello':
        header('X-Server: php-strong');
        echo 'Hello, world!';
        break;
    case '/echo':
        header('Content-Type: text/plain');
        $input = file_get_contents('php://input');
        echo $_SERVER['REQUEST_METHOD'], '|', $input === false ? '' : $input, '|', $_SERVER['HTTP_X_CUSTOM'] ?? '';
        break;
    case '/status':
        http_response_code((int) ($_GET['code'] ?? 500));
        echo 'status body';
        break;
    case '/redirect':
        header('Location: /hello', true, 302);
        break;
    case '/cookies':
        header('Set-Cookie: a=1', false);
        header('Set-Cookie: b=2', false);
        break;
    case '/slow':
        usleep(700_000);
        echo 'slow';
        break;
    default:
        http_response_code(404);
}

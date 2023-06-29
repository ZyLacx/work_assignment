<?php

spl_autoload_register(function ($class) {
    include_once __DIR__ . "/api/$class.php";
});

header("Content-type: application/json; charset=UTF-8");

$url_parts = explode("/", $_SERVER["REQUEST_URI"]);

switch ($url_parts[2]) {
    case "api":
        APIHandler::handle_request(array_slice($url_parts, 2));
        exit;
    default:
        http_response_code(404);
}

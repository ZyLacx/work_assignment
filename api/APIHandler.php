<?php

class APIHandler {
    public static function handle_request($url_parts) {
        if (count($url_parts) < 2) {
            http_response_code(400);
            exit;
        }

        switch ($url_parts[1]) {
            case "tenis":
                return APIHandler::handle_tenis_request(array_slice($url_parts, 1));
            default:
                http_response_code(400);
                exit;
        }
    }

    private static function handle_tenis_request($url_parts) {
        if (count($url_parts) < 2) {
            http_response_code(400);
            exit;
        }

        $pdo = new PDO("sqlite:tenis.db");

        try {
            switch ($url_parts[1]) {
                case "reservation":
                    $reservation = new Reservation($pdo);
                    echo $reservation->process($_SERVER["REQUEST_METHOD"]);
                    break;
                case "court":
                    $court = new Court($pdo);
                    echo $court->process($_SERVER["REQUEST_METHOD"]);
                    break;
                default:
                    http_response_code(400);
                    exit;
            }
        }
        catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode([
                "message" => $e->getMessage()
            ]);
        }
        catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                "message" => $e->getMessage(),
                "code" => $e->getCode(),
                "line" => $e->getLine()
            ]);
        }
    }
}

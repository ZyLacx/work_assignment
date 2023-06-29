<?php

class Reservation {
    private PDO $pdo;
    private int $MAX_DURATION = 36000;
    private int $MIN_DURATION = 3600;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function process(string $request) {
        // null will become empty array
        $data = (array) json_decode(file_get_contents("php://input"), true);
        switch ($request) {
            case "GET":
                return $this->get_data($data);
            case "POST":
                return $this->create_reservation($data);
            case "DELETE":
                $this->delete($data);
                return null;
            default:
                throw new Exception("Unknown request");
        }
    }

    private function get_data($data) {
        $stmt = null;
        if (empty($data)) {
            $stmt = $this->get_all();
        }
        elseif (isset($data["court"])) {
            $stmt = $this->get_by_court($data["court"]);
        }
        elseif (isset($data["phone"])) {
            $stmt = $this->get_by_phone($data["phone"]);
        }
        else {
            throw new InvalidArgumentException("Expected court id or phone number got none");
        }

        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($result ? $result : []);
    }
    
    private function get_all() {
        return $this->pdo->prepare("SELECT * FROM reservations");
    }

    private function get_by_court(int $id) {
        if (!is_int($id)) {
            throw new InvalidArgumentException("id not an integer");
        }

        $query = "SELECT * FROM reservations WHERE court_id=:id";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        return $stmt;
    }

    private function get_by_phone(string $phone_number) {
        if (!Security::is_phone_number($phone_number)) {
            throw new InvalidArgumentException("phone number not in valid format");
        }

        $query = "SELECT * FROM reservations WHERE phone_number=:phone";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(":phone", $phone_number, PDO::PARAM_STR);
        return $stmt;
    }

    private function create_reservation($data) {
        $this->verify_create_data($data);

        $stmt = $this->pdo->prepare("SELECT surface FROM courts WHERE id=:id");
        $stmt->bindValue(":id", $data["court_id"], PDO::PARAM_INT);
        $stmt->execute();
        $surface = $stmt->fetchAll()[0][0];
        $price = Pricing::for_tenis((int)$data["end"] - (int)$data["start"], $data["game"], $surface);
        
        $password = Security::generate_password(6);
        $password_hash = hash("sha256", $password);
        
        $phone = preg_replace('/\s*/', '', $data["phone"]);
        $phone_hash = hash("sha256", $phone);

        $query = "INSERT INTO reservations VALUES (NULL, :court_id, :phone, :password, :game, :start, :end)";
        $stmt = $this->pdo->prepare($query);

        $stmt->bindValue(":court_id", $data["court_id"], PDO::PARAM_INT);
        $stmt->bindValue(":phone", $phone_hash, PDO::PARAM_STR);
        $stmt->bindValue(":password", $password_hash, PDO::PARAM_STR);
        $stmt->bindValue(":game", $data["game"], PDO::PARAM_INT);
        $stmt->bindValue(":start", $data["start"], PDO::PARAM_INT);
        $stmt->bindValue(":end", $data["end"], PDO::PARAM_INT);

        $result = $stmt->execute();
        if ($result) {
            http_response_code(201);
            echo json_encode([
                "price" => $price,
                "password" => $password
            ]);
            return;
        }

        http_response_code(500);
        exit;
    }

    private function verify_create_data($data) {
        if (!isset($data["court_id"], $data["phone"], $data["game"], $data["start"], $data["end"])) {
            throw new InvalidArgumentException("some arguments are missing");
        }
        
        if (!is_int($data["court_id"])) {
            throw new InvalidArgumentException("court id not in correct format");
        }

        $court_id = (int)$data["court_id"];
        $court_count = $this->pdo->query("SELECT COUNT(*) FROM courts")->fetchAll()[0][0];

        if ($court_id < 1 || $court_id > $court_count) {
            throw new InvalidArgumentException("court id is not valid");
        }

        if (!Security::is_phone_number($data["phone"])) {
            throw new InvalidArgumentException("phone number not in correct format");
        }

        if (!is_int($data["game"]) || $data["game"] < 0 || $data["game"] >= Pricing::get_games_count()) {
            throw new InvalidArgumentException("game type is not in valid format");
        }

        if (!is_int($data["start"]) || !is_int($data["end"]))
        {
            throw new InvalidArgumentException("start or end is not in valid format");
        }

        $start = (int)$data["start"];
        $end = (int)$data["end"];

        $this->verify_interval($start, $end, $data["court_id"]);
    }

    private function verify_interval(int $start, int $end, int $id) {
        if ($start > $end || $end - $start > $this->MAX_DURATION || $end - $start < $this->MIN_DURATION) {
            throw new InvalidArgumentException("reservation interval not valid");
        }

        if ($start <= time()) {
            throw new InvalidArgumentException("can't create reservation in the past");
        }

        $query = 'SELECT * FROM reservations WHERE court_id=:court_id AND start>=:start AND end<=:end';
        $stmt = $this->pdo->prepare($query);
        
        $stmt->bindValue(":start", $start, PDO::PARAM_INT);
        $stmt->bindValue(":end", $end, PDO::PARAM_INT);
        $stmt->bindValue(":court_id", $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($result) {
            throw new InvalidArgumentException("reservation in this interval already exists");
        }
    }

    private function delete($data) {
        if (!isset($data["id"], $data["password"], $data["phone"])) {
            throw new InvalidArgumentException("invalid arguments");
        }

        if (!is_int($data["id"])) {
            throw new InvalidArgumentException("id not in correct format");
        }

        if (!Security::is_phone_number($data["phone"])) {
            throw new InvalidArgumentException("phone number not in correct format");
        }

        
        $phone = preg_replace('/\s*/', '', $data["phone"]);
        $phone_hash = hash("sha256", $phone);
        $password_hash = hash("sha256", $data["password"]);

        $query = "SELECT COUNT(*) FROM reservations WHERE id=:id AND phone_number=:phone AND password=:password";
        $stmt = $this->pdo->prepare($query);

        $stmt->bindValue(":id", $data["id"], PDO::PARAM_INT);
        $stmt->bindValue(":password", $password_hash, PDO::PARAM_STR);
        $stmt->bindValue(":phone", $phone_hash, PDO::PARAM_STR);

        $stmt->execute();
        $count = $stmt->fetchAll()[0][0];
        if ($count == 0) {
            throw new InvalidArgumentException("invalid entry (does not exist or not correct information)");
        }

        $query = "DELETE FROM reservations WHERE id=:id AND phone_number=:phone AND password=:password";
        $stmt = $this->pdo->prepare($query);
        
        $stmt->bindValue(":id", $data["id"], PDO::PARAM_INT);
        $stmt->bindValue(":password", $password_hash, PDO::PARAM_STR);
        $stmt->bindValue(":phone", $phone_hash, PDO::PARAM_STR);

        $result = $stmt->execute();
        if (!$result) {
            throw new InvalidArgumentException("something went wrong");
        }
    }
}
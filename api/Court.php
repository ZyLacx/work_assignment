<?php

class Court {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function process(string $request) {
        switch ($request) {
            case "GET":
                return $this->get_all();
            default:
                throw new Exception();
        }
    }
        
    private function get_all() {
        $query = $this->pdo->query("SELECT * FROM courts");
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($rows);
    }
}
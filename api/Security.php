<?php

class Security {
    public static function generate_password(int $len) : string {
        $password = "";
        for ($i = 0; $i < $len; $i++) {
            if (random_int(0, 1)) {
                $password .= chr(random_int(65, 90));
            }
            else {
                $password .= random_int(0, 9);
            }
        }

        return $password;
    }

    public static function is_phone_number(string $phone) : ?string {
        return !(is_null($phone) || preg_match("/\+[0-9\s]*$/", trim($phone)) == 0);
    }
}
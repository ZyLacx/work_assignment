<?php

class Pricing {
    // should rather be in db
    private static int $TENIS_PRICE = 500;
    private static $TENIS_GAME = array(1, 1.5);
    private static $SURFACE_PRICING = array(1, 1.5, 2);

    public static function for_tenis(int $duration, int $game, int $surface) {
        return intdiv($duration, 3600) * Pricing::$TENIS_PRICE 
                                        * Pricing::$TENIS_GAME[$game]
                                        * Pricing::$SURFACE_PRICING[$surface];
    }

    public static function get_games_count() {
        return count(Pricing::$TENIS_GAME);
    }

    public static function get_surfaces_count() {
        return count(Pricing::$SURFACE_PRICING);
    }
}
<?php

namespace App\Services;

use App\Models\GameSession;

class PinGenerator
{
    public function generateUniquePin(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (
            GameSession::query()
                ->where('pin', $pin)
                ->whereIn('status', ['waiting', 'playing'])
                ->exists()
        );

        return $pin;
    }
}

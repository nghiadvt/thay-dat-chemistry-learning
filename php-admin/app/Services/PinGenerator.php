<?php

namespace App\Services;

use App\Models\GameSession;

class PinGenerator
{
    public function generateUniquePin(?string $ignorePin = null): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (
            GameSession::query()
                ->where('pin', $pin)
                ->when($ignorePin, fn ($q) => $q->where('pin', '!=', $ignorePin))
                ->whereIn('status', ['waiting', 'playing'])
                ->exists()
        );

        return $pin;
    }
}

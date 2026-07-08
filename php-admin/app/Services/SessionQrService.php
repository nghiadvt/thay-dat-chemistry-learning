<?php

namespace App\Services;

use App\Models\GameSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SessionQrService
{
    public function joinUrl(GameSession $session): string
    {
        return url('/join/'.$session->pin);
    }

    /**
     * Tạo (hoặc dùng lại) ảnh QR PNG trong storage/app/public/sessions/{pin}.png
     */
    public function ensureQr(GameSession $session, ?string $joinUrl = null): string
    {
        $joinUrl ??= $this->joinUrl($session);
        $path = $session->qr_path ?: "sessions/{$session->pin}.png";

        if ($session->qr_path && Storage::disk('public')->exists($session->qr_path)) {
            return $session->qr_path;
        }

        Storage::disk('public')->makeDirectory('sessions');

        $response = Http::timeout(20)->get('https://api.qrserver.com/v1/create-qr-code/', [
            'size' => '512x512',
            'format' => 'png',
            'data' => $joinUrl,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Không tạo được ảnh QR. Kiểm tra kết nối mạng hoặc thử lại sau.');
        }

        Storage::disk('public')->put($path, $response->body());

        if ($session->qr_path !== $path) {
            $session->update(['qr_path' => $path]);
        }

        return $path;
    }

    public function publicUrl(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }
}

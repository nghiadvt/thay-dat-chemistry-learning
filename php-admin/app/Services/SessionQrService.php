<?php

namespace App\Services;

use App\Models\GameSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SessionQrService
{
    /**
     * Public join URL for students — always from APP_URL (not the current request host).
     * Local LAN phone test: APP_URL=http://192.168.x.x:38480
     * Production: APP_URL=https://your-domain.tld
     */
    public function joinUrl(GameSession $session): string
    {
        return rtrim((string) config('app.url'), '/').'/join/'.$session->pin;
    }

    /**
     * URL ảnh QR luôn mã hóa đúng joinUrl.
     * Ưu tiên PNG đã lưu; nếu thiếu/lỗi mạng → CDN qrserver với đúng data=joinUrl.
     * Không bao giờ trả asset mock (qr-login.png).
     */
    public function displayQrUrl(GameSession $session, ?string $joinUrl = null): string
    {
        $joinUrl ??= $this->joinUrl($session);

        try {
            $this->ensureQr($session, $joinUrl);
            $session->refresh();
        } catch (\Throwable) {
            // Fallback CDN bên dưới
        }

        return $session->qr_url ?: $this->cdnQrUrl($joinUrl);
    }

    public function cdnQrUrl(string $joinUrl): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&data='.rawurlencode($joinUrl);
    }

    /**
     * Create (or refresh) QR PNG when missing or when encoded join URL ≠ current APP_URL.
     * Sidecar `sessions/{pin}.joinurl` stores the URL that was encoded into the PNG.
     */
    public function ensureQr(GameSession $session, ?string $joinUrl = null): string
    {
        $joinUrl ??= $this->joinUrl($session);
        $path = $session->qr_path ?: "sessions/{$session->pin}.png";
        $metaPath = $this->metaPath($session->pin);

        if ($this->qrMatchesJoinUrl($path, $metaPath, $joinUrl)) {
            if ($session->qr_path !== $path) {
                $session->update(['qr_path' => $path]);
            }

            return $path;
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
        Storage::disk('public')->put($metaPath, $joinUrl);

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

    private function metaPath(string $pin): string
    {
        return "sessions/{$pin}.joinurl";
    }

    private function qrMatchesJoinUrl(string $path, string $metaPath, string $joinUrl): bool
    {
        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        if (! Storage::disk('public')->exists($metaPath)) {
            // Legacy QR (no sidecar) — treat as stale so APP_URL changes regenerate.
            return false;
        }

        return trim((string) Storage::disk('public')->get($metaPath)) === $joinUrl;
    }
}

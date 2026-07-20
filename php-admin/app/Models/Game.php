<?php

namespace App\Models;

use App\Support\DuckRaceAssets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
        'play_mode_id',
        'mode_config',
    ];

    protected function casts(): array
    {
        return [
            'mode_config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Xóa game: chụp lại tên + id game vào các phòng chơi cũ rồi gỡ FK,
        // để lịch sử phòng chơi giữ nguyên và vẫn biết từng chạy game nào.
        static::deleting(function (self $game): void {
            $game->sessions()->update([
                'deleted_game_id' => $game->id,
                'deleted_game_name' => $game->name,
                'game_id' => null,
            ]);
        });
    }

    public function playMode(): BelongsTo
    {
        return $this->belongsTo(PlayMode::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedModeConfig(): array
    {
        $mode = $this->relationLoaded('playMode') ? $this->playMode : $this->playMode()->first();

        if (! $mode) {
            return [];
        }

        $config = $mode->resolvedConfig($this->mode_config);

        // Danh sách vịt luôn đọc "sống" từ DB tại thời điểm tạo phòng, không
        // phụ thuộc vào giá trị đã lưu lúc save game — admin thêm vịt mới là
        // phòng tạo sau đó thấy ngay, không cần lưu lại game.
        if ($mode->slug === 'duck_race') {
            $config['visual']['duck_sprites'] = DuckRaceAssets::listSpriteTokens();
        }

        return $config;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function coverImageUrl(): ?string
    {
        if ($this->playMode?->slug === 'duck_race') {
            return asset('htd-admin/assets/games/dua-vit.png');
        }

        return $this->playMode?->bannerUrl();
    }

    public function isDuckRace(): bool
    {
        return $this->playMode?->slug === 'duck_race';
    }
}

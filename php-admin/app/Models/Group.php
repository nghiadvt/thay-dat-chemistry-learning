<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $table = 'content_groups';

    public const SCOPE_QUIZ = 'quiz';
    public const SCOPE_QUESTION_BANK = 'question_bank';
    public const SCOPE_SESSION = 'session';

    /** @var array<string, string> */
    public const SCOPE_LABELS = [
        self::SCOPE_QUIZ => 'Quiz',
        self::SCOPE_QUESTION_BANK => 'Bộ câu hỏi',
        self::SCOPE_SESSION => 'Phòng chơi',
    ];

    /** @var list<string> */
    public const PRESET_COLORS = Tag::PRESET_COLORS;

    protected $fillable = [
        'scope',
        'name',
        'slug',
        'color',
        'sort_order',
    ];

    public function scopeOfScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function questionBankItems(): HasMany
    {
        return $this->hasMany(QuestionBankItem::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /**
     * Đếm số mục đang thuộc nhóm — tùy scope mà đếm ở bảng tương ứng.
     */
    public function itemsCount(): int
    {
        return match ($this->scope) {
            self::SCOPE_QUIZ => $this->quizzes()->count(),
            self::SCOPE_QUESTION_BANK => $this->questionBankItems()->count(),
            self::SCOPE_SESSION => $this->gameSessions()->count(),
            default => 0,
        };
    }

    public static function findOrCreateFromName(string $name, string $scope, ?string $color = null): self
    {
        $trimmed = trim($name);
        $slug = Str::slug($trimmed);
        if ($slug === '') {
            $slug = 'nhom-'.substr(md5(mb_strtolower($trimmed)), 0, 12);
        }

        return static::firstOrCreate(
            ['scope' => $scope, 'slug' => $slug],
            [
                'name' => $trimmed,
                'color' => $color ?? self::nextDefaultColor($scope),
            ]
        );
    }

    public static function nextDefaultColor(string $scope): string
    {
        $count = static::query()->where('scope', $scope)->count();

        return self::PRESET_COLORS[$count % count(self::PRESET_COLORS)];
    }

    protected function textColor(): Attribute
    {
        return Attribute::get(fn (): string => Tag::contrastTextColor($this->color ?? '#2D46D6'));
    }
}

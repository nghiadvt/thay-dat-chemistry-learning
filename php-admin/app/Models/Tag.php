<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    /** @var list<string> */
    public const PRESET_COLORS = [
        '#2D46D6',
        '#059669',
        '#DC2626',
        '#D97706',
        '#7C3AED',
        '#0891B2',
        '#DB2777',
    ];

    protected $fillable = [
        'name',
        'slug',
        'color',
        'scope',
    ];

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_tag');
    }

    public function questionBankItems(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBankItem::class, 'question_bank_tag');
    }

    public function imageCropSources(): BelongsToMany
    {
        return $this->belongsToMany(ImageCropSource::class, 'image_crop_source_tag');
    }

    public static function findOrCreateFromName(string $name, ?string $color = null, string $scope = 'content'): self
    {
        $trimmed = trim($name);
        $slug = Str::slug($trimmed);
        if ($slug === '') {
            $slug = 'tag-'.substr(md5(mb_strtolower($trimmed)), 0, 12);
        }

        return static::firstOrCreate(
            ['slug' => $slug, 'scope' => $scope],
            [
                'name' => $trimmed,
                'color' => $color ?? self::nextDefaultColor($scope),
            ]
        );
    }

    public static function nextDefaultColor(string $scope = 'content'): string
    {
        $count = static::query()->where('scope', $scope)->count();

        return self::PRESET_COLORS[$count % count(self::PRESET_COLORS)];
    }

    protected function textColor(): Attribute
    {
        return Attribute::get(function (): string {
            return self::contrastTextColor($this->color ?? '#2D46D6');
        });
    }

    public static function contrastTextColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.62 ? '#1f2937' : '#ffffff';
    }
}

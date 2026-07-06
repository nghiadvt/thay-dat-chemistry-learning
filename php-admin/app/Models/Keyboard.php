<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Keyboard extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'subject',
        'config',
        'preview_path',
    ];

    protected $appends = [
        'preview_url',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->preview_path || ! Storage::disk('public')->exists($this->preview_path)) {
                return null;
            }

            return asset('storage/'.$this->preview_path);
        });
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Đường dẫn lưu preview: keyboards/{dd-mm-YYYY}-{ten-ban-phim}.png
     */
    public function buildPreviewStoragePath(): string
    {
        $date = now()->format('d-m-Y');
        $slug = Str::slug($this->name, '-');
        if ($slug === '') {
            $slug = 'ban-phim';
        }
        $slug = Str::limit($slug, 180, '');

        $path = "keyboards/{$date}-{$slug}.png";
        if (Storage::disk('public')->exists($path) && $this->preview_path !== $path) {
            $path = "keyboards/{$date}-{$slug}-{$this->id}.png";
        }

        return $path;
    }
}

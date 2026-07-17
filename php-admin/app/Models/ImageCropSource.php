<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ImageCropSource extends Model
{
    protected $fillable = [
        'name',
        'original_filename',
        'path',
        'preview_path',
        'folder',
    ];

    protected $appends = [
        'image_url',
        'preview_url',
    ];

    public function regions(): HasMany
    {
        return $this->hasMany(ImageCropRegion::class)->orderBy('position');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'image_crop_source_tag');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->path || ! Storage::disk('public')->exists($this->path)) {
                return null;
            }

            return asset('storage/'.$this->path);
        });
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
}

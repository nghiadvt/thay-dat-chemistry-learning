<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ImageCropRegion extends Model
{
    protected $fillable = [
        'image_crop_source_id',
        'label',
        'position',
        'x',
        'y',
        'w',
        'h',
        'rotation',
        'flipped',
        'cropped_path',
    ];

    protected $appends = [
        'cropped_url',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'w' => 'integer',
            'h' => 'integer',
            'rotation' => 'float',
            'flipped' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ImageCropSource::class, 'image_crop_source_id');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function croppedUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->cropped_path || ! Storage::disk('public')->exists($this->cropped_path)) {
                return null;
            }

            return asset('storage/'.$this->cropped_path);
        });
    }
}

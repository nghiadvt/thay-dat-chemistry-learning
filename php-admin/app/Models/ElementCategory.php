<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nhóm nguyên tố (Kim loại kiềm, Kiềm thổ…) dùng chung mọi phiên bản bảng.
 */
class ElementCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'color',
        'deep_color',
        'sort_order',
    ];

    public function elements(): HasMany
    {
        return $this->hasMany(Element::class, 'category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'image', 'parent_id', 'is_active'];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    // Parent Category Relation
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Children Subcategories Relation
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Products Relation
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

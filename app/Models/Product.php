<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'name_kh',
        'category_id',
        'price',
        'stock',
        'image_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    // Auto generate UUID when creating
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cart()
    {
        return $this->hasMany(Cart::class);
    }

    // ✅ FIXED: Accessor for image URL
    public function getImageUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // ✅ ប្រសិនបើជា URL ពេញ (http:// ឬ https://)
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // ✅ ប្រសិនបើជាផ្លូវរូបភាព (storage/products/xxx.jpg)
        return asset('storage/' . $value);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'LIKE', "%{$term}%")
            ->orWhere('name_kh', 'LIKE', "%{$term}%");
    }

    // Check if product is in stock
    public function hasStock($quantity = 1)
    {
        return $this->stock >= $quantity;
    }

    // Reduce stock
    public function reduceStock($quantity = 1)
    {
        if ($this->stock >= $quantity) {
            $this->decrement('stock', $quantity);
            return true;
        }
        return false;
    }
}
    
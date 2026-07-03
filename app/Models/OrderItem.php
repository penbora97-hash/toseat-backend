<?php
// app/Models/OrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    // ✅ Table name (Laravel នឹងប្រើ "order_items" ដោយស្វ័យប្រវត្តិ)
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // ✅ Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // ✅ Accessor (គណនាតម្លៃសរុប)
    public function getTotalAttribute()
    {
        return $this->quantity * $this->product_price;
    }

    // ✅ Formatted price
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->product_price, 2);
    }

    // ✅ Formatted subtotal
    public function getFormattedSubtotalAttribute()
    {
        return '$' . number_format($this->subtotal, 2);
    }
}

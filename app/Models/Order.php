<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_number',
        'user_id',
        'total_amount',
        'subtotal',
        'delivery_fee', 
        'tax',
        'discount',
        'status',
        'payment_method',
        'payment_status',
        'delivery_address',
        'customer_name',
        'customer_phone',
        'customer_email', // ✅ បន្ថែម
        'notes',
        'delivered_at',
        'cancelled_by', // ✅ បន្ថែម
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    // ✅ Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // ✅ Cancelled By Constants
    const CANCELLED_BY_USER = 'user';
    const CANCELLED_BY_ADMIN = 'admin';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->order_number)) {
                $model->order_number = 'ORD-' . strtoupper(Str::random(8));
            }
        });
    }

    // ✅ Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // =============================================
    // ✅ Scopes
    // =============================================
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopePreparing($query)
    {
        return $query->where('status', self::STATUS_PREPARING);
    }

    public function scopeOutForDelivery($query)
    {
        return $query->where('status', self::STATUS_OUT_FOR_DELIVERY);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeCancelledByUser($query)
    {
        return $query->where('status', self::STATUS_CANCELLED)
                     ->where('cancelled_by', self::CANCELLED_BY_USER);
    }

    public function scopeCancelledByAdmin($query)
    {
        return $query->where('status', self::STATUS_CANCELLED)
                     ->where('cancelled_by', self::CANCELLED_BY_ADMIN);
    }

    // =============================================
    // ✅ Helper Methods
    // =============================================
    
    // Status checks
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isPreparing(): bool
    {
        return $this->status === self::STATUS_PREPARING;
    }

    public function isOutForDelivery(): bool
    {
        return $this->status === self::STATUS_OUT_FOR_DELIVERY;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isModifiable(): bool
    {
        return !in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED
        ]);
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // Cancelled by checks
    public function isCancelledByUser(): bool
    {
        return $this->status === self::STATUS_CANCELLED && 
               $this->cancelled_by === self::CANCELLED_BY_USER;
    }

    public function isCancelledByAdmin(): bool
    {
        return $this->status === self::STATUS_CANCELLED && 
               $this->cancelled_by === self::CANCELLED_BY_ADMIN;
    }

    // ✅ Get cancelled by label
    public function getCancelledByLabel(): string
    {
        if ($this->status !== self::STATUS_CANCELLED) {
            return '';
        }
        
        if ($this->cancelled_by === self::CANCELLED_BY_USER) {
            return 'Cancelled by Customer';
        }
        
        if ($this->cancelled_by === self::CANCELLED_BY_ADMIN) {
            return 'Cancelled by Admin';
        }
        
        return 'Cancelled';
    }

    // ✅ Get cancelled by color for badge
    public function getCancelledByColor(): string
    {
        if ($this->status !== self::STATUS_CANCELLED) {
            return '';
        }
        
        if ($this->cancelled_by === self::CANCELLED_BY_USER) {
            return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        }
        
        if ($this->cancelled_by === self::CANCELLED_BY_ADMIN) {
            return 'bg-red-500/10 text-red-400 border-red-500/20';
        }
        
        return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    }

    // ✅ Get cancelled by icon
    public function getCancelledByIcon(): ?string
    {
        if ($this->status !== self::STATUS_CANCELLED) {
            return null;
        }
        
        if ($this->cancelled_by === self::CANCELLED_BY_USER) {
            return 'user';
        }
        
        if ($this->cancelled_by === self::CANCELLED_BY_ADMIN) {
            return 'admin';
        }
        
        return null;
    }

    // ✅ Get status label
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    // ✅ Get status color
    public function getStatusColor(): string
    {
        $colors = [
            self::STATUS_PENDING => 'amber',
            self::STATUS_CONFIRMED => 'blue',
            self::STATUS_PREPARING => 'purple',
            self::STATUS_OUT_FOR_DELIVERY => 'orange',
            self::STATUS_DELIVERED => 'emerald',
            self::STATUS_CANCELLED => 'red',
        ];
        return $colors[$this->status] ?? 'gray';
    }

    // ✅ Get all statuses
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    // ✅ Get status options for dropdown
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    // ✅ Get cancelled by options for dropdown
    public static function getCancelledByOptions(): array
    {
        return [
            self::CANCELLED_BY_USER => 'User',
            self::CANCELLED_BY_ADMIN => 'Admin',
        ];
    }
}
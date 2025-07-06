<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'category_id',
        'service_id',
        'api_order_id',
        'api_refill_id',
        'link',
        'quantity',
        'price',
        'status',
        'refill_status',
        'status_description',
        'reason',
        'agree',
        'start_counter',
        'remains',
        'runs',
        'interval',
        'drip_feed',
        'refilled_at',
        'added_on',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:8',
        'agree' => 'boolean',
        'runs' => 'integer',
        'interval' => 'integer',
        'drip_feed' => 'boolean',
        'refilled_at' => 'datetime',
        'added_on' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category associated with the order.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the service associated with the order.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}

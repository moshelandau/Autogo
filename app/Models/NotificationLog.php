<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'notification_template_id', 'reminder_id', 'user_id',
        'channel', 'phone_number', 'message', 'status', 'external_id',
        'error_message', 'sent_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function template(): BelongsTo { return $this->belongsTo(NotificationTemplate::class, 'notification_template_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

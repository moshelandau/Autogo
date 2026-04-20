<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EzPassAccount extends Model
{
    protected $fillable = ['customer_id', 'account_number', 'tag_number', 'status', 'balance', 'notes'];

    protected function casts(): array { return ['balance' => 'decimal:2']; }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}

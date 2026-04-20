<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeTaskComment extends Model
{
    protected $fillable = ['office_task_id', 'body', 'user_id'];

    public function officeTask(): BelongsTo { return $this->belongsTo(OfficeTask::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

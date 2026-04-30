<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteComment extends Model
{
    protected $fillable = ['note_id', 'user_id', 'body'];

    public function note(): BelongsTo { return $this->belongsTo(Note::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

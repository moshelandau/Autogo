<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseDocumentItem extends Model
{
    protected $fillable = [
        'lease_document_checklist_id', 'name', 'sort_order',
        'is_collected', 'file_path', 'collected_at', 'collected_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['is_collected' => 'boolean', 'collected_at' => 'datetime'];
    }

    public function checklist(): BelongsTo { return $this->belongsTo(LeaseDocumentChecklist::class, 'lease_document_checklist_id'); }
    public function collectedByUser(): BelongsTo { return $this->belongsTo(User::class, 'collected_by'); }

    public function markCollected(?string $filePath = null, ?int $userId = null): void
    {
        $this->update([
            'is_collected' => true,
            'collected_at' => now(),
            'collected_by' => $userId ?? auth()->id(),
            'file_path' => $filePath ?? $this->file_path,
        ]);
        $this->checklist->checkCompletion();
    }

    public function markUncollected(): void
    {
        $this->update([
            'is_collected' => false,
            'collected_at' => null,
            'collected_by' => null,
        ]);
        $this->checklist->update(['status' => 'pending']);
    }
}

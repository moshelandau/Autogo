<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerMarkdown extends Model
{
    protected $fillable = [
        'dealer_id', 'dealer_name', 'amount', 'title',
        'make', 'model', 'year_from', 'year_to',
        'valid_from', 'valid_through',
        'notes', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'valid_from'    => 'date',
            'valid_through' => 'date',
            'is_active'     => 'boolean',
        ];
    }

    public function dealer(): BelongsTo { return $this->belongsTo(Dealer::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(function ($w) {
                $w->whereNull('valid_through')->orWhereDate('valid_through', '>=', now());
            });
    }

    /**
     * Find markdowns that could apply to a given vehicle. Optional
     * dealer scope narrows further. Order: most specific first
     * (dealer + make + model), broadest last (no dealer / no vehicle).
     */
    public function scopeFor(Builder $q, ?string $make = null, ?string $model = null, ?int $year = null, ?int $dealerId = null): Builder
    {
        return $q->active()
            ->when($make,  fn ($w, $m) => $w->where(function ($x) use ($m) { $x->whereNull('make')->orWhereRaw('LOWER(make) = ?', [strtolower($m)]); }))
            ->when($model, fn ($w, $m) => $w->where(function ($x) use ($m) { $x->whereNull('model')->orWhereRaw('LOWER(model) = ?', [strtolower($m)]); }))
            ->when($year,  fn ($w, $y) => $w->where(function ($x) use ($y) {
                $x->where(function ($a) use ($y) { $a->whereNull('year_from')->orWhere('year_from', '<=', $y); })
                  ->where(function ($a) use ($y) { $a->whereNull('year_to')->orWhere('year_to', '>=', $y); });
            }))
            ->when($dealerId, fn ($w, $d) => $w->where(function ($x) use ($d) { $x->whereNull('dealer_id')->orWhere('dealer_id', $d); }))
            ->orderByDesc('amount');
    }
}

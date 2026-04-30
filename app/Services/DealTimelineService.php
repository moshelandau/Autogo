<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Deal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds a unified, reverse-chronological event feed for one deal.
 *
 * Pulls from:
 *   - audit_logs   — any state-changing HTTP request whose path mentions
 *                    /deals/{id} (covers stage transitions, field updates,
 *                    task toggles, quote add/remove, document deletes…)
 *   - deal_documents — each upload becomes a "Document uploaded" entry
 *   - deal_tasks    — completed_at becomes a "Task completed" entry
 *   - deal_quotes   — created_at becomes a "Quote added" entry
 *
 * Returns a flat array of { kind, at, actor, label, meta } sorted desc.
 */
class DealTimelineService
{
    public function build(Deal $deal, int $limit = 200): array
    {
        $events = collect()
            ->merge($this->fromAudit($deal))
            ->merge($this->fromDocuments($deal))
            ->merge($this->fromTasks($deal))
            ->merge($this->fromQuotes($deal));

        return $events
            ->sortByDesc(fn ($e) => $e['at'])
            ->values()
            ->take($limit)
            ->map(fn ($e) => [
                'kind'  => $e['kind'],
                'at'    => $e['at'] instanceof CarbonInterface ? $e['at']->toIso8601String() : (string) $e['at'],
                'actor' => $e['actor'] ?? null,
                'label' => $e['label'],
                'meta'  => $e['meta'] ?? null,
            ])
            ->all();
    }

    private function fromAudit(Deal $deal): Collection
    {
        $needle = "/deals/{$deal->id}";
        return AuditLog::query()
            ->where('path', 'ilike', "%{$needle}%")
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (AuditLog $a) use ($deal) {
                $kind = 'update';
                $label = ucfirst($a->action ?? strtolower($a->method));
                if (str_contains((string) $a->path, '/transition')) {
                    $kind = 'stage';
                    $stage = $a->params['stage'] ?? null;
                    $label = $stage ? "Stage changed to {$stage}" : 'Stage changed';
                } elseif (str_contains((string) $a->path, '/tasks/')) {
                    $kind = 'task';
                    $label = 'Task updated';
                } elseif (str_contains((string) $a->path, '/quote')) {
                    $kind = 'quote';
                    $label = $a->method === 'POST' ? 'Quote added' : ($a->method === 'DELETE' ? 'Quote removed' : 'Quote updated');
                } elseif (str_contains((string) $a->path, '/document') || str_contains((string) $a->path, '/upload')) {
                    $kind = 'document';
                    $label = $a->method === 'DELETE' ? 'Document removed' : 'Document changed';
                } elseif ($a->method === 'PUT' && rtrim((string) $a->path, '/') === "/leasing/deals/{$deal->id}") {
                    $kind = 'update';
                    // Surface which keys were touched (skip the noisy ones)
                    $keys = collect(array_keys($a->params ?? []))->reject(fn ($k) => in_array($k, ['_method','_token']))->take(4)->implode(', ');
                    $label = $keys ? "Updated: {$keys}" : 'Deal updated';
                }
                return [
                    'kind'  => $kind,
                    'at'    => $a->created_at,
                    'actor' => $a->user_name,
                    'label' => $label,
                    'meta'  => null,
                ];
            });
    }

    private function fromDocuments(Deal $deal): Collection
    {
        return $deal->documents()->orderByDesc('id')->limit(100)->get()
            ->map(fn ($d) => [
                'kind'  => 'document',
                'at'    => $d->created_at,
                'actor' => optional($d->uploadedBy ?? null)->name,
                'label' => "Document uploaded: {$d->name}",
                'meta'  => $d->type,
            ]);
    }

    private function fromTasks(Deal $deal): Collection
    {
        return $deal->tasks()->whereNotNull('completed_at')->orderByDesc('completed_at')->limit(100)->get()
            ->map(fn ($t) => [
                'kind'  => 'task',
                'at'    => $t->completed_at,
                'actor' => null,
                'label' => "Task completed: {$t->name}",
                'meta'  => $t->stage,
            ]);
    }

    private function fromQuotes(Deal $deal): Collection
    {
        return $deal->quotes()->orderByDesc('id')->limit(50)->get()
            ->map(fn ($q) => [
                'kind'  => 'quote',
                'at'    => $q->created_at,
                'actor' => null,
                'label' => 'Quote added',
                'meta'  => $q->lender->name ?? null,
            ]);
    }
}

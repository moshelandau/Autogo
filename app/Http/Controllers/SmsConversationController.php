<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SmsConversationController extends Controller
{
    /**
     * Inbox view: one row per "other party" (phone number), most-recent first.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        // Other-party phone = `from` if inbound, `to` if outbound
        $rows = CommunicationLog::query()
            ->where('channel', 'sms')
            ->select([
                DB::raw("CASE WHEN direction = 'inbound' THEN \"from\" ELSE \"to\" END AS other_party"),
                'customer_id',
                'body',
                'direction',
                'status',
                'sent_at',
                'created_at',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('from', 'ilike', "%{$search}%")
                      ->orWhere('to', 'ilike', "%{$search}%")
                      ->orWhere('body', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        // Group by other_party, keep latest, count unread (inbound + status=received)
        $grouped = [];
        foreach ($rows as $r) {
            $key = $this->normalizePhone((string) $r->other_party);
            if ($key === '') continue;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'phone'        => $r->other_party,
                    'customer_id'  => $r->customer_id,
                    'last_body'    => $r->body,
                    'last_at'      => $r->sent_at ?? $r->created_at,
                    'last_dir'     => $r->direction,
                    'unread_count' => 0,
                    'total'        => 0,
                ];
            }
            $grouped[$key]['total']++;
            if ($r->direction === 'inbound' && $r->status === 'received') {
                $grouped[$key]['unread_count']++;
            }
        }

        // Attach customer names
        $customerIds = collect($grouped)->pluck('customer_id')->filter()->unique()->values();
        $customers = Customer::whereIn('id', $customerIds)->get(['id', 'first_name', 'last_name'])->keyBy('id');
        foreach ($grouped as &$g) {
            if ($g['customer_id'] && $customers->has($g['customer_id'])) {
                $c = $customers[$g['customer_id']];
                $g['customer_name'] = trim("{$c->first_name} {$c->last_name}");
            } else {
                $g['customer_name'] = null;
            }
        }

        return Inertia::render('Sms/Index', [
            'conversations' => array_values($grouped),
            'filters'       => ['search' => $search],
        ]);
    }

    /**
     * Thread view: all messages with one phone number.
     */
    public function show(string $phone)
    {
        $last10 = substr(preg_replace('/\D/', '', $phone), -10);

        $messages = CommunicationLog::query()
            ->where('channel', 'sms')
            ->where(function ($q) use ($last10) {
                $q->where('from', 'ilike', "%{$last10}")
                  ->orWhere('to', 'ilike', "%{$last10}");
            })
            ->orderBy('created_at')
            ->get(['id', 'direction', 'from', 'to', 'body', 'status', 'sent_at', 'created_at', 'user_id', 'customer_id']);

        // Mark inbound 'received' as 'read' on view (only safe non-update would be a separate read_at column;
        // CommunicationLog is not append-only — see model — so an update here is fine)
        CommunicationLog::query()
            ->where('channel', 'sms')
            ->where('direction', 'inbound')
            ->where('status', 'received')
            ->where(function ($q) use ($last10) {
                $q->where('from', 'ilike', "%{$last10}");
            })
            ->update(['status' => 'read']);

        $customer = $messages->firstWhere('customer_id', '!=', null)?->customer_id
            ? Customer::find($messages->firstWhere('customer_id', '!=', null)->customer_id)
            : null;

        return Inertia::render('Sms/Show', [
            'phone'    => $phone,
            'messages' => $messages,
            'customer' => $customer,
        ]);
    }

    /**
     * Inline thread fragment for embedding in Customer/Deal Show pages.
     * Returns JSON for axios consumption.
     */
    public function customerThread(Customer $customer)
    {
        $messages = CommunicationLog::query()
            ->where('channel', 'sms')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->get(['id', 'direction', 'from', 'to', 'body', 'status', 'sent_at', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    private function normalizePhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        return strlen($d) >= 10 ? substr($d, -10) : $d;
    }
}

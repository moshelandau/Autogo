<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\User;
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
        $mine   = (bool) $request->boolean('mine');

        // Other-party phone = `from` if inbound, `to` if outbound
        $rows = CommunicationLog::query()
            ->where('channel', 'sms')
            ->select([
                DB::raw("CASE WHEN direction = 'inbound' THEN \"from\" ELSE \"to\" END AS other_party"),
                'customer_id',
                'assigned_to',
                'body',
                'direction',
                'status',
                'sent_at',
                'created_at',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $digits = preg_replace('/\D/', '', $search);
                $q->where(function ($w) use ($search, $digits) {
                    $w->where('from', 'ilike', "%{$search}%")
                      ->orWhere('to', 'ilike', "%{$search}%")
                      ->orWhere('body', 'ilike', "%{$search}%");
                    if ($digits !== '') {
                        $w->orWhere('from', 'ilike', "%{$digits}%")
                          ->orWhere('to',   'ilike', "%{$digits}%");
                    }
                    // Match by linked customer name
                    $w->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('first_name', 'ilike', "%{$search}%")
                           ->orWhere('last_name', 'ilike', "%{$search}%");
                    });
                });
            })
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
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
                    'assigned_to'  => $r->assigned_to,
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

        // Attach assignee names
        $userIds = collect($grouped)->pluck('assigned_to')->filter()->unique()->values();
        $users   = User::whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');
        foreach ($grouped as &$g) {
            $g['assignee_name'] = $g['assigned_to'] && $users->has($g['assigned_to'])
                ? $users[$g['assigned_to']]->name : null;
        }

        return Inertia::render('Sms/Index', [
            'conversations' => array_values($grouped),
            'filters'       => ['search' => $search, 'mine' => $mine],
            'staff'         => User::where('email', 'like', '%@autogoco.com')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Reassign every message in a conversation (by phone) to a staff member. */
    public function assign(Request $request, string $phone)
    {
        $validated = $request->validate(['user_id' => 'nullable|exists:users,id']);
        $last10 = substr(preg_replace('/\D/', '', $phone), -10);
        CommunicationLog::query()
            ->where('channel', 'sms')
            ->where(function ($q) use ($last10) {
                $q->where('from', 'ilike', "%{$last10}")->orWhere('to', 'ilike', "%{$last10}");
            })
            ->update(['assigned_to' => $validated['user_id'] ?? null]);
        return back()->with('success', $validated['user_id'] ? 'Conversation assigned.' : 'Assignment cleared.');
    }

    /** Mark a single message as unread (status=received) or read. */
    public function markStatus(Request $request, int $id)
    {
        $validated = $request->validate(['status' => 'required|in:received,read']);
        CommunicationLog::where('id', $id)->update(['status' => $validated['status']]);

        // When flagging back to unread, leave the thread (otherwise show() would
        // immediately re-mark as read on the redirect). Send to the inbox.
        if ($validated['status'] === 'received') {
            return redirect()->route('sms.index')->with('success', 'Marked unread — back to inbox.');
        }
        return back()->with('success', 'Marked read.');
    }

    /**
     * Thread view: all messages with one phone number.
     */
    /**
     * Mask sensitive patterns in an SMS body for display (SSN, full card #s).
     * The bot still has access to the real value via session.collected — only
     * the chat-log rendering is sanitised so casual viewers can't read it.
     */
    private function maskSensitive(?string $body): ?string
    {
        if ($body === null || $body === '') return $body;
        // SSN: XXX-XX-XXXX or 9 consecutive digits → ***-**-1234
        $body = preg_replace_callback('/\b\d{3}-?\d{2}-?\d{4}\b/', function ($m) {
            return '***-**-' . substr(preg_replace('/\D/', '', $m[0]), -4);
        }, $body);
        // Card numbers: 13–19 digits (with optional spaces/dashes) → •••• 1234
        $body = preg_replace_callback('/\b(?:\d[ -]?){13,19}\b/', function ($m) {
            $digits = preg_replace('/\D/', '', $m[0]);
            return '•••• •••• •••• ' . substr($digits, -4);
        }, $body);
        return $body;
    }

    public function show(string $phone)
    {
        $last10 = substr(preg_replace('/\D/', '', $phone), -10);

        // Auto-mark inbound 'received' as 'read' when the thread is opened.
        // Skip on Inertia polling (partial reloads) so newly arrived messages
        // visibly show as 'unread' until the user actually opens/refreshes.
        if (!request()->header('X-Inertia-Partial-Data')) {
            CommunicationLog::query()
                ->where('channel', 'sms')
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->where('from', 'ilike', "%{$last10}")
                ->update(['status' => 'read']);
        }

        $messages = CommunicationLog::query()
            ->where('channel', 'sms')
            ->where(function ($q) use ($last10) {
                $q->where('from', 'ilike', "%{$last10}")
                  ->orWhere('to', 'ilike', "%{$last10}");
            })
            ->orderBy('created_at')
            ->get(['id', 'direction', 'from', 'to', 'body', 'attachments', 'status', 'sent_at', 'created_at', 'user_id', 'customer_id', 'assigned_to'])
            ->map(function ($m) { $m->body = $this->maskSensitive($m->body); return $m; });

        $customer = $messages->firstWhere('customer_id', '!=', null)?->customer_id
            ? Customer::find($messages->firstWhere('customer_id', '!=', null)->customer_id)
            : null;

        $assignedTo = $messages->reverse()->firstWhere('assigned_to', '!=', null)?->assigned_to;

        return Inertia::render('Sms/Show', [
            'phone'      => $phone,
            'messages'   => $messages,
            'customer'   => $customer,
            'staff'      => User::where('email', 'like', '%@autogoco.com')->orderBy('name')->get(['id', 'name']),
            'assignedTo' => $assignedTo,
        ]);
    }

    /**
     * Inline thread fragment for embedding in Customer/Deal Show pages.
     * Returns JSON for axios consumption.
     */
    public function customerThread(Customer $customer)
    {
        // Auto-mark received -> read on first load (skip on polling partial reloads)
        if (!request()->header('X-Inertia-Partial-Data') && !request()->boolean('poll')) {
            CommunicationLog::query()
                ->where('channel', 'sms')
                ->where('customer_id', $customer->id)
                ->where('direction', 'inbound')
                ->where('status', 'received')
                ->update(['status' => 'read']);
        }

        $messages = CommunicationLog::query()
            ->where('channel', 'sms')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at')
            ->get(['id', 'direction', 'from', 'to', 'body', 'attachments', 'status', 'sent_at', 'created_at', 'assigned_to'])
            ->map(function ($m) { $m->body = $this->maskSensitive($m->body); return $m; });

        $assignedTo = $messages->reverse()->firstWhere('assigned_to', '!=', null)?->assigned_to;
        $phone = $customer->phone;

        return response()->json([
            'messages'   => $messages,
            'assignedTo' => $assignedTo,
            'phone'      => $phone,
            'staff'      => User::where('email', 'like', '%@autogoco.com')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        return strlen($d) >= 10 ? substr($d, -10) : $d;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\LeaseApplicationSession;
use Illuminate\Support\Facades\Log;

/**
 * AI-driven conversational handler used INSIDE an active session. Replaces
 * the rigid step-by-step advanceSession() prompts with a Claude-driven loop:
 *
 *   - The model sees the goal (which flow, what fields still need collecting),
 *     the customer's full thread, the latest reply, and the customer record.
 *   - It returns a JSON action: save_field / update_other_field / add_phone /
 *     ask / finalize / handoff_to_staff.
 *
 * The rule-based fallback in LeaseApplicationBot still handles the no-AI
 * path (when ai_validator_disabled = 1 or Anthropic isn't configured).
 *
 * The session.collected JSON is the source of truth — this just decides
 * what to mutate / what to text back next.
 */
class SmsAgentBot
{
    /** Required fields per flow (in display order). */
    public const REQUIRED = [
        'lease' => [
            'license_image_front', 'license_image_back', 'first_name', 'last_name',
            'date_of_birth', 'ssn', 'address', 'city', 'state', 'zip',
            'own_or_rent', 'monthly_housing', 'years_at_address', 'email',
            'employer', 'employer_address', 'employer_city', 'employer_state',
            'employer_zip', 'employer_phone', 'position', 'years_employed',
            'annual_income', 'has_coapplicant', 'vehicle_interest',
        ],
        'finance' => [],   // mirrored from lease
        'rental' => [
            'first_name', 'last_name', 'email', 'date_of_birth',
            'license_image_front', 'license_image_back',
            'address', 'has_insurance', 'pickup_date', 'pickup_location',
            'return_date', 'vehicle_preference',
        ],
        'towing' => [
            'first_name', 'last_name', 'pickup_location', 'dropoff_location',
            'vehicle', 'situation', 'wheels_turn', 'urgency',
        ],
        'bodyshop' => [
            'first_name', 'last_name', 'vehicle', 'damage_area',
            'has_photos', 'is_insurance_claim', 'preferred_drop_off', 'rental_needed',
        ],
    ];

    public function __construct(private readonly TelebroadService $telebroad) {}

    /**
     * Decide what to do with this inbound for an active session.
     * Returns true on success (something happened), false to fall through to
     * the legacy rule-based handler.
     */
    public function handle(LeaseApplicationSession $session, string $body, array $mediaUrls = []): bool
    {
        // Skip the agent for image-expecting steps; the legacy handler already
        // does OCR + side-effect work that the model can't replicate.
        $imageSteps = ['license_image_front', 'license_image_back'];
        if (in_array($session->current_step, $imageSteps, true)) return false;
        // Don't try to drive the no-active step
        if ($session->current_step === '__intent__') return false;
        if (!app(AiClient::class)->isConfigured()) return false;

        $required = self::REQUIRED[$session->flow] ?? self::REQUIRED['lease'];
        if ($session->flow === 'finance') $required = self::REQUIRED['lease'];

        $collected = $session->collected ?? [];
        $missing = array_values(array_filter($required, fn ($f) => empty($collected[$f]) && !in_array($f, $imageSteps, true)));

        $customer = $session->customer_id ? Customer::find($session->customer_id) : null;
        $thread = $this->recentThread($session->phone, 12);

        $prompt = $this->buildPrompt($session, $customer, $collected, $missing, $thread, $body);
        $decision = $this->ask($prompt);
        if (!$decision) return false;

        return $this->apply($session, $decision, $body);
    }

    private function buildPrompt(LeaseApplicationSession $s, ?Customer $cu, array $collected, array $missing, string $thread, string $reply): string
    {
        $flowLabel = match ($s->flow) {
            'lease', 'finance' => "lease/finance application",
            'rental'           => "rental reservation",
            'towing'           => "towing dispatch",
            'bodyshop'         => "bodyshop estimate",
            default            => $s->flow,
        };
        $haveJson = json_encode(array_intersect_key($collected, array_flip(array_diff(array_keys($collected), ['__update_queue__', 'license_extracted', 'license_image_front_url', 'license_image_back_url', 'license_image_front_path', 'license_image_back_path']))));
        $missingList = empty($missing) ? '(none — ready to finalize)' : implode(', ', $missing);

        $custBlock = $cu ? "Known customer #{$cu->id}: {$cu->first_name} {$cu->last_name} · {$cu->phone}" : 'Unknown sender (no customer record).';

        return <<<TXT
You are AutoGo's friendly intake assistant on SMS. We do car rentals, leasing/financing, towing, and bodyshop work in Monroe NY. Talk like a person — short, warm, no robotic re-asks.

Right now you're in the middle of a {$flowLabel}.

CONVERSATION SO FAR (oldest -> newest):
{$thread}

CUSTOMER JUST REPLIED:
"{$reply}"

CUSTOMER RECORD:
{$custBlock}

DATA WE'VE COLLECTED SO FAR:
{$haveJson}

FIELDS STILL MISSING (in priority order):
{$missingList}

CURRENT STEP THE OLD CODE THINKS WE'RE ON: {$s->current_step}

Output ONE JSON action, no prose, no code fences. Choose:

{"action":"save_field","field":"<missing field name>","value":"<cleaned value>","reply":"<short message confirming + asking next question>"}
  -- when their reply IS an answer to a missing field. Pick the right field
     (it might not be the current step). Always include the next question
     in the reply so the customer keeps moving.

{"action":"update_other","field":"<any field>","value":"<value>","reply":"<short ack + back to where we were>"}
  -- when they want to change something we already have, or fill in a
     non-priority field they volunteered. Examples: "my email is X",
     "actually update my address to Y".

{"action":"add_phone","phone":"<number>","label":"Mobile|Home|Work|Other","reply":"<short ack + next question>"}
  -- when they want to add an additional phone number to their account.

{"action":"ask","reply":"<short clarifying question>"}
  -- when their reply is unclear or off-topic and you need more info to proceed.

{"action":"finalize","reply":"<short wrap-up message>"}
  -- only when EVERY required field is filled. We'll create the deal/reservation/task.

{"action":"handoff","reply":"<short message — a team member will follow up>"}
  -- if the customer is hostile, asking something the bot can't handle (pricing
     specifics, complaints, legal questions), or wants a human.

Rules:
- Never make pricing or availability promises.
- Never say "Reply STOP" — the carrier handles that.
- Don't say "I'm an AI". Be a friendly intake assistant.
- Keep replies short — usually one or two sentences.
- For boolean fields (has_coapplicant, has_insurance, etc.), parse "yes"/"no" from natural language.
TXT;
    }

    private function ask(string $prompt): ?array
    {
        try {
            $resp = app(AiClient::class)->messages([
                'model'       => 'claude-sonnet-4-5',
                'max_tokens'  => 400,
                'temperature' => 0.2,
                'system'      => "You are a precise SMS intake bot. Output ONLY one JSON action, no prose.",
                'messages'    => [['role' => 'user', 'content' => $prompt]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data) || empty($data['action'])) {
                Log::warning('SmsAgentBot: bad JSON', ['raw' => $text]);
                return null;
            }
            return $data;
        } catch (\Throwable $e) {
            Log::warning('SmsAgentBot ask failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function apply(LeaseApplicationSession $s, array $d, string $body): bool
    {
        $collected = $s->collected ?? [];
        $reply = (string) ($d['reply'] ?? '');

        switch ($d['action']) {
            case 'save_field':
                $field = (string) ($d['field'] ?? '');
                if ($field === '') return false;
                $collected[$field] = (string) ($d['value'] ?? '');
                break;
            case 'update_other':
                $field = (string) ($d['field'] ?? '');
                if ($field === '') return false;
                $collected[$field] = (string) ($d['value'] ?? '');
                if ($s->customer_id) $this->propagateCustomerField($s->customer_id, $field, $collected[$field]);
                break;
            case 'add_phone':
                $phone = (string) ($d['phone'] ?? '');
                $label = (string) ($d['label'] ?? 'Other');
                if ($phone !== '' && $s->customer_id) {
                    CustomerPhone::create([
                        'customer_id'    => $s->customer_id,
                        'phone'          => $phone,
                        'label'          => $label,
                        'is_sms_capable' => true,
                    ]);
                }
                break;
            case 'finalize':
                $s->update(['collected' => $collected]);
                $this->sendReply($s->phone, $reply);
                // Hand control back to LeaseApplicationBot for the actual
                // finalize() (creates Deal / Reservation / OfficeTask).
                return $this->triggerFinalizeViaLegacy($s);
            case 'handoff':
                $s->update(['aborted_at' => now(), 'collected' => $collected]);
                $this->sendReply($s->phone, $reply ?: "Got it — a team member will reach out shortly.");
                return true;
            case 'ask':
            default:
                if ($reply === '') return false;
                $this->sendReply($s->phone, $reply);
                $s->update(['last_inbound_at' => now()]);
                return true;
        }

        // For save_field / update_other / add_phone we recompute what's missing
        // and let the next question come from the same model reply.
        $next = $this->firstMissing($s->flow, $collected);
        $s->update([
            'collected'    => $collected,
            'current_step' => $next ?: '__done__',
            'last_inbound_at' => now(),
        ]);
        if ($reply !== '') $this->sendReply($s->phone, $reply);
        return true;
    }

    private function firstMissing(string $flow, array $collected): ?string
    {
        $required = self::REQUIRED[$flow] ?? self::REQUIRED['lease'];
        if ($flow === 'finance') $required = self::REQUIRED['lease'];
        foreach ($required as $f) if (empty($collected[$f])) return $f;
        return null;
    }

    private function propagateCustomerField(int $customerId, string $field, string $value): void
    {
        $cu = Customer::find($customerId);
        if (!$cu) return;
        $colMap = [
            'first_name'=>'first_name','last_name'=>'last_name','email'=>'email',
            'address'=>'address','city'=>'city','state'=>'state','zip'=>'zip',
            'date_of_birth'=>'date_of_birth',
        ];
        if (isset($colMap[$field])) {
            try { $cu->update([$colMap[$field] => $value ?: null]); } catch (\Throwable) {}
        }
    }

    private function recentThread(string $phone, int $limit): string
    {
        $last10 = substr(preg_replace('/\D/', '', $phone), -10);
        return CommunicationLog::query()
            ->where('channel', 'sms')
            ->where(function ($q) use ($last10) {
                $q->where('from', 'ilike', "%{$last10}")->orWhere('to', 'ilike', "%{$last10}");
            })
            ->orderByDesc('id')->limit($limit)->get(['direction', 'body'])
            ->reverse()->values()
            ->map(fn ($m) => ($m->direction === 'inbound' ? 'CUST: ' : 'BOT: ') . trim((string) $m->body))
            ->implode("\n");
    }

    private function sendReply(string $toPhone, string $message): void
    {
        $result = $this->telebroad->sendSms($toPhone, $message);
        $last10 = substr(preg_replace('/\D/', '', $toPhone), -10);
        $customer = Customer::where('phone', 'ilike', "%{$last10}")->first();
        CommunicationLog::create([
            'subject_type' => $customer ? Customer::class : null,
            'subject_id'   => $customer?->id,
            'customer_id'  => $customer?->id,
            'channel'      => 'sms',
            'direction'    => 'outbound',
            'from'         => (string) (\App\Models\Setting::getValue('telebroad_phone_number') ?: config('services.telebroad.phone_number')),
            'to'           => $toPhone,
            'body'         => $message,
            'attachments'  => ['_bot' => true],
            'external_ref' => $result['external_id'] ?? null,
            'status'       => ($result['success'] ?? false) ? 'sent' : 'failed',
            'sent_at'      => now(),
        ]);
    }

    /** Mark current_step __done__ so legacy LeaseApplicationBot::advanceSession's next call finalizes. */
    private function triggerFinalizeViaLegacy(LeaseApplicationSession $s): bool
    {
        $s->update(['current_step' => '__done__']);
        try {
            app(LeaseApplicationBot::class)->handleInbound($s->phone, '', $s->customer, []);
        } catch (\Throwable $e) {
            Log::warning('Finalize delegation failed', ['error' => $e->getMessage()]);
        }
        return true;
    }
}

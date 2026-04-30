<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Deal;
use App\Models\LeaseApplicationSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SMS-driven bot. Handles two flows:
 *
 *   LEASE  — verified field set from `AutoGo Leasing Application_260419.pdf`
 *   RENTAL — verified field set from rental agreement (docs/RENTAL_AGREEMENT.md):
 *            license image + insurance check + dates + vehicle preference.
 *
 * Triggers (case-insensitive):
 *   "lease" | "finance" | "leasing" | "apply"   -> LEASE
 *   "rent"  | "rental"                          -> RENTAL
 *   Brand-new phone with anything else          -> ask which flow
 *
 * "STOP" aborts at any time.
 */
class LeaseApplicationBot
{
    public const STEPS_LEASE = [
        ['key' => 'license_image_front', 'prompt' => "Let's start with your DRIVER'S LICENSE. Please TEXT a photo of the FRONT of your license. (We'll auto-read your name, DL #, expiration, and address.)", 'expects' => 'image'],
        ['key' => 'license_image_back',  'prompt' => "Now please TEXT a photo of the BACK of your license.", 'expects' => 'image'],
        ['key' => 'first_name',        'prompt' => "Great — what's your FIRST name? (we'll confirm what we read)"],
        ['key' => 'last_name',         'prompt' => "Thanks {first_name}. And your LAST name?"],
        ['key' => 'date_of_birth',     'prompt' => "Date of birth? (MM/DD/YYYY)"],
        ['key' => 'ssn_last4',         'prompt' => "Last 4 digits of your Social Security number? (just the last 4 — full SSN is collected later via secure form when we run the credit pull)."],
        ['key' => 'address',           'prompt' => "Current home street address?"],
        ['key' => 'city',              'prompt' => "City?"],
        ['key' => 'state',             'prompt' => "State? (2-letter, e.g. NY)"],
        ['key' => 'zip',               'prompt' => "ZIP code?"],
        ['key' => 'own_or_rent',       'prompt' => "Do you OWN or RENT your home? (own / rent)"],
        ['key' => 'monthly_housing',   'prompt' => "Monthly mortgage/rent payment? ($)"],
        ['key' => 'years_at_address',  'prompt' => "How many years at this address?"],
        ['key' => 'email',             'prompt' => "Email address?"],
        ['key' => 'employer',          'prompt' => "Current employer name?"],
        ['key' => 'employer_address',  'prompt' => "Employer street address?"],
        ['key' => 'employer_city',     'prompt' => "Employer city?"],
        ['key' => 'employer_state',    'prompt' => "Employer state? (2-letter)"],
        ['key' => 'employer_zip',      'prompt' => "Employer ZIP?"],
        ['key' => 'employer_phone',    'prompt' => "Employer phone?"],
        ['key' => 'position',          'prompt' => "Your position / job title?"],
        ['key' => 'years_employed',    'prompt' => "Years employed there?"],
        ['key' => 'annual_income',     'prompt' => "Annual income? ($)"],
        ['key' => 'has_coapplicant',   'prompt' => "Do you have a CO-APPLICANT? (yes / no)"],

        ['key' => 'co_first_name',       'prompt' => "Co-applicant FIRST name?",        'requires' => 'has_coapplicant'],
        ['key' => 'co_last_name',        'prompt' => "Co-applicant LAST name?",         'requires' => 'has_coapplicant'],
        ['key' => 'co_date_of_birth',    'prompt' => "Co-applicant date of birth? (MM/DD/YYYY)", 'requires' => 'has_coapplicant'],
        ['key' => 'co_ssn_last4',        'prompt' => "Last 4 digits of co-applicant's SSN? (full SSN later via secure form)", 'requires' => 'has_coapplicant'],
        ['key' => 'co_phone',            'prompt' => "Co-applicant phone?",             'requires' => 'has_coapplicant'],
        ['key' => 'co_address',          'prompt' => "Co-applicant street address?",    'requires' => 'has_coapplicant'],
        ['key' => 'co_city',             'prompt' => "Co-applicant city?",              'requires' => 'has_coapplicant'],
        ['key' => 'co_state',            'prompt' => "Co-applicant state?",             'requires' => 'has_coapplicant'],
        ['key' => 'co_zip',              'prompt' => "Co-applicant ZIP?",               'requires' => 'has_coapplicant'],
        ['key' => 'co_own_or_rent',      'prompt' => "Co-applicant: own or rent?",      'requires' => 'has_coapplicant'],
        ['key' => 'co_monthly_housing',  'prompt' => "Co-applicant monthly housing? ($)", 'requires' => 'has_coapplicant'],
        ['key' => 'co_years_at_address', 'prompt' => "Co-applicant years at address?",  'requires' => 'has_coapplicant'],
        ['key' => 'co_employer',         'prompt' => "Co-applicant employer?",          'requires' => 'has_coapplicant'],
        ['key' => 'co_position',         'prompt' => "Co-applicant position?",          'requires' => 'has_coapplicant'],
        ['key' => 'co_annual_income',    'prompt' => "Co-applicant annual income? ($)", 'requires' => 'has_coapplicant'],

        ['key' => 'vehicle_interest', 'prompt' => "Last question — what vehicle are you interested in? (year/make/model, or just describe). Lender approval and final terms are subject to a credit decision."],
        ['key' => '__done__',         'prompt' => "Got it — your application is in. A team member will reach out with options after the credit review."],
    ];

    public const STEPS_RENTAL = [
        ['key' => 'first_name',        'prompt' => "Great — let's set up your rental. What's your FIRST name?"],
        ['key' => 'last_name',         'prompt' => "Thanks {first_name}. LAST name?"],
        ['key' => 'email',             'prompt' => "Email address?"],
        ['key' => 'date_of_birth',     'prompt' => "Date of birth? (MM/DD/YYYY) — required for the rental agreement."],
        ['key' => 'license_image_front', 'prompt' => "Please TEXT a photo of the FRONT of your driver's license. (We'll auto-read your name, DL #, expiration, and address.)", 'expects' => 'image'],
        ['key' => 'license_image_back',  'prompt' => "Now please TEXT a photo of the BACK of your license.", 'expects' => 'image'],
        ['key' => 'address_confirmation', 'prompt' => "Got your license. Is this address correct? Reply YES, or text the correct address."],
        ['key' => 'has_insurance',     'prompt' => "Do you have your own auto insurance? (yes / no)"],
        ['key' => 'insurance_company', 'prompt' => "Insurance company name?", 'requires' => 'has_insurance'],
        ['key' => 'insurance_policy',  'prompt' => "Policy number?",          'requires' => 'has_insurance'],
        ['key' => 'pickup_date',       'prompt' => "Pick-up date? (MM/DD/YYYY)"],
        ['key' => 'pickup_location',   'prompt' => "Pick up at MONROE or MONSEY?"],
        ['key' => 'return_date',       'prompt' => "Return date? (MM/DD/YYYY)"],
        ['key' => 'vehicle_preference','prompt' => "Any vehicle preference? (e.g. SUV, sedan, minivan — or 'whatever's available'). Final vehicle and pricing confirmed by our team."],
        ['key' => '__done__',          'prompt' => "Got it — your request is in. A team member will text you to confirm vehicle availability and pricing."],
    ];

    public const STEPS_TOWING = [
        ['key' => 'first_name',      'prompt' => "🚛 AutoGo Towing — quick details please. ⚠️ If this is a medical or roadway emergency, call 911 first.\n\nYour FIRST name?"],
        ['key' => 'last_name',       'prompt' => "Last name?"],
        ['key' => 'pickup_location', 'prompt' => "Where is the vehicle? (street address, intersection, or landmark)"],
        ['key' => 'dropoff_location','prompt' => "Where should it be towed TO? (e.g. AutoGo bodyshop, your home, dealership)"],
        ['key' => 'vehicle',         'prompt' => "Year/make/model/color of the vehicle? (e.g. 2021 Toyota Camry, white)"],
        ['key' => 'situation',       'prompt' => "What happened? (accident / breakdown / lockout / battery / out of gas / other)"],
        ['key' => 'wheels_turn',     'prompt' => "Do all 4 wheels still turn? (yes / no — affects truck type)"],
        ['key' => 'urgency',         'prompt' => "How urgent? (now / today / tomorrow / scheduled)"],
        ['key' => '__done__',        'prompt' => "Got it — request received. A dispatcher will call you back to confirm vehicle availability and ETA."],
    ];

    public const STEPS_BODYSHOP = [
        ['key' => 'first_name',     'prompt' => "🔧 AutoGo Bodyshop — let's get an estimate. Your FIRST name?"],
        ['key' => 'last_name',      'prompt' => "Last name?"],
        ['key' => 'vehicle',        'prompt' => "Year/make/model? (e.g. 2022 Honda Accord)"],
        ['key' => 'damage_area',    'prompt' => "What part of the car is damaged? (front bumper / driver door / hood / etc.)"],
        ['key' => 'has_photos',     'prompt' => "Can you TEXT us photos of the damage? (yes / no — if yes, just reply with photos and we'll attach them)"],
        ['key' => 'is_insurance_claim', 'prompt' => "Is this an insurance claim? (yes / no)"],
        ['key' => 'insurance_company', 'prompt' => "Which insurance company?", 'requires' => 'is_insurance_claim'],
        ['key' => 'claim_number',      'prompt' => "Claim number? (or 'pending' if not opened yet)", 'requires' => 'is_insurance_claim'],
        ['key' => 'preferred_drop_off','prompt' => "When would you like to drop off the car? (today / this week / next week / specific date)"],
        ['key' => 'rental_needed',     'prompt' => "Will you need a rental car while it's being repaired? (yes / no — we'll let you know if a loaner is available)"],
        ['key' => '__done__',          'prompt' => "Thanks — your estimate request is in. A team member will text/call you to schedule a drop-off. The estimate itself is provided after we see the vehicle."],
    ];

    public const TRIGGERS_LEASE   = ['apply', 'lease', 'leasing', 'application'];
    public const TRIGGERS_RENTAL  = ['rent', 'rental', 'rentals', 'hire'];
    public const TRIGGERS_FINANCE = ['finance', 'financing', 'loan'];
    public const TRIGGERS_TOWING  = ['tow', 'towing', 'stuck', 'breakdown', 'lockout'];
    public const TRIGGERS_BODYSHOP= ['body', 'bodyshop', 'collision', 'repair', 'estimate'];
    public const STOP_WORDS       = ['stop', 'cancel', 'quit', 'unsubscribe'];

    public function __construct(private readonly TelebroadService $telebroad) {}

    /**
     * Manually kick off a bot conversation to a customer's phone. Used by the
     * "Text Application" button in the Customer Show page — staff picks a flow
     * (lease or rental) and the bot sends the intro + first question.
     */
    public function triggerManually(string $phone, string $flow, ?Customer $customer = null): void
    {
        // Abandon any active session for this phone so the new one is clean
        LeaseApplicationSession::where('phone', $phone)
            ->whereNull('completed_at')->whereNull('aborted_at')
            ->update(['aborted_at' => now()]);

        // If no customer passed but we can match the number, link them
        if (!$customer) {
            $last10 = substr(preg_replace('/\D/', '', $phone), -10);
            $customer = Customer::where('phone', 'ilike', "%{$last10}")->first();
        }

        $this->startFlow($phone, $customer, $flow);
    }

    /**
     * @param array $mediaUrls Image URLs from inbound MMS (parsed from Telebroad webhook)
     * @return bool true if a reply was sent
     */
    public function handleInbound(string $fromPhone, string $body, ?Customer $customer, array $mediaUrls = []): bool
    {
        $text = strtolower(trim($body));
        $session = LeaseApplicationSession::where('phone', $fromPhone)
            ->whereNull('completed_at')->whereNull('aborted_at')
            ->latest('id')->first();

        // STOP at any time
        if ($session && in_array($text, self::STOP_WORDS, true)) {
            $session->update(['aborted_at' => now()]);
            $this->reply($fromPhone, "OK — cancelled. Text HELP to start over.");
            return true;
        }

        // Even mid-session, an exact trigger word should restart fresh — lets
        // a customer escape a stuck flow by texting "help" / "car" / "rental" / etc.
        // NOTE: bare "rent" and "body" are deliberately NOT triggers — they
        // collide with legitimate mid-session answers (own_or_rent housing
        // tenure, damage_area body part). Customers who want to start a flow
        // can text the unambiguous "rental" / "bodyshop".
        $strict = preg_replace('/[^a-z]/', '', $text);
        $isTopLevelTrigger = in_array($strict, [
            'help', 'new', 'car', 'menu', 'options', 'start',
            'lease', 'rental', 'tow', 'towing',
            'bodyshop', 'collision', 'finance', 'financing',
        ], true);

        // SECURE keyword on an SSN step — escalate to staff, do NOT collect
        // sensitive identifiers over plain SMS. Until iFields-style hosted
        // entry is built, a person collects it directly.
        if ($session && in_array($strict, ['secure', 'webform', 'link'], true)
            && in_array($session->current_step, ['ssn', 'co_ssn'], true)) {
            $session->update(['aborted_at' => now()]);
            $this->reply($session->phone,
                "Got it — we'll skip SSN over text. A finance team member will reach out so you can give it directly. Your application is paused on our end until then."
            );
            try {
                $users = \App\Models\User::where('email', 'like', '%@autogoco.com')->get();
                \Notification::send($users, new \App\Notifications\OperationalReminder(
                    "🔒 Customer wants secure SSN entry",
                    "Phone {$fromPhone} — application paused at SSN. Call them to collect it.",
                    route('sms.show', $fromPhone)
                ));
            } catch (\Throwable) {}
            return true;
        }

        if ($session && !$isTopLevelTrigger) {
            // Hard-routed control keywords must bypass the AI agent — the
            // agent often interprets them as conversational ("SECURE" got
            // mis-classified as an SSN escalation, "NEXT" can be re-read as
            // the customer wanting to move on with content, etc.). Route
            // straight to advanceSession so the explicit keyword handlers
            // there (SECURE/WEB/LINK/FORM, NEXT/SKIP/LATER, 1/2/3/LICENSE/
            // FILE/REVIEW for diff confirm) take precedence.
            $controlKeyword = strtoupper(trim($body));
            $isControl = in_array($controlKeyword, [
                'SECURE', 'WEB', 'LINK', 'FORM',
                'NEXT', 'SKIP', 'LATER',
                '1', '2', '3', 'LICENSE', 'FILE', 'REVIEW', 'L', 'F', 'R',
            ], true) || !empty($mediaUrls); // media → image step handles it directly

            // Try the AI agent first — it handles sidetracks, free-form
            // replies, and non-priority field updates naturally. Falls
            // through to the rule-based advanceSession() if the agent
            // bails (no API key, image step, unclear decision).
            $agentHandled = false;
            if (!$isControl && (string) \App\Models\Setting::getValue('ai_agent_disabled') !== '1') {
                try {
                    $agentHandled = app(\App\Services\SmsAgentBot::class)->handle($session, $body, $mediaUrls);
                } catch (\Throwable $e) {
                    Log::warning('SmsAgentBot failed, falling back', ['error' => $e->getMessage()]);
                }
            }
            if ($agentHandled) return true;
            // Try the legacy rule-based handler
            try {
                return $this->advanceSession($session, $body, $mediaUrls);
            } catch (\Throwable $e) {
                Log::warning('advanceSession failed, sending generic handoff', ['error' => $e->getMessage()]);
                // NEVER stay silent on a real customer message — send a
                // generic ack and abort the session so staff sees it.
                $session->update(['aborted_at' => now()]);
                $this->reply($session->phone, "Got your message — a team member will reach out shortly.");
                return true;
            }
        }
        if ($session && $isTopLevelTrigger) {
            $session->update(['aborted_at' => now()]);
            $session = null;  // fall through to new-conversation logic
        }

        // ── No active session: only start one on STRICT, exact trigger words. ──
        // help / new / car  → menu of all options
        // rental            → rental flow
        // lease             → lease flow
        // tow / towing      → towing flow
        // bodyshop          → bodyshop flow
        // finance           → finance flow
        // Anything else from someone with no active session → bot stays SILENT.
        // Manual triggers via triggerManually() (Customer page button) bypass this.
        $strict = preg_replace('/[^a-z]/', '', $text);  // letters only, lowercase
        $flow = match ($strict) {
            'lease'                                 => 'lease',
            'rental'                                => 'rental',
            'tow', 'towing'                         => 'towing',
            'bodyshop', 'collision'                 => 'bodyshop',
            'finance', 'financing'                  => 'finance',
            default                                 => null,
        };
        $wantsMenu = in_array($strict, ['help', 'new', 'car', 'menu', 'options', 'start'], true);

        if (!$flow && !$wantsMenu) {
            // Not a recognized trigger — silent. Staff sees the message in /sms.
            return false;
        }

        if ($flow) {
            // Block rental self-service when balance is owed
            if ($flow === 'rental' && $customer && $customer->hasOutstandingBalance()) {
                $bal = number_format((float) $customer->cached_outstanding_balance, 2);
                $this->reply($fromPhone, "Hi {$customer->first_name} — there's an outstanding balance of \${$bal} on your account. Please contact our office to resolve it before starting a new rental. Thanks!");
                return true;
            }
            $this->startFlow($fromPhone, $customer, $flow);
            return true;
        }

        // Menu request — ask which one
        $menu = "Hi! 👋 This is AutoGo. How can we help? Reply with a number or word:\n\n"
              . "1 LEASE — lease/finance a car\n"
              . "2 RENTAL — rent a car\n"
              . "3 TOW — towing\n"
              . "4 BODYSHOP — collision repair";
        $this->reply($fromPhone, $menu);
        LeaseApplicationSession::create([
            'phone' => $fromPhone, 'flow' => 'intent', 'current_step' => '__intent__',
            'collected' => [], 'customer_id' => $customer?->id, 'last_inbound_at' => now(),
        ]);
        return true;
    }


    /** Friendly prompt for each identity field when the customer wants to update it. */
    private function updatePromptFor(string $field): string
    {
        return match ($field) {
            'first_name' => "What's the correct FIRST name?",
            'last_name'  => "And the correct LAST name?",
            'address'    => "What's the correct street address?",
            'city'       => "City?",
            'state'      => "State? (2-letter, e.g. NY)",
            'zip'        => "ZIP code?",
            'phone'      => "What's the best phone number to reach you on?",
            'email'      => "What's the correct email address?",
            default      => "What's the correct {$field}?",
        };
    }

    /**
     * Ask AI which collected field(s) the customer wants to update based on
     * their free-text reply ("just my address", "the phone is wrong", etc.).
     * Returns the list of collected-keys to wipe. Has a regex fallback if
     * AI isn't configured / errors out.
     */
    private function aiPickFieldsToUpdate(string $reply, array $availableKeys): array
    {
        // Regex shortcuts first (covers 95% of real replies + works without AI)
        $r = strtolower($reply);
        $hits = [];
        $map = [
            'first_name' => ['first name', 'firstname'],
            'last_name'  => ['last name', 'lastname', 'surname'],
            'address'    => ['address', 'street'],
            'city'       => ['city'],
            'state'      => ['state'],
            'zip'        => ['zip', 'postal'],
            'phone'      => ['phone', 'number', 'cell', 'mobile'],
            'email'      => ['email', 'mail'],
        ];
        foreach ($map as $field => $needles) {
            if (!in_array($field, $availableKeys, true)) continue;
            foreach ($needles as $n) if (str_contains($r, $n)) { $hits[] = $field; break; }
        }
        if (in_array('name', explode(' ', $r), true) && !in_array('first_name', $hits, true) && !in_array('last_name', $hits, true)) {
            // bare "name" → both
            $hits[] = 'first_name'; $hits[] = 'last_name';
        }
        if (!empty($hits)) return array_values(array_unique($hits));

        // AI fallback for ambiguous replies
        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                'model'       => 'claude-haiku-4-5',
                'max_tokens'  => 80,
                'temperature' => 0,
                'system'      => 'Return JSON only.',
                'messages'    => [['role' => 'user', 'content' =>
                    "User wants to update something on file. Which field(s) from this list:\n"
                    . implode(', ', ['first_name','last_name','address','city','state','zip','phone','email']) . "\n"
                    . "Their reply: \"{$reply}\"\n"
                    . "Output JSON: {\"fields\":[\"address\"]}"
                ]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (is_array($data) && !empty($data['fields'])) {
                return array_values(array_intersect($data['fields'], ['first_name','last_name','address','city','state','zip','phone','email']));
            }
        } catch (\Throwable $e) {
            \Log::warning('aiPickFieldsToUpdate failed', ['error' => $e->getMessage()]);
        }
        return [];
    }

    private function stepsFor(string $flow): array
    {
        return match ($flow) {
            'lease', 'finance' => self::STEPS_LEASE,   // finance shares the credit-app structure
            'rental'           => self::STEPS_RENTAL,
            'towing'           => self::STEPS_TOWING,
            'bodyshop'         => self::STEPS_BODYSHOP,
            default            => self::STEPS_LEASE,
        };
    }

    private function startFlow(string $phone, ?Customer $customer, string $flow): void
    {
        // For Towing and Bodyshop we don't really need to confirm address; just go.
        $skipConfirm = in_array($flow, ['towing', 'bodyshop'], true);

        // If we already know this customer, prefill what we have and ask them
        // to confirm name + address + phone before continuing.
        if ($customer && !$skipConfirm) {
            $session = LeaseApplicationSession::create([
                'phone'        => $phone,
                'flow'         => $flow,
                'current_step' => '__confirm_existing__',
                'collected'    => array_filter([
                    'first_name'    => $customer->first_name,
                    'last_name'     => $customer->last_name,
                    'email'         => $customer->email,
                    'address'       => $customer->address,
                    'city'          => $customer->city,
                    'state'         => $customer->state,
                    'zip'           => $customer->zip,
                    'date_of_birth' => $customer->date_of_birth?->toDateString(),
                    'drivers_license_number' => $customer->drivers_license_number,
                    'dl_state'               => $customer->dl_state,
                    'dl_expiration'          => $customer->dl_expiration?->toDateString(),
                    'insurance_company'      => $customer->insurance_company,
                    'insurance_policy'       => $customer->insurance_policy,
                ]),
                'customer_id'     => $customer->id,
                'last_inbound_at' => now(),
            ]);

            $intro = match ($flow) {
                'lease', 'finance' => "Hi {$customer->first_name}! 👋 This is AutoGo Leasing — starting your application.",
                'rental'           => "Hi {$customer->first_name}! 👋 This is AutoGo Rentals — setting up your rental.",
                'towing'           => "Hi {$customer->first_name}! 👋 This is AutoGo Towing.",
                'bodyshop'         => "Hi {$customer->first_name}! 👋 This is AutoGo Bodyshop.",
                default            => "Hi {$customer->first_name}! 👋",
            };
            // For lease/finance, also offer the web form as a faster
            // alternative to the SMS Q&A. Either path lands in the same
            // session — they can switch back and forth.
            if (in_array($flow, ['lease', 'finance'], true)) {
                $intro .= "\n\nFastest way: tap to fill out the form ({$session->apply_url}) — or just keep replying here and I'll ask you piece by piece.";
            }
            $this->reply($phone, $intro);

            $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
            $addr = trim(($customer->address ?? '') . ', ' . ($customer->city ?? '') . ', ' . ($customer->state ?? '') . ' ' . ($customer->zip ?? ''), ' ,');
            $confirmMsg = "I have you on file as:\n\n  Name: {$name}\n  Address: " . ($addr ?: '(none on file)') . "\n  Phone: {$phone}\n\nIs that all still correct? Reply YES to continue, or NO to update.";
            $this->reply($phone, $confirmMsg);
            return;
        }

        // Towing/bodyshop or no-existing-customer path: pre-fill anything we
        // already know so the bot doesn't waste questions asking for it.
        $steps = $this->stepsFor($flow);
        $prefill = $customer ? array_filter([
            'first_name' => $customer->first_name,
            'last_name'  => $customer->last_name,
            'email'      => $customer->email,
            'address'    => $customer->address,
            'city'       => $customer->city,
            'state'      => $customer->state,
            'zip'        => $customer->zip,
        ]) : [];

        $first = $this->firstUnfilledStep($steps, $prefill) ?? $steps[0];
        $session = LeaseApplicationSession::create([
            'phone'        => $phone,
            'flow'         => $flow,
            'current_step' => $first['key'],
            'collected'    => $prefill,
            'customer_id'  => $customer?->id,
            'last_inbound_at' => now(),
        ]);

        $intro = match ($flow) {
            'lease', 'finance' => "Hi! 👋 This is AutoGo Leasing — let's start your application.",
            'rental'           => "Hi! 👋 This is AutoGo Rentals — let's set up your rental.",
            'towing'           => "Hi! 👋 This is AutoGo Towing.",
            'bodyshop'         => "Hi! 👋 This is AutoGo Bodyshop — let's get a quick estimate request started.",
            default            => "Hi! 👋 This is AutoGo.",
        };
        $this->reply($phone, $intro);
        $this->reply($phone, $this->renderPrompt($first, $session));
    }

    private function advanceSession(LeaseApplicationSession $session, string $body, array $mediaUrls): bool
    {
        $text = strtolower(trim($body));

        // Intent-pick session: accept either keyword or menu number
        if ($session->flow === 'intent') {
            $digit = trim($text);
            $flowMap = ['1' => 'lease', '2' => 'rental', '3' => 'finance', '4' => 'towing', '5' => 'bodyshop'];
            $chosen = $flowMap[$digit] ?? null;
            if (!$chosen) {
                if ($this->matchesAny($text, self::TRIGGERS_LEASE))  $chosen = 'lease';
                elseif ($this->matchesAny($text, self::TRIGGERS_RENTAL)) $chosen = 'rental';
                elseif (str_contains($text, 'tow'))     $chosen = 'towing';
                elseif (str_contains($text, 'body') || str_contains($text, 'collision') || str_contains($text, 'repair')) $chosen = 'bodyshop';
                elseif (str_contains($text, 'finance')) $chosen = 'finance';
            }
            if ($chosen) {
                $session->delete();
                $this->startFlow($session->phone, $session->customer, $chosen);
                return true;
            }
            $this->reply($session->phone, "Please reply with one of: 1 LEASE, 2 RENTAL, 3 TOW, 4 BODYSHOP.");
            return true;
        }

        // Existing-customer confirmation step.
        if ($session->current_step === '__confirm_existing__') {
            $steps = $this->stepsFor($session->flow);
            $collected = $session->collected ?? [];
            if (in_array($text, ['yes', 'y', 'correct', 'confirm', 'right', 'yep', 'yup', 'ok'], true)) {
                $next = $this->firstUnfilledStep($steps, $collected);
                $session->update(['current_step' => $next['key'] ?? '__done__']);
                if ($next === null || $next['key'] === '__done__') { $this->finalize($session); return true; }
                $this->reply($session->phone, "Great — continuing.");
                $this->reply($session->phone, $this->renderPrompt($next, $session));
                return true;
            }
            // They said NO (or anything else). Don't wipe everything — ask
            // which field needs updating, then only re-collect that one.
            $session->update(['current_step' => '__confirm_what_to_update__']);
            $this->reply($session->phone, "No problem — what needs to be updated? (e.g. \"my address\", \"phone number\", \"name\")");
            return true;
        }

        // Customer told us "what to update" — let AI pick the field(s),
        // queue them for collection, ask the first one.
        if ($session->current_step === '__confirm_what_to_update__') {
            $collected = $session->collected ?? [];
            $fieldsToFix = $this->aiPickFieldsToUpdate($body, array_keys($collected));
            if (empty($fieldsToFix)) {
                $this->reply($session->phone, "Sorry, didn't catch that — which field? (name / address / city / state / zip / phone / email)");
                return true;
            }
            // Wipe what they want to update + queue them on the session
            foreach ($fieldsToFix as $k) unset($collected[$k]);
            $collected['__update_queue__'] = $fieldsToFix;
            $first = $fieldsToFix[0];
            $session->update([
                'collected'    => $collected,
                'current_step' => '__update_' . $first . '__',
            ]);
            $this->reply($session->phone, "Got it — let's update: " . implode(', ', $fieldsToFix));
            $this->reply($session->phone, $this->updatePromptFor($first));
            return true;
        }

        // Inside an active update of a single identity field
        if (str_starts_with($session->current_step, '__update_')) {
            $field = substr($session->current_step, strlen('__update_'), -2);
            $collected = $session->collected ?? [];
            $collected[$field] = $this->normalize($field, trim($body));
            $queue = (array) ($collected['__update_queue__'] ?? []);
            array_shift($queue); // remove the one we just answered
            if (!empty($queue)) {
                $collected['__update_queue__'] = $queue;
                $session->update(['collected' => $collected, 'current_step' => '__update_' . $queue[0] . '__']);
                $this->reply($session->phone, $this->updatePromptFor($queue[0]));
                return true;
            }
            // Updates done → go to the first unfilled step of the regular flow
            unset($collected['__update_queue__']);
            $steps = $this->stepsFor($session->flow);
            $next  = $this->firstUnfilledStep($steps, $collected);
            $session->update(['collected' => $collected, 'current_step' => $next['key'] ?? '__done__']);
            if ($next === null || $next['key'] === '__done__') { $this->finalize($session); return true; }
            $this->reply($session->phone, "Updated. Continuing.");
            $this->reply($session->phone, $this->renderPrompt($next, $session));
            return true;
        }

        $steps   = $this->stepsFor($session->flow);
        $stepIdx = $this->indexOfStep($steps, $session->current_step);
        if ($stepIdx === null) return false;
        $step = $steps[$stepIdx];
        $collected = $session->collected ?? [];

        // Pending identity-confirmation reply (set when license OCR found
        // values that differ from on-file). User answers LICENSE / FILE /
        // <new value>; we apply, clear the flag, then advance to next step.
        if (!empty($collected['_pending_identity_confirm']) && empty($mediaUrls)) {
            return $this->handleIdentityConfirmResponse($session, $step, $stepIdx, $collected, $body);
        }

        // SECURE reply on any text-input step → send a SINGLE-STEP secure
        // page just for that one field (or the full form for non-mapped
        // steps). The SMS flow STAYS ACTIVE — we re-send the current step's
        // prompt right after, so the customer can answer either via web or
        // by just replying here. Whichever arrives first wins.
        if (($step['expects'] ?? null) !== 'image' && $step['key'] !== '__done__') {
            $upper = strtoupper(trim($body));
            if (in_array($upper, ['SECURE', 'WEB', 'LINK', 'FORM'], true)) {
                $stepUrl = rtrim(config('app.url', 'https://app.autogoco.com'), '/')
                    . '/apply/' . $session->web_token . '/step/' . $step['key'];
                $this->reply($session->phone,
                    "Secure link for this question only:\n{$stepUrl}\n\n" .
                    "Or just reply here — whichever's easier."
                );
                // Re-send the current prompt so the SMS path is still active.
                // Customer can answer via web OR text — first one in wins.
                $this->reply($session->phone, $this->renderPrompt($step, $session));
                $session->update(['last_inbound_at' => now()]);
                return true;
            }
        }

        // Image step (license_image_front / license_image_back) needs MMS
        if (($step['expects'] ?? null) === 'image') {
            $reply = strtoupper(trim($body));

            // SECURE on image step → combined front+back upload page (NOT
            // single-step), so they can do both sides in one go without
            // navigating twice. SMS path stays active in case they prefer
            // to text the photos instead.
            if (in_array($reply, ['SECURE', 'WEB', 'LINK', 'FORM'], true)) {
                $licenseUrl = rtrim(config('app.url', 'https://app.autogoco.com'), '/')
                    . '/apply/' . $session->web_token . '/license';
                $this->reply($session->phone,
                    "Upload both sides here instead of MMS:\n{$licenseUrl}\n\n" .
                    "Or just text the photos — whichever's easier."
                );
                $this->reply($session->phone, $this->renderPrompt($step, $session));
                $session->update(['last_inbound_at' => now()]);
                return true;
            }

            // Customer-driven skip — they can reply NEXT / SKIP / LATER to
            // jump past this step. The image gets flagged as missing on the
            // session so staff knows to follow up; the application keeps
            // moving instead of getting stuck forever.
            if (in_array($reply, ['NEXT', 'SKIP', 'LATER'], true)) {
                $collected['_skipped_' . $step['key']] = now()->toIso8601String();
                $collected['_needs_staff_followup'] = true;
                Log::error('Image step skipped by customer', ['session_id' => $session->id, 'step' => $step['key']]);
                return $this->skipImageStepAndAdvance($session, $step, $stepIdx, $collected,
                    "No problem — I'll skip the photo for now and have the team follow up. Continuing.");
            }
            if (empty($mediaUrls)) {
                $side = str_contains($step['key'], 'back') ? 'BACK' : 'FRONT';
                $this->reply($session->phone,
                    "I need an actual photo — please TEXT (MMS) the {$side} of your driver's license.\n\n" .
                    "Or reply NEXT to skip for now and finish the rest first."
                );
                return true;
            }
            $extracted = $this->ingestLicenseImage($session, $mediaUrls[0]);

            // Per-step retry counter — after 2 failed attempts at the same
            // image step, give up gracefully and advance with a "team will
            // follow up" note instead of bouncing the customer forever.
            $retryKey = '_retries_' . $step['key'];
            $retries  = (int) ($collected[$retryKey] ?? 0);

            // Hard-failure: if we couldn't even fetch/store the image, tell the
            // user instead of silently advancing to the next prompt with an
            // empty address line.
            if (empty($extracted['_stored_path'])) {
                Log::warning('License image ingest had no stored path', [
                    'session_id' => $session->id, 'step' => $step['key'], 'attempt' => $retries + 1, 'media' => $mediaUrls[0] ?? null,
                ]);
                if ($retries >= 1) {
                    return $this->skipImageStepAndAdvance($session, $step, $stepIdx, $collected,
                        "I'm still having trouble with that photo — I can't process it right now. " .
                        "We'll follow up with you on this. Moving on for now.");
                }
                $collected[$retryKey] = $retries + 1;
                $session->update(['collected' => $collected, 'last_inbound_at' => now()]);
                $this->reply($session->phone,
                    "Sorry, I had trouble receiving your photo — please try sending it again.\n\n" .
                    "Or reply NEXT to skip for now and finish the rest first."
                );
                return true; // stay on same step
            }

            // Side + obstruction gate — runs for BACK only. For FRONT, the
            // OCR-field-count gate below is a stronger and more reliable
            // signal: if Opus extracted 4+ of the 6 key identity fields,
            // the photo is good enough. The Opus verifier was over-
            // rejecting valid front photos with false-positive "bottom
            // cut off" reasons even when all 6 fields were readable.
            // BACK has no OCR data to count so we still need the verifier
            // for it.
            $expectedSide = $step['key'] === 'license_image_back' ? 'back' : 'front';
            $verify = $expectedSide === 'back'
                ? $this->verifyLicenseSide($extracted['_stored_path'] ?? null, 'back')
                : ['valid' => true, 'reason' => ''];
            if (!empty($verify) && empty($verify['valid'])) {
                $reason = $verify['reason'] ?: 'looks cut off or covered';
                Log::error('License side/obstruction check failed', [
                    'session_id' => $session->id, 'expected' => $expectedSide, 'attempt' => $retries + 1, 'reason' => $reason,
                ]);
                if ($retries >= 1) {
                    $collected[$step['key'] . '_url']  = $mediaUrls[0];
                    $collected[$step['key'] . '_path'] = $extracted['_stored_path'] ?? null;
                    $collected['_needs_license_review'] = true;
                    return $this->skipImageStepAndAdvance($session, $step, $stepIdx, $collected,
                        "Still can't get a clean photo — I'll save what you sent and have the team verify. Moving on for now.");
                }
                $collected[$retryKey] = $retries + 1;
                $session->update(['collected' => $collected, 'last_inbound_at' => now()]);
                $sideUp = strtoupper($expectedSide);
                $this->reply($session->phone,
                    "Can't accept this photo — {$reason}. " .
                    "Please re-send the {$sideUp} of your license: whole card visible, all four corners, nothing covering it (no wires, fingers, papers).\n\n" .
                    "Or reply NEXT to skip for now and finish the rest first."
                );
                return true;
            }

            // Quality gate (FRONT only): require ≥4 of the 6 key identity
            // fields. Previously we passed if ANY one was extracted, so a
            // cropped photo where OCR caught the name + DL # but missed DOB
            // and address would advance the bot — exactly the failure mode
            // we hit. Demanding a fuller extraction forces a re-ask on
            // partial / cropped photos.
            if ($step['key'] === 'license_image_front') {
                $keyFields = ['first_name', 'last_name', 'drivers_license_number', 'date_of_birth', 'address', 'dl_state'];
                $present = 0;
                foreach ($keyFields as $f) if (!empty($extracted[$f])) $present++;
                if ($present < 4) {
                    Log::error('License front OCR partial — re-asking', [
                        'session_id' => $session->id, 'attempt' => $retries + 1, 'present_count' => $present,
                    ]);
                    if ($retries >= 1) {
                        // Save the URL so staff can review, then advance.
                        $collected[$step['key'] . '_url']  = $mediaUrls[0];
                        $collected[$step['key'] . '_path'] = $extracted['_stored_path'] ?? null;
                        return $this->skipImageStepAndAdvance($session, $step, $stepIdx, $collected,
                            "I still can't read your license clearly — I'll save what you sent and have the team " .
                            "follow up to verify your details. Moving on for now.");
                    }
                    $collected[$retryKey] = $retries + 1;
                    $session->update(['collected' => $collected, 'last_inbound_at' => now()]);
                    $this->reply($session->phone,
                        "I couldn't read your license. Please re-send with: " .
                        "(1) the WHOLE license visible — all four corners, no fingers/thumbs covering anything, " .
                        "(2) right-side up (not rotated/sideways), " .
                        "(3) clear focus and good lighting (no glare).\n\n" .
                        "Or reply NEXT to skip for now and finish the rest first."
                    );
                    return true; // stay on same step
                }
            }

            $collected[$step['key'] . '_url'] = $mediaUrls[0];      // license_image_front_url, _back_url
            $collected[$step['key'] . '_path'] = $extracted['_stored_path'] ?? null;

            // Save the CustomerDocument NOW (not at finalize) so the license
            // shows up on the deal's Documents tab immediately. Without this
            // the file exists on disk but has no DB row, and the Documents
            // tab — which reads from customer.documents — can't see it.
            // Idempotent: skips if a document already exists for this exact
            // path. The finalize() pass runs the same code; firstOrCreate-
            // style guards keep it from duplicating.
            if ($session->customer && !empty($extracted['_stored_path'])) {
                $side = str_contains($step['key'], 'back') ? 'back' : 'front';
                CustomerDocument::firstOrCreate(
                    [
                        'customer_id' => $session->customer->id,
                        'path'        => $extracted['_stored_path'],
                    ],
                    [
                        'type'          => 'drivers_license_' . $side,
                        'label'         => trim(($collected['first_name'] ?? '') . ' ' . ($collected['last_name'] ?? '') . ' · ' . ($collected['drivers_license_number'] ?? '') . " ({$side})", ' ·'),
                        'disk'          => 'public',
                        'original_name' => basename($extracted['_stored_path']),
                        'mime_type'     => 'image/jpeg',
                        'expires_at'    => $this->parseDate($collected['dl_expiration'] ?? null),
                        'uploaded_by'   => null,
                    ]
                );
            }

            // Only the FRONT is OCR'd for identity fields
            if ($step['key'] === 'license_image_front') {
                $collected['license_extracted'] = $extracted;

                // Compare extracted identity fields against the on-file
                // customer profile. If any differ (DOB, address, name), pause
                // here and ask which is correct before advancing. Returning
                // customers especially — we don't want to silently overwrite
                // their saved address with whatever the license says, OR
                // accept a license without flagging that the DOB doesn't
                // match what we have.
                $diffs = $this->licenseFieldDiffs($session->customer, $extracted);
                if (!empty($diffs)) {
                    $collected['_pending_identity_confirm'] = $diffs;
                    $session->update([
                        'collected' => $collected,
                        'last_inbound_at' => now(),
                    ]);
                    $msg = "Got your license. Quick confirm — your license info doesn't match what we have on file:\n\n";
                    foreach ($diffs as $field => $pair) {
                        $label = ['date_of_birth' => 'DATE OF BIRTH', 'address' => 'ADDRESS', 'first_name' => 'FIRST NAME', 'last_name' => 'LAST NAME'][$field] ?? strtoupper($field);
                        // Display dates MM/DD/YYYY (US convention) — internal
                        // storage stays YYYY-MM-DD ISO.
                        $licDisp  = $field === 'date_of_birth' ? $this->displayDate($pair['license']) : $pair['license'];
                        $fileDisp = $field === 'date_of_birth' ? $this->displayDate($pair['file'])    : $pair['file'];
                        $msg .= "{$label}\n  License: " . ($licDisp ?: '—') . "\n  On file: " . ($fileDisp ?: '—') . "\n\n";
                    }
                    $msg .= "Reply:\n  1 — use LICENSE info\n  2 — keep what's ON FILE\n  3 — read looks wrong, flag for team review\nOr text the correct value.";
                    $this->reply($session->phone, $msg);
                    return true; // stay on this step until user confirms
                }

                foreach (['first_name','last_name','date_of_birth','address','city','state','zip'] as $k) {
                    if (empty($collected[$k]) && !empty($extracted[$k])) $collected[$k] = $extracted[$k];
                }
                if (!empty($extracted['drivers_license_number'])) $collected['drivers_license_number'] = $extracted['drivers_license_number'];
                if (!empty($extracted['dl_state']))               $collected['dl_state']               = $extracted['dl_state'];
                if (!empty($extracted['dl_expiration']))          $collected['dl_expiration']          = $extracted['dl_expiration'];
            }
        } elseif ($step['key'] !== '__done__') {
            // AI validates the answer matches what was asked. May reject (re-ask
            // with hint), skip (customer refused), accept (save), or escalate.
            $validation = app(\App\Services\SmsAiValidator::class)
                ->validate($step['key'], $this->renderPrompt($step, $session), $body);

            if ($validation['action'] === 'reject') {
                // Don't advance — re-ask with the model's hint.
                $hint = $validation['message'] ?: "Sorry, I didn't catch that.";
                $this->reply($session->phone, "{$hint}\n\n" . $this->renderPrompt($step, $session));
                $session->update(['last_inbound_at' => now()]);
                return true;
            }
            if ($validation['action'] === 'skip') {
                $collected[$step['key']] = '';  // empty, but advance
            } elseif ($validation['action'] === 'escalate') {
                $session->update(['aborted_at' => now(), 'last_inbound_at' => now()]);
                $msg = $validation['message'] ?: "Got it — a team member will follow up shortly.";
                $this->reply($session->phone, $msg);
                return true;
            } else {
                // accept — store the cleaned/parsed value (after our existing normalize pass)
                $collected[$step['key']] = $this->normalize($step['key'], $validation['parsed'] ?? $body);
            }
        }

        $next = $this->nextApplicableStep($steps, $stepIdx, $collected);
        $session->update([
            'collected'       => $collected,
            'current_step'    => $next['key'] ?? '__done__',
            'last_inbound_at' => now(),
        ]);

        if ($next === null || $next['key'] === '__done__') {
            $this->finalize($session);
            return true;
        }

        // Special prompt: address_confirmation includes the extracted address
        if ($next['key'] === 'address_confirmation') {
            $extracted = $collected['license_extracted'] ?? [];
            $line = trim(($extracted['address'] ?? '') . ', ' . ($extracted['city'] ?? '') . ', ' . ($extracted['state'] ?? '') . ' ' . ($extracted['zip'] ?? ''), ' ,');
            $prompt = "Got your license. Is this address correct?\n\n  {$line}\n\nReply YES, or text the correct address.";
            $this->reply($session->phone, $prompt);
            return true;
        }

        $this->reply($session->phone, $this->renderPrompt($next, $session));
        return true;
    }

    private function indexOfStep(array $steps, string $key): ?int
    {
        foreach ($steps as $i => $s) if ($s['key'] === $key) return $i;
        return null;
    }

    private function nextApplicableStep(array $steps, int $currentIdx, array $collected): ?array
    {
        for ($i = $currentIdx + 1; $i < count($steps); $i++) {
            $s = $steps[$i];
            if (isset($s['requires'])) {
                $val = strtolower((string) ($collected[$s['requires']] ?? ''));
                if (!in_array($val, ['yes', 'y', '1', 'true'], true)) continue;
            }
            // Skip steps whose key we already have a value for (re-used customers)
            if (!empty($collected[$s['key']]) && $s['key'] !== '__done__' && ($s['expects'] ?? null) !== 'image') continue;
            return $s;
        }
        return null;
    }

    private function firstUnfilledStep(array $steps, array $collected): ?array
    {
        foreach ($steps as $s) {
            if ($s['key'] === '__done__') return $s;
            if (isset($s['requires'])) {
                $val = strtolower((string) ($collected[$s['requires']] ?? ''));
                if (!in_array($val, ['yes', 'y', '1', 'true'], true)) continue;
            }
            if (empty($collected[$s['key']]) || ($s['expects'] ?? null) === 'image') return $s;
        }
        return null;
    }

    private function matchesAny(string $text, array $triggers): bool
    {
        foreach ($triggers as $t) if (str_contains($text, $t)) return true;
        return false;
    }

    private function renderPrompt(array $step, LeaseApplicationSession $session): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($session) {
            return (string) ($session->collected[$m[1]] ?? '');
        }, $step['prompt']);
    }

    private function normalize(string $key, string $value): string
    {
        $v = trim($value);
        return match ($key) {
            'state', 'employer_state', 'co_state' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $v), 0, 2)),
            'zip', 'employer_zip', 'co_zip'      => substr(preg_replace('/[^0-9]/', '', $v), 0, 10),
            'ssn', 'co_ssn'                       => $this->formatSsn($v),
            'monthly_housing', 'annual_income', 'co_monthly_housing', 'co_annual_income' => preg_replace('/[^0-9.]/', '', $v),
            'has_coapplicant', 'has_insurance'    => str_starts_with(strtolower($v), 'y') ? 'yes' : 'no',
            'own_or_rent', 'co_own_or_rent'       => str_starts_with(strtolower($v), 'o') ? 'own' : 'rent',
            'pickup_location'                     => str_contains(strtolower($v), 'monsey') ? 'Monsey' : 'Monroe',
            default => $v,
        };
    }

    private function formatSsn(string $v): string
    {
        $d = preg_replace('/\D/', '', $v);
        return strlen($d) === 9 ? substr($d,0,3).'-'.substr($d,3,2).'-'.substr($d,5) : $d;
    }

    /**
     * Download the MMS image, store it, run Claude vision, save as a CustomerDocument.
     * Returns the extracted-fields array (may be empty if no API key / parse failed).
     */
    private function ingestLicenseImage(LeaseApplicationSession $session, string $url): array
    {
        try {
            $binary = @file_get_contents($url);
            if ($binary === false) { Log::warning('Could not fetch license image', ['url' => $url]); return []; }

            // Persist the original image regardless of OCR outcome so staff
            // always have it on file to review manually.
            $filename = 'license-mms-' . now()->format('YmdHis') . '-' . Str::random(6) . '.jpg';
            $path = "lease-bot-uploads/{$session->id}/{$filename}";
            Storage::disk('public')->put($path, $binary);

            // OCR — try the original orientation first, then 90/180/270 if
            // it returns nothing usable. Some phones strip EXIF orientation
            // when sent via MMS; auto-rotating + retrying is much friendlier
            // than asking the customer to re-shoot.
            $extracted = $this->ocrLicenseWithRotations($binary);

            $extracted['_stored_path'] = $path;
            $extracted['_stored_disk'] = 'public';
            $extracted['_stored_size'] = strlen($binary);

            return $extracted;
        } catch (\Throwable $e) {
            Log::warning('ingestLicenseImage failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Run the OCR call up to 4 times — original, then 90°/180°/270°
     * rotations — taking the first attempt that returns ≥4 of the key
     * fields. Falls back to whichever rotation returned the most fields
     * (or empty if all 4 yielded nothing). GD's imagerotate is used for
     * the rotation; if GD isn't available we skip the rotation passes.
     */
    private function ocrLicenseWithRotations(string $binary): array
    {
        if (empty(config('services.anthropic.api_key'))) return [];

        $rotations = [0];
        if (function_exists('imagecreatefromstring') && function_exists('imagerotate')) {
            $rotations = [0, 90, 180, 270];
        }

        $best = ['count' => 0, 'data' => []];
        foreach ($rotations as $deg) {
            $image = $deg === 0 ? $binary : $this->rotateJpeg($binary, $deg);
            if ($image === null) continue;

            $data = $this->callOcr($image);
            $count = $this->countOcrFields($data);
            Log::error('License OCR attempt', ['rotation' => $deg, 'fields' => $count]); // error level survives LOG_LEVEL=error

            if ($count >= 4) return $data; // good enough — stop here
            if ($count > $best['count']) {
                $best = ['count' => $count, 'data' => $data];
            }
        }
        return $best['data'];
    }

    private function rotateJpeg(string $binary, int $degrees): ?string
    {
        try {
            $img = @imagecreatefromstring($binary);
            if (!$img) return null;
            // imagerotate is counter-clockwise; flip sign so positive degrees
            // means clockwise rotation (matches "rotate 90° clockwise").
            $rotated = @imagerotate($img, -$degrees, 0);
            imagedestroy($img);
            if (!$rotated) return null;
            ob_start();
            imagejpeg($rotated, null, 90);
            $out = ob_get_clean();
            imagedestroy($rotated);
            return $out !== false ? $out : null;
        } catch (\Throwable $e) {
            Log::error('License image rotate failed', ['degrees' => $degrees, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function callOcr(string $imageBinary): array
    {
        try {
            $resp = app(\App\Services\AiClient::class)->messages([
                // NOTE: 'temperature' is deprecated for Opus 4.7 — passing it
                // causes a 400 invalid_request_error. Omitting it means the
                // model uses its default. (Sonnet 4.5/4.6 accept temperature
                // but Opus 4.7 doesn't.)
                'model' => 'claude-opus-4-7', 'max_tokens' => 800,
                'system' => 'OCR US driver licenses. Output VALID JSON only. The photo MAY BE ROTATED 90/180/270 degrees — mentally rotate the image so the license reads upright before extracting any fields, then read normally. Read each visible digit carefully (1 vs 7, 0 vs 8, 3 vs 8). Use empty string ONLY if a field is truly unreadable or absent — do not skip a whole field over a single uncertain digit, just give your best read.',
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($imageBinary)]],
                        ['type' => 'text', 'text' => 'Extract: { "first_name":"", "last_name":"", "address":"", "city":"", "state":"", "zip":"", "drivers_license_number":"", "dl_state":"", "dl_expiration":"YYYY-MM-DD", "date_of_birth":"YYYY-MM-DD" }. Address: street + unit only (no city/state/zip — those have their own fields).'],
                    ],
                ]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            Log::error('License OCR call failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function countOcrFields(array $data): int
    {
        $keys = ['first_name', 'last_name', 'drivers_license_number', 'date_of_birth', 'address', 'dl_state'];
        $count = 0;
        foreach ($keys as $k) if (!empty($data[$k])) $count++;
        return $count;
    }

    /**
     * Verify a back-of-license photo is complete (all four corners
     * visible, nothing cut off or covered). Returns
     *   ['complete' => bool, 'reason' => string]
     * Fails open (returns complete=true) on any error so we don't block
     * progress when the verifier is itself broken.
     */
    /**
     * Wrapper kept for backwards compat — back-only verification now
     * delegates to the unified verifier.
     */
    private function verifyBackLicenseImage(?string $storedPath): array
    {
        $r = $this->verifyLicenseSide($storedPath, 'back');
        return ['complete' => $r['valid'], 'reason' => $r['reason']];
    }

    /**
     * Verify a license photo is (1) the right side, (2) the entire card
     * is visible, and (3) nothing is obstructing it (wires, fingers,
     * shadows, glare). Failing any of these returns valid=false with a
     * one-sentence reason that the bot uses verbatim in the re-ask
     * prompt — so the customer knows exactly what to fix.
     *
     * Common rejection cases this catches:
     *  - User sent the FRONT when bot asked for the BACK (or vice versa)
     *  - Wire / cable visible on top of the license (your case!)
     *  - Cropped at any edge
     *  - Finger or thumb covering data
     *  - Severe glare washing out printed text
     *
     * Fails OPEN on any error (returns valid=true) so a broken verifier
     * never blocks legitimate uploads.
     */
    private function verifyLicenseSide(?string $storedPath, string $expectedSide): array
    {
        if (!$storedPath || empty(config('services.anthropic.api_key'))) {
            return ['valid' => true, 'reason' => ''];
        }
        $expectedSide = $expectedSide === 'back' ? 'back' : 'front';
        try {
            $binary = \Storage::disk('public')->get($storedPath);
            if (!$binary) return ['valid' => true, 'reason' => ''];

            $sideRule = $expectedSide === 'front'
                ? 'The photo MUST be the FRONT of the license — the side with the photo, name, address, DOB, and DL number. If it is the BACK (barcode/magnetic-stripe side), set valid=false with reason "this is the back, please send the FRONT".'
                : 'The photo MUST be the BACK of the license — the side with the magnetic stripe, barcode, restrictions, donor info. If it is the FRONT (photo + identity fields), set valid=false with reason "this is the front, please send the BACK".';

            $resp = app(\App\Services\AiClient::class)->messages([
                'model' => 'claude-opus-4-7', 'max_tokens' => 200,
                'system' => "You verify US driver license photos. Output VALID JSON only: {\"valid\": <bool>, \"reason\": \"<short reason if invalid>\"}. {$sideRule} Also set valid=false if (a) any part of the license is cut off, (b) any object is covering or partially blocking the license — fingers, thumbs, wires, cables, papers, glare patches, shadows over printed text — or (c) the print is too blurry to read. Be strict — better to reject a borderline photo than accept a bad one. Reason should be customer-facing, e.g. \"a wire is covering part of the license\" or \"top-right corner is cut off\".",
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($binary)]],
                        ['type' => 'text', 'text' => "Verify this {$expectedSide}-of-license photo."],
                    ],
                ]],
            ]);
            $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
            $data = json_decode($text, true);
            if (!is_array($data)) return ['valid' => true, 'reason' => ''];
            return [
                'valid'  => (bool) ($data['valid'] ?? false),
                'reason' => (string) ($data['reason'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::error('verifyLicenseSide failed', ['expected' => $expectedSide, 'error' => $e->getMessage()]);
            return ['valid' => true, 'reason' => ''];
        }
    }

    private function finalize(LeaseApplicationSession $session): void
    {
        $c = $session->collected ?? [];
        $last10 = substr(preg_replace('/\D/', '', $session->phone), -10);

        $customer = $session->customer
            ?? Customer::where('phone', 'ilike', "%{$last10}")->first()
            ?? new Customer();

        $customer->fill(array_filter([
            'first_name'             => $c['first_name']    ?? null,
            'last_name'              => $c['last_name']     ?? null,
            'phone'                  => $session->phone,
            'email'                  => $c['email']         ?? null,
            'date_of_birth'          => $this->parseDate($c['date_of_birth'] ?? null),
            'address'                => $c['address']       ?? null,
            'city'                   => $c['city']          ?? null,
            'state'                  => $c['state']         ?? null,
            'zip'                    => $c['zip']           ?? null,
            'drivers_license_number' => $c['drivers_license_number'] ?? null,
            'dl_state'               => $c['dl_state']               ?? null,
            'dl_expiration'          => $this->parseDate($c['dl_expiration'] ?? null),
            'insurance_company'      => $c['insurance_company']      ?? null,
            'insurance_policy'       => $c['insurance_policy']       ?? null,
        ]));
        $customer->is_active = true;
        $customer->save();

        // Attach license images (front + back) as CustomerDocuments if we have them
        foreach (['front', 'back'] as $side) {
            $path = $c['license_image_' . $side . '_path'] ?? null;
            if (!$path) continue;
            CustomerDocument::create([
                'customer_id'   => $customer->id,
                'type'          => 'drivers_license_' . $side,
                'label'         => trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '') . ' · ' . ($c['drivers_license_number'] ?? '') . " ({$side})", ' ·'),
                'disk'          => 'public',
                'path'          => $path,
                'original_name' => basename($path),
                'mime_type'     => 'image/jpeg',
                'expires_at'    => $this->parseDate($c['dl_expiration'] ?? null),
                'uploaded_by'   => null,
            ]);
        }

        $session->customer_id = $customer->id;

        if (in_array($session->flow, ['lease', 'finance'], true)) {
            $deal = Deal::create([
                'deal_number'  => Deal::generateDealNumber(),
                'customer_id'  => $customer->id,
                'payment_type' => $session->flow === 'finance' ? 'finance' : 'lease',
                'stage'        => 'application',
                'priority'     => 'medium',
                'notes'        => $this->buildNote($session->flow, $c),
            ]);
            $session->deal_id = $deal->id;
            $session->update(['completed_at' => now()]);
            $this->reply($session->phone, "Got it — your application is in. A team member will reach out shortly.");
        } elseif (in_array($session->flow, ['towing', 'bodyshop'], true)) {
            // Towing + Bodyshop don't have a strict structured target yet.
            // Save as a tagged note on the customer + complete the session;
            // staff sees it in the Bot Intakes list and can route from there.
            \App\Models\OfficeTask::create([
                'title'       => strtoupper($session->flow) . ' request from ' . trim($c['first_name'] ?? '' . ' ' . ($c['last_name'] ?? '')),
                'description' => $this->buildNote($session->flow, $c) . "\n\nFrom phone: {$session->phone}",
                'customer_id' => $customer->id,
                'priority'    => $session->flow === 'towing' ? 'high' : 'medium',
                'status'      => 'pending',
            ]);
            $session->update(['completed_at' => now()]);
            $msg = $session->flow === 'towing'
                ? "Thanks ✅ — dispatcher will call you in about 5 minutes."
                : "Thanks ✅ — bodyshop will text you to schedule your drop-off.";
            $this->reply($session->phone, $msg);
        } else {
            // RENTAL — create a draft Reservation if dates parse cleanly; otherwise leave as note for staff
            $deal = null;
            try {
                $pickup = $this->parseDate($c['pickup_date'] ?? null);
                $return = $this->parseDate($c['return_date'] ?? null);
                if ($pickup && $return && class_exists(\App\Models\Reservation::class)) {
                    $deal = \App\Models\Reservation::create([
                        'customer_id'      => $customer->id,
                        'pickup_date'      => $pickup,
                        'return_date'      => $return,
                        'pickup_location'  => $c['pickup_location'] ?? 'Monroe',
                        'status'           => 'pending',
                        'notes'            => $this->buildNote($session->flow, $c),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Bot: could not create reservation', ['error' => $e->getMessage()]);
            }

            $session->update(['completed_at' => now()]);
            $msg = $deal
                ? "All set ✅ — rental request created (Reservation #{$deal->id}). A team member will text you to confirm vehicle + total."
                : "All set ✅ — request received. A team member will text you shortly to confirm vehicle, dates, and total.";
            $this->reply($session->phone, $msg);
        }
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v) return null;
        try { return \Carbon\Carbon::parse($v)->toDateString(); } catch (\Throwable) { return null; }
    }

    private function buildNote(string $flow, array $c): string
    {
        $steps = $flow === 'lease' ? self::STEPS_LEASE : self::STEPS_RENTAL;
        $lines = [strtoupper($flow) . " bot intake:"];
        foreach ($steps as $s) {
            if (in_array($s['key'], ['__done__', 'license_image', 'address_confirmation'], true)) continue;
            if (!array_key_exists($s['key'], $c)) continue;
            $label = ucwords(str_replace('_', ' ', $s['key']));
            $val = $c[$s['key']];
            if (in_array($s['key'], ['ssn', 'co_ssn'], true)) $val = '***-**-' . substr((string)$val, -4);
            $lines[] = "  {$label}: {$val}";
        }
        return implode("\n", $lines);
    }

    private function reply(string $toPhone, string $message): void
    {
        $result = $this->telebroad->sendSms($toPhone, $message);
        $last10 = substr(preg_replace('/\D/', '', $toPhone), -10);
        $customer = Customer::where('phone', 'ilike', "%{$last10}")->first();
        CommunicationLog::create([
            'subject_type' => $customer ? Customer::class : null,
            'subject_id'   => $customer?->id,
            'customer_id'  => $customer?->id,
            'user_id'      => null,
            'channel'      => 'sms',
            'direction'    => 'outbound',
            'from'         => (string) config('services.telebroad.phone_number'),
            'to'           => $toPhone,
            'body'         => $message,
            'attachments'  => ['_bot' => true],
            'external_ref' => $result['external_id'] ?? null,
            'status'       => ($result['success'] ?? false) ? 'sent' : 'failed',
            'sent_at'      => now(),
        ]);
    }

    /**
     * Compare extracted license fields to the on-file customer profile.
     * Returns ['field' => ['license' => 'X', 'file' => 'Y'], ...] for any
     * field that's set on BOTH sides and differs (case-/punctuation-
     * normalised). Empty array if no diffs (or no on-file customer to
     * compare against).
     */
    private function licenseFieldDiffs(?Customer $customer, array $extracted): array
    {
        if (!$customer) return [];
        $norm = fn ($v) => trim(preg_replace('/\s+/', ' ', strtolower((string) $v)));
        $diffs = [];

        // DOB — customer.date_of_birth is a date cast; compare as YYYY-MM-DD.
        // We flag a diff in three cases:
        //   1. License has a DOB and on-file has a DOB and they don't match
        //   2. License has a DOB and on-file is EMPTY (so customer confirms
        //      the extracted date before we silently save it)
        // We never flag when license is empty — that's a missed extraction,
        // not a confirmation prompt.
        $licDob  = $norm($extracted['date_of_birth'] ?? '');
        $fileDob = $customer->date_of_birth ? $customer->date_of_birth->format('Y-m-d') : '';
        if ($licDob && (empty($fileDob) || $norm($fileDob) !== $licDob)) {
            $diffs['date_of_birth'] = [
                'license' => $extracted['date_of_birth'],
                'file'    => $fileDob ?: '(none on file)',
            ];
        }

        // Address — combine line1 + city + state + zip into one normalised
        // string so a different street number trips the diff but the same
        // address with different formatting doesn't.
        $licAddr  = $norm(trim(($extracted['address'] ?? '') . ' ' . ($extracted['city'] ?? '') . ' ' . ($extracted['state'] ?? '') . ' ' . ($extracted['zip'] ?? '')));
        $fileAddr = $norm(trim(($customer->address ?? '') . ' ' . ($customer->city ?? '') . ' ' . ($customer->state ?? '') . ' ' . ($customer->zip ?? '')));
        if ($licAddr && $fileAddr && $licAddr !== $fileAddr) {
            $diffs['address'] = [
                'license' => trim(($extracted['address'] ?? '') . ', ' . ($extracted['city'] ?? '') . ', ' . ($extracted['state'] ?? '') . ' ' . ($extracted['zip'] ?? ''), ' ,'),
                'file'    => trim(($customer->address ?? '') . ', ' . ($customer->city ?? '') . ', ' . ($customer->state ?? '') . ' ' . ($customer->zip ?? ''), ' ,'),
            ];
        }

        // Names — only flag if different non-empty values (typo tolerance via
        // normalize). Common case: nickname on license vs. legal name on file.
        foreach (['first_name', 'last_name'] as $f) {
            $lic  = $norm($extracted[$f] ?? '');
            $file = $norm($customer->{$f} ?? '');
            if ($lic && $file && $lic !== $file) {
                $diffs[$f] = ['license' => $extracted[$f], 'file' => $customer->{$f}];
            }
        }

        return $diffs;
    }

    /**
     * Format a YYYY-MM-DD-ish date as MM/DD/YYYY for SMS display. Pass-
     * through anything we can't parse (including "(none on file)") so
     * the caller doesn't have to special-case empty values.
     */
    private function displayDate(?string $v): string
    {
        if (!$v) return '';
        if (!preg_match('/\d{4}-\d{1,2}-\d{1,2}|\d{1,2}\/\d{1,2}\/\d{2,4}/', $v)) return $v; // raw string passthrough
        try {
            $ts = strtotime($v);
            return $ts ? date('m/d/Y', $ts) : $v;
        } catch (\Throwable) {
            return $v;
        }
    }

    /**
     * Does the customer's free-text correction look like a date? Accepts
     * 12/27/1985, 12-27-1985, 1985-12-27, "Dec 27 1985" etc. Used to
     * route DOB corrections to the right field instead of silently
     * saving them as the address.
     */
    private function looksLikeDate(string $text): bool
    {
        $text = trim($text);
        if (preg_match('/^\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}$/', $text)) return true;        // 12/27/1985, 12-27-85
        if (preg_match('/^\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2}$/', $text)) return true;          // 1985-12-27
        if (preg_match('/^[A-Za-z]{3,9}\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{2,4}$/', $text)) return true; // Dec 27 1985
        return false;
    }

    /**
     * Normalize a date-shaped string into YYYY-MM-DD. Returns the
     * original string unchanged if PHP's date parsing can't make sense
     * of it (better to save the raw response than to drop it silently).
     */
    private function normalizeDate(string $text): string
    {
        try {
            $ts = strtotime($text);
            return $ts ? date('Y-m-d', $ts) : $text;
        } catch (\Throwable $e) {
            return $text;
        }
    }

    /**
     * Give-up path for an image step that's failed twice. Saves whatever
     * we have (URL only — no OCR data), flags the session for staff
     * follow-up, and advances to the next applicable step. Sends the
     * provided prefix message followed by the next step's prompt so the
     * customer keeps moving instead of getting stuck on the photo.
     */
    private function skipImageStepAndAdvance(LeaseApplicationSession $session, array $step, int $stepIdx, array $collected, string $prefixMessage): bool
    {
        $collected['_image_step_skipped_' . $step['key']] = now()->toIso8601String();
        $collected['_needs_staff_followup'] = true;

        $steps = $this->stepsFor($session->flow);
        $next  = $this->nextApplicableStep($steps, $stepIdx, $collected);
        $session->update([
            'collected'       => $collected,
            'current_step'    => $next['key'] ?? '__done__',
            'last_inbound_at' => now(),
        ]);

        if ($next === null || ($next['key'] ?? '') === '__done__') {
            $this->reply($session->phone, $prefixMessage);
            $this->finalize($session);
            return true;
        }

        $this->reply($session->phone, $prefixMessage . "\n\n" . $this->renderPrompt($next, $session));
        return true;
    }

    /**
     * Customer is replying to the diff prompt. We accept:
     *   "1" / "LICENSE"  → use license values, overwrite session collected
     *   "2" / "FILE"     → keep on-file values (with license fallback for
     *                      empty fields, see PR #37)
     *   "3" / "REVIEW"   → OCR read looks wrong; keep license values for
     *                      now BUT flag session for staff to verify later
     *   date-shaped text → save as DOB, carry on-file address through
     *   anything else    → save as address, carry license DOB through
     * Then advance to the next applicable step.
     */
    private function handleIdentityConfirmResponse(LeaseApplicationSession $session, array $step, int $stepIdx, array $collected, string $body): bool
    {
        $diffs = $collected['_pending_identity_confirm'] ?? [];
        $extracted = $collected['license_extracted'] ?? [];
        $trim  = trim($body);
        $upper = strtoupper($trim);

        // Accept numeric shortcuts first — phone keyboards prefer 1/2/3.
        $choice = null;
        if ($trim === '1' || $upper === 'LICENSE' || $upper === 'L') {
            $choice = 'license';
        } elseif ($trim === '2' || $upper === 'FILE' || $upper === 'F') {
            $choice = 'file';
        } elseif ($trim === '3' || $upper === 'REVIEW' || $upper === 'R') {
            $choice = 'review';
        }

        if ($choice === 'license') {
            foreach (['date_of_birth','address','city','state','zip','first_name','last_name'] as $k) {
                if (!empty($extracted[$k])) $collected[$k] = $extracted[$k];
            }
        } elseif ($choice === 'file') {
            // Keep on-file values — but for any field that's EMPTY on file
            // (e.g. DOB never captured), fall back to the license value so
            // the application doesn't end up with blanks. "FILE" means
            // "don't overwrite what I have"; if nothing's there, use license.
            $cust = $session->customer;
            foreach (['address','city','state','zip','first_name','last_name'] as $k) {
                $fileVal = $cust->{$k} ?? null;
                $licVal  = $extracted[$k] ?? null;
                $collected[$k] = !empty($fileVal) ? $fileVal : ($licVal ?: ($collected[$k] ?? null));
            }
            $fileDob = $cust && $cust->date_of_birth ? $cust->date_of_birth->format('Y-m-d') : null;
            $licDob  = $extracted['date_of_birth'] ?? null;
            $collected['date_of_birth'] = !empty($fileDob) ? $fileDob : ($licDob ?: ($collected['date_of_birth'] ?? null));
        } elseif ($choice === 'review') {
            // Customer thinks the OCR read is wrong but can't easily correct
            // it via SMS. Take the license values as the best-we-have but
            // flag the session so staff manually verify before the deal
            // moves forward. Don't block the flow — let the customer keep
            // moving through the application.
            foreach (['date_of_birth','address','city','state','zip','first_name','last_name'] as $k) {
                if (!empty($extracted[$k])) $collected[$k] = $extracted[$k];
            }
            $collected['_needs_license_review'] = true;
            $collected['_needs_staff_followup'] = true;
            $collected['_license_review_flagged_at'] = now()->toIso8601String();
        } else {
            // Free-text correction — detect what shape it is so we route to
            // the right field instead of always treating it as an address.
            // Date-shaped → DOB. Everything else → address.
            $text = trim($body);
            if ($this->looksLikeDate($text)) {
                $collected['date_of_birth'] = $this->normalizeDate($text);
                // Pull on-file address into collected so we don't lose it
                $cust = $session->customer;
                if ($cust) {
                    foreach (['address','city','state','zip'] as $k) {
                        if (empty($collected[$k]) && !empty($cust->{$k})) $collected[$k] = $cust->{$k};
                    }
                }
            } else {
                $collected['address'] = $text;
                // Carry the license DOB through if on-file is empty —
                // otherwise an address-only correction silently drops the
                // extracted DOB (the bug the user just flagged).
                if (empty($collected['date_of_birth'])) {
                    $licDob = $extracted['date_of_birth'] ?? null;
                    $fileDob = $session->customer && $session->customer->date_of_birth
                        ? $session->customer->date_of_birth->format('Y-m-d') : null;
                    $collected['date_of_birth'] = $fileDob ?: $licDob ?: null;
                }
            }
        }

        unset($collected['_pending_identity_confirm']);
        $collected['_identity_confirmed_at'] = now()->toIso8601String();

        // Persist so the front-license is recorded as accepted.
        $collected['license_image_front_url']  = $collected['license_image_front_url']  ?? null;
        $collected['license_image_front_path'] = $collected['license_image_front_path'] ?? null;

        $steps = $this->stepsFor($session->flow);
        $next = $this->nextApplicableStep($steps, $stepIdx, $collected);
        $session->update([
            'collected'       => $collected,
            'current_step'    => $next['key'] ?? '__done__',
            'last_inbound_at' => now(),
        ]);

        if ($next === null || ($next['key'] ?? '') === '__done__') {
            $this->finalize($session);
            return true;
        }
        $ack = match ($choice) {
            'license' => 'Got it — using license info.',
            'file'    => "Got it — keeping what's on file.",
            'review'  => "Got it — flagged for our team to review the read. Continuing for now.",
            default   => 'Got it — saved your correction.',
        };
        $this->reply($session->phone, $ack . "\n\n" . $this->renderPrompt($next, $session));
        return true;
    }
}

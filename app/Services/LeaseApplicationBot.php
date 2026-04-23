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
        ['key' => 'ssn',               'prompt' => "Your full SSN? (XXX-XX-XXXX). Used only for the credit application."],
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
        ['key' => 'co_ssn',              'prompt' => "Co-applicant SSN? (XXX-XX-XXXX)", 'requires' => 'has_coapplicant'],
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

        ['key' => 'vehicle_interest', 'prompt' => "Last question — what vehicle are you interested in? (year/make/model, or just describe)"],
        ['key' => '__done__',         'prompt' => "All set ✅ — your application is in. A team member will reach out with options shortly. Reply STOP to opt out."],
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
        ['key' => 'vehicle_preference','prompt' => "Any vehicle preference? (e.g. SUV, sedan, minivan — or 'whatever's available')"],
        ['key' => '__done__',          'prompt' => "All set ✅ — your rental request is in. A team member will text you to confirm a vehicle and total. Reply STOP to opt out."],
    ];

    public const STEPS_TOWING = [
        ['key' => 'first_name',      'prompt' => "🚛 AutoGo Towing — quick details please. Your FIRST name?"],
        ['key' => 'last_name',       'prompt' => "Last name?"],
        ['key' => 'pickup_location', 'prompt' => "Where is the vehicle? (street address, intersection, or landmark)"],
        ['key' => 'dropoff_location','prompt' => "Where should it be towed TO? (e.g. AutoGo bodyshop, your home, dealership)"],
        ['key' => 'vehicle',         'prompt' => "Year/make/model/color of the vehicle? (e.g. 2021 Toyota Camry, white)"],
        ['key' => 'situation',       'prompt' => "What happened? (accident / breakdown / lockout / battery / out of gas / other)"],
        ['key' => 'wheels_turn',     'prompt' => "Do all 4 wheels still turn? (yes / no — affects truck type)"],
        ['key' => 'urgency',         'prompt' => "How urgent? (now / today / tomorrow / scheduled)"],
        ['key' => '__done__',        'prompt' => "Got it ✅ — dispatching now. We'll call you within 5 minutes to confirm ETA. Reply STOP to opt out."],
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
        ['key' => 'rental_needed',     'prompt' => "Will you need a rental car while it's being repaired? (yes / no)"],
        ['key' => '__done__',          'prompt' => "Thanks ✅ — your estimate request is in. A team member will text/call you to schedule a drop-off. Reply STOP to opt out."],
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

        if ($session) {
            return $this->advanceSession($session, $body, $mediaUrls);
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
            'rental', 'rent'                        => 'rental',
            'tow', 'towing'                         => 'towing',
            'bodyshop', 'body', 'collision'         => 'bodyshop',
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
              . "4 BODYSHOP — collision repair\n\n"
              . "Reply STOP to opt out.";
        $this->reply($fromPhone, $menu);
        LeaseApplicationSession::create([
            'phone' => $fromPhone, 'flow' => 'intent', 'current_step' => '__intent__',
            'collected' => [], 'customer_id' => $customer?->id, 'last_inbound_at' => now(),
        ]);
        return true;
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
                default            => "Hi {$customer->first_name}! 👋",
            };
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
            'lease', 'finance' => "Hi! 👋 This is AutoGo Leasing. I'll text a few quick questions for your application. Reply STOP to opt out.",
            'rental'           => "Hi! 👋 This is AutoGo Rentals. I'll text a few quick questions to set up your rental. Reply STOP to opt out.",
            'towing'           => "Hi! 👋 This is AutoGo Towing. I'll text a few quick questions to dispatch a truck. Reply STOP to opt out.",
            'bodyshop'         => "Hi! 👋 This is AutoGo Bodyshop. I'll text a few quick questions for your repair estimate. Reply STOP to opt out.",
            default            => "Hi! 👋 This is AutoGo. Reply STOP to opt out.",
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
            $this->reply($session->phone, "Please reply 1, 2, 3, 4, or 5.");
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
        // wipe just those, then ask for the new value(s).
        if ($session->current_step === '__confirm_what_to_update__') {
            $steps = $this->stepsFor($session->flow);
            $collected = $session->collected ?? [];
            $fieldsToFix = $this->aiPickFieldsToUpdate($body, array_keys($collected));
            if (empty($fieldsToFix)) {
                $this->reply($session->phone, "Sorry, didn't catch that — which field? (name / address / city / state / zip / phone / email)");
                return true;
            }
            foreach ($fieldsToFix as $k) unset($collected[$k]);
            $next = $this->firstUnfilledStep($steps, $collected);
            $session->update([
                'collected'    => $collected,
                'current_step' => $next['key'] ?? '__done__',
            ]);
            if ($next === null || $next['key'] === '__done__') { $this->finalize($session); return true; }
            $this->reply($session->phone, "Got it — updating: " . implode(', ', $fieldsToFix));
            $this->reply($session->phone, $this->renderPrompt($next, $session));
            return true;
        }

        $steps   = $this->stepsFor($session->flow);
        $stepIdx = $this->indexOfStep($steps, $session->current_step);
        if ($stepIdx === null) return false;
        $step = $steps[$stepIdx];
        $collected = $session->collected ?? [];

        // Image step (license_image_front / license_image_back) needs MMS
        if (($step['expects'] ?? null) === 'image') {
            if (empty($mediaUrls)) {
                $side = str_contains($step['key'], 'back') ? 'BACK' : 'FRONT';
                $this->reply($session->phone, "I need an actual photo — please TEXT (MMS) the {$side} of your driver's license.");
                return true;
            }
            $extracted = $this->ingestLicenseImage($session, $mediaUrls[0]);
            $collected[$step['key'] . '_url'] = $mediaUrls[0];      // license_image_front_url, _back_url
            $collected[$step['key'] . '_path'] = $extracted['_stored_path'] ?? null;

            // Only the FRONT is OCR'd for identity fields
            if ($step['key'] === 'license_image_front') {
                $collected['license_extracted'] = $extracted;
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
            $b64 = base64_encode($binary);

            $extracted = [];
            if (!empty(config('services.anthropic.api_key'))) {
                try {
                    $resp = app(\App\Services\AiClient::class)->messages([
                        'model' => 'claude-sonnet-4-5', 'max_tokens' => 800, 'temperature' => 0,
                        'system' => 'OCR US driver licenses. Output VALID JSON only.',
                        'messages' => [[
                            'role' => 'user',
                            'content' => [
                                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $b64]],
                                ['type' => 'text', 'text' => 'Extract: { "first_name":"", "last_name":"", "address":"", "city":"", "state":"", "zip":"", "drivers_license_number":"", "dl_state":"", "dl_expiration":"YYYY-MM-DD", "date_of_birth":"YYYY-MM-DD" }. Empty string if missing.'],
                            ],
                        ]],
                    ]);
                    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $resp->content[0]->text ?? ''));
                    $data = json_decode($text, true);
                    if (is_array($data)) $extracted = $data;
                } catch (\Throwable $e) {
                    Log::warning('License OCR failed', ['error' => $e->getMessage()]);
                }
            }

            // Save as a CustomerDocument once we know the customer
            // (will be wired in finalize — for now, stash the binary)
            $filename = 'license-mms-' . now()->format('YmdHis') . '-' . Str::random(6) . '.jpg';
            $path = "lease-bot-uploads/{$session->id}/{$filename}";
            Storage::disk('public')->put($path, $binary);

            $extracted['_stored_path'] = $path;
            $extracted['_stored_disk'] = 'public';
            $extracted['_stored_size'] = strlen($binary);

            return $extracted;
        } catch (\Throwable $e) {
            Log::warning('ingestLicenseImage failed', ['error' => $e->getMessage()]);
            return [];
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
            $this->reply($session->phone, "All set ✅ — your application is in (Deal #{$deal->deal_number}). A team member will reach out shortly. Reply STOP to opt out.");
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
}

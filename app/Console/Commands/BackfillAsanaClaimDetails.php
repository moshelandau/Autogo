<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Models\ClaimInsuranceEntry;
use App\Models\Customer;
use Illuminate\Console\Command;

class BackfillAsanaClaimDetails extends Command
{
    protected $signature = 'backfill:claim-details {--limit=0} {--dry-run}';
    protected $description = 'Re-fetch Asana Claims tasks and parse NOTES for insurance company, claim#, adjuster, VIN, plate';

    private string $token;
    private string $baseUrl = 'https://app.asana.com/api/1.0';
    private string $claimsProjectGid = '1203511376320076';

    public function __construct()
    {
        parent::__construct();
        $this->token = (string) (config('services.asana.token') ?: env('ASANA_TOKEN', ''));
    }

    // Known insurance carriers (case-insensitive). Mapping to canonical name.
    private array $insurers = [
        'Progressive', 'Geico', 'GEICO', 'Allstate', 'State Farm', 'StateFarm', 'Statefarm',
        'Liberty Mutual', 'Liberty General',
        'Nationwide', 'Farmers', 'Formers', 'USAA', 'Travelers', 'American Family',
        'Erie', 'National General', 'MAPFRE', 'Hagerty', 'Grundy',
        'Kemper', 'Dairyland', 'Infinity', 'Direct Auto', 'Plymouth Rock',
        'Plymouth Rock Assurance', 'NJM', 'NYCM', 'Esurance', 'Mercury',
        'Safeco', 'American Transit', 'American State', 'American States',
        'Philadelphia Insurance', 'Philadelphia', 'Integon', 'Hanover',
        'Foremost', 'Bristol West', 'Titan', 'MetLife', 'MetLife Auto',
        'Auto-Owners', 'The Hartford', 'Hartford', 'American Modern',
        'Adirondack', 'Stillwater', 'Encompass',
        // Additional carriers discovered in Asana notes
        'Maya Assurance', 'Utica National', 'Utica',
        'Guard Insurance', 'Guard',
        'Pennsylvania Lumbermens Mutual', 'Lumbermens',
        'Mesa Underwriters Specialty', 'Mesa Underwriters', 'Mesa',
        'Kingstone',
        'Affirmative',
        'Hereford Insurance', 'Hereford',
        'Great West Casualty', 'Great West',
        'Stillwater',
        'Amguard', 'Norguard',
        'CURE',
        'AAA',
        'Clearcover',
        'Electric Insurance',
        'Farmers of Texas',
        'Zurich',
        'Tokio Marine',
        'Chubb',
        'AIG',
        'Global Liberty', 'Global',
        'Maya',
        'Country Financial', 'Country',
    ];

    // Normalize to canonical spelling
    private array $insurerAliases = [
        'statefarm' => 'State Farm',
        'formers'   => 'Farmers',  // common misspelling
        'geico'     => 'Geico',
        'mapfre'    => 'MAPFRE',
    ];

    public function handle(): int
    {
        $this->info('Fetching all Claims tasks from Asana with full notes...');
        $tasks = $this->getAllTasks();
        $this->info("Fetched " . count($tasks) . " tasks.");

        $limit = (int) $this->option('limit');
        if ($limit > 0) $tasks = array_slice($tasks, 0, $limit);

        $dryRun = $this->option('dry-run');
        $updated = 0;
        $insEntries = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($tasks as $task) {
            $name  = trim($task['name'] ?? '');
            $notes = trim($task['notes'] ?? '');
            if ($name === '' || str_ends_with($name, ':')) { $skipped++; continue; }
            if ($notes === '') { $skipped++; continue; }

            // Find claim by matching customer name
            $customer = $this->findCustomer($name);
            if (!$customer) { $notFound++; continue; }

            // Match the claim with the longest notes similarity, or first one with matching customer+notes
            $claim = Claim::where('customer_id', $customer->id)
                ->where(function ($q) use ($notes) {
                    $q->where('notes', $notes)->orWhere('story', $notes);
                })->first();
            if (!$claim) {
                $claim = Claim::where('customer_id', $customer->id)->orderByDesc('created_at')->first();
            }
            if (!$claim) { $notFound++; continue; }

            $parsed = $this->parseNotes($notes);

            // Update claim-level adjuster fields (picking the first found)
            $claimUpdates = [];
            if ($parsed['adjuster_name']  && !$claim->adjuster_name)  $claimUpdates['adjuster_name']  = $parsed['adjuster_name'];
            if ($parsed['adjuster_phone'] && !$claim->adjuster_phone) $claimUpdates['adjuster_phone'] = $parsed['adjuster_phone'];
            if ($parsed['adjuster_email'] && !$claim->adjuster_email) $claimUpdates['adjuster_email'] = $parsed['adjuster_email'];
            if ($parsed['vehicle_vin']    && !$claim->vehicle_vin)    $claimUpdates['vehicle_vin']    = $parsed['vehicle_vin'];
            if ($parsed['vehicle_plate']  && !$claim->vehicle_plate)  $claimUpdates['vehicle_plate']  = $parsed['vehicle_plate'];

            if ($claimUpdates && !$dryRun) $claim->update($claimUpdates);

            // Insurance entries (can be multiple). Need claim_number (NOT NULL column).
            foreach ($parsed['insurance_entries'] as $ie) {
                if (!$ie['claim_number']) continue; // skip entries without a claim number
                // Skip dupes
                $exists = ClaimInsuranceEntry::where('claim_id', $claim->id)
                    ->where('claim_number', $ie['claim_number'])
                    ->exists();
                if ($exists) continue;

                if (!$dryRun) {
                    ClaimInsuranceEntry::create([
                        'claim_id'          => $claim->id,
                        'insurance_company' => $ie['insurance_company'] ?: 'Unknown',
                        'claim_number'      => $ie['claim_number'],
                        'policy_number'     => $ie['policy_number'],
                    ]);
                }
                $insEntries++;
            }

            if ($claimUpdates || $parsed['insurance_entries']) {
                $updated++;
                if ($updated <= 30) {
                    $this->line("  ✓ {$name} — " . json_encode([
                        'adj' => $claimUpdates['adjuster_name'] ?? null,
                        'ins' => array_map(fn($x) => $x['insurance_company'] . ' #' . $x['claim_number'], $parsed['insurance_entries']),
                    ]));
                }
            }
        }

        $this->newLine();
        $this->info(sprintf('Claims updated: %d · Insurance entries added: %d · Skipped: %d · Not found: %d',
            $updated, $insEntries, $skipped, $notFound));
        if ($dryRun) $this->warn('DRY RUN — no DB writes performed.');

        return 0;
    }

    private function getAllTasks(): array
    {
        $all = [];
        $offset = null;
        do {
            $p = ['limit' => 100, 'opt_fields' => 'name,notes,memberships.section.name'];
            if ($offset) $p['offset'] = $offset;
            $r = $this->asanaGet("/projects/{$this->claimsProjectGid}/tasks", $p);
            $all = array_merge($all, $r['data'] ?? []);
            $offset = $r['next_page']['offset'] ?? null;
        } while ($offset);
        return $all;
    }

    private function asanaGet(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;
        if ($params) $url .= '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token, 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);
        $r = curl_exec($ch);
        curl_close($ch);
        return json_decode($r, true) ?? [];
    }

    private function findCustomer(string $fullName): ?Customer
    {
        // Strip trailing "/AutoGo" or similar suffixes, trim whitespace
        $name = trim(preg_replace('#/.*$#', '', $fullName));
        $parts = preg_split('/\s+/', $name, 2);
        if (count($parts) < 2) return null;
        [$first, $last] = [$parts[0], $parts[1]];

        return Customer::whereRaw('LOWER(first_name) = ?', [strtolower($first)])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($last)])
            ->first()
            ?? Customer::whereRaw('LOWER(CONCAT(first_name,\' \',last_name)) = ?', [strtolower($name)])->first();
    }

    /**
     * Parse the notes field into structured claim details.
     *
     * Returns:
     *   adjuster_name, adjuster_phone, adjuster_email, vehicle_vin, vehicle_plate,
     *   insurance_entries: [ { insurance_company, claim_number, policy_number }, ... ]
     */
    private function parseNotes(string $notes): array
    {
        $out = [
            'adjuster_name'  => null,
            'adjuster_phone' => null,
            'adjuster_email' => null,
            'vehicle_vin'    => null,
            'vehicle_plate'  => null,
            'insurance_entries' => [],
        ];

        // Normalize line endings and split into lines
        $lines = preg_split('/\r\n|\r|\n/', $notes);
        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, fn($l) => $l !== ''));

        // VIN (17 alphanumeric, commonly on a "VIN" line)
        if (preg_match('/\bVIN[:\s#]*([A-HJ-NPR-Z0-9]{17})\b/i', $notes, $m)) {
            $out['vehicle_vin'] = strtoupper($m[1]);
        }

        // Plate
        if (preg_match('/(?:Plate|License\s*Plate)[:\s#]*([A-Z0-9 \-]{4,10})/i', $notes, $m)) {
            $out['vehicle_plate'] = trim(strtoupper($m[1]));
        }

        // Emails (likely adjuster contact)
        if (preg_match('/[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+/i', $notes, $m)) {
            $out['adjuster_email'] = $m[0];
        }

        // Phone (common US patterns)
        if (preg_match('/\b(\(?\d{3}\)?[\s\.\-]?\d{3}[\s\.\-]?\d{4})\b/', $notes, $m)) {
            $out['adjuster_phone'] = $m[1];
        }

        // Adjuster / Examiner / Appraiser name — require a colon or explicit label format
        // to avoid matching free-form text like "assigned to an examiner for review"
        if (preg_match('/(?:Examiner|Adjuster|Appraiser|Supervisor)\s*(?::|-)\s*([A-Z][a-zA-Z\'\-]+(?:\s+[A-Z][a-zA-Z\'\-]+){0,3})(?:\s|$)/', $notes, $m)) {
            $out['adjuster_name'] = trim($m[1]);
        }

        // Walk line-by-line to pair "Claim Number" / "Claim #" / "Claim num" with the next known insurer
        $currentClaim = null;
        $currentPolicy = null;
        $currentCompany = null;
        $flush = function () use (&$currentClaim, &$currentCompany, &$currentPolicy, &$out) {
            if ($currentClaim || $currentCompany) {
                $out['insurance_entries'][] = [
                    'insurance_company' => $currentCompany,
                    'claim_number'      => $currentClaim,
                    'policy_number'     => $currentPolicy,
                ];
            }
            $currentClaim = $currentCompany = $currentPolicy = null;
        };

        $justSawClaim = false;
        foreach ($lines as $line) {
            // "Claim Number: XXX"
            if (preg_match('/^claim\s*(?:number|num|nr|no|#)?\s*[:#]?\s*([A-Z0-9][\w\-\s]{3,})\s*$/i', $line, $m)
                && preg_match('/\d/', $m[1])
                && !in_array(strtolower(trim($m[1])), ['number', 'num', 'nr', 'no'])) {
                if ($currentClaim) $flush();
                $currentClaim = trim($m[1]);
                $justSawClaim = true;
                continue;
            }
            if (preg_match('/claim\s*(?:number|num|nr|no|#)\s*[:#]?\s*([A-Z0-9][\w\-]{4,})/i', $line, $m)
                && preg_match('/\d/', $m[1])) {
                if ($currentClaim) $flush();
                $currentClaim = trim($m[1]);
                $justSawClaim = true;
                continue;
            }

            if (preg_match('/^(?:policy|pol)\s*[#:]?\s*([A-Z0-9][\w\-]{3,})/i', $line, $m)) {
                $currentPolicy = trim($m[1]);
                continue;
            }

            // Ignore phones, emails, VIN/Plate prefix lines
            if (preg_match('/^(?:VIN|License|Plate|Phone|Fax|Email|Address|Vehicle)[:\s#]/i', $line)) continue;
            if (preg_match('/^[\d\-\(\)\s]{7,}$/', $line)) continue;
            if (str_contains($line, '@') && preg_match('/@[\w\-\.]+/', $line)) continue;
            if (preg_match('/^https?:\/\//i', $line)) continue;

            // Try known insurer whitelist
            $matched = false;
            foreach ($this->insurers as $ins) {
                if (preg_match('/\b' . preg_quote($ins, '/') . '\b/i', $line)) {
                    $key = strtolower(trim($ins));
                    $currentCompany = $this->insurerAliases[$key] ?? $ins;
                    $matched = true;
                    break;
                }
            }

            // Fallback: this is the line right after "Claim Number:" and it looks like a company name
            // (contains word chars, letters, not an ID/phone/email, not too short, not too long)
            if (!$matched && $justSawClaim && !$currentCompany) {
                $cleaned = trim($line);
                if (strlen($cleaned) >= 3 && strlen($cleaned) <= 60
                    && preg_match('/[A-Za-z]{3,}/', $cleaned)
                    && !preg_match('/^\d+$/', $cleaned)) {
                    // Strip trailing punctuation/noise
                    $currentCompany = rtrim($cleaned, " \t\n,;:.-");
                }
            }

            $justSawClaim = false;
        }
        // Flush last group
        $flush();

        return $out;
    }
}

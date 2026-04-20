<?php

namespace App\Console\Commands;

use App\Models\BusinessDocument;
use App\Models\Claim;
use App\Models\ClaimStep;
use App\Models\Customer;
use App\Models\EzPassAccount;
use App\Models\LeaseDocumentChecklist;
use App\Models\LeaseDocumentItem;
use App\Models\OfficeTask;
use App\Models\PartsOrder;
use App\Models\RentalClaim;
use Illuminate\Console\Command;

class ImportAsanaData extends Command
{
    protected $signature = 'import:asana';
    protected $description = 'Import all data from Asana workspace into AutoGo database';

    private string $token;
    private string $baseUrl = 'https://app.asana.com/api/1.0';

    public function __construct()
    {
        parent::__construct();
        $this->token = (string) (config('services.asana.token') ?: env('ASANA_TOKEN', ''));
    }

    private array $projects = [
        ['gid' => '1203511376320076', 'name' => 'Claims',              'handler' => 'importClaims'],
        ['gid' => '1203527710634708', 'name' => 'Business Documents',  'handler' => 'importBusinessDocuments'],
        ['gid' => '1205241267337509', 'name' => 'Leasing',            'handler' => 'importLeasing'],
        ['gid' => '1205418054918103', 'name' => 'Rental Claims',      'handler' => 'importRentalClaims'],
        ['gid' => '1205418057333360', 'name' => 'M&M Car Rental',     'handler' => 'importMmCarRental'],
        ['gid' => '1208347547832630', 'name' => 'Office Work Ext 204', 'handler' => 'importOfficeTasks'],
        ['gid' => '1208352489115455', 'name' => 'EZ Pass',            'handler' => 'importEzPass'],
        ['gid' => '1208352489115483', 'name' => 'Parts',              'handler' => 'importParts'],
        ['gid' => '1208653230031688', 'name' => 'Office Work Ext 201', 'handler' => 'importOfficeTasks'],
    ];

    public function handle(): int
    {
        $this->info('Starting Asana import...');
        $this->newLine();

        foreach ($this->projects as $project) {
            $this->info("=== Importing: {$project['name']} (GID: {$project['gid']}) ===");

            $tasks = $this->getAllTasks($project['gid']);
            $this->info("  Found " . count($tasks) . " tasks");

            $handler = $project['handler'];
            $this->$handler($tasks, $project);

            $this->newLine();
        }

        $this->info('Asana import complete!');
        return 0;
    }

    // ── API helpers ──────────────────────────────────────────────────

    private function asanaGet(string $endpoint, array $params = []): ?array
    {
        $url = $this->baseUrl . $endpoint;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            $this->warn("    API error (HTTP {$httpCode}) for {$endpoint}");
            return null;
        }

        $decoded = json_decode($response, true);
        return $decoded ?? null;
    }

    private function getAllTasks(string $projectGid): array
    {
        $allTasks = [];
        $offset = null;

        do {
            $params = [
                'limit' => 100,
                'opt_fields' => 'name,completed,completed_at,notes,memberships.section.name',
            ];
            if ($offset) {
                $params['offset'] = $offset;
            }

            $result = $this->asanaGet("/projects/{$projectGid}/tasks", $params);
            if (!$result || !isset($result['data'])) {
                break;
            }

            $allTasks = array_merge($allTasks, $result['data']);

            $offset = $result['next_page']['offset'] ?? null;
        } while ($offset);

        return $allTasks;
    }

    private function getSubtasks(string $taskGid): array
    {
        $result = $this->asanaGet("/tasks/{$taskGid}/subtasks", [
            'opt_fields' => 'name,completed,completed_at',
        ]);

        return $result['data'] ?? [];
    }

    private function getSectionName(array $task): string
    {
        $memberships = $task['memberships'] ?? [];
        foreach ($memberships as $membership) {
            if (isset($membership['section']['name'])) {
                return $membership['section']['name'];
            }
        }
        return '';
    }

    // ── Customer matching ────────────────────────────────────────────

    private function findOrCreateCustomer(string $fullName): ?Customer
    {
        $fullName = trim($fullName);
        if (empty($fullName)) {
            return null;
        }

        $parts = preg_split('/\s+/', $fullName);
        if (count($parts) >= 2) {
            $lastName = array_pop($parts);
            $firstName = implode(' ', $parts);
        } else {
            $firstName = $fullName;
            $lastName = '';
        }

        // Try exact match first
        $customer = Customer::where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->first();

        if ($customer) {
            return $customer;
        }

        // Try case-insensitive match
        $customer = Customer::whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)])
            ->first();

        if ($customer) {
            return $customer;
        }

        // Create minimal customer record
        return Customer::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_active' => true,
            'can_receive_sms' => false,
        ]);
    }

    // ── Project import handlers ──────────────────────────────────────

    private function importClaims(array $tasks, array $project): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                // Skip section headers (they end with colon)
                $skipped++;
                continue;
            }

            $section = $this->getSectionName($task);
            $status = match (true) {
                str_contains(strtolower($section), 'new') => 'new',
                str_contains(strtolower($section), 'filled') || str_contains(strtolower($section), 'filed') => 'filed',
                str_contains(strtolower($section), 'progress') => 'in_progress',
                str_contains(strtolower($section), 'completed') || str_contains(strtolower($section), 'complete') => 'completed',
                default => 'new',
            };

            $customer = $this->findOrCreateCustomer($name);

            $notes = $task['notes'] ?? '';

            $claim = Claim::firstOrCreate(
                ['customer_id' => $customer?->id, 'notes' => $notes ?: null],
                [
                    'status' => $status,
                    'story' => $notes ?: null,
                ]
            );

            // Import subtasks as ClaimSteps
            $subtasks = $this->getSubtasks($task['gid']);
            foreach ($subtasks as $i => $subtask) {
                $stepName = trim($subtask['name'] ?? '');
                if (empty($stepName)) {
                    continue;
                }

                ClaimStep::firstOrCreate(
                    ['claim_id' => $claim->id, 'name' => $stepName],
                    [
                        'sort_order' => $i,
                        'is_completed' => $subtask['completed'] ?? false,
                        'completed_at' => ($subtask['completed'] ?? false) ? ($subtask['completed_at'] ?? now()) : null,
                    ]
                );
            }

            // If no subtasks were imported, generate default steps
            if (empty($subtasks)) {
                $claim->generateSteps();
            }

            $created++;
            $this->line("    [{$created}] Claim: {$name} ({$status})");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }

    private function importBusinessDocuments(array $tasks, array $project): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                $skipped++;
                continue;
            }

            $section = $this->getSectionName($task);
            $category = match (true) {
                str_contains(strtolower($section), 'high') && str_contains(strtolower($section), 'rental') => 'high_rental',
                str_contains(strtolower($section), 'general') => 'general',
                str_contains(strtolower($section), 'insurance') => 'insurance',
                str_contains(strtolower($section), 'license') => 'license',
                str_contains(strtolower($section), 'tax') => 'tax',
                default => 'general',
            };

            $notes = $task['notes'] ?? '';

            BusinessDocument::firstOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'status' => ($task['completed'] ?? false) ? 'complete' : 'pending',
                    'notes' => $notes ?: null,
                ]
            );

            $created++;
            $this->line("    [{$created}] Document: {$name} ({$category})");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }

    private function importLeasing(array $tasks, array $project): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                $skipped++;
                continue;
            }

            $section = $this->getSectionName($task);

            // Only import "Pending Damage Waiver" section tasks
            if (!str_contains(strtolower($section), 'pending') && !str_contains(strtolower($section), 'damage') && !str_contains(strtolower($section), 'waiver')) {
                // Also import if section is not specifically filtered (import all to be safe)
                // But prioritize Pending Damage Waiver
            }

            $customer = $this->findOrCreateCustomer($name);

            $checklist = LeaseDocumentChecklist::firstOrCreate(
                ['customer_id' => $customer?->id],
                [
                    'status' => ($task['completed'] ?? false) ? 'complete' : 'pending',
                    'notes' => $task['notes'] ?? null,
                ]
            );

            // Import subtasks as document items
            $subtasks = $this->getSubtasks($task['gid']);
            foreach ($subtasks as $i => $subtask) {
                $itemName = trim($subtask['name'] ?? '');
                if (empty($itemName)) {
                    continue;
                }

                $item = LeaseDocumentItem::firstOrCreate(
                    ['lease_document_checklist_id' => $checklist->id, 'name' => $itemName],
                    [
                        'sort_order' => $i,
                        'is_collected' => $subtask['completed'] ?? false,
                        'collected_at' => ($subtask['completed'] ?? false) ? ($subtask['completed_at'] ?? now()) : null,
                    ]
                );
            }

            // If no subtasks, generate default items
            if (empty($subtasks)) {
                $checklist->generateItems();
            }

            $created++;
            $this->line("    [{$created}] Lease checklist: {$name}");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }

    private function importRentalClaims(array $tasks, array $project): void
    {
        $this->importRentalClaimsWithBrand($tasks, 'default');
    }

    private function importMmCarRental(array $tasks, array $project): void
    {
        $this->importRentalClaimsWithBrand($tasks, 'mm_car_rental');
    }

    private function importRentalClaimsWithBrand(array $tasks, string $brand): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                $skipped++;
                continue;
            }

            $section = $this->getSectionName($task);
            $status = match (true) {
                str_contains(strtolower($section), 'new') => 'new',
                str_contains(strtolower($section), 'pending') => 'pending_documents',
                str_contains(strtolower($section), 'completed') || str_contains(strtolower($section), 'complete') => 'completed',
                default => 'new',
            };

            $customer = $this->findOrCreateCustomer($name);

            RentalClaim::firstOrCreate(
                ['customer_id' => $customer?->id, 'brand' => $brand],
                [
                    'status' => $status,
                    'notes' => $task['notes'] ?? null,
                    'damage_description' => $task['notes'] ?? null,
                ]
            );

            $created++;
            $this->line("    [{$created}] Rental claim: {$name} ({$status}, brand={$brand})");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }

    private function importOfficeTasks(array $tasks, array $project): void
    {
        $created = 0;
        $skipped = 0;

        // Determine which extension based on project name
        $ext = str_contains($project['name'], '201') ? '201' : '204';

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                $skipped++;
                continue;
            }

            $section = $this->getSectionName($task);
            $sectionValue = match (true) {
                str_contains(strtolower($section), 'today') => 'today',
                str_contains(strtolower($section), 'to do') || str_contains(strtolower($section), 'todo') => 'todo',
                str_contains(strtolower($section), 'recurring') => 'recurring',
                str_contains(strtolower($section), 'completed') || str_contains(strtolower($section), 'complete') => 'completed',
                default => 'todo',
            };

            $isCompleted = ($task['completed'] ?? false) || $sectionValue === 'completed';
            $isRecurring = $sectionValue === 'recurring';

            OfficeTask::firstOrCreate(
                ['title' => $name, 'description' => "Imported from Asana - Office Work Ext {$ext}"],
                [
                    'section' => $sectionValue,
                    'is_completed' => $isCompleted,
                    'completed_at' => $isCompleted ? ($task['completed_at'] ?? now()) : null,
                    'is_recurring' => $isRecurring,
                    'notes' => $task['notes'] ?? null,
                ]
            );

            $created++;
            $this->line("    [{$created}] Office task (Ext {$ext}): {$name} ({$sectionValue})");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }

    private function importEzPass(array $tasks, array $project): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                $skipped++;
                continue;
            }

            $customer = $this->findOrCreateCustomer($name);

            EzPassAccount::firstOrCreate(
                ['customer_id' => $customer?->id],
                [
                    'status' => ($task['completed'] ?? false) ? 'active' : 'pending',
                    'notes' => $task['notes'] ?? null,
                ]
            );

            $created++;
            $this->line("    [{$created}] EZ Pass: {$name}");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }

    private function importParts(array $tasks, array $project): void
    {
        $created = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $name = trim($task['name'] ?? '');
            if (empty($name) || str_ends_with($name, ':')) {
                $skipped++;
                continue;
            }

            $status = ($task['completed'] ?? false) ? 'out' : 'pending';

            PartsOrder::firstOrCreate(
                ['vehicle_description' => $name],
                [
                    'status' => $status,
                    'notes' => $task['notes'] ?? null,
                    'parts_list' => $task['notes'] ?? null,
                ]
            );

            $created++;
            $this->line("    [{$created}] Parts order: {$name} ({$status})");
        }

        $this->info("  Created: {$created}, Skipped: {$skipped}");
    }
}

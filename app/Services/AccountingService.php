<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PeriodClosing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    // ── Chart of Accounts ──────────────────────────────
    public function getAllAccounts(): Collection
    {
        return ChartOfAccount::with('parent')->orderBy('code')->get();
    }

    public function getActiveAccounts(): Collection
    {
        return ChartOfAccount::where('is_active', true)->orderBy('code')->get();
    }

    public function findAccount(int $id): ChartOfAccount
    {
        return ChartOfAccount::findOrFail($id);
    }

    public function findAccountByCode(string $code): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', $code)->first();
    }

    public function createAccount(array $data): ChartOfAccount
    {
        return ChartOfAccount::create($data);
    }

    public function updateAccount(ChartOfAccount $account, array $data): ChartOfAccount
    {
        $account->update($data);
        return $account;
    }

    public function deleteAccount(ChartOfAccount $account): void
    {
        if ($account->is_system) {
            throw new \RuntimeException('Cannot delete a system account.');
        }
        if ($account->journalEntryLines()->exists()) {
            throw new \RuntimeException('Cannot delete an account with journal entries.');
        }
        $account->delete();
    }

    // ── Journal Entries ────────────────────────────────
    public function paginateJournalEntries(int $perPage = 25): LengthAwarePaginator
    {
        return JournalEntry::with(['lines.account', 'user'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findJournalEntry(int $id): JournalEntry
    {
        return JournalEntry::with(['lines.account', 'user'])->findOrFail($id);
    }

    public function createJournalEntry(array $data, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($data, $lines) {
            $entry = JournalEntry::create(array_merge($data, [
                'entry_number' => JournalEntry::generateEntryNumber(),
            ]));

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines.account');
        });
    }

    public function updateJournalEntry(JournalEntry $entry, array $data, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data, $lines) {
            $entry->update($data);
            $entry->lines()->delete();

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines.account');
        });
    }

    public function deleteJournalEntry(JournalEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });
    }

    // ── Specialized Recording Methods ──────────────────
    public function recordPayment(string $debitAccountCode, string $creditAccountCode, float $amount, string $description, ?int $userId = null, ?string $refType = null, ?int $refId = null): JournalEntry
    {
        $debitAccount = $this->findAccountByCode($debitAccountCode);
        $creditAccount = $this->findAccountByCode($creditAccountCode);

        if (!$debitAccount || !$creditAccount) {
            throw new \RuntimeException("Account not found for codes: {$debitAccountCode}, {$creditAccountCode}");
        }

        return $this->createJournalEntry([
            'date' => now()->toDateString(),
            'description' => $description,
            'user_id' => $userId,
            'reference_type' => $refType,
            'reference_id' => $refId,
        ], [
            ['account_id' => $debitAccount->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $amount],
        ]);
    }

    public function recordRentalRevenue(float $amount, string $description, ?int $userId = null, ?string $refType = null, ?int $refId = null): JournalEntry
    {
        return $this->recordPayment('1110', '4000', $amount, $description, $userId, $refType, $refId);
    }

    public function recordRentalPaymentReceived(float $amount, string $description, ?int $userId = null, ?string $refType = null, ?int $refId = null): JournalEntry
    {
        return $this->recordPayment('1010', '1110', $amount, $description, $userId, $refType, $refId);
    }

    public function recordLeasingCommission(float $amount, string $description, ?int $userId = null, ?string $refType = null, ?int $refId = null): JournalEntry
    {
        return $this->recordPayment('1120', '4100', $amount, $description, $userId, $refType, $refId);
    }

    public function recordBodyshopRevenue(float $amount, string $description, ?int $userId = null, ?string $refType = null, ?int $refId = null): JournalEntry
    {
        return $this->recordPayment('1130', '4200', $amount, $description, $userId, $refType, $refId);
    }

    public function recordInsuranceClaimPayment(float $amount, string $description, ?int $userId = null, ?string $refType = null, ?int $refId = null): JournalEntry
    {
        return $this->recordPayment('1010', '1140', $amount, $description, $userId, $refType, $refId);
    }

    public function recordExpense(string $expenseAccountCode, float $amount, string $description, ?int $userId = null): JournalEntry
    {
        return $this->recordPayment($expenseAccountCode, '1010', $amount, $description, $userId);
    }

    // ── Financial Reports ──────────────────────────────
    public function getTrialBalance(?string $asOf = null): array
    {
        $query = JournalEntryLine::query()
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id');

        if ($asOf) {
            $query->where('journal_entries.date', '<=', $asOf);
        }

        $balances = $query->select(
            'chart_of_accounts.id',
            'chart_of_accounts.code',
            'chart_of_accounts.name',
            'chart_of_accounts.type',
            'chart_of_accounts.department',
            DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
            DB::raw('SUM(journal_entry_lines.credit) as total_credit')
        )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type', 'chart_of_accounts.department')
            ->orderBy('chart_of_accounts.code')
            ->get();

        return [
            'balances' => $balances,
            'total_debit' => $balances->sum('total_debit'),
            'total_credit' => $balances->sum('total_credit'),
            'as_of' => $asOf ?? now()->toDateString(),
        ];
    }

    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null, ?string $department = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        $query = JournalEntryLine::query()
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereBetween('journal_entries.date', [$startDate, $endDate])
            ->whereIn('chart_of_accounts.type', ['revenue', 'expense']);

        if ($department) {
            $query->where('chart_of_accounts.department', $department);
        }

        $items = $query->select(
            'chart_of_accounts.id',
            'chart_of_accounts.code',
            'chart_of_accounts.name',
            'chart_of_accounts.type',
            'chart_of_accounts.department',
            DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
            DB::raw('SUM(journal_entry_lines.credit) as total_credit')
        )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type', 'chart_of_accounts.department')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $revenue = $items->where('type', 'revenue');
        $expenses = $items->where('type', 'expense');
        $totalRevenue = $revenue->sum('total_credit') - $revenue->sum('total_debit');
        $totalExpenses = $expenses->sum('total_debit') - $expenses->sum('total_credit');

        return [
            'revenue' => $revenue->values(),
            'expenses' => $expenses->values(),
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_income' => $totalRevenue - $totalExpenses,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'department' => $department,
        ];
    }

    public function getBalanceSheet(?string $asOf = null): array
    {
        $asOf = $asOf ?? now()->toDateString();

        $query = JournalEntryLine::query()
            ->join('chart_of_accounts', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.date', '<=', $asOf)
            ->whereIn('chart_of_accounts.type', ['asset', 'liability', 'equity']);

        $items = $query->select(
            'chart_of_accounts.id',
            'chart_of_accounts.code',
            'chart_of_accounts.name',
            'chart_of_accounts.type',
            'chart_of_accounts.subtype',
            DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
            DB::raw('SUM(journal_entry_lines.credit) as total_credit')
        )
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name', 'chart_of_accounts.type', 'chart_of_accounts.subtype')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $assets = $items->where('type', 'asset');
        $liabilities = $items->where('type', 'liability');
        $equity = $items->where('type', 'equity');

        $totalAssets = $assets->sum('total_debit') - $assets->sum('total_credit');
        $totalLiabilities = $liabilities->sum('total_credit') - $liabilities->sum('total_debit');
        $totalEquity = $equity->sum('total_credit') - $equity->sum('total_debit');

        return [
            'assets' => $assets->values(),
            'liabilities' => $liabilities->values(),
            'equity' => $equity->values(),
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'as_of' => $asOf,
        ];
    }

    public function getAccountLedger(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = $this->findAccount($accountId);

        $query = JournalEntryLine::with(['journalEntry'])
            ->where('account_id', $accountId);

        if ($startDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('date', '>=', $startDate));
        }
        if ($endDate) {
            $query->whereHas('journalEntry', fn($q) => $q->where('date', '<=', $endDate));
        }

        $lines = $query->get()->sortBy(fn($line) => $line->journalEntry->date);

        $runningBalance = 0;
        $ledger = $lines->map(function ($line) use ($account, &$runningBalance) {
            $isDebitNormal = in_array($account->type, ['asset', 'expense']);
            $amount = $isDebitNormal
                ? ($line->debit - $line->credit)
                : ($line->credit - $line->debit);
            $runningBalance += $amount;

            return [
                'date' => $line->journalEntry->date->toDateString(),
                'entry_number' => $line->journalEntry->entry_number,
                'description' => $line->journalEntry->description,
                'memo' => $line->memo,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'balance' => $runningBalance,
            ];
        });

        return [
            'account' => $account,
            'entries' => $ledger->values(),
            'ending_balance' => $runningBalance,
        ];
    }

    // ── Period Closing ─────────────────────────────────
    public function closePeriod(string $periodStart, string $periodEnd, ?int $userId = null, ?string $notes = null): PeriodClosing
    {
        return DB::transaction(function () use ($periodStart, $periodEnd, $userId, $notes) {
            $pnl = $this->getProfitAndLoss($periodStart, $periodEnd);
            $netIncome = $pnl['net_income'];
            $retainedEarnings = $this->findAccountByCode('3100');

            if (!$retainedEarnings) {
                throw new \RuntimeException('Retained Earnings account (3100) not found.');
            }

            $lines = [];
            foreach ($pnl['revenue'] as $rev) {
                $amount = $rev['total_credit'] - $rev['total_debit'];
                if ($amount != 0) {
                    $lines[] = ['account_id' => $rev['id'], 'debit' => $amount > 0 ? $amount : 0, 'credit' => $amount < 0 ? abs($amount) : 0];
                }
            }
            foreach ($pnl['expenses'] as $exp) {
                $amount = $exp['total_debit'] - $exp['total_credit'];
                if ($amount != 0) {
                    $lines[] = ['account_id' => $exp['id'], 'debit' => 0, 'credit' => $amount > 0 ? $amount : 0];
                }
            }

            if ($netIncome > 0) {
                $lines[] = ['account_id' => $retainedEarnings->id, 'debit' => 0, 'credit' => $netIncome];
            } elseif ($netIncome < 0) {
                $lines[] = ['account_id' => $retainedEarnings->id, 'debit' => abs($netIncome), 'credit' => 0];
            }

            $journalEntry = null;
            if (!empty($lines)) {
                $journalEntry = $this->createJournalEntry([
                    'date' => $periodEnd,
                    'description' => "Period closing: {$periodStart} to {$periodEnd}",
                    'user_id' => $userId,
                ], $lines);
            }

            return PeriodClosing::create([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'journal_entry_id' => $journalEntry?->id,
                'user_id' => $userId,
                'closed_at' => now(),
                'notes' => $notes,
            ]);
        });
    }

    public function getPeriodClosings(): Collection
    {
        return PeriodClosing::with(['journalEntry', 'user'])->orderByDesc('period_end')->get();
    }

    // ── Department Summary ─────────────────────────────
    public function getDepartmentSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $departments = ['rental', 'leasing', 'bodyshop', 'insurance'];
        $summary = [];

        foreach ($departments as $dept) {
            $pnl = $this->getProfitAndLoss($startDate, $endDate, $dept);
            $summary[$dept] = [
                'revenue' => $pnl['total_revenue'],
                'expenses' => $pnl['total_expenses'],
                'net_income' => $pnl['net_income'],
            ];
        }

        return $summary;
    }
}

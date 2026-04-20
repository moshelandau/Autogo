<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function __construct(private readonly AccountingService $accounting) {}

    // Chart of Accounts
    public function chartOfAccounts()
    {
        return Inertia::render('Accounting/ChartOfAccounts', [
            'accounts' => $this->accounting->getAllAccounts(),
        ]);
    }

    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:chart_of_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'subtype' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'department' => 'nullable|in:rental,leasing,bodyshop,insurance,general',
        ]);

        $this->accounting->createAccount($validated);

        return back()->with('success', 'Account created.');
    }

    public function updateAccount(Request $request, ChartOfAccount $account)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subtype' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'department' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $this->accounting->updateAccount($account, $validated);

        return back()->with('success', 'Account updated.');
    }

    public function deleteAccount(ChartOfAccount $account)
    {
        try {
            $this->accounting->deleteAccount($account);
            return back()->with('success', 'Account deleted.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // Journal Entries
    public function journalEntries()
    {
        return Inertia::render('Accounting/JournalEntries', [
            'entries' => $this->accounting->paginateJournalEntries(),
        ]);
    }

    public function showJournalEntry(int $id)
    {
        return Inertia::render('Accounting/JournalEntryShow', [
            'entry' => $this->accounting->findJournalEntry($id),
        ]);
    }

    public function storeJournalEntry(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.memo' => 'nullable|string',
        ]);

        $totalDebit = collect($validated['lines'])->sum('debit');
        $totalCredit = collect($validated['lines'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withErrors(['lines' => 'Debits must equal credits.']);
        }

        $this->accounting->createJournalEntry(
            ['date' => $validated['date'], 'description' => $validated['description'], 'user_id' => auth()->id()],
            $validated['lines']
        );

        return redirect()->route('accounting.journal-entries')->with('success', 'Journal entry created.');
    }

    // Reports
    public function trialBalance(Request $request)
    {
        return Inertia::render('Accounting/TrialBalance', [
            'report' => $this->accounting->getTrialBalance($request->as_of),
        ]);
    }

    public function profitLoss(Request $request)
    {
        return Inertia::render('Accounting/ProfitLoss', [
            'report' => $this->accounting->getProfitAndLoss(
                $request->start_date,
                $request->end_date,
                $request->department
            ),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        return Inertia::render('Accounting/BalanceSheet', [
            'report' => $this->accounting->getBalanceSheet($request->as_of),
        ]);
    }

    public function accountLedger(Request $request, int $accountId)
    {
        return Inertia::render('Accounting/AccountRegister', [
            'report' => $this->accounting->getAccountLedger($accountId, $request->start_date, $request->end_date),
        ]);
    }
}

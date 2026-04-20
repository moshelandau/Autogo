<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1000s)
            ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'current', 'is_system' => true],
            ['code' => '1010', 'name' => 'Checking Account', 'type' => 'asset', 'subtype' => 'current', 'is_system' => true],
            ['code' => '1020', 'name' => 'Savings Account', 'type' => 'asset', 'subtype' => 'current'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'current', 'is_system' => true],
            ['code' => '1110', 'name' => 'Rental A/R', 'type' => 'asset', 'subtype' => 'current', 'department' => 'rental'],
            ['code' => '1120', 'name' => 'Leasing A/R', 'type' => 'asset', 'subtype' => 'current', 'department' => 'leasing'],
            ['code' => '1130', 'name' => 'Bodyshop A/R', 'type' => 'asset', 'subtype' => 'current', 'department' => 'bodyshop'],
            ['code' => '1140', 'name' => 'Insurance Claims Receivable', 'type' => 'asset', 'subtype' => 'current', 'department' => 'insurance'],
            ['code' => '1200', 'name' => 'Security Deposits Held', 'type' => 'asset', 'subtype' => 'current', 'department' => 'rental'],
            ['code' => '1500', 'name' => 'Vehicles - Fleet', 'type' => 'asset', 'subtype' => 'fixed', 'department' => 'rental'],
            ['code' => '1510', 'name' => 'Vehicles - Inventory', 'type' => 'asset', 'subtype' => 'fixed', 'department' => 'leasing'],
            ['code' => '1600', 'name' => 'Equipment', 'type' => 'asset', 'subtype' => 'fixed'],
            ['code' => '1700', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'subtype' => 'fixed'],
            // Liabilities (2000s)
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'current', 'is_system' => true],
            ['code' => '2100', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'subtype' => 'current', 'is_system' => true],
            ['code' => '2200', 'name' => 'Customer Deposits', 'type' => 'liability', 'subtype' => 'current', 'department' => 'rental'],
            ['code' => '2300', 'name' => 'Unearned Revenue', 'type' => 'liability', 'subtype' => 'current'],
            // Equity (3000s)
            ['code' => '3000', 'name' => 'Owner\'s Equity', 'type' => 'equity', 'is_system' => true],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'is_system' => true],
            // Revenue (4000s)
            ['code' => '4000', 'name' => 'Rental Revenue', 'type' => 'revenue', 'department' => 'rental', 'is_system' => true],
            ['code' => '4010', 'name' => 'Rental Add-Ons Revenue', 'type' => 'revenue', 'department' => 'rental'],
            ['code' => '4020', 'name' => 'Late Fees Revenue', 'type' => 'revenue', 'department' => 'rental'],
            ['code' => '4100', 'name' => 'Leasing Commissions', 'type' => 'revenue', 'department' => 'leasing'],
            ['code' => '4110', 'name' => 'Financing Commissions', 'type' => 'revenue', 'department' => 'leasing'],
            ['code' => '4200', 'name' => 'Bodyshop Labor Revenue', 'type' => 'revenue', 'department' => 'bodyshop'],
            ['code' => '4210', 'name' => 'Bodyshop Parts Revenue', 'type' => 'revenue', 'department' => 'bodyshop'],
            ['code' => '4300', 'name' => 'Towing Revenue', 'type' => 'revenue', 'department' => 'bodyshop'],
            ['code' => '4400', 'name' => 'Insurance Claim Payments', 'type' => 'revenue', 'department' => 'insurance'],
            // Expenses (5000s-6000s)
            ['code' => '5000', 'name' => 'Vehicle Expenses', 'type' => 'expense', 'department' => 'rental'],
            ['code' => '5010', 'name' => 'Fuel', 'type' => 'expense', 'department' => 'rental'],
            ['code' => '5020', 'name' => 'Maintenance & Repairs', 'type' => 'expense'],
            ['code' => '5030', 'name' => 'Vehicle Insurance', 'type' => 'expense'],
            ['code' => '5040', 'name' => 'Depreciation - Vehicles', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'Parts Cost', 'type' => 'expense', 'department' => 'bodyshop'],
            ['code' => '5110', 'name' => 'Paint & Materials', 'type' => 'expense', 'department' => 'bodyshop'],
            ['code' => '6000', 'name' => 'Payroll', 'type' => 'expense'],
            ['code' => '6100', 'name' => 'Rent', 'type' => 'expense'],
            ['code' => '6200', 'name' => 'Utilities', 'type' => 'expense'],
            ['code' => '6300', 'name' => 'Office Supplies', 'type' => 'expense'],
            ['code' => '6400', 'name' => 'Marketing', 'type' => 'expense'],
            ['code' => '6500', 'name' => 'Professional Fees', 'type' => 'expense'],
            ['code' => '6600', 'name' => 'Bank Fees', 'type' => 'expense'],
            ['code' => '6700', 'name' => 'CC Processing Fees', 'type' => 'expense'],
            ['code' => '6800', 'name' => 'Toll Charges', 'type' => 'expense', 'department' => 'rental'],
            ['code' => '6900', 'name' => 'Miscellaneous', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['code' => $account['code']],
                array_merge($account, ['is_active' => true, 'is_system' => $account['is_system'] ?? false, 'department' => $account['department'] ?? null])
            );
        }
    }
}

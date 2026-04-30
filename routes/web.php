<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\RentalClaimController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\LeaseDocumentController;
use App\Http\Controllers\LenderController;
use App\Http\Controllers\OfficeTaskController;
use App\Http\Controllers\RentalDashboardController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SmsConversationController;
use App\Http\Controllers\PermissionTypeController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\BusinessDocumentController;
use App\Http\Controllers\EzPassController;
use App\Http\Controllers\PartsOrderController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Public, no-auth: customer-facing lease application form. Authorized
// by the random web_token in the URL. The customer was sent the link
// via SMS by the bot.
Route::get ('apply/{token}',                       [\App\Http\Controllers\PublicApplicationController::class, 'show'])->name('public.apply.show');
Route::post('apply/{token}',                       [\App\Http\Controllers\PublicApplicationController::class, 'submit'])->name('public.apply.submit');
Route::post('apply/{token}/send-otp',              [\App\Http\Controllers\PublicApplicationController::class, 'sendOtp'])->name('public.apply.send-otp');
Route::post('apply/{token}/verify-otp',            [\App\Http\Controllers\PublicApplicationController::class, 'verifyOtp'])->name('public.apply.verify-otp');
Route::get ('apply/{token}/done',                  [\App\Http\Controllers\PublicApplicationController::class, 'done'])->name('public.apply.done');
Route::get ('apply/{token}/step/{step}',           [\App\Http\Controllers\PublicApplicationController::class, 'showStep'])->name('public.apply.show.step');
Route::post('apply/{token}/step/{step}',           [\App\Http\Controllers\PublicApplicationController::class, 'submitStep'])->name('public.apply.submit.step');
Route::get ('apply/{token}/step/{step}/done',      [\App\Http\Controllers\PublicApplicationController::class, 'stepDone'])->name('public.apply.step.done');

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Customers
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/documents', [\App\Http\Controllers\CustomerDocumentController::class, 'store'])->name('customers.documents.store');
    Route::delete('customers/{customer}/documents/{document}', [\App\Http\Controllers\CustomerDocumentController::class, 'destroy'])->name('customers.documents.destroy');
    Route::get('customers-search', [CustomerController::class, 'search'])->name('customers.search');
    Route::post('customers-quick', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
    Route::get('customers/{customer}/scan',  [\App\Http\Controllers\CustomerScanController::class, 'index'])->name('customers.scan');
    Route::post('customers/{customer}/scan', [\App\Http\Controllers\CustomerScanController::class, 'ingest'])->name('customers.scan.ingest');
    Route::post('customers/{customer}/text-application', [\App\Http\Controllers\CustomerController::class, 'textApplication'])->name('customers.text-application');

    // Multi-phone management
    Route::get   ('customers/{customer}/phones',           [\App\Http\Controllers\CustomerPhoneController::class, 'index'])->name('customers.phones.index');
    Route::post  ('customers/{customer}/phones',           [\App\Http\Controllers\CustomerPhoneController::class, 'store'])->name('customers.phones.store');
    Route::put   ('customers/{customer}/phones/{phone}',   [\App\Http\Controllers\CustomerPhoneController::class, 'update'])->name('customers.phones.update');
    Route::delete('customers/{customer}/phones/{phone}',   [\App\Http\Controllers\CustomerPhoneController::class, 'destroy'])->name('customers.phones.destroy');

    // SMS bot intake sessions (lease/rental) — including incomplete
    Route::get('bot-sessions',     [\App\Http\Controllers\BotSessionController::class, 'index'])->name('bot-sessions.index');
    Route::get('bot-sessions/{session}', [\App\Http\Controllers\BotSessionController::class, 'show'])->name('bot-sessions.show');
    Route::post('bot-sessions/{session}/finalize', [\App\Http\Controllers\BotSessionController::class, 'finalize'])->name('bot-sessions.finalize');
    Route::post('bot-sessions/{session}/abort',    [\App\Http\Controllers\BotSessionController::class, 'abort'])->name('bot-sessions.abort');
    Route::post('scan/license-extract',      [\App\Http\Controllers\CustomerScanController::class, 'extractLicense'])->name('scan.license-extract');
    Route::post('scan/any-extract',          [\App\Http\Controllers\CustomerScanController::class, 'extractAny'])->name('scan.any-extract');

    // Tokenized cards on file (PAN -> Cardknox -> xToken; PAN never persisted)
    Route::get   ('customers/{customer}/cards',                [\App\Http\Controllers\CustomerCardController::class, 'index'])->name('customers.cards.index');
    Route::post  ('customers/{customer}/cards',                [\App\Http\Controllers\CustomerCardController::class, 'store'])->name('customers.cards.store');
    Route::post  ('customers/{customer}/cards/{card}/default', [\App\Http\Controllers\CustomerCardController::class, 'setDefault'])->name('customers.cards.default');
    Route::delete('customers/{customer}/cards/{card}',         [\App\Http\Controllers\CustomerCardController::class, 'destroy'])->name('customers.cards.destroy');

    // ── Car Rental Module ──────────────────────────────
    Route::prefix('rental')->name('rental.')->group(function () {
        Route::get('/', RentalDashboardController::class)->name('dashboard');
        Route::resource('vehicles', VehicleController::class)->except(['destroy']);

        // Reservations
        Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::post('reservations/{reservation}/pickup', [ReservationController::class, 'pickup'])->name('reservations.pickup');
        Route::post('reservations/{reservation}/return', [ReservationController::class, 'return'])->name('reservations.return');
        Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::post('reservations/{reservation}/generate-agreement', [ReservationController::class, 'generateAgreement'])->name('reservations.generate-agreement');
        Route::post('reservations/{reservation}/payment', [ReservationController::class, 'recordPayment'])->name('reservations.payment');
        Route::post('reservations/{reservation}/open-claim', [ReservationController::class, 'openClaim'])->name('reservations.openClaim');
        Route::get('reservations/{reservation}/agreement/preview', [\App\Http\Controllers\RentalAgreementController::class, 'preview'])->name('reservations.agreement.preview');
        Route::post('reservations/{reservation}/agreement', [\App\Http\Controllers\RentalAgreementController::class, 'generate'])->name('reservations.agreement.generate');

        // Agreement revisions (immutable history per reservation)
        Route::get('reservations/{reservation}/revisions', [\App\Http\Controllers\AgreementRevisionController::class, 'listForReservation'])->name('reservations.revisions');
    });

    // Revision-level actions (can be from anywhere)
    Route::get('revisions/{revision}/download', [\App\Http\Controllers\AgreementRevisionController::class, 'download'])->name('reservations.revisions.download');
    Route::post('revisions/{revision}/email', [\App\Http\Controllers\AgreementRevisionController::class, 'email'])->name('reservations.revisions.email');

    // Audit Logs UI (immutable, append-only)
    Route::get('audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');

    // Notification bell
    Route::post('notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all',  [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Vehicle Violations (parking, camera, school bus, toll, etc.)
    Route::resource('violations', \App\Http\Controllers\VehicleViolationController::class)
        ->only(['index','create','store','show','update']);
    Route::post('violations/{violation}/bill-renter', [\App\Http\Controllers\VehicleViolationController::class, 'billRenter'])->name('violations.bill-renter');

    Route::prefix('rental')->name('rental.')->group(function () {
        // (all other rental routes already in this group above — placeholder to keep routes group structure)
        Route::post('holds/{hold}/release', [ReservationController::class, 'releaseHold'])->name('holds.release');
        Route::post('holds/{hold}/capture', [ReservationController::class, 'captureHold'])->name('holds.capture');

        // Vehicle Inspections (before/after images)
        Route::post('reservations/{reservation}/inspection', [InspectionController::class, 'upload'])->name('inspection.upload');
        Route::delete('reservations/{reservation}/inspection/{inspection}', [InspectionController::class, 'destroy'])->name('inspection.destroy');
        Route::get('reservations/{reservation}/inspection/{type}', [InspectionController::class, 'status'])->name('inspection.status');
        Route::get('reservations/{reservation}/inspection-compare', [InspectionController::class, 'compare'])->name('inspection.compare');
        Route::post('inspection/{inspection}/flag', [InspectionController::class, 'flagDamage'])->name('inspection.flag');
        Route::post('inspection/{inspection}/analyze', [InspectionController::class, 'analyzeImage'])->name('inspection.analyze');
        Route::post('reservations/{reservation}/analyze-all', [InspectionController::class, 'analyzeReservation'])->name('inspection.analyze-all');

        Route::get('calendar', [ReservationController::class, 'calendar'])->name('calendar');
    });

    // ── SMS (Telebroad) ────────────────────────────────
    Route::get('sms', [SmsConversationController::class, 'index'])->name('sms.index');
    Route::get('sms/thread/{phone}', [SmsConversationController::class, 'show'])->name('sms.show')->where('phone', '[0-9+\-\s\(\)]+');
    Route::get('sms/customer/{customer}/thread', [SmsConversationController::class, 'customerThread'])->name('sms.customer-thread');
    Route::post('sms/thread/{phone}/assign',    [SmsConversationController::class, 'assign'])->name('sms.assign')->where('phone', '[0-9+\-\s\(\)]+');
    Route::post('sms/thread/{phone}/resolve',   [SmsConversationController::class, 'resolve'])->name('sms.resolve')->where('phone', '[0-9+\-\s\(\)]+');
    Route::post('sms/thread/{phone}/unresolve', [SmsConversationController::class, 'unresolve'])->name('sms.unresolve')->where('phone', '[0-9+\-\s\(\)]+');
    Route::post('sms/message/{id}/status', [SmsConversationController::class, 'markStatus'])->name('sms.mark-status');
    Route::post('sms/send',      [SmsController::class, 'send'])->name('sms.send');
    Route::get ('sms/templates', [SmsController::class, 'templates'])->name('sms.templates');

    // SMS template management UI
    Route::get   ('settings/sms-templates',            [\App\Http\Controllers\SmsTemplateController::class, 'index'])->name('settings.sms-templates.index');
    Route::post  ('settings/sms-templates',            [\App\Http\Controllers\SmsTemplateController::class, 'store'])->name('settings.sms-templates.store');
    Route::put   ('settings/sms-templates/{template}', [\App\Http\Controllers\SmsTemplateController::class, 'update'])->name('settings.sms-templates.update');
    Route::delete('settings/sms-templates/{template}', [\App\Http\Controllers\SmsTemplateController::class, 'destroy'])->name('settings.sms-templates.destroy');

    // ── Car Leasing / Financing Module ─────────────────
    Route::prefix('leasing')->name('leasing.')->group(function () {
        // Deals Pipeline
        Route::get('/', [DealController::class, 'index'])->name('deals.index');
        Route::get('deals/create', [DealController::class, 'create'])->name('deals.create');
        Route::post('deals', [DealController::class, 'store'])->name('deals.store');
        Route::get('deals/{deal}', [DealController::class, 'show'])->name('deals.show');
        Route::put('deals/{deal}', [DealController::class, 'update'])->name('deals.update');
        Route::post('deals/{deal}/transition', [DealController::class, 'transition'])->name('deals.transition');
        Route::post('deals/{deal}/reorder', [DealController::class, 'reorder'])->name('deals.reorder');
        Route::post('deals/{deal}/lost', [DealController::class, 'markLost'])->name('deals.lost');

        // Deal sub-actions
        Route::post('deals/{deal}/tasks/{task}', [DealController::class, 'completeTask'])->name('deals.task');
        Route::post('deals/{deal}/note', [DealController::class, 'addNote'])->name('deals.note');
        Route::post('deals/{deal}/quote', [DealController::class, 'addQuote'])->name('deals.quote');
        Route::post('deals/{deal}/quotes/{quote}/select', [DealController::class, 'selectQuote'])->name('deals.select-quote');
        Route::put   ('deals/{deal}/quotes/{quote}', [DealController::class, 'updateQuote'])->name('deals.update-quote');
        Route::delete('deals/{deal}/quotes/{quote}', [DealController::class, 'deleteQuote'])->name('deals.delete-quote');
        Route::post  ('deals/{deal}/documents', [DealController::class, 'uploadDocument'])->name('deals.documents.store');
        Route::post('deals/{deal}/credit-pull', [DealController::class, 'pullCredit'])->name('deals.credit-pull');

        // Editable web-form version of the verified PDF application + email to dealer
        Route::get('deals/{deal}/application',         [\App\Http\Controllers\LeaseApplicationFormController::class, 'show'])->name('deals.application.show');
        Route::put('deals/{deal}/application',         [\App\Http\Controllers\LeaseApplicationFormController::class, 'update'])->name('deals.application.update');
        Route::get('deals/{deal}/application/pdf',     [\App\Http\Controllers\LeaseApplicationFormController::class, 'pdf'])->name('deals.application.pdf');
        Route::post('deals/{deal}/application/approve',[\App\Http\Controllers\LeaseApplicationFormController::class, 'approveField'])->name('deals.application.approve');
        Route::post('deals/{deal}/application/email',  [\App\Http\Controllers\LeaseApplicationFormController::class, 'emailToDealer'])->name('deals.application.email');

        // VIN Decode API
        Route::post('vin-decode', [DealController::class, 'decodeVin'])->name('vin-decode');

        // Lease Document Checklists (Damage Waiver)
        Route::get('documents', [LeaseDocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/create', [LeaseDocumentController::class, 'create'])->name('documents.create');
        Route::post('documents', [LeaseDocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{checklist}', [LeaseDocumentController::class, 'show'])->name('documents.show');
        Route::post('documents/{checklist}/items/{item}', [LeaseDocumentController::class, 'toggleItem'])->name('documents.toggle');

        // Lenders
        Route::get('lenders', [LenderController::class, 'index'])->name('lenders.index');
        Route::post('lenders', [LenderController::class, 'store'])->name('lenders.store');
        Route::put('lenders/{lender}', [LenderController::class, 'update'])->name('lenders.update');
    });

    // ── Rental Claims Module ───────────────────────────
    Route::prefix('rental-claims')->name('rental-claims.')->group(function () {
        Route::get('/', [RentalClaimController::class, 'index'])->name('index');
        Route::get('create', [RentalClaimController::class, 'create'])->name('create');
        Route::post('/', [RentalClaimController::class, 'store'])->name('store');
        Route::get('{rentalClaim}', [RentalClaimController::class, 'show'])->name('show');
        Route::post('{rentalClaim}/status', [RentalClaimController::class, 'updateStatus'])->name('status');
        Route::post('{rentalClaim}/comment', [RentalClaimController::class, 'addComment'])->name('comment');
        Route::post('{rentalClaim}/photos', [RentalClaimController::class, 'uploadPhoto'])->name('photos.store');
        Route::delete('{rentalClaim}/photos/{document}', [RentalClaimController::class, 'deletePhoto'])->name('photos.destroy');
    });

    // ── Towing Module ─────────────────────────────────
    Route::prefix('towing')->name('towing.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TowJobController::class, 'index'])->name('index');
        Route::get('board', [\App\Http\Controllers\TowJobController::class, 'board'])->name('board');
        Route::get('create', [\App\Http\Controllers\TowJobController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\TowJobController::class, 'store'])->name('store');
        Route::get('{towJob}', [\App\Http\Controllers\TowJobController::class, 'show'])->name('show');
        Route::put('{towJob}', [\App\Http\Controllers\TowJobController::class, 'update'])->name('update');
        Route::post('{towJob}/status', [\App\Http\Controllers\TowJobController::class, 'setStatus'])->name('status');
        Route::delete('{towJob}', [\App\Http\Controllers\TowJobController::class, 'destroy'])->name('destroy');
        Route::post('import-towbook-batch', [\App\Http\Controllers\TowBookImportController::class, 'importBatch'])->name('import.towbook');
    });

    // ── Bodyshop Module ───────────────────────────────
    Route::prefix('bodyshop')->name('bodyshop.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BodyshopController::class, 'floor'])->name('floor');
        Route::get('workers', [\App\Http\Controllers\BodyshopController::class, 'workers'])->name('workers');
        Route::post('workers', [\App\Http\Controllers\BodyshopController::class, 'storeWorker'])->name('workers.store');
        Route::put('workers/{worker}', [\App\Http\Controllers\BodyshopController::class, 'updateWorker'])->name('workers.update');
        Route::delete('workers/{worker}', [\App\Http\Controllers\BodyshopController::class, 'destroyWorker'])->name('workers.destroy');
        Route::get('lifts', [\App\Http\Controllers\BodyshopController::class, 'lifts'])->name('lifts');
        Route::post('lifts', [\App\Http\Controllers\BodyshopController::class, 'storeLift'])->name('lifts.store');
        Route::put('lifts/{lift}', [\App\Http\Controllers\BodyshopController::class, 'updateLift'])->name('lifts.update');
        Route::delete('lifts/{lift}', [\App\Http\Controllers\BodyshopController::class, 'destroyLift'])->name('lifts.destroy');
        Route::post('lifts/{lift}/assign', [\App\Http\Controllers\BodyshopController::class, 'assign'])->name('assign');
        Route::post('lifts/{lift}/release', [\App\Http\Controllers\BodyshopController::class, 'release'])->name('release');
        Route::put('slots/{slot}', [\App\Http\Controllers\BodyshopController::class, 'updateSlot'])->name('slots.update');
    });

    // ── Insurance Claims Module ──────────────────────────
    Route::prefix('claims')->name('claims.')->group(function () {
        Route::get('/', [ClaimController::class, 'index'])->name('index');
        Route::get('board', [ClaimController::class, 'board'])->name('board');
        Route::get('create', [ClaimController::class, 'create'])->name('create');
        Route::post('/', [ClaimController::class, 'store'])->name('store');
        Route::get('{claim}', [ClaimController::class, 'show'])->name('show');
        Route::put('{claim}', [ClaimController::class, 'update'])->name('update');
        Route::post('{claim}/status', [ClaimController::class, 'setStatus'])->name('status');
        Route::post('{claim}/step', [ClaimController::class, 'setStep'])->name('step');

        // Claim actions
        Route::post('{claim}/steps/{step}/complete', [ClaimController::class, 'completeStep'])->name('complete-step');
        Route::post('{claim}/steps/{step}/uncomplete', [ClaimController::class, 'uncompleteStep'])->name('uncomplete-step');
        Route::post('{claim}/insurance', [ClaimController::class, 'addInsurance'])->name('add-insurance');
        Route::post('{claim}/supplement', [ClaimController::class, 'addSupplement'])->name('add-supplement');
        Route::post('{claim}/comment', [ClaimController::class, 'addComment'])->name('comment');
        Route::post('{claim}/payment', [ClaimController::class, 'recordPayment'])->name('payment');
    });

    // ── Accounting ─────────────────────────────────────
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->name('chart-of-accounts');
        Route::post('chart-of-accounts', [AccountingController::class, 'storeAccount'])->name('chart-of-accounts.store');
        Route::put('chart-of-accounts/{account}', [AccountingController::class, 'updateAccount'])->name('chart-of-accounts.update');
        Route::delete('chart-of-accounts/{account}', [AccountingController::class, 'deleteAccount'])->name('chart-of-accounts.delete');

        Route::get('journal-entries', [AccountingController::class, 'journalEntries'])->name('journal-entries');
        Route::post('journal-entries', [AccountingController::class, 'storeJournalEntry'])->name('journal-entries.store');
        Route::get('journal-entries/{id}', [AccountingController::class, 'showJournalEntry'])->name('journal-entries.show');

        Route::get('trial-balance', [AccountingController::class, 'trialBalance'])->name('trial-balance');
        Route::get('profit-loss', [AccountingController::class, 'profitLoss'])->name('profit-loss');
        Route::get('balance-sheet', [AccountingController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('account-ledger/{accountId}', [AccountingController::class, 'accountLedger'])->name('account-ledger');
    });

    // ── Office Tasks Module ───────────────────────────
    Route::prefix('office-tasks')->name('office-tasks.')->group(function () {
        Route::get('/', [OfficeTaskController::class, 'index'])->name('index');
        Route::post('/', [OfficeTaskController::class, 'store'])->name('store');
        Route::post('{officeTask}/complete', [OfficeTaskController::class, 'complete'])->name('complete');
        Route::post('{officeTask}/uncomplete', [OfficeTaskController::class, 'uncomplete'])->name('uncomplete');
        Route::post('{officeTask}/move', [OfficeTaskController::class, 'moveSection'])->name('move');
        Route::post('{officeTask}/comment', [OfficeTaskController::class, 'addComment'])->name('comment');
        Route::delete('{officeTask}', [OfficeTaskController::class, 'destroy'])->name('destroy');
    });

    // ── Permission Types ──────────────────────────────
    Route::prefix('permission-types')->name('permission-types.')->group(function () {
        Route::get('/', [PermissionTypeController::class, 'index'])->name('index');
        Route::post('/', [PermissionTypeController::class, 'store'])->name('store');
        Route::get('{permissionType}/edit', [PermissionTypeController::class, 'edit'])->name('edit');
        Route::put('{permissionType}', [PermissionTypeController::class, 'update'])->name('update');
        Route::delete('{permissionType}', [PermissionTypeController::class, 'destroy'])->name('destroy');
    });

    // ── User Management ───────────────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('{user}', [UserManagementController::class, 'show'])->name('show');
        Route::put('{user}', [UserManagementController::class, 'update'])->name('update');
        Route::post('{user}/role', [UserManagementController::class, 'updateRole'])->name('update-role');
        Route::post('{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('reset-password');
        Route::get('roles/manage', [UserManagementController::class, 'roles'])->name('roles');
        Route::post('roles/{role}/permissions', [UserManagementController::class, 'updateRolePermissions'])->name('update-role-permissions');
    });

    // ── Parts Orders ──────────────────────────────────
    Route::prefix('parts')->name('parts.')->group(function () {
        Route::get('/', [PartsOrderController::class, 'index'])->name('index');
        Route::post('/', [PartsOrderController::class, 'store'])->name('store');
        Route::post('{partsOrder}/status', [PartsOrderController::class, 'updateStatus'])->name('status');
        Route::post('{partsOrder}/comment', [PartsOrderController::class, 'addComment'])->name('comment');
    });

    // ── Business Documents ────────────────────────────
    Route::prefix('business-documents')->name('business-documents.')->group(function () {
        Route::get('/', [BusinessDocumentController::class, 'index'])->name('index');
        Route::post('/', [BusinessDocumentController::class, 'store'])->name('store');
        Route::put('{businessDocument}', [BusinessDocumentController::class, 'update'])->name('update');
    });

    // ── EZ Pass ───────────────────────────────────────
    Route::prefix('ezpass')->name('ezpass.')->group(function () {
        Route::get('/', [EzPassController::class, 'index'])->name('index');
        Route::post('/', [EzPassController::class, 'store'])->name('store');
        Route::put('{ezPassAccount}', [EzPassController::class, 'update'])->name('update');
        Route::get('import',  [\App\Http\Controllers\EzPassImportController::class, 'show'])->name('import.show');
        Route::post('import', [\App\Http\Controllers\EzPassImportController::class, 'import'])->name('import');
        Route::post('bill/{reservation}', [\App\Http\Controllers\EzPassImportController::class, 'billReservation'])->name('bill');
    });

    // ── Credit Pulls ──────────────────────────────────
    Route::prefix('credit')->name('credit.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CreditPullController::class, 'index'])->name('index');
        Route::get('create', [\App\Http\Controllers\CreditPullController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\CreditPullController::class, 'store'])->name('store');
        Route::get('{creditPull}', [\App\Http\Controllers\CreditPullController::class, 'show'])->name('show');
        Route::get('customer/{customer}/history', [\App\Http\Controllers\CreditPullController::class, 'forCustomer'])->name('customer-history');
    });

    // ── Lender Programs ───────────────────────────────
    Route::prefix('lender-programs')->name('lender-programs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LenderProgramController::class, 'index'])->name('index');
        Route::get('create', [\App\Http\Controllers\LenderProgramController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\LenderProgramController::class, 'store'])->name('store');
        Route::put('{lenderProgram}', [\App\Http\Controllers\LenderProgramController::class, 'update'])->name('update');
    });

    // ── Quote Calculator ──────────────────────────────
    Route::post('quote-calculator/calculate', [\App\Http\Controllers\QuoteCalculatorController::class, 'calculate'])->name('quote-calculator.calculate');
    Route::post('quote-calculator/find-program', [\App\Http\Controllers\QuoteCalculatorController::class, 'findProgram'])->name('quote-calculator.find-program');
    Route::post('quote-calculator/rebates', [\App\Http\Controllers\QuoteCalculatorController::class, 'rebates'])->name('quote-calculator.rebates');

    // ── Settings ───────────────────────────────────────
    Route::get('settings', [SettingController::class, 'index'])->name('settings');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/test/{integration}', [SettingController::class, 'test'])->name('settings.test');

    // S3 — locked-down with test-before-save + history (no delete endpoint, by design)
    Route::get('settings/s3', [\App\Http\Controllers\S3SettingsController::class, 'index'])->name('settings.s3');
    Route::post('settings/s3', [\App\Http\Controllers\S3SettingsController::class, 'saveNew'])->name('settings.s3.save');
    Route::post('settings/s3/{history}/restore', [\App\Http\Controllers\S3SettingsController::class, 'restore'])->name('settings.s3.restore');
});

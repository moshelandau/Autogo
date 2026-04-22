<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCard;
use App\Services\SolaPaymentsService;
use Illuminate\Http\Request;

/**
 * Save / list / delete tokenized cards on file. Per CLAUDE.md PCI rules:
 *   - The PAN is accepted in the POST body (HTTPS only)
 *   - Immediately tokenized via Cardknox cc:save
 *   - Only the resulting xToken + brand + last4 + exp are persisted
 *   - The PAN is NEVER written to the DB or logs (AuditLogMiddleware
 *     already redacts card_number / cvv / cvc)
 *
 * Future: replace the raw POST with Cardknox iFields (PCI-safe iframe entry)
 * so the PAN never touches our server at all. Tracked in docs/CARDKNOX.md.
 */
class CustomerCardController extends Controller
{
    public function __construct(private readonly SolaPaymentsService $sola) {}

    /** List a customer's tokenized cards (no PAN). */
    public function index(Customer $customer)
    {
        return response()->json([
            'data' => $customer->cards()->orderByDesc('is_default')->orderByDesc('id')->get()
                ->map(fn ($c) => [
                    'id'         => $c->id,
                    'account'    => $c->account,
                    'brand'      => $c->brand,
                    'last4'      => $c->last4,
                    'exp'        => $c->exp,
                    'cardholder' => $c->cardholder,
                    'label'      => $c->label,
                    'is_default' => $c->is_default,
                    'display'    => $c->display,
                ]),
        ]);
    }

    /** Tokenize + save a new card. */
    public function store(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'account'    => 'required|in:autogo,high_rental',
            'number'     => 'required|string|min:13|max:19',
            'exp'        => 'required|string|size:5',         // MM/YY
            'cvv'        => 'required|string|min:3|max:4',
            'zip'        => 'nullable|string|max:10',
            'cardholder' => 'nullable|string|max:100',
            'label'      => 'nullable|string|max:50',
            'set_default'=> 'nullable|boolean',
        ]);

        // Tokenize via Cardknox; PAN dies here.
        $result = $this->sola->saveCard($data['account'], [
            'number' => preg_replace('/\D/', '', $data['number']),
            'exp'    => $data['exp'],
            'cvv'    => $data['cvv'],
            'zip'    => $data['zip'] ?? null,
            'name'   => $data['cardholder'] ?? null,
        ]);

        if (!($result['success'] ?? false)) {
            return back()->with('error', 'Card save failed: ' . ($result['error'] ?? 'unknown'));
        }

        if ($request->boolean('set_default')) {
            $customer->cards()->update(['is_default' => false]);
        }

        $card = CustomerCard::create([
            'customer_id' => $customer->id,
            'account'     => $data['account'],
            'x_token'     => $result['token'],
            'brand'       => $result['brand'] ?? null,
            'last4'       => $result['last4'] ?? null,
            'exp'         => $data['exp'],
            'cardholder'  => $data['cardholder'] ?? null,
            'label'       => $data['label'] ?? null,
            'is_default'  => $request->boolean('set_default'),
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', "Card saved: {$card->display}");
    }

    public function destroy(Customer $customer, CustomerCard $card)
    {
        abort_unless($card->customer_id === $customer->id, 403);
        $card->delete();
        return back()->with('success', 'Card removed.');
    }

    public function setDefault(Customer $customer, CustomerCard $card)
    {
        abort_unless($card->customer_id === $customer->id, 403);
        $customer->cards()->update(['is_default' => false]);
        $card->update(['is_default' => true]);
        return back()->with('success', 'Default card updated.');
    }
}

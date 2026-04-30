<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InsuranceBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsuranceBrokerController extends Controller
{
    public function index(Request $request)
    {
        $term = $request->string('q')->toString();
        $query = InsuranceBroker::query()->withCount('deals')->orderBy('name');
        if ($term !== '') {
            $like = '%' . trim($term) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                    ->orWhere('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like);
            });
        }

        return Inertia::render('Brokers/Index', [
            'brokers' => $query->paginate(50)->withQueryString(),
            'filters' => ['q' => $term],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        InsuranceBroker::create($data + ['is_active' => true]);
        return back()->with('success', 'Broker added.');
    }

    public function update(Request $request, InsuranceBroker $broker)
    {
        $data = $this->validated($request, withActive: true);
        $broker->update($data);
        return back()->with('success', 'Broker updated.');
    }

    public function destroy(InsuranceBroker $broker)
    {
        $broker->update(['is_active' => false]);
        return back()->with('success', 'Broker deactivated.');
    }

    /** Typeahead JSON for Deal Information dropdown. */
    public function typeahead(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();
        $rows = InsuranceBroker::search($term)
            ->limit(15)
            ->get(['id', 'name', 'first_name', 'last_name', 'phone', 'email']);
        return response()->json($rows);
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'name'         => 'required|string|max:255',
            'first_name'   => 'nullable|string|max:255',
            'last_name'    => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:32',
            'email'        => 'nullable|email|max:255',
            'website'      => 'nullable|string|max:255',
            'claims_phone' => 'nullable|string|max:32',
            'claims_email' => 'nullable|email|max:255',
            'address'      => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
        ];
        if ($withActive) {
            $rules['is_active'] = 'boolean';
        }
        return $request->validate($rules);
    }
}

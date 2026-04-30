<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dealer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DealerController extends Controller
{
    public function index(Request $request)
    {
        $term = $request->string('q')->toString();
        $query = Dealer::query()->withCount('deals')->orderBy('name');
        if ($term !== '') {
            $like = '%' . trim($term) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                    ->orWhere('contact_name', 'ilike', $like)
                    ->orWhere('city', 'ilike', $like)
                    ->orWhere('state', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('makes_carried', 'ilike', $like);
            });
        }

        return Inertia::render('Dealers/Index', [
            'dealers' => $query->paginate(50)->withQueryString(),
            'filters' => ['q' => $term],
        ]);
    }

    public function store(Request $request)
    {
        Dealer::create($this->validated($request) + ['is_active' => true]);
        return back()->with('success', 'Dealership added.');
    }

    public function update(Request $request, Dealer $dealer)
    {
        $dealer->update($this->validated($request, withActive: true));
        return back()->with('success', 'Dealership updated.');
    }

    public function destroy(Dealer $dealer)
    {
        $dealer->update(['is_active' => false]);
        return back()->with('success', 'Dealership deactivated.');
    }

    public function typeahead(Request $request): JsonResponse
    {
        $rows = Dealer::search($request->string('q')->toString())
            ->limit(15)
            ->get(['id', 'name', 'contact_name', 'phone', 'email', 'city', 'state', 'makes_carried']);
        return response()->json($rows);
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'name'           => 'required|string|max:255',
            'contact_name'   => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:32',
            'email'          => 'nullable|email|max:255',
            'website'        => 'nullable|string|max:255',
            'address'        => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:2',
            'zip'            => 'nullable|string|max:10',
            'makes_carried'  => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
        ];
        if ($withActive) $rules['is_active'] = 'boolean';
        return $request->validate($rules);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lienholder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LienholderController extends Controller
{
    public function index(Request $request)
    {
        $term = $request->string('q')->toString();
        $query = Lienholder::query()->withCount('deals')->orderBy('name');
        if ($term !== '') {
            $like = '%' . trim($term) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                    ->orWhere('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like)
                    ->orWhere('elt_number', 'ilike', $like);
            });
        }

        return Inertia::render('Lienholders/Index', [
            'lienholders' => $query->paginate(50)->withQueryString(),
            'filters' => ['q' => $term],
        ]);
    }

    public function store(Request $request)
    {
        Lienholder::create($this->validated($request) + ['is_active' => true]);
        return back()->with('success', 'Lienholder added.');
    }

    public function update(Request $request, Lienholder $lienholder)
    {
        $lienholder->update($this->validated($request, withActive: true));
        return back()->with('success', 'Lienholder updated.');
    }

    public function destroy(Lienholder $lienholder)
    {
        $lienholder->update(['is_active' => false]);
        return back()->with('success', 'Lienholder deactivated.');
    }

    public function typeahead(Request $request): JsonResponse
    {
        $rows = Lienholder::search($request->string('q')->toString())
            ->limit(15)
            ->get(['id', 'name', 'first_name', 'last_name', 'phone', 'email', 'elt_number']);
        return response()->json($rows);
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'name'        => 'required|string|max:255',
            'first_name'  => 'nullable|string|max:255',
            'last_name'   => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:32',
            'email'       => 'nullable|email|max:255',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'state'       => 'nullable|string|max:2',
            'zip'         => 'nullable|string|max:10',
            'elt_number'  => 'nullable|string|max:50',
            'notes'       => 'nullable|string',
        ];
        if ($withActive) $rules['is_active'] = 'boolean';
        return $request->validate($rules);
    }
}

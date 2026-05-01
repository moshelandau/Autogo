<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\DealerMarkdown;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DealerMarkdownController extends Controller
{
    public function index(Request $request)
    {
        $term = $request->string('q')->toString();
        $query = DealerMarkdown::query()
            ->with('dealer:id,name', 'createdBy:id,name')
            ->orderByDesc('id');

        if ($term !== '') {
            $like = '%' . trim($term) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'ilike', $like)
                    ->orWhere('dealer_name', 'ilike', $like)
                    ->orWhere('make', 'ilike', $like)
                    ->orWhere('model', 'ilike', $like)
                    ->orWhere('notes', 'ilike', $like)
                    ->orWhereHas('dealer', fn ($d) => $d->where('name', 'ilike', $like));
            });
        }

        return Inertia::render('DealerMarkdowns/Index', [
            'markdowns' => $query->paginate(50)->withQueryString(),
            'filters'   => ['q' => $term],
            'dealers'   => Dealer::active()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        DealerMarkdown::create($data + [
            'is_active'  => true,
            'created_by' => auth()->id(),
        ]);
        return back()->with('success', 'Dealer markdown added.');
    }

    public function update(Request $request, DealerMarkdown $markdown)
    {
        $markdown->update($this->validated($request, withActive: true));
        return back()->with('success', 'Dealer markdown updated.');
    }

    public function destroy(DealerMarkdown $markdown)
    {
        $markdown->update(['is_active' => false]);
        return back()->with('success', 'Markdown deactivated.');
    }

    private function validated(Request $request, bool $withActive = false): array
    {
        $rules = [
            'dealer_id'     => 'nullable|exists:dealers,id',
            'dealer_name'   => 'nullable|string|max:255',
            'amount'        => 'required|numeric|min:0',
            'title'         => 'required|string|max:255',
            'make'          => 'nullable|string|max:60',
            'model'         => 'nullable|string|max:60',
            'year_from'     => 'nullable|integer|min:1990|max:2099',
            'year_to'       => 'nullable|integer|min:1990|max:2099',
            'valid_from'    => 'nullable|date',
            'valid_through' => 'nullable|date|after_or_equal:valid_from',
            'notes'         => 'nullable|string',
        ];
        if ($withActive) $rules['is_active'] = 'boolean';
        return $request->validate($rules);
    }
}

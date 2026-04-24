<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SmsTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/SmsTemplates', [
            'templates' => SmsTemplate::orderByDesc('is_active')->orderBy('category')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'    => 'required|string|max:80',
            'body'     => 'required|string|max:1600',
            'category' => 'nullable|string|max:40',
        ]);
        SmsTemplate::create([...$data, 'created_by' => auth()->id(), 'is_active' => true]);
        return back()->with('success', 'Template added.');
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $data = $request->validate([
            'label'     => 'sometimes|string|max:80',
            'body'      => 'sometimes|string|max:1600',
            'category'  => 'sometimes|nullable|string|max:40',
            'is_active' => 'sometimes|boolean',
        ]);
        $template->update($data);
        return back()->with('success', 'Template updated.');
    }

    public function destroy(SmsTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'Template removed.');
    }
}

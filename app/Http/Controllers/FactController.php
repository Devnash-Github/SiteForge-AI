<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessFact;
use App\Services\Facts\FactKeys;
use App\Services\Facts\FactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FactController extends Controller
{
    public function __construct(private FactService $facts) {}

    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);
        $data = $request->validate([
            'key' => ['required', Rule::in(FactKeys::all())],
            'value' => ['required', 'string', 'max:1500'],
        ]);
        $this->facts->addManual($business, $data['key'], $data['value']);

        return back()->with('success', FactKeys::label($data['key']).' added.');
    }

    public function update(Request $request, Business $business, BusinessFact $fact): RedirectResponse
    {
        $this->authorize('update', $business);
        $data = $request->validate([
            'value' => ['nullable', 'string', 'max:1500'],
            'status' => ['nullable', Rule::in([BusinessFact::STATUS_CONFIRMED, BusinessFact::STATUS_REJECTED, BusinessFact::STATUS_EXTRACTED])],
        ]);

        if (isset($data['value'])) {
            $this->facts->updateValue($fact, $data['value']);
        } elseif (isset($data['status'])) {
            $this->facts->setStatus($fact, $data['status']);
        }

        return back();
    }

    public function destroy(Request $request, Business $business, BusinessFact $fact): RedirectResponse
    {
        $this->authorize('update', $business);
        $fact->delete();

        return back()->with('success', 'Removed.');
    }

    public function confirmAll(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);
        $count = $this->facts->confirmAllUnconflicted($business);

        return back()->with('success', $count === 0 ? 'Nothing left to confirm.' : "{$count} facts confirmed.");
    }

    public function resolve(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);
        $data = $request->validate([
            'key' => ['required', Rule::in(FactKeys::SINGLE)],
            'fact_id' => ['nullable', 'integer'],
            'value' => ['nullable', 'string', 'max:1500'],
            'keep_all' => ['nullable', 'boolean'],
        ]);
        $this->facts->resolveConflict($business, $data['key'], $data['fact_id'] ?? null, $data['value'] ?? null, (bool) ($data['keep_all'] ?? false));

        return back()->with('success', FactKeys::label($data['key']).' resolved.');
    }
}

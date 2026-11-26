<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiClient;
use App\Services\Business\BusinessService;
use App\Services\Research\ResearchService;
use App\Services\Websites\AiCopyWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request, AiClient $gemini, ResearchService $research): Response
    {
        return Inertia::render('Settings', [
            'preferences' => [
                'default_tone' => $request->user()->preference('default_tone', 'professional'),
            ],
            'tones' => array_keys(AiCopyWriter::TONES),
            'integrations' => [
                'ai' => ['configured' => $gemini->isConfigured(), 'provider' => $gemini->provider(), 'model' => $gemini->model()],
                'search' => ['configured' => $research->isAvailable(), 'provider' => $research->providerName()],
                'queue' => config('queue.default'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['default_tone' => ['required', Rule::in(array_keys(AiCopyWriter::TONES))]]);
        $user = $request->user();
        $user->update(['preferences' => array_merge($user->preferences ?? [], $data)]);

        return back()->with('success', 'Settings saved.');
    }

    public function destroyAccount(Request $request, BusinessService $businesses): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        foreach ($user->businesses as $business) {
            $businesses->delete($business);
        }
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account was deleted.');
    }
}

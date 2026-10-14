<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AiCredit;
use App\Support\AiBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'ai' => AiBudget::current(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    // Guthaben aus der Anthropic-Console übernehmen; ab jetzt wird davon abgezogen.
    public function updateAiCredit(Request $request): RedirectResponse
    {
        $request->merge(['amount' => str_replace(',', '.', trim((string) $request->input('amount')))]);
        $data = $request->validateWithBag('aiCredit', ['amount' => ['required', 'numeric', 'min:0', 'max:100000']], [], ['amount' => 'Guthaben']);

        AiCredit::create(['user_id' => $request->user()->id, 'amount_micros' => (int) round($data['amount'] * 1_000_000)]);

        return Redirect::route('profile.edit')->with('status', 'ai-credit-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

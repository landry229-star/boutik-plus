<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ConfirmAccountEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        $emailChanged = $data['email'] !== $user->email;
        $user->update(['name' => $data['name']]);

        if ($emailChanged) {
            $nonce = Str::random(64);
            $cacheKey = 'account-email-change:'.$user->id;
            Cache::put($cacheKey, [
                'email' => $data['email'],
                'nonce' => $nonce,
            ], now()->addMinutes(30));

            $confirmationUrl = URL::temporarySignedRoute(
                'compte.email.confirm',
                now()->addMinutes(30),
                ['user' => $user->id, 'nonce' => $nonce],
            );

            Notification::route('mail', $data['email'])
                ->notify(new ConfirmAccountEmail($confirmationUrl));

            return back()->with('success', 'Votre nom a été modifié. Un lien de confirmation a été envoyé à la nouvelle adresse e-mail. Il est valable 30 minutes.');
        }

        return back()->with('success', 'Les informations de votre compte ont été mises à jour.');
    }

    public function confirmEmail(Request $request, int $user): RedirectResponse
    {
        $account = User::query()->findOrFail($user);
        $cacheKey = 'account-email-change:'.$account->id;
        $pendingChange = Cache::get($cacheKey);

        abort_unless(
            is_array($pendingChange)
                && isset($pendingChange['email'], $pendingChange['nonce'])
                && is_string($pendingChange['email'])
                && is_string($pendingChange['nonce'])
                && hash_equals($pendingChange['nonce'], (string) $request->query('nonce')),
            Response::HTTP_FORBIDDEN,
        );

        $data = Validator::make(
            ['email' => $pendingChange['email']],
            ['email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)]],
        )->validate();

        DB::table('password_reset_tokens')->where('email', $account->email)->delete();
        $account->forceFill([
            'email' => $data['email'],
            'email_verified_at' => now(),
        ])->save();

        Cache::forget($cacheKey);

        return redirect()->route('login')->with('success', 'Votre nouvelle adresse e-mail est confirmée. Vous pouvez maintenant vous connecter avec cette adresse.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        Auth::logoutOtherDevices($data['current_password']);
        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Votre mot de passe a été changé. Les autres sessions de votre compte ont été déconnectées.');
    }
}

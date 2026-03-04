<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        session()->forget('google_linking');

        return Socialite::driver('google')->redirect();
    }

    public function redirectForLink(): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect('/users')->with('oauth_error', 'You must be logged in to link a Google account.');
        }

        session(['google_linking' => true]);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect('/users')->with('oauth_error', 'Google authentication failed. Please try again.');
        }

        $googleId = (string) $googleUser->getId();
        $googleEmail = $googleUser->getEmail();
        $googleName = $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User';
        $googleAvatar = $googleUser->getAvatar();

        $isLinkingFlow = (bool) $request->session()->pull('google_linking', false);

        if ($isLinkingFlow && Auth::check()) {
            $authenticatedUser = Auth::user();

            $alreadyLinkedToAnother = User::query()
                ->where('google_id', $googleId)
                ->where('id', '!=', $authenticatedUser->id)
                ->exists();

            if ($alreadyLinkedToAnother) {
                return redirect('/users')->with('oauth_error', 'This Google account is already linked to another user.');
            }

            $authenticatedUser->google_id = $googleId;
            $authenticatedUser->google_avatar = $googleAvatar;
            $authenticatedUser->save();

            return redirect('/users')->with('oauth_status', 'Google account linked successfully.');
        }

        $userByGoogle = User::query()->where('google_id', $googleId)->first();

        if ($userByGoogle) {
            Auth::login($userByGoogle, true);

            return redirect('/users')->with('oauth_status', 'Signed in with Google.');
        }

        if ($googleEmail) {
            $userByEmail = User::query()->where('email', $googleEmail)->first();

            if ($userByEmail) {
                $userByEmail->google_id = $googleId;
                $userByEmail->google_avatar = $googleAvatar;
                $userByEmail->save();

                Auth::login($userByEmail, true);

                return redirect('/users')->with('oauth_status', 'Google account linked to your existing account.');
            }
        }

        $newUser = User::create([
            'name' => $googleName,
            'email' => $googleEmail ?: $this->generateFallbackEmail($googleId),
            'password' => Hash::make(Str::random(40)),
            'google_id' => $googleId,
            'google_avatar' => $googleAvatar,
        ]);

        Auth::login($newUser, true);

        return redirect('/users')->with('oauth_status', 'New account created with Google.');
    }

    private function generateFallbackEmail(string $googleId): string
    {
        return "google-user-{$googleId}@local.example";
    }
}
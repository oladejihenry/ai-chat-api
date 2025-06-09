<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')
                ->user();

            //Check if user exists
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'email_verified_at' => now(),
                    'password' => bcrypt(Str::random(24)),
                    'google_id' => $googleUser->id,
                    'google_avatar' => $googleUser->avatar,
                ]);

                event(new Registered($user));
            } else {
                $user->update([
                    'google_id' => $googleUser->id,
                    'google_avatar' => $googleUser->avatar,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }

            // Login user
            Auth::login($user);

            return redirect()->intended(config('app.frontend_url') . '/');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to login with Google');
        }
    }
}

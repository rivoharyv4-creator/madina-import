<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerAuthController extends Controller
{
    public function form(Request $request)
    {
        $request->session()->put('url.intended', route('customer.checkout'));

        return Inertia::render('Customer/Auth', ['register' => $request->routeIs('customer.register'), 'publicConfig' => [...config('madina.public'), 'address' => config('madina.company.address')]]);
    }

    public function register(Request $request, EmailCodeService $codes)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => 'required|string|max:255', 'phone' => 'required|string|max:30', 'email' => 'required|email|max:255', 'password' => ['required', 'confirmed', Password::defaults()]]);
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'Inscription indisponible. Connectez-vous ou contactez Madina Import.']);
        }
        $user = User::create([...$data, 'role' => 'customer', 'permissions' => [], 'active' => true, 'email_verified_at' => null]);
        Auth::login($user);
        $request->session()->regenerate();
        $sent = $codes->send($user);

        return redirect()->route('verification.notice')->with('success', $sent ? 'Code envoyé.' : 'Envoi indisponible. Votre compte est créé ; vous pourrez renvoyer le code.');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'role' => 'customer', 'active' => true])) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects ou accès indisponible.']);
        }
        $request->session()->regenerate();

        return redirect()->route($request->user()->hasVerifiedEmail() ? 'customer.checkout' : 'verification.notice');
    }

    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('customer.checkout');
        }
        $email = $request->user()->email;
        [$name,$domain] = explode('@', $email);
        $row = DB::table('email_verification_codes')->where('user_id', $request->user()->id)->first();

        return Inertia::render('Customer/Verify', ['maskedEmail' => mb_substr($name, 0, 1).'***@'.mb_substr($domain, 0, 1).'***', 'retryAfter' => $row ? max(0, 60 - (int) now()->diffInSeconds($row->last_sent_at, true)) : 0, 'publicConfig' => [...config('madina.public'), 'address' => config('madina.company.address')]]);
    }

    public function verify(Request $request, EmailCodeService $codes)
    {
        $request->validate(['code' => 'required|string|max:20']);
        $codes->verify($request->user(), $request->input('code'));

        return redirect()->route('customer.checkout');
    }

    public function resend(Request $request, EmailCodeService $codes)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('customer.checkout');
        }

        return back()->with('success', $codes->send($request->user()) ? 'Un nouveau code a été envoyé.' : 'Envoi indisponible. Réessayez dans une minute.');
    }
}

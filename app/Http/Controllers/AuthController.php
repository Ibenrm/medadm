<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Tampilkan form login
    public function showLoginForm()
    {
        return view('login');
    }

    // Proses login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:3'
        ]);

        $email = $request->email;
        $password = $request->password;

        // Dummy auth: ganti sesuai dengan database
        $allowedUsers = [
            'ea.iben@medtools.id' => 'ea123',
            'it.iben@medtools.id' => 'it123',
        ];

        if (isset($allowedUsers[$email]) && $allowedUsers[$email] === $password) {
            // Set session user
            session(['email' => $email]);

            // Redirect dashboard sesuai email
            if (str_contains($email, 'ea.')) {
                return response()->json(['success' => true, 'redirect' => route('dashboard.ea')]);
            } elseif (str_contains($email, 'it.')) {
                return response()->json(['success' => true, 'redirect' => route('dashboard.it')]);
            }
        }

        return response()->json(['success' => false, 'message' => 'Email atau password salah']);
    }

    // Dashboard EA
    public function dashboardEA()
    {
        $email = session('email');
        if (!$email || !str_contains($email, 'ea.')) {
            abort(403);
        }
        return view('dashboards.ea.ea'); // <-- folder ea/ea.blade.php
    }

    // Dashboard IT
    public function dashboardIT()
    {
        $email = session('email');
        if (!$email || !str_contains($email, 'it.')) {
            abort(403);
        }
        return view('dashboards.it.it'); // <-- folder it/it.blade.php
    }
}

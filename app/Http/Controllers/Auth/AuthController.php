<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Polda;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the Multi-Tenant Login Form.
     */
    public function showLogin(): Response
    {
        $poldas = Polda::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Auth/Login', [
            'poldas' => $poldas,
        ]);
    }

    /**
     * Handle Login Submission.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'pin'      => 'nullable|string',
        ]);

        return redirect()->route('dashboard');
    }
}


<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);
        $user = \App\Models\User::where('email', $data['email'])->where('active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }
        if ($user->tenant_id && ! $user->account()->where('active', true)->exists()) {
            throw ValidationException::withMessages(['email' => 'A empresa vinculada a este usuário está inativa.']);
        }

        return [
            'token' => $user->createToken($data['device_name'] ?? 'web')->plainTextToken,
            'user' => $user->load('roles', 'account', 'clients'),
        ];
    }

    public function me(Request $request)
    {
        return $request->user()->load('roles.permissions', 'account', 'clients');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->noContent();
    }
}

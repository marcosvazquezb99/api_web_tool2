<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    /**
     * Generar un token de acceso personal para el usuario autenticado
     */
    public function createToken(Request $request)
    {
        // Validar que el usuario esté autenticado
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Crear un token con un nombre
        $tokenName = $request->input('token_name', 'default_token_name');
        $token = $user->createToken($tokenName);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Revocar todos los tokens del usuario
     */
    public function revokeAllTokens(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Revocar todos los tokens del usuario
        $user->tokens()->delete();

        return response()->json(['message' => 'Todos los tokens han sido revocados exitosamente']);
    }

    /**
     * Revocar el token actual
     */
    public function revokeCurrentToken(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Revocar el token actual (el token que está haciendo la solicitud)
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token actual revocado exitosamente']);
    }

    /**
     * Revocar un token específico
     */
    public function revokeToken(Request $request, $tokenId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Revocar el token específico por su ID
        $user->tokens()->where('id', $tokenId)->delete();

        return response()->json(['message' => 'Token revocado exitosamente']);
    }
}

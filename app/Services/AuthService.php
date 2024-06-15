<?php

namespace App\Services;

use App\Http\Requests\AuthRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function login(AuthRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->validated())) {
            return response()->json(['message' => "Данные для входа неверны"], 422);
        }
        $token = auth()->user()->createToken('user');
        return response()->json(['token' => $token->plainTextToken]);
    }

}

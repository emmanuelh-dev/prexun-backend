<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{

    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    /**
     * Handle user login.
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {   
        $user = User::where('email', $request->email)
            ->with([
                'campuses',
                'campuses.latestCashRegister',
                'userCampuses',
                'userCampuses.campus',
                'userCampuses.latestCashRegister'
            ])
            ->first();
    
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => __('Contraseña incorrecta.'),
            ], 401);
        }
        
        if ($user->suspendido) {
            return response()->json([
                'message' => __('Usuario suspendido.'),
            ], 401);
        }
        $token = $user->createToken('API Token')->plainTextToken;
    
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Handle user logout.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

     public function user(Request $request) {
        $user = auth()->user()->load([
            'campuses',
            'campuses.latestCashRegister'
        ]);
        return response()->json($user);
    }

    public function logout(Request $request)
    {
        $token = auth()->user()->currentAccessToken();
        $token->delete();

        return response()->json([
            'message' => __('Session closed successfully.'),
        ]);
    }
}

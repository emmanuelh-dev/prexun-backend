<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCampus;
use Illuminate\Validation\Rules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function index()
    {
        $users = User::with(['campuses', 'userCampuses'])->get();
        return response()->json($users);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required'],
            'campuses' => ['array'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->has('campuses')) {
            $user->campuses()->sync($request->campuses);
        }

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'user' => $user->load(['campuses', 'userCampuses']),
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::findOrFail($id);
            
            $validatedData = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$id],
                'role' => ['required', 'string'],
                'password' => ['nullable', 'string', 'min:8'],
                'grupos' => ['array', 'nullable'],
                'grupos.*' => ['exists:grupos,id'],
                'campuses' => ['array', 'nullable']
            ]);

            $user->name = $validatedData['name'];
            $user->email = $validatedData['email'];
            $user->role = $validatedData['role'];
            
            if (!empty($validatedData['password'])) {
                $user->password = Hash::make($validatedData['password']);
            }

            $user->save();

            // Manejar la asignación de campus
            if (isset($validatedData['campuses'])) {
                $user->campuses()->sync($validatedData['campuses']);
            }

            // Manejar la asignación de grupos para maestros
            if (($user->role === 'teacher' || $user->role === 'maestro') && isset($validatedData['grupos'])) {
                $user->grupos()->sync($validatedData['grupos']);
            }

            DB::commit();
            
            // Cargar las relaciones necesarias
            $user->load(['grupos', 'campuses', 'userCampuses']);
            
            return response()->json([
                'message' => 'Usuario actualizado correctamente',
                'user' => $user
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user:', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente'
        ]);
    }
}

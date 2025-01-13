<?php

namespace App\Http\Controllers\Api;

use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Denomination;
use App\Models\Gasto;
use App\Models\GastoDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        if ($request->campus_id) {
            $gastos = Gasto::where('campus_id', $request->campus_id)->with('admin')->with('user')->get();
        } else {
            $gastos = Gasto::with('admin')->with('user')->get();
        }

        // Add full URL to image paths
        $gastos->transform(function ($gasto) {
            if ($gasto->image) {
                $gasto->image = asset('storage/' . $gasto->image);
            }
            return $gasto;
        });

        return response()->json($gastos);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->all();

            if (isset($data['image']) && !$request->hasFile('image')) {
                $data['image'] = null;
            }

            $validated = validator($data, [
                'concept' => 'required|string',
                'amount' => 'required|numeric',
                'date' => 'required|date',
                'method' => 'required|string',
                'user_id' => 'required|exists:users,id',
                'admin_id' => 'required|exists:users,id',
                'category' => 'required|string',
                'campus_id' => 'required|exists:campuses,id',
                'image' => 'nullable|image',
                'cash_register_id' => 'nullable|exists:cash_registers,id',
                'denominations' => 'nullable',
            ])->validate();

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('gastos', 'public');
            } else {
                $data['image'] = null;
            }

            $gasto = Gasto::create(array_merge($validated, ['image' => $validated['image'] ?? null]));

            if ($validated['method'] === 'Efectivo' && !empty($validated['denominations'])) {
                $denominationsData = is_string($validated['denominations'])
                    ? json_decode($validated['denominations'], true)
                    : (array)$validated['denominations'];

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Error decoding denominations: ' . json_last_error_msg());
                }

                foreach ($denominationsData as $value => $quantity) {
                    if ($quantity > 0) {
                        $denomination = Denomination::firstOrCreate(
                            ['value' => $value],
                            ['type' => $value >= 100 ? 'billete' : 'moneda']
                        );

                        GastoDetail::create([
                            'gasto_id' => $gasto->id,
                            'denomination_id' => $denomination->id,
                            'quantity' => $quantity
                        ]);
                    }
                }
            }

            if ($gasto->image) {
                $gasto->image = asset('storage/' . $gasto->image);
            }

            return response()->json($gasto->load('gastoDetails.denomination'), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing expense',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        $gasto = Gasto::find($id);
        if ($gasto && $gasto->image) {
            $gasto->image = asset('storage/' . $gasto->image);
        }
        return response()->json($gasto);
    }

    public function update(Request $request, $id)
    {
        $gasto = Gasto::find($id);
        $data = $request->validate([
            'concept' => 'sometimes|string',
            'amount' => 'sometimes|numeric',
            'date' => 'sometimes|date',
            'method' => 'sometimes|string',
            'user_id' => 'sometimes|exists:users,id',
            'admin_id' => 'sometimes|exists:users,id',
            'category' => 'sometimes|string',
            'campus_id' => 'sometimes|exists:campus,id',
            'image' => 'nullable|image',
            'cash_register_id' => 'nullable|exists:cash_cuts,id'
        ]);

        if ($request->hasFile('image')) {
            if ($gasto->image) {
                Storage::disk('public')->delete($gasto->image);
            }

            $path = $request->file('image')->store('gastos', 'public');
            $data['image'] = $path;
        }

        $gasto->update($data);

        // Return full URL for image
        if ($gasto->image) {
            $gasto->image = asset('storage/' . $gasto->image);
        }

        return response()->json($gasto);
    }

    public function destroy($id)
    {
        $gasto = Gasto::find($id);

        // Delete image if exists
        if ($gasto->image) {
            Storage::disk('public')->delete($gasto->image);
        }

        $gasto->delete();
        return response()->json(['message' => 'Gasto eliminado correctamente']);
    }
}

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
            if ($gasto->signature) {
                $gasto->signature = asset('storage/' . $gasto->signature);
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
                'signature' => 'nullable|string', // Base64 encoded signature
                'cash_register_id' => 'nullable|exists:cash_registers,id',
            ])->validate();

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('gastos', 'public');
            } else {
                $data['image'] = null;
            }

            // Handle signature if provided (base64 encoded)
            if (isset($validated['signature']) && !empty($validated['signature'])) {
                // Decode base64 signature
                $signatureData = $validated['signature'];
                if (strpos($signatureData, 'data:image/') === 0) {
                    $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
                }
                $signatureData = base64_decode($signatureData);
                
                $fileName = 'signature_' . time() . '_' . uniqid() . '.png';
                $signaturePath = 'gastos/signatures/' . $fileName;
                
                Storage::disk('public')->put($signaturePath, $signatureData);
                $data['signature'] = $signaturePath;
            } else {
                $data['signature'] = null;
            }

            $gasto = Gasto::create(array_merge($validated, [
                'image' => $data['image'],
                'signature' => $data['signature']
            ]));

            if ($gasto->image) {
                $gasto->image = asset('storage/' . $gasto->image);
            }
            if ($gasto->signature) {
                $gasto->signature = asset('storage/' . $gasto->signature);
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
        if ($gasto) {
            if ($gasto->image) {
                $gasto->image = asset('storage/' . $gasto->image);
            }
            if ($gasto->signature) {
                $gasto->signature = asset('storage/' . $gasto->signature);
            }
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
            'campus_id' => 'sometimes|exists:campuses,id',
            'image' => 'nullable|image',
            'signature' => 'nullable|string', // Base64 encoded signature
            'cash_register_id' => 'nullable|exists:cash_registers,id'
        ]);

        if ($request->hasFile('image')) {
            if ($gasto->image) {
                Storage::disk('public')->delete($gasto->image);
            }

            $path = $request->file('image')->store('gastos', 'public');
            $data['image'] = $path;
        }

        // Handle signature update
        if (isset($data['signature']) && !empty($data['signature'])) {
            // Delete old signature if exists
            if ($gasto->signature) {
                Storage::disk('public')->delete($gasto->signature);
            }

            // Decode and save new signature
            $signatureData = $data['signature'];
            if (strpos($signatureData, 'data:image/') === 0) {
                $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
            }
            $signatureData = base64_decode($signatureData);
            
            $fileName = 'signature_' . time() . '_' . uniqid() . '.png';
            $signaturePath = 'gastos/signatures/' . $fileName;
            
            Storage::disk('public')->put($signaturePath, $signatureData);
            $data['signature'] = $signaturePath;
        }

        $gasto->update($data);

        // Return full URL for image and signature
        if ($gasto->image) {
            $gasto->image = asset('storage/' . $gasto->image);
        }
        if ($gasto->signature) {
            $gasto->signature = asset('storage/' . $gasto->signature);
        }

        return response()->json($gasto);
    }

    public function destroy($id)
    {
        $gasto = Gasto::find($id);

        // Delete image and signature if exists
        if ($gasto->image) {
            Storage::disk('public')->delete($gasto->image);
        }
        if ($gasto->signature) {
            Storage::disk('public')->delete($gasto->signature);
        }

        $gasto->delete();
        return response()->json(['message' => 'Gasto eliminado correctamente']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
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
        $data = $request->validate([
            'concept' => 'required|string',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'method' => 'required|string',
            'user_id' => 'required|exists:users,id',
            'admin_id' => 'required|exists:users,id',
            'category' => 'required|string',
            'campus_id' => 'required',
            'image' => 'nullable|image',
            'cash_cut_id' => 'nullable|exists:cash_cuts,id'
        ]);
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('gastos', 'public');
            $data['image'] = $path;
        }

        $gasto = Gasto::create($data);

        // Return full URL for image
        if ($gasto->image) {
            $gasto->image = asset('storage/' . $gasto->image);
        }

        return response()->json($gasto, 201);
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
            'cash_cut_id' => 'nullable|exists:cash_cuts,id'
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCuts;
use Illuminate\Http\Request;

class CashCutController extends Controller
{
    public function index(){
        $cashCuts = CashCuts::all();
        return response()->json($cashCuts);
    }

    public function store(Request $request){
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'initial_amount' => 'required|numeric',
            'final_amount' => 'required|numeric',
            'real_amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date',
            'campus_id' => 'required|exists:campuses,id'
        ]);

        $cashCut = CashCuts::create($request->all());
        return response()->json($cashCut, 201);
    }

    public function show($id){
        $cashCut = CashCuts::find($id);
        return response()->json($cashCut);
    }

    public function update(Request $request, $id){
        $cashCut = CashCuts::find($id);
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'initial_amount' => 'required|numeric',
            'final_amount' => 'required|numeric',
            'real_amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date',
            'campus_id' => 'required|exists:campuses,id'
        ]);

        $cashCut->update($request->all());
        return response()->json($cashCut);
    }

    public function destroy($id){
        $cashCut = CashCuts::find($id);
        $cashCut->delete();
        return response()->json(['message' => 'Cash cut deleted successfully']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function index()
    {
        return Period::all();
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);
        
        return Period::create($request->all());
    }

    public function update(Request $request, $id)
    {
        return Period::find($id)->update($request->all());
    }

    public function destroy($id)
    {
        return Period::find($id)->delete();
    }
}

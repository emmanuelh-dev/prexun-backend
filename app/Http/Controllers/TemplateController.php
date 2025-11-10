<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $templates = Template::active()->orderBy('name')->get();
            
            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las plantillas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:templates,name',
                'meta_id' => 'required|string|max:255|unique:templates,meta_id',
                'parameters' => 'nullable|array',
                'parameters.*.name' => 'required|string',
                'parameters.*.example' => 'required|string',
                'example_message' => 'nullable|string',
                'is_active' => 'boolean'
            ], [
                'name.required' => 'El nombre de la plantilla es obligatorio',
                'name.unique' => 'Ya existe una plantilla con este nombre',
                'meta_id.required' => 'El ID de Meta es obligatorio',
                'meta_id.unique' => 'Ya existe una plantilla con este ID de Meta',
                'parameters.array' => 'Los parámetros deben ser un arreglo',
                'parameters.*.name.required' => 'El nombre del parámetro es obligatorio',
                'parameters.*.example.required' => 'El ejemplo del parámetro es obligatorio'
            ]);

            $template = Template::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla creada exitosamente',
                'data' => $template
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la plantilla',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Template $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:templates,name,' . $template->id,
                'meta_id' => 'required|string|max:255|unique:templates,meta_id,' . $template->id,
                'parameters' => 'nullable|array',
                'parameters.*.name' => 'required|string',
                'parameters.*.example' => 'required|string',
                'example_message' => 'nullable|string',
                'is_active' => 'boolean'
            ], [
                'name.required' => 'El nombre de la plantilla es obligatorio',
                'name.unique' => 'Ya existe una plantilla con este nombre',
                'meta_id.required' => 'El ID de Meta es obligatorio',
                'meta_id.unique' => 'Ya existe una plantilla con este ID de Meta',
                'parameters.array' => 'Los parámetros deben ser un arreglo',
                'parameters.*.name.required' => 'El nombre del parámetro es obligatorio',
                'parameters.*.example.required' => 'El ejemplo del parámetro es obligatorio'
            ]);

            $template->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla actualizada exitosamente',
                'data' => $template
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la plantilla',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Template $template): JsonResponse
    {
        try {
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plantilla eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la plantilla',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Context;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ContextController extends Controller
{
    /**
     * Listar todos los contextos
     */
    public function index(): JsonResponse
    {
        $contexts = Context::active()->get();

        return response()->json([
            'success' => true,
            'data' => $contexts
        ]);
    }

    /**
     * Crear nuevo contexto
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:contexts,name',
            'instructions' => 'required|string'
        ]);

        $context = Context::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contexto creado correctamente',
            'data' => $context
        ], 201);
    }

    /**
     * Mostrar contexto específico
     */
    public function show(Context $context): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $context
        ]);
    }

    /**
     * Obtener contexto por nombre
     */
    public function getByName(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $context = Context::getByName($request->name);

        if (!$context) {
            return response()->json([
                'success' => false,
                'message' => 'Contexto no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $context
        ]);
    }

    /**
     * Actualizar contexto
     */
    public function update(Request $request, Context $context): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|unique:contexts,name,' . $context->id,
            'instructions' => 'sometimes|string'
        ]);

        $context->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contexto actualizado correctamente',
            'data' => $context->fresh()
        ]);
    }

    /**
     * Obtener instrucciones del contexto
     */
    public function getInstructions(Context $context): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'instructions' => $context->instructions,
                'context_name' => $context->name
            ]
        ]);
    }

    /**
     * Activar contexto
     */
    public function activate(Context $context): JsonResponse
    {
        $context->activate();

        return response()->json([
            'success' => true,
            'message' => 'Contexto activado correctamente'
        ]);
    }

    /**
     * Desactivar contexto
     */
    public function deactivate(Context $context): JsonResponse
    {
        $context->deactivate();

        return response()->json([
            'success' => true,
            'message' => 'Contexto desactivado correctamente'
        ]);
    }

    /**
     * Eliminar contexto
     */
    public function destroy(Context $context): JsonResponse
    {
        $context->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contexto eliminado correctamente'
        ]);
    }

    /**
     * Obtener estadísticas de contextos
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total' => Context::count(),
            'active' => Context::active()->count(),
            'inactive' => Context::where('is_active', false)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Crear contexto por defecto para WhatsApp
     */
    public function createWhatsAppDefault(): JsonResponse
    {
        try {
            $context = Context::createWhatsAppDefault();
            
            return response()->json([
                'success' => true,
                'message' => 'Contexto por defecto de WhatsApp creado',
                'data' => $context
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear contexto: ' . $e->getMessage()
            ], 400);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Context;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ContextController extends Controller
{
    public function getByWhatsApp($whatsappId): JsonResponse
    {
        $context = Context::getOrCreateForWhatsApp($whatsappId);
        return response()->json($context);
    }

    public function updateInstructions(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp_id' => 'required|string',
            'instructions' => 'required|string|max:5000'
        ]);

        $context = Context::getOrCreateForWhatsApp($request->whatsapp_id);
        $context->updateInstructions($request->instructions);

        return response()->json([
            'success' => true,
            'context' => $context->fresh()
        ]);
    }

    public function updateUserInfo(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp_id' => 'required|string',
            'user_info' => 'required|array'
        ]);

        $context = Context::getOrCreateForWhatsApp($request->whatsapp_id);
        $context->updateUserInfo($request->user_info);

        return response()->json([
            'success' => true,
            'context' => $context->fresh()
        ]);
    }

    public function setState(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp_id' => 'required|string',
            'state' => 'required|string',
            'temp_data' => 'sometimes|array'
        ]);

        $context = Context::getOrCreateForWhatsApp($request->whatsapp_id);
        $context->setState($request->state, $request->temp_data);

        return response()->json([
            'success' => true,
            'context' => $context->fresh()
        ]);
    }

    public function reset($whatsappId): JsonResponse
    {
        $context = Context::where('whatsapp_id', $whatsappId)->first();
        
        if ($context) {
            $context->reset();
            return response()->json([
                'success' => true,
                'message' => 'Context reset successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Context not found'
        ], 404);
    }

    public function deactivate($whatsappId): JsonResponse
    {
        $context = Context::where('whatsapp_id', $whatsappId)->first();
        
        if ($context) {
            $context->deactivate();
            return response()->json([
                'success' => true,
                'message' => 'Context deactivated successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Context not found'
        ], 404);
    }

    public function getActiveContexts(): JsonResponse
    {
        $contexts = Context::active()
            ->recentlyActive()
            ->select(['id', 'whatsapp_id', 'current_state', 'last_interaction'])
            ->orderBy('last_interaction', 'desc')
            ->paginate(20);

        return response()->json($contexts);
    }

    public function getStats(): JsonResponse
    {
        $stats = [
            'total_contexts' => Context::count(),
            'active_contexts' => Context::active()->count(),
            'recently_active' => Context::active()->recentlyActive()->count()
        ];

        return response()->json($stats);
    }
}

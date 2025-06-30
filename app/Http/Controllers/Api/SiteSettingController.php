<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SiteSettingController extends Controller
{
    /**
     * Obtener todas las configuraciones agrupadas
     */
    public function index()
    {
        try {
            $settings = SiteSetting::orderBy('group')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();

            // Enriquecer configuraciones con opciones dinámicas
            $settings = $settings->map(function ($setting) {
                return $this->enrichSettingWithDynamicOptions($setting);
            });

            // Agrupar por group
            $groupedSettings = $settings->groupBy('group');

            return response()->json([
                'status' => 'success',
                'data' => $groupedSettings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Enriquecer configuración con opciones dinámicas
     */
    private function enrichSettingWithDynamicOptions($setting)
    {
        // Si la configuración necesita opciones dinámicas
        if ($setting->type === 'select' && $this->needsDynamicOptions($setting->key)) {
            $setting->options = $this->getDynamicOptions($setting->key);
        }

        return $setting;
    }

    /**
     * Verificar si una configuración necesita opciones dinámicas
     */
    private function needsDynamicOptions($key)
    {
        $dynamicKeys = [
            'default_period_id',
            'default_campus_id',
            'default_grupo_id',
            // Agregar más keys que necesiten opciones dinámicas
        ];

        return in_array($key, $dynamicKeys);
    }

    /**
     * Obtener opciones dinámicas según la key
     */
    private function getDynamicOptions($key)
    {
        switch ($key) {
            case 'default_period_id':
                return \App\Models\Period::orderBy('name')
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray();

            case 'default_campus_id':
                return \App\Models\Campus::where('is_active', true)
                    ->orderBy('name')
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray();

            case 'default_grupo_id':
                return \App\Models\Grupo::orderBy('name')
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray();

            default:
                return [];
        }
    }

    /**
     * Obtener configuraciones por grupo
     */
    public function getByGroup($group)
    {
        try {
            $settings = SiteSetting::getByGroup($group);

            return response()->json([
                'status' => 'success',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuraciones del grupo: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener una configuración específica
     */
    public function show($id)
    {
        try {
            $setting = SiteSetting::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $setting
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configuración no encontrada'
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Crear una nueva configuración
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:site_settings,key',
            'label' => 'required|string|max:255',
            'value' => 'nullable',
            'type' => 'required|in:text,number,boolean,select,json,textarea,email,url,password',
            'description' => 'nullable|string',
            'options' => 'nullable|array',
            'group' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $value = $request->value;
            if (in_array($request->type, ['json', 'array']) && (is_array($value) || is_object($value))) {
                $value = json_encode($value);
            }

            $setting = SiteSetting::create([
                'key' => $request->key,
                'label' => $request->label,
                'value' => $value,
                'type' => $request->type,
                'description' => $request->description,
                'options' => $request->options,
                'group' => $request->group,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración creada exitosamente',
                'data' => $setting
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear configuración: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar una configuración
     */
    public function update(Request $request, $id)
    {
        $setting = SiteSetting::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:site_settings,key,' . $id,
            'label' => 'required|string|max:255',
            'value' => 'nullable',
            'type' => 'required|in:text,number,boolean,select,json,textarea,email,url,password',
            'description' => 'nullable|string',
            'options' => 'nullable|array',
            'group' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $value = $request->value;
            if (in_array($request->type, ['json', 'array']) && (is_array($value) || is_object($value))) {
                $value = json_encode($value);
            }

            $setting->update([
                'key' => $request->key,
                'label' => $request->label,
                'value' => $value,
                'type' => $request->type,
                'description' => $request->description,
                'options' => $request->options,
                'group' => $request->group,
                'sort_order' => $request->sort_order ?? $setting->sort_order,
                'is_active' => $request->is_active ?? $setting->is_active
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración actualizada exitosamente',
                'data' => $setting->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuración: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Actualizar múltiples configuraciones
     */
    public function updateMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:site_settings,id',
            'settings.*.value' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            foreach ($request->settings as $settingData) {
                $setting = SiteSetting::find($settingData['id']);
                if ($setting) {
                    $value = $settingData['value'];
                    if (in_array($setting->type, ['json', 'array']) && (is_array($value) || is_object($value))) {
                        $value = json_encode($value);
                    }
                    $setting->update(['value' => $value]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuraciones actualizadas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuraciones: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Eliminar una configuración
     */
    public function destroy($id)
    {
        try {
            $setting = SiteSetting::findOrFail($id);
            $setting->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar configuración: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener el valor de una configuración específica por key
     */
    public function getValue($key)
    {
        try {
            $value = SiteSetting::getValue($key);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'key' => $key,
                    'value' => $value
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener valor de configuración'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener configuraciones específicas para la UI
     */
    public function getUIConfig()
    {
        try {
            $paymentMethods = SiteSetting::getValue('payment_methods_enabled', '["cash"]');
            if (is_string($paymentMethods)) {
                $paymentMethods = json_decode($paymentMethods, true);
            }

            $config = [
                'default_period_id' => \App\Helpers\SiteConfigHelper::getDefaultPeriodId(),
                'default_items_per_page' => (int) SiteSetting::getValue('default_items_per_page', 10),
                'default_theme' => SiteSetting::getValue('default_theme', 'light'),
                'payment_methods_enabled' => $paymentMethods,
                'default_payment_method' => SiteSetting::getValue('default_payment_method', 'cash'),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuración de UI: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

# Integración de Google desde Backend - Guía de Implementación

## 📋 Resumen

Esta guía describe cómo implementar la integración con Google APIs desde el backend de Laravel, permitiendo que los planteles mantengan una conexión persistente con Google Contacts y otros servicios de Google sin depender de la sesión del usuario en el frontend.

## 🔑 Gestión de Tokens de Google

### Access Tokens
- **Duración**: ~1 hora (3600 segundos)
- **Problema**: Se vencen muy rápido para uso empresarial
- **Uso**: Para realizar llamadas inmediatas a APIs de Google

### Refresh Tokens
- **Duración**: Hasta 6 meses (si se usan regularmente)
- **Ventaja**: Permiten obtener nuevos access tokens automáticamente
- **Importante**: Solo se obtienen en el primer consentimiento con `prompt=consent`

## 🏗️ Arquitectura Propuesta

### 1. Estructura de Base de Datos

```sql
-- Tabla para almacenar integraciones de Google por plantel
CREATE TABLE google_integrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campus_id BIGINT UNSIGNED NOT NULL,
    refresh_token TEXT NOT NULL, -- Encriptado
    access_token TEXT, -- Encriptado
    token_expires_at TIMESTAMP NULL,
    scope TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campus_integration (campus_id),
    INDEX idx_campus_active (campus_id, is_active),
    INDEX idx_token_expires (token_expires_at)
);
```

### 2. Modelo Eloquent

```php
<?php
// app/Models/GoogleIntegration.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleIntegration extends Model
{
    protected $fillable = [
        'campus_id',
        'refresh_token',
        'access_token',
        'token_expires_at',
        'scope',
        'email',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    protected $hidden = [
        'refresh_token',
        'access_token'
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    // Mutators para encriptar tokens
    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = encrypt($value);
    }

    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = encrypt($value);
    }

    // Accessors para desencriptar tokens
    public function getRefreshTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    public function getAccessTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeExpiredTokens($query)
    {
        return $query->where('token_expires_at', '<', now());
    }
}
```

## 🔧 Servicios de Implementación

### 1. Google Token Service

```php
<?php
// app/Services/GoogleTokenService.php

namespace App\Services;

use App\Models\GoogleIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleTokenService
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect_uri');
    }

    /**
     * Intercambiar código de autorización por tokens
     */
    public function exchangeCodeForTokens(string $code, int $campusId): array
    {
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for tokens');
        }

        $tokens = $response->json();
        
        // Obtener información del usuario
        $userInfo = $this->getUserInfo($tokens['access_token']);

        // Guardar o actualizar la integración
        $integration = GoogleIntegration::updateOrCreate(
            ['campus_id' => $campusId],
            [
                'refresh_token' => $tokens['refresh_token'],
                'access_token' => $tokens['access_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'scope' => $tokens['scope'] ?? 'contacts',
                'email' => $userInfo['email'],
                'is_active' => true,
                'last_used_at' => now()
            ]
        );

        Log::info('Google integration created/updated', [
            'campus_id' => $campusId,
            'email' => $userInfo['email']
        ]);

        return $tokens;
    }

    /**
     * Obtener un access token válido para un plantel
     */
    public function getValidAccessToken(int $campusId): ?string
    {
        $integration = GoogleIntegration::active()
            ->where('campus_id', $campusId)
            ->first();

        if (!$integration) {
            return null;
        }

        // Verificar si el token necesita renovación
        if ($integration->token_expires_at <= now()->addMinutes(5)) {
            $this->refreshAccessToken($integration);
            $integration->refresh();
        }

        $integration->update(['last_used_at' => now()]);
        
        return $integration->access_token;
    }

    /**
     * Renovar access token usando refresh token
     */
    private function refreshAccessToken(GoogleIntegration $integration): void
    {
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $integration->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            Log::error('Failed to refresh Google token', [
                'campus_id' => $integration->campus_id,
                'response' => $response->body()
            ]);
            
            // Marcar como inactiva si el refresh falla
            $integration->update(['is_active' => false]);
            throw new \Exception('Failed to refresh access token');
        }

        $tokens = $response->json();

        $integration->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        Log::info('Google token refreshed successfully', [
            'campus_id' => $integration->campus_id
        ]);
    }

    /**
     * Obtener información del usuario autenticado
     */
    private function getUserInfo(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}"
        ])->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (!$response->successful()) {
            throw new \Exception('Failed to get user info');
        }

        return $response->json();
    }

    /**
     * Revocar la integración de Google para un plantel
     */
    public function revokeIntegration(int $campusId): bool
    {
        $integration = GoogleIntegration::where('campus_id', $campusId)->first();

        if (!$integration) {
            return false;
        }

        // Revocar el token en Google
        $response = Http::post('https://oauth2.googleapis.com/revoke', [
            'token' => $integration->refresh_token
        ]);

        // Marcar como inactiva independientemente de la respuesta de Google
        $integration->update(['is_active' => false]);

        Log::info('Google integration revoked', [
            'campus_id' => $campusId,
            'revoke_successful' => $response->successful()
        ]);

        return true;
    }
}
```

### 2. Google Contacts Service

```php
<?php
// app/Services/GoogleContactsService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleContactsService
{
    /**
     * Obtener contactos de Google
     */
    public function getContacts(string $accessToken, int $pageSize = 100): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}"
        ])->get('https://people.googleapis.com/v1/people/me/connections', [
            'personFields' => 'names,emailAddresses,phoneNumbers',
            'pageSize' => $pageSize
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch contacts from Google');
        }

        $data = $response->json();
        return $data['connections'] ?? [];
    }

    /**
     * Crear un contacto en Google
     */
    public function createContact(string $accessToken, array $contactData): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json'
        ])->post('https://people.googleapis.com/v1/people:createContact', [
            'names' => [['givenName' => $contactData['name']]],
            'emailAddresses' => [['value' => $contactData['email']]],
            'phoneNumbers' => [['value' => $contactData['phone']]]
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create contact in Google');
        }

        return $response->json();
    }
}
```

## 🎮 Controladores

### 1. Google Auth Controller

```php
<?php
// app/Http/Controllers/GoogleAuthController.php

namespace App\Http\Controllers;

use App\Services\GoogleTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GoogleAuthController extends Controller
{
    private GoogleTokenService $googleTokenService;

    public function __construct(GoogleTokenService $googleTokenService)
    {
        $this->googleTokenService = $googleTokenService;
    }

    /**
     * Obtener URL de autorización de Google
     */
    public function getAuthUrl(Request $request): JsonResponse
    {
        $campusId = $request->user()->campus_id;
        
        $params = [
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'scope' => 'https://www.googleapis.com/auth/contacts https://www.googleapis.com/auth/userinfo.email',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $campusId
        ];

        $authUrl = 'https://accounts.google.com/oauth/authorize?' . http_build_query($params);

        return response()->json(['auth_url' => $authUrl]);
    }

    /**
     * Manejar callback de Google OAuth
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $code = $request->input('code');
        $campusId = $request->input('state');

        if (!$code || !$campusId) {
            return response()->json(['error' => 'Missing authorization code or state'], 400);
        }

        try {
            $tokens = $this->googleTokenService->exchangeCodeForTokens($code, $campusId);
            
            return response()->json([
                'message' => 'Google integration successful',
                'campus_id' => $campusId
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verificar estado de la integración
     */
    public function getIntegrationStatus(Request $request): JsonResponse
    {
        $campusId = $request->user()->campus_id;
        $accessToken = $this->googleTokenService->getValidAccessToken($campusId);

        return response()->json([
            'integrated' => !is_null($accessToken),
            'campus_id' => $campusId
        ]);
    }

    /**
     * Revocar integración de Google
     */
    public function revokeIntegration(Request $request): JsonResponse
    {
        $campusId = $request->user()->campus_id;
        
        $success = $this->googleTokenService->revokeIntegration($campusId);

        return response()->json([
            'message' => $success ? 'Integration revoked successfully' : 'No integration found',
            'success' => $success
        ]);
    }
}
```

### 2. Google Contacts Controller

```php
<?php
// app/Http/Controllers/GoogleContactsController.php

namespace App\Http\Controllers;

use App\Services\GoogleTokenService;
use App\Services\GoogleContactsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GoogleContactsController extends Controller
{
    private GoogleTokenService $googleTokenService;
    private GoogleContactsService $googleContactsService;

    public function __construct(
        GoogleTokenService $googleTokenService,
        GoogleContactsService $googleContactsService
    ) {
        $this->googleTokenService = $googleTokenService;
        $this->googleContactsService = $googleContactsService;
    }

    /**
     * Obtener contactos de Google para el plantel
     */
    public function getContacts(Request $request): JsonResponse
    {
        $campusId = $request->user()->campus_id;
        $accessToken = $this->googleTokenService->getValidAccessToken($campusId);

        if (!$accessToken) {
            return response()->json(['error' => 'Google integration not found'], 404);
        }

        try {
            $contacts = $this->googleContactsService->getContacts($accessToken);
            
            return response()->json([
                'contacts' => $contacts,
                'count' => count($contacts)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear contacto en Google
     */
    public function createContact(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        $campusId = $request->user()->campus_id;
        $accessToken = $this->googleTokenService->getValidAccessToken($campusId);

        if (!$accessToken) {
            return response()->json(['error' => 'Google integration not found'], 404);
        }

        try {
            $contact = $this->googleContactsService->createContact(
                $accessToken,
                $request->only(['name', 'email', 'phone'])
            );
            
            return response()->json([
                'message' => 'Contact created successfully',
                'contact' => $contact
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

## 🛠️ Configuración

### 1. Variables de Entorno (.env)

```env
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://your-domain.com/auth/google/callback
```

### 2. Configuración de Servicios (config/services.php)

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
],
```

## 🛣️ Rutas de API

```php
// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {
    // Google Authentication
    Route::prefix('google')->group(function () {
        Route::get('/auth-url', [GoogleAuthController::class, 'getAuthUrl']);
        Route::post('/callback', [GoogleAuthController::class, 'handleCallback']);
        Route::get('/status', [GoogleAuthController::class, 'getIntegrationStatus']);
        Route::delete('/revoke', [GoogleAuthController::class, 'revokeIntegration']);
    });

    // Google Contacts
    Route::prefix('google/contacts')->group(function () {
        Route::get('/', [GoogleContactsController::class, 'getContacts']);
        Route::post('/', [GoogleContactsController::class, 'createContact']);
    });
});
```

## 🔄 Comandos Artisan

### 1. Comando para Renovar Tokens

```php
<?php
// app/Console/Commands/RefreshGoogleTokens.php

namespace App\Console\Commands;

use App\Models\GoogleIntegration;
use App\Services\GoogleTokenService;
use Illuminate\Console\Command;

class RefreshGoogleTokens extends Command
{
    protected $signature = 'google:refresh-tokens';
    protected $description = 'Refresh expiring Google tokens';

    public function handle(GoogleTokenService $googleTokenService)
    {
        $expiringIntegrations = GoogleIntegration::active()
            ->where('token_expires_at', '<=', now()->addHours(2))
            ->get();

        $this->info("Found {$expiringIntegrations->count()} tokens to refresh");

        foreach ($expiringIntegrations as $integration) {
            try {
                $googleTokenService->getValidAccessToken($integration->campus_id);
                $this->info("Refreshed token for campus {$integration->campus_id}");
            } catch (\Exception $e) {
                $this->error("Failed to refresh token for campus {$integration->campus_id}: {$e->getMessage()}");
            }
        }
    }
}
```

### 2. Programar en Kernel

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Renovar tokens de Google cada hora
    $schedule->command('google:refresh-tokens')->hourly();
}
```

## 🚀 Ventajas de esta Implementación

### ✅ Beneficios Técnicos
- **Persistencia**: La integración sobrevive al cierre del navegador
- **Seguridad**: Tokens encriptados y nunca expuestos al frontend
- **Multi-usuario**: Varios usuarios del plantel pueden beneficiarse
- **Automatización**: Sincronización y renovación automática de tokens
- **Auditoría**: Logs centralizados de uso y errores

### ✅ Beneficios de Negocio
- **Continuidad**: La integración permanece activa para el plantel
- **Escalabilidad**: Fácil expansión a otros servicios de Google
- **Mantenimiento**: Gestión centralizada de integraciones
- **Compliance**: Mejor control de datos sensibles

## ⚠️ Consideraciones de Seguridad

1. **Encriptación**: Todos los tokens deben estar encriptados en la base de datos
2. **Logs**: No registrar tokens en logs, solo metadatos
3. **Acceso**: Implementar middleware para verificar permisos de plantel
4. **Revocación**: Permitir revocación manual y automática por inactividad
5. **Monitoreo**: Alertas cuando los refresh tokens fallan

## 📅 Próximos Pasos

1. **Crear migración** para la tabla `google_integrations`
2. **Implementar modelos y servicios** siguiendo este diseño
3. **Configurar OAuth** en Google Cloud Console
4. **Crear tests unitarios** para los servicios críticos
5. **Implementar monitoreo** y alertas para tokens expirados
6. **Documentar endpoints** en la documentación de API

---

*Documento creado el 30 de agosto de 2025*
*Versión: 1.0*
*Autor: Sistema de Documentación Automática*

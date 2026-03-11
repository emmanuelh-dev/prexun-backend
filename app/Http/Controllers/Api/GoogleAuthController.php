<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    public function authUrl(Request $request)
    {
        $campusId = $request->query('campus_id');
        if (!$campusId) {
            return response()->json(['message' => 'El campus_id es obligatorio'], 400);
        }

        $client = new \Google\Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        
        // Agregar scopes para People API
        $client->addScope('https://www.googleapis.com/auth/contacts');
        $client->addScope('https://www.googleapis.com/auth/userinfo.email');

        // Pasamos el campus_id en el estado
        $client->setState($campusId);

        $authUrl = $client->createAuthUrl();

        return response()->json(['url' => $authUrl]);
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $campusId = $request->query('state');

        if (!$code || !$campusId) {
            return response()->json(['message' => 'Faltan parámetros en la respuesta de Google'], 400);
        }

        $client = new \Google\Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));

        // Canjea el código por un token
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return response()->json(['message' => 'Error al obtener token', 'error' => $token['error']], 400);
        }

        $client->setAccessToken($token);

        // Obtenemos el correo del usuario que se acaba de loguear
        $oauth2 = new \Google\Service\Oauth2($client);
        $userInfo = $oauth2->userinfo->get();
        $email = $userInfo->email;

        // Guardar o actualizar en la base de datos
        \App\Models\GoogleSession::updateOrCreate(
            ['campus_id' => $campusId, 'email' => $email],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_in' => isset($token['created'], $token['expires_in']) 
                                ? \Carbon\Carbon::createFromTimestamp($token['created'] + $token['expires_in']) 
                                : null,
                'token_data' => $token,
                'is_active' => true
            ]
        );

        // Redirigir de nuevo al frontend (planteles/estudiantes o configuración)
        // Puedes pasar un parámetro success para mostrar un mensaje en el frontend
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        
        return redirect()->away($frontendUrl . '/planteles/estudiantes?google_auth=success');
    }

    public function index($campusId)
    {
        $sessions = \App\Models\GoogleSession::where('campus_id', $campusId)
            ->where('is_active', true)
            ->get(['id', 'email', 'updated_at']);
            
        return response()->json($sessions);
    }

    public function destroy($id)
    {
        $session = \App\Models\GoogleSession::findOrFail($id);
        
        // Opcional: revocar el token en Google
        try {
            $client = new \Google\Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->revokeToken($session->access_token);
        } catch (\Exception $e) {
            // Ignoramos errores de revocación, lo importante es quitarlo de DB
            \Illuminate\Support\Facades\Log::warning("No se pudo revocar token de Google: " . $e->getMessage());
        }

        $session->delete();

        return response()->json(['message' => 'Sesión desvinculada exitosamente']);
    }
}

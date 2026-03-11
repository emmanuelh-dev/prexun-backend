<?php

namespace App\Services;

use App\Models\GoogleSession;
use Google\Client;
use Google\Service\PeopleService;

class GoogleContactsService
{
    /**
     * Sincroniza un contacto con todas las sesiones de Google vinculadas a un plantel.
     * 
     * @param int $campusId ID del plantel
     * @param array $contactData [ 'name' => string, 'email' => string, 'phone' => string, 'secondaryPhone' => string|null ]
     */
    public function syncContactToCampus($campusId, $contactData)
    {
        $sessions = GoogleSession::where('campus_id', $campusId)->where('is_active', true)->get();

        foreach ($sessions as $session) {
            try {
                $this->createContact($session, $contactData);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Error sincronizando contacto de Google para sesión {$session->id}: " . $e->getMessage());
                // Podríamos marcar la sesión como is_active = false si el error es 'invalid_grant'
                if (str_contains($e->getMessage(), 'invalid_grant')) {
                    $session->update(['is_active' => false]);
                }
            }
        }
    }

    private function createContact(GoogleSession $session, $data)
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        
        $client->setAccessToken($session->access_token);

        // Si el token expiró y tenemos refresh_token, lo renovamos
        if ($client->isAccessTokenExpired() && $session->refresh_token) {
            $client->fetchAccessTokenWithRefreshToken($session->refresh_token);
            $newAccessToken = $client->getAccessToken();
            
            // Actualizamos la DB
            $session->update([
                'access_token' => $newAccessToken['access_token'],
                'expires_in' => isset($newAccessToken['created'], $newAccessToken['expires_in']) 
                                ? \Carbon\Carbon::createFromTimestamp($newAccessToken['created'] + $newAccessToken['expires_in']) 
                                : null,
                'token_data' => $newAccessToken
            ]);
        }

        $service = new PeopleService($client);

        $person = new \Google\Service\PeopleService\Person();
        
        // Nombres
        $name = new \Google\Service\PeopleService\Name();
        $name->setGivenName($data['name']);
        $person->setNames([$name]);

        // Email
        if (!empty($data['email'])) {
            $email = new \Google\Service\PeopleService\EmailAddress();
            $email->setValue($data['email']);
            $email->setType('work');
            $person->setEmailAddresses([$email]);
        }

        // Teléfonos
        $phones = [];
        if (!empty($data['phone'])) {
            $phone = new \Google\Service\PeopleService\PhoneNumber();
            $phone->setValue($data['phone']);
            $phone->setType('mobile');
            $phones[] = $phone;
        }

        if (!empty($data['secondaryPhone'])) {
            $secPhone = new \Google\Service\PeopleService\PhoneNumber();
            $secPhone->setValue($data['secondaryPhone']);
            $secPhone->setType('other');
            $phones[] = $secPhone;
        }

        if (count($phones) > 0) {
            $person->setPhoneNumbers($phones);
        }

        // Ejecutar creación
        $service->people->createContact($person);
    }
}

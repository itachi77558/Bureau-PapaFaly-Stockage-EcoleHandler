<?php

namespace App\Http\Controllers;

use App\Services\AuthentificationServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthentificationServiceInterface $authService)
    {
        $this->authService = $authService;
        $this->authService->setAuthMode(config('app.auth_mode'));
    }

    public function login(Request $request)
    {
        // Vérifier et créer le client personnel si nécessaire
        $this->ensurePersonalAccessClientExists();

        $credentials = $request->only('email', 'password');
        $authResponse = $this->authService->authenticate($credentials);

        if (is_array($authResponse) && isset($authResponse['success'])) {
            if ($authResponse['success']) {
                return response()->json([
                    'access_token' => $authResponse['token'],
                    'token_type' => 'Bearer',
                    'user' => $authResponse['user'],
                ]);
            } elseif (isset($authResponse['require_password_change']) && $authResponse['require_password_change']) {
                return response()->json([
                    'success' => false,
                    'message' => $authResponse['message'],
                    'require_password_change' => true
                ], 200);
            }
        }

        return response()->json(['error' => $authResponse['message'] ?? 'Unauthorized'], 401);
    }

    public function logout()
    {
        $this->authService->logout();
        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    private function ensurePersonalAccessClientExists()
    {
        // Vérifier si le client personnel existe déjà
        $client = DB::table('oauth_clients')
            ->where('personal_access_client', 1)
            ->first();

        if (!$client) {
            // Créer un nouveau client personnel
            Artisan::call('passport:client --personal');

            // Récupérer les informations du client personnel
            $output = Artisan::output();
            $clientId = $this->extractClientId($output);
            $clientSecret = $this->extractClientSecret($output);

            // Stocker les informations dans le fichier .env
            $this->updateEnvFile('PASSPORT_PERSONAL_ACCESS_CLIENT_ID', $clientId);
            $this->updateEnvFile('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET', $clientSecret);

            // Recharger la configuration
            Artisan::call('config:cache');
        }
    }

    private function extractClientId($output)
    {
        preg_match('/Client ID: (\d+)/', $output, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    private function extractClientSecret($output)
    {
        preg_match('/Client secret: (.+)/', $output, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    private function updateEnvFile($key, $value)
    {
        $envFile = app()->environmentFilePath();
        $envContent = File::get($envFile);

        if (strpos($envContent, $key) !== false) {
            $envContent = preg_replace("/^{$key}=.+$/m", "{$key}={$value}", $envContent);
        } else {
            $envContent .= "\n{$key}={$value}\n";
        }

        File::put($envFile, $envContent);
    }
}

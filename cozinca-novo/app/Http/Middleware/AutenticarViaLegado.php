<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AutenticarViaLegado
{
    private const COOKIE_NAME = 'cozinca_ponte';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $token = $request->cookie(self::COOKIE_NAME);

        if ($token && $usuario = $this->validarToken($token)) {
            Auth::login($usuario);
            return $next($request);
        }

        $loginUrl = config('services.ponte.login_url');
        return redirect()->away($loginUrl);
    }

    private function validarToken(string $token): ?Usuario
    {
        $partes = explode('.', $token);

        if (count($partes) !== 2) {
            return null;
        }

        [$payloadBase64, $assinaturaRecebida] = $partes;

        $secret = config('services.ponte.secret');

        if (empty($secret)) {
            Log::error('PONTE_SECRET_KEY não configurada no .env do Laravel.');
            return null;
        }

        $assinaturaEsperada = hash_hmac('sha256', $payloadBase64, $secret);

        if (!hash_equals($assinaturaEsperada, $assinaturaRecebida)) {
            return null;
        }

        $payload = json_decode(base64_decode($payloadBase64), true);

        if (!is_array($payload) || !isset($payload['uid'], $payload['exp'])) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null;
        }

        return Usuario::find($payload['uid']);
    }
}
<?php

namespace App\Services\Gis;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ArcGisToken
{
    private const CACHE_KEY = 'gis.arcgis.token';

    /** Returns a portal token, or null when no ArcGIS credentials are configured (anonymous queries). */
    public function get(): ?string
    {
        $username = (string) config('gis.arcgis.username');
        if ($username === '') {
            return null;
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), fn (): string => $this->generate($username));
    }

    private function generate(string $username): string
    {
        try {
            $response = Http::asForm()->timeout(30)->post(rtrim((string) config('gis.arcgis.portal_url'), '/').'/sharing/rest/generateToken', [
                'username' => $username,
                'password' => (string) config('gis.arcgis.password'),
                'client' => 'referer',
                'referer' => config('app.url'),
                'expiration' => 60,
                'f' => 'json',
            ]);
            $token = $response->json('token');
        } catch (Throwable) {
            throw new RuntimeException('Token ArcGIS tidak dapat dijana.');
        }

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Token ArcGIS tidak dapat dijana.');
        }

        return $token;
    }
}

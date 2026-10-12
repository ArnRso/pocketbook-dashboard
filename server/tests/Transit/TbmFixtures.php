<?php

declare(strict_types=1);

namespace App\Tests\Transit;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Faux serveur SIRI-Lite de TBM : réponses enregistrées pour Palmer (trams A et E).
 */
final class TbmFixtures
{
    public static function http(): MockHttpClient
    {
        return new MockHttpClient(static fn (string $method, string $url): MockResponse => self::response($url), 'https://bdx.mecatran.com');
    }

    public static function response(string $url): MockResponse
    {
        $name = match (true) {
            str_contains($url, '/lines-discovery.json') => 'tbm-lines.json',
            str_contains($url, '/stoppoints-discovery.json') => 'tbm-stop-points.json',
            default => 'tbm-stop-monitoring.json',
        };
        $json = file_get_contents(__DIR__.'/../fixtures/'.$name);

        return new MockResponse(false !== $json ? $json : '');
    }
}

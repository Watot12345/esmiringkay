<?php

namespace App\Domain\Rooms\Services;

class TurnCredentialService
{
    /**
     * Generate ephemeral HMAC-SHA1 coturn credentials (RFC 5766) per 05-infrastructure.md Section 6.
     *
     * @return array<string, mixed>
     */
    public function generateIceServers(string $userId = 'guest'): array
    {
        $turnUrl = config('services.turn.url', 'turn:turn.emiringkay.com:3478');
        $secret = config('services.turn.secret', 'emiringkay-local-turn-secret');
        $ttl = (int) config('services.turn.ttl', 86400);

        $expiry = time() + $ttl;
        $username = sprintf('%d:%s', $expiry, $userId);
        $credential = base64_encode(hash_hmac('sha1', $username, $secret, true));

        return [
            [
                'urls' => [
                    'stun:stun.l.google.com:19302',
                    'stun:stun1.l.google.com:19302',
                ],
            ],
            [
                'urls' => $turnUrl,
                'username' => $username,
                'credential' => $credential,
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenService
{
    public function getOrCreateToken(User $user, string $deviceName): string
    {
        $existing = $user->tokens()
            ->where('name', $deviceName)
            ->latest()
            ->get()
            ->first(fn (PersonalAccessToken $token) => $this->isValid($token));

        if ($existing !== null) {
            $plain = Cache::get($this->cacheKey($existing->id));
            if (is_string($plain) && $plain !== '') {
                return $plain;
            }

            $existing->delete();
        }

        return $this->createAndCache($user, $deviceName);
    }

    private function createAndCache(User $user, string $deviceName): string
    {
        $expiresAt = $this->expiresAt();
        $newToken = $user->createToken($deviceName, ['*'], $expiresAt);
        $plain = $newToken->plainTextToken;

        Cache::put(
            $this->cacheKey($newToken->accessToken->id),
            $plain,
            $expiresAt ?? now()->addYears(5),
        );

        return $plain;
    }

    private function isValid(PersonalAccessToken $token): bool
    {
        $expiration = config('sanctum.expiration');

        if ($expiration && $token->created_at->lte(now()->subMinutes($expiration))) {
            return false;
        }

        return ! ($token->expires_at?->isPast() ?? false);
    }

    private function cacheKey(int $tokenId): string
    {
        return "api_token_plain:{$tokenId}";
    }

    private function expiresAt(): ?DateTimeInterface
    {
        $minutes = config('sanctum.expiration');

        return $minutes ? now()->addMinutes($minutes) : null;
    }
}

<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Counts the wrong secrets that come from each IP address, and refuses that address's writes once there
 * are too many. A secret is 32 random bytes, so guessing one is hopeless anyway: this keeps someone trying
 * from flooding the server, and makes it pointless to try a player's id with secret after secret.
 *
 * A right secret never clears the count, or anyone with a player of their own could clear it between
 * guesses.
 */
final class SecretGuessLimiter
{
    public function __construct(private readonly RateLimiterFactoryInterface $secretGuessLimiter)
    {
    }

    /** Requests that send a secret: those with a token, and every write, registrations included. */
    public static function sendsASecret(Request $request): bool
    {
        return $request->headers->has('Authorization') || !$request->isMethodSafe();
    }

    public function refuseIfBlocked(Request $request): void
    {
        $limit = $this->limiter($request)->consume(0);

        if (0 === $limit->getRemainingTokens()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsHttpException($retryAfter, 'Demasiadas claves incorrectas. Vuelve a intentarlo más tarde.');
        }
    }

    public function countWrongSecret(Request $request): void
    {
        $this->limiter($request)->consume();
    }

    private function limiter(Request $request): LimiterInterface
    {
        return $this->secretGuessLimiter->create($request->getClientIp() ?? 'unknown');
    }
}

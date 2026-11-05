<?php

namespace App\EventSubscriber;

use App\Security\SecretGuessLimiter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/** Counts wrong tokens, and turns away an address with too many before the firewall checks another. */
final class SecretGuessSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SecretGuessLimiter $limiter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before the firewall (priority 8), so a blocked address gets no answer about its token.
            KernelEvents::REQUEST => ['onRequest', 16],
            LoginFailureEvent::class => 'onWrongToken',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && SecretGuessLimiter::sendsASecret($request)) {
            $this->limiter->refuseIfBlocked($request);
        }
    }

    public function onWrongToken(LoginFailureEvent $event): void
    {
        $this->limiter->countWrongSecret($event->getRequest());
    }
}

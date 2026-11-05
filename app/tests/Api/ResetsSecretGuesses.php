<?php

namespace App\Tests\Api;

/** The count of wrong secrets outlives a test (it is in the cache): each test starts with none. */
trait ResetsSecretGuesses
{
    private function resetSecretGuesses(): void
    {
        static::getContainer()->get('limiter.secret_guess')->create('127.0.0.1')->reset();
    }
}

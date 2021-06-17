<?php

namespace Daanra\LaravelLetsEncrypt\Support;

use Daanra\LaravelLetsEncrypt\Contracts\PathGenerator;

class DefaultPathGenerator implements PathGenerator
{
    public function getChallengePath(string $token, string $domain): string
    {
        return $domain . '/.well-known/acme-challenge/' . $token;
    }

    public function getCertificatePath(string $domain, string $filename): string
    {
        return $domain . '/ssl/' . $filename;
    }
}

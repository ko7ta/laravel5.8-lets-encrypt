<?php

namespace Daanra\LaravelLetsEncrypt\Jobs;

use AcmePhp\Core\AcmeClient;
use AcmePhp\Core\Protocol\AuthorizationChallenge;
use Daanra\LaravelLetsEncrypt\Models\LetsEncryptCertificate;
use Daanra\LaravelLetsEncrypt\Support\PathGeneratorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CleanUpChallenge implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    /** @var AuthorizationChallenge */
    protected $challenge;

    /** @var LetsEncryptCertificate */
    protected $certificate;

    public function __construct(AuthorizationChallenge $httpChallenge, LetsEncryptCertificate $certificate)
    {
        $this->challenge = $httpChallenge;
        $this->certificate = $certificate;
    }

    /**
     * Cleans up the HTTP challenge by removing the file. Should be called right after the challenge was approved.
     * @return void
     */
    public function handle()
    {
        $generator = PathGeneratorFactory::create();
        Storage::disk(config('lets_encrypt.challenge_disk'))
            ->delete($generator->getChallengePath($this->challenge->getToken(), $this->certificate->domain));
    }
}

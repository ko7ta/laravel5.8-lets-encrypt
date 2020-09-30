<?php

namespace Daanra\LaravelLetsEncrypt;

use AcmePhp\Core\AcmeClient;
use AcmePhp\Core\Http\SecureHttpClientFactory;
use AcmePhp\Ssl\Generator\KeyPairGenerator;
use AcmePhp\Ssl\KeyPair;
use AcmePhp\Ssl\PrivateKey;
use AcmePhp\Ssl\PublicKey;
use Daanra\LaravelLetsEncrypt\Exceptions\DomainAlreadyExists;
use Daanra\LaravelLetsEncrypt\Exceptions\InvalidDomainException;
use Daanra\LaravelLetsEncrypt\Exceptions\InvalidKeyPairConfiguration;
use Daanra\LaravelLetsEncrypt\Jobs\RegisterAccount;
use Daanra\LaravelLetsEncrypt\Jobs\RequestAuthorization;
use Daanra\LaravelLetsEncrypt\Jobs\RequestCertificate;
use Daanra\LaravelLetsEncrypt\Models\LetsEncryptCertificate;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LetsEncrypt
{
    /** @var \AcmePhp\Core\Http\SecureHttpClientFactory */
    protected $factory;

    /**
     * LetsEncrypt constructor.
     * @param SecureHttpClientFactory $factory
     */
    public function __construct(SecureHttpClientFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Creates a new certificate. The heavy work is pushed on the queue.
     * @param string $domain
     * @return PendingDispatch
     * @throws DomainAlreadyExists
     * @throws InvalidDomainException
     */
    public function create(string $domain): PendingDispatch
    {
        $this->validateDomain($domain);
        $this->checkDomainDoesNotExist($domain);

        $email = config('lets_encrypt.universal_email_address');

        return RegisterAccount::withChain([
            new RequestAuthorization($domain),
            new RequestCertificate($domain),
        ])->dispatch($email);
    }

    /**
     * Creates a certificate synchronously: it's not pushed on the queue.
     * This is not recommended in general, but can be useful if you're running it from the command
     * line or when you're trying to debug.
     * @param string $domain
     * @return LetsEncryptCertificate
     * @throws DomainAlreadyExists
     * @throws InvalidDomainException
     */
    public function createNow(string $domain): LetsEncryptCertificate
    {
        $this->validateDomain($domain);
        $this->checkDomainDoesNotExist($domain);

        $email = config('lets_encrypt.universal_email_address');

         RegisterAccount::dispatchNow($email);
         RequestAuthorization::dispatchNow($domain);
         RequestCertificate::dispatchNow($domain);

         return LetsEncryptCertificate::create([
             'domain' => $domain,
         ]);
    }

    /**
     * Checks mainly to prevent API errors when a user passes e.g. 'https://domain.com' as a domain. This should be
     * 'domain.com' instead.
     * @param string $domain
     * @throws InvalidDomainException
     */
    public function validateDomain(string $domain): void
    {
        if (Str::contains($domain, [':', '/', ','])) {
            throw new InvalidDomainException($domain);
        }
    }

    /**
     * @param string $domain
     * @throws DomainAlreadyExists
     */
    public function checkDomainDoesNotExist(string $domain): void
    {
        if (LetsEncryptCertificate::withTrashed()->where('domain', $domain)->exists()) {
            throw new DomainAlreadyExists($domain);
        }
    }

    public function renew(string $domain): PendingDispatch
    {
        if (Str::contains($domain, [':', '/', ','])) {
            throw new InvalidDomainException($domain);
        }

        $email = config('lets_encrypt.universal_email_address', null);

        return RegisterAccount::withChain([
            new RequestAuthorization($domain),
            new RequestCertificate($domain),
        ])->dispatch($email);
    }

    /**
     * @return AcmeClient
     * @throws InvalidKeyPairConfiguration
     */
    public function createClient(): AcmeClient
    {
        $keyPair = $this->getKeyPair();
        $secureHttpClient = $this->factory->createSecureHttpClient($keyPair);

        return new AcmeClient(
            $secureHttpClient,
            config('lets_encrypt.api_url', 'https://acme-staging-v02.api.letsencrypt.org/directory')
        );
    }

    /**
     * Retrieves a key pair or creates a new one if it does not exist.
     * @return KeyPair
     * @throws InvalidKeyPairConfiguration
     */
    protected function getKeyPair(): KeyPair
    {
        $publicKeyPath = config('lets_encrypt.public_key_path', storage_path('app/lets-encrypt/keys/account.pub.pem'));
        $privateKeyPath = config('lets_encrypt.private_key_path', storage_path('app/lets-encrypt/keys/account.pem'));

        if (! file_exists($privateKeyPath) && ! file_exists($publicKeyPath)) {
            $keyPairGenerator = new KeyPairGenerator();
            $keyPair = $keyPairGenerator->generateKeyPair();

            File::ensureDirectoryExists(File::dirname($publicKeyPath));
            File::ensureDirectoryExists(File::dirname($privateKeyPath));

            file_put_contents($publicKeyPath, $keyPair->getPublicKey()->getPEM());
            file_put_contents($privateKeyPath, $keyPair->getPrivateKey()->getPEM());

            return $keyPair;
        }

        if (! file_exists($privateKeyPath)) {
            throw new InvalidKeyPairConfiguration('Private key does not exist but public key does.');
        }

        if (! file_exists($publicKeyPath)) {
            throw new InvalidKeyPairConfiguration('Public key does not exist but private key does.');
        }

        $publicKey = new PublicKey(file_get_contents($publicKeyPath));
        $privateKey = new PrivateKey(file_get_contents($privateKeyPath));

        return new KeyPair($publicKey, $privateKey);
    }
}

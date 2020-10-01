<?php

namespace Daanra\LaravelLetsEncrypt\Tests\Facades;

use Daanra\LaravelLetsEncrypt\Exceptions\InvalidDomainException;
use Daanra\LaravelLetsEncrypt\Facades\LetsEncrypt;
use Daanra\LaravelLetsEncrypt\Jobs\RegisterAccount;
use Daanra\LaravelLetsEncrypt\Jobs\RequestAuthorization;
use Daanra\LaravelLetsEncrypt\Jobs\RequestCertificate;
use Daanra\LaravelLetsEncrypt\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

class LetsEncryptTest extends TestCase
{
    /** @test */
    public function test_can_create_now()
    {
        Bus::fake();

        $certificate = \Daanra\LaravelLetsEncrypt\Facades\LetsEncrypt::createNow('somedomain.com');
        $this->assertEquals('somedomain.com', $certificate->domain);

        Bus::assertDispatched(RegisterAccount::class);
    }

    /** @test */
    public function test_can_create()
    {
        Queue::fake();

        [$certificate, $pendingDispatch] = \Daanra\LaravelLetsEncrypt\Facades\LetsEncrypt::create('someotherdomain.com');

        $this->assertEquals('someotherdomain.com', $certificate->domain);

        Queue::assertPushedWithChain(RegisterAccount::class, [
            RequestAuthorization::class,
            RequestCertificate::class,
        ]);
    }

    public function test_invalid_domain()
    {
        $this->expectException(InvalidDomainException::class);
        LetsEncrypt::validateDomain('https://mydomain.com');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test_is_valid_domain()
    {
        LetsEncrypt::validateDomain('test-some-domain.company');
        LetsEncrypt::validateDomain('google.com');
        LetsEncrypt::validateDomain('test.test.test.dev');
    }
}

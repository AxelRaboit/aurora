<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Editorial\Captcha;

use Aurora\Core\Encryption\Service\EncryptionServiceInterface;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Editorial\Captcha\CaptchaSettingEnum;
use Aurora\Module\Editorial\Captcha\CaptchaSettings;
use Aurora\Module\Editorial\Captcha\CaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The check that decides whether a stranger's comment is read at all.
 *
 * Three behaviours are worth pinning, and two of them are refusals people
 * disagree about: a missing token is a refusal, and a provider that cannot be
 * reached is not. The second is the one that would otherwise get "fixed" into
 * closing every client's comment form the next time Cloudflare has an
 * afternoon.
 */
final class CaptchaVerifierTest extends TestCase
{
    public function testAnUnconfiguredSiteAsksNothing(): void
    {
        $verifier = $this->verifier([], new MockHttpClient(static function (): MockResponse {
            self::fail('an unconfigured site must not call anybody');
        }));

        self::assertTrue($verifier->verify(null, '203.0.113.1'));
    }

    public function testAMissingTokenIsRefused(): void
    {
        $verifier = $this->verifier($this->configured(), new MockHttpClient(static function (): MockResponse {
            self::fail('a submission with no token needs no round trip to refuse');
        }));

        self::assertFalse($verifier->verify(null, '203.0.113.1'));
        self::assertFalse($verifier->verify('   ', '203.0.113.1'));
    }

    public function testAValidTokenPasses(): void
    {
        $verifier = $this->verifier(
            $this->configured(),
            new MockHttpClient(new MockResponse(json_encode(['success' => true]))),
        );

        self::assertTrue($verifier->verify('a-token', '203.0.113.1'));
    }

    public function testAnInvalidTokenIsRefused(): void
    {
        $verifier = $this->verifier(
            $this->configured(),
            new MockHttpClient(new MockResponse(json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]))),
        );

        self::assertFalse($verifier->verify('a-token', '203.0.113.1'));
    }

    /** reCAPTCHA verifies the token and then says how human it looked. */
    public function testALowRecaptchaScoreIsRefused(): void
    {
        $settings = $this->configured();
        $settings[CaptchaSettingEnum::Provider->value] = 'recaptcha';

        $verifier = $this->verifier(
            $settings,
            new MockHttpClient(new MockResponse(json_encode(['success' => true, 'score' => 0.1]))),
        );

        self::assertFalse($verifier->verify('a-token', '203.0.113.1'));
    }

    public function testAGoodRecaptchaScorePasses(): void
    {
        $settings = $this->configured();
        $settings[CaptchaSettingEnum::Provider->value] = 'recaptcha';

        $verifier = $this->verifier(
            $settings,
            new MockHttpClient(new MockResponse(json_encode(['success' => true, 'score' => 0.9]))),
        );

        self::assertTrue($verifier->verify('a-token', '203.0.113.1'));
    }

    /**
     * The deliberate one. This check sits on top of a honeypot, a link
     * filter, a flood limit and moderation; a provider outage must not close
     * the form those four are still guarding.
     */
    public function testAProviderThatCannotBeReachedLetsTheCommentThrough(): void
    {
        $verifier = $this->verifier(
            $this->configured(),
            new MockHttpClient(new MockResponse('', ['error' => 'connection refused'])),
        );

        self::assertTrue($verifier->verify('a-token', '203.0.113.1'));
    }

    /** @return array<string, string> */
    private function configured(): array
    {
        return [
            CaptchaSettingEnum::Enabled->value => '1',
            CaptchaSettingEnum::Provider->value => 'turnstile',
            CaptchaSettingEnum::SiteKey->value => 'site-key',
            CaptchaSettingEnum::SecretKey->value => 'encrypted-secret',
        ];
    }

    /** @param array<string, string> $stored */
    private function verifier(array $stored, MockHttpClient $client): CaptchaVerifier
    {
        $repository = $this->createStub(SettingRepository::class);
        $repository->method('get')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $stored[$key] ?? $default,
        );
        $repository->method('getBoolean')->willReturnCallback(
            static fn (string $key): bool => '1' === ($stored[$key] ?? '0'),
        );

        $encryption = $this->createStub(EncryptionServiceInterface::class);
        $encryption->method('decrypt')->willReturnCallback(
            static fn (string $value): string => 'decrypted-'.$value,
        );

        return new CaptchaVerifier(
            $client,
            new CaptchaSettings($repository, $encryption),
            new NullLogger(),
        );
    }
}

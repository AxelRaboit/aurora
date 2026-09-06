<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Pexels\Setting;

use Aurora\Core\Encryption\Service\EncryptionServiceInterface;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use Aurora\Module\Ged\Pexels\Setting\PexelsSettingEnum;
use Aurora\Module\Ged\Pexels\Setting\PexelsSettings;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The gate in front of a third-party account that belongs to the client.
 *
 * What is worth testing here is not that a checkbox persists, but that the
 * integration cannot end up switched on without somebody having agreed to
 * the terms it runs under.
 */
final class PexelsSettingsTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $store = [];

    private function settings(): PexelsSettings
    {
        $repository = $this->createStub(SettingRepository::class);
        $repository->method('get')->willReturnCallback(
            fn (string $key, ?string $default = null): ?string => $this->store[$key] ?? $default,
        );
        $repository->method('getBoolean')->willReturnCallback(
            fn (string $key, bool $default = false): bool => '1' === ($this->store[$key] ?? ($default ? '1' : '0')),
        );
        $repository->method('saveMany')->willReturnCallback(function (iterable $entries): void {
            foreach ($entries as [$key, $value]) {
                $this->store[$key] = $value;
            }
        });

        return new PexelsSettings($repository, $this->encryption());
    }

    /** Reversible and obviously not real, so a leaked ciphertext in a failure message is harmless. */
    private function encryption(): EncryptionServiceInterface
    {
        return new class implements EncryptionServiceInterface {
            public function encrypt(string $plaintext): string
            {
                return base64_encode($plaintext);
            }

            public function decrypt(string $encoded): ?string
            {
                $decoded = base64_decode($encoded, strict: true);

                return false === $decoded ? null : $decoded;
            }
        };
    }

    /** A fresh install must not talk to anyone before being asked to. */
    public function testItIsOffOnAFreshInstall(): void
    {
        $settings = $this->settings();

        self::assertFalse($settings->isEnabled());
        self::assertSame(
            ['enabled' => false, 'hasKey' => false, 'acceptedAt' => null, 'acceptedBy' => null],
            $settings->state(),
        );
    }

    public function testTheKeyIsStoredEncryptedAndReadBack(): void
    {
        $settings = $this->settings();
        $settings->save(enabled: true, termsAccepted: true, apiKey: 's3cret', acceptedBy: 'axel@example.com');

        self::assertSame('s3cret', $settings->apiKey());
        // What lands in the table is not the key.
        self::assertNotSame('s3cret', $this->store[PexelsSettingEnum::ApiKey->value]);
        self::assertTrue($settings->isEnabled());
    }

    /**
     * The three conditions are not interchangeable. A key on its own means
     * nobody agreed to anything, and an agreement on its own has nothing to
     * search with.
     */
    public function testEnablingNeedsBothAnAcceptanceAndAKey(): void
    {
        $settings = $this->settings();

        $settings->save(enabled: true, termsAccepted: false, apiKey: 'k', acceptedBy: 'axel@example.com');
        self::assertFalse($settings->isEnabled(), 'No acceptance, no integration.');

        $settings->save(enabled: true, termsAccepted: true, apiKey: '', acceptedBy: 'axel@example.com');
        self::assertFalse($settings->isEnabled(), 'No key, nothing to search with.');
    }

    /** Who agreed and when, because that is the point of recording it at all. */
    public function testTheAcceptanceIsRecordedWithADateAndAName(): void
    {
        $settings = $this->settings();
        $settings->save(enabled: true, termsAccepted: true, apiKey: 'k', acceptedBy: 'axel@example.com');

        $state = $settings->state();
        self::assertSame('axel@example.com', $state['acceptedBy']);
        self::assertNotNull($state['acceptedAt']);
        self::assertInstanceOf(DateTimeImmutable::class, new DateTimeImmutable((string) $state['acceptedAt']));
    }

    /**
     * Changing the key later is not a second acceptance. Overwriting the date
     * would quietly erase when the decision was actually taken.
     */
    public function testChangingTheKeyLaterDoesNotRewriteTheAcceptance(): void
    {
        $settings = $this->settings();
        $settings->save(enabled: true, termsAccepted: true, apiKey: 'first', acceptedBy: 'axel@example.com');
        $firstAcceptance = $settings->acceptedAt();

        $settings->save(enabled: true, termsAccepted: true, apiKey: 'second', acceptedBy: 'someone-else@example.com');

        self::assertSame($firstAcceptance, $settings->acceptedAt());
        self::assertSame('axel@example.com', $settings->acceptedBy());
        self::assertSame('second', $settings->apiKey());
    }

    /**
     * Withdrawing the acceptance switches it off in the same write. Leaving
     * it on would keep calling an API on behalf of someone who has just said
     * they no longer agree to its terms.
     */
    public function testWithdrawingTheAcceptanceSwitchesItOff(): void
    {
        $settings = $this->settings();
        $settings->save(enabled: true, termsAccepted: true, apiKey: 'k', acceptedBy: 'axel@example.com');

        $settings->save(enabled: true, termsAccepted: false, apiKey: null, acceptedBy: 'axel@example.com');

        self::assertFalse($settings->isEnabled());
        self::assertNull($settings->acceptedAt());
        self::assertNull($settings->acceptedBy());
    }

    /** The form never receives the key, so saving the rest of the tab must not wipe it. */
    public function testANullKeyLeavesTheStoredOneAlone(): void
    {
        $settings = $this->settings();
        $settings->save(enabled: true, termsAccepted: true, apiKey: 'kept', acceptedBy: 'axel@example.com');

        $settings->save(enabled: false, termsAccepted: true, apiKey: null, acceptedBy: 'axel@example.com');

        self::assertSame('kept', $settings->apiKey());
        self::assertFalse($settings->isEnabled());
        self::assertTrue($settings->state()['hasKey']);
    }

    /**
     * A key written before the encryption key was rotated cannot be read
     * back. The integration goes quiet instead of throwing on every page
     * that opens an image picker.
     */
    public function testAnUndecipherableKeyReadsAsAbsent(): void
    {
        $settings = $this->settings();
        $settings->save(enabled: true, termsAccepted: true, apiKey: 'k', acceptedBy: 'axel@example.com');

        $this->store[PexelsSettingEnum::ApiKey->value] = 'not base64 at all !!';

        self::assertSame('', $settings->apiKey());
        self::assertFalse($settings->isEnabled());
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Comment\Captcha;

use Aurora\Core\Encryption\Service\EncryptionServiceInterface;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use SensitiveParameter;

/**
 * Whether the comment form is checked against an anti-robot service, and with
 * whose keys.
 *
 * Off until somebody turns it on, and off by default on every site delivered.
 * The account behind the keys belongs to the client, like every other
 * third-party integration here: the check sends the reader's IP address to
 * Cloudflare or to Google, which is the client's decision to make and the
 * client's privacy notice to write, not mine.
 *
 * Enabled means all three at once - the toggle, a site key and a secret. Two
 * out of three is a form that cannot be submitted at all, and a check that
 * cannot pass is worse than no check.
 *
 * The secret is encrypted at rest, like the Pexels key. The site key is not:
 * it is printed in the page by design.
 */
final readonly class CaptchaSettings
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EncryptionServiceInterface $encryption,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settingRepository->getBoolean(CaptchaSettingEnum::Enabled->value)
            && '' !== $this->siteKey()
            && '' !== $this->secretKey();
    }

    public function provider(): CaptchaProviderEnum
    {
        $stored = (string) $this->settingRepository->get(CaptchaSettingEnum::Provider->value, '');

        return CaptchaProviderEnum::tryFrom($stored) ?? CaptchaProviderEnum::Turnstile;
    }

    public function siteKey(): string
    {
        return mb_trim((string) $this->settingRepository->get(CaptchaSettingEnum::SiteKey->value, ''));
    }

    public function secretKey(): string
    {
        $stored = (string) $this->settingRepository->get(CaptchaSettingEnum::SecretKey->value, '');

        if ('' === $stored) {
            return '';
        }

        // A secret written before the encryption key was rotated decrypts to
        // null. Treated as absent rather than fatal: the check goes quiet and
        // the tab asks for the key again, which is the one thing that fixes
        // it. Quiet here means comments keep working - a rotated key must not
        // close the form.
        return $this->encryption->decrypt($stored) ?? '';
    }

    /**
     * What the public page is allowed to know: enough to draw the widget,
     * and nothing else.
     *
     * @return array{enabled: bool, provider: string, siteKey: string, scriptUrl: string}
     */
    public function publicView(): array
    {
        $enabled = $this->isEnabled();
        $provider = $this->provider();

        return [
            'enabled' => $enabled,
            'provider' => $provider->value,
            'siteKey' => $enabled ? $this->siteKey() : '',
            'scriptUrl' => $enabled ? $provider->scriptUrl($this->siteKey()) : '',
        ];
    }

    /**
     * What the settings tab is allowed to know: whether a secret is set, not
     * what it is.
     *
     * @return array{enabled: bool, provider: string, siteKey: string, hasSecret: bool}
     */
    public function adminView(): array
    {
        return [
            'enabled' => $this->settingRepository->getBoolean(CaptchaSettingEnum::Enabled->value),
            'provider' => $this->provider()->value,
            'siteKey' => $this->siteKey(),
            'hasSecret' => '' !== $this->secretKey(),
        ];
    }

    /**
     * @param string|null $secretKey null leaves the stored secret alone -
     *                               which is how a form that never received
     *                               it saves the rest of the tab. An empty
     *                               string is an explicit "forget it".
     */
    public function save(
        bool $enabled,
        CaptchaProviderEnum $provider,
        string $siteKey,
        #[SensitiveParameter]
        ?string $secretKey,
    ): void {
        $entries = [
            [CaptchaSettingEnum::Enabled->value, $enabled ? '1' : '0'],
            [CaptchaSettingEnum::Provider->value, $provider->value],
            [CaptchaSettingEnum::SiteKey->value, '' === $siteKey ? null : $siteKey],
        ];

        if (null !== $secretKey) {
            $entries[] = [
                CaptchaSettingEnum::SecretKey->value,
                '' === $secretKey ? null : $this->encryption->encrypt($secretKey),
            ];
        }

        $this->settingRepository->saveMany($entries);
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Pexels\Setting;

use Aurora\Core\Encryption\Service\EncryptionServiceInterface;
use Aurora\Module\Configuration\Setting\Repository\SettingRepository;
use DateTimeImmutable;
use SensitiveParameter;

/**
 * Whether this installation may talk to Pexels, and with whose key.
 *
 * Off until somebody turns it on. Aurora is delivered to clients, and the
 * account behind the key is theirs: Pexels holds the account holder
 * responsible for what is done with the photos, so the person who accepts
 * that has to be the person who owns the site, not the person who installed
 * it. That is why enabling requires three things at once - a key, a
 * deliberate acceptance of the terms, and the toggle - and why the
 * acceptance is stored with a date and a name rather than as a bare flag.
 *
 * The key is encrypted at rest with {@see EncryptionServiceInterface}, the
 * same treatment mount point passwords get. It is a low-value credential -
 * read-only access to a stock photo search - but a database dump is a
 * database dump, and a key that leaks is a key the client has to rotate.
 */
final readonly class PexelsSettings
{
    public function __construct(
        private SettingRepository $settingRepository,
        private EncryptionServiceInterface $encryption,
    ) {}

    /**
     * The one question the rest of the code asks.
     *
     * All three conditions, every time: a key without an acceptance means
     * nobody agreed to anything, and an acceptance without a key means
     * nothing can be searched. Either way the tab must stay shut.
     */
    public function isEnabled(): bool
    {
        return $this->settingRepository->getBoolean(PexelsSettingEnum::Enabled->value)
            && null !== $this->acceptedAt()
            && '' !== $this->apiKey();
    }

    public function apiKey(): string
    {
        $stored = (string) $this->settingRepository->get(PexelsSettingEnum::ApiKey->value, '');

        if ('' === $stored) {
            return '';
        }

        // A key written before the encryption key was rotated decrypts to
        // null. Treated as absent rather than fatal: the integration goes
        // quiet and the settings tab asks for the key again, which is the
        // one thing that fixes it.
        return $this->encryption->decrypt($stored) ?? '';
    }

    public function acceptedAt(): ?string
    {
        $value = (string) $this->settingRepository->get(PexelsSettingEnum::TermsAcceptedAt->value, '');

        return '' !== $value ? $value : null;
    }

    public function acceptedBy(): ?string
    {
        $value = (string) $this->settingRepository->get(PexelsSettingEnum::TermsAcceptedBy->value, '');

        return '' !== $value ? $value : null;
    }

    /**
     * What the settings screen is allowed to know.
     *
     * `hasKey` rather than the key: the browser needs to draw "a key is
     * saved" and nothing more. Sending the key back so a form can pre-fill
     * it would put it in the page source of every admin who opens the tab.
     *
     * @return array{enabled: bool, hasKey: bool, acceptedAt: string|null, acceptedBy: string|null}
     */
    public function state(): array
    {
        return [
            'enabled' => $this->settingRepository->getBoolean(PexelsSettingEnum::Enabled->value),
            'hasKey' => '' !== $this->apiKey(),
            'acceptedAt' => $this->acceptedAt(),
            'acceptedBy' => $this->acceptedBy(),
        ];
    }

    /**
     * @param string|null $apiKey null leaves the stored key alone - which is
     *                            how a form that never received it can save
     *                            the rest of the tab. An empty string is an
     *                            explicit "forget it".
     */
    public function save(
        bool $enabled,
        bool $termsAccepted,
        #[SensitiveParameter]
        ?string $apiKey,
        string $acceptedBy,
    ): void {
        $entries = [];

        if (null !== $apiKey) {
            $entries[] = [
                PexelsSettingEnum::ApiKey->value,
                '' === $apiKey ? null : $this->encryption->encrypt($apiKey),
            ];
        }

        if (!$termsAccepted) {
            // Withdrawing the acceptance switches the integration off in the
            // same write. Leaving it on would keep calling an API on behalf
            // of someone who has just said they no longer agree to its terms.
            $entries[] = [PexelsSettingEnum::TermsAcceptedAt->value, null];
            $entries[] = [PexelsSettingEnum::TermsAcceptedBy->value, null];
            $entries[] = [PexelsSettingEnum::Enabled->value, '0'];

            $this->settingRepository->saveMany($entries);

            return;
        }

        // Recorded the first time only. Re-saving the tab to change the key
        // is not a new acceptance, and overwriting the date would erase when
        // the decision was actually made.
        if (null === $this->acceptedAt()) {
            $entries[] = [
                PexelsSettingEnum::TermsAcceptedAt->value,
                new DateTimeImmutable()->format(DateTimeImmutable::ATOM),
            ];
            $entries[] = [PexelsSettingEnum::TermsAcceptedBy->value, $acceptedBy];
        }

        $entries[] = [PexelsSettingEnum::Enabled->value, $enabled ? '1' : '0'];

        $this->settingRepository->saveMany($entries);
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\R2;

use SensitiveParameter;

/**
 * What it takes to talk to one R2 bucket.
 *
 * A value object rather than five constructor arguments passed around,
 * because in phase 3 these stop coming from the environment and start coming
 * from the settings table, and the day that happens only
 * {@see R2ConfigurationProviderInterface} should have to change.
 *
 * `publicBaseUrl` is the odd one out: optional, and not a credential. It is
 * the hostname an administrator mapped onto the bucket, when they mapped one.
 * Without it the adapter simply has no public URL to offer.
 */
final readonly class R2Configuration
{
    public function __construct(
        public string $endpoint,
        public string $bucket,
        #[SensitiveParameter]
        public string $accessKeyId,
        #[SensitiveParameter]
        public string $secretAccessKey,
        public ?string $publicBaseUrl = null,
    ) {}

    public static function empty(): self
    {
        return new self('', '', '', '');
    }

    /**
     * Whether there is enough here to build a client at all.
     *
     * Four values, all required. A configuration missing one of them is not
     * "partly configured", it is off: attempting a call would produce a
     * signature error from Cloudflare that says nothing useful about which
     * field an administrator forgot.
     */
    public function isComplete(): bool
    {
        return !in_array('', [$this->endpoint, $this->bucket, $this->accessKeyId, $this->secretAccessKey], true);
    }

    /**
     * The fields that are missing, for a message a human can act on.
     *
     * @return list<string>
     */
    public function missingFields(): array
    {
        $missing = [];

        foreach ([
            'endpoint' => $this->endpoint,
            'bucket' => $this->bucket,
            'access key id' => $this->accessKeyId,
            'secret access key' => $this->secretAccessKey,
        ] as $label => $value) {
            if ('' === $value) {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Comment\Captcha;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Asks the configured service whether the token the browser sent is real.
 *
 * Two decisions worth naming.
 *
 * A submission with no token is refused when the check is on. The alternative
 * - letting it through - would mean anything that ignores the JavaScript gets
 * in, which is every robot and no reader.
 *
 * A service that does not answer lets the submission through. The check is
 * one layer over a honeypot, a link filter, a flood limit and moderation:
 * Cloudflare having a bad afternoon should not close a client's comment form,
 * and the failure is logged so it is not silent. This is the one place where
 * "fail open" is the right way round, and it is a choice rather than an
 * oversight.
 */
final readonly class CaptchaVerifier
{
    private const int TIMEOUT_SECONDS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CaptchaSettings $settings,
        private LoggerInterface $logger,
    ) {}

    public function verify(?string $token, ?string $ip): bool
    {
        if (!$this->settings->isEnabled()) {
            return true;
        }

        if (null === $token || '' === mb_trim($token)) {
            return false;
        }

        $provider = $this->settings->provider();

        try {
            $response = $this->httpClient->request('POST', $provider->verifyUrl(), [
                'body' => array_filter([
                    'secret' => $this->settings->secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ], static fn (?string $value): bool => null !== $value && '' !== $value),
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $payload = $response->toArray(false);
        } catch (Throwable $throwable) {
            $this->logger->warning('Captcha verification failed to reach the provider.', [
                'provider' => $provider->value,
                'exception' => $throwable->getMessage(),
            ]);

            return true;
        }

        if (true !== ($payload['success'] ?? false)) {
            return false;
        }

        // Turnstile answers pass or fail. reCAPTCHA v3 answers a score, and a
        // successful call with a low score is exactly what a robot looks
        // like: a token that verifies and behaviour that does not.
        if (CaptchaProviderEnum::Recaptcha === $provider && isset($payload['score'])) {
            return (float) $payload['score'] >= CaptchaProviderEnum::RECAPTCHA_THRESHOLD;
        }

        return true;
    }
}

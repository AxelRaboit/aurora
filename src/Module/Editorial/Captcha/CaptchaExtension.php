<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Captcha;

use Twig\Attribute\AsTwigFunction;

/**
 * What a public template needs to draw the anti-robot widget.
 *
 * A function rather than a global: only the templates that carry something a
 * robot can post ask for it, and a page with neither a comment thread nor a
 * form should not pay for a settings read.
 */
final readonly class CaptchaExtension
{
    public function __construct(
        private CaptchaSettings $settings,
    ) {}

    /** @return array{enabled: bool, provider: string, siteKey: string, scriptUrl: string} */
    #[AsTwigFunction(name: 'public_captcha')]
    public function publicCaptcha(): array
    {
        return $this->settings->publicView();
    }

    /**
     * The name this had when comments were the only thing it guarded. Kept
     * because a client's own theme may call it, and a theme is a file in
     * someone else's project - renaming it there is not something a release of
     * this package can do.
     *
     * @return array{enabled: bool, provider: string, siteKey: string, scriptUrl: string}
     */
    #[AsTwigFunction(name: 'comment_captcha')]
    public function commentCaptcha(): array
    {
        return $this->publicCaptcha();
    }
}

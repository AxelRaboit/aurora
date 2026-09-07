<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Comment\Captcha;

use Twig\Attribute\AsTwigFunction;

/**
 * What a public template needs to draw the anti-robot widget.
 *
 * A function rather than a global: the comment section is the only place that
 * asks, and a template that never renders comments should not pay for a
 * settings read on every page.
 */
final readonly class CaptchaExtension
{
    public function __construct(
        private CaptchaSettings $settings,
    ) {}

    /** @return array{enabled: bool, provider: string, siteKey: string, scriptUrl: string} */
    #[AsTwigFunction(name: 'comment_captcha')]
    public function commentCaptcha(): array
    {
        return $this->settings->publicView();
    }
}

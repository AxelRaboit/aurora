<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Dto;

use Aurora\Core\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function is_array;

#[AsAlias(ContractTemplateVersionInputFactoryInterface::class)]
class ContractTemplateVersionInputFactory implements ContractTemplateVersionInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): ContractTemplateVersionInputInterface
    {
        return new ContractTemplateVersionInput(
            translations: $this->translations($data['translations'] ?? null),
            governingLocale: Str::trimOrNull((string) ($data['governingLocale'] ?? '')),
        );
    }

    /**
     * A locale is kept when it carries a title.
     *
     * An empty title is how the editor says "I am not writing this language",
     * and keeping the row would publish a version whose Spanish document is a
     * blank page with a heading. The content is taken as it comes: what a
     * valid block document looks like is the editor's contract, and a
     * half-understood shape rewritten here would be worse than one stored
     * whole.
     *
     * @return array<string, array{title: string, content: array<string, mixed>}>
     */
    private function translations(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $translations = [];

        foreach ($raw as $locale => $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $title = Str::trimOrNull((string) ($payload['title'] ?? ''));

            if (null === $title) {
                continue;
            }

            $content = $payload['content'] ?? [];

            $translations[(string) $locale] = [
                'title' => $title,
                'content' => is_array($content) ? $content : [],
            ];
        }

        return $translations;
    }
}

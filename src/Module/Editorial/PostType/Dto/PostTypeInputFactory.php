<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\PostType\Dto;

use Aurora\Core\Support\Str;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PostTypeInputFactoryInterface::class)]
class PostTypeInputFactory implements PostTypeInputFactoryInterface
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): PostTypeInputInterface
    {
        return new PostTypeInput(
            slug: mb_strtolower(Str::trimOrNull((string) ($data['slug'] ?? '')) ?? ''),
            label: Str::trimOrNull((string) ($data['label'] ?? '')) ?? '',
            description: Str::trimOrNull((string) ($data['description'] ?? '')),
            icon: Str::trimOrNull((string) ($data['icon'] ?? '')),
            hasArchive: (bool) ($data['hasArchive'] ?? false),
            supports: $this->stringList($data['supports'] ?? null),
            archiveTitle: Str::trimOrNull((string) ($data['archiveTitle'] ?? '')),
            archivePostId: $this->id($data['archivePostId'] ?? null),
            // True unless the browser says otherwise: every archive that
            // predates the switch lists, and so does a new one.
            archiveShowsList: (bool) ($data['archiveShowsList'] ?? true),
        );
    }

    /** A positive id, or nothing: zero and "" both mean "no publication". */
    private function id(mixed $raw): ?int
    {
        $id = (int) (is_scalar($raw) ? $raw : 0);

        return $id > 0 ? $id : null;
    }

    /** @return list<string> */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => (string) $item, $raw)));
    }
}

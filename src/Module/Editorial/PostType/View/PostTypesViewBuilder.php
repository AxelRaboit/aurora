<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\PostType\View;

use Aurora\Core\Locale\Service\LocaleContext;
use Aurora\Module\Editorial\Post\Repository\PostRepository;
use Aurora\Module\Editorial\PostType\Entity\AbstractPostType;
use Aurora\Module\Editorial\PostType\Entity\AbstractPostTypeField;
use Aurora\Module\Editorial\PostType\Repository\PostTypeRepository;
use Aurora\Module\Editorial\PostType\Serializer\PostTypeSerializerInterface;

/**
 * Builds the Twig payload consumed by the admin post-types screen.
 */
final readonly class PostTypesViewBuilder
{
    public function __construct(
        private PostTypeRepository $postTypeRepository,
        private PostTypeSerializerInterface $postTypeSerializer,
        private PostRepository $postRepository,
        private LocaleContext $localeContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @return list<array{value: int, label: string}>
     */
    private function postOptions(): array
    {
        $options = [];

        foreach ($this->postRepository->findAllPublishedForPicker() as $post) {
            $id = $post->getId();

            if (null === $id) {
                continue;
            }

            $options[] = [
                'value' => $id,
                // The title in the site's own language, since that is the one
                // a back office reads in; an untranslated publication is named
                // by its id rather than by a blank line.
                'label' => $post->getTranslation($this->localeContext->getDefaultLocale())?->getTitle() ?? sprintf('#%d', $id),
            ];
        }

        return $options;
    }

    /**
     * The post type a bare `/post-types` should send the reader to, or null
     * when there is nothing to send them to.
     *
     * Asked of the builder rather than of a repository injected into the
     * controller: the builder already owns the ordering the menu and the page
     * both show, and "first" has to mean the same thing in all three.
     */
    public function firstId(): ?int
    {
        $postTypes = $this->postTypeRepository->findAllWithRelations();

        return [] === $postTypes ? null : $postTypes[0]->getId();
    }

    /**
     * @param ?int $activeId the post type the address names, null when there
     *                       are none to name
     */
    public function indexView(?int $activeId = null): array
    {
        return [
            'activeId' => $activeId,
            'postTypes' => array_map(
                $this->postTypeSerializer->serialize(...),
                $this->postTypeRepository->findAllWithRelations(),
            ),
            // The vocabularies live in PHP; handing them over keeps the Vue
            // form from carrying a second copy that can drift.
            'supportOptions' => AbstractPostType::SUPPORTS,
            'fieldTypes' => AbstractPostTypeField::TYPES,
            // The publications a listing page may borrow a header from. The
            // whole list rather than a search endpoint: this screen is opened
            // rarely and the picker it feeds searches its own options, so a
            // second round trip would buy nothing.
            'postOptions' => $this->postOptions(),
        ];
    }
}

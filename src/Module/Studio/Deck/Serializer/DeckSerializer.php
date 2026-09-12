<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Serializer;

use Aurora\Core\Storage\Enum\MimeGroupEnum;
use Aurora\Module\Ged\Document\Entity\DocumentInterface;
use Aurora\Module\Ged\Document\Repository\DocumentRepository;
use Aurora\Module\Ged\Document\Service\DocumentUrlGenerator;
use Aurora\Module\Studio\Customer\Entity\CustomerInterface;
use Aurora\Module\Studio\Deck\Entity\DeckCategoryInterface;
use Aurora\Module\Studio\Deck\Entity\DeckInterface;
use Aurora\Module\Studio\Deck\Entity\SlideInterface;

use function is_int;

use const DATE_ATOM;

class DeckSerializer
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentUrlGenerator $documentUrls,
    ) {}

    /**
     * A deck as the list shows it: no slides, a count instead.
     *
     * The list draws thirty rows and none of them shows a slide's contents.
     * Sending them would be the whole deck thirty times over for a number the
     * caller already has.
     *
     * @return array<string, mixed>
     */
    public function summary(DeckInterface $deck, int $slideCount = 0): array
    {
        return [
            'id' => $deck->getId(),
            'title' => $deck->getTitle(),
            'description' => $deck->getDescription(),
            'category' => $this->category($deck->getCategory()),
            'customer' => $deck->getCustomer() instanceof CustomerInterface ? [
                'id' => $deck->getCustomer()->getId(),
                'legalName' => $deck->getCustomer()->getLegalName(),
            ] : null,
            'slideCount' => $slideCount,
            'updatedAt' => $deck->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * A deck as its own page shows it: slides included, in order.
     *
     * @return array<string, mixed>
     */
    public function full(DeckInterface $deck): array
    {
        // The pictures resolved in one query rather than one per slide: a deck
        // of thirty slides is thirty round trips otherwise, for a handful of
        // ids that are known before the loop starts.
        $ids = [];
        foreach ($deck->getSlides() as $slide) {
            $id = $slide->getContent()['mediaId'] ?? null;
            if (is_int($id)) {
                $ids[] = $id;
            }
        }

        $pictures = [];
        foreach ([] === $ids ? [] : $this->documents->findBy(['id' => $ids]) as $document) {
            $pictures[(int) $document->getId()] = $document;
        }

        $slides = [];
        foreach ($deck->getSlides() as $slide) {
            $slides[] = $this->slide($slide, $pictures);
        }

        return [...$this->summary($deck, count($slides)), 'slides' => $slides];
    }

    /**
     * @param array<int, DocumentInterface> $pictures already-loaded documents, by id
     *
     * @return array<string, mixed>
     */
    public function slide(SlideInterface $slide, array $pictures = []): array
    {
        $content = $slide->getContent();
        $mediaId = $content['mediaId'] ?? null;

        if (is_int($mediaId)) {
            $document = $pictures[$mediaId] ?? $this->documents->find($mediaId);

            // `mediaUrl` and `mediaAlt` are derived, never stored: the manager
            // whitelists the content against the layout's slots and neither is
            // one, so a payload carrying them back is dropped rather than
            // persisted. The address of a picture changes when its file does,
            // and a copy of it in the slide would be a second truth to keep.
            $content['mediaUrl'] = $this->pictureUrl($document);
            $content['mediaAlt'] = $document?->getAlt() ?? '';
        }

        return [
            'id' => $slide->getId(),
            'layout' => $slide->getLayout()->value,
            'content' => $content,
            'speakerNotes' => $slide->getSpeakerNotes(),
            'position' => $slide->getPosition(),
        ];
    }

    /**
     * The address of a picture, or null when there is nothing to draw.
     *
     * The mime check is not ceremony: the picker only ever offers images, but a
     * fixture, an API write, or a document whose file is replaced after the
     * slide was composed all reach past it, and an `<img>` pointed at a PDF is
     * a broken image with nothing said anywhere.
     */
    private function pictureUrl(?DocumentInterface $document): ?string
    {
        if (!$document instanceof DocumentInterface) {
            return null;
        }

        if (!MimeGroupEnum::Image->matches($document->getMimeType())) {
            return null;
        }

        return $this->documentUrls->variantUrl($document, 'large')
            ?? $this->documentUrls->publicUrl($document);
    }

    /** @return array<string, mixed>|null */
    public function category(?DeckCategoryInterface $category): ?array
    {
        if (!$category instanceof DeckCategoryInterface) {
            return null;
        }

        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'color' => $category->getColor(),
            'position' => $category->getPosition(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Serializer;

use Aurora\Module\Studio\Customer\Entity\CustomerInterface;
use Aurora\Module\Studio\Deck\Entity\DeckCategoryInterface;
use Aurora\Module\Studio\Deck\Entity\DeckInterface;
use Aurora\Module\Studio\Deck\Entity\SlideInterface;

use const DATE_ATOM;

class DeckSerializer
{
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
        $slides = [];

        foreach ($deck->getSlides() as $slide) {
            $slides[] = $this->slide($slide);
        }

        return [...$this->summary($deck, count($slides)), 'slides' => $slides];
    }

    /** @return array<string, mixed> */
    public function slide(SlideInterface $slide): array
    {
        return [
            'id' => $slide->getId(),
            'layout' => $slide->getLayout()->value,
            'content' => $slide->getContent(),
            'speakerNotes' => $slide->getSpeakerNotes(),
            'position' => $slide->getPosition(),
        ];
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

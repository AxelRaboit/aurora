<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Entity;

use Aurora\Core\Timestampable\TimestampableInterface;
use Aurora\Module\Studio\Customer\Entity\CustomerInterface;
use Doctrine\Common\Collections\Collection;

interface DeckInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getTitle(): string;

    public function setTitle(string $title): static;

    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    public function getCategory(): ?DeckCategoryInterface;

    public function setCategory(?DeckCategoryInterface $category): static;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): static;

    /** @return Collection<int, SlideInterface> */
    public function getSlides(): Collection;

    public function addSlide(SlideInterface $slide): static;

    public function removeSlide(SlideInterface $slide): static;
}

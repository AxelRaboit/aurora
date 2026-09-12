<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Entity;

use Aurora\Module\Studio\Deck\Repository\DeckCategoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeckCategoryRepository::class)]
#[ORM\Table(name: 'core_deck_categories')]
class DeckCategory extends AbstractDeckCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_deck_category_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

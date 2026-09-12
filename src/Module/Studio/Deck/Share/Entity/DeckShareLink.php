<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Share\Entity;

use Aurora\Module\Studio\Deck\Share\Repository\DeckShareLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeckShareLinkRepository::class)]
#[ORM\Table(name: 'core_deck_share_links')]
class DeckShareLink extends AbstractDeckShareLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_deck_share_link_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

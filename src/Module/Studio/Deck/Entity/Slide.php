<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Entity;

use Aurora\Module\Studio\Deck\Repository\SlideRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SlideRepository::class)]
#[ORM\Table(name: 'core_deck_slides')]
class Slide extends AbstractSlide
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_deck_slide_id', allocationSize: 1)]
    #[ORM\Column]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

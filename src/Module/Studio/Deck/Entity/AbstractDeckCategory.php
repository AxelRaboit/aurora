<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Deck\Entity;

use Aurora\Core\Timestampable\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a deck is filed under: audit, strategy, onboarding.
 *
 * The module carries its own categories rather than borrowing Editorial's
 * taxonomies, which are bound to post types by a many-to-many and would tie a
 * deck to the publication system to express "this one is an audit". The GED
 * made the same call with its document categories, and it is the call the
 * module system exists to allow.
 *
 * A colour, because a list of thirty decks is scanned rather than read, and a
 * category one can see is a filter one does not have to apply.
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractDeckCategory implements DeckCategoryInterface
{
    use TimestampableTrait;

    #[ORM\Column(length: 100)]
    protected string $name = '';

    /** Hex colour used to tint the badge, e.g. `#6366f1`. */
    #[ORM\Column(length: 7, nullable: true)]
    protected ?string $color = null;

    /** Ordered by hand: a reader files by habit, not alphabetically. */
    #[ORM\Column(options: ['default' => 0])]
    protected int $position = 0;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}

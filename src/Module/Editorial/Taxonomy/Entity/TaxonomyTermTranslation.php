<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\Taxonomy\Entity;

use Aurora\Module\Editorial\Taxonomy\Repository\TaxonomyTermTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxonomyTermTranslationRepository::class)]
#[ORM\Table(name: 'core_taxonomy_term_translations')]
#[ORM\UniqueConstraint(name: 'uniq_taxonomy_term_translation_locale', columns: ['term_id', 'locale'])]
// A term slug is a public URL segment, and the route that reads it names the
// taxonomy too: `/{locale}/{taxonomySlug}/{termSlug}`. So the address only has
// to be unique inside its taxonomy - a tag and a documentation rubric may both
// be called "Éditorial", and refusing that refused legitimate content.
#[ORM\UniqueConstraint(name: 'uniq_term_taxonomy_locale_slug', columns: ['taxonomy_id', 'locale', 'slug'])]
class TaxonomyTermTranslation extends AbstractTaxonomyTermTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'seq_core_taxonomy_term_translation_id', allocationSize: 1)]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}

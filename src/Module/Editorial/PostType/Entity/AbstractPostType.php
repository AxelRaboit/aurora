<?php

declare(strict_types=1);

namespace Aurora\Module\Editorial\PostType\Entity;

use Aurora\Module\Editorial\Post\Entity\PostInterface;
use Aurora\Module\Editorial\Taxonomy\Entity\TaxonomyInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractPostType implements PostTypeInterface
{
    /**
     * What a post of this type can carry. `blocks` gives it a body in the
     * block editor, `thumbnail` a featured image. Only capabilities with
     * something behind them belong here - an entry nothing reads is a
     * checkbox that lies to the editor.
     */
    public const array SUPPORTS = ['blocks', 'thumbnail'];

    #[ORM\Column(length: 100, unique: true)]
    protected string $slug;

    #[ORM\Column(length: 100)]
    protected string $label;

    #[ORM\Column(length: 50, nullable: true)]
    protected ?string $icon = null;

    /**
     * What this type of content is for, in the author's own words.
     *
     * Nullable because every post type that exists predates the column, and a
     * required sentence would have to be invented for each of them on the way
     * in. Where it is empty the side menu says what the type holds instead - a
     * count is a fact, and a fact beats a blank line under a name.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column]
    protected bool $hasArchive = false;

    /**
     * What the listing page calls itself.
     *
     * The label names one thing - a publication *is* a Service - and the page
     * that lists them all is called Services. Reusing the label there printed
     * the singular as a page title, which is the kind of small wrongness a
     * reader notices before anything else on the page.
     *
     * Nullable and falling back to the label: every type that exists predates
     * this, and "Service" is a better heading than a blank one.
     */
    #[ORM\Column(length: 150, nullable: true)]
    protected ?string $archiveTitle = null;

    /**
     * The publication whose header this listing page borrows.
     *
     * Not a picture of its own, deliberately. A banner here would be a second
     * banner: same name as the real one, none of its settings - no height, no
     * darkening, no alignment, no buttons, and no words per language - and set
     * on the screen that describes the *structure* of the site rather than in
     * the editor where every other visual decision is made. Every one of those
     * settings already exists on a publication, so the listing page points at
     * one and gets all of it.
     *
     * The publication keeps an address of its own, so two URLs show the same
     * header; `noindex` on that one settles it, and it is already a field in
     * its Search engines tab.
     *
     * An id rather than a relation, which is the one thing here that looks
     * wrong and is not: `core_posts` already points at `core_post_types`, and
     * pointing back would close a circular foreign key that the fixtures
     * purger cannot untangle - it truncates in dependency order, and a cycle
     * has none. So this is the arrangement the homepage already uses: a
     * publication named by its id, resolved on the way out and refused when
     * it has gone, is unpublished, or says nothing in the language being read.
     */
    #[ORM\Column(nullable: true)]
    protected ?int $archivePostId = null;

    /**
     * Set on the types the bootstrap creates (page, article). Built-in
     * types cannot be deleted and keep their slug, since routes and
     * content already point at them.
     */
    #[ORM\Column]
    protected bool $isBuiltIn = false;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    protected array $supports = self::SUPPORTS;

    /** @var Collection<int, PostTypeFieldInterface> */
    #[ORM\OneToMany(targetEntity: PostTypeFieldInterface::class, mappedBy: 'postType', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => Order::Ascending->value])]
    protected Collection $fields;

    /**
     * Mapped on the concrete class: the owning side of a ManyToMany needs a
     * join table, and a MappedSuperclass may not declare one.
     *
     * @var Collection<int, TaxonomyInterface>
     */
    protected Collection $taxonomies;

    /** @var Collection<int, PostInterface> */
    #[ORM\OneToMany(targetEntity: PostInterface::class, mappedBy: 'postType')]
    protected Collection $posts;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
        $this->taxonomies = new ArrayCollection();
        $this->posts = new ArrayCollection();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function hasArchive(): bool
    {
        return $this->hasArchive;
    }

    public function getArchiveTitle(): ?string
    {
        return $this->archiveTitle;
    }

    public function setArchiveTitle(?string $archiveTitle): static
    {
        $this->archiveTitle = $archiveTitle;

        return $this;
    }

    /** The title of the listing page, or the label when none was written. */
    public function getArchiveHeading(): string
    {
        return null !== $this->archiveTitle && '' !== $this->archiveTitle
            ? $this->archiveTitle
            : $this->label;
    }

    public function getArchivePostId(): ?int
    {
        return $this->archivePostId;
    }

    public function setArchivePostId(?int $archivePostId): static
    {
        $this->archivePostId = $archivePostId;

        return $this;
    }

    public function setHasArchive(bool $hasArchive): static
    {
        $this->hasArchive = $hasArchive;

        return $this;
    }

    public function isBuiltIn(): bool
    {
        return $this->isBuiltIn;
    }

    public function setIsBuiltIn(bool $isBuiltIn): static
    {
        $this->isBuiltIn = $isBuiltIn;

        return $this;
    }

    public function getSupports(): array
    {
        return $this->supports;
    }

    public function setSupports(array $supports): static
    {
        $this->supports = array_values(array_intersect(self::SUPPORTS, $supports));

        return $this;
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports, true);
    }

    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function findFieldById(int $fieldId): ?PostTypeFieldInterface
    {
        foreach ($this->fields as $field) {
            if ($field->getId() === $fieldId) {
                return $field;
            }
        }

        return null;
    }

    public function addField(PostTypeFieldInterface $field): static
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setPostType($this);
        }

        return $this;
    }

    public function removeField(PostTypeFieldInterface $field): static
    {
        $this->fields->removeElement($field);

        return $this;
    }

    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getTaxonomies(): Collection
    {
        return $this->taxonomies;
    }

    /**
     * Owning side of the association. Mirrors onto the taxonomy so both
     * collections agree in memory, not only after a reload.
     */
    public function addTaxonomy(TaxonomyInterface $taxonomy): static
    {
        if (!$this->taxonomies->contains($taxonomy)) {
            $this->taxonomies->add($taxonomy);
            $taxonomy->addPostType($this);
        }

        return $this;
    }

    public function removeTaxonomy(TaxonomyInterface $taxonomy): static
    {
        if ($this->taxonomies->removeElement($taxonomy)) {
            $taxonomy->removePostType($this);
        }

        return $this;
    }
}

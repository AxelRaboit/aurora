<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Entity;

use Aurora\Core\Timestampable\TimestampableTrait;
use Aurora\Module\Accounting\Contract\Exception\PublishedVersionIsImmutableException;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * One numbered state of a template's wording.
 *
 * A version has exactly two lives. While `publishedAt` is null it is a draft:
 * it can be written to freely, and nothing outside the back office has seen
 * it. Publishing closes it for good, and from then on every write throws.
 *
 * Publication is the trigger rather than usage, deliberately. A published but
 * unused version could otherwise be rewritten between the moment it was shown
 * to somebody and the moment they signed, and nothing would record that the
 * text had moved. Publishing is an explicit act, which is what the guarantee
 * should hang on.
 *
 * The guard sits on the entity and not only in the manager, because a rule
 * enforced by whoever remembers to call the right service is a rule that holds
 * until the first new code path. Here there is no path: a client subclass, a
 * future controller and a command all hit `assertEditable()`.
 *
 * Editing published wording is still perfectly possible - by opening a new
 * draft, which is what the manager does. What is impossible is doing it in
 * place.
 */
#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractContractTemplateVersion implements ContractTemplateVersionInterface
{
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ContractTemplateInterface::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ContractTemplateInterface $template;

    /**
     * 1, 2, 3 within its own template.
     *
     * A number rather than a date, because it is quoted by people: "the
     * contract is on version 2 of the monthly trame" is checkable, a timestamp
     * is not.
     */
    #[ORM\Column]
    protected int $number = 1;

    #[ORM\Column(nullable: true)]
    protected ?DateTimeImmutable $publishedAt = null;

    /** @var Collection<string, ContractTemplateVersionTranslationInterface> */
    #[ORM\OneToMany(
        targetEntity: ContractTemplateVersionTranslationInterface::class,
        mappedBy: 'version',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        indexBy: 'locale',
    )]
    protected Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    abstract public function getId(): ?int;

    public function getTemplate(): ContractTemplateInterface
    {
        return $this->template;
    }

    public function setTemplate(ContractTemplateInterface $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->assertEditable();

        $this->number = $number;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return $this->publishedAt instanceof DateTimeImmutable;
    }

    public function publish(DateTimeImmutable $at): static
    {
        // Not idempotent on purpose, unlike archiving: publishing twice would
        // mean the caller believes this version is still open, and letting
        // that pass is how a published version gets written to next.
        $this->assertEditable();

        $this->publishedAt = $at;

        return $this;
    }

    public function assertEditable(): void
    {
        if ($this->isPublished()) {
            throw PublishedVersionIsImmutableException::forVersion($this->getId(), $this->number);
        }
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function getTranslation(string $locale): ?ContractTemplateVersionTranslationInterface
    {
        return $this->translations->get($locale);
    }

    public function addTranslation(ContractTemplateVersionTranslationInterface $translation): static
    {
        $this->assertEditable();

        $locale = $translation->getLocale();

        if (!$this->translations->containsKey($locale)) {
            $this->translations->set($locale, $translation);
            $translation->setVersion($this);
        }

        return $this;
    }

    public function removeTranslation(string $locale): static
    {
        $this->assertEditable();

        $this->translations->remove($locale);

        return $this;
    }

    public function updateTranslation(string $locale, string $title, array $content): static
    {
        $this->assertEditable();

        $translation = $this->translations->get($locale);

        // Silently doing nothing would let a caller believe it had written
        // something. Creating the row here is not an option either: the
        // concrete class to instantiate is the manager's decision, so a
        // client can substitute it.
        if (!$translation instanceof ContractTemplateVersionTranslationInterface) {
            throw new InvalidArgumentException(sprintf('This version carries no "%s" wording to update. Add the translation first.', $locale));
        }

        $translation->setTitle($title)->setContent($content);

        return $this;
    }
}

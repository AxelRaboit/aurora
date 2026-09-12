<?php

declare(strict_types=1);

namespace Aurora\Module\Studio\Contract\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One language of one version's wording.
 *
 * A contract is signed in a language, and the language it was signed in is the
 * one that has to be reproducible years later - which is why the wording hangs
 * off a version rather than off the template. Translating a clause is a change
 * to the document, so it lands in a draft like any other and is published with
 * it.
 *
 * The setters are public because Doctrine hydrates through them and because
 * the version's own guarded API writes through them. Reaching a translation
 * directly and writing to it does bypass the immutability guard; the version
 * is the intended door, and it is the one every caller in this module uses.
 */
#[ORM\MappedSuperclass]
abstract class AbstractContractTemplateVersionTranslation implements ContractTemplateVersionTranslationInterface
{
    #[ORM\ManyToOne(targetEntity: ContractTemplateVersionInterface::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    protected ContractTemplateVersionInterface $version;

    #[ORM\Column(length: 10)]
    protected string $locale;

    /** What prints at the top of the document, in this language. */
    #[ORM\Column(length: 250)]
    protected string $title = '';

    /**
     * The document itself, as an editor block document.
     *
     * JSON rather than HTML: the same nineteen articles have to render into a
     * web page and into a PDF, and a block list can be walked for both. It is
     * also what the editor already speaks everywhere else in this application.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    protected array $content = [];

    abstract public function getId(): ?int;

    public function getVersion(): ContractTemplateVersionInterface
    {
        return $this->version;
    }

    public function setVersion(ContractTemplateVersionInterface $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function setContent(array $content): static
    {
        $this->content = $content;

        return $this;
    }
}

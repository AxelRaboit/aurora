<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Accounting\Contract\Entity;

use Aurora\Module\Accounting\Contract\Entity\ContractTemplate;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersion;
use Aurora\Module\Accounting\Contract\Entity\ContractTemplateVersionTranslation;
use Aurora\Module\Accounting\Contract\Exception\PublishedVersionIsImmutableException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The one rule the module is built around.
 *
 * A contract signed against version 2 has to still say in a year what it said
 * on the day it was signed. That holds only if a published version cannot be
 * written to, and it has to hold at the entity, not at the service: a rule
 * enforced by whoever remembers to call the right manager holds until the first
 * new code path.
 *
 * These tests are therefore not about the manager. They take the entity on its
 * own and try to write to it.
 */
final class ContractTemplateVersionImmutabilityTest extends TestCase
{
    public function testADraftAcceptsWording(): void
    {
        $version = $this->draft();

        $version->addTranslation($this->wording('fr', 'Contrat de prestation'));
        $version->updateTranslation('fr', 'Contrat de prestation de services', ['blocks' => [['type' => 'header']]]);

        self::assertSame('Contrat de prestation de services', $version->getTranslation('fr')?->getTitle());
        self::assertSame(['blocks' => [['type' => 'header']]], $version->getTranslation('fr')?->getContent());
        self::assertFalse($version->isPublished());
    }

    public function testPublishingClosesTheVersion(): void
    {
        $version = $this->draft();
        $version->addTranslation($this->wording('fr', 'Contrat'));

        $version->publish(new DateTimeImmutable('2026-09-08 10:00:00'));

        self::assertTrue($version->isPublished());
        self::assertSame('2026-09-08 10:00:00', $version->getPublishedAt()?->format('Y-m-d H:i:s'));
    }

    public function testAPublishedVersionRefusesAWordingChange(): void
    {
        $version = $this->published();

        $this->expectException(PublishedVersionIsImmutableException::class);

        $version->updateTranslation('fr', 'Texte réécrit après signature', []);
    }

    public function testAPublishedVersionRefusesANewLanguage(): void
    {
        $version = $this->published();

        $this->expectException(PublishedVersionIsImmutableException::class);

        $version->addTranslation($this->wording('es', 'Contrato'));
    }

    public function testAPublishedVersionRefusesLosingALanguage(): void
    {
        $version = $this->published();

        $this->expectException(PublishedVersionIsImmutableException::class);

        $version->removeTranslation('fr');
    }

    /**
     * The clause is part of the wording. Moving which language prevails after
     * publication would change what a signed contract says without changing a
     * word of its articles.
     */
    public function testAPublishedVersionRefusesMovingTheGoverningLanguage(): void
    {
        $version = $this->published();

        $this->expectException(PublishedVersionIsImmutableException::class);

        $version->setGoverningLocale('en');
    }

    public function testAPublishedVersionRefusesBeingRenumbered(): void
    {
        $version = $this->published();

        $this->expectException(PublishedVersionIsImmutableException::class);

        $version->setNumber(7);
    }

    /**
     * Publishing twice would mean the caller believes the version is still
     * open, and letting that pass is how a published version gets written to
     * on the next line.
     */
    public function testAPublishedVersionRefusesBeingPublishedAgain(): void
    {
        $version = $this->published();
        $firstPublication = $version->getPublishedAt();

        try {
            $version->publish(new DateTimeImmutable('2027-01-01 00:00:00'));
            self::fail('A second publication should have been refused.');
        } catch (PublishedVersionIsImmutableException) {
            self::assertSame($firstPublication, $version->getPublishedAt());
        }
    }

    public function testUpdatingAnUnknownLanguageSaysSoInsteadOfDoingNothing(): void
    {
        $version = $this->draft();

        $this->expectExceptionMessage('carries no "es" wording');

        $version->updateTranslation('es', 'Contrato', []);
    }

    public function testTheTemplateReportsItsDraftAndItsPublishedVersion(): void
    {
        $template = new ContractTemplate();

        $published = new ContractTemplateVersion();
        $published->setNumber($template->claimNextVersionNumber());
        $template->addVersion($published);
        $published->addTranslation($this->wording('fr', 'Contrat'));
        $published->publish(new DateTimeImmutable());

        $draft = new ContractTemplateVersion();
        $draft->setNumber($template->claimNextVersionNumber());
        $template->addVersion($draft);

        self::assertSame($draft, $template->getDraft());
        self::assertSame($published, $template->getLatestPublishedVersion());
        self::assertSame(2, $draft->getNumber());
    }

    /**
     * A number is burnt when it is handed out, not when the version survives.
     *
     * The counter is what makes that true: a discarded draft takes its row
     * away, so anything derived from the rows that remain would hand the same
     * number out twice.
     */
    public function testANumberIsNeverHandedOutTwice(): void
    {
        $template = new ContractTemplate();

        self::assertSame(1, $template->claimNextVersionNumber());
        self::assertSame(2, $template->claimNextVersionNumber());
        self::assertSame(3, $template->claimNextVersionNumber());
        self::assertSame(3, $template->getVersionCounter());
    }

    public function testATemplateWithoutAPublishedVersionSaysSo(): void
    {
        $template = new ContractTemplate();

        $draft = new ContractTemplateVersion();
        $draft->setNumber($template->claimNextVersionNumber());
        $template->addVersion($draft);

        self::assertNull($template->getLatestPublishedVersion());
        self::assertSame($draft, $template->getDraft());
    }

    private function draft(): ContractTemplateVersion
    {
        $version = new ContractTemplateVersion();
        $version->setNumber(1);
        $version->setTemplate(new ContractTemplate());

        return $version;
    }

    private function published(): ContractTemplateVersion
    {
        $version = $this->draft();
        $version->addTranslation($this->wording('fr', 'Contrat de prestation de services'));
        $version->publish(new DateTimeImmutable('2026-09-08 10:00:00'));

        return $version;
    }

    private function wording(string $locale, string $title): ContractTemplateVersionTranslation
    {
        $translation = new ContractTemplateVersionTranslation();
        $translation->setLocale($locale)->setTitle($title)->setContent([]);

        return $translation;
    }
}

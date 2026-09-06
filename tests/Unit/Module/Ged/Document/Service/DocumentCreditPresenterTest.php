<?php

declare(strict_types=1);

namespace Aurora\Tests\Unit\Module\Ged\Document\Service;

use Aurora\Module\Ged\Document\Entity\Document;
use Aurora\Module\Ged\Document\Service\DocumentCreditPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Whether a picture owes somebody a line of credit.
 *
 * The question used to be "is this file ours". It no longer distinguishes
 * anything - every picture is downloaded now, stock ones included - so the
 * answer comes from the attribution itself.
 */
final class DocumentCreditPresenterTest extends TestCase
{
    private function document(?string $name, ?string $url = null): Document
    {
        $document = new Document();
        $document->setTitle('A photo');
        $document->setFilePath('ged/2026/09/photo.jpeg');
        $document->setAttributionName($name);
        $document->setAttributionUrl($url);

        return $document;
    }

    /** The ordinary case: a file somebody uploaded owes nobody anything. */
    public function testAnUploadHasNoCredit(): void
    {
        self::assertNull((new DocumentCreditPresenter())->present($this->document(null)));
    }

    public function testNothingToCreditIsNotACredit(): void
    {
        self::assertNull((new DocumentCreditPresenter())->present($this->document('   ')));
    }

    /**
     * A downloaded stock photo is an ordinary local file. If the credit were
     * still keyed on the file being remote, it would silently stop rendering
     * the moment the picture was stored - which is the licence condition,
     * quietly dropped.
     */
    public function testADownloadedStockPhotoStillCarriesItsCredit(): void
    {
        $credit = (new DocumentCreditPresenter())
            ->present($this->document('Jane Doe', 'https://www.pexels.com/@jane'));

        self::assertSame(['name' => 'Jane Doe', 'url' => 'https://www.pexels.com/@jane'], $credit);
    }

    /** A photographer with no profile link is still a photographer. */
    public function testACreditWithoutALinkIsStillACredit(): void
    {
        self::assertSame(
            ['name' => 'Jane Doe', 'url' => null],
            (new DocumentCreditPresenter())->present($this->document('Jane Doe')),
        );
    }

    public function testNoDocumentIsNoCredit(): void
    {
        self::assertNull((new DocumentCreditPresenter())->present(null));
    }
}

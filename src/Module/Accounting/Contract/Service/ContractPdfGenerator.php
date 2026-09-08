<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Service;

use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Exception\ContractPdfAlreadyGeneratedException;
use Aurora\Module\Accounting\Contract\Signature\Entity\ContractSignatureInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

use function dirname;
use function file_put_contents;
use function hash_file;
use function is_file;
use function sprintf;

/**
 * The PDF, generated once and never again.
 *
 * "Once" is the whole specification. A document regenerated later would be
 * whatever today's renderer, today's fonts and today's template produce, which
 * is precisely the property a signed contract must not have - and the second
 * copy would differ from the one the parties were sent without anybody
 * noticing. So the file is written at the countersignature, hashed, and from
 * then on it is read rather than rebuilt.
 *
 * The refusal to regenerate is an exception rather than a silent skip: a caller
 * asking for a second one has misunderstood something, and returning the old
 * path quietly would hide that.
 *
 * dompdf rather than a headless browser: no process to supervise, no Chrome to
 * keep patched on a small VPS, and a legal document needs a fixed layout and
 * no JavaScript at all - which is exactly the subset dompdf does well. The cost
 * is that the template cannot use flex or grid, which is why the PDF has its
 * own stylesheet built from tables and blocks.
 */
final readonly class ContractPdfGenerator
{
    /** Where a contract's PDF lives, under the module's own directory. */
    public const string SUBDIRECTORY = 'contracts';

    public function __construct(
        private Environment $twig,
        private Filesystem $filesystem,
        private string $uploadDir,
    ) {}

    /**
     * Writes the PDF and returns its relative path and hash.
     *
     * @param list<ContractSignatureInterface> $signatures both parties, in signing order
     *
     * @return array{path: string, hash: string}
     *
     * @throws ContractPdfAlreadyGeneratedException when one already exists
     */
    public function generate(ContractInterface $contract, array $signatures): array
    {
        if (null !== $contract->getPdfPath()) {
            throw ContractPdfAlreadyGeneratedException::forContract($contract->getReference(), $contract->getPdfPath());
        }

        $html = $this->twig->render('@Accounting/pdf/contract.html.twig', [
            'contract' => $contract,
            'customer' => $contract->getCustomer(),
            'signatures' => $signatures,
            // The document as it was sealed, printed verbatim. Re-rendering it
            // from the snapshot would mean trusting the renderer of the day.
            'documentHtml' => $contract->getRenderedHtml() ?? '',
        ]);

        $relative = $this->relativePathFor($contract);
        $absolute = sprintf('%s/%s', $this->uploadDir, $relative);

        // A path already holding a file is a collision this must not paper
        // over: the reference is unique, so reaching here means something is
        // wrong upstream and overwriting would destroy a signed document.
        if (is_file($absolute)) {
            throw new RuntimeException(sprintf('A file already exists at %s.', $relative));
        }

        $this->filesystem->mkdir(dirname($absolute));

        if (false === file_put_contents($absolute, $this->render($html))) {
            throw new RuntimeException(sprintf('The contract PDF could not be written to %s.', $relative));
        }

        $hash = hash_file('sha256', $absolute);

        if (false === $hash) {
            throw new RuntimeException('The contract PDF was written but could not be hashed.');
        }

        return ['path' => $relative, 'hash' => $hash];
    }

    /**
     * `contracts/2026/CM-2026-0001.pdf`.
     *
     * By year, like every other upload in this application, and named by the
     * reference rather than by an id: somebody looking for a contract on disk
     * is looking for the number an accountant quoted at them.
     */
    public function relativePathFor(ContractInterface $contract): string
    {
        $reference = $contract->getReference();

        if (null === $reference) {
            throw new RuntimeException('A contract with no reference has no PDF path: seal it first.');
        }

        return sprintf(
            '%s/%s/%s.pdf',
            self::SUBDIRECTORY,
            $contract->getFrozenAt()?->format('Y') ?? date('Y'),
            $reference,
        );
    }

    public function absolutePathFor(ContractInterface $contract): string
    {
        return sprintf('%s/%s', $this->uploadDir, $contract->getPdfPath() ?? $this->relativePathFor($contract));
    }

    public function root(): string
    {
        return $this->uploadDir;
    }

    /**
     * The bytes, from dompdf.
     *
     * Remote resources are off, and that is the security-relevant setting here:
     * with them on, a stylesheet or an image URL inside the document would make
     * the server fetch it, which turns a rendering step into a request
     * forgery. Everything a contract needs is text.
     */
    private function render(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        // No PHP inside the document, ever. It is off by default; naming it
        // here means a future template cannot quietly turn it on by accident.
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('defaultPaperSize', 'A4');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = $dompdf->output();

        if ('' === $output) {
            throw new RuntimeException('dompdf produced no output for this contract.');
        }

        return $output;
    }
}

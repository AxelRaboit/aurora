<?php

declare(strict_types=1);

namespace Aurora\Module\Accounting\Contract\Service;

use Aurora\Core\Storage\StorageManager;
use Aurora\Core\Storage\Workspace\LocalWorkspace;
use Aurora\Module\Accounting\Contract\Entity\ContractInterface;
use Aurora\Module\Accounting\Contract\Exception\ContractPdfAlreadyGeneratedException;
use Aurora\Module\Accounting\Contract\Signature\Entity\ContractSignatureInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Twig\Environment;

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
        private StorageManager $storageManager,
        private LocalWorkspace $workspace,
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
        $adapter = $this->storageManager->active();

        // A key already holding a file is a collision this must not paper
        // over: the reference is unique, so reaching here means something is
        // wrong upstream and overwriting would destroy a signed document.
        if ($adapter->exists($relative)) {
            throw new RuntimeException(sprintf('A file already exists at %s.', $relative));
        }

        $bytes = $this->render($html);
        $adapter->write($relative, $bytes);

        // Hashed from what was rendered rather than read back. Reading back
        // would be a second call for the same bytes, and on a remote backend a
        // billed one.
        return ['path' => $relative, 'hash' => hash('sha256', $bytes)];
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

    /** The key a contract's PDF is stored under, generated or not. */
    public function keyFor(ContractInterface $contract): string
    {
        return $contract->getPdfPath() ?? $this->relativePathFor($contract);
    }

    public function exists(ContractInterface $contract): bool
    {
        return $this->storageManager->active()->exists($this->keyFor($contract));
    }

    /**
     * The bytes, a chunk at a time, for a response that hands them to a
     * browser.
     *
     * @return iterable<string>
     */
    public function readStream(ContractInterface $contract): iterable
    {
        return $this->storageManager->active()->readStream($this->keyFor($contract));
    }

    /**
     * Lends a real path for the length of `$work`.
     *
     * For the one caller that cannot take bytes: an email attachment goes
     * through `attachFromPath`, and teaching the mail service to take a string
     * would be a change to something every module uses, for one attachment.
     *
     * @template T
     *
     * @param callable(string): T $work
     *
     * @return T
     */
    public function withLocalCopy(ContractInterface $contract, callable $work): mixed
    {
        return $this->workspace->readable(
            $this->storageManager->active(),
            $this->keyFor($contract),
            $work,
        );
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

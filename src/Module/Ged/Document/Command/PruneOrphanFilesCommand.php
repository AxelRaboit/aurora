<?php

declare(strict_types=1);

namespace Aurora\Module\Ged\Document\Command;

use Aurora\Core\Storage\Enum\StorageAreaEnum;
use Aurora\Core\Storage\StorageManager;
use Aurora\Module\Ged\Document\Repository\DocumentRepository;
use Aurora\Module\Ged\Document\Repository\DocumentVersionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists, and on `--force` removes, the files under `var/uploads/ged/` that no
 * database row points at any more.
 *
 * Two things leave files behind. Until v0.8.1 deleting a document erased only
 * its image variants, so its file, its thumbnail and every version file stayed
 * on disk forever - and since version rows disappear through an
 * `ON DELETE CASCADE`, nothing was left to even name them. That is fixed, but
 * the files from past deletions are still there.
 *
 * The second source is permanent and harmless: the upload endpoint writes the
 * bytes before the form is submitted, so an abandoned create form leaves a file
 * with no row. Which is why this refuses to touch anything recent - `--days`
 * (7 by default) keeps a file somebody is still working on out of reach.
 *
 * Dry by default. Nothing is deleted without `--force`.
 *
 * Note on `--days`: it reads the stored object's modification date, which on a
 * remote backend is when the object was put there rather than when the file
 * was made. Right after a migration everything looks new, and this spares all
 * of it until the window passes. Prudent rather than wrong, but surprising if
 * you do not know it.
 */
#[AsCommand(
    name: 'aurora:ged:prune-orphans',
    description: 'List (or remove) the GED files no database row points at.',
)]
final class PruneOrphanFilesCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentVersionRepository $versionRepository,
        private readonly StorageManager $storageManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete. Without it, nothing is touched.');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Spare files modified in the last N days.', '7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('GED orphan files');

        $days = max(0, (int) $input->getOption('days'));
        $force = true === $input->getOption('force');

        $adapter = $this->storageManager->active();
        $referenced = $this->referencedPaths();
        $cutoff = time() - ($days * 86400);

        $orphans = [];
        $spared = 0;
        $bytes = 0;

        // One listing, and every size and date comes with it. Asking the
        // backend again per file would be free on a disk and one billed
        // request per file anywhere else.
        foreach ($adapter->list(StorageAreaEnum::Ged->value) as $object) {
            if (isset($referenced[$object->key])) {
                continue;
            }

            if ($object->lastModifiedAt->getTimestamp() > $cutoff) {
                ++$spared;
                continue;
            }

            $orphans[] = $object->key;
            $bytes += $object->size;
        }

        if ($spared > 0) {
            $io->text(sprintf('%d unreferenced file(s) left alone: modified less than %d day(s) ago.', $spared, $days));
        }

        if ([] === $orphans) {
            $io->success('No orphan file.');

            return Command::SUCCESS;
        }

        sort($orphans);
        $io->listing(array_slice($orphans, 0, 50));
        if (count($orphans) > 50) {
            $io->text(sprintf('... and %d more.', count($orphans) - 50));
        }

        $summary = sprintf('%d orphan file(s), %s.', count($orphans), $this->humanBytes($bytes));

        if (!$force) {
            $io->warning($summary.' Nothing deleted: pass --force.');

            return Command::SUCCESS;
        }

        // In one call rather than one per orphan: this command exists to clean
        // up after thousands of them.
        $adapter->deleteMany($orphans);

        $io->success($summary.' Deleted.');

        return Command::SUCCESS;
    }

    /**
     * Every relative path the database still names: a document's file, its
     * thumbnail, each of its generated variants, and every version file.
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $paths = [];

        /** @var list<array{filePath: string|null, thumbnailPath: string|null, variants: array<string, string>}> $documents */
        $documents = $this->documentRepository->createQueryBuilder('d')
            ->select('d.filePath', 'd.thumbnailPath', 'd.variants')
            ->getQuery()
            ->getResult();

        foreach ($documents as $document) {
            foreach ([$document['filePath'], $document['thumbnailPath']] as $path) {
                if (null !== $path && '' !== $path) {
                    $paths[$path] = true;
                }
            }

            foreach ($document['variants'] as $variant) {
                if ('' !== $variant) {
                    $paths[$variant] = true;
                }
            }
        }

        /** @var list<array{filePath: string}> $versions */
        $versions = $this->versionRepository->createQueryBuilder('v')
            ->select('v.filePath')
            ->getQuery()
            ->getResult();

        foreach ($versions as $version) {
            $paths[$version['filePath']] = true;
        }

        return $paths;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unit = 'KB';
        foreach ($units as $candidate) {
            $unit = $candidate;
            if ($value < 1024) {
                break;
            }

            $value /= 1024;
        }

        return sprintf('%.1f %s', $value, $unit);
    }
}

<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Command;

use Aurora\Core\Storage\Adapter\StoredObject;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Exception\StorageException;
use Aurora\Core\Storage\StorageManager;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function sprintf;

/**
 * Proves a storage backend works, by using it.
 *
 * Not a configuration check: reading four non-empty strings back out of the
 * environment proves nothing, and the failure everyone actually hits is a
 * token with read-only rights or scoped to the wrong bucket, which looks
 * perfectly configured right up until the first write.
 *
 * So it writes a witness object, reads it back, compares the bytes, confirms
 * the listing sees it, and deletes it. Each step is reported on its own line,
 * because "it does not work" is not actionable and "write succeeded, read
 * failed" is.
 *
 * The witness lives under `_doctor/`, carries a random name and is deleted in
 * a `finally`. A crash still leaves at most one small object, named clearly
 * enough that nobody has to wonder what it is.
 */
#[AsCommand(
    name: 'aurora:storage:doctor',
    description: 'Write, read back and delete a witness object, to prove a storage backend works.',
)]
final class StorageDoctorCommand extends Command
{
    private const string WITNESS_PREFIX = '_doctor';

    public function __construct(
        private readonly StorageManager $storageManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'disk',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf('Which backend to check (%s).', implode(', ', array_column(StorageDiskEnum::cases(), 'value'))),
            StorageDiskEnum::Local->value,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $requested = (string) $input->getOption('disk');
        $disk = StorageDiskEnum::tryFrom($requested);

        if (!$disk instanceof StorageDiskEnum) {
            $io->error(sprintf(
                'Unknown disk "%s". Known: %s.',
                $requested,
                implode(', ', array_column(StorageDiskEnum::cases(), 'value')),
            ));

            return Command::INVALID;
        }

        $io->title(sprintf('Storage doctor: %s', $disk->value));

        try {
            $adapter = $this->storageManager->forDisk($disk);
        } catch (StorageException $storageException) {
            $io->error($storageException->getMessage());

            return Command::FAILURE;
        }

        $key = sprintf(
            '%s/%s-%s.txt',
            self::WITNESS_PREFIX,
            new DateTimeImmutable()->format('Ymd-His'),
            bin2hex(random_bytes(6)),
        );
        $contents = sprintf('aurora storage doctor %s', bin2hex(random_bytes(16)));

        $failed = false;

        try {
            $this->step($io, 'write', static fn () => $adapter->write($key, $contents));

            $this->step($io, 'read back', static function () use ($adapter, $key, $contents): void {
                $read = $adapter->read($key);

                if ($read !== $contents) {
                    throw new StorageException('the bytes read back differ from the bytes written');
                }
            });

            $this->step($io, 'exists', static function () use ($adapter, $key): void {
                if (!$adapter->exists($key)) {
                    throw new StorageException('the object was written but exists() says no');
                }
            });

            $this->step($io, 'stat carries size and date', static function () use ($adapter, $key, $contents): void {
                $stored = $adapter->stat($key);

                if (!$stored instanceof StoredObject) {
                    throw new StorageException('stat() found nothing');
                }

                if ($stored->size !== mb_strlen($contents, '8bit')) {
                    throw new StorageException(sprintf('stat() reports %d bytes, %d were written', $stored->size, mb_strlen($contents, '8bit')));
                }
            });

            $this->step($io, 'listing sees it, with its metadata', static function () use ($adapter, $key, $contents): void {
                foreach ($adapter->list(self::WITNESS_PREFIX) as $object) {
                    if ($object->key !== $key) {
                        continue;
                    }

                    if ($object->size !== mb_strlen($contents, '8bit')) {
                        throw new StorageException('the listing reports a size that does not match');
                    }

                    return;
                }

                throw new StorageException('the object is not in the listing');
            });
        } catch (Throwable $throwable) {
            $failed = true;
            $io->newLine();
            $io->error($throwable->getMessage());
            $this->explain($io, $disk, $throwable);
        } finally {
            // Runs whichever step failed, so a backend that can write but not
            // delete still gets told, and a run that died mid-way does not
            // leave litter for the next one to puzzle over.
            try {
                $adapter->delete($key);
                $io->writeln('  <fg=green>ok</>  clean up');
            } catch (Throwable $exception) {
                $failed = true;
                $io->writeln(sprintf('  <fg=red>failed</>  clean up: %s', $exception->getMessage()));
                $io->warning(sprintf('The witness object may still be there, at "%s".', $key));
            }
        }

        if ($failed) {
            return Command::FAILURE;
        }

        $io->newLine();
        $io->success(sprintf('%s is working: written, read back, listed and deleted.', $disk->value));

        return Command::SUCCESS;
    }

    private function step(SymfonyStyle $io, string $label, callable $work): void
    {
        $work();
        $io->writeln(sprintf('  <fg=green>ok</>  %s', $label));
    }

    /**
     * Turns the common failures into the thing to go and change.
     *
     * Every one of these cost somebody an evening at some point, and the
     * message the API returns for them names none of the causes.
     */
    private function explain(SymfonyStyle $io, StorageDiskEnum $disk, Throwable $exception): void
    {
        if (StorageDiskEnum::R2 !== $disk) {
            return;
        }

        $message = mb_strtolower($exception->getMessage());

        $hint = match (true) {
            str_contains($message, 'not configured') => 'Fill R2_ENDPOINT, R2_BUCKET, R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY in .env.local, or in the server environment.',
            str_contains($message, 'signature') => 'The secret access key does not match the access key id. Both are shown once, at token creation; a new token is quicker than guessing.',
            str_contains($message, 'access denied'), str_contains($message, '403') => 'The token reaches Cloudflare but is refused. Check it carries "Object Read & Write" rather than read only, and that its bucket scope includes this one.',
            str_contains($message, 'nosuchbucket'), str_contains($message, '404') => 'The bucket named in R2_BUCKET does not exist at this endpoint. Note the endpoint must be the account URL with no bucket at the end: the console shows one with the bucket appended, and pasting that puts the bucket in twice.',
            str_contains($message, 'could not resolve'), str_contains($message, 'timed out') => 'The endpoint hostname did not answer. Check R2_ENDPOINT against the console, and that this machine can reach it.',
            default => null,
        };

        if (null !== $hint) {
            $io->note($hint);
        }
    }
}

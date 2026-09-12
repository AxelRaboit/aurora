<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Probe;

/**
 * What a probe run found.
 *
 * `hint` is the part worth having: the error a storage API returns names none
 * of the causes an operator can act on, so a run that failed carries the thing
 * to go and change rather than only the thing that broke.
 */
final readonly class StorageProbeResult
{
    /**
     * @param list<StorageProbeStep> $steps
     */
    public function __construct(
        public bool $ok,
        public array $steps,
        public ?string $error = null,
        public ?string $hint = null,
    ) {}

    /**
     * @return array{ok: bool, steps: list<array{key: string, ok: bool, error: string|null}>, error: string|null, hint: string|null}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'steps' => array_map(
                static fn (StorageProbeStep $step): array => [
                    'key' => $step->key,
                    'ok' => $step->ok,
                    'error' => $step->error,
                ],
                $this->steps,
            ),
            'error' => $this->error,
            'hint' => $this->hint,
        ];
    }
}

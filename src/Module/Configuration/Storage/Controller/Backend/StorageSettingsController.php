<?php

declare(strict_types=1);

namespace Aurora\Module\Configuration\Storage\Controller\Backend;

use Aurora\Core\Enum\HttpMethodEnum;
use Aurora\Core\Http\JsonRequestTrait;
use Aurora\Core\Http\JsonResponseTrait;
use Aurora\Core\Storage\Enum\StorageDeliveryModeEnum;
use Aurora\Core\Storage\Enum\StorageDiskEnum;
use Aurora\Core\Storage\Exception\StorageException;
use Aurora\Core\Storage\Probe\StorageProbe;
use Aurora\Core\Storage\StorageManager;
use Aurora\Module\Configuration\Storage\Setting\StorageSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reads and writes the Storage tab of the settings screen.
 *
 * The interesting rule is the one on switching disk: it is refused unless the
 * backend has answered a probe since its configuration last changed. Turning
 * on a bucket nobody has reached means every upload from that moment fails,
 * and the person who finds out is whoever next tries to add a document. The
 * cost of the rule is one button press.
 */
#[Route('/backend/configuration/storage', name: 'backend_configuration_storage')]
#[IsGranted('configuration.settings.manage')]
final class StorageSettingsController extends AbstractController
{
    use JsonRequestTrait;
    use JsonResponseTrait;

    public function __construct(
        private readonly StorageSettings $settings,
        private readonly StorageManager $storageManager,
        private readonly StorageProbe $probe,
    ) {}

    #[Route('', name: '_show', methods: [HttpMethodEnum::Get->value])]
    public function show(): JsonResponse
    {
        return $this->jsonSuccess($this->settings->state());
    }

    #[Route('', name: '_save', methods: [HttpMethodEnum::Post->value])]
    public function save(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);

        $disk = StorageDiskEnum::tryFrom((string) ($payload['activeDisk'] ?? '')) ?? StorageDiskEnum::Local;
        $deliveryMode = StorageDeliveryModeEnum::tryFrom((string) ($payload['deliveryMode'] ?? ''))
            ?? StorageDeliveryModeEnum::Proxy;

        // Absent means "leave the stored credential alone"; present means
        // replace it, including with an empty string to forget it. The form
        // only sends a field somebody typed in.
        $accessKeyId = array_key_exists('accessKeyId', $payload) ? mb_trim((string) $payload['accessKeyId']) : null;
        $secretAccessKey = array_key_exists('secretAccessKey', $payload) ? mb_trim((string) $payload['secretAccessKey']) : null;

        $this->settings->save(
            activeDisk: StorageDiskEnum::Local,
            deliveryMode: $deliveryMode,
            endpoint: mb_trim((string) ($payload['endpoint'] ?? '')),
            bucket: mb_trim((string) ($payload['bucket'] ?? '')),
            accessKeyId: $accessKeyId,
            secretAccessKey: $secretAccessKey,
            publicBaseUrl: mb_trim((string) ($payload['publicBaseUrl'] ?? '')),
        );

        // Written in two passes on purpose. The credentials have to be stored
        // before the disk can be judged: a first save that carries both a new
        // bucket and the switch would otherwise be checked against the old
        // configuration.
        if (StorageDiskEnum::Local !== $disk) {
            if (null === $this->settings->verifiedAt()) {
                return $this->jsonFailure(
                    'backend.settings.storage.errors.verification_required',
                    extra: ['state' => $this->settings->state()],
                );
            }

            $this->settings->save(
                activeDisk: $disk,
                deliveryMode: $deliveryMode,
                endpoint: mb_trim((string) ($payload['endpoint'] ?? '')),
                bucket: mb_trim((string) ($payload['bucket'] ?? '')),
                accessKeyId: null,
                secretAccessKey: null,
                publicBaseUrl: mb_trim((string) ($payload['publicBaseUrl'] ?? '')),
            );
        }

        return $this->jsonSuccess($this->settings->state());
    }

    /**
     * Runs the probe against a backend and, when it passes, records that it
     * did so the toggle unlocks.
     */
    #[Route('/test', name: '_test', methods: [HttpMethodEnum::Post->value])]
    public function test(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $disk = StorageDiskEnum::tryFrom((string) ($payload['disk'] ?? '')) ?? StorageDiskEnum::R2;

        try {
            $adapter = $this->storageManager->forDisk($disk);
        } catch (StorageException $storageException) {
            return $this->jsonSuccess([
                'ok' => false,
                'steps' => [],
                'error' => $storageException->getMessage(),
                'hint' => 'backend.settings.storage.hints.incomplete',
                'state' => $this->settings->state(),
            ]);
        }

        $result = $this->probe->run($adapter);

        if ($result->ok && StorageDiskEnum::Local !== $disk) {
            $this->settings->markVerified();
        }

        return $this->jsonSuccess($result->toArray() + ['state' => $this->settings->state()]);
    }
}

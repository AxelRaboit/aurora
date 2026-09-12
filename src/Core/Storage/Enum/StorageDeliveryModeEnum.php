<?php

declare(strict_types=1);

namespace Aurora\Core\Storage\Enum;

/**
 * How the bytes of a remotely stored file reach a browser.
 *
 * In all three cases the address of a document stays `/uploads/{path}`. That
 * is not a detail: the block editor writes the URL of an image into the body
 * of a publication, so a document that changes disk must not change address,
 * or every page that embedded it breaks. What varies is only what the
 * `/uploads/{path}` endpoint answers.
 */
enum StorageDeliveryModeEnum: string
{
    /**
     * Stream the bytes through PHP. No public bucket, no signature, the access
     * rules of the application still apply because the request goes through it.
     * The bytes cross the server twice, which is the price.
     */
    case Proxy = 'proxy';

    /**
     * Answer a redirect to a short-lived signed URL. The bytes go straight from
     * the bucket to the browser and the object stays private. Costs one extra
     * round trip, and the redirect cannot be cached for long since the link it
     * points at expires.
     */
    case Presigned = 'presigned';

    /**
     * Answer a redirect to the public hostname mapped onto the bucket. Fastest,
     * cacheable, and the CDN takes the load. The object is then readable by
     * anyone holding the address, which suits published media and not much
     * else.
     */
    case PublicUrl = 'public_url';

    public function isRedirect(): bool
    {
        return self::Proxy !== $this;
    }
}

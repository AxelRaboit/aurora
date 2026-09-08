/**
 * Mirrors src/Module/Media/Library/Enum/MimeTypeEnum.php so the front-end can share the
 * same vocabulary as the back-end without scattering raw `'image/png'` strings
 * across components.
 *
 * ⚠️ Keep this file in sync with the PHP enum: every case added there must be
 * reflected here (and vice-versa).
 */
export const MimeType = Object.freeze({
    Jpeg: "image/jpeg",
    Jpg: "image/jpg",
    Png: "image/png",
    Gif: "image/gif",
    Webp: "image/webp",
    Svg: "image/svg+xml",
    Pdf: "application/pdf",
    Mp4: "video/mp4",
    Webm: "video/webm",
});

const VIDEO_TYPES = new Set([MimeType.Mp4, MimeType.Webm]);

/** True for a file a browser can play in a `<video>`. */
export function isVideoMimeType(mimeType) {
    return VIDEO_TYPES.has(mimeType);
}

const IMAGE_TYPES = new Set([
    MimeType.Jpeg,
    MimeType.Jpg,
    MimeType.Png,
    MimeType.Gif,
    MimeType.Webp,
    MimeType.Svg,
]);

/** True for any image/* (raster or vector). Excludes PDFs. */
export function isImageMimeType(mimeType) {
    return IMAGE_TYPES.has(mimeType);
}

/** True for `application/pdf`. */
export function isPdfMimeType(mimeType) {
    return mimeType === MimeType.Pdf;
}

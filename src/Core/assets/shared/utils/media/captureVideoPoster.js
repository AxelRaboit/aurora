import { isVideoMimeType } from "@core/utils/enums/media/mimeType.js";

/**
 * Draws one frame out of a video file the user just picked, to send along as
 * the document's poster.
 *
 * Why here and not on the server: a `<video preload="none">` with no poster is
 * a black rectangle at the browser's default 300x150 ratio, and the only way
 * to avoid that is to have a still. Producing one server-side means ffmpeg,
 * which on a plain Ubuntu pulls close to two hundred packages, GPU drivers and
 * a speech recognition engine included, onto every server an Aurora runs on.
 * The browser about to upload the film already carries the codecs, so it draws
 * the frame itself and the server stores what it is handed.
 *
 * Everything here degrades to `null` rather than throwing: a poster is a nicety
 * and an upload that works today must not start failing because a codec, a
 * seek or a canvas did not cooperate. The caller uploads the film regardless.
 */

/** Where in the film to look. Past a fade from black, before a short clip ends. */
const SEEK_SECONDS = 1;

/** Long side of the stored still. It is decoration, not a second copy. */
const MAX_SIDE = 1280;

/** A file that never fires its events must not hold the upload hostage. */
const TIMEOUT_MS = 10000;

function settledOn(element, event) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            cleanup();
            reject(new Error(`timeout waiting for ${event}`));
        }, TIMEOUT_MS);

        function cleanup() {
            clearTimeout(timer);
            element.removeEventListener(event, onDone);
            element.removeEventListener("error", onError);
        }

        function onDone() {
            cleanup();
            resolve();
        }

        function onError() {
            cleanup();
            reject(new Error(`error waiting for ${event}`));
        }

        element.addEventListener(event, onDone, { once: true });
        element.addEventListener("error", onError, { once: true });
    });
}

function toBlob(canvas) {
    return new Promise((resolve) => {
        // An unsupported type is not an error for `toBlob`: the browser
        // quietly encodes a PNG instead. Either is a raster image, which is
        // all the server asks of it.
        canvas.toBlob((blob) => resolve(blob), "image/webp", 0.82);
    });
}

/**
 * @param {File} file the video the user picked
 * @returns {Promise<{poster: File, width: number, height: number}|null>}
 *          the frame and the film's own dimensions, or null when no frame
 *          could be drawn
 */
export async function captureVideoPoster(file) {
    if (
        !file ||
        !isVideoMimeType(file.type) ||
        typeof document === "undefined"
    ) {
        return null;
    }

    const objectUrl = URL.createObjectURL(file);
    const video = document.createElement("video");
    video.preload = "auto";
    video.muted = true;
    video.playsInline = true;
    video.crossOrigin = "anonymous";

    try {
        video.src = objectUrl;
        await settledOn(video, "loadedmetadata");

        const width = video.videoWidth;
        const height = video.videoHeight;
        if (!width || !height) return null;

        // A clip shorter than the seek point still deserves a poster, so aim
        // for its midpoint instead. A stream with no known duration reports
        // Infinity, in which case the fixed mark is the best guess available.
        const duration = Number.isFinite(video.duration)
            ? video.duration
            : SEEK_SECONDS * 2;
        video.currentTime = Math.min(SEEK_SECONDS, duration / 2);
        await settledOn(video, "seeked");

        const scale = Math.min(1, MAX_SIDE / Math.max(width, height));
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        canvas
            .getContext("2d")
            .drawImage(video, 0, 0, canvas.width, canvas.height);

        const blob = await toBlob(canvas);
        if (!blob) return null;

        const extension = blob.type === "image/webp" ? "webp" : "png";

        return {
            poster: new File([blob], `poster.${extension}`, {
                type: blob.type,
            }),
            width,
            height,
        };
    } catch {
        return null;
    } finally {
        URL.revokeObjectURL(objectUrl);
        video.removeAttribute("src");
        video.load();
    }
}

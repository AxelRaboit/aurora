import { describe, expect, it, vi, beforeEach, afterEach } from "vitest";
import { captureVideoPoster } from "./captureVideoPoster.js";

/**
 * jsdom has no video decoder, so what is pinned down here is the contract
 * around the decoding rather than the decoding itself: who is asked, what
 * comes back when the answer is no, and above all that a failure is a `null`
 * and never an exception. An upload that works today must not start failing
 * because a browser could not draw a frame.
 */

function videoFile(type = "video/mp4") {
    return new File([new Uint8Array([0, 0, 0, 1])], "reel.mp4", { type });
}

/**
 * Stands in for the `<video>` jsdom will not drive: metadata arrives, seeking
 * lands, and the canvas hands back bytes.
 */
function stubMediaPipeline({
    width = 404,
    height = 720,
    duration = 39.6,
    blob = new Blob(["x"], { type: "image/webp" }),
} = {}) {
    const realCreate = document.createElement.bind(document);

    vi.spyOn(document, "createElement").mockImplementation((tag) => {
        const element = realCreate(tag);

        if (tag === "video") {
            Object.defineProperty(element, "videoWidth", { value: width });
            Object.defineProperty(element, "videoHeight", { value: height });
            Object.defineProperty(element, "duration", { value: duration });
            Object.defineProperty(element, "load", { value: () => {} });

            const fire = (event) =>
                setTimeout(() => element.dispatchEvent(new Event(event)), 0);
            Object.defineProperty(element, "src", {
                set: () => fire("loadedmetadata"),
                get: () => "blob:stub",
                configurable: true,
            });
            Object.defineProperty(element, "currentTime", {
                set: () => fire("seeked"),
                get: () => 1,
                configurable: true,
            });
        }

        if (tag === "canvas") {
            element.getContext = () => ({ drawImage: () => {} });
            element.toBlob = (callback) => callback(blob);
        }

        return element;
    });
}

beforeEach(() => {
    URL.createObjectURL = vi.fn(() => "blob:stub");
    URL.revokeObjectURL = vi.fn();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe("captureVideoPoster", () => {
    it("returns the frame and the film's own dimensions", async () => {
        stubMediaPipeline();

        const capture = await captureVideoPoster(videoFile());

        expect(capture).not.toBeNull();
        expect(capture.width).toBe(404);
        expect(capture.height).toBe(720);
        expect(capture.poster.type).toBe("image/webp");
        expect(capture.poster.name).toBe("poster.webp");
    });

    it("names the file after the type the browser actually encoded", async () => {
        // Safari before 14 ignores the requested type and writes a PNG. The
        // stored file has to say so, or it is served under a lie.
        stubMediaPipeline({ blob: new Blob(["x"], { type: "image/png" }) });

        const capture = await captureVideoPoster(videoFile());

        expect(capture.poster.name).toBe("poster.png");
    });

    it("ignores anything that is not a playable video", async () => {
        const spy = vi.spyOn(document, "createElement");

        expect(
            await captureVideoPoster(videoFile("application/pdf")),
        ).toBeNull();
        expect(await captureVideoPoster(null)).toBeNull();
        expect(spy).not.toHaveBeenCalled();
    });

    it("gives up quietly when the player reports no dimensions", async () => {
        stubMediaPipeline({ width: 0, height: 0 });

        await expect(captureVideoPoster(videoFile())).resolves.toBeNull();
    });

    it("gives up quietly when the canvas hands back nothing", async () => {
        stubMediaPipeline({ blob: null });

        await expect(captureVideoPoster(videoFile())).resolves.toBeNull();
    });

    it("releases the object URL whichever way it ends", async () => {
        stubMediaPipeline({ blob: null });

        await captureVideoPoster(videoFile());

        expect(URL.revokeObjectURL).toHaveBeenCalledWith("blob:stub");
    });
});

import { describe, expect, it, vi } from "vitest";

const request = vi.fn();
const currentToken = vi.fn(async () => "a-token");
const reset = vi.fn();

vi.mock("vue-i18n", () => ({
    useI18n: () => ({ t: (key) => key }),
}));

vi.mock("@/shared/composables/http/frontend/useRequest.js", () => ({
    useRequest: () => ({ request }),
}));

vi.mock("@editorial/frontend/composables/usePublicCaptcha.js", () => ({
    usePublicCaptcha: () => ({ enabled: true, currentToken, reset }),
}));

const { useFormRender } = await import("./useFormRender.js");

/**
 * What a submission carries, and what happens to the challenge afterwards.
 *
 * The token travels beside the answers rather than among them, because the
 * server reads answers by field id. If it ever moved into that map it would
 * collide the day a form has a field whose id serialises to the same key, and
 * the server would store the token as somebody's reply.
 */
function form() {
    return useFormRender({
        form: { fields: [{ id: 7, type: "text", conditions: [] }] },
        submitPath: "/fr/forms/contact",
        captcha: { enabled: true, provider: "turnstile", siteKey: "x" },
    });
}

describe("useFormRender - the anti-robot token", () => {
    it("sends it beside the answers", async () => {
        request.mockReset().mockResolvedValue({ success: true });
        currentToken.mockClear();

        const { answers, submit } = form();
        answers.value["7"] = "Bonjour";
        await submit();

        expect(request).toHaveBeenCalledWith("/fr/forms/contact", {
            7: "Bonjour",
            captchaToken: "a-token",
        });
    });

    /** A provider hands out one token per challenge; retrying with it fails. */
    it("spends the token when the server refuses", async () => {
        request.mockReset().mockResolvedValue({ success: false, errors: {} });
        reset.mockClear();

        const { submit } = form();
        await submit();

        expect(reset).toHaveBeenCalledOnce();
    });

    it("spends it when the request itself fails", async () => {
        request.mockReset().mockResolvedValue(null);
        reset.mockClear();

        const { submit } = form();
        await submit();

        expect(reset).toHaveBeenCalledOnce();
    });
});

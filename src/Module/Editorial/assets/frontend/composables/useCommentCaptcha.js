import { onBeforeUnmount, ref } from "vue";

/**
 * The anti-robot check on the comment form, when the site has one configured.
 *
 * Two services, two shapes, one interface. Turnstile draws a widget and hands
 * over a token when the reader passes it; reCAPTCHA v3 draws nothing and is
 * asked for a token at submit time, scoring behaviour instead. So the caller
 * gets `mount()` for the box and `token()` for the value, and does not care
 * which one is configured.
 *
 * Nothing is loaded when the check is off: a site without one must not fetch
 * a script from Cloudflare or Google to render a comment form.
 */
export function useCommentCaptcha(config) {
    const ready = ref(false);
    const token = ref("");

    const enabled = Boolean(config?.enabled) && Boolean(config?.siteKey);
    const provider = config?.provider ?? "turnstile";

    let widgetId = null;
    let scriptElement = null;

    /**
     * One script tag per page, whoever asks first. Loading it twice makes the
     * provider log a warning and, for Turnstile, renders the widget twice.
     */
    function loadScript() {
        if (!enabled) return Promise.resolve(false);

        const existing = document.querySelector(
            `script[src="${config.scriptUrl}"]`,
        );
        if (existing) {
            return existing.dataset.loaded === "1"
                ? Promise.resolve(true)
                : new Promise((resolve) => {
                      existing.addEventListener("load", () => resolve(true));
                      existing.addEventListener("error", () => resolve(false));
                  });
        }

        return new Promise((resolve) => {
            scriptElement = document.createElement("script");
            scriptElement.src = config.scriptUrl;
            scriptElement.async = true;
            scriptElement.defer = true;
            scriptElement.addEventListener("load", () => {
                scriptElement.dataset.loaded = "1";
                resolve(true);
            });
            scriptElement.addEventListener("error", () => resolve(false));
            document.head.appendChild(scriptElement);
        });
    }

    /** Draws the widget into `element`, for the provider that has one. */
    async function mount(element) {
        if (!enabled || !element) return;

        const loaded = await loadScript();
        if (!loaded) return;

        if ("turnstile" === provider) {
            // The script sets the global asynchronously after load, so a
            // render straight away can miss it.
            await waitFor(() => window.turnstile);

            widgetId = window.turnstile?.render(element, {
                sitekey: config.siteKey,
                callback: (value) => {
                    token.value = value;
                },
                "expired-callback": () => {
                    token.value = "";
                },
                "error-callback": () => {
                    token.value = "";
                },
            });
        }

        ready.value = true;
    }

    /**
     * The token to send. Turnstile already has one; reCAPTCHA is asked now,
     * so the score reflects the submission rather than the page load.
     */
    async function currentToken() {
        if (!enabled) return "";

        if ("recaptcha" === provider) {
            await waitFor(() => window.grecaptcha?.execute);

            return new Promise((resolve) => {
                window.grecaptcha.ready(async () => {
                    try {
                        resolve(
                            await window.grecaptcha.execute(config.siteKey, {
                                action: "comment",
                            }),
                        );
                    } catch {
                        resolve("");
                    }
                });
            });
        }

        return token.value;
    }

    /** After a refused or accepted submission, a token is spent. */
    function reset() {
        token.value = "";

        if ("turnstile" === provider && null !== widgetId) {
            window.turnstile?.reset(widgetId);
        }
    }

    function waitFor(predicate, attempts = 40) {
        return new Promise((resolve) => {
            const tick = (left) => {
                if (predicate() || left <= 0) {
                    resolve(predicate());
                    return;
                }

                window.setTimeout(() => tick(left - 1), 50);
            };

            tick(attempts);
        });
    }

    onBeforeUnmount(() => {
        if ("turnstile" === provider && null !== widgetId) {
            window.turnstile?.remove(widgetId);
        }
    });

    return { enabled, provider, ready, mount, currentToken, reset };
}

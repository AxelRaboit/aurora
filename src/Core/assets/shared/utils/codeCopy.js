/**
 * The copy button on a code zone - `<button data-code-copy>`.
 *
 * A snippet exists to be used somewhere else, and selecting one by hand in a
 * scrolling box is the small annoyance nobody reports. The button is rendered
 * hidden and this module reveals it: without a script, or without a clipboard
 * to write to, a reader would otherwise be looking at a control that does
 * nothing when pressed.
 *
 * The words come from the markup rather than from a catalogue here - Twig has
 * the reader's language, and one attribute is cheaper than pulling vue-i18n
 * into a page that has no Vue on it.
 *
 * Markup: templates/Frontend/themes/default/editorial/post/_grid_zone.html.twig
 */
const SELECTOR = "button[data-code-copy]";

/** How long the button says it worked before going back to its own name. */
const CONFIRM_MS = 1600;

function copy(button) {
    const snippet = button.closest("[data-code-zone]")?.querySelector("code");

    if (!snippet) {
        return;
    }

    void navigator.clipboard.writeText(snippet.textContent ?? "").then(() => {
        const label = button.dataset.codeCopy;
        const done = button.dataset.codeCopied;

        button.textContent = done;
        // Announced as well as shown: the button's own name changing is the
        // only feedback there is, and a reader who cannot see it gets nothing
        // otherwise.
        button.setAttribute("aria-label", done);

        window.setTimeout(() => {
            button.textContent = label;
            button.setAttribute("aria-label", label);
        }, CONFIRM_MS);
    });
}

function arm() {
    // `writeText` needs a secure context, which every real page is and a plain
    // `http://` one is not. Rather than let the button fail silently there, it
    // stays hidden and the snippet stays selectable by hand.
    if (!navigator.clipboard?.writeText) {
        return;
    }

    document.querySelectorAll(SELECTOR).forEach((button) => {
        button.hidden = false;
        button.addEventListener("click", () => copy(button));
    });
}

if ("loading" === document.readyState) {
    document.addEventListener("DOMContentLoaded", arm);
} else {
    arm();
}

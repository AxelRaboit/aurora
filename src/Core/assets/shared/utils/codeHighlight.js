/**
 * Colours the code zones of a page, if there are any.
 *
 * Loaded on every page, and on almost every page it costs one
 * `querySelector` and stops. The highlighter itself - some fifty kilobytes
 * once compressed - is fetched only when a snippet is actually present, which
 * is why the import sits inside the branch rather than at the top.
 *
 * The markup is already readable before this runs: the server sends a plain
 * `<pre><code>`, and a reader whose JavaScript never arrives gets the snippet
 * intact, in a monospace box. Colour is the enhancement, not the content.
 */
const SELECTOR = 'pre code[class*="language-"]';

async function highlight() {
    const blocks = document.querySelectorAll(SELECTOR);

    if (0 === blocks.length) {
        return;
    }

    const { default: hljs } = await import("./format/highlighter.js");

    blocks.forEach((block) => {
        // `language-x` is what the server wrote; highlight.js reads the same
        // convention, so nothing has to be translated between them. An
        // unknown language leaves the block as it was.
        hljs.highlightElement(block);
    });
}

if ("loading" === document.readyState) {
    document.addEventListener("DOMContentLoaded", () => void highlight());
} else {
    void highlight();
}

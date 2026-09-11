import { computed, ref } from "vue";
import { Marked } from "marked";
import DOMPurify from "dompurify";

/**
 * Turns one manual page into what the screen needs: its HTML and the headings
 * it is made of.
 *
 * The search used to live here too. It moved with the column it belonged to,
 * into `RubricsPanel` in the side menu: the field and the results it replaces
 * were always the same surface.
 *
 * The Markdown ships with the product and nobody else writes it, so the
 * parser stays plain: headings, paragraphs, lists, emphasis, code and
 * pictures. No wiki-links, no callouts, no checkboxes - the Notes renderer
 * carries those because a note is written by a person, and borrowing it here
 * would hand the manual four behaviours it never uses.
 *
 * DOMPurify runs anyway. The content is ours today; a sanitiser costs
 * nothing and the day somebody pastes a table from elsewhere is not the day
 * to discover it was missing.
 */
/** The attribute the overlay listens on, and the label its trigger carries. */
const TRIGGER = "data-doc-image";

export function useDocumentationPage(props, zoomLabel = "") {
    const ZOOM_LABEL = zoomLabel;

    /** Headings get an address so the summary on the right can point at them. */
    function slugify(text) {
        return String(text)
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/(^-|-$)/g, "");
    }

    /**
     * The pictures live in the package, behind a route, so their address is
     * built here rather than written in the Markdown. The files say
     * `../../images/<rubric>/<name>.png`, which is the truth on disk and
     * keeps the folder browsable outside the application.
     */
    function imageUrl(source) {
        const match = /images\/([\w-]+)\/([\w.-]+)$/.exec(source);

        if (null === match) return source;

        return props.imagePathTemplate
            .replace("__rubric__", match[1])
            .replace("__name__", match[2]);
    }

    /**
     * The pictures of the page being read, in the order they appear.
     *
     * Collected while rendering rather than parsed a second time: the
     * enlarged view is indexed by position, and two passes over the same
     * Markdown is two chances for the orders to disagree.
     */
    const pictures = ref([]);

    const rendered = computed(() => {
        const collected = [];

        const renderer = {
            heading({ tokens, depth }) {
                const text = this.parser.parseInline(tokens);

                return `<h${depth} id="${slugify(text)}">${text}</h${depth}>`;
            },
            image({ href, title, text }) {
                const url = imageUrl(href);
                const at = collected.length;
                collected.push({ url, alt: text ?? "", caption: title ?? "" });

                // A button rather than a div with a handler: enlarging a
                // screenshot is an action, and a reader on a keyboard has to
                // be able to reach it.
                //
                // `loading="lazy"`: a page carries up to six screenshots of
                // 1600 pixels, and the reader sees the first one.
                return `<figure><button type="button" class="doc-figure" ${TRIGGER}="${at}" aria-label="${ZOOM_LABEL}"><img src="${url}" alt="${text ?? ""}"${title ? ` title="${title}"` : ""} loading="lazy"></button></figure>`;
            },
        };

        const parser = new Marked({ gfm: true });
        parser.use({ renderer });

        const html = DOMPurify.sanitize(
            parser.parse(props.page.markdown ?? ""),
            {
                ADD_ATTR: [TRIGGER],
            },
        );

        pictures.value = collected;

        return html;
    });

    /** The page's own sections, for the summary beside it. */
    const outline = computed(() =>
        [...(props.page.markdown ?? "").matchAll(/^## +(.+)$/gm)].map(
            (match) => ({
                id: slugify(match[1].replace(/\*\*/g, "")),
                label: match[1].replace(/\*\*/g, ""),
            }),
        ),
    );

    function pageUrl(slug) {
        return props.pagePathTemplate.replace("__slug__", slug);
    }

    return {
        rendered,
        pictures,
        outline,
        pageUrl,
        TRIGGER,
    };
}

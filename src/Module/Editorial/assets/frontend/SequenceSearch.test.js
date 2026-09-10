import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createTestI18n } from "@/tests/helpers/createTestI18n.js";
import SequenceSearch from "./SequenceSearch.vue";

const MESSAGES = {
    frontend: {
        sequence: {
            search: {
                placeholder: "Chercher dans la documentation…",
                loading: "Recherche…",
                count: "1 page trouvée | {count} pages trouvées",
                empty: "Rien ne correspond à « {query} ».",
                clear: "Effacer",
            },
        },
    },
};

const POSTS = [
    {
        id: 3,
        title: "Composer avec les blocs",
        slug: "composer-avec-les-blocs",
        description: "Titres, listes, encadrés.",
        postTypeSlug: "documentation",
    },
];

/**
 * The summary is rendered by the server and lives beside the field, inside
 * the same nav. The component hides it while a search is answering rather
 * than redrawing it, so the test needs that nav to exist.
 */
function mountInNav() {
    const host = document.createElement("nav");
    const summary = document.createElement("div");
    summary.setAttribute("data-sequence-summary", "");
    host.append(summary);
    document.body.append(host);

    const mountPoint = document.createElement("div");
    host.prepend(mountPoint);

    const wrapper = mount(SequenceSearch, {
        props: { searchUrl: "/fr/search?type=documentation", locale: "fr" },
        global: { plugins: [createTestI18n(MESSAGES)] },
        attachTo: mountPoint,
    });

    return { wrapper, summary };
}

describe("SequenceSearch", () => {
    beforeEach(() => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true, posts: POSTS }),
        });
    });

    afterEach(() => {
        document.body.innerHTML = "";
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    it("asks the server for what was typed", async () => {
        const { wrapper } = mountInNav();

        await wrapper.find("input").setValue("blocs");
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());

        const url = new URL(global.fetch.mock.calls[0][0]);

        expect(url.searchParams.get("q")).toBe("blocs");
        // The scope, which is the point of the whole endpoint change.
        expect(url.searchParams.get("type")).toBe("documentation");
    });

    it("lists what came back, with its address", async () => {
        const { wrapper } = mountInNav();

        await wrapper.find("input").setValue("blocs");
        await vi.waitFor(() =>
            expect(wrapper.text()).toContain("Composer avec les blocs"),
        );

        expect(wrapper.find("a").attributes("href")).toBe(
            "/fr/documentation/composer-avec-les-blocs",
        );
        expect(wrapper.text()).toContain("1 page trouvée");
    });

    /**
     * One letter matches everything and means nothing, so nothing is asked
     * before two - and the summary stays where it is.
     */
    it("asks nothing for a single letter", async () => {
        const { wrapper, summary } = mountInNav();

        await wrapper.find("input").setValue("b");
        await flushPromises();

        expect(global.fetch).not.toHaveBeenCalled();
        expect(summary.hidden).toBe(false);
    });

    it("hides the summary while searching and gives it back when cleared", async () => {
        const { wrapper, summary } = mountInNav();

        await wrapper.find("input").setValue("blocs");
        await vi.waitFor(() => expect(summary.hidden).toBe(true));

        await wrapper.find("input").setValue("");
        await flushPromises();

        expect(summary.hidden).toBe(false);
    });

    it("says so when nothing matches", async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true, posts: [] }),
        });

        const { wrapper } = mountInNav();

        await wrapper.find("input").setValue("girafes");
        await vi.waitFor(() =>
            expect(wrapper.text()).toContain("Rien ne correspond"),
        );

        expect(wrapper.text()).toContain("girafes");
    });

    /** A summary left hidden by a component that is gone is a lost page. */
    it("gives the summary back when it unmounts", async () => {
        const { wrapper, summary } = mountInNav();

        await wrapper.find("input").setValue("blocs");
        await vi.waitFor(() => expect(summary.hidden).toBe(true));

        wrapper.unmount();

        expect(summary.hidden).toBe(false);
    });
});

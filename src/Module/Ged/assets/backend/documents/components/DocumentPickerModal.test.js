import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createTestI18n } from "@/tests/helpers/createTestI18n.js";
import DocumentPickerModal from "./DocumentPickerModal.vue";

// The library tab fetches directly; the Unsplash tab goes through useRequest.
// Both are stubbed so the component is exercised without a network.
const request = vi.fn();
vi.mock("@/shared/composables/http/backend/useRequest.js", () => ({
    useRequest: () => ({ request }),
}));

const PHOTO = {
    id: "abc123",
    url: "https://images.unsplash.com/photo-1?w=1080",
    thumbUrl: "https://images.unsplash.com/photo-1?w=200",
    authorName: "Jane Doe",
    description: "A tidy desk",
    color: "#0f172a",
};

// Only the keys these tests read back; createTestI18n falls back to the raw
// key for everything else, which is fine and keeps the fixture honest.
const MESSAGES = {
    backend: {
        ged: {
            documents: { picker_tab_library: "Médiathèque" },
            unsplash: {
                tab: "Unsplash",
                not_configured:
                    "Renseignez UNSPLASH_ACCESS_KEY pour l'activer.",
                empty: "Aucune photo pour cette recherche.",
                search_placeholder: "Rechercher une photo…",
            },
        },
    },
};

function mountPicker(props = {}) {
    return mount(DocumentPickerModal, {
        props: {
            show: true,
            listPath: "/backend/ged/documents/list",
            mimePrefix: "image/",
            ...props,
        },
        global: {
            plugins: [createTestI18n(MESSAGES)],
            stubs: {
                AppModal: {
                    template: "<div><slot /><slot name='footer' /></div>",
                },
                AppModalFooter: { template: "<div><slot /></div>" },
                AppLoader: true,
                AppNoData: true,
                AppPagination: true,
            },
        },
    });
}

function findTab(wrapper, label) {
    return wrapper
        .findAll("button")
        .find((button) => button.text().includes(label));
}

beforeEach(() => {
    request.mockReset();
    global.fetch = vi.fn().mockResolvedValue({
        json: async () => ({ items: [], page: 1, totalPages: 1 }),
    });
});

describe("DocumentPickerModal Unsplash tab", () => {
    /**
     * Unsplash returns photographs and nothing else, so a picker opened to
     * choose a contract has no use for the tab - and showing it would invite
     * an editor to file a landscape as a signed document.
     */
    it("is offered only when the field is asking for an image", () => {
        expect(findTab(mountPicker(), "Unsplash")).toBeDefined();
        expect(
            findTab(mountPicker({ mimePrefix: null }), "Unsplash"),
        ).toBeUndefined();
    });

    it("searches only once there is something to search for", async () => {
        const wrapper = mountPicker();
        await findTab(wrapper, "Unsplash").trigger("click");

        wrapper.vm.onUnsplashSearch("   ");
        await flushPromises();

        expect(request).not.toHaveBeenCalled();
    });

    it("tells the editor when the integration has no key rather than looking empty", async () => {
        request.mockResolvedValue({
            configured: false,
            results: [],
            totalPages: 0,
        });

        const wrapper = mountPicker();
        await findTab(wrapper, "Unsplash").trigger("click");
        wrapper.vm.onUnsplashSearch("desk");
        await flushPromises();

        expect(wrapper.text()).toContain("UNSPLASH_ACCESS_KEY");
    });

    /**
     * The import is what puts a picture in the library, so it happens on
     * confirm: browsing results must not litter the media library with every
     * photo an editor merely looked at.
     */
    it("imports nothing while the editor is only browsing", async () => {
        request.mockResolvedValue({
            configured: true,
            results: [PHOTO],
            totalPages: 1,
        });

        const wrapper = mountPicker();
        await findTab(wrapper, "Unsplash").trigger("click");
        wrapper.vm.onUnsplashSearch("desk");
        await flushPromises();

        wrapper.vm.pickPhoto(PHOTO);
        await flushPromises();

        // One call: the search. Nothing was imported.
        expect(request).toHaveBeenCalledTimes(1);
    });

    it("imports the chosen photo on confirm and emits the document it became", async () => {
        const document = { id: 42, fileUrl: PHOTO.url, isRemote: true };
        request
            .mockResolvedValueOnce({
                configured: true,
                results: [PHOTO],
                totalPages: 1,
            })
            .mockResolvedValueOnce({ document });

        const wrapper = mountPicker();
        await findTab(wrapper, "Unsplash").trigger("click");
        wrapper.vm.onUnsplashSearch("desk");
        await flushPromises();

        wrapper.vm.pickPhoto(PHOTO);
        await wrapper.vm.confirm();

        expect(request).toHaveBeenLastCalledWith(
            "/backend/ged/unsplash/import",
            { photo: PHOTO },
            expect.anything(),
        );
        expect(wrapper.emitted("select")).toEqual([[document]]);
    });

    /**
     * A failed import keeps the modal open: the search that led here took
     * effort, and closing on failure would throw it away.
     */
    it("emits nothing when the import fails", async () => {
        request
            .mockResolvedValueOnce({
                configured: true,
                results: [PHOTO],
                totalPages: 1,
            })
            .mockResolvedValueOnce(null);

        const wrapper = mountPicker();
        await findTab(wrapper, "Unsplash").trigger("click");
        wrapper.vm.onUnsplashSearch("desk");
        await flushPromises();

        wrapper.vm.pickPhoto(PHOTO);
        await wrapper.vm.confirm();

        expect(wrapper.emitted("select")).toBeUndefined();
    });
});

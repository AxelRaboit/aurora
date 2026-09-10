import { describe, it, expect, vi } from "vitest";
import { defineComponent } from "vue";
import { mount } from "@vue/test-utils";
import { createTestI18n } from "@/tests/helpers/createTestI18n.js";
import { usePostRowActions } from "./usePostRowActions.js";

/**
 * What a row offers depends on the permission and on where the record sits,
 * which is a rule about the record rather than a layout decision. These pin
 * the rules that are easy to get backwards.
 */
function actionsFor(post, options = {}) {
    let actions;

    const Harness = defineComponent({
        setup() {
            const build = usePostRowActions({
                can: () => true,
                editPath: (item) => `/posts/${item.id}/edit`,
                restore: vi.fn(),
                confirmDelete: vi.fn(),
                forceDelete: vi.fn(),
                duplicate: vi.fn(),
                preview: vi.fn(),
                ...options,
            });

            actions = build(post);

            return () => null;
        },
    });

    mount(Harness, { global: { plugins: [createTestI18n()] } });

    return actions.map((action) => action.key);
}

describe("usePostRowActions", () => {
    it("offers the preview only when the list was given its address", () => {
        expect(actionsFor({ id: 1 }, { canPreview: true })).toContain(
            "preview",
        );
        expect(actionsFor({ id: 1 }, { canPreview: false })).not.toContain(
            "preview",
        );
    });

    /**
     * Modifier reste en tête, parce que c'est le geste courant de cet écran ;
     * la lecture vient ensuite, et l'irréversible en dernier. L'ordre de
     * cette liste est celui dans lequel on la parcourt sous pression.
     */
    it("reads edit, preview, duplicate, then delete", () => {
        expect(actionsFor({ id: 1 }, { canPreview: true })).toEqual([
            "edit",
            "preview",
            "duplicate",
            "delete",
        ]);
    });

    /** Nothing to preview and nothing to copy on the way out. */
    it("offers restore and permanent delete on a trashed row, and nothing else", () => {
        expect(
            actionsFor({ id: 1, trashed: true }, { canPreview: true }),
        ).toEqual(["restore", "force-delete"]);
    });

    it("calls back with the row it was given", () => {
        const preview = vi.fn();
        let actions;

        const Harness = defineComponent({
            setup() {
                const build = usePostRowActions({
                    can: () => true,
                    editPath: () => "/x",
                    restore: vi.fn(),
                    confirmDelete: vi.fn(),
                    forceDelete: vi.fn(),
                    duplicate: vi.fn(),
                    preview,
                    canPreview: true,
                });

                actions = build({ id: 42 });

                return () => null;
            },
        });

        mount(Harness, { global: { plugins: [createTestI18n()] } });
        actions.find((action) => action.key === "preview").onSelect();

        expect(preview).toHaveBeenCalledWith({ id: 42 });
    });
});

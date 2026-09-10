import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { h, nextTick, ref } from "vue";
import { createTestI18n } from "@/tests/helpers/createTestI18n.js";
import AppRevealList from "./AppRevealList.vue";

const MESSAGES = {
    shared: {
        reveal: { more: "1 de plus | {count} de plus" },
        common: { collapse: "Replier" },
    },
};

const TERMS = Array.from({ length: 8 }, (_, index) => ({
    id: index + 1,
    label: `Rubrique ${index + 1}`,
}));

function render(props = {}) {
    return mount(AppRevealList, {
        props: { items: TERMS, ...props },
        slots: {
            default: ({ item }) => h("span", { class: "row" }, item.label),
        },
        global: { plugins: [createTestI18n(MESSAGES)] },
    });
}

describe("AppRevealList", () => {
    it("shows the first five and counts the rest", () => {
        const wrapper = render();

        expect(wrapper.findAll(".row")).toHaveLength(5);
        expect(wrapper.text()).toContain("3 de plus");
    });

    it("shows everything once opened, and folds back", async () => {
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        expect(wrapper.findAll(".row")).toHaveLength(8);
        expect(wrapper.text()).toContain("Replier");

        await wrapper.find("button").trigger("click");
        expect(wrapper.findAll(".row")).toHaveLength(5);
    });

    it("draws no toggle when everything already fits", () => {
        const wrapper = render({ items: TERMS.slice(0, 4) });

        expect(wrapper.findAll(".row")).toHaveLength(4);
        expect(wrapper.find("button").exists()).toBe(false);
    });

    /**
     * The case that makes this more than cosmetic: a filter ticked among the
     * hidden ones would narrow the screen with nothing on screen to say why.
     */
    it("opens itself when a hidden entry is active", () => {
        const wrapper = render({ isActive: (item) => item.id === 7 });

        expect(wrapper.findAll(".row")).toHaveLength(8);
    });

    it("stays folded when the active entry is already visible", () => {
        const wrapper = render({ isActive: (item) => item.id === 2 });

        expect(wrapper.findAll(".row")).toHaveLength(5);
    });

    /** Ticking a hidden entry from elsewhere has to open it too. */
    it("opens when an entry becomes active later", async () => {
        const active = ref(0);
        const wrapper = mount(AppRevealList, {
            props: {
                items: TERMS,
                isActive: (item) => item.id === active.value,
            },
            slots: {
                default: ({ item }) => h("span", { class: "row" }, item.label),
            },
            global: { plugins: [createTestI18n(MESSAGES)] },
        });

        expect(wrapper.findAll(".row")).toHaveLength(5);

        active.value = 8;
        await nextTick();

        expect(wrapper.findAll(".row")).toHaveLength(8);
    });

    it("takes the number to show from its caller", () => {
        const wrapper = render({ visible: 2 });

        expect(wrapper.findAll(".row")).toHaveLength(2);
        expect(wrapper.text()).toContain("6 de plus");
    });
});

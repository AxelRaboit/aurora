import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { createI18n } from "vue-i18n";
import AppFieldLabel from "./AppFieldLabel.vue";

const i18n = createI18n({
    legacy: false,
    locale: "en",
    messages: { en: { shared: { common: { close: "Close", help: "Help" } } } },
});

describe("AppFieldLabel", () => {
    it("renders label text", () => {
        const wrapper = mount(AppFieldLabel, {
            props: { label: "My Label" },
        });
        expect(wrapper.text()).toContain("My Label");
    });

    it("shows red asterisk when required=true", () => {
        const wrapper = mount(AppFieldLabel, {
            props: { label: "Required Field", required: true },
        });
        const asterisk = wrapper.find("span.text-red-500");
        expect(asterisk.exists()).toBe(true);
        expect(asterisk.text()).toBe("*");
    });

    it("does not show asterisk when required=false", () => {
        const wrapper = mount(AppFieldLabel, {
            props: { label: "Optional Field", required: false },
        });
        expect(wrapper.find("span.text-red-500").exists()).toBe(false);
    });

    it("renders nothing when label is empty", () => {
        const wrapper = mount(AppFieldLabel, {
            props: { label: "" },
        });
        expect(wrapper.find("label").exists()).toBe(false);
    });

    /**
     * Eleven controls share this label. The eleven that pass no topic must
     * render exactly as they did before the help button existed, which means
     * keeping the plain block layout.
     */
    it("stays a plain block, with no button, when no help topic is given", () => {
        const wrapper = mount(AppFieldLabel, {
            props: { label: "Plain" },
            global: { plugins: [i18n] },
        });

        expect(wrapper.find("button").exists()).toBe(false);
        expect(wrapper.find("label").classes()).toContain("block");
    });

    it("places a help button beside the label when a topic is given", () => {
        const wrapper = mount(AppFieldLabel, {
            props: { label: "With help", help: "storage.delivery_mode" },
            global: { plugins: [i18n], stubs: { Teleport: true } },
        });

        expect(wrapper.find("button").exists()).toBe(true);
        expect(wrapper.find("label").classes()).toContain("flex");
    });
});

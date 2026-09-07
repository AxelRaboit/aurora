import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { useRequest } from "@/shared/composables/http/frontend/useRequest.js";
import { useCommentCaptcha } from "./useCommentCaptcha.js";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";

function emptyForm() {
    return {
        authorName: "",
        authorEmail: "",
        content: "",
        parentId: null,
        // The honeypot. Named `website` because that is what a bot expects to
        // find and fill; a reader never sees it.
        website: "",
    };
}

export function usePostComments(props) {
    const { t } = useI18n();
    const { request } = useRequest();
    const captcha = useCommentCaptcha(props.captcha);

    const comments = ref([]);
    const total = ref(0);
    const reactionTypes = ref([]);
    const loaded = ref(false);

    const form = ref(emptyForm());
    const errors = ref({});
    const sending = ref(false);
    const notice = ref(null);

    const replyingTo = computed(() =>
        comments.value.find((comment) => comment.id === form.value.parentId),
    );

    function applyThread(thread) {
        if (!thread) return;

        comments.value = thread.comments ?? [];
        total.value = thread.total ?? 0;
        reactionTypes.value = thread.reactionTypes ?? [];
    }

    async function load() {
        const data = await request(props.listPath, null, HttpMethod.Get);
        applyThread(data);
        loaded.value = true;
    }

    onMounted(load);

    async function submit() {
        if (sending.value) return;

        sending.value = true;
        errors.value = {};
        notice.value = null;

        try {
            // Fetched per submission rather than held: a reCAPTCHA score is
            // about this send, and a Turnstile token is spent once.
            const captchaToken = await captcha.currentToken();
            const data = await request(props.submitPath, {
                ...form.value,
                captchaToken,
            });
            if (!data) return;

            if (!data.success) {
                // Server messages arrive as translation keys, the same as
                // everywhere else in Aurora - a reader must never be shown a
                // raw `frontend.editorial.…` string.
                errors.value = Object.fromEntries(
                    Object.entries(data.errors ?? {}).map(([field, key]) => [
                        field,
                        t(key),
                    ]),
                );
                if (data.error)
                    notice.value = { type: "error", text: t(data.error) };

                // Refused or not, the token is used up: leaving the spent one
                // in place makes the next attempt fail for a reason the reader
                // cannot see.
                captcha.reset();

                return;
            }

            applyThread(data.thread);
            captcha.reset();
            form.value = emptyForm();
            notice.value = {
                type: "success",
                text: t(
                    data.pending
                        ? "frontend.editorial.comments.pending"
                        : "frontend.editorial.comments.sent",
                ),
            };
        } finally {
            sending.value = false;
        }
    }

    function replyTo(comment) {
        form.value.parentId = comment?.id ?? null;
    }

    async function react(comment, type) {
        const data = await request(
            buildPath(props.reactPathTemplate, { commentId: comment.id }),
            { type },
        );
        if (!data?.success) return;

        // Only the counts come back, so the thread is patched in place rather
        // than refetched - a reaction should not scroll the page or drop a
        // half-typed reply.
        patchReactions(comment.id, data.counts ?? {});
    }

    function patchReactions(commentId, counts) {
        for (const comment of comments.value) {
            if (comment.id === commentId) {
                comment.reactions = counts;

                return;
            }

            for (const reply of comment.replies ?? []) {
                if (reply.id === commentId) {
                    reply.reactions = counts;

                    return;
                }
            }
        }
    }

    return {
        comments,
        total,
        reactionTypes,
        loaded,
        form,
        errors,
        sending,
        notice,
        replyingTo,
        submit,
        replyTo,
        react,
        captcha,
    };
}

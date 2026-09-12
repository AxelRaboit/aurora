<script setup>
/**
 * One slide, drawn at the shape it will be shown in.
 *
 * **A fixed 16:9 frame**, which is the whole reason this module has layouts
 * rather than a flowing grid: what the reader arranges here is what lands on
 * the wall, and a frame that reflowed would be a preview that lies. The frame
 * scales with its container and its type scales with it, through `cqw` units,
 * so the same component is a thumbnail in the list and the full preview beside
 * the form without drawing twice.
 */
defineProps({
    slide: { type: Object, required: true },
    /** Thumbnails drop the body text: at 160px nothing of it is legible. */
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="slide-ratio">
        <div class="slide-frame" :class="compact ? 'is-compact' : ''">
            <template v-if="slide.layout === 'title'">
                <p class="sf-title">{{ slide.content.title }}</p>
                <p v-if="!compact && slide.content.subtitle" class="sf-subtitle">{{ slide.content.subtitle }}</p>
            </template>

            <template v-else-if="slide.layout === 'section'">
                <p class="sf-section">{{ slide.content.title }}</p>
            </template>

            <template v-else-if="slide.layout === 'bullets'">
                <p class="sf-heading">{{ slide.content.title }}</p>
                <ul v-if="!compact" class="sf-list">
                    <li v-for="(bullet, at) in slide.content.bullets ?? []" :key="at">{{ bullet }}</li>
                </ul>
                <div v-else class="sf-lines">
                    <span v-for="(bullet, at) in (slide.content.bullets ?? []).slice(0, 4)" :key="at" />
                </div>
            </template>

            <template v-else-if="slide.layout === 'quote'">
                <p class="sf-quote">{{ slide.content.quote }}</p>
                <p v-if="slide.content.attribution" class="sf-attribution">{{ slide.content.attribution }}</p>
            </template>

            <template v-else-if="slide.layout === 'split'">
                <p class="sf-heading">{{ slide.content.title }}</p>
                <div class="sf-columns">
                    <p>{{ compact ? "" : slide.content.left }}</p>
                    <p>{{ compact ? "" : slide.content.right }}</p>
                </div>
            </template>

            <template v-else-if="slide.layout === 'image'">
                <div class="sf-image">
                    <span class="sf-image-mark" />
                </div>
                <p v-if="!compact && slide.content.caption" class="sf-caption">{{ slide.content.caption }}</p>
            </template>
        </div>
    </div>
</template>

<style scoped>
/* Container queries rather than a viewport breakpoint: the same frame is a
   160px thumbnail and a 700px preview on the same screen, so what the type has
   to follow is its own box, not the window. */
/**
 * Le rapport 16/9 par le remplissage, pas par `aspect-ratio`.
 *
 * `aspect-ratio` cède dès que le parent décide la hauteur autrement, et la
 * vignette redevenait carrée sans que rien ne le signale : ni `min-height: 0`
 * ni une sortie du contexte flex n'y ont suffi. `padding-top: 56.25%` se
 * résout toujours contre la largeur, quel que soit le contexte, et c'est le
 * seul point ici qui doit être vrai partout : un aperçu qui ne fait pas la
 * forme de la slide est un aperçu qui ment.
 */
.slide-ratio {
    /* Le conteneur de requête, c'est cette boîte-ci : sa largeur est décidée
       par la colonne (246 px) ou par la page (768 px), donc `cqw` y résout
       quelque chose de connu. Portée par le cadre lui-même, qui est
       positionné, l'unité se résolvait contre une largeur que le navigateur
       n'avait pas encore arrêtée, et la typographie tombait à rien. */
    container-type: inline-size;
    position: relative;
    width: 100%;
    padding-top: 56.25%;
}


.slide-frame {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2cqw;
    padding: 6cqw;
    background: var(--color-surface-2, #161b22);
    border: 1px solid var(--color-line, #30363d);
    border-radius: 0.5rem;
    /* Rogné plutôt qu'étiré : une slide trop remplie déborde au mur aussi, et
       un aperçu qui s'agrandit pour tout montrer est un aperçu qui ment. */
    overflow: hidden;
}



.sf-title { margin: 0; font-size: 8cqw; font-weight: 600; line-height: 1.1; }
.sf-subtitle { margin: 0; font-size: 4cqw; opacity: 0.7; }
.sf-section { margin: 0; font-size: 7cqw; font-weight: 600; text-align: center; }
.sf-heading { margin: 0; font-size: 6cqw; font-weight: 600; }
.sf-list { margin: 0; padding-left: 5cqw; font-size: 4cqw; line-height: 1.5; }
.sf-quote { margin: 0; font-size: 6cqw; font-style: italic; line-height: 1.3; }
.sf-attribution { margin: 0; font-size: 3.5cqw; opacity: 0.7; }
.sf-caption { margin: 0; font-size: 3.5cqw; opacity: 0.7; }

.sf-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 4cqw; font-size: 3.6cqw; }
.sf-columns p { margin: 0; }

.sf-image { flex: 1; display: grid; place-items: center; border-radius: 0.25rem; background: var(--color-surface-3, #21262d); }
.sf-image-mark { width: 12cqw; height: 12cqw; border-radius: 9999px; background: currentColor; opacity: 0.25; }

/* The thumbnail's stand-in for body text: grey bars say "there are four
   bullets here" without pretending 3px of type is readable. */
.sf-lines { display: flex; flex-direction: column; gap: 2cqw; }
.sf-lines span { height: 2cqw; border-radius: 9999px; background: currentColor; opacity: 0.2; }
.sf-lines span:nth-child(2) { width: 80%; }
.sf-lines span:nth-child(3) { width: 65%; }
.sf-lines span:nth-child(4) { width: 72%; }

.is-compact { gap: 1.5cqw; padding: 7cqw; }
</style>

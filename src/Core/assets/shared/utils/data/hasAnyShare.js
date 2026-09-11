/**
 * Est-ce qu'une répartition a quelque chose à montrer ?
 *
 * Une barre de répartition reçoit un segment par catégorie connue, qu'elle
 * soit remplie ou non : trois statuts de commentaire donnent trois segments
 * même sans un seul commentaire. Compter les segments répondait donc « oui »
 * à un site qui n'a rien, et le tableau de bord affichait une carte titrée
 * au-dessus d'une barre vide.
 *
 * La question utile est celle-ci, et elle se pose au même endroit pour les
 * quatre répartitions du tableau de bord.
 *
 * @param {Array<{value?: number}>} segments
 */
export function hasAnyShare(segments) {
    return (segments ?? []).some((segment) => (segment?.value ?? 0) > 0);
}

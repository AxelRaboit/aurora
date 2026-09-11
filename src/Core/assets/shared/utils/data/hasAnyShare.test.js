import { describe, it, expect } from "vitest";
import { hasAnyShare } from "./hasAnyShare.js";

describe("hasAnyShare", () => {
    it("dit oui dès qu'un segment porte quelque chose", () => {
        expect(hasAnyShare([{ value: 0 }, { value: 3 }])).toBe(true);
    });

    // Le défaut : le tableau de bord comptait les segments. Une barre de
    // répartition en reçoit un par catégorie connue, remplie ou non, donc un
    // site sans un seul commentaire affichait une carte titrée au-dessus
    // d'une barre vide.
    it("dit non quand tous les segments sont à zéro", () => {
        expect(hasAnyShare([{ value: 0 }, { value: 0 }, { value: 0 }])).toBe(
            false,
        );
    });

    it("dit non sur une liste vide ou absente", () => {
        expect(hasAnyShare([])).toBe(false);
        expect(hasAnyShare(undefined)).toBe(false);
    });

    it("traite une valeur manquante comme zéro", () => {
        expect(hasAnyShare([{}, { value: null }])).toBe(false);
    });
});

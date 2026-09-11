---
title: "L'identité du prestataire"
description: "Les douze réglages lus par provider.*, et pourquoi un seul vide bloque."
rubric: "Comptabilité"
---
Votre propre identité ne vit pas dans une fiche client : elle est dans les réglages, et c'est de là que les jetons `provider.*` la tirent au moment de sceller.

## Où ça se règle

Configuration → Réglages → Comptabilité. L'onglet porte l'identité, les coordonnées bancaires, la durée de conservation et les relances.

![L'onglet Comptabilité des réglages](../../images/06-comptabilite/identite-du-prestataire-01-l-onglet-comptabilite-des-reglages.png)

## Les douze réglages

- Dénomination, représentant, adresse professionnelle.
- SIRET, code APE, mention de TVA.
- Email, téléphone.
- Titulaire du compte, IBAN, BIC, banque.

Chacun correspond à un jeton du même nom dans les trames.

![L'identité et les coordonnées du prestataire](../../images/06-comptabilite/identite-du-prestataire-02-l-identite-et-les-coordonnees.png)

## Pourquoi un seul vide bloque le scellement

Si une trame nomme `{{provider.iban}}` et que le réglage est vide, le contrat partirait avec un trou à l'endroit où le client doit virer l'argent. Le scellement refuse plutôt que de produire ce document.

Le refus ne dépend donc pas des douze réglages, mais de ceux que vos trames utilisent réellement.

![Les coordonnées bancaires, la conservation et les relances](../../images/06-comptabilite/identite-du-prestataire-03-les-coordonnees-bancaires-et-les-relances.png)

## Ce qui change quand vous les modifiez

Rien, pour les contrats déjà scellés : ils portent la copie faite au scellement. Changer d'IBAN aujourd'hui ne réécrit aucun contrat signé, et c'est voulu.

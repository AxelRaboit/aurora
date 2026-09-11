---
title: "Le PDF signé"
description: "Généré une fois, son empreinte propre, et par où il est servi."
rubric: "Comptabilité"
---
Une fois le contrat conclu, un PDF est produit. Une seule fois, et il ne change plus.

## Le bloc de preuve

Sur la page du contrat, il rassemble ce qui fait la valeur du document : l'empreinte, l'algorithme et la forme canonique, la date de scellement, la date jusqu'à laquelle le contrat est conservé, et le compteur de relances.

Le bandeau « le sceau est intact » compare le document stocké à son empreinte, à chaque affichage.

![Le bloc de preuve d'un contrat conclu](../../images/06-comptabilite/pdf-signe-01-le-bloc-de-preuve-d-un-contrat-conclu.png)

## Pourquoi une seule fois

Regénérer le PDF produirait ce que le code d'aujourd'hui sait faire, pas ce qui a été signé. Le document est donc rendu une fois, à la conclusion, et conservé tel quel avec sa propre empreinte, distincte de celle du HTML scellé.

## Par où il est servi

Par une adresse dédiée du contrat, réservée à l'administration. Le client, lui, retrouve son exemplaire en rouvrant le lien de signature, qui affiche le contrat signé.

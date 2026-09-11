---
title: "Les fichiers orphelins"
description: "Retrouver ce qui traîne sur le disque sans ligne en base."
rubric: "Médiathèque"
---
Un fichier peut survivre à sa ligne : un import interrompu, une suppression à moitié faite. La commande les liste.

## S'en servir

- Lancée sans option, elle liste et ne supprime rien.
- Elle ne regarde que le dossier des dépôts, et compare avec ce que la base dit connaître.
- La suppression se demande explicitement.

## Le sens de la vérification

Elle ne détecte pas l'inverse : une ligne dont le fichier a disparu se voit à l'écran, par une image qui ne s'affiche pas.

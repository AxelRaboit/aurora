---
title: "Ce qui est chiffré en base"
description: "Le contenu des notes, et ce que ça protège ou non."
rubric: "Notes"
---
Le contenu d'une note est chiffré dans la base de données. C'est un des deux seuls endroits de l'application où c'est le cas, avec le tracé d'une signature de contrat.

## Ce que ça protège

Une copie de la base qui sortirait sans sa clé : une sauvegarde égarée, un accès en lecture à la base, un disque récupéré. Le contenu y est illisible.

## Ce que ça ne protège pas

Rien de ce qui passe par l'application, qui a la clé : un compte compromis lit les notes de son propriétaire comme d'habitude. Les **étiquettes** ne sont pas chiffrées non plus, puisqu'il faut pouvoir filtrer dessus. Le titre et le contenu, eux, le sont tous les deux.

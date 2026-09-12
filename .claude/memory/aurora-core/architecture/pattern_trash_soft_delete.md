# Corbeille : soft delete par module, purge partagée

## Règle

Une entité dont la suppression détruit du travail irremplaçable porte un
`deletedAt` et non un `remove()` direct. Le geste utilisateur (`delete()`)
devient réversible, et un second verbe (`forceDelete()`) porte la destruction.

Trois obligations vont avec la colonne :

1. **Tous les finders visibles filtrent** `deletedAt IS NULL` : listing,
   recherche, statistiques de contenu, et tout ce que voit le front public.
   Un document en corbeille qui reste visible quelque part est le bug que ce
   pattern doit empêcher.
2. **Les finders "physiques" ne filtrent pas** : ce qui décide si un fichier
   est encore référencé (`filterPathsInUse`, `countOnDisk` en GED) doit compter
   les lignes en corbeille, sinon la purge d'un voisin efface le fichier d'un
   document restaurable.
3. **La purge passe par le même réglage** `ApplicationParameterEnum::TrashAutoPurgeDays`
   que la corbeille des publications, via un `RecurringMessageProvider` du
   module. Jamais un délai maison.

La corbeille est un **filtre sur la liste existante** (`?trashed=1` porté
jusqu'au repository), pas un écran séparé : les autres filtres continuent de
fonctionner à l'intérieur, et il n'y a qu'une forme de payload à maintenir.

## Pourquoi

Parce que les deux moitiés du geste ont des conséquences opposées. Effacer la
ligne et les octets d'un seul coup, c'est une action que personne ne peut
annuler sur un fichier qui n'existe souvent nulle part ailleurs. Les séparer
donne à l'utilisateur une fenêtre de rattrapage, et au système un endroit unique
où la destruction arrive vraiment.

Deux délais de rétention différents seraient deux promesses faites à la même
personne sans le lui dire : d'où le réglage partagé.

## Comment l'appliquer

Implémenté en GED (documents) et en Editorial (publications). Pour un nouveau
module, suivre `Module/Ged/Document` :

- entité : `deletedAt` + `isTrashed()`, colonne indexée (le `IS NULL` est sur
  le chemin chaud de tous les listings) ;
- repository : paramètre `bool $trashed` sur le finder paginé, plus
  `countTrashed()`, `findAllTrashed()`, `findTrashedBefore()` ;
- manager : `delete()` (soft, idempotent), `restore()`, `forceDelete()`,
  `emptyTrash()`, `purgeTrashedBefore()`, et un `destroy()` protégé partagé par
  les deux derniers pour que la purge et les boutons ne divergent jamais ;
- contrôleur : `?trashed=1` sur l'index, plus `restore`, `force-delete`,
  `bulk-restore`, `empty-trash`, toutes sous le privilège `delete` ;
- audit : `x.trashed` et `x.restored` en plus de `x.deleted`, avec leur libellé
  dans `Module/Dev/Audit/translations` (un test le vérifie) ;
- front : la corbeille est une vue dans `useDocumentFilters`, hors de
  `hasActiveFilter` et de `resetFilters` (réinitialiser les filtres ne doit pas
  faire sortir de la corbeille).

Piège rencontré : le wording de confirmation. `delete_warning` disait « Cette
action est irréversible » alors qu'elle ne l'est plus. Le message irréversible
appartient désormais à la suppression définitive.

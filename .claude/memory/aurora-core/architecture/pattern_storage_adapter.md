---
name: Couche de stockage - tout passe par l'adaptateur
description: Aucun accès direct à app.upload_dir - StorageAdapterInterface, LocalWorkspace pour GD/pdftoppm, et les listings portent leurs métadonnées
type: feedback
---

## Règle

**Aucun code nouveau ne lit `app.upload_dir`.** Les octets se manipulent par
`Aurora\Core\Storage\Adapter\StorageAdapterInterface`, obtenu via
`StorageManager::active()` (où j'écris) ou `forDisk()` (qui possède ces
octets-là). Une clé est le chemin relatif que la base stocke déjà dans
`filePath`, inchangé.

Les seuls consommateurs légitimes de `app.upload_dir` restants :
`LocalStorageAdapter` (il *est* le disque), `UploadsServeController` et
`UserProfilePhotoManager` (à migrer, phases 4 et 7), plus
`ContractPdfGenerator` câblé par `services.yaml`.

## Pourquoi

Le paramètre était éparpillé sur huit classes qui joignaient un chemin à la
main. Changer de support voulait dire toutes les toucher, et l'outillage
image/PDF en dessous prend des noms de fichiers, pas des flux.

## Comment l'appliquer

**Du code qui a besoin d'un vrai chemin** (GD, `pdftoppm`, Ghostscript,
`getimagesize`) passe par `LocalWorkspace`, jamais par une concaténation.
Trois verbes, et le choix n'est pas cosmétique :

- `readable()` : je regarde, je ne modifie pas. Ce que le travail écrit est
  perdu sur un back-end distant.
- `writable()` : je modifie sur place, le résultat est stocké. Rien n'est
  réécrit si le travail lève.
- `target()` : je crée un nouvel objet. Rien n'est stocké si le travail n'a
  rien écrit.

Sur le disque local, les trois prêtent le fichier stocké lui-même : pas de
copie, pas de temporaire, exactement les performances d'avant.

**Un listing porte toujours ses métadonnées.** `list()` rend des
`StoredObject` avec taille et date. Rendre les clés seules transforme un
appel sur mille objets en mille et un, gratuits sur un disque et facturés
ailleurs. Même logique pour `deleteMany()` plutôt qu'une boucle de `delete()`.

**Pas d'`is_file()` / `filesize()` réflexe** sur un objet stocké : c'est un
appel réseau facturé dès qu'on quitte le disque. Demander à l'adaptateur
(`exists()`, `stat()`), et se souvenir de ce qu'on vient d'écrire plutôt que
de le redemander.

**Un back-end s'ajoute** en implémentant l'interface (auto-taguée
`aurora.storage_adapter` par `_instanceof`) et en ajoutant un cas à
`StorageDiskEnum`. Rien d'autre à modifier. L'enum ne porte que des cas qui
ont un adaptateur derrière, sinon une colonne peut nommer un disque que
`StorageManager` ne sait pas servir.

**Temporaires** : préfixe `aurora_storage_workspace_`, déclaré dans
`CleanTempFilesHandler::TMP_PREFIXES` (cf. [[convention_tmp_files_scheduler]]).

## Piège

Sur un back-end distant, la date d'un objet est celle de sa mise en dépôt, pas
celle du fichier d'origine. Après une migration tout paraît neuf, et les
gardes du type `--days` de `aurora:ged:prune-orphans` épargnent tout le lot.

Livré en phase 1 du chantier R2 (septembre 2026). Le reste du plan, dont
l'adaptateur R2 et le déplacement par document, n'est pas commencé.

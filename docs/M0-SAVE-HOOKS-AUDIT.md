# M0 — Audit des points de capture, Geeklog 2.2.2

Date : 2026-10-16. **Statut : audit statique partiel, aucun test d'intégration ni modification du Core réalisés.**

## Résultat confirmé sur le tag officiel v2.2.2

| Fournisseur | Chemin principal contrôlé | Écriture | Notification |
| --- | --- | --- | --- |
| article | system/lib-article.php : service_submit_story() | system/classes/article.class.php : saveToDatabase(), DB_save(stories) vers ligne 879 | system/lib-article.php : PLG_itemSaved() après saveToDatabase(), vers lignes 2882-2889 |
| staticpages | plugins/staticpages/services.inc.php : service_submit_staticpages() | DB_save(staticpage), vers ligne 639 | PLG_itemSaved() à partir de la ligne 661, donc après l'écriture |

Le dispatcher générique PLG_itemSaved() (system/lib-plugins.php, vers ligne 2775) est explicitement un événement **après** enregistrement. Il transmet id, type, old_id et sub_type ; il ne transporte pas le précédent snapshot.

La fonction **PLG_itemPreSave($type, $content)** existe bien dans system/lib-plugins.php (vers ligne 745), mais **aucun appel à cette fonction n'a été repéré** dans les chemins service_submit_story / saveToDatabase ni service_submit_staticpages examinés. Ne pas confondre disponibilité de la fonction avec présence d'une notification dans le cycle de vie étudié.

### Particularités constatées
- **Articles :** saveToDatabase() applique GLText::applyHTMLFilter() à introtext/bodytext juste avant DB_save. Choisir explicitement si le snapshot doit refléter l'état effectivement stocké. Les changements de sid sont accompagnés du transfert de commentaires/images et de la suppression de l'ancien enregistrement.
- **Static Pages :** après DB_save(), la fonction peut écraser sp_content avec un contenu calculé pour la recherche (modèles/PHP), vers lignes 710-724 ; ne jamais assimiler ce cache de recherche au document source éditable. Les changements de sp_id entraînent aussi transferts/suppressions.
- Une notification PLG_itemSaved() n'est **pas une preuve** que toutes les écritures externes ou dérivées ont été capturées.
- Certaines opérations internes (par exemple changements de propriétaire/groupe, contenu dérivé, statut/featured) ont leurs propres UPDATE ; déterminer lesquelles relèvent réellement du périmètre éditorial.

## Décision d'architecture proposée

**Ne pas implémenter une capture naïve dans plugin_itemsaved_revisions().** Elle arriverait trop tard pour obtenir l'état précédent d'un item modifié.

Option recommandée, soumise à prototype : contrat générique *avant mutation* appelé par les propriétaires au point fiable de leur enregistrement, transportant provider/type/id d'origine, ancien snapshot éditable versionné et information sur le nouvel état (ou un mécanisme équivalent avec persistance atomique). Revisions demeure propriétaire de l'historique, les articles/Static Pages propriétaires de leur validation et de la restauration.

Étudier l'utilisation/extension documentée de PLG_itemPreSave() plutôt que créer une API parallèle inutile ; vérifier ses limites (contenu passé par valeur, retour textuel d'erreur, timing, veto et compatibilité). Un nouveau contrat de type event pré-écriture peut être préférable si le mécanisme existant ne garantit ni sécurité ni atomicité. **Aucun patch Core n'est encore rédigé.**

Les tables historiques Revisions doivent être isolées par la base de chaque site ; pas d'UPDATE direct des tables de fournisseurs depuis le plugin. Les fichiers historiques, s'il y en a, ne doivent jamais résider dans un cache nettoyable.

## Tests restants avant clôture M0

- [ ] Prototype sur instance Geeklog 2.2.2 dédiée : déclenchement précis des événements pour modifications, brouillons, clones et changement d'ID sur les deux fournisseurs.
- [ ] Matrice complète des chemins admin / service / soumissions / Make, IA et Automation / scripts internes / import ; conserver preuves du support et limites.
- [ ] Vérifier ordre des validations, GLText filtrage, ACL et transactions disponibles (certaines tables MyISAM), puis concevoir absence de snapshot fantôme en cas d'échec.
- [ ] Tester sauvegardes concurrentes et stabilité de l'identité, particulièrement old_sid et sp_old_id.
- [ ] Définir le contrat source pour pages PHP/modèles (page_data, sp_content et champ de recherche) avec tests sur la fidélité.
- [ ] Prototype de restauration via chemin propriétaire normal, cache/index/événements, politique sur médias et liens.
- [ ] Documenter le plus petit patch générique nécessaire et décider PR distincte Core / Static Pages.

**Gate M0 : NON VALIDÉ.** Analyse statique utile réalisée, mais preuve en exécution et contrat de restauration restent à produire.

## Sources, tag v2.2.2
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/system/lib-article.php
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/system/classes/article.class.php
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/plugins/staticpages/services.inc.php
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/system/lib-plugins.php
- https://github.com/hostellerie/memorandum/blob/main/plugin-source-field-audit-mutation-contract.md

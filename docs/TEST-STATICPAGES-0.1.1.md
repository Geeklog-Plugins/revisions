> **Historical test plan for version 0.1.1.** This procedure is preserved for regression history. The Start tracking and Check changes interface described here is no longer the normal workflow in Revisions 0.2.5. For current installation and validation, see [README](../README.md).

# Tester la capture Static Pages avec Revisions 0.1.1

## Parcours prioritaire

1. Mettre à jour le plugin avec le ZIP `dist/revisions_0.1.1_2.2.2.zip` créé par GitHub Actions (ou copier les fichiers correctement sur le site de test).
2. Ouvrir **Plugins → Revisions**. La page doit afficher *Revisions 0.1.1 — Static Pages pilot*.
3. Sous **Tracked pages**, vérifier la présence de l'identifiant exact de la page déjà initialisée.
4. Si elle est absente, saisir le `sp_id` réel de la page puis cliquer **Start tracking**.
5. Modifier et enregistrer une page statique simple (sans PHP, sans modèle).
6. Retourner dans Revisions : vérifier **Recent versions**.
7. S'il est vide, cliquer **Check changes** sur la ligne de cette page. Cela compare la version stockée à son état actuel et archive l'ancienne version en cas de différence.

Les révisions créées par cette vérification différée ont `actor_id=0` (auteur inconnu). Le plugin ne prétend pas connaître l'utilisateur qui a réellement enregistré la modification.

## Diagnostics

- Aucune page sous **Tracked pages** : le suivi n'a pas été initialisé dans la base du site, ou l'ancien formulaire a échoué. Initier une page existante.
- La page est suivie et **Check changes** ajoute une révision, mais pas le callback : le dispatch ou le chargement du callback `plugin_itemsaved_revisions` doit être diagnostiqué sur l'installation de test.
- La page est suivie, mais **Check changes** ne produit pas de révision : vérifier que le contenu a réellement changé et que la page ne comporte pas de code PHP/modèle.
- Le plugin enregistre uniquement **l'ancienne version** (la précédente baseline), pas un duplicata de la version actuelle.
- En cas d'échec SQL consulter les journaux Geeklog. Ne pas activer sur les sites de production.

## Garanties non acquises

Ce pilote n'est **pas transactionnel** sur l'ensemble des écritures Static Pages (MyISAM), le callback ne couvre pas tous les chemins d'écriture, et la synchronisation manuelle n'est pas une preuve que la sauvegarde a été capturée instantanément. Pas de fonction Restore en 0.1.1.

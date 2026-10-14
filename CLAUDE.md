# tarifs-ia

Table des prix des API d'IA, publiée (dépôt public) et lue par Analytics Hub et
GéoPrestations. Voir README.md.

- `modeles.json` : liste des modèles suivis (source de vérité éditée à la main).
- `tarifs-ia.json` : **produit** par `php tools/extraire.php --ecrire`, jamais édité à la main.
- `lib/tarifs.php` : fonctions pures ; tests `php tests/run.php` (sans PHPUnit).
- Mise à jour : skill `/maj-tarifs` ; rien n'est poussé sans accord de l'utilisateur
  (un push sur `main` est lu par tous les sites).
- Format publié `dc-tarifs-ia/1` : tout changement incompatible demande un nouveau
  schéma (`/2`) et une période de double publication, les plugins installés lisant
  l'ancien.
- Code et commentaires en français.

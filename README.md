# Tarifs IA

Table unique des prix des API d'IA utilisées par les outils Dynamic Creative
(Analytics Hub, GéoPrestations). Un seul fichier publié, lu par chaque outil :

```
https://raw.githubusercontent.com/dynamiccreative/tarifs-ia/main/tarifs-ia.json
```

## Contenu de `tarifs-ia.json`

Clé de chaque modèle : `fournisseur/modèle` (convention de GéoPrestations :
`claude/…`, `openai/…`, `openai_image/…`, `gemini/…`, `gemini_image/…`,
`google_maps/…`, `google_translate/…`).

| Champ | Sens |
|---|---|
| `unite` | `tokens`, `image`, `requete` ou `appel` |
| `entree`, `sortie` | USD par million de tokens (texte) |
| `cache_ecriture`, `cache_lecture` | USD par million de tokens, tels que publiés (jamais déduits d'une proportion) |
| `par_appel` | USD par appel : estimation pour le texte (hypothèse `hypotheses.appel_texte`), prix réel pour une image ou une requête |
| `statut` | `actuel` ou `ancien` (conservé pour chiffrer l'historique) |
| `origine` | `litellm:<clé>` ou `manuel` (alors `source` et `verifie_le` sont renseignés) |

## Sources

1. **LiteLLM** (`model_prices_and_context_window.json`, BerriAI) : source principale,
   la plus complète (cache, images, anciens modèles). La version utilisée est notée
   dans `litellm.commit`.
2. **aitokenprice.com** (`/api/models.json`, CC BY 4.0) : contrôle croisé. Prix
   vérifiés sur les pages officielles, statut et successeur de chaque modèle.
3. **Saisie manuelle** dans `modeles.json` pour ce que LiteLLM ne couvre pas
   (Google Maps et Translate, images OpenAI au prix par image, anciens modèles).

## Mettre à jour

Dans Claude Code, depuis ce dossier : `/maj-tarifs`. À la main :

```bash
php tools/extraire.php            # relecture : écarts et contrôle croisé, rien n'est écrit
php tools/extraire.php --ecrire   # écrit tarifs-ia.json si tout est valide
php tests/run.php
git commit -am "Tarifs du AAAA-MM-JJ" && git push
```

Ajouter ou retirer un modèle suivi : modifier `modeles.json`, puis relancer.
Un modèle suivi qui disparaît de LiteLLM arrête l'extraction (jamais de prix à 0
silencieux).

## Côté outils

Chaque outil lit ce fichier en cache (Hub : chaque jour ; plugins : chaque semaine),
garde une copie embarquée en secours, et laisse les réglages manuels de son écran
d'administration prendre le pas.

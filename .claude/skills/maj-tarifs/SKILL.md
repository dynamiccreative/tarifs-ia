---
name: maj-tarifs
description: Met à jour tarifs-ia.json (prix des API d'IA lus par Analytics Hub et GéoPrestations) depuis LiteLLM, avec contrôle croisé aitokenprice.com, relecture des écarts par l'utilisateur avant commit. À utiliser quand l'utilisateur tape /maj-tarifs, demande de mettre à jour les tarifs IA, ou quand le Hub signale des tarifs anciens ou un modèle sans prix.
---

# Mise à jour des tarifs IA

Dépôt public `dynamiccreative/tarifs-ia`. Tout push sur `main` est lu par les outils
(Hub chaque jour, plugins chaque semaine) : **rien ne part sans l'accord explicite
de l'utilisateur**.

1. `git pull`, puis `php tools/extraire.php` (relecture, n'écrit rien).
2. Présenter à l'utilisateur, en français et de façon lisible :
   - les **écarts** avec la version publiée (prix modifiés, modèles ajoutés ou retirés) ;
   - le **contrôle croisé** : tout écart de prix entre LiteLLM et aitokenprice est à
     trancher sur la page officielle du fournisseur (Anthropic :
     https://platform.claude.com/docs/en/about-claude/pricing ; OpenAI :
     https://openai.com/api/pricing/ ; Google : https://ai.google.dev/gemini-api/docs/pricing).
     Le vérifier soi-même et dire quelle source a raison ;
   - les **successeurs** signalés : proposer d'ajouter le nouveau modèle à `modeles.json`
     (et de passer l'ancien en `"statut": "ancien"` seulement s'il n'est plus utilisé).
3. En cas d'erreur (modèle suivi absent de LiteLLM) : proposer soit sa nouvelle clé
   LiteLLM, soit une entrée `manuel` avec `source` et `verifie_le`.
4. Revoir aussi les entrées `manuel` dont `verifie_le` a plus de 6 mois.
5. Après accord : modifier `modeles.json` si besoin, `php tools/extraire.php --ecrire`,
   `php tests/run.php`, ligne dans `CHANGELOG.md`, commit « Tarifs du AAAA-MM-JJ »,
   push sur `main`.

Ne jamais modifier `tarifs-ia.json` à la main : il est produit par le script.

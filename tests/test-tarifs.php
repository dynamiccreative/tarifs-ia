<?php
/**
 * Construction de tarifs-ia.json à partir de modeles.json et de LiteLLM.
 *
 * Ce que ces tests protègent :
 *  1. la conversion des prix LiteLLM (au token) en prix au million de tokens,
 *     cache compris, sans règle de proportion inventée ;
 *  2. l'estimation par appel de GéoPrestations (hypothèse de tokens explicite) ;
 *  3. les valeurs manuelles reprises telles quelles, avec leur source ;
 *  4. l'arrêt net si un modèle suivi disparaît de LiteLLM (jamais de 0 silencieux) ;
 *  5. la vérification du fichier publié et le relevé des écarts.
 *
 * Lancement : php tests/test-tarifs.php
 */

require_once __DIR__ . '/bootstrap.php';

$litellm = [
    'claude-opus-5-5' => [
        'input_cost_per_token' => 4.0E-6, 'output_cost_per_token' => 2.0E-5,
        'cache_creation_input_token_cost' => 5.0E-6, 'cache_read_input_token_cost' => 2.0E-7,
        'mode' => 'chat',
    ],
    'gemini-3.1-flash-image' => [
        'input_cost_per_token' => 5.0E-7, 'output_cost_per_token' => 3.0E-6,
        'output_cost_per_image' => 0.0672, 'mode' => 'image_generation',
    ],
];
$config = [
    'hypotheses' => [ 'appel_texte' => [ 'entree' => 3000, 'sortie' => 2500 ] ],
    'modeles'    => [
        'claude/claude-opus-5-5'              => [ 'litellm' => 'claude-opus-5-5' ],
        'gemini_image/gemini-3.1-flash-image' => [ 'litellm' => 'gemini-3.1-flash-image' ],
        'google_maps/places_details'          => [ 'manuel' => [ 'unite' => 'requete', 'par_appel' => 0.017 ], 'source' => 'https://exemple', 'verifie_le' => '2026-07-01' ],
        'claude/claude-sonnet-4'              => [ 'manuel' => [ 'unite' => 'appel', 'par_appel' => 0.018 ], 'statut' => 'ancien', 'source' => 'GéoPrestations 3.1.6', 'verifie_le' => '2026-07-01' ],
    ],
];

echo "Construction\n";
[ $t, $erreurs ] = tarifs_construire( $config, $litellm, '2026-10-14', [ 'commit' => 'abc1234', 'date' => '2026-10-13' ] );
check( 'aucune erreur', $erreurs, [] );
check( 'schéma et date', [ $t['schema'], $t['genere_le'] ], [ 'dc-tarifs-ia/1', '2026-10-14' ] );
check( 'référence LiteLLM conservée', $t['litellm'], [ 'commit' => 'abc1234', 'date' => '2026-10-13' ] );

$o = $t['modeles']['claude/claude-opus-5-5'];
check( 'texte : prix au million de tokens', [ $o['unite'], $o['entree'], $o['sortie'] ], [ 'tokens', 4.0, 20.0 ] );
check( 'texte : cache repris de LiteLLM (0,20 $, pas 10 % de l\'entrée)', [ $o['cache_ecriture'], $o['cache_lecture'] ], [ 5.0, 0.2 ] );
check( 'texte : estimation par appel (3 000 + 2 500 tokens)', $o['par_appel'], 0.062 );
check( 'texte : origine et statut', [ $o['origine'], $o['statut'] ], [ 'litellm:claude-opus-5-5', 'actuel' ] );

$i = $t['modeles']['gemini_image/gemini-3.1-flash-image'];
check( 'image LiteLLM : prix par image', [ $i['unite'], $i['par_appel'] ], [ 'image', 0.0672 ] );

$m = $t['modeles']['google_maps/places_details'];
check( 'manuel : valeur, source et date', [ $m['unite'], $m['par_appel'], $m['origine'], $m['source'], $m['verifie_le'] ], [ 'requete', 0.017, 'manuel', 'https://exemple', '2026-07-01' ] );
check( 'manuel : statut « ancien » conservé', $t['modeles']['claude/claude-sonnet-4']['statut'], 'ancien' );

echo "\nModèle disparu de LiteLLM\n";
$config_cassee = $config;
$config_cassee['modeles']['openai/gpt-9'] = [ 'litellm' => 'gpt-9' ];
[ , $erreurs ] = tarifs_construire( $config_cassee, $litellm, '2026-10-14', [] );
check( 'erreur nommée, pas de prix à 0', $erreurs, [ 'openai/gpt-9 : « gpt-9 » absent de LiteLLM' ] );
$config_cassee = $config;
$config_cassee['modeles']['x/sans-prix'] = [];
[ , $erreurs ] = tarifs_construire( $config_cassee, $litellm, '2026-10-14', [] );
check( 'entrée sans source de prix : erreur', $erreurs, [ 'x/sans-prix : ni « litellm » ni « manuel »' ] );

echo "\nVérification du fichier publié\n";
check( 'fichier construit valide', tarifs_verifier( $t ), [] );
$faux = $t;
$faux['modeles']['claude/claude-opus-5-5']['par_appel'] = -1;
check( 'prix négatif refusé', tarifs_verifier( $faux ), [ 'claude/claude-opus-5-5 : par_appel absent ou négatif' ] );
$faux = $t;
unset( $faux['modeles']['google_maps/places_details']['par_appel'] );
check( 'par_appel manquant refusé', tarifs_verifier( $faux ), [ 'google_maps/places_details : par_appel absent ou négatif' ] );
check( 'mauvais schéma refusé', tarifs_verifier( [ 'schema' => 'autre' ] ), [ 'schéma attendu : dc-tarifs-ia/1', 'aucun modèle' ] );

echo "\nÉcarts avec la version précédente\n";
$nouveau = $t;
$nouveau['modeles']['claude/claude-opus-5-5']['sortie'] = 25.0;
unset( $nouveau['modeles']['claude/claude-sonnet-4'] );
$nouveau['modeles']['openai/gpt-6'] = [ 'unite' => 'tokens', 'entree' => 1.0, 'sortie' => 2.0, 'par_appel' => 0.008, 'statut' => 'actuel', 'origine' => 'litellm:gpt-6' ];
check( 'ajout, retrait et prix modifié relevés', tarifs_ecarts( $t, $nouveau ), [
    'ajouté   openai/gpt-6 (par appel 0.008)',
    'modifié  claude/claude-opus-5-5 : sortie 20 → 25',
    'retiré   claude/claude-sonnet-4',
] );
check( 'aucun écart', tarifs_ecarts( $t, $t ), [] );

echo "\nContrôle croisé avec aitokenprice.com\n";
$atp = [
    [ 'apiId' => 'claude-opus-5-5', 'status' => 'current', 'replacedBy' => null, 'price' => [ 'input' => 4, 'output' => 20, 'cacheRead' => 0.2 ], 'verified' => '2026-10-13' ],
    [ 'apiId' => 'claude-sonnet-4', 'status' => 'retired', 'replacedBy' => 'claude-sonnet-5', 'price' => [ 'input' => 3, 'output' => 15 ] ],
];
check( 'prix concordants : rien à signaler', tarifs_controle_croise( $t, $atp ), [] );
$atp[0]['price']['output'] = 25;
$atp[0]['replacedBy']      = 'claude-opus-6';
check( 'écart de prix et successeur signalés (modèles « ancien » ignorés)', tarifs_controle_croise( $t, $atp ), [
    'prix     claude/claude-opus-5-5 : sortie 20 ici, 25 sur aitokenprice (vérifié le 2026-10-13)',
    'successeur claude/claude-opus-5-5 → claude-opus-6',
] );
$atp[0]['price']['output'] = 20;
$atp[0]['replacedBy']      = 'claude-opus-5-5';
check( 'successeur déjà suivi : pas signalé', tarifs_controle_croise( $t, $atp ), [] );
$atp[0]['replacedBy'] = 'claude-opus-5.5';
check( 'successeur écrit avec des points : reconnu', tarifs_controle_croise( $t, $atp ), [] );

tests_bilan();

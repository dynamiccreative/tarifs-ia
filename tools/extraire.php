<?php
/**
 * Régénère tarifs-ia.json depuis modeles.json et LiteLLM, avec contrôle croisé
 * aitokenprice.com. N'écrit rien sans --ecrire : affiche d'abord les écarts.
 *
 *   php tools/extraire.php            relecture (écarts, contrôle croisé)
 *   php tools/extraire.php --ecrire   écrit tarifs-ia.json si tout est valide
 *
 * Options hors ligne : --litellm=fichier.json --atp=fichier.json
 * Code retour : 0 sans erreur, 1 si un modèle suivi n'a plus de prix ou si le
 * fichier produit est invalide (rien n'est écrit dans ce cas).
 */

require_once dirname( __DIR__ ) . '/lib/tarifs.php';

const URL_LITELLM  = 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json';
const URL_COMMITS  = 'https://api.github.com/repos/BerriAI/litellm/commits?path=model_prices_and_context_window.json&per_page=1';
const URL_ATP      = 'https://aitokenprice.com/api/models.json';

$racine  = dirname( __DIR__ );
$options = getopt( '', [ 'ecrire', 'litellm:', 'atp:' ] );

function lire_json( $source ) {
    $ctx = stream_context_create( [ 'http' => [ 'header' => "User-Agent: dc-tarifs-ia\r\n", 'timeout' => 30 ] ] );
    $brut = @file_get_contents( $source, false, $ctx );
    if ( $brut === false ) return null;
    $data = json_decode( $brut, true );
    return is_array( $data ) ? $data : null;
}

// Sources.
$config = lire_json( $racine . '/modeles.json' );
if ( ! $config ) { fwrite( STDERR, "modeles.json illisible.\n" ); exit( 1 ); }

$litellm = lire_json( $options['litellm'] ?? URL_LITELLM );
if ( ! $litellm ) { fwrite( STDERR, "Prix LiteLLM illisibles.\n" ); exit( 1 ); }

$ref = [];
if ( ! isset( $options['litellm'] ) ) {
    $commits = lire_json( URL_COMMITS );
    if ( isset( $commits[0]['sha'] ) ) {
        $ref = [ 'commit' => substr( $commits[0]['sha'], 0, 12 ), 'date' => substr( (string) ( $commits[0]['commit']['committer']['date'] ?? '' ), 0, 10 ) ];
    }
}

// Construction et contrôles.
[ $tarifs, $erreurs ] = tarifs_construire( $config, $litellm, gmdate( 'Y-m-d' ), $ref );
$problemes = tarifs_verifier( $tarifs );

$precedent = lire_json( $racine . '/tarifs-ia.json' ) ?: [];
$ecarts    = tarifs_ecarts( $precedent, $tarifs );

$atp      = lire_json( $options['atp'] ?? URL_ATP );
$croise   = is_array( $atp ) ? tarifs_controle_croise( $tarifs, $atp['models'] ?? $atp ) : null;

// Rapport.
echo 'Modèles suivis : ' . count( $tarifs['modeles'] ) . ( $ref ? ' — LiteLLM ' . $ref['commit'] . ' du ' . $ref['date'] : '' ) . "\n";
echo "\nÉcarts avec tarifs-ia.json :\n" . ( $ecarts ? '  ' . implode( "\n  ", $ecarts ) : '  aucun' ) . "\n";
echo "\nContrôle croisé aitokenprice.com :\n";
echo $croise === null ? "  source injoignable (contrôle non fait)\n" : ( $croise ? '  ' . implode( "\n  ", $croise ) : '  rien à signaler' ) . "\n";
if ( $erreurs || $problemes ) {
    echo "\nERREURS (rien n'est écrit) :\n  " . implode( "\n  ", array_merge( $erreurs, $problemes ) ) . "\n";
    exit( 1 );
}

if ( isset( $options['ecrire'] ) ) {
    file_put_contents( $racine . '/tarifs-ia.json', json_encode( $tarifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ) . "\n" );
    echo "\ntarifs-ia.json écrit.\n";
} else {
    echo "\nRelecture seulement : relancer avec --ecrire pour enregistrer.\n";
}

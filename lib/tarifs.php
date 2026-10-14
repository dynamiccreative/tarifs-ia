<?php
/**
 * Tarifs IA — fonctions pures : construction de tarifs-ia.json à partir de
 * modeles.json et du fichier de prix de LiteLLM, vérification, écarts.
 *
 * Testées par tests/test-tarifs.php ; utilisées par tools/extraire.php.
 */

const TARIFS_SCHEMA = 'dc-tarifs-ia/1';
const TARIFS_UNITES = [ 'tokens', 'image', 'requete', 'appel' ];

/** Prix LiteLLM au token → prix au million de tokens. */
function tarifs_par_million( $prix_token ) {
    return round( (float) $prix_token * 1000000, 6 );
}

/** Nombre lisible (sans zéros inutiles) pour les écarts. */
function tarifs_nombre( $v ) {
    return rtrim( rtrim( sprintf( '%.6f', (float) $v ), '0' ), '.' );
}

/**
 * Entrée publiée pour un modèle repris de LiteLLM, ou null si inexploitable.
 * Texte : prix au million de tokens, cache tel que publié (jamais déduit par
 * une règle de proportion), plus l'estimation par appel de GéoPrestations.
 * Image : prix par image quand LiteLLM le donne.
 */
function tarifs_depuis_litellm( array $e, array $hypothese ) {
    if ( ( $e['mode'] ?? '' ) === 'image_generation' && isset( $e['output_cost_per_image'] ) ) {
        return [ 'unite' => 'image', 'par_appel' => round( (float) $e['output_cost_per_image'], 6 ) ];
    }
    if ( ! isset( $e['input_cost_per_token'], $e['output_cost_per_token'] ) ) return null;

    $entree = tarifs_par_million( $e['input_cost_per_token'] );
    $sortie = tarifs_par_million( $e['output_cost_per_token'] );
    $out    = [ 'unite' => 'tokens', 'entree' => $entree, 'sortie' => $sortie ];
    if ( isset( $e['cache_creation_input_token_cost'] ) ) $out['cache_ecriture'] = tarifs_par_million( $e['cache_creation_input_token_cost'] );
    if ( isset( $e['cache_read_input_token_cost'] ) )     $out['cache_lecture']  = tarifs_par_million( $e['cache_read_input_token_cost'] );
    $out['par_appel'] = round( ( (int) $hypothese['entree'] * $entree + (int) $hypothese['sortie'] * $sortie ) / 1000000, 6 );
    return $out;
}

/**
 * Construit le fichier publié.
 *
 * @param array $config  Contenu de modeles.json.
 * @param array $litellm Contenu de model_prices_and_context_window.json.
 * @param string $date   Date de génération (AAAA-MM-JJ).
 * @param array $ref     Version de LiteLLM utilisée (commit, date).
 * @return array{0: array, 1: string[]} fichier et erreurs (bloquantes).
 */
function tarifs_construire( array $config, array $litellm, $date, array $ref ) {
    $hypothese = $config['hypotheses']['appel_texte'] ?? [ 'entree' => 0, 'sortie' => 0 ];
    $modeles   = [];
    $erreurs   = [];

    foreach ( (array) ( $config['modeles'] ?? [] ) as $cle => $def ) {
        if ( isset( $def['litellm'] ) ) {
            $source = $litellm[ $def['litellm'] ] ?? null;
            $entree = is_array( $source ) ? tarifs_depuis_litellm( $source, $hypothese ) : null;
            if ( $entree === null ) {
                $erreurs[] = $cle . ' : « ' . $def['litellm'] . ' » ' . ( is_array( $source ) ? 'sans prix exploitable dans LiteLLM' : 'absent de LiteLLM' );
                continue;
            }
            $entree['origine'] = 'litellm:' . $def['litellm'];
        } elseif ( isset( $def['manuel'] ) && is_array( $def['manuel'] ) ) {
            $entree = [
                'unite'     => (string) ( $def['manuel']['unite'] ?? 'appel' ),
                'par_appel' => round( (float) ( $def['manuel']['par_appel'] ?? -1 ), 6 ),
                'origine'   => 'manuel',
            ];
        } else {
            $erreurs[] = $cle . ' : ni « litellm » ni « manuel »';
            continue;
        }
        $entree['statut'] = (string) ( $def['statut'] ?? 'actuel' );
        foreach ( [ 'source', 'verifie_le', 'note' ] as $champ ) {
            if ( isset( $def[ $champ ] ) ) $entree[ $champ ] = (string) $def[ $champ ];
        }
        $modeles[ $cle ] = $entree;
    }

    return [ [
        'schema'     => TARIFS_SCHEMA,
        'genere_le'  => (string) $date,
        'litellm'    => $ref,
        'hypotheses' => [ 'appel_texte' => [ 'entree' => (int) $hypothese['entree'], 'sortie' => (int) $hypothese['sortie'] ] ],
        'modeles'    => $modeles,
    ], $erreurs ];
}

/** Contrôle du fichier publié : liste des problèmes (vide si valide). */
function tarifs_verifier( array $t ) {
    $problemes = [];
    if ( ( $t['schema'] ?? '' ) !== TARIFS_SCHEMA ) $problemes[] = 'schéma attendu : ' . TARIFS_SCHEMA;
    if ( empty( $t['modeles'] ) || ! is_array( $t['modeles'] ) ) {
        $problemes[] = 'aucun modèle';
        return $problemes;
    }
    foreach ( $t['modeles'] as $cle => $m ) {
        if ( ! preg_match( '#^[a-z_]+/[A-Za-z0-9._-]+$#', (string) $cle ) ) $problemes[] = $cle . ' : clé « fournisseur/modèle » invalide';
        if ( ! in_array( $m['unite'] ?? '', TARIFS_UNITES, true ) )        $problemes[] = $cle . ' : unité inconnue';
        if ( ! isset( $m['par_appel'] ) || ! is_numeric( $m['par_appel'] ) || $m['par_appel'] < 0 ) {
            $problemes[] = $cle . ' : par_appel absent ou négatif';
        }
        if ( ( $m['unite'] ?? '' ) === 'tokens' && ( ! isset( $m['entree'], $m['sortie'] ) || $m['entree'] < 0 || $m['sortie'] < 0 ) ) {
            $problemes[] = $cle . ' : prix d\'entrée ou de sortie manquant';
        }
    }
    return $problemes;
}

/**
 * Contrôle croisé avec aitokenprice.com (`/api/models.json`, CC BY 4.0) :
 * prix vérifiés sur les pages officielles, statut et successeur de chaque
 * modèle. Signale les prix qui divergent (à trancher sur la page officielle)
 * et les modèles actuels déjà remplacés. Les modèles « ancien » sont ignorés.
 *
 * @param array $t   Fichier construit.
 * @param array $atp Liste des modèles d'aitokenprice.
 */
function tarifs_controle_croise( array $t, array $atp ) {
    $index = [];
    foreach ( $atp as $m ) {
        if ( isset( $m['apiId'] ) ) $index[ (string) $m['apiId'] ] = $m;
    }
    $suivis = [];
    foreach ( array_keys( (array) ( $t['modeles'] ?? [] ) ) as $cle ) {
        $suivis[] = tarifs_id_normalise( substr( (string) $cle, strpos( (string) $cle, '/' ) + 1 ) );
    }
    $champs = [ 'entree' => 'input', 'sortie' => 'output', 'cache_lecture' => 'cacheRead' ];
    $lignes = [];
    foreach ( (array) ( $t['modeles'] ?? [] ) as $cle => $m ) {
        if ( ( $m['statut'] ?? '' ) === 'ancien' || ( $m['unite'] ?? '' ) !== 'tokens' ) continue;
        $id = substr( (string) $cle, strpos( (string) $cle, '/' ) + 1 );
        if ( ! isset( $index[ $id ] ) ) continue;
        $ref = $index[ $id ];
        foreach ( $champs as $ici => $la ) {
            if ( ! isset( $m[ $ici ], $ref['price'][ $la ] ) ) continue;
            if ( abs( (float) $m[ $ici ] - (float) $ref['price'][ $la ] ) > 1e-6 ) {
                $lignes[] = 'prix     ' . $cle . ' : ' . $ici . ' ' . tarifs_nombre( $m[ $ici ] ) . ' ici, '
                    . tarifs_nombre( $ref['price'][ $la ] ) . ' sur aitokenprice'
                    . ( isset( $ref['verified'] ) ? ' (vérifié le ' . $ref['verified'] . ')' : '' );
            }
        }
        // aitokenprice écrit « gpt-5-6-sol » là où l'API dit « gpt-5.6-sol ».
        if ( ! empty( $ref['replacedBy'] ) && ! in_array( tarifs_id_normalise( $ref['replacedBy'] ), $suivis, true ) ) {
            $lignes[] = 'successeur ' . $cle . ' → ' . $ref['replacedBy'];
        }
    }
    return $lignes;
}

/** Identifiant de modèle comparable entre sources (points et tirets confondus). */
function tarifs_id_normalise( $id ) {
    return strtolower( str_replace( '.', '-', (string) $id ) );
}

/** Écarts entre deux versions publiées, pour la relecture avant commit. */
function tarifs_ecarts( array $avant, array $apres ) {
    $a = (array) ( $avant['modeles'] ?? [] );
    $b = (array) ( $apres['modeles'] ?? [] );
    $lignes = [];
    foreach ( array_diff_key( $b, $a ) as $cle => $m ) {
        $lignes[] = 'ajouté   ' . $cle . ' (par appel ' . tarifs_nombre( $m['par_appel'] ?? 0 ) . ')';
    }
    foreach ( array_intersect_key( $b, $a ) as $cle => $m ) {
        foreach ( [ 'unite', 'entree', 'sortie', 'cache_ecriture', 'cache_lecture', 'par_appel', 'statut' ] as $champ ) {
            $v1 = $a[ $cle ][ $champ ] ?? null;
            $v2 = $m[ $champ ] ?? null;
            if ( $v1 === $v2 || ( is_numeric( $v1 ) && is_numeric( $v2 ) && abs( $v1 - $v2 ) < 1e-9 ) ) continue;
            $f = function ( $v ) { return $v === null ? '—' : ( is_numeric( $v ) ? tarifs_nombre( $v ) : (string) $v ); };
            $lignes[] = 'modifié  ' . $cle . ' : ' . $champ . ' ' . $f( $v1 ) . ' → ' . $f( $v2 );
        }
    }
    foreach ( array_diff_key( $a, $b ) as $cle => $m ) {
        $lignes[] = 'retiré   ' . $cle;
    }
    return $lignes;
}

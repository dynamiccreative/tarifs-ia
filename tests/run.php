<?php
/**
 * Lance tous les tests : php tests/run.php (code retour non nul en cas d'échec).
 */

$echecs = 0;
foreach ( glob( __DIR__ . '/test-*.php' ) as $fichier ) {
    $sortie = [];
    exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $fichier ), $sortie, $code );
    $bilan = end( $sortie );
    printf( "  %-4s %-20s %s\n", $code === 0 ? 'OK' : 'FAIL', basename( $fichier, '.php' ), $bilan );
    if ( $code !== 0 ) {
        $echecs++;
        echo implode( "\n", array_filter( $sortie, function ( $l ) { return strpos( $l, 'FAIL' ) !== false || strpos( $l, 'attendu' ) !== false || strpos( $l, 'obtenu' ) !== false; } ) ) . "\n";
    }
}
exit( $echecs > 0 ? 1 : 0 );

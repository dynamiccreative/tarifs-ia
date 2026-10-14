<?php
/**
 * Harnais minimal : check( libellé, obtenu, attendu ) et bilan.
 */

$GLOBALS['__ok']   = 0;
$GLOBALS['__fail'] = 0;

function check( $label, $actual, $expected = true ) {
    if ( $actual === $expected ) {
        $GLOBALS['__ok']++;
        echo "  OK   $label\n";
        return;
    }
    $GLOBALS['__fail']++;
    echo "  FAIL $label\n       obtenu  : " . var_export( $actual, true ) . "\n       attendu : " . var_export( $expected, true ) . "\n";
}

function tests_bilan() {
    echo "\n" . $GLOBALS['__ok'] . ' OK, ' . $GLOBALS['__fail'] . " FAIL\n";
    exit( $GLOBALS['__fail'] > 0 ? 1 : 0 );
}

require_once dirname( __DIR__ ) . '/lib/tarifs.php';

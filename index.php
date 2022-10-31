<?php

require('bibli_params.php');
require('bibli_generale.php');

# affichage de tous les erreurs dans le navigateur
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting( E_ALL );


#################  start try
ob_start();

htmlDebut('les etudiants');

$bd = bdConnect();

//-- Requête ----------------------------------------
$sql = 'SELECT * FROM  etudiant';
$r = mysqli_query($bd, $sql);

//-- Traitement -------------------------------------
while($enr = mysqli_fetch_assoc($r)){
    foreach($enr as $var => $val){
        echo $var, ':', $val, "    ";
    }
    echo '<br>';
}

// Libération de la mémoire associée au résultat de la requête
mysqli_free_result($r);
//-- Déconnexion ------------------------------------
mysqli_close($bd);

htmlFin();
##################### end try



?>
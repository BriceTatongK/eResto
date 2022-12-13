<?php

/*
***********************************************
* constantes et paramètres du project eRestoU *
*********************************************** 
*/


# affichage de tous les erreurs dans le navigateur
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting( E_ALL );


define('IS_DEV', true); // true en phase de développement, false en phase de production.

define('BD_SERVER', 'localhost'); // nom d'hôte ou adresse IP du serveur de base de données
define('BD_NAME', 'eRestoU_bd'); // nom de la base sur le serveur de base de données
define('BD_USER', 'eRestoU_user'); // nom de l'utilisateur de la base
define('BD_PASS', 'eRestoU_pass'); // mot de passe de l'utilisateur de la base



?>

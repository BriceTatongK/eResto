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


// Définit le fuseau horaire par défaut à utiliser. Disponible depuis PHP 5.1
date_default_timezone_set('Europe/Paris');

//set locale
setlocale(LC_ALL, 'fr_FR@euro', 'fr_FR');

// Définit la date d'aujourd'hui au format AAAAMMJJ
define ('DATE_AUJOURDHUI', date('Ymd'));
define ('ANNEE_MIN', date('Y')-1);
define ('ANNEE_MAX', date('Y')+1);

// Nombre de plats de catégorie 'boisson'
define ('NB_CAT_BOISSON', 4);
// Nombre de plats de catégorie 'divers'
define ('NB_CAT_DIVERS', 2);

define('NB_ANNEE_DATE_NAISSANCE', 100);
define('AGE_MINIMUM', 16);

// limites liées aux tailles des champs de la table etudiant
define('LMAX_LOGIN', 8);    // taille du champ etLogin de la table etudiant
define('LMAX_NOM',50);      // taille du champ etNom de la table etudiant
define('LMAX_PRENOM',80);   // taille du champ etPrenom de la table etudiant

define('LMIN_LOGIN', 4);



?>
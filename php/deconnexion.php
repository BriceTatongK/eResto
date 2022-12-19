<?php

session_start();

if (isset($_SESSION['etNumero'])) {

    unset($_SESSION['etNumero']);
    unset($_SESSION['etLogin']);

    // url de redirection
    $url = (isset($_SESSION['url']))?$_SESSION['url']:'./connexion.php';

    // libèrer les variables de session
    unset($_SESSION['url']);

    // destruction de la session
    session_destroy();

    // redirection vers l'url
    header('Location: '.$url);
    exit;
}

//header('Location: menu.php');

?>
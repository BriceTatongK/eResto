<?php

require_once('bibli_generale.php');
require_once('bibli_eRestoU.php');

session_start();
ob_start();

//$_SESSION['url'] = $_SERVER['REQUEST_URI']; // enregistre url courante.
$errs = [];
if (isset($_POST['btnConnexion'])) {
    var_dump($_POST);
    # validation des champs du form reçus.
    # appel la fonction de validation qui exit du script s'il ya potentiel error.
    if ( !isset($_POST['login']) || !isset($_POST['password']) ) {
        $errs[] = 'enter tout';
    }
    # les clés sont valides

    # les tests suivants seront placés ici
    // test du login
    $nom = trim($_POST['login']);
    $l = mb_strlen($nom, encoding:'UTF-8');
    if ($l < 2 || $l > 8) {
        $errs[] = 'Le nom doit contenir entre 2 et 8 caractères';
    }
    $noTags = strip_tags($nom);
    if ($noTags != $nom){
        $errs[] = 'Le nom ne doit pas contenir de tags HTML';
    }

    // test de la password
    if (strlen($_POST['password']) == 0) {
        $errs[] = '';
    }

    if (count($errs) > 0){
        echo '<p>Des erreurs ont été détectées :';
        foreach($errs as $err){
            echo '<br>- ', $err;
        }
        echo '</p>';
    }
    else {
        echo '<p>Ok, les données saisies sont valides.</p>';
    }
}
//else {
//    header('location: connexion.php'); // si le button set n'a pas été pressé, redirection vers la page de connexion.php
//}


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



echo
'<!doctype html>',
'<html lang="fr">',

'<head>',
    '<meta charset="UTF-8">',
    '<title>eRestoU | Connexion</title>',
    '<link rel="stylesheet" type="text/css" href="../styles/eResto.css">',
'</head>',

'<body>',
    '<main>',
        '<header>',
            '<h1>Connexion</h1><a href="http://www.crous-bfc.fr" target="_blank"></a><a href="http://www.univ-fcomte.fr" target="_blank"></a></header>',
        '<nav>',
            '<ul>',
                '<li><a href="../index.php">Accueil</a></li>',
                '<li><a href="../php/menu.php">Menus et repas</a></li>',
                '<li><a href="../php/connexion.php">Connexion</a></li>',
            '</ul>',
        '</nav>',
        '<section>',
            '<h3>Formulaire de connexion</h3>',
            '<p>Pour vous identifier, remplissez le formulaire ci-dessous.</p>',

// call fonction afficheErreur($errs); comme parametre l'array des erreurs

            '<form action="connexion.php" method="post">',
                '<table>',
                    '<tr>',
                        '<td><label for="txtPseudo">Login ENT :</label></td>',
                        '<td><input type="text" name="login" id="txtPseudo" value=""></td>',
                    '</tr>',
                    '<tr>',
                        '<td><label for="txtPassword">Mot de passe :</label></td>',
                        '<td><input type="password" name="password" id="txtPassword"></td>',
                    '</tr>',
                    '<tr>',
                        '<td colspan="2"><input type="submit" name="btnConnexion" value="Se connecter"><input type="reset" value="Annuler"></td>',
                    '</tr>',
                '</table><input type="hidden" name="redirection" value="http://localhost:8888/2020-2021/EAD/eRestoU/index.php">',
            '</form>',
            '<p>Pas encore inscrit ? N\'attendez pas, <a href="inscription.php">inscrivez-vous</a> !</p>',
        '</section>',
        '<footer>&copy; Master Info EAD - Octobre 2022 - Université de Franche-Comté - CROUS de Franche-Comté</footer>',
    '</main>',
'</body>',

'</html>';


?>
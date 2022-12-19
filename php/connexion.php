<?php

require_once('bibli_generale.php');
require_once('bibli_eRestoU.php');

// bufferisation
ob_start();

// nouvelle session
session_start();

$Errs = []; // array pour les erreurs

$Nom = ''; // nom de login

$Pass = ''; // mot de pass

UtilChampDonneeCONN($Errs, $Nom, $Pass);

##############################
########### CONTENU DE LA PAGE
##############################

// affiche en tète
AffEntete('eRestoU | Connexion', '../styles/eResto.css');

// affiche menu navigation
AffMenuNavigation((isset($_SESSION['etLogin']))?$_SESSION['etLogin']:'', '..');

// affiche le contenu de la et les eventuelles erreurs de connexion
AffContenuConn($Errs, $Nom, $Pass);

// pied de page
AffPiedDePage();

// envoi du buffer
ob_end_flush();





//________________________________________
/**
 * check les camps
 * @param   array   &$errs   |s
 * @param   string  &$nom    |
 * @param   string  &$pass   |
 * @return  void
 */
function UtilChampDonneeCONN(&$errs, &$nom, &$pass): void{

    if (isset($_POST['btnConnexion'])) {

        //var_dump($_POST);
        # validation des champs du form reçus.
        # appel la fonction de validation qui exit du script s'il ya potentiel error.
        if ( !isset($_POST['login']) || !isset($_POST['password']) ) {
            $errs[] = '=> entrer toutes les informations';
            return;
        }
        
        // test du login
        if (strlen($_POST['login']) > 0) {
    
            // test du login
            $nom = trim($_POST['login']);
            $l = mb_strlen($nom, encoding:'UTF-8');
    
            if ($l < 2 || $l > 8) {
                $errs[] = '=> Le nom doit contenir entre 2 et 8 caractères';
            }
    
            $noTags = strip_tags($nom);
            if ($noTags != $nom){
                $errs[] = '=> Le nom ne doit pas contenir de tags HTML';
            }
        }
        else {
            $errs[] = '=> entrer le login';
        }
    
        // test de la password
        if (strlen($_POST['password']) == 0) {
            $errs[] = '=> entrer le mot de passe';
        }else
            $pass = $_POST['password'];
    
        // check errors
        if (count($errs) > 0){
            return;
        }
        else {
    
            // objet connexion
            $bd = bdConnect();
    
            //-- Requête ----------------------------------------
            $nom = '"'.$nom.'"';
            $sql = "SELECT * FROM  etudiant WHERE etLogin = {$nom}";
            $r = mysqli_query($bd, $sql);
    
            //-- Traitement --------------------------
            if ($r->num_rows == 0) {
                $errs[] = '=> utilisateur non enregistré !';
            }
            else {
                $enr = mysqli_fetch_assoc($r);
                $passwordBD = $enr['etMotDePasse'];
                $passwordFORM = $_POST['password'];
    
                if (password_verify($passwordFORM, $passwordBD)) {
                    
                    // paramètres de session
                    $_SESSION["etNumero"] = $enr['etNumero'];
                    $_SESSION["etLogin"] = $enr['etLogin'];

                    // l'url de redirection
                    $url = (isset($_SESSION['url']))?$_SESSION['url']:'../index.php';
    
                    // redirection
                    header('Location: '.$url);
                    exit;
                }
                else {
                    $errs[] = '=> la password n\'est pas correcte';
                }
            }
            
            // Libération de la mémoire associée au résultat de la requête
            mysqli_free_result($r);
    
            //-- Déconnexion -------------------------
            mysqli_close($bd);
        }
    }

    // si l'user est déjà connecté => redirection vers index.php
    if (isset($_SESSION['etLogin'])) {
        header('Location: ../index.php');
        exit();
    }
}


//_______________________________________
/**
 * affiche le coontenu de la page connexion
 * @param   array   $err    |array contenant les erreurs de connexion à afficher
 * @param   string  $n      |
 * @param   string  $p      |
 * @return  void
 */
function AffContenuConn(&$err, &$n, &$p): void {
    
    echo
    '<section>',
        '<h3>Formulaire de connexion</h3>',
        '<p>Pour vous identifier, remplissez le formulaire ci-dessous.</p>';

    // afficher Erreur
    foreach ($err as $msg) {
        echo '<p style="color:red; text-align:center;">','&#x1F61E; '.$msg,'</p>';
    }

    echo
        '<form action="connexion.php" method="post">',
            '<table>',
                '<tr>',
                    '<td><label for="txtPseudo">Login ENT :</label></td>',
                    '<td><input type="text" name="login" id="txtPseudo" value="', HtmlProtegerSortie($n),'"></td>',
                '</tr>',

                '<tr>',
                    '<td><label for="txtPassword">Mot de passe :</label></td>',
                    '<td><input type="password" name="password" id="txtPassword" value="', HtmlProtegerSortie($p),'"></td>',
                '</tr>',

                '<tr>',
                    '<td colspan="2">
                        <input type="submit" name="btnConnexion" value="Se connecter">
                        <input type="reset" value="Annuler">
                    </td>',
                '</tr>',

            '</table>
            <input type="hidden" name="redirection" value="http://localhost:8888/2020-2021/EAD/eRestoU/index.php">',
        '</form>',
        '<p>Pas encore inscrit ? N\'attendez pas, <a href="inscription.php">inscrivez-vous</a> !</p>',
    '</section>';
}

?>
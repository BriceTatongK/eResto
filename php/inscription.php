<?php

require_once('bibli_generale.php');
require_once('bibli_eRestoU.php');

ob_start(); // bufferisation

session_start(); //nouvelle session

// erreurs
$erreurs = [];

// si l'utilisateur est déjà authentifié
if (isset($_SESSION['etLogin'])){
    header ('location: ../index.php');
    exit();
}

// si formulaire soumis, traitement de la demande d'inscription
if (isset($_POST['btnInscription'])) {
    $erreurs = traitementInscriptionL();
}
else{
    $erreurs = null;
}

// affiche entete
AffEntete('eRestoU | Inscription', '../styles/eResto.css');

// affiche menu navigation
AffMenuNavigation((isset($_SESSION['etLogin']))?$_SESSION['etLogin']:'', '..');

// contenu de la page
AffContenuPageINSCRIPTION($erreurs);

// affiche pied de page
AffPiedDePage();

// envoi du buffer
ob_end_flush();




//___________________________________
/**
 * génère le form du body et affiche les eventuelles erreurs
 * @param   array   $errs   |
 * @return  void
 */
function AffContenuPageINSCRIPTION($errs): void{

    $anneeCourante = (int) date('Y');
    
    $login = (isset($_POST['login'])) ? htmlProtegerSortie($_POST['login']) : '';
    $nom = (isset($_POST['nom'])) ? htmlProtegerSortie($_POST['nom']) : '';
    $prenom = (isset($_POST['prenom'])) ? htmlProtegerSortie($_POST['prenom']) : '';
    $numero = (isset($_POST['numero'])) ? htmlProtegerSortie($_POST['numero']) : '';
    $jour = (isset($_POST['jour'])) ? (int)$_POST['jour'] : 1;
    $mois = (isset($_POST['mois'])) ? (int)$_POST['mois'] : 1;
    $annee = (isset($_POST['annee'])) ? (int)$_POST['annee'] : $anneeCourante;

    echo 
        '<section>',
            '<h3>Formulaire d\'inscription</h3>',
            '<p>Pour vous inscrire, remplissez le formulaire ci-dessous.</p>',
            '<form action="inscription.php" method="post">';

    //n'affiche rien si pas de soumission
    UtilaffResultatSoumission($errs, 'Les erreurs suivantes ont été relevées lors de votre inscription');
    echo
                '<table>',
                    '<tr>',
                        '<td><label for="txtLogin">Entrez votre login étudiant :</label></td>',
                        '<td><input type="text" name="login" id="txtLogin" value="',$login,'"></td>',
                    '</tr>',
                    '<tr>',
                        '<td><label for="txtNumero">Votre numéro étudiant :</label></td>',
                        '<td><input type="text" name="numero" id="txtNumero" value="',$numero,'"></td>',
                    '</tr>',
                    '<tr>',
                        '<td><label for="txtNom">Votre nom :</label></td>',
                        '<td><input type="text" name="nom" id="txtNom" value="',$nom,'"></td>',
                    '</tr>',
                    '<tr>',
                        '<td><label for="txtPrenom">Votre prénom :</label></td>',
                        '<td><input type="text" name="prenom" id="txtPrenom" value="',$prenom,'"></td>',
                    '</tr>',
                    '<tr>',
                        '<td>Votre date de naissance :</td>',
                        '<td>';
                        AffDateNavINSCRIPTION($jour, 'jour');
                        AffDateNavINSCRIPTION($mois, 'mois');
                        AffDateNavINSCRIPTION($annee, 'annee');
    echo
                        '<td>',
                    '</tr>',
                    '<tr>',
                        '<td><label for="txtPassword1">Choissiez un mot de passe :</label></td>',
                        '<td><input type="password" name="passe1" id="txtPassword1"></td>',
                    '</tr>',
                    '<tr>',
                        '<td><label for="txtPassword2">Répétez le mot de passe :</label></td>',
                        '<td><input type="password" name="passe2" id="txtPassword2"></td>',
                    '</tr>',
                    '<tr>',
                        '<td><input type="submit" name="btnInscription" value="S\'inscrire"></td>',
                        '<td><input type="reset" value="Réinitialiser"></td>',
                    '</tr>',
                '</table>',
            '</form>',
        '</section>';
}


/**
 *  Traitement d'une demande d'inscription.
 *
 *  Si l'inscription réussit, un nouvel enregistrement est ajouté dans la table utilisateur,
 *  la variable de session $_SESSION['user'] est créée et l'utilisateur est redirigé vers la
 *  page index.php
 *
 *  @return array    un tableau contenant les erreurs s'il y en a
 */
function traitementInscriptionL(): array {
    
    /* Toutes les erreurs détectées qui nécessitent une modification du code HTML sont considérées comme des tentatives de piratage 
    et donc entraînent l'appel de la fonction sessionExit() */

    if( !UtilparametresControle('post', ['login', 'nom', 'prenom', 'jour', 'mois', 'annee',
                                                'passe1', 'passe2', 'numero', 'btnInscription'])) {
        UtilsessionExit();   
    }

    $erreurs = [];

    // vérification du pseudo
    $login = $_POST['login'] = trim($_POST['login']);

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]{' . (LMIN_LOGIN - 1) . ',' .(LMAX_LOGIN - 1). '}$/u',$login)) {
        $erreurs[] = 'Le login doit contenir entre '. LMIN_LOGIN .' et '. LMAX_LOGIN .
                    ' caractères alphanumériques (lettres sans accents ou chiffres) et commencer par une lettre.';
    }

    // vérification des noms et prénoms
    $nom = $_POST['nom'] = trim($_POST['nom']);
    $prenom = $_POST['prenom'] = trim($_POST['prenom']);
    UtilverifierTexte($nom, 'Le nom', $erreurs, LMAX_NOM);
    UtilverifierTexte($prenom, 'Le prénom', $erreurs, LMAX_PRENOM);

    // vérification de la date de naissance
    if (! (UtilestEntier($_POST['jour']) && UtilestEntre($_POST['jour'], 1, 31))){
        UtilsessionExit(); 
    }
    if (! (UtilestEntier($_POST['mois']) && UtilestEntre($_POST['mois'], 1, 12))){
        UtilsessionExit(); 
    }
    $anneeCourante = (int) date('Y');
    if (! (UtilestEntier($_POST['annee']) && UtilestEntre($_POST['annee'], $anneeCourante  - NB_ANNEE_DATE_NAISSANCE + 1, $anneeCourante))){
        UtilsessionExit(); 
    }
    $jour = (int)$_POST['jour'];
    $mois = (int)$_POST['mois'];
    $annee = (int)$_POST['annee'];
    if (!checkdate($mois, $jour, $annee)) {
        $erreurs[] = 'La date de naissance n\'est pas valide.';
    }
    else if (mktime(0,0,0,$mois,$jour,$annee + AGE_MINIMUM) > time()) {
        $erreurs[] = 'Vous devez avoir au moins '. AGE_MINIMUM. ' ans pour vous inscrire.'; 
    }

    // vérification du numéro d'étudiant
    $numero = trim($_POST['numero']);
    if (!preg_match('/^[1-9][0-9]{7,8}$/u',$numero)) {
        $erreurs[] = 'Le numéro d\'étudiant doit être un entier de 8 ou 9 chiffres, et ne pas commencer par le chiffre 0.';
    }

    // vérification des mots de passe
    $passe1 = $_POST['passe1'];
    $passe2 = $_POST['passe2'];
    if (empty($passe1) || empty($passe2)) {
        $erreurs[] = 'Les mots de passe ne doivent pas être vides.';
    }
    else if ($passe1 !== $passe2) {
        $erreurs[] = 'Les mots de passe doivent être identiques.';
    }

    // si erreurs --> retour
    if (count($erreurs) > 0) {
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    // on vérifie si le login et le numéro étudiant ne sont pas encore utilisés que si tous les autres champs
    // sont valides car ces 2 dernières vérifications coûtent un bras !

    // ouverture de la connexion à la base 
    $co = bdConnect();

    // vérification de l'existence du pseudo ou de l'email
    $login2 = bdProtegerEntree($co, $login); // fait par principe, mais inutile ici car on a déjà vérifié que le login
                                            // ne contenait que des caractères alphanumériques
    $numero2 = bdProtegerEntree($co, $numero);//idem
    $sql = "SELECT etLogin, etNumero FROM etudiant WHERE etLogin = '{$login2}' OR etNumero = '{$numero2}'";
    $res = bdSendRequest($co, $sql);

    while($tab = $res->fetch_assoc()) {
        if ($tab['etLogin'] == $login){
            $erreurs[] = 'Le login existe déjà.';
        }
        if ($tab['etNumero'] == $numero){
            $erreurs[] = 'Le numéro d\'étudiant existe déjà.';
        }
    }
    $res->free();

    // si erreurs --> retour
    if (count($erreurs) > 0) {
        // fermeture de la connexion à la base de données
        $co->close();
        return $erreurs;   //===> FIN DE LA FONCTION
    }

    // calcul du hash du mot de passe pour enregistrement dans la base.
    $passe = password_hash($passe1, PASSWORD_DEFAULT);

    $passe = bdProtegerEntree($co, $passe);

    $dateNaissance = $annee*10000 + $mois*100 + $jour;

    $nom = bdProtegerEntree($co, $nom);
    $prenom = bdProtegerEntree($co, $prenom);

    $sql = "INSERT INTO etudiant(etLogin, etMotDePasse, etNumero, etNom, etPrenom, etDateNaissance) 
            VALUES ('{$login2}','{$passe}', {$numero2}, '{$nom}', '{$prenom}', {$dateNaissance})";
        
    bdSendRequest($co, $sql);

    // enregistrement dans la variable de session du pseudo avant passage par la fonction bdProtegerEntree()
    // car, d'une façon générale, celle-ci risque de rajouter des antislashs
    // Rappel : ici, elle ne rajoute jamais d'antislash car le pseudo ne peut contenir que des caractères alphanumériques
    $_SESSION['etLogin'] = $login;
    $_SESSION['etNumero'] = $numero;

    // fermeture de la connexion à la base de données
    $co->close();

    // redirection vers la page index.php
    header('location: ../index.php');
    exit(); //===> Fin du script
}


//___________________________________________________________________
/**
 * Affiche le résultat (succès ou erreur(s)) d'une demande de modification
 * - utilisé par les pages menu.php, commentaire.php et inscription.php
 *
 * En absence de soumission, $resultat est égal à null et la fonction n'affiche rien
 * Si soumission d'un formulaire :
 * - en cas de modification réussie, $resultat est une chaîne
 * - quand la demande de modification échoue, $resultat est un tableau de chaînes
 *
 * @param array|string|null   $resultat     Résultat de la soumission
 * @param string              $titre        titre du bloc div affiché en cas d'erreur
 *
 * @return  void
 */
function UtilaffResultatSoumission(array|string|null $resultat, string $titre = 'Les erreurs suivantes ont été relevées') : void{
    if ($resultat !== null) {
        if (is_array($resultat)) {
            echo '<div class="erreur">', $titre, ' :<ul>';
            foreach ($resultat as $err) {
                echo '<li>', $err, '</li>';   
            }
            echo '</ul></div>';
        }
        else {
            echo '<p class="succes">', $resultat, '</p>';   
        }
    }
}



//________________________________________
/**
 * affiche l'élement de selection de la date
 * @param   int     $selected      jour selectioné
 * @param   string  $str    
 * @return  void
 */
function AffDateNavINSCRIPTION($selected, $str): void{
    echo '<select name="',$str,'">';

    $min = 1;
    $max = 1;
    switch ($str) {
        case 'jour':{
            $max = 31;
            break;
        }
        case 'mois':{
            $max = 12;
            $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                    'Juillet', 'Ao&ucirct', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            break;
        }
        case 'annee':{
            $y = date('Y');
            $min = $y - 100;
            $max = $y;
        break;
        }
    }
    for($i=$min; $i<=$max; $i++){
        echo '<option value="', $i, '"', ($i==$selected)?'selected':'', '>', ($str=="mois")?$mois[$i-1]:$i, '</option>';
    }
    echo '</select>';
}

?>
<?php

require_once('bibli_generale.php');
require_once('bibli_eRestoU.php');

// bufferisation
ob_start();

// nouvelle session
session_start();


/*****************************|
 ****| INTERCEPTION POST |****|
 ******************************
 */
$Errs = []; // array erreurs
$ErrsFichier = ''; // erreurs upload fichier
// pour gérer les différents cas : (-1)->Erreurs ; $_session['action']='modif'->ModifierCommentaire
// $_session['action']='ajout'->NouveauCommentaire ; (1)->Succès
$Cas = 0; 

UtilTraitementCOMMENTAIRE($Errs, $Cas, $ErrsFichier);



/*******************************|
 *****| RECUPERE DONNEES |******|
 ********************************
 */
// s'il s'agit d'une modification du  commentaire ??
$InfoCommentaire = [];
if (isset($_SESSION['action']) && $_SESSION['action'] == 'modif') {
    BdDonneesCOMMENTAIRE($InfoCommentaire);
}



/********************************|
 ****| AFFICHE CONTENU PAGE |****|
 *********************************
 */
// affiche en te^te page
AffEntete('Menus et repas', '../styles/eResto.css'); // Entete de page

// affiche connexion ou deconnexion en fonction de si l'utilisateur est connecté ou pas.
AffMenuNavigation((isset($_SESSION['etLogin']))?$_SESSION['etLogin']:'', '..');

// affiche contenu de la page
AffContenuPageCOMMENTAIRE($Cas, $InfoCommentaire, $Errs, $ErrsFichier);

// affiche pied de page
AffPiedDePage();

// end bufferisation
ob_end_flush();









//______________________________________
/**
 * identifie le cas à traiter par la suite
 * @param   array   $_errs      | recupère les possibles erreurs du form
 * @param   int     $_cas       | recupère le cas à gérer
 * @param   string  $_errFichier| 
 * @return  void
 */
function UtilTraitementCOMMENTAIRE(&$_errs, &$_cas, &$_errFichier):void {

    // $_SESSION['dateMenu'] : utilise la session en cours, pour maintenir la date du menu en question.
    // $_SESSION['action'] = modif/ajout : pour 

    // nouveau commentaire
    if (isset($_POST['BtnEcriComm'])) {
        $_SESSION['dateMenu'] = $_POST['dateRepas'];
        $_SESSION['action'] = 'ajout';
        return;
    }

    // modifier commentaire
    if (isset($_POST['BtnEditComm'])) {
        $_SESSION['dateMenu'] = $_POST['date'];
        $_SESSION['action'] = 'modif';
        return;
    }

    // traitement form commentaire
    if (isset($_POST['btnCommentaire'])) {
        
        $dateRepas  = $_SESSION['dateMenu'];
        $note       = $_POST['note'];
        $etudiant   = $_SESSION['etNumero'];
        $texte      = $_POST['commentaire'];
        $datePub    = date('YmdHi');

        # validation des champs du form
        // vérification de la note
        if (! (UtilestEntier($_POST['note']) && UtilestEntre($_POST['note'], 1, 5))) {
            UtilsessionExit();
        }

        // vérification du texte : constitué uniquement de nombre ?
        if ((ctype_digit($_POST['commentaire']))) {
            UtilsessionExit();
        }

        // zone texte vide
        if (strlen($_POST['commentaire']) == 0) {
            $_errs[] = 'Le texte du commentaire ne doit pas etre vide.';
        }

        // si erreurs
        if (count($_errs) > 0) {
            $_cas = -1;
            return;
        }

        // si aucune erreur, sauvegarder le commentaire dans la base donnée
        $bd = BdConnect();
        $sql = "INSERT INTO commentaire(coDateRepas, coNote, coDatePubblication, coTexte, coEtudiant)
                VALUES ({$dateRepas}, {$note}, {$datePub}, '{$texte}', {$etudiant})";
        BdSendRequest($bd, $sql);
        
        $_SESSION['action'] = 'modif';
        $_cas = 1;
        return;
    }

    # traitement upload
    if (isset($_POST['btnUpl'])) {

        // Vérification si erreurs
        $f = $_FILES['uplFichier'];

        switch ($f['error']) {
            case 1:
            case 2:
                $_errFichier = "'{$f['name']}' est trop gros.";
                break;
            case 3:
                $_errFichier = "Erreur de transfert de '{$f['name']}'";
                break;
            case 4:
                $_errFichier = "'{$f['name']}' introuvable.";
        }

        if ($_errFichier != '') {
            $_SESSION['resUpl'] = 'no';
            return;
        }

        // extensions autorisées
        $nom = $f['name'];
        $ext = strtolower(substr($nom, strrpos($nom, '.')));
        if ($ext !='.jpg') {
            $_errFichier = 'Extension du fichier non autorisée';
            $_SESSION['resUpl'] = 'no';
            return;
        }

        // Pas d'erreur => placement du fichier
        if (! @is_uploaded_file($f['tmp_name'])) {
            $_errFichier = 'Erreur interne de transfert : placement';
            $_SESSION['resUpl'] = 'no';
            return;
        }

        // déplacement interne du fichier
        $place = '../upload/'.$_SESSION['dateMenu'].'_'.$_SESSION['etNumero'].'.jpg';
        if (! @move_uploaded_file($f['tmp_name'], $place)) {
            $_errFichier = 'Erreur interne de transfert : déplacement';
            $_SESSION['resUpl'] = 'no';
            return;
        }

        // upload réussi
        $_SESSION['resUpl'] = 'ok';
    }
}




//______________________________________
/**
 * recupère les informations du commentaire à modifier
 * @param   array   $_commentaire   | array qui doit contenir les infos du commmentaire
 * @return  void
 */
function BdDonneesCOMMENTAIRE(&$_commentaire): void {

    $etudiant = $_SESSION['etNumero'];
    $_date = $_SESSION['dateMenu'];

    $bd = BdConnect();
    $sql = "SELECT *
            FROM commentaire
            WHERE coEtudiant = {$etudiant} AND coDateRepas = {$_date}";
    $res = BdSendRequest($bd, $sql);
    
    $_commentaire = $res->fetch_assoc();
    $res->free();   // libération des ressources
}



//______________________________________
/**
 * affiche le contenu de la page en fonction des différentes situations
 * @param   int     $LeCas      | cas à gérer
 * @param   array   $LeComm     | info eventuelle modification commentaire
 * @param   array   $LesErr     | eventuelles erreurs à afficher
 * @param   string  $ErrFichier | les erreurs du form de upload fichier
 * @return  void
 */
function AffContenuPageCOMMENTAIRE($LeCas, $LeComm, $LesErr, $ErrFichier):void {

    // date du jour
    $jour = date('d');
    $mois = date('m');
    $annee = date('Y');
 
    $text = (isset($_SESSION['action']) && $_SESSION['action'] == 'modif')?$LeComm['coTexte']:((isset($_POST['commentaire']))?$_POST['commentaire']:''); 
    $note = (isset($_SESSION['action']) && $_SESSION['action'] == 'modif')?$LeComm['coNote']:((isset($_POST['note']))?$_POST['note']:'');
    
    echo 
    '<section>',
    '<p style="text:bold;">Pour revenir au menu sujet du commentaire, cliquez ',
    '<a style="color:red;" href="./menu.php?jour=',$jour,'&mois=',$mois,'&annee=',$annee,'&submit=Consulter">ici</a>.</p><br>';

    if(isset($_SESSION['action']) && ($_SESSION['action'] == 'modif')){echo '<h3>Edition d\'un commentaire</h3>';}
    else{echo '<h3>Ajout d\'un commentaire</h3>';}

    // resultat soumission.
    switch ($LeCas) {
        case -1:
            $titre = 'Les erreurs suivantes ont été rélevées';
            echo '<div class="erreur">', $titre, ' :<ul>';
            foreach ($LesErr as $err) {
                echo '<li>', $err, '</li>';   
            }
            echo '</ul></div>';
            break;
        case 1:
            $titre = 'Commentaire ajouté avec succès.';
            echo '<p class="succes">', $titre, '</p>';
            break;
        
        default:
            break;
    }

    echo
        '<form action="commentaire.php" method="post" style="text-align:center;">',
            '<label for="txtCommentaire">Texte du commentaire :</label>',
            '<textarea name="commentaire" id="txtCommentaire" rows="20" cols="30" value="">',$text,'</textarea><br>',
            '<label for="txtNote">Note du repas :</label>',
            '<input type="number" name="note" list="noteMarks" id="txtNote" min="1" max="5" value="',$note,'"><br>',
            '<datalist id="noteMarks">',
                '<option value="1">Médiocre (1/5)</option>',
                '<option value="2">Bof (2/5)</option>',
                '<option value="3">Passable (3/5)</option>',
                '<option value="4">Bien (4/5)</option>',
                '<option value="5" selected>Super (5/5)</option>',
            '</datalist>',
            '<input type="submit" name="btnCommentaire" value="Enregistrer">',
            '<input type="reset" value="Annuler">',
        '</form><br>';

    // photo d'illustration
    if(isset($_SESSION['action']) && ($_SESSION['action'] == 'modif')) {
        echo '<h3>Photo d\'illustration</h3>';

        // resultat upload
        $res = '';
        if (isset($_SESSION['resUpl'])) {$res = $_SESSION['resUpl'];}
        if ($res == 'ok') {echo '<p class="succes">Fichier transféré avec succès.</p>';}
        if ($res == 'no') {echo '<p class="erreur">', $ErrFichier,'</p>';}

        // afficher eventuelle ancienne photo
        $img = $_SESSION['dateMenu'].'_'.$_SESSION['etNumero'];
        $file = '../upload/'.$img.'.jpg';
        if (file_exists($file)) {
            echo '<br><label for="img">La photo ci-dessous est actuellement associée au commentaire :</label><br>',
            '<a id="img" href="',$file,'" target="_blank">',
            '<img width="300" height="300" src="',$file,'" alt="Photo illustrant le commentaire" title="Cliquez pour agrandir"></a>';
        }else {
            echo
            '<p>Aucune photo n\'est associée au commentaire.</p>';
        }

        // form pour upload une nouvelle photo d'illustration
        echo '<br>',
        '<form method="post" enctype="multipart/form-data" action="commentaire.php">',
            '<input type="hidden" name="MAX_FILE_SIZE" value="100000"><br>',
            '<p>Les images acceptées sont des fichiers JPG de taille 100 ko maximum.</p><br>',
            'Choisissez un fichier à télécharger: ',
            '<input type="file" name="uplFichier">',
            '<input type="submit" name="btnUpl" value="Envoyer l\'image">',
        '</form></section>';
    }
}

?>
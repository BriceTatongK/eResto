<?php
require('bibli_eRestoU.php');
require('bibli_generale.php');

ob_start();// bufferisation

session_start();// nouvelle session

$_SESSION['url'] = $_SERVER['REQUEST_URI'];// conserver l'url de cette page


/********************************|
 **** GESTION COMMANDE FAITE ****|
 * *******************************
 */
$resultatCommande = false; // le resultat finale de la commande, true = succès
$errCommande = []; // array pour mémoriser les eventuelles erreurs 
UtilTraitementCommandeMENU($errCommande, $resultatCommande);



/********************************************************|
 * INFORMATIONS NECESSAIRE POUR AFFICHER LE CONTENU *****|
 * *******************************************************
 */
$DateDeMenu = -1; // date selectionée pour un eventuel menu

$WeekEnd = false; // date correspond à un jour du weekend ?

$ErrMessage = []; // erreurs sur la date à afficher

$ErrDateUrl = UtilDateMENU($DateDeMenu, $WeekEnd, $ErrMessage); // controle de l'url et set de la date d'un eventuel Menu

// menu , plats , commentaires et repas de l'etudiant du jour
$menu = []; $commentaires = []; $repas = [];

$RestoOuvert = false; // si le resto est ouvert ce jour.

$CommentaireUser = false; // sera mise à "true" si le user connecté a dejà commenté le menu en affiche, correspondant à une date donnée.
$CommandeUser = false; 

$bd = bdConnect(); //  object connexion

// s'il ya pas eu d'erreurs à afficher, on recupère les données
if ($ErrDateUrl == 0) {
    BdDonneesMENU($bd, $DateDeMenu, $RestoOuvert, $menu, $commentaires, $repas, $CommentaireUser, $CommandeUser);
}



/********************************|
 * AFFICHE DU CONTENU DE LA PAGE |
 * *******************************
 */
AffEntete('Menus et repas', '../styles/eResto.css'); // Entete de page

// affiche connexion ou deconnexion en fonction de si l'utilisateur est connecté ou pas.
AffMenuNavigation((isset($_SESSION['etLogin']))?$_SESSION['etLogin']:'', '..');

// contenu de cette page
AffContenuPageMENU($ErrDateUrl, $ErrMessage, $RestoOuvert, $WeekEnd,
                    $DateDeMenu, $menu, $repas, $commentaires, $errCommande,
                        $resultatCommande, $CommentaireUser, $CommandeUser);

mysqli_close($bd);// fermeture de la connexion

AffPiedDePage();// pied de page

ob_end_flush();// envoi du buffer















//_______________________________________________________________
/**
 * menu de la date selectionée
 * Les  paramètres sont passés par référence.
 *
 * @param mysqli    $bd             |connexion à la base de données MySQL
 * @param int       $DateDeMenu     |date du menu
 * @param bool      $RestoOuvert    |indique si le restoU est ouvert
 * @param array     $menu           |menu de la date consultée
 * @param array     $commentaires   |commentaires sur les repas
 * @param array     $RepasDuJour    |le repas de l'etudiant de ce jour
 * @param bool      $Commenter      |variable pour savoir si l'user a dejà commenté le menu affiché
 * @param bool      $Commender      |variable pour savoir si l'user a dejà commendé le menu affiché
 * @return void
 */
function BdDonneesMENU($bd, &$DateDeMenu, &$RestoOuvert, &$menu, &$commentaires, &$RepasDuJour, &$Commenter, &$Commander): void {
    // récuperer le menu et les eventuels commentaires
    $sql = 
    "SELECT plID, plNom, plCategorie
        FROM (
                (
                    SELECT mePlat
                    FROM menu 
                    WHERE meDate = {$DateDeMenu}
                )as menuJour
                INNER JOIN plat ON menuJour.mePlat = plID
            )";

    // envoi de la requête SQL
    $res = bdSendRequest($bd, $sql);

    // Quand le resto U est fermé, la requête précédente ne renvoie aucun plat
    if ($res->num_rows == 0) {
        $RestoOuvert = false;
        return;
    }
    
    $RestoOuvert = true;

    // tableau associatif contenant les constituants du menu : un élément par section
    $menu = array(  'entrees'           => array(),
                    'plats'             => array(),
                    'accompagnements'   => array(),
                    'desserts'          => array(),
                    'boissons'          => array(),
                    'divers'            => array()
                );

    // parcours des ressources :
    while ($tab = $res->fetch_assoc()) {
        $platsDuJour[] = $tab['plID'];

        switch ($tab['plCategorie']) {
            case 'entree':
                $menu['entrees'][] = $tab;
                break;
            case 'viande':
            case 'poisson':
                $menu['plats'][] = $tab;
                break;
            case 'accompagnement':
                $menu['accompagnements'][] = $tab;
                break;
            case 'dessert':
            case 'fromage':
                $menu['desserts'][] = $tab;
                break;
            case 'boisson':
                $menu['boissons'][] = $tab;
                break;
            default:
                $menu['divers'][] = $tab;
        }
    }
    $res->free(); // libération des ressources

    // récupérer les commentaires associés au menu de ce jour
    $sql = "SELECT coTexte, coDatePublication, etNom, etPrenom, coNote, coDateRepas, coEtudiant
            FROM (commentaire INNER JOIN etudiant ON coEtudiant = etNumero)
            WHERE coDateRepas = {$DateDeMenu}";

    $res = bdSendRequest($bd, $sql);

    while ($tab = $res->fetch_assoc()) {
        //$commentaires = $tab;
        $clé = $tab['coDateRepas'].$tab['coEtudiant'];
        $commentaires[$clé] = $tab;
    }
    $res->free(); // libération des ressources

    // si l'user est connecté, vérifier s'il a déjà commendé et commenté le menu affiché
    if (isset($_SESSION['etNumero'])) {
        $num = (int)$_SESSION['etNumero'];
        // commendé
        $sql = "SELECT *
                FROM repas
                WHERE reDate = {$DateDeMenu} AND reEtudiant = {$num}";
        $res = bdSendRequest($bd, $sql);
        $Commander = ($res->num_rows > 0)?true:false;
        $res->free(); // libération des ressources

        // commenté
        $sql = "SELECT *
                FROM commentaire
                WHERE coDateRepas = {$DateDeMenu} AND coEtudiant = {$num}";
        $res = bdSendRequest($bd, $sql);
        $Commenter = ($res->num_rows > 0)?true:false;
        $res->free(); // libération des ressources
    }

    // si user connecté et recupère son 'etNumero'
    if (isset( $_SESSION['etNumero'] )) {
        $EtNumero = (int)$_SESSION['etNumero']; // recupère le Numero de l'etudiant connecté
        $sql = "SELECT rePlat, reQuantite
                FROM repas
                WHERE reDate = {$DateDeMenu} AND reEtudiant = {$EtNumero}";
        
        $res = bdSendRequest($bd, $sql);
        while ($tab = $res->fetch_assoc()) {
            $k = $tab['rePlat'];
            $RepasDuJour[$k] = $tab;
        }
        $res->free();   // libération des ressources
    }
}


//______________________________________________
/**
 * validation champs du form de la nouvelle commande du menu du jour
 * @param   array   $errs           | liste des erreurs
 * @param   bool    $resultatComm   | resultat de la commande
 * @return  void
 */
function UtilTraitementCommandeMENU(&$errs, &$resultatComm): void {

    // ouverture connexion à la base
    $bd = bdConnect();

    // form envoyé
    if (isset($_POST['btnCommander'])) {

        // minimum un accompagnement et une boisson
        if (!isset($_POST['radBoisson'])) {
            $errs[] = 'choisir au moins une boisson.';
        }

        // controle sur les accompagnements
        $flag = false;
        $accompagne = [];
        for($i = 28; $i <= 33; $i++){
            $m = 'cb'.$i;
            if (isset($_POST[$m])) {
                $accompagne[] = $_POST[$m];
                $flag = true;
                break;
            }
        }
        if (!$flag) {
            $errs[] = 'choisir au moins un accompagnement.';
        }

        // validation des suppléments 'num38', 'num39'
        if (! (UtilestEntier($_POST['num38']) && UtilestEntre($_POST['num38'], 0, 2))){
            $errs[] = '2 pains maximum';
        }

        if (! (UtilestEntier($_POST['num39']) && UtilestEntre($_POST['num39'], 0, 2))){
            $errs[] = '2 serviettes maximum';
        }

        // des erreurs à afficher
        if (count($errs) > 0) {
            exit();
        }

        // traitement de la commande
        #####
        var_dump($_POST);
        #####

        // ouverture de la connexion à la base 
        $bd = bdConnect();
        $LaDate = DATE_AUJOURDHUI;
        $LeNumEtudiant = $_SESSION['etNumero'];
        $LaQuantite = 1;

        $valeurs = []; // array pour la liste des "id" des éléments qui ont été selectionnés
        if ($_POST['radBoisson']!='') {$valeurs[] = $_POST['radBoisson'];}
        if ($_POST['radDessert']!='') {$valeurs[] = $_POST['radDessert'];}
        if ($_POST['radEntree']!='') {$valeurs[] = $_POST['radEntree'];}
        if ($_POST['radPlat']!='') {$valeurs[] = $_POST['radPlat'];}

        if (count($valeurs) > 0) {
            $c = count($valeurs);
            $sql = "INSERT INTO repas(reDate, rePlat, reEtudiant, reQuantite)
                    VALUES ";

            for($i=1; $i<=$c; $i++){
                $sql = $sql."({$LaDate}, {$valeurs[$i]}, '{$LeNumEtudiant}', {$LaQuantite})";
                if ($i < $c) {$sql = $sql.',';}
            }
            bdSendRequest($bd, $sql);
        }

        // pain et serviettes
        if ($_POST['num38']>0) {
            $num38 = $_POST['num38'];
            $sql = "INSERT INTO repas(reDate, rePlat, reEtudiant, reQuantite)
                    VALUES ({$LaDate},38,'{$LeNumEtudiant}',{$num38})";

            if ($_POST['num39']) {
                $num39 = $_POST['num39'];
                $sql = $sql.",({$LaDate},39,'{$LeNumEtudiant}',{$num39})";
            }

            bdSendRequest($bd, $sql);
        }

        // accompagnements
        $Accompagnements = [];
        for($i = 28; $i <= 33; $i++){
            $m = 'cb'.$i;
            if (isset($_POST[$m])) {
                $Accompagnements[] = $_POST[$m];
            }

            $c = count($Accompagnements);
            $sql = "INSERT INTO repas(reDate, rePlat, reEtudiant, reQuantite)
                    VALUES ";

            for($i=1; $i<=$c; $i++){
                $sql = $sql."({$LaDate}, {$Accompagnements[$i]}, '{$LeNumEtudiant}', {$LaQuantite})";
                if ($i < $c) {$sql = $sql.',';}
            }
            bdSendRequest($bd, $sql);
        }

        $resultatComm = true;
    }
}


//____________________________________________
/**
 * controle les paramètres passés via l'url et fournit la date de Menu $DateDeMenu
 * le code erreur renvoyé nous permettra de traiter l'erreur sur l'url
 *  
 * @param   int       $DateDeMenu     |date du menu, passée comme paramètre global
 * @param   bool      $WeekEnd        |jour est un week-end ou pas ?
 * @param   array     $Messages       |liste des erreurs relévées sur la date de l'url
 * @return  int                       |code erreur rencontrées dans l'url
 *
 */
function UtilDateMENU(&$DateDeMenu, &$WeekEnd, &$Messages): int{
    // forme transmit, controle des clées obligatoires
    if (isset($_GET['submit']) && !UtilParametresControle('GET', ['jour', 'mois', 'annee', 'submit'], [])) {
        $DateDeMenu = -1;   // pas utilisé
        return -1;          // erreur sur l'url
    }

    // aucun form transmit
    if (!isset($_GET['submit'])) {
        $DateDeMenu = DATE_AUJOURDHUI;
        // si la date d'aujourd'hui est un week-end
        if (UtilEstEntre(UtilJourSemaine((int)substr(DATE_AUJOURDHUI, 6, 2),
                                         (int)substr(DATE_AUJOURDHUI, 4, 2),
                                         (int)substr(DATE_AUJOURDHUI, 0, 4)), 6, 7)) {
            $WeekEnd = true;
            return -3;
        }
        else
            return 0;
    }

    // $jour, $mois, $annee
    $jour = $_GET['jour'];
    $mois = $_GET['mois'];
    $annee = $_GET['annee'];

    // check jour
    if (!(UtilEstEntier($jour)) || !(UtilEstEntre($jour, 1, 31))) {
        $Messages[] = "le jour "."'".$jour."'"." pas valide ! (doit ètre compris entre 01 et 31)";
    }

    # check mois
    if (!(UtilEstEntier($mois)) || !(UtilEstEntre($mois, 1, 12))) {
        $Messages[] = "le mois "."'".$mois."'"." pas valide ! (doit ètre compris entre 01 et 12)";
    }

    # check année
    if (!(UtilEstEntier($annee)) || !(UtilEstEntre($annee, ANNEE_MIN, ANNEE_MAX))) {
        $Messages[] = "l'année "."'".$annee."'"." pas valide ! (doit ètre comprise entre ".ANNEE_MIN." et ".ANNEE_MAX.")";
    }

    // s'il existe des erreurs à afficher
    if (count($Messages) != 0){
        return -2;
    }

    // date valide ?
    if (!checkDate($mois, $jour, $annee)) {
        $DateDeMenu = UtilDateFormatMENU($jour, $mois, $annee);
        return -4;
    }

    // jusqu'ici la date est valide
    // controle si la date tombe un week-end
    if (UtilEstEntre(UtilJourSemaine($jour, $mois, $annee), 6, 7)) {
        $WeekEnd = true;
        $DateDeMenu = UtilDateFormatMENU($jour, $mois, $annee);
        return -3;
    }

    // la date est correcte et peux correspondre à un eventuel menu
    $DateDeMenu = UtilDateFormatMENU($jour, $mois, $annee);
    return 0;
}



//______________________________________________
/**
 * renvoie la date format "Lundi 03 Octobre 2000"
 * @param   int       $date   |
 * @return  string            |
 */
function UtilGetDateEnLettreMENU($date): string {
    $d = new DateTimeImmutable($date);
    return UtilDateEnFrançais($date, 'l j F Y');
}



//____________________________________
/**
 * renvoie la date suivante
 * @param int   $date   |
 * @return array
 */
function UtilGetJourSuivantMENU($date): array {
    
    $d = new DateTime($date);
    while(true){
       
        $d->modify('+1 day');
        $ap =  UtilDateEnFrançais($d->format('Ymd'), 'l');
        if ($ap !== 'Dimanche' &&  $ap !== 'Samedi') {
            break;
        }
    }
    
    return array('jour'=> $d->format('d'),
                    'mois'=> $d->format('m'),
                        'annee'=> $d->format('Y'));
}


//________________________________________
/**
 * renvoie la date precedente
 * @param int   $date   |
 * @return array
 */
function UtilGetJourPrecedantMENU($date): array {
    $d = new DateTime($date);
    while(true){
        $d->modify('-1 day');
        $ap =  UtilDateEnFrançais($d->format('Ymd'), 'l');
        if ($ap !== 'Dimanche' &&  $ap !== 'Samedi') {
            break;
        }
    }
    
    return array('jour'=> $d->format('d'),
                'mois'=> $d->format('m'),
                    'annee'=> $d->format('Y'));
}


//________________________________________
/**
 * affiche l'élement de selection de la date
 * @param   int   $j  jour selectioné
 * @param   string  $str    
 * @return  void
 */
function AffDateNavMENU($selected, $str): void{
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
            $min = $selected;
            $max = $selected + 1;
        break;
        }
    }
    for($i=$min; $i<=$max; $i++){
        echo '<option value="', $i, '"', ($i==$selected)?'selected':'', '>', ($str=="mois")?$mois[$i-1]:$i, '</option>';
    }
    echo '</select>';
}


//______________________________________________
/**
 * affiche les eventuels commentaires de ce menu
 * @param   array   $commentaires   | liste des commentaires
 * @param   int     $date           | date du menu
 * @param   bool    $CmtUser        | commentaire user
 * @param   bool    $CmdUser        | commande user
 * @return  void
 */
function AffCommentaireMENU($commentaires, $date, $CmtUser, $CmdUser): void{
    echo '<h4 id="commentaires">Commentaires sur ce menu</h4>';

    // si l'user est connecté et pour ce menu il n'a pas encore inséré de commentaire, afficher le form pour un nouveau commentaire
    if (isset($_SESSION['etNumero']) && !$CmtUser && $CmdUser) {
        # form pour inserer un nouveau commentaire si l'user connecté n'en avait pas encore inséré
        echo'<form action="commentaire.php" method="post"><div style="display:flex; flex-directio:row;">',
            '<label for="BtnEcriComm">Un avis a donner ? </label>',
            '<input type="submit" style="width: auto;" name="BtnEcriComm" value="Ecrire un commentaire">',
            '<input type="hidden" value="',$date,'" name="dateRepas"></div>',
        '</form></p>';
    }

    // afficher les commentaires de ce menu
    if (count($commentaires) > 0) {
        $note = 0;
        $Nombre = 0;

        foreach($commentaires as $key => $val) {
            $note = $note + (int)$val['coNote'];
            $Nombre++;
        }
        echo '<p>Note moyenne de ce menu : ', round($note/$Nombre), '/5 sur la base de ', $Nombre, ' commentaires</p>';

        // array_reverse devrait renverser l'ordre d'affichage. plus récent => plus ancient
        foreach(array_reverse($commentaires, true) as $key => $value) {
            
            $nom = $value['etPrenom'].' '.$value['etNom'];
            $Date = UtilDateEnFrançais(substr($value['coDatePublication'], 0, 8), 'j F Y');    //12 octobre 2022 à 17h42
            $Heure = substr($value['coDatePublication'], 8, 2).'h'.substr($value['coDatePublication'], 10, 2);
            echo
            '<article>',
            '<h5>Commentaire de ', $nom, ', publié le ', $Date, ' à ', $Heure, '</h5>',
            '<p>', $value['coTexte'],'</p>',
            '<footer>Note : ', $value['coNote'],'/5</footer>';

            $FormValue = $value['coDateRepas'];
            $img = $FormValue.'_'.$value['coEtudiant'];
            $file = '../upload/'.$img.'.jpg';

            if (file_exists($file)) {
                echo'<a href="',$file,'" target="_blank"><img src="',$file,'" alt="Photo illustrant le commentaire" title="Cliquez pour agrandir"></a>';
            }
            if (isset($_SESSION['etNumero']) && ($_SESSION['etNumero'] == $value['coEtudiant'])) {
                echo
                '<form action="commentaire.php" method="post">',
                '<input type="hidden" value="',$FormValue,'" name="date">',
                '<input type="submit" style="width: auto;" name="BtnEditComm" value="Editer le commentaire">',
                '</form>';
            }
            echo'</article>';
        }
    }
    else {
        echo '<p>Pas de commentaires pour ce menu.</p>';
    }
}

//__________________________________________________
/**
 * affiche les plats du menu du jour
 * @param   array   $menu           | le menu du jour
 * @param   array   $repas          | liste des plats selectionés et quantités
 * @param   string  $date           | date selectionée
 * @param   array   $errCommande    | eventuelles erreurs durant la commande
 * @param   bool    $CommandeFaite  | commande bien terminé ??
 * @return  void
 */
function AffPlatsMENU($MenuDuJour, $repas, $date, $errCommande, $CommandeFaite): void {

    // traitement des données de la commande ?? les selections de la commande
    $selections = [];   // array pour la liste des "id" des éléments qui ont été selectionnés
    $boisson = isset($_POST['radBoisson'])?$_POST['radBoisson']:'';
    $selections[] = 'radBoisson'.$boisson;

    $fromageDessert = isset($_POST['radDessert'])?$_POST['radDessert']:'';
    $selections[] = 'radDessert'.$fromageDessert;

    $entree = isset($_POST['radEntree'])?$_POST['radEntree']:'';
    $selections[] = 'radEntree'.$entree;

    $plat = isset($_POST['radPlat'])?$_POST['radPlat']:'';
    $selections[] = 'radPlat'.$plat;

    $pain = isset($_POST['num38'])?$_POST['num38']:0;
    $serviette = isset($_POST['num39'])?$_POST['num39']:0;

    // liste des accompagnements possible
    $accompagnements = [];
    for($i = 28; $i <= 33; $i++){
        $m = 'cb'.$i;
        if (isset($_POST[$m])) {
            $accompagnements[] = $_POST[$m];
        }
    }

    // titre h3 des sections à afficher
    $categorie_En_h3 = array('entrees'  => 'Entrées', 
                'plats'                 => 'Plat', 
                'accompagnements'       => 'Accompagnement', 
                'desserts'              => 'Fromage/dessert', 
                'boissons'              => 'Boisson',
                'divers'                => 'Suppléments'
                );

    // pour les proprietés des l'eléments html
    $h3_En_Name = array('Entrées'               => 'radEntree',
                            'Plat'              => 'radPlat',
                            'Fromage/dessert'   => 'radDessert',
                            'Boisson'           => 'radBoisson',
                            'Accompagnement'    => '',
                            'Suppléments'       => ''
                            );

    // verifie si la commande faite pour ce jour et la dateMenu est égale à la date du jour.
    $CommandePossible = ((count($repas) == 0) &&  ($date == DATE_AUJOURDHUI) && (isset($_SESSION['etLogin'])))?true:false;

    if ($CommandePossible) {
        echo'<p class="notice">Toutes les commandes sont préparées sur des plateaux contenant des couverts (couteau, fourchette, petite cuillère) et un verre. </p>';

        // afficher les eventuelles erreurs survenus durant la commande
        if (count($errCommande) != 0) {
            UtilAffResultatCommande($errCommande, 'Les erreurs suivantes ont été relevées durant le traitement de votre demande:');
        }
        echo '<form method="POST" action="menu.php">';
    }

    // commande bien terminée, afficher le message
    if ($CommandeFaite) {
        echo '<p class="succes">',HtmlProtegerSortie('La commande a été enregistrée avec succès.'),'</p>';
    }

    // chaque categorie
    foreach($MenuDuJour as $categorie => $ListePlats){
        
        // si une categorie ne contient aucun plat pour ce menu, continue sans afficher le titre
        if (count($ListePlats) == 0) {continue;}

        $h3 = $categorie_En_h3[$categorie];
        echo
        '<section>',
        '<h3>', $h3, '</h3>',
        '<div class="flexdiv">';

        // Les "entrées", "plats" et les "désserts/fromages" proposés peuvent ne pas être choisis
        if ($CommandePossible) {
            switch ($h3) {
                case 'Entrées':
                    echo
                    '<input id="radEntree0" name="radEntree" type="radio" value="0"', ($entree==0)?"checked":"",'>',
                    '<label class="plat" for="radEntree0">',
                    '<img src="../images/repas/0.jpg" alt="Pas d\'entrée" title="Pas d\'entrée">Pas d\'entrée',
                    '</label>';
                    break;
    
                case 'Plat':
                    echo
                    '<input id="radPlat0" name="radPlat" type="radio" value="0" ', ($plat==0)?"checked":"",'>',
                    '<label class="plat" for="radPlat0">',
                    '<img src="../images/repas/0.jpg" alt="Pas de plat" title="Pas de plat">Pas de plat',
                    '</label>';
                    break;
    
                case 'Fromage/dessert':
                    echo
                    '<input id="radDessert0" name="radDessert" type="radio" value="0"', ($fromageDessert==0)?"checked":"",' >',
                    '<label class="plat" for="radDessert0">',
                    '<img src="../images/repas/0.jpg" alt="Pas de fromage/déssert" title="Pas de fromage/déssert">Pas de fromage/déssert',
                    '</label>';
                    break;
                
                default:
                    break;
            }
        }

        // chaque plat d'une categorie
        foreach ($ListePlats as $plat) {
            $value = $plat['plID'];
            $name = $h3_En_Name[$h3];
            $id = $name.$value;
            $IsChecked = array_key_exists($value, $repas); // si ce plat fait parti de la liste de repas.

            switch ($categorie) {
                case 'divers':
                    $name = 'num'.$value;
                    echo
                    '<label class="plat">',
                        '<img src="../images/repas/', $value,'.jpg" alt="', $plat['plNom'],'" title="', $plat['plNom'],'">', $plat['plNom'],
                        '<input type="number" ', ($CommandePossible)?"":"disabled", ' name="', $name, '" value="', $plat['reQuantite'],'">',
                    '</label>';
                    break;
                
                case 'accompagnement':
                    $id = 'cb'.$value;
                    echo
                    '<input id="', $id,'" name="', $id,'" type="checkbox" value="', $value,'" ', ($CommandePossible)?"":"disabled", ' ', ($IsChecked || in_array($value, $accompagnements))?"checked":"", '>',
                    '<label class="plat" for="', $id,'">',
                        '<img src="../images/repas/', $value, '.jpg" alt="', $plat['plNom'], '" title="', $plat['plNom'], '">', $plat['plNom'],
                    '</label>';
                    break;

                default:
                    echo
                    '<input id="', $id,'" name="', $name,'" type="radio" value="', $value,'" ', ($CommandePossible)?"":"disabled", ' ', ($IsChecked || in_array($id, $selections))?"checked":"", '>',
                    '<label class="plat" for="', $id, '">',
                        '<img src="../images/repas/', $value, '.jpg" alt="', $plat['plNom'], '" title="', $plat['plNom'], '">', $plat['plNom'],
                    '</label>';
                    break;
            }
        }

        echo'</div></section>';
    }

    // afficher boissons, suppléments et les buttons de la commande
    if ($CommandePossible) {
        echo
            '<section>',
                '<h3>Boisson</h3>',
                '<div class="flexdiv">',
                    '<input id="radBoisson34" name="radBoisson" type="radio" value="34" ', ($boisson==34)?"checked":"",'>',
                    '<label class="plat" for="radBoisson34">',
                        '<img src="../images/repas/34.jpg" alt="Carafe d&#039;eau" title="Carafe d&#039;eau">Carafe d&#039;eau',
                    '</label>',
                    '<input id="radBoisson35" name="radBoisson" type="radio" value="35" ', ($boisson==35)?"checked":"",'>',
                    '<label class="plat" for="radBoisson35">',
                        '<img src="../images/repas/35.jpg" alt="Soda au cola" title="Soda au cola">Soda au cola',
                    '</label>',
                    '<input id="radBoisson36" name="radBoisson" type="radio" value="36" ', ($boisson==36)?"checked":"",'>',
                    '<label class="plat" for="radBoisson36">',
                        '<img src="../images/repas/36.jpg" alt="Soda &agrave; l&#039;orange" title="Soda &agrave; l&#039;orange">Soda &agrave; l&#039;orange',
                    '</label>',
                    '<input id="radBoisson37" name="radBoisson" type="radio" value="37" ', ($boisson==37)?"checked":"",'>',
                    '<label class="plat" for="radBoisson37">',
                        '<img src="../images/repas/37.jpg" alt="Soda au citron" title="Soda au citron">Soda au citron',
                    '</label>',
                '</div>',
            '</section>',
            '<section>',
                '<h3>Suppléments</h3>',
                '<div class="flexdiv">',
                    '<label class="plat">',
                        '<img src="../images/repas/38.jpg" alt="Pain" title="Pain">Pain',
                        '<input type="number" min="0" max="2" name="num38" value="',$pain,'">',
                    '</label>',
                    '<label class="plat">',
                        '<img src="../images/repas/39.jpg" alt="Serviette en papier" title="Serviette en papier">Serviette en papier',
                        '<input type="number" min="0" max="2" name="num39" value="',$serviette,'">',
                    '</label>',
                '</div>',
            '</section>',
        '<section>',
            '<h3>Validation de la commande</h3>',
            '<p class="attention">Toute commande passée qui ne sera pas récupérée sera facturée à l\'étudiant la somme forfaitaire de 20 euros.</p>',
            '<p style="text-align:center;">',
                '<input type="submit" name="btnCommander" value="Commander">',
                '<input type="reset" name="btnAnnuler" value="Annuler">',
            '</p></section></form>';
    }
}



//___________________________________________________
/**
 * affiche les erreurs pendant la commande
 * @param array $ERRS   | array contenant les erreurs
 * @param string $titre |
 * @return  void
 */
function UtilAffResultatCommande($ERRS, $titre):void{
    echo '<div class="erreur">', $titre, ' :<ul>';
        foreach ($ERRS as $Er) {
            echo '<li>', $Er, '</li>';   
        }
    echo '</ul></div>';
}


//_____________________________
/**
 * change le format de la date
 * @param   int $jour   |
 * @param   int $mois   |
 * @param   int $annee  |
 * @return  int
 */
function UtilDateFormatMENU($jour, $mois, $annee): int{
    return (int)$annee.sprintf('%02d', $mois).sprintf('%02d', $jour);
}


//___________________________________
/**
 * affiche le contenu de la page
 * 
 * @param   int     $errCode        |
 * @param   array   $errMsg         |
 * @param   bool    $restoOuvert    |
 * @param   bool    $weedEnd        |
 * @param   int     $date           | date selectionée
 * @param   array   $MenuDuJour     | le menu de ce jour
 * @param   array   $Repas          |
 * @param   array   $Commentaires   |
 * @param   array   $ErrComm        | erreurs durant la commande
 * @param   bool    $resComm        | resultat commande
 * @param   bool    $userComm       | si le user a dejà commenté le menu affiché
 * @param   bool    $userCommande   | commande faite par l'user ?
 * 
 * @return  void
 */
function AffContenuPageMENU($errCode, $errMsg, $restoOuvert, $weedEnd, $date, $MenuDuJour, $Repas, $Commentaires, $ErrComm, $resComm, $userComm, $userCommande): void{

    $date = ($date == -1)?DATE_AUJOURDHUI:$date;
    //UtilDateFormatMENU($date); // changer le format de la date, la rendre facilement utilisable pour nos fonctions

    // date non valide !
    if ($errCode == -4) {
        echo
        '<h4 style="text-align:center; text-decoration-line:underline;">Menu du ', substr($date,6,2).'/'.substr($date,4,2).'/'.substr($date,0,4),'</h4>',
        '<p style="min-height: 300px;">La date demandée n\'existe pas.<br>';
        return;
    }

    $DateEnLettre = UtilGetDateEnLettreMENU($date);
    $DateSuiv = UtilGetJourSuivantMENU($date);
    $DatePrec = UtilGetJourPrecedantMENU($date);

    echo
    '<h2>', $DateEnLettre, '</h2>',
    '<form action="menu.php" method="GET" style="text-align: center;">',
    '<a href="menu.php?jour=', $DatePrec['jour'], '&mois=', $DatePrec['mois'], '&annee=', $DatePrec['annee'], '&submit=Consulter" style="float: left;">Jour précédent</a>',
    '<a href="menu.php?jour=', $DateSuiv['jour'], '&mois=', $DateSuiv['mois'], '&annee=', $DateSuiv['annee'], '&submit=Consulter" style="float: right;">Jour suivant</a>Date : ';

    $d = new DateTime($date);
    $ObDate = [
        'jj' => $d->format('d'),
            'mm'=> $d->format('m'),
                'yyyy'=> $d->format('Y')];

    // affiche l'élement de selection de la date
    AffDateNavMENU($ObDate['jj'], 'jour');
    AffDateNavMENU($ObDate['mm'], 'mois');
    AffDateNavMENU($ObDate['yyyy'], 'annee');

    echo'<input type="submit" name="submit" value="Consulter" style="padding: 2px;"></form>';

    // erreurs diverses sur la date dans l'url
    if ($errCode == -2) {
        echo '<h4 style="text-align: center;">Erreur, la date passée dans l\'URL</h4>';
        foreach ($errMsg as $msg) {
            echo "<p style=min-height: 300px;> $msg </p><br>";
        }
        return;
    }

    // la date tombe un weekEnd => resto fermée
    if (($errCode == -3) || ( ($date != -1) && !($restoOuvert) ) ) {
        echo '<p>Aucun repas n\'est servi ce jour.</p>';
        return;
    }

    // erreur sur format de l'url
    if ($errCode == -1){
        echo '<h4 style="text-align: center;">Erreur format de l\'URL</h4>',
        '<p style="min-height: 300px;">Il faut utiliser une URL de la forme :<br>',
        'http://..../php/menu.php?jour=10&mois=10&annee=2022&submit=Consulter</p>';

        return;
    }

    AffPlatsMENU($MenuDuJour, $Repas, $date, $ErrComm, $resComm); // affiche les plats du menu du jour
    AffCommentaireMENU($Commentaires, $date, $userComm, $userCommande); // affiche les evenuels commentaires
}


?>
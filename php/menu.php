<?php
require('bibli_eRestoU.php');
require('bibli_generale.php');

ob_start();// bufferisation

session_start();// nouvelle session

$_SESSION['url'] = $_SERVER['REQUEST_URI'];// conserver l'url de cette page


/***************************************************|
 * INFORMATIONS NECESSAIRE POUR AFFICHER LE CONTENU |
 * **************************************************
 */

$DateDeMenu = -1;// date selectionée pour un eventuel menu

$WeekEnd = false;// date correspond à un jour du weekend ?

$ErrMessage = []; // erreurs sur la date à afficher

$ErrDateUrl = UtilDateMENU($DateDeMenu, $WeekEnd, $ErrMessage); // controle de l'url et set de la date d'un eventuel Menu

// menu , plats , commentaires et repas de l'etudiant du jour
$menu = []; $commentaires = []; $repas = [];

$RestoOuvert = false;

$bd = bdConnect(); // la connexion

// s'il ya pas eu d'erreurs à afficher, on recupère les données
if ($ErrDateUrl == 0) {
    BdDonneesMENU($bd, $DateDeMenu, $RestoOuvert, $menu, $commentaires, $repas);
}



/********************************|
 * AFFICHE DU CONTENU DE LA PAGE |
 * *******************************
 */
AffEntete('Menus et repas', '../styles/eResto.css'); // Entete de page

// affiche connexion ou deconnexion en fonction de si l'utilisateur est connecté ou pas.
AffMenuNavigation((isset($_SESSION['etLogin']))?$_SESSION['etLogin']:'', '..');

// contenu de cette page
AffContenuPageMENU($ErrDateUrl, $ErrMessage, $RestoOuvert, $WeekEnd, $DateDeMenu, $menu, $repas, $commentaires);

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
 * @return void
 */
function BdDonneesMENU($bd, &$DateDeMenu, &$RestoOuvert, &$menu, &$commentaires, &$RepasDuJour): void {
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

    // récupérer les commentaires associés au de ce jour
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

    # check si user connecté et recupère son 'etNumero'
    if (isset( $_SESSION['etNumero'] )) {
        // recupère le Numero de l'etudiant connecté
        $EtNumero = (int)$_SESSION['etNumero'];
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


//____________________________________________
/**
 * controle les paramètres passés via l'url et fournit la date de Menu $DateDeMenu
 * le code erreur renvoyé nous permettra de traiter l'erreur sur l'url
 *  
 * @param int       $DateDeMenu     |date du menu, passée comme paramètre global
 * @param bool      $WeekEnd        |jour est un week-end ou pas ?
 * @param array     $Messages       |liste des erreurs relévées sur la date de l'url
 * @return int                      |code erreur rencontrées dans l'url
 *
 */
function UtilDateMENU(&$DateDeMenu, &$WeekEnd, &$Messages): int{
    // forme transmit, controle des clées obligatoires
    if (isset($_GET['submit']) && !UtilParametresControle('GET', ['jour', 'mois', 'annee', 'submit'], [])) {
        $DateDeMenu = -1; // pas utilisé
        return -1; // erreur sur l'url
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
    // controle si la date correspond à un week-end
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


//________________________________
/**
 * affiche les eventuels commentaires de ce menu
 * @param   array   $commenttaires  |liste des commentaires
 * @return  void
 */
function AffCommentaireMENU($commentaires): void{
    echo '<h4 id="commentaires">Commentaires sur ce menu</h4>';

    if (count($commentaires) > 0) {
        $note = 0;
        $Nombre = 0;
        foreach($commentaires as $key => $value){
            $note = $note + (int)$value['coNote'];
            $Nombre++ ;
        }
        echo '<p>Note moyenne de ce menu : ', $note, '/5 sur la base de ', $Nombre, ' commentaires</p>';

        foreach($commentaires as $key => $value){
            $nom = $value['etPrenom'].' '.$value['etNom'];
            $Date = UtilDateEnFrançais(substr($value['coDatePublication'], 0, 8), 'j F Y');    //12 octobre 2022 à 17h42
            $Heure = substr($value['coDatePublication'], 8, 2).'h'.substr($value['coDatePublication'], 10, 2);
            echo
            '<article>',
                '<h5>Commentaire de ', $nom, ', publié le ', $Date, ' à ', $Heure, '</h5>',
                '<p>', $value['coTexte'],'</p>',
                '<footer>Note : ', $value['coNote'],' / 5</footer>';

            if (isset($_SESSION['etNumero'])) {
                $value = $value['coDateRepas'];
                $img = $value.'_'.$_SESSION['etNumero'];
                echo
                '<a href="../upload/', $img, '.jpg" target="_blank"><img src="../upload/', $img, '.jpg" alt="Photo illustrant le commentaire" title="Cliquez pour agrandir"></a>',
                '<form action="commentaire.php" method="post"><input type="hidden" value="20221012" name="date"><input type="submit" style="width: auto;" value="Editer le commentaire"></form>';
            }
            echo
            '</article>';
        }
    }else{
        echo '<p>Pas de commentaires pour ce menu.</p>';
    }
}

//_______________________________
/**
 * affiche les plats du menu du jour
 * @param   array   $menu   | le menu du jour
 * @param   array   $repas  | liste des plats selectionés et quantités
 * @return  void
 */
function AffPlatsMENU($MenuDuJour, $repas): void{

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

    foreach($MenuDuJour as $categorie => $ListePlats){
        $h3 = $categorie_En_h3[$categorie];
        echo
        '<section>',
        '<h3>', $h3, '</h3>',
        '<div class="flexdiv">';

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
                        '<input type="number" disabled  min="0" max="2" name="', $name, '" value="', $plat['reQuantite'],'">',
                    '</label>';
                    break;
                
                case 'accompagnement':
                    $id = 'cb'.$value;
                    echo
                    '<input id="', $id,'" name="', $id,'" type="checkbox" value="', $value,'" disabled ', ($IsChecked)?"checked":"", '>',
                    '<label class="plat" for="', $id,'">',
                        '<img src="../images/repas/', $value, '.jpg" alt="', $plat['plNom'], '" title="', $plat['plNom'], '">', $plat['plNom'],
                    '</label>';
                    break;

                default:
                    echo
                    '<input id="', $id,'" name="', $name,'" type="radio" value="', $value,'" disabled ', ($IsChecked)?"checked":"", '>',
                    '<label class="plat" for="', $id, '">',
                        '<img src="../images/repas/', $value, '.jpg" alt="', $plat['plNom'], '" title="', $plat['plNom'], '">', $plat['plNom'],
                    '</label>';
                    break;
            }
        }

        echo
        '</div>',
        '</section>';
    }
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
 * @param   int     $date           |
 * @param   array   $MenuDuJour     |le menu de ce jour
 * @param   array   $Repas          |
 * @param   array   $Commentaires   |
 * 
 * @return  void
 */
function AffContenuPageMENU($errCode, $errMsg, $restoOuvert, $weedEnd, $date, $MenuDuJour, $Repas, $Commentaires): void{

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

    AffPlatsMENU($MenuDuJour, $Repas); // affiche les plats du menu du jour
    AffCommentaireMENU($Commentaires); // affiche les evenuels commentaires

}


?>
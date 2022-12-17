<?php
require('bibli_eRestoU.php');
require('bibli_generale.php');

// bufferisation
ob_start();

// nouvelle session
session_start();


/*********************************************
 * INFORMATIONS POUR LE CONTENU
 */
// maintient l'url de cette page
$_SESSION['url'] = $_SERVER['REQUEST_URI'];

// date selectionée pour un eventuel menu
$DateDeMenu = -1;

// date correspond à un jour du weekend ?
$WeekEnd = false;

$ErrMessage = []; // erreurs sur la date à afficher

$ErrDateUrl = UtilDateMENU($DateDeMenu, $WeekEnd, $ErrMessage); // controle de l'url et set de la date d'un eventuel Menu

// menu , plats , commentaires et repas de l'etudiant du jour
$menu = []; $commentaires = []; $repas = [];

$RestoOuvert = false;

$bd = bdConnect(); // la connexion

if ($ErrDateUrl == 0) {
    BdDonneesMENU($bd, $DateDeMenu, $RestoOuvert, $menu, $commentaires, $repas);
}



/***************************************************
 * AFFICHER DU CONTENU DE LA PAGE
 */

// Entete de page
AffEntete('Menus et repas', '../styles/eResto.css');

// affiche connexion ou deconnexion en fonction de si l'utilisateur est connecté ou pas.
if (isset($_SESSION['etLogin'])) {AffMenuNavigation($_SESSION['etLogin'], '..');}
else{AffMenuNavigation('', '..');}

// contenu de cette page
AffContenuPageMENU($ErrDateUrl, $ErrMessage, $RestoOuvert, $WeekEnd, $DateDeMenu, $menu);

// fermeture de la connexion
mysqli_close($bd);

// pied de page
AffPiedDePage();

// envoi du buffer
ob_end_flush();



//_______________________________________________________________
/**
 * menu de la date selectionée
 *
 * Les 3 derniers paramètres sont passés par référence. Ce sont des paramètres de sortie.
 *
 * @param mysqli    $bd             |connexion à la base de données MySQL
 * @param int       $DateDeMenu     |date du menu
 * @param bool      $RestoOuvert    |indique si le restoU est ouvert
 * @param array     $menu           |menu de la date consultée
 * @param array     $commentaires   |commentaires sur les repas
 * @param array     $RepasDuJour    |le repas de l'etudiant de ce jour
 *
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

    ##############
    echo'<p>##### => MENU </p>';
    ##############
    while ($tab = $res->fetch_assoc()) {

        #################
        var_dump($tab); echo'<br>';
        #################

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

    // libération des ressources
    $res->free();

    // récupérer les commentaires associés au de ce jour
    $sql = "SELECT coTexte, coDatePublication, etNom, etPrenom, coNote, coDateRepas, coEtudiant
            FROM (commentaire INNER JOIN etudiant ON coEtudiant = etNumero)
            WHERE coDateRepas = {$DateDeMenu}";

    $res = bdSendRequest($bd, $sql);
    ########################
    echo'<p>##### => COMMENTAIRES </p>';
    ########################
    while ($tab = $res->fetch_assoc()) {
        //$commentaires = $tab;
        $clé = $tab['coDateRepas'].$tab['coEtudiant'];
        $commentaires[$clé] = $tab;
        
        ####################
        var_dump($tab);echo'<br>';
        ####################
    }

    echo '<br>';
    //var_dump($commentaires);
    foreach ($commentaires as $key => $value) {
        echo  $key, '--', $value['coDateRepas'], ' | ', $value['coEtudiant'], '<br>';
    }
    // libération des ressources
    $res->free();


    # check si user connecté et recupère son 'etNumero'
    if (isset( $_SESSION['etNumero'] )) {

        // recupère le Numero de l'etudiant connecté
        $EtNumero = (int)$_SESSION['etNumero'];
        $sql = "SELECT rePlat, reQuantite
                FROM repas 
                WHERE reDate = {$DateDeMenu} AND reEtudiant = {$EtNumero}";
        
        $res = bdSendRequest($bd, $sql);
        ###########################
        echo'<p>##### => REPAS SELECTED </p>';
        ###########################
        while ($tab = $res->fetch_assoc()) {
            $k = $tab['rePlat'];
            $RepasDuJour[$k] = $tab;

            ####################
            var_dump($tab);echo '<br>';
            ####################
        }
        // libération des ressources
        $res->free();
    }
}


//____________________________________________
/**
 * controle les paramètres passés via l'url et fournit la date de Menu $DateDeMenu
 * le code erreur renvoyé nous permettra de traiter l'erreur sur l'url
 *  
 * @param int   $DateDeMenu     date du menu, passée comme paramètre global
 * @param bool  $WeekEnd    jour est un week-end ou pas ?
 * @param array    $Messages    liste des erreurs relévées sur la date de l'url
 * @return int  code erreur rencontrées dans l'url
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

        // si la date d'aujourd'hui est un week-end
        if (UtilEstEntre(UtilJourSemaine((int)substr(DATE_AUJOURDHUI, 6, 2),
                                         (int)substr(DATE_AUJOURDHUI, 4, 2),
                                         (int)substr(DATE_AUJOURDHUI, 0, 4)), 6, 7)) {
            $WeekEnd = true;
            //echo "date today est weekend", '<br>';
            $DateDeMenu = -1;
            return -3;
        }
        else{
            $DateDeMenu = DATE_AUJOURDHUI;
            return 0;
        }
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

    // jusqu'ici la date est valide
    // controle si la date correspond à un week-end
    if (UtilEstEntre(UtilJourSemaine($jour, $mois, $annee), 6, 7)) {
        $WeekEnd = true;
        return -3;
    }

    // la date est correcte et peux correspondre à un eventuel menu
    $DateDeMenu = (int)($annee.$mois.$jour);

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
    //echo UtilDateEnFrançais($date, 'l j F Y'), '<br>';
    //echo $d->format('l j F Y'), '<br>';
    return UtilDateEnFrançais($date, 'l j F Y');
}


//_______________________________
/**
 * renvoie la date suivante
 * @param int   $date   |
 * @return array
 */
function UtilGetJourSuivantMENU($date): array {
    
    $d = new DateTime($date);
    while(true){
       // $av = UtilDateEnFrançais($date, 'l');
        $d->modify('+1 day');
        $ap =  UtilDateEnFrançais($d->format('Ymd'), 'l');
        //echo 'Fsuiv->', $d->format('d m Y'), ' av: ', $av, ' ap: ', $ap, '<br>';
        if ($ap !== 'Dimanche' &&  $ap !== 'Samedi') {
            break;
        }
    }
    
    //echo $date,' || ', $d->format('d m Y'),' - ',$d->format('m'),' - ',$d->format('l'), '<br>';
    return array('jour'=> $d->format('d'),
                    'mois'=> $d->format('m'),
                        'annee'=> $d->format('Y'));
}


//_______________________________
/**
 * renvoie la date precedente
 * @param int   $date   |
 * @return array
 */
function UtilGetJourPrecedantMENU($date): array {
    $d = new DateTime($date);
    while(true){
        //$av = UtilDateEnFrançais($date, 'l');
        $d->modify('-1 day');
        $ap =  UtilDateEnFrançais($d->format('Ymd'), 'l');
        //echo 'Fprec->', $d->format('d m Y'), ' av: ', $av, ' ap: ', $ap, '<br>';
        if ($ap !== 'Dimanche' &&  $ap !== 'Samedi') {
            break;
        }
    }
    
    //echo $date,' || ', $d->format('d m Y'),' - ',$d->format('m'), '<br>';
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
            $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Ao&ucirct', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
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


//_______________________________
/**
 * affiche les plats du menu du jour
 * @param   array   $menu   |
 * 
 * @return  void
 */
function AffPlatsMENU($MenuDuJour): void{
    # .... 
}

//________________________________________
/**
 * affiche le contenu de la page
 * 
 * @param   int     $errCode    
 * @param   array   $errMsg
 * @param   bool    $restoOuvert
 * @param   bool    $weedEnd
 * @param   int     $date
 * @param   array   $MenuDuJour     |le menu de ce jour
 * 
 * @return  void
 */
function AffContenuPageMENU($errCode, $errMsg, $restoOuvert, $weedEnd, $date, $MenuDuJour): void{

    $date = ($date == -1)? DATE_AUJOURDHUI: $date;

    $DateEnLettre = UtilGetDateEnLettreMENU($date);
    $DateSuiv = UtilGetJourSuivantMENU($date);
    $DatePrec = UtilGetJourPrecedantMENU($date);

    echo
    
        //  '<a href="menu.php?jour=', $dateDemain['mday'], '&mois=', $dateDemain['mon'], '&annee=', $dateDemain['year'], '" style="float: right;">Jour suivant</a>',
        '<h2>', $DateEnLettre, '</h2>',
        '<form action="menu.php" method="GET" style="text-align: center;">',
        '<a href="menu.php?jour=', $DatePrec['jour'], '&mois=', $DatePrec['mois'], '&annee=', $DatePrec['annee'], '&submit=Consulter" style="float: left;">Jour précédent</a>',
        '<a href="menu.php?jour=', $DateSuiv['jour'], '&mois=', $DateSuiv['mois'], '&annee=', $DateSuiv['annee'], '&submit=Consulter" style="float: right;">Jour suivant</a>Date : ';

    $d = new DateTime($date);
    $ObDate = [
        'jj' => $d->format('d'),
            'mm'=> $d->format('m'),
                'yyyy'=> $d->format('Y')];
    //var_dump($ObDate);

    // affiche l'élement de selection de la date
    AffDateNavMENU($ObDate['jj'], 'jour');
    AffDateNavMENU($ObDate['mm'], 'mois');
    AffDateNavMENU($ObDate['yyyy'], 'annee');

    
    echo
            '<input type="submit" name="submit" value="Consulter" style="padding: 2px;"></form>';

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

    // erreur format de l'url
    // gestion des erreurs dans $date
    if ($errCode == -1){
        echo '<h4 style="text-align: center;">Erreur format de l\'URL</h4>',
            '<p style="min-height: 300px;">Il faut utiliser une URL de la forme :<br>',
            'http://..../php/menu.php?jour=10&mois=10&annee=2022&submit=Consulter</p>';

        return;
    }


    AffPlatsMENU($MenuDuJour);
    /*
    echo
        // AffPlatsMENU()

        '<section>',
            '<h3>Entrées</h3>',
            '<div class="flexdiv">',
                '<input id="radEntree1" name="radEntree" type="radio" value="1" disabled checked>',
                '<label class="plat" for="radEntree1">',
                    '<img src="../images/repas/1.jpg" alt="Salade de carottes" title="Salade de carottes">Salade de carottes',
                '</label>',
                '<input id="radEntree7" name="radEntree" type="radio" value="7" disabled>',
                '<label class="plat" for="radEntree7">',
                        '<img src="../images/repas/7.jpg" alt="Salade de coleslaw" title="Salade de coleslaw">Salade de coleslaw',
                '</label>',
                '<input id="radEntree8" name="radEntree" type="radio" value="8" disabled>',
                '<label class="plat" for="radEntree8">',
                    '<img src="../images/repas/8.jpg" alt="Salade pi&eacute;montaise" title="Salade pi&eacute;montaise">Salade pi&eacute;montaise',
                '</label>',
            '</div>',
        '</section>',

        '<section>',
            '<h3>Plat</h3>',
            '<div class="flexdiv">',
                '<input id="radPlat18" name="radPlat" type="radio" value="18" disabled>',
                '<label class="plat" for="radPlat18">',
                    '<img src="../images/repas/18.jpg" alt="R&ocirc;ti de porc" title="R&ocirc;ti de porc">R&ocirc;ti de porc',
                '</label>',
                '<input id="radPlat23" name="radPlat" type="radio" value="23" disabled checked>',
                '<label class="plat" for="radPlat23">',
                    '<img src="../images/repas/23.jpg" alt="Daurade" title="Daurade">Daurade</label>',
            '</div>',
        '</section>',

        '<section>',
            '<h3>Accompagnement</h3>',
            '<div class="flexdiv">',
                '<input id="cb30" name="cb30" type="checkbox" value="30" disabled>',
                '<label class="plat" for="cb30">',
                    '<img src="../images/repas/30.jpg" alt="Frites" title="Frites">Frites',
                '</label>',
                '<input id="cb32" name="cb32" type="checkbox" value="32" disabled checked>',
                '<label class="plat" for="cb32">',
                    '<img src="../images/repas/32.jpg" alt="Gratin dauphinois" title="Gratin dauphinois">Gratin dauphinois',
                '</label>',
                '<input id="cb33" name="cb33" type="checkbox" value="33" disabled>',
                '<label class="plat" for="cb33">',
                    '<img src="../images/repas/33.jpg" alt="Julienne de l&eacute;gumes" title="Julienne de l&eacute;gumes">Julienne de l&eacute;gumes',
                '</label>',
            '</div>',
        '</section>',

        '<section>',
            '<h3>Fromage/dessert</h3>',
            '<div class="flexdiv">',
                '<input id="radDessert40" name="radDessert" type="radio" value="40" disabled>',
                '<label class="plat" for="radDessert40">',
                    '<img src="../images/repas/40.jpg" alt="Yahourt bio" title="Yahourt bio">Yahourt bio',
                '</label>',
                '<input id="radDessert47" name="radDessert" type="radio" value="47" disabled checked>',
                '<label class="plat" for="radDessert47">',
                    '<img src="../images/repas/47.jpg" alt="Part de morbier" title="Part de morbier">Part de morbier',
                '</label>',
                '<input id="radDessert48" name="radDessert" type="radio" value="48" disabled>',
                '<label class="plat" for="radDessert48">',
                    '<img src="../images/repas/48.jpg" alt="Fromage de ch&egrave;vre" title="Fromage de ch&egrave;vre">Fromage de ch&egrave;vre',
                '</label>',
            '</div>',
        '</section>',

        '<section>',
            '<h3>Boisson</h3>',
           '<div class="flexdiv">',
                '<input id="radBoisson34" name="radBoisson" type="radio" value="34" disabled>',
                '<label class="plat" for="radBoisson34">',
                    '<img src="../images/repas/34.jpg" alt="Carafe d&#039;eau" title="Carafe d&#039;eau">Carafe d&#039;eau',
                '</label>',
                '<input id="radBoisson35" name="radBoisson" type="radio" value="35" disabled checked>',
                '<label class="plat" for="radBoisson35">',
                    '<img src="../images/repas/35.jpg" alt="Soda au cola" title="Soda au cola">Soda au cola',
                '</label>',
                '<input id="radBoisson36" name="radBoisson" type="radio" value="36" disabled>',
                '<label class="plat" for="radBoisson36">',
                    '<img src="../images/repas/36.jpg" alt="Soda &agrave; l&#039;orange" title="Soda &agrave; l&#039;orange">Soda &agrave; l&#039;orange',
                '</label>',
                '<input id="radBoisson37" name="radBoisson" type="radio" value="37" disabled>',
                '<label class="plat" for="radBoisson37">',
                    '<img src="../images/repas/37.jpg" alt="Soda au citron" title="Soda au citron">Soda au citron',
                '</label>',
            '</div>',
        '</section>',

        '<h4 id="commentaires">Commentaires sur ce menu</h4>',
        '<p>Note moyenne de ce menu : 2/5 sur la base de 2 commentaires</p>',
        
        '<article>',
            '<h5>Commentaire de Frédéric Dadeau, publié le 13 octobre 2022 à 14h09</h5>',
            '<p>Pas &agrave; la hauteur de mes hautes esp&eacute;rances...</p>',
            '<footer>Note : 1 / 5</footer>',
            '<a href="../upload/20221012_98001091.jpg" target="_blank"><img src="../upload/20221012_98001091.jpg" alt="Photo illustrant le commentaire" title="Cliquez pour agrandir"></a>',
            '<form action="commentaire.php" method="post"><input type="hidden" value="20221012" name="date"><input type="submit" style="width: auto;" value="Editer le commentaire"></form>',
        '</article>',

        '<article>',
            '<h5>Commentaire de Eric Merlet, publié le 12 octobre 2022 à 17h42</h5>',
            '<p>J&#039;ai bien aim&eacute;, mais la viande manquait un peu de cuisson. </p>',
            '<footer>Note : 3 / 5</footer>',
        '</article>';

        */

}


?>
<?php


/*********************************************************
 *        Bibliothèque generale de fonctions
 * 
 * Les régles de nommage sont les suivantes.
 * Les noms des fonctions respectent la notation Pascal case.
 *
 * Ils commencent en général par un terme définisant le "domaine" de la fonction :
 *  Aff   la fonction affiche du code html / texte destiné au navigateur
 *  Html  la fonction renvoie du code html / texte
 *  Bd    la fonction gère la base de données
 *  Util  la fonction utile, effectue des taches spécifiques sur des variables ou objets du project.
 *
 * Les fonctions qui ne sont utilisés que dans un seul script
 * sont définies dans le script et les noms de ces fonctions se
 * sont suffixées avec le nome du fichier de la page. EX: menu.php => AffContenuPadeMENU()
 *
 *********************************************************/

 //________________________________________________
/**
 * Teste si un entier est compris entre 2 autres
 *
 * Les bornes $min et $max sont incluses.
 *
 * @param   int    $x  valeur à tester
 * @param   int    $min  valeur minimale
 * @param   int    $max  valeur maximale
 *
 * @return  bool   true si $min <= $x <= $max
 */
function UtilEstEntre(int $x, int $min, int $max):bool {
    return ($x >= $min) && ($x <= $max);
}

//______________________________________________
/**
 * Teste si une valeur est une valeur entière
 *
 * @param   mixed    $x  valeur à tester
 *
 * @return  bool     true si entier, false sinon
 */
function UtilEstEntier(mixed $x):bool {
    return is_numeric($x) && ($x == (int) $x);
}


//_______________________________________________________________
/**
 * Affichage de l'entete HTML + entete de la page web (bandeau de titre + menu)
 *
 * @param  string  $title  Le titre de la page (<head>)
 * @param  string  $css    Le chemin vers la feuille de style à inclure
 *
 * @return void
 */
function AffEntete(string $titre, string $css): void {
    
    echo '<!doctype html>', 
        '<html lang="fr">', 
            '<head>', 
                '<meta charset="UTF-8">', 
                '<title>eRestoU | ', $titre, '</title>', 
                '<link rel="stylesheet" type="text/css" href="', $css, '">', 
            '</head>', 
            '<body>',
                '<main>',
                    '<header>',
                        '<h1>', $titre, '</h1>',
                        '<a href="http://www.crous-bfc.fr" target="_blank"></a>',
                        '<a href="http://www.univ-fcomte.fr" target="_blank"></a>',
                    '</header>';
}


//__________________________________________________
/**
 * renvoie le code html du menu de navigation, 
 * élément commun à toutes les pages
 * la fonction verifie si l'utilisateur est authentifié
 * 
 * @param   int      $Numero     variable de session  $_SESSION['etNumero'], contenant le numero étudiant
 * @param   string   $prefixe    pour obtenir le path correct aux pages
 * 
 * @return void
 */
function AffMenuNavigation($Numero, $prefixe) : void {

    echo
    '<nav>',
            '<ul>',
                '<li><a href="', $prefixe, '/index.php">Accueil</a></li>',
                '<li><a href="', $prefixe, '/php/menu.php">Menus et repas</a></li>';

    if ( $Numero > -1 ) {
        echo '<li><a href="', $prefixe, '/php/deconnexion.php">Deconnexion["',$Numero,']</a></li>';
    }else {
        echo '<li><a href="', $prefixe, '/php/connexion.php">Connexion</a></li>';
    }

    echo'</ul>','</nav>';
}



//___________________________________________________________
/**
 * Envoie à la sortie standard la fin du code HTML d'une page
 *
 * @return void
 */
function AffPiedDePage() : void{
    echo '<footer>',
            '&copy; Master Info EAD - Octobre 2022 - Université de Franche-Comté - CROUS de Franche-Comté',
        '</footer>',
    '</main>', 
    '</body>',
    '</html>';
}

//__________________________________________________________
/** 
 *  Protection des sorties (code HTML généré à destination du client).
 *
 *  Fonction à appeler pour toutes les chaines provenant de :
 *      - de saisies de l'utilisateur (formulaires)
 *      - de la bdD
 *  Permet de se protéger contre les attaques XSS (Cross site scripting)
 *  Convertit tous les caractères éligibles en entités HTML, notamment :
 *      - les caractères ayant une signification spéciales en HTML (<, >, ...)
 *      - les caractères accentués
 *
 *  Si on lui transmet un tableau, la fonction renvoie un tableau où toutes les chaines
 *  qu'il contient sont protégées, les autres données du tableau ne sont pas modifiées.
 *
 * @param  array|string  $content   la chaine à protéger ou un tableau contenant des chaines à protéger
 *
 * @return array|string             la chaîne protégée ou le tableau
 */
function htmlProtegerSortie(array|string $content): array|string {
    if (is_array($content)) {
        foreach ($content as &$value) {
            if (is_array($value) || is_string($value)){
                $value = htmlProtegerSortie($value);
            }
        }
        unset ($value); // à ne pas oublier (de façon générale)
        return $content;
    }
    // $content est de type string
    return htmlentities($content, ENT_QUOTES, encoding:'UTF-8');
}




//____________________________________________________________________________
/**
 * Envoie une requête SQL au serveur de BdD en gérant les erreurs.
 *
 * En cas d'erreur, une page propre avec un message d'erreur est affichée et le
 * script est arrêté. Si l'envoi de la requête réussit, cette fonction renvoie :
 *      - un objet de type mysqli_result dans le cas d'une requête SELECT
 *      - true dans le cas d'une requête INSERT, DELETE ou UPDATE
 *
 * @param   mysqli              $bd     Objet connecteur sur la base de données
 * @param   string              $sql    Requête SQL
 *
 * @return  mysqli_result|bool          Résultat de la requête
 */
function BdSendRequest(mysqli $bd, string $sql): mysqli_result|bool {
    try{
        return mysqli_query($bd, $sql);
    }
    catch(mysqli_sql_exception $e){
        $err['titre'] = 'Erreur de requête';
        $err['code'] = $e->getCode();
        $err['message'] = $e->getMessage();
        $err['appels'] = $e->getTraceAsString();
        $err['autres'] = array('Requête' => $sql);
        bdErreurExit($err);    // ==> ARRET DU SCRIPT
    }
}




//____________________________________________________________________________
/**
 *  Ouverture de la connexion à la base de données en gérant les erreurs.
 *
 *  En cas d'erreur de connexion, une page "propre" avec un message d'erreur
 *  adéquat est affiché ET le script est arrêté.
 *
 *  @return mysqli  objet connecteur à la base de données
 */
function BdConnect(): mysqli {
    // pour forcer la levée de l'exception mysqli_sql_exception
    // si la connexion échoue
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try{
        $conn = mysqli_connect(BD_SERVER, BD_USER, BD_PASS, BD_NAME);
    }
    catch(mysqli_sql_exception $e){
        $err['titre'] = 'Erreur de connexion';
        $err['code'] = $e->getCode();
        // $e->getMessage() est encodée en ISO-8859-1, il faut la convertir en UTF-8
        $err['message'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1');
        $err['appels'] = $e->getTraceAsString(); //Pile d'appels
        $err['autres'] = array('Paramètres' =>   'BD_SERVER : '. BD_SERVER
                                                    ."\n".'BD_USER : '. BD_USER
                                                    ."\n".'BD_PASS : '. BD_PASS
                                                    ."\n".'BD_NAME : '. BD_NAME);
        bdErreurExit($err); // ==> ARRET DU SCRIPT
    }
    try{
        //mysqli_set_charset() définit le jeu de caractères par défaut à utiliser lors de l'envoi
        //de données depuis et vers le serveur de base de données.
        mysqli_set_charset($conn, 'utf8');
        return $conn;     // ===> Sortie connexion OK
    }
    catch(mysqli_sql_exception $e){
        $err['titre'] = 'Erreur lors de la définition du charset';
        $err['code'] = $e->getCode();
        $err['message'] = mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1');
        $err['appels'] = $e->getTraceAsString();
        bdErreurExit($err); // ==> ARRET DU SCRIPT
    }
}




//____________________________________________________________________________
/**
 * Arrêt du script si erreur de base de données
 *
 * Affichage d'un message d'erreur, puis arrêt du script
 * Fonction appelée quand une erreur 'base de données' se produit :
 *      - lors de la phase de connexion au serveur MySQL
 *      - ou lorsque l'envoi d'une requête échoue
 *
 * @param array    $err    Informations utiles pour le débogage
 *
 * @return void
 */
function BdErreurExit(array $err):void {
    ob_end_clean(); // Suppression de tout ce qui a pu être déja généré

    echo    '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">',
            '<title>Erreur',
            IS_DEV ? ' base de données': '', '</title>',
            '</head><body>';
    if (IS_DEV){
        // Affichage de toutes les infos contenues dans $err
        echo    '<h4>', $err['titre'], '</h4>',
                '<pre>',
                    '<strong>Erreur mysqli</strong> : ',  $err['code'], "\n",
                    $err['message'], "\n";
        if (isset($err['autres'])){
            echo "\n";
            foreach($err['autres'] as $cle => $valeur){
                echo    '<strong>', $cle, '</strong> :', "\n", $valeur, "\n";
            }
        }
        echo    "\n",'<strong>Pile des appels de fonctions :</strong>', "\n", $err['appels'],
                '</pre>';
    }
    else {
        echo 'Une erreur s\'est produite';
    }

    echo    '</body></html>';

    if (! IS_DEV){
        // Mémorisation des erreurs dans un fichier de log
        $fichier = @fopen('error.log', 'a');
        if($fichier){
            fwrite($fichier, '['.date('d/m/Y').' '.date('H:i:s')."]\n");
            fwrite($fichier, $err['titre']."\n");
            fwrite($fichier, "Erreur mysqli : {$err['code']}\n");
            fwrite($fichier, "{$err['message']}\n");
            if (isset($err['autres'])){
                foreach($err['autres'] as $cle => $valeur){
                    fwrite($fichier,"{$cle} :\n{$valeur}\n");
                }
            }
            fwrite($fichier,"Pile des appels de fonctions :\n");
            fwrite($fichier, "{$err['appels']}\n\n");
            fclose($fichier);
        }
    }
    exit(1);        // ==> ARRET DU SCRIPT
}


?>
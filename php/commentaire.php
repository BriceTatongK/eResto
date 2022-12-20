<?php

require_once('bibli_generale.php');
require_once('bibli_eRestoU.php');

// bufferisation
ob_start();

// nouvelle session
session_start();

// $_SESSION['url'] = $_SERVER['REQUEST_URI'];

AffEntete('Menus et repas', '../styles/eResto.css'); // Entete de page

// affiche connexion ou deconnexion en fonction de si l'utilisateur est connecté ou pas.
AffMenuNavigation((isset($_SESSION['etLogin']))?$_SESSION['etLogin']:'', '..');

echo 
    '<section>',
    '<p style="text:bold;">Pour revenir au menu sujet du commentaire, cliquez <a style="color:red;" href="./menu.php">ici</a>.</p><br>',
    '<h3>Ajout d\'un commentaire</h3>',

    '<form action="commentaire.php" method="post" style="text-align:center;">',
        '<label for="txtCommentaire">Texte du commentaire :</label>',
        '<textarea name="commentaire" id="txtCommentaire" rows="20" cols="30" value=""></textarea><br>',

        '<label for="txtNote">Note du repas :</label>',
        '<input type="number" name="note" list="noteMarks" id="txtNote" min="1" max="5" value=""><br>',
        '<datalist id="noteMarks">',
            '<option value="Médiocre 1/5"></option>',
            '<option value="Bof (2/5)"></option>',
            '<option value="Passable 3/5"></option>',
            '<option value="Bien (4/5)"></option>',
            '<option value="Super (5/5)"></option>',
        '</datalist>',

        '<input type="submit" name="btnCommentaire" value="Enregistrer">',
        '<input type="reset" value="Annuler">',
    '</form><br>',
    '<h3>Photo d\'illustration</h3>',
    '<p>Aucune photo n\'est associée au commentaire.</p>',
    '</section>';

// affiche pied de page
AffPiedDePage();

// end bufferisation
ob_end_flush();

?>
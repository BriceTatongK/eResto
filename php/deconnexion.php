<?php

# redirection vers la page d'origine
if (isset($_SESSION['url'])) {
    $goto = $_SESSION['url'];
}

# termine la session


?>
<?php 
/* MIT License
 * Copyright (c) 2023 ARIR - Erwan Goalou
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”),
 * to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
session_start();
//chargement des fonctions de l'application
require_once('require/function.php');
if (isset($_POST['dcnx']) && $_POST['dcnx'] == "dcnx"){
	unset($_POST);
	$_SESSION = array();
	// Si vous voulez détruire complètement la session, effacez également
	// le cookie de session.
	// Note : cela détruira la session et pas seulement les données de session !
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
				);
	}

	// Finalement, on détruit la session.
	session_destroy();
	redirection(true);
}
require_once('require/plugins/function_ldap.php');
//version de l'application
define('VERSION','2.0.1');

//Connexion à la base de données
$bdd=dbconnect();
//print_r($_SESSION);
//on peut accéder à une visuel de ressource 
//si cela est autorisé dans l'application
if (isset($_GET['link']) && defined('VISUEL') && VISUEL){
	//on vérifie que ce lien de la ressource est bien attribué
	$sql= "select count(id) nb from ressources where link=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$_GET['link'],PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	if ($donnees['nb'] > 0){
		require_once('visuel.php');
		die();	
	}
}



//si la connexion à la base de données est non fonctionnelle,
//on lance l'installation
if (!$bdd){
	require_once('install.php');
	die();
}else{
	//Sinon, on vérifie que les tables dans la base sont présentes
	$sql="show tables";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$donnees=$reponse->fetch();
	//Si ce n'est pas le cas, on lance l'installation
	if (!is_array($donnees)){
		$bdd=FALSE;
		require_once('install.php');
		die();
	}
}
//unset($_SESSION[NAME]['id']);
if (!isset($_SESSION[NAME]['nigend'])){
	//authentification 
	if (AUTH == "LOCAL" || AUTH == "LDAP"){
		//ajout de la restriction pour ne pas afficher le menu lors de la demande de connexion
		$_GET['restriction']=true;
		require_once("local_connect.php");
		
	}elseif (AUTH == "SSO"){	
		require_once('require/plugins/SSO.php');
		cnx_sso();
	}
}
//Si on ne connait pas la version de la base de donnée
if (!isset($_SESSION[NAME]['VERSION_DATABASE'])){
	//on va la chercher
	$sql="select tvalue from admin where name='VERSION_DATABASE'";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$donnees=$reponse->fetch();
	if (!isset($donnees['tvalue'])){
		//on rajoute la version de la base de données si elle n'existait pas
		$sql="insert into admin (name,tvalue) values ('VERSION_DATABASE',:version_appli)";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':version_appli',VERSION,PDO::PARAM_STR);
		$reponse->execute();
		$_SESSION[NAME]['VERSION_DATABASE']=VERSION;
	}else{//si elle existe en base, on la récupère (pour savoir si on doit faire une mise à jour)
		$_SESSION[NAME]['VERSION_DATABASE']=$donnees['tvalue'];
	}
}
//Si la version de la base est inférieure à la version de la GUI
//on doit mettre à jour la base de données
if ($_SESSION[NAME]['VERSION_DATABASE'] < VERSION){
	//log_action("Mise à jour de la base de données");
	executeSqlFile();
	$sql="UPDATE admin set tvalue=:version_gui where name='VERSION_DATABASE'";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':version_gui',VERSION,PDO::PARAM_STR);
	$reponse->execute();
	$_SESSION[NAME]['VERSION_DATABASE'] = VERSION;
}





//print_r($_SESSION[NAME]);
//profil non défini (Par défaut, pas de restriction dans l'application)
//donc on ne doit pas rentrer dans cette boucle
//c'est juste si on décide de brider l'application à des unités
if (!isset($_SESSION[NAME]['profil'])){
	entete();
	echo '<div class="row">';
	echo '<div class="col-xl-3" align=center >';
	echo '</div>';
	echo '<div class="col-xl-6" align=center >';
	msg('<b>Votre profil ne vous permet pas de vous connecter à cette application</b>',1,false);
	echo '</div>';
	echo '<div class="col-xl-3" align=center >';
	echo '</div>';
	echo '</div>';
	pied_page();
	die();
}

/*Chargement de la date de modification de l'administration
 * Par ex: dernière modif d'une création de ressource réservable
 * ou la création d'un calendrier
 * cette variable permet de recharger les menus pour tous les utilisateurs
 * et éviter de se déconnecter puis reconnecter
 */
$sql="select name,tvalue from admin where name='ADMIN_MODIF'";
$reponse=$bdd->prepare($sql);
$reponse->execute();
$donnees=$reponse->fetch();
if (!isset($donnees['name'])){
	maj_admin_timestamp();
}elseif (isset($_SESSION[NAME]['admin_modif']) && $_SESSION[NAME]['admin_modif'] != $donnees['tvalue']){
	profil_application();
	$_SESSION[NAME]['admin_modif']=$donnees['tvalue'];	
}elseif (!isset($_SESSION[NAME]['admin_modif']))
	$_SESSION[NAME]['admin_modif']=$donnees['tvalue'];
	
//chargement des configurations des pages
require_once('require/pages.php');


//cliquer sur le bouton annuler va supprimer les variables $_GET
if (isset($_POST['annuler']) && $_POST['annuler'] != ''){
	redirection();
}



//si la page à visualiser n'est pas dispo (à la connexion)
if (!isset($_GET['p'])){
//on prend la page d'accueil
		$_GET['p']=100;
	//print_r($menu_page);
}
//bidouille d'URL
if (!isset($menu_page[$_GET['p']])){
	require_once('error.php');
	die();
}
entete();
if (is_numeric($_GET['p']))
	require_once($menu_page[$_GET['p']]);
else 
//on peut se retrouver dans ce cas si INSCRIPTION et RESA_SALLES sont à false (voir fichier de config require/conf/) et que l'utilisateur n'est pas admin de l'application
	msg("L'application n'est pas disponible<br>Aucune ressource réservable configurée, aucun calendrier créé.<br>Contactez le super administrateur de l'application.",1);
//fermeture de la page html
pied_page();
?>

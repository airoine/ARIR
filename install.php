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

/*
 * Page qui permet l'installation de l'application
 * Cette page sert ensuite à la configuration de l'application
 * pour un super admin 
 * 
 * 
 */


//on ne peux pas appeler cette page sans passer par index.php
if (!defined('NAME')){
	require_once('error.php');
	die();
}
//suppression du fichier temp (pour éviter d'avoir login/mdp dans un fichier sur le serveur)
if (file_exists('SQL/bdd.sql'))
	unlink('SQL/bdd.sql');
//récupération de la dernière version actuellement en production
/*if (!isset($_SESSION[NAME]['VDISTANT']) && ($handle = @fopen("http://bep.local.gendarmerie.fr/calendriers/?version", "r")) !== FALSE) {
		$row=0;
		$data = fgetcsv($handle, 1000);
			$_SESSION[NAME]['VDISTANT']=$data[0];
		fclose($handle);
}*/
//fonction pour se connecter à la base de données
function dbconnect_install() {
	global $_POST;
	try {
		$pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
		$bdd = new PDO('mysql:host='.$_POST['HOST'].';dbname='.$_POST['DBNAME'].'', $_POST['LOGIN'], $_POST['MDP'], $pdo_options);
		$bdd->exec("SET CHARACTER SET utf8");
	} catch (Exception $e) {
		return FALSE;
			//die('Erreur de connexion à la base de données: '.$e->getMessage());// En cas d'erreur précédemment, on affiche un message et on arrête tout	 
	}
	return $bdd;

}


/****
 * Fonction qui permet de retourner la valeur d'une constante ou de la valeur POST
 * @param unknown $name
 * @return mixed
 */
function isexist($name){
	global $_POST,$list_const_ini;
	
	
	if (!defined($name))
		return 'FALSE';
	
	if (isset($_POST[$name]))
		return $_POST[$name];	
	if ($list_const_ini[$name] == 'txt')
		return constant($name);
	if ($list_const_ini[$name] == 'array')
		return implode(',',(constant($name)));
	if ($list_const_ini[$name] == 'bool'){
		if (@constant($name))
			return 'TRUE';
		else 
			return 'FALSE';
	}	
}

//print_r($_POST);
//si on change de fichier de conf, on efface les données précédentes.
if (isset($_POST['old_ini']) && isset($_POST['reload_conf']) && $_POST['old_ini'] != $_POST['reload_conf']){
	$temp=$_POST['reload_conf'];
	$_POST=array();
	$_POST['reload_conf']=$temp;
}

$list_const_ini=array('HOST'=>'txt','DBNAME'=>'txt','LOGIN'=>'txt','MDP'=>'txt','NAME'=>'txt','DEMO'=>'bool','DAYMAXEVENTS'=>'bool',
		'RESA_SALLES'=>'bool','TYPE_CAL_RESA'=>'txt','INSCRIPTION'=>'bool','LOGO'=>'txt','LOGO_MAIL'=>'txt','ADMIN'=>'array',
		'NIGEND_ADMIN'=>'array','REST_ACCES'=>'array','NO_SHOW_LOG_4_NIGEND'=>'array','PURGE_LOG'=>'txt','MAIL_CC_ORGA'=>'bool',
		'DETAIL_RESA'=>'txt','MAILS'=>'bool','UP_DEL_DDE'=>'txt','VISUEL'=>'bool','AUTH'=>'txt',
		'DSLDAP'=>'txt','DNSERVICELDAP'=>'txt','DNPERSONNESLDAP'=>'txt','SEND_MAILS'=>'txt'
);

$oui_non['']['TRUE']='OUI';
$oui_non['']['FALSE']='NON';

$active_desactive['']['TRUE']='ACTIVE';
$active_desactive['']['FALSE']='DESACTIVE';


if (!$bdd){
	entete();
	//on crée le nom lié à l'url pour un futur fichier de conf
	$url=explode('/',$_SERVER['REQUEST_URI']);
	$nb_values=count($url);
	$list_conf['']="";
	if (isset($url[($nb_values-2)])){
		$list_conf[$url[($nb_values-2)]]=$url[($nb_values-2)]."_ini.php";
		if (!file_exists('require/conf/'.$url[($nb_values-2)].'_ini.php'))
			$list_conf[$url[($nb_values-2)]].= " (sera créé) - Vous devriez choisir cette option...";
	}
	$list_conf['default']="default_ini.php";
	$readonly=false;
	//cadre pour le choix du fichier de conf
	echo '<div class="row">';
	echo '<div class="col-xl-3" align=center >';
	if (!$bdd)
		msg('Vous êtes sur cette page de configuration car la configuration ne permet pas de se connecter à une base de donnée.');
	echo '</div>';
	echo '<div class="col-xl-6" align=center >';
	$data ='<div class="container">';
	$data .= html_champ("Utiliser le fichier de configuration :", "reload_conf",'select_1',array('Fichiers'=>$list_conf),'',$readonly);
	$data .= '</div>';
	html_cadre("Configuration",$data);
	if (isset($_POST['valider_change']) && $_POST['valider_change'] == "valider"){
		$msg="";
		$bdd_inst=dbconnect_install();
		if (!$bdd_inst)
			$msg.="La base de donnée est non fonctionnelle...<br>";
		else{
			/*$sql="show tables";
			$reponse=$bdd_inst->prepare($sql);
			$reponse->execute();
			while ($donnees=$reponse->fetch()){
				print_r($donnees); 
				
			}*/
			
			//executeSqlFile();
		}
		if ($_POST['ADMIN'] == "" && $_POST['NIGEND_ADMIN'] == "")
			$msg.="Aucun personnel n'est administrateur de l'application. <br>Mais si vous êtes en authentification locale, vous pourrez créer cet administrateur par la suite<br>";
		if (trim($_POST['NAME']) == "")
			$msg.="L'application n'a pas de nom...<br>";
		if ($_POST['RESA_SALLES'] == "FALSE" && $_POST['INSCRIPTION'] == "FALSE")
			$msg.="Auncune fonctionnalité de l'application n'est activée...<br>";
			$javascript='document.getElementById(\'confirme\').value=\'confirmer\';form.submit();';
			$buttons='&nbsp;&nbsp;<input type="button" id ="confirmer" name="confirmer" value="Confirmer" class="btn btn-success" onclick="'.$javascript.'">';
			$buttons .= '&nbsp;&nbsp;<input type="submit" id ="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
			if (file_exists('require/conf/'.$_POST['reload_conf']."_ini.php"))
				msg('Vous confirmez que vous voulez modifier ce fichier?'.$buttons,0);
			else
				msg('Vous confirmez que vous voulez créer ce fichier?'.$buttons,0);
			if ($msg != "")
				msg($msg,3);
			
	}//on va lire le fichier de conf uniquement si on n'est pas sur le fichier default (il est déjà chargé par défaut) et si on n'est pas déjà dans un formulaire (pas le premier passage)
	elseif (isset($_POST['reload_conf']) && $_POST['reload_conf'] != "default" && file_exists('require/conf/'.$_POST['reload_conf'].'_ini.php') && !isset($_POST['ADMIN'])){
		$handle = @fopen('require/conf/'.$_POST['reload_conf']."_ini.php", 'r');
		while(!feof($handle)) {
			$line = trim(fgets($handle));
			//on ne prend que les lignes qui commencent par define(
			$pos = strpos($line, "define(");
			if ($pos === 0) {
				//on supprime les '
				$info= str_replace("'","",substr(substr($line,7),0,-2));
				//on remplace la première virgule par un $ (pour éviter de casser une constante array())
				$info= preg_replace('/,/', '$', $info, 1);
		
				$array_info=explode('$',$info);
				//echo $array_info[1]."<br>";
				//recherche pour voir si on est sur une constante tableau
				$info_tab=strpos(trim($array_info[1]),"array(");
				//echo $info_tab;
				if ($info_tab === 0){
					//echo substr(substr($array_info[1],6),0,-1)."<br>";
					$_POST[$array_info[0]]=substr(substr($array_info[1],6),0,-1);
				}elseif (!isset($_POST[$array_info[0]])){
					$_POST[$array_info[0]]=$array_info[1];
				}
				//print_r($array_info);
			}
		}
		fclose($handle);		
		//si ce fichier n'existe pas, on va le créer plus tard		
	}
	echo '</div>';
	echo '<div class="col-xl-3" align=center >';
	echo '</div>';
	echo '</div>';
		
}else{
	$_POST['reload_conf']=$_SESSION['FILE_CONF'];
}


if (isset($_POST['confirme']) && $_POST['confirme'] == "confirmer"){
	//traitement des données envoyées
	if (file_exists('require/conf/'.$_POST['reload_conf']."_ini.php"))
		//si le fichier existe déjà, on le supprime
		unlink('require/conf/'.$_POST['reload_conf']."_ini.php");
	//création du nouveau fichier
	$php_conf_file="<?php\n";
	$php_conf_file.="/* MIT License\n";
	$php_conf_file.=" * Copyright (c) 2023 ARIR - Erwan Goalou\n";
	$php_conf_file.=" * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”),\n";
	$php_conf_file.=" * to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,\n";
	$php_conf_file.=" * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\n";
	$php_conf_file.=" * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.\n";
	$php_conf_file.=" * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,\n";
	$php_conf_file.=" * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,\n";
	$php_conf_file.=" * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.\n";
	$php_conf_file.="*/\n";
	//pour chaque donnée envoyée
	foreach ($_POST as $k=>$v){
		if (isset($list_const_ini[$k])){
			$php_conf_file.="if (!defined('".$k."'))\n";
			//si la donnée est de type texte
			if ($list_const_ini[$k] == 'txt')
				//on écrit la donnée envoyée avec des cotes
				$php_conf_file.="    define('".$k."','".$v."');\n";
			//si la donnée est de type booleen
			elseif ($list_const_ini[$k] == 'bool') 
				//on écrit la donnée envoyée directement
				$php_conf_file.="    define('".$k."',".$v.");\n";
			//si la donnée est de type tableau
			elseif($list_const_ini[$k] == 'array'){
				//on split les données avec la virgule comme séparateur
				$my_data_array=explode(',',$v);
				//on regarde le nb de valeurs dans le champ
				$nb_my_data_array=count($my_data_array);
				//variable contenant les différentes valeurs
				$list_data_array="";
				//si le nombre de valeur est supérieure à 1
				if ($nb_my_data_array > 1){
					//variable qui nous servira à voir si on est sur la dernière valeur du tableau
					$i=0;
					foreach ($my_data_array as $array_value){
						//on supprime les éventuelles " saisie par l'utilisateur
						$array_value=str_replace('"', "", $array_value);
						//on ajoute des " entre chaque valeur
						$list_data_array.='"'.$array_value.'"';	
						$i++;
						//si on est entre des valeurs, on ajoute une virgule
						if ($i < $nb_my_data_array)
							$list_data_array.=",";
					}
				//si on n'a qu'une seule valeur dans le tableau
				}elseif ($nb_my_data_array === 1){
					$list_data_array = '"'.$v.'"';					
				}
				//on écrit la donnée tableau
				$php_conf_file.="    define('".$k."',array(".$list_data_array."));\n";
				
			}
		}
	}
	//on ferme le fichier de conf
	$php_conf_file.="?>";
	//on crée le fichier de conf correspondant.
	file_put_contents('require/conf/'.$_POST['reload_conf']."_ini.php", $php_conf_file);
	executeSqlFile();
	msg("Modifications prisent en compte. Recharger la page au besoin.",0);
	/*$location="Location: ";
	header($location);*/
}

foreach ($list_const_ini as $k=>$v){
	$_POST[$k]=isexist($k);		
}
//si le choix de fichier de conf a été fait, on affiche les autres tableaux
if (isset($_POST['reload_conf']) && $_POST['reload_conf'] != ''){
	//initialisation des champs
	$data_bdd="";
	$data_conf="";
	$data_mail="";	
	$data_ldap="";
	
	if (isset($readonly)){
		$javascript_button_valider='document.getElementById(\'valider_change\').value=\'valider\';form.submit();';
		//echo '<br><input type="button" id ="valider" name="valider" value="Valider les changements" class="btn btn-success" onclick="'.$javascript.'">';
		$data_bdd = html_champ("HOST de la base de données:", "HOST",'text','','',$readonly);
		$javascript='document.getElementById(\'test_cnx_bdd\').value=\'valider\';form.submit();';
		$data_bdd .= html_champ("Nom de la base de données", "DBNAME",'text','','',$readonly);
		$data_bdd .= html_champ("Login qui va se connecter à la base de donnée", "LOGIN",'text','','',$readonly);
		$data_bdd .= html_champ("Mot de passe du Login qui va se connecter à la base de donnée", "MDP",'text','','',$readonly);
		$data_bdd .= '<input type="button" id ="testcnx" name="testcnx" value="Tester la connexion à la base de données" class="btn btn-success" onclick="'.$javascript.'">';
	}else{
		$javascript_button_valider='document.getElementById(\'confirme\').value=\'confirmer\';form.submit();';
		echo '<input type="hidden" id ="HOST" name="HOST" value="'.HOST.'">';
		echo '<input type="hidden" id ="DBNAME" name="DBNAME" value="'.DBNAME.'">';
		echo '<input type="hidden" id ="LOGIN" name="LOGIN" value="'.LOGIN.'">';
		echo '<input type="hidden" id ="MDP" name="MDP" value="'.MDP.'">';
		$readonly = false;
	}
	$data_conf = html_champ("Nom de l'application", "NAME",'text','','',$readonly);
	$data_conf .= html_champ("Réservation de ressource", "RESA_SALLES",'select_1',$active_desactive,'',$readonly);
	$data_conf .= html_champ("Inscription à une ressource", "INSCRIPTION",'select_1',$active_desactive,'',$readonly);
	$calendar['Type']=array('CALENDAR'=>'Calendrier','TIMELINE'=>'Timeline');
	$data_conf .= html_champ("Type de calendrier", "TYPE_CAL_RESA",'select_1',$calendar,'',$readonly);
	$affich_resa['Affiche']=array('codeUnite'=>'Code service','unite'=>'Libellé service','displayname'=>'Identité du demandeur');
	$data_conf .= html_champ("Données affichées sur le calendrier des demandes", "DETAIL_RESA",'select_1',$affich_resa,'',$readonly);
	$data_conf .= html_champ("Avoir un visuel restreint dans le calendrier <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Si le nombre de demandes/séances par jour est trop important, on ajoute +x pour avoir une vision plus homogène\"></i>", "DAYMAXEVENTS",'select_1',$active_desactive,'',$readonly);
	$files = scandir('img/');
	foreach ($files as $k=>$v){
		if (!in_array($v,array(".","..")) && strpos($v, ".png"))
			$imgs[$v]=$v;		
	}
	$data_conf .= html_champ("Logo de l'application <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"mettez vos images dans le répertoire img/ de l'application\"></i>", "LOGO",'select_1',array('Images dans img/'=> $imgs),'',$readonly);
	$data_conf .= html_champ("Donner un visuel aux calendriers de réservation <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Cela permet par exemple d'afficher les réservations sur un écran déporté par exemple\"></i>", "VISUEL",'select_1',$active_desactive,'',$readonly);
	
	$data_mail .= html_champ("Mails", "MAILS",'select_1',$active_desactive,'',$readonly);
	$data_mail .= html_champ("Envoi de mail par ", "SEND_MAILS",'select_1',array('Envoi mails'=> array('FALSE'=>'DESACTIVE','MAIL_SRV'=>'SERVEUR MAIL','MAIL_SSO'=>'SSO')),'',$readonly);
	$data_mail .= html_champ("Envoi sur la boîte service du demandeur", "MAIL_CC_ORGA",'select_1',$active_desactive,'',$readonly);
	$data_mail .= html_champ("Logo envoyé en mail auto <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Le logo envoyé par mail est une image hébergée sur votre intranet/internet. Ex: https://calendrier.xxx.fr/img/logo.png\"></i>", "LOGO_MAIL",'text','','',$readonly);
	global $placeholder;
	$placeholder=array('DSLDAP'=>'ex : ldap.xxx.fr','DNSERVICELDAP'=>'ex : dmdName=Services,dc=xxx,dc=fr','DNPERSONNESLDAP'=>'ex : dmdName=Personnes,dc=xxx,dc=fr');
	$data_ldap .= html_champ("dsLDAP", "DSLDAP",'text','','',$readonly);
	$data_ldap .= html_champ("DNSERVICELDAP", "DNSERVICELDAP",'text','','',$readonly);
	$data_ldap .= html_champ("DNPERSONNESLDAP", "DNPERSONNESLDAP",'text','','',$readonly);

	$data_conf_admin = html_champ("Authentification", "AUTH",'select_1',array(''=>array('LOCAL' => 'Locale','LDAP' => 'Ldap','SSO'=>'SSO + Ldap')),'',$readonly);
	$data_conf_admin .= html_champ("Code Services administrateurs de l'application <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Les personnes appartenant à ce(s) service(s) seront super administrateur de l'application (Séraper les code par des ,)\"></i>", "ADMIN",'number','','',$readonly);
	$data_conf_admin .= html_champ("ID utilisateurs administrateurs de l'application <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Les personnes faisant appartenant à ce(s) service(s) seront super administrateur de l'application (Séraper les code par des ,)\"></i>", "NIGEND_ADMIN",'number','','',$readonly);
	$data_conf_admin .= html_champ("Restreindre l'accès à un ensemble de services (Séraper par des ,)
			<i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"restreindre l'accès à un ensemble de service (laisser ce champ à vide ne met aucune contrainte d'accès)
			il est possible de mettre une liste de services
			ou de se baser sur le libellé departmentuid du ldap.
			ex: seule les services de Rennes peuvent accéder à l'application
			Compléter le champ par  XXX RENNES YYYY (le champs departmentuid d'un personnel du service Rennes contient cette chaine)
			ex2 : on veut limiter l'accès aux services de Rennes et au service informatique
			On complète le champ par 'XXX RENNES YYYY',67675 (67675 est le code service informatique)\"></i>", "REST_ACCES",'text','','',$readonly);
	$data_conf_admin .= html_champ("Ne pas afficher les logs des ID utilisateurs (Séraper par des ,)", "NO_SHOW_LOG_4_NIGEND",'number','','',$readonly);
	
	$data_conf_admin .= html_champ("Mode DEMO", "DEMO",'select_1',$active_desactive,'',$readonly);
	$data_conf_admin .= html_champ("Purge des LOGS (en jours)", "PURGE_LOG",'number','','',$readonly);
	$data_conf_admin .= html_champ("Qui peut modifier/supprimer une demande de réservation", "UP_DEL_DDE",'select_1',array(''=>array('DEMANDEUR' => 'Le demandeur','UNITE' => 'Les personnels du service du demandeur')),'',$readonly);
	echo '<br>';
	echo '<div class="row">';
	echo '<div class="col-xl-12" align=center >';
	echo '<input type="button" id ="valider" name="valider" value="Valider les changements" class="btn btn-success" onclick="'.$javascript_button_valider.'">';
	echo '</div>';
	echo '</div>';
	echo "<br>";
	echo '<div class="row">';
	if ($data_bdd != ""){		
		$col="col-xl-3";
		echo '<div class="'.$col.'" align=center >';
		if (isset($_POST['test_cnx_bdd']) && $_POST['test_cnx_bdd'] == "valider"){
			$bdd_inst=dbconnect_install();
			if (!$bdd_inst){
				file_put_contents('SQL/bdd.sql', "create database IF NOT EXISTS ".$_POST['DBNAME'].";
create user IF NOT EXISTS '".$_POST['LOGIN']."'@'".$_POST['HOST']."' identified by '".$_POST['MDP']."';
grant all privileges on ".$_POST['DBNAME'].".* to '".$_POST['LOGIN']."'@'".$_POST['HOST']."';
flush privileges;");
				msg("Données de connexion non fonctionnelles",1);
				msg("Il vous faut créer la base de données, l'utilisateur.
	        			<br>Vous pouvez utiliser le fichier suivant => <a href='SQL/bdd.sql'>bdd.sql</a>
	        			<br>et le lancer avec la commande suivante:
	        			<br><b><i>sudo mysql < bdd.sql</i></b>",0);
			}else
				msg("Données de connexion OK",2,false);
		}
		html_cadre("Base de données",$data_bdd);
		echo '</div>';
	}else{
		$col="col-xl-4";		
	}
	echo '<div class="'.$col.'" align=center >';
	html_cadre("Visuel",$data_conf);
	echo "&nbsp;";
	echo '</div>';
	echo '<div class="'.$col.'" align=center >';
	html_cadre("Administration",$data_conf_admin);	
	echo "&nbsp;";
	echo '</div>';
	echo '<div class="'.$col.'" align=center >';
	html_cadre("Gestion Mail <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Pour que l'envoi de mail soit possible, vous devez avoir un serveur mail configuré sur votre serveur ou utiliser un SSO configuré pour l'envoi de mail\"></i>",$data_mail);
	echo "&nbsp;";
	html_cadre("Conf LDAP <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Il est possible que le fichier spécifique Ldap (require/plugins) doive être modifier pour prendre en compte vos propres configurations\"></i>",$data_ldap);
	echo '</div>';
	echo '</div>';
	
	
	

	
	
	 
}
//if (isset($_POST['reload_ressource']) && $_POST['reload_ressource'] != 'tous'){


//}
echo '<input type="hidden" id ="valider_change" name="valider_change" value="">';
echo '<input type="hidden" id ="test_cnx_bdd" name="test_cnx_bdd" value="">';
echo '<input type="hidden" id ="confirme" name="confirme" value="">';
//html_cadre($titre,$data);
if (isset($_POST['reload_conf']))
	echo '<input type="hidden" id ="old_ini" name="old_ini" value="'.$_POST['reload_conf'].'">';

?>
<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})
</script>
<?php 
pied_page();

?>
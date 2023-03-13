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
 * 
 * 
 * 
 * 
 */
//on ne peux pas appeler cette page sans passer par index.php
if (!defined('NAME')){
	require_once('error.php');
	die();
}
entete();
$msg="";
$data = html_champ("LOGIN", "LOGIN",'text','','','');
$data .= html_champ("PASSWORD", "PASSWD",'password','','','');
$javascript_button_valider='document.getElementById(\'submit_form\').value=\'connexion\';form.submit();';
$data .= '<br><input type="button" id ="valider" name="valider" value="Connexion" class="btn btn-success" onclick="'.$javascript_button_valider.'">';

if (isset($_POST['submit_form']) && $_POST['submit_form'] != ''){
	if ($_POST['submit_form'] == 'connexion'){
		if (isset($_POST['LOGIN']) && $_POST['LOGIN'] != "" && isset($_POST['PASSWD']) && $_POST['PASSWD'] != ""){
			if (AUTH == "LDAP"){
				$valid=cnx_user($_POST['LOGIN'],$_POST['PASSWD']);
				if ($valid){
					$info_user=ldap_info_user($_POST['LOGIN'],$filter='uid');
					search_ldap_session($info_user);
					$msg="Connexion effectuée";
					$type_msg=0;
					profil_application();
				}else{
					$msg="Mauvais LOGIN/PASSWORD";
					$type_msg=1;
					sleep(5);
				}		
			}elseif (AUTH == "LOCAL"){
				$sql='select nigend,uid,codeUnite,mail,login,profil from users where login=:login and password=PASSWORD(:password)';
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':login',$_POST['LOGIN'],PDO::PARAM_STR);
				$reponse->bindvalue(':password',$_POST['PASSWD'],PDO::PARAM_STR);
				$reponse->execute();
				$donnees=$reponse->fetch();
				if (isset($donnees['nigend'])){	
					$msg="Connexion effectuée";
					$type_msg=0;
					foreach ($donnees as $k=>$v){
						$_SESSION[NAME][$k]=$v;
						
					}
					$_SESSION[NAME]['displayname']=$_SESSION[NAME]['uid'];
					$_SESSION[NAME]['departmentUID']=$_SESSION[NAME]['codeUnite'];
					profil_application();
				}else{
					$msg="Mauvais LOGIN/PASSWORD";
					$type_msg=1;		
					sleep(5);
				}		
				
			}
		}	
	}elseif($_POST['submit_form'] == 'creer_user'){
		//on ajoute déjà un service dans la base
		$sql="insert into services (codeUnite,unite) values (:codeUnite,:unite) ON DUPLICATE KEY UPDATE unite = :unite";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':codeUnite','67675',PDO::PARAM_STR);
		$reponse->bindvalue(':unite','Service Informatique',PDO::PARAM_STR);		
		$reponse->execute();
		
		//on ajoute l'utilisateur par défaut
		$sql="insert into users (nigend,uid,codeUnite,mail,login,password,profil) values (:nigend,:uid,:codeUnite,:mail,:login,PASSWORD(:password),'sadministrateur')";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':nigend','172156',PDO::PARAM_STR);
		$reponse->bindvalue(':uid','Erwan AIROINE',PDO::PARAM_STR);
		$reponse->bindvalue(':codeUnite','67675',PDO::PARAM_STR);
		$reponse->bindvalue(':mail','airoine@rone.fr',PDO::PARAM_STR);
		$reponse->bindvalue(':login','rone',PDO::PARAM_STR);
		$reponse->bindvalue(':password','rone',PDO::PARAM_STR);
		$reponse->execute();
		log_action('Ajout de l\'utilisateur rone - SUPER-ADMIN');
		echo "<script>window.location.replace(\"\")</script>";
		
	}
}

$sql='select count(nigend) nb from users';
$reponse=$bdd->prepare($sql);
$reponse->execute();
$donnees=$reponse->fetch();
if ($donnees['nb'] < 1){
	$msg = "Vous n'avez pas d'utilisateur local dans votre base de données.<br>Vous devez d'abord en créer un.";
	$type_msg=0;
	$javascript_button_valider='document.getElementById(\'submit_form\').value=\'creer_user\';form.submit();';
	$msg .=  '<br><input type="button" id ="creer_user" name="creer_user" value="CREER SUPER UTILISATEUR" class="btn btn-success" onclick="'.$javascript_button_valider.'">';
	$msg .= '<br>login : rone<br> Mot de passe : rone';

}


$col="col-xl-4";
echo '<div class="row">';
echo '<div class="'.$col.'" align=center ></div>';
echo '<div class="'.$col.'" align=center >';
if ($msg != '')
	msg($msg,$type_msg);
html_cadre("CONNEXION",$data);
echo '</div>';
echo '<div class="'.$col.'" align=center ></div>';
echo '</div>';
echo '<input type="hidden" id ="submit_form" name="submit_form" value="">';
pied_page();
if (isset($_SESSION[NAME]['nigend'])){
	echo "<script>window.location.replace(\"\")</script>";
}
die();
?>
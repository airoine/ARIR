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
//on ne peux pas appeler cette page sans passer par index.php
if (!defined('NAME')){
	require_once('error.php');
	die();
}

if (isset($_GET['option'])){
	redirection();
}

if (isset($_GET['option']) && isset($_POST['add']) && $_POST['add'] != '')
	unset($_GET['option']);

$error=array();
//Suppression d'un élement dans les tableaux à l'affichage
if (isset($_POST['del']) && $_POST['del'] != ""){
	switch ($_POST['del']){
		case 'TYPE_SEANCE':
			type_seance('DEL',$_POST['id_form']);
			break;
		case 'SEANCE':
			seance('DEL',$_POST['id_form']);
			break;	
		case 'RESSOURCE':
			ressource_seance('DEL',$_POST['id_form']);
			break;
	}	
}
//ajout d'un élément dans les tableaux d'affichage
if (isset($_POST['add']) && $_POST['add'] != ''){
	switch ($_POST['add']){
		case 'ressource':
			$error=ressource_seance('ADD');
			$pass=true;
			break;
		case 'type_seance':
			$error=type_seance('ADD');
			$pass=true;
			break;
		case 'seance':
			$error=seance('ADD');
			$pass=true;
			break;			
	}	
}

//modification  d'un élément dans les tableaux d'affichage
if (isset($_POST['modif'])  && $_POST['modif'] != ''){
	switch ($_POST['modif']){
		case 'type_seance':
			//modif d'un type de séance
			$error=type_seance('MODIF',$_POST['id_form']);
			$pass=true;
			break;
		case 'seance':
			//modif d'une séance
			$error=seance('MODIF',$_POST['id_form']);
			$pass=true;
			break;			
		case 'ressource':
			//modif d'une ressource
			$error=ressource_seance('MODIF',$_POST['id_form']);
			$pass=true;
			break;
	}
}


if ($error == array() && isset($pass)){	
	redirection();
}elseif (isset($pass))
	$_POST['modif'] = mb_strtoupper($_POST['modif']);



echo '<div class="row">';
echo '<div class="col-xl-3">';
echo '</div><div class="col-xl-6" align=center>';
$msg="Vous avez les droits d'administration sur le(s) calendrier(s) suivants:<br>";
if (isset($_SESSION[NAME]['cal']))
	$msg.= implode(', ',$_SESSION[NAME]['cal']);
else
	$msg.= "AUCUN CALENDRIER DISPONIBLE";
msg($msg,2,false);
echo "</div>";
echo '</div>';
echo '<div class="col-xl-3">';
echo '</div>';
echo '</div>';

//si aucun calendrier n'existe (initialisation de l'application ou réinitialisation)
if (!isset($_SESSION[NAME]['cal']))
	die();

if ($error != array()){
	msg("Les champs suivants ne sont pas complétés:<br>".implode(";",$error),1);
}
unset($data);
//Formulaire html de modification
if (isset($_POST['modif']) && $_POST['modif'] != ''){
	switch ($_POST['modif']){
		case 'RESSOURCE':
			//formulaire de modification de ressource
			$titre="Modification d'une ressource";
			$data=html_ressource('MODIF');
			$type_aff='RESSOURCE';
			break;
		case 'SEANCE':
			//modif d'une séance
			$titre="Modification d'une séance";
			$data=html_seance('MODIF');
			$type_aff='SEANCE';
			break;
		case 'TYPE_SEANCE':
			//modif d'un type séance
			$titre="Modification d'un type séance";
			$data=html_type_seance('MODIF');
			$type_aff='TYPE_SEANCE';
			break;
	}	
}
//formulaire html d'ajout
if (isset($_GET['add']) && $_GET['add'] != ''){
	switch ($_GET['add']){
		case 'ressource':
			//formulaire de modification de ressource
			$titre="Ajout d'une ressource";
			$data=html_ressource('ADD');
			$type_aff='RESSOURCE';
			break;
		case 'seance':
			//modif d'une séance
			$titre="Ajout d'une séance";
			$data=html_seance('ADD');
			$type_aff='SEANCE';
			break;
		case 'type_seance':
			//modif d'une ressource
			$titre="Ajout d'un type séance";
			$data=html_type_seance('ADD');
			$type_aff='TYPE_SEANCE';
			break;
	}
}

//formulaire html de duplication
if (isset($_POST['action']) && $_POST['action'] == "DUPLI_SEANCE"){
		//modif d'une séance
		$titre="Ajout d'une séance";
		$data=html_seance('DUPLI');
		$type_aff='SEANCE';
}

//affichage des formulaires de saisie
if (isset($data)){
	//On a besoin d'avoir un visuel sur le calendrier uniquement si on gère les séances
	if ($type_aff == 'SEANCE'){
		echo '<div class="row">';
		echo '<div class="col-xl-1" align=center></div>';
		echo '<div class="col-xl-6" align=center>';
		echo html_cadre($titre,$data);
		echo '</div>';
		echo '<div class="col-xl-5">';
		$event=array();
		if (isset($_POST['reload_cal'])){
			//récupération des séances disponibles
			$sql="select s.id,s.id_cal,s.id_type,s.comment,s.nb_pers,
					 s.date_debut,s.date_fin,s.heure_debut,s.heure_fin,
					 s.date_fin_inscription,ts.nom as nom_type,
					 ts.couleur,r.id as id_ressource,r.nom as nom_ressource,r.type
		 	 from seance s, type_seance ts, ressources r
		 	 where s.id_type=ts.id and r.id=s.id_ressource";
			//Si un calendrier est choisi, on réduit la requête à ce calendrier	
			$sql.= " and s.id_cal=:id_cal";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id_cal',$_POST['reload_cal'],PDO::PARAM_STR);
			$reponse->execute();
			$event=array();
			while ($donnees=$reponse->fetch()){
				$event[]=array('backgroundColor' => $donnees['couleur'],'title'=>$donnees['nom_type']." (".$donnees['nom_ressource'].')','description'=>$donnees['comment'],
						'url'=>'','start'=>$donnees['date_debut'].($donnees['heure_debut'] != '00:00:00'?'T'.$donnees['heure_debut']:''),
						'end'=>$donnees['date_fin'].($donnees['heure_fin'] != '00:00:00'?'T'.$donnees['heure_fin']:''));
			}	
		}
		
		if (isset($_POST['reload_ressource'])){
			//récupération des demandes de réservation
			$sql="select d.id as id_dde,d.*,r.* from dde_resa d,ressources r where d.id_ressource=r.id and r.id=:id and (status=1 or status=2)";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$_POST['reload_ressource'],PDO::PARAM_STR);
			$reponse->execute();
			while ($donnees=$reponse->fetch()){
				$comment="";
				if ($donnees['status'] == 1){
					$color= '#A9A9A9';
					$comment="En attente de validation";
				}else{
					$color=$donnees['couleur'];
					$comment="Réservé";
				}
				$url='';
				$event[]=array('backgroundColor' => $color,'title'=>$comment.' ('.$donnees['nom'].')','url'=>$url,'description'=>$donnees['comment'],
						'start'=>$donnees['date_debut'].($donnees['heure_debut'] != '00:00:00'?'T'.$donnees['heure_debut']:''),
						'end'=>$donnees['date_fin'].($donnees['heure_fin'] != '00:00:00'?'T'.$donnees['heure_fin']:''));
			}		
		}
		aff_calendrier($event);
		echo "<div id='calendar' style=\"background-color:#ffffff;\"></div>";	
		echo '</div>';
		echo '</div>';
	}else{
		echo '<div class="row">';
		echo '<div class="col-xl-2"></div>';
		echo '<div class="col-xl-8" align=center>';
		echo html_cadre($titre,$data);
		echo '</div>';
		echo '<div class="col-xl-2"></div>';
		echo '</div>';
	}
}else{//affichages des tableaux de données
	//l'option d'activation des inscriptions est active dans dans le fichier de conf (require/conf)
	if (INSCRIPTION && isset($_SESSION[NAME]['cal_visible'])){
		echo '<div class="row">';	
		echo '<div class="col-xl-12">';
		//Tableau des séances
		tableau_mes_seances();
		echo '</div>';
		echo '</div>';
		echo "<br>";
		echo '<div class="row">';
		echo '<div class="col-xl-6">';
		//Tableau des types séance	
		tableau_type_seance();	
		echo '</div>';
		echo '<div class="col-xl-6">';
		//Tableau des ressources de séance	
		tableau_ressource();
		echo '</div>';
		echo '</div>';
		echo "<br>";
		echo '<div class="row">';
		echo '<div class="col-xl-6" align=center>';
		if (isset($_POST['action']) && $_POST['action'] == "VISU_PERS"){
			//Tableau des personnes inscrites dans un type de séance
			tableau_pers_by_type();
		}
		//Tableau des logs du/des calendriers gérés par la personne connectée.
		echo '</div><div class="col-xl-6" align=center>';
		tableau_log();
		echo '</div>';
		echo '</div>';	
	}else{
		echo '<div class="row">';
		echo '<div class="col-xl-6">';
		//Tableau des ressources de séance
		tableau_ressource();
		echo '</div><div class="col-xl-6" align=center>';
		tableau_log();
		echo '</div>';
		echo '</div>';	
	}
}
//champs des actions demandées
echo '<input type="hidden" id ="add" name="add" value="'.(isset($_POST['add'])?$_POST['add']:'').'">';
echo '<input type="hidden" id ="modif" name="modif" value="'.(isset($_POST['modif'])?$_POST['modif']:'').'">';
echo '<input type="hidden" id ="del" name="del" value="">';
echo '<input type="hidden" id ="action" name="action" value="">';
echo '<input type="hidden" id ="id_form" name="id_form" value="'.(isset($_POST['id_form'])?$_POST['id_form']:'').'">';

?>
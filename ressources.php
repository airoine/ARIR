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
 * Page des réservations de ressources
 * Permet aussi de traiter les demandes
 *
 *
 */
//on ne peux pas appeler cette page sans passer par index.php
if (!defined('NAME')){
	require_once('error.php');
	die();
}
//initialisation des variables
$error=array();
$event=array();
$admin=false;
if (isset($_GET['visu']) && is_numeric($_GET['visu']) && !isset($_POST['id_form'])){
	$sql="select id from dde_resa where id=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$_GET['visu'],PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	if (isset($donnees['id']))
		$_POST['id_form']=$_GET['visu'];
	else 
		redirection();
}

$liste_ressources=ressources_reservables();
//si aucune ressource n'est accessible à la réservation
if (!isset($liste_ressources) || $liste_ressources == array()){
	echo '<div class="row">';
	echo '<div class="col-xl-3" align=center>';
	echo '</div>';
	echo '<div class="col-xl-6" align=center>';
	msg("Aucune ressource n'est disponible à la réservation",1);
	echo '</div>';
	echo '<div class="col-xl-3" align=center>';
	echo '</div>';
	echo '</div>';
	pied_page();
	die();
}




/*
 * 
 * Application des filtres + sauvegardes
 * 
 * 
 */

if (isset($_POST['raz_filtre']) && $_POST['raz_filtre'] == "Effacer les filtres"){
	unset($_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_ressource'],$_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_dde'],$_SESSION[NAME]['TIME_EVENT_RESSOURCES']);
	unset($_POST['filtres_ressource'],$_POST['filtres_dde'],$_POST['id_form']);	
}
//si on clique sur le bouton ok de la popup
//elle se fermera
if (isset($_POST['button_list_resa']) && $_POST['button_list_resa'] == "Ok")
	unset($_POST['id_form']);

if (isset($_POST['val_filtre']) && $_POST['val_filtre'] == "Appliquer les filtres"){
	unset($_SESSION[NAME]['TIME_EVENT_RESSOURCES'],$_POST['id_form']);
	if (isset($_POST['filtres_dde'])){
		$_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_dde']=$_POST['filtres_dde'];
	}else
		unset($_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_dde']);
	if (isset($_POST['filtres_ressource']))
		$_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_ressource']=$_POST['filtres_ressource'];
	else 
		unset($_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_ressource']);
}
/*
 * 
 * Gestion des filtres
 * 
 */
if (isset($_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_ressource']))
	$_POST['filtres_ressource']=$_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_ressource'];
if (isset($_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_dde']))
	$_POST['filtres_dde']=$_SESSION[NAME]['FILTRE'][$_GET['option']]['filtres_dde'];

//print_r($liste_ressources);
//permet de mettre à jour les libellés unités des anciennes versions
$sql="select count(*) nb from  dde_resa where lbl_unite is null";
$reponse=$bdd->prepare($sql);
$reponse->execute();
$donnees=$reponse->fetch();
if ($donnees['nb']>0){
	$sql="select distinct(unite_demandeur) from dde_resa where lbl_unite is null";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	while ($donnees=$reponse->fetch()){
		$info=ldap_info_cu($donnees['unite_demandeur']);
		$sql="update dde_resa set lbl_unite = :lbl_unite where unite_demandeur=:cu";
		$reponse2=$bdd->prepare($sql);
		$reponse2->bindvalue(':lbl_unite',$info[0]['businessou'][0],PDO::PARAM_STR);
		$reponse2->bindvalue(':cu',$donnees['unite_demandeur'],PDO::PARAM_STR);
		$reponse2->execute();
	}
	
	
}

//ajout d'une demande de réservation
if (isset($_POST['add']) && $_POST['add'] == "demande"){
	$error=verif_champs('DDE_RESA');
	if ($error == array()){
		if (!is_numeric($_POST['ressource']))
			$id_ressource=explode(' - ',$_POST['ressource']);
		else
			$id_ressource[]=$_POST['ressource'];
		$error=resa('ADD',$id_ressource[0]);	
	}
	if ($error == array()){
		redirection();
	}

}

//suppression d'une demande
if (isset($_POST['del']) && $_POST['del'] == "DDE"){
	resa('DEL',$_POST['id_form']);
	redirection();
}
//modification d'une demande
if (isset($_POST['modif']) && $_POST['modif'] == "demande"){
	$error=verif_champs('DDE_RESA',$_POST['id_form']);
	if ($error == array())
		$error=resa('MODIF',$_POST['id_form']);
	if ($error == array()){
		redirection();
	}
}
//validation d'une réservation
if (isset($_POST['valid']) && $_POST['valid'] == "valider"){
//	echo $_POST['id_form'];
	resa('VALID',$_POST['id_form']);
	redirection();
}
//refuser une demande
if (isset($_POST['refuser']) && $_POST['refuser'] == "refuser"){
	resa('REFUS',$_POST['id_form']);
	redirection();
}

//sécurité - on ne peut accéder à cette page sans accéder à une ressource
if (isset($_GET['option']) && is_numeric($_GET['option']) && isset($liste_ressources['RESSOURCES'][$_GET['option']])){
		$_POST['ressource']=$_GET['option'];
}else{
	echo "<script>window.location.replace(\"?p=2&option=".array_key_last($liste_ressources['RESSOURCES'])."\")</script>";
//	header('Location: ?p=2&option='.array_key_last($liste_ressources['RESSOURCES']));
	die();
}

//liste des ressources liées à cette ressource
if (!is_numeric($_POST['ressource'])){
	$numeric_ressource=explode(' - ',$_POST['ressource']);
	if (!is_numeric($numeric_ressource[0])){
		msg('Erreur dans la ressource',1);
		die();
	}else
		$_POST['ressource']=$numeric_ressource[0];
}
//print_r($_POST);
$ids_ressources[]=$_POST['ressource'];
$sql="select id from ressources where lier=:id";
$reponse=$bdd->prepare($sql);
$reponse->bindvalue(':id',$_GET['option'],PDO::PARAM_STR);
$reponse->execute();
while ($donnees=$reponse->fetch()){
	//gestion du filtre sur les ressources
	if (isset($_POST['filtres_ressource'])){
		if (in_array($donnees['id'],$_POST['filtres_ressource']))
				$ids_ressources[]=$donnees['id'];		
	}else
		$ids_ressources[]=$donnees['id'];
}

//purge de la ressource
foreach ($ids_ressources as $k=>$v){
	if (!is_numeric($v))
		break;
	purge_ressource($v);
}
//print_r($_SESSION);
$sql= "select id,nom,comment,time_modif from ressources where id in (".implode(',',$ids_ressources).") ";
$reponse=$bdd->prepare($sql);
$reponse->execute();
while ($donnees=$reponse->fetch()){
	//print_r2($donnees);
	//echo $_SESSION[NAME]['TIME_EVENT_RESSOURCES'][$donnees['id']];
	//si la date du dernier changement est différent qu'au dernier chargement
    //unset($_SESSION[NAME]['TIME_EVENT_RESSOURCES'][$donnees['id']]);
	$ressource_cal[]=array('id'=>$donnees['id'], 'title' => $donnees['nom']);
	//on réinitialise
	
	if (!isset($_SESSION[NAME]['TIME_EVENT_RESSOURCES'][$donnees['id']]) || $_SESSION[NAME]['TIME_EVENT_RESSOURCES'][$donnees['id']] != $donnees['time_modif']){	
		//echo 'toto';
		unset($event);
			$sql="select r.id, ts.nom,r.couleur,r.cu_admin,date_debut,date_fin,heure_debut,heure_fin, s.id as id_seance_ressource,s.comment
					from seance s,ressources r, type_seance ts
					where s.id_ressource=r.id and s.id_type=ts.id and r.couleur is not NULL";
			$sql .= " and r.id = :id ";
			$reponse2=$bdd->prepare($sql);
			$reponse2->bindvalue(':id',$donnees['id'],PDO::PARAM_STR);
			$reponse2->execute();
			while ($donnees2=$reponse2->fetch()){
				$description=strip_tags(preg_replace("/<br>|\n|\r/", "", $donnees2['comment']));
				$event[]=array('backgroundColor' => $donnees2['couleur'],'title'=>$donnees2['nom'],'description'=>$description,'url'=>'','resourceId'=>$donnees['id'],
						'start'=>$donnees2['date_debut'].($donnees2['heure_debut'] != '00:00:00'?'T'.$donnees2['heure_debut']:''),
						'end'=>$donnees2['date_fin'].($donnees2['heure_fin'] != '00:00:00'?'T'.$donnees2['heure_fin']:''));
			}

			//récupération des demandes de réservation
			$sql="select d.id as id_dde,d.*,r.* from dde_resa d,ressources r where d.id_ressource=r.id and r.id = :id and status in (1,2)";
			if (isset($_POST['filtres_dde']) && is_array($_POST['filtres_dde']))
				$sql.=" and (d.nigend_demandeur in (".implode(',',$_POST['filtres_dde']).") or d.unite_demandeur in (".implode(',',$_POST['filtres_dde'])."))";
			$reponse2=$bdd->prepare($sql);
			$reponse2->bindvalue(':id',$donnees['id'],PDO::PARAM_STR);
			$reponse2->execute();
			while ($donnees2=$reponse2->fetch()){

				$description=strip_tags(preg_replace("/<br>|\n|\r/", "", $donnees2['motif_dde']));
				$comment="";
				if (defined('TYPE_CAL_RESA') && TYPE_CAL_RESA == 'TIMELINE')
					$comment .= $donnees2['lbl_unite'];
				else{
					//si on est sur des ressources liées
					//on affiche le nom de la ressource
					if (count($ids_ressources) > 1)
						$comment.=$donnees2['nom']." ";
					//libellé de l'unité qui a fait la demande
					$comment.="(".$donnees2['lbl_unite'].')';
				}
									
				if ($donnees2['status'] == 1){
					$color= '#A9A9A9';
					$comment.=" - En attente";
				}else{
					$color=$donnees2['couleur'];
				}
				$url='?p='.$_GET['p'].'&option='.$_GET['option'].'&visu='.$donnees2['id_dde'].'&date_seance='.$donnees2['date_debut'];
				$event[]=array('backgroundColor' => $color,'title'=>$comment,'description'=>$description,'url'=>$url,'resourceId'=>$donnees['id'],
						'start'=>$donnees2['date_debut'].($donnees2['heure_debut'] != '00:00:00'?'T'.$donnees2['heure_debut']:''),
						'end'=>$donnees2['date_fin'].($donnees2['heure_fin'] != '00:00:00'?'T'.$donnees2['heure_fin']:''));
			}
			//echo "<font color=red>".$donnees['id']."</font>";
			$_SESSION[NAME]['TIME_EVENT_RESSOURCES'][$donnees['id']]=$donnees['time_modif'];
			if (isset($event))
				$_SESSION[NAME]['EVENT_RESSOURCES'][$donnees['id']]=$event;		
			else
				unset($_SESSION[NAME]['EVENT_RESSOURCES'][$donnees['id']]);
	}
	//die();
}	

$event_cumul=array();
//récupération des événements à afficher
foreach($ids_ressources as $k=>$v){
	if (isset($_SESSION[NAME]['EVENT_RESSOURCES'][$v])){
		foreach($_SESSION[NAME]['EVENT_RESSOURCES'][$v] as $k1=>$v1){
			$event_cumul[]=$v1;	
		}
	}
}

//print_r2($_SESSION[NAME]['EVENT_RESSOURCES']);
//affichage du calendrier
//aff_calendrier($event_cumul);
if (defined('TYPE_CAL_RESA') && TYPE_CAL_RESA == 'TIMELINE')
	aff_calendrier_timeline($event_cumul,$ressource_cal);
else 
	aff_calendrier($event_cumul);
if (isset($_POST['ressource']) && is_numeric($_POST['ressource']) && isset($_SESSION[NAME]['admin_ressource'][$_POST['ressource']]))
	$admin=true;


echo '<div class="row">';


echo '<div class="col-xl-2" align=center>';
$msg_info="Vous êtes sur le planning de <b><u>".$liste_ressources['RESSOURCES'][$_GET['option']]."</u></b>";
//suppression des balises paragraphe <p> et </p>
$msg_info.="<br><small><i>".str_replace('<p>','',str_replace('</p>','',$liste_ressources['COMMENT'][$_GET['option']]))."</i></small><br>";
$msg_info_admin="";
if ($admin){
	$msg_info_admin .= "<font color=green><b><u>Vous gérez cette ressource</u></b></font><br>";
	if (defined('VISUEL') && VISUEL){
		if (isset($_SERVER['HTTP_REFERER'])){
			$url=parse_url($_SERVER['HTTP_REFERER']);
			$link=$url['scheme']."://".$url['host'].$url['path'];			
		}else{
			$link="";				
		}
		if (isset($_POST['id_form']) && $_POST['id_form'] == 'lien'){
			$crypt_link=url_public();
			$sql="update ressources set link=:link where id in (".implode(',',$ids_ressources).")";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':link',$crypt_link,PDO::PARAM_STR);
			$reponse->execute();
		}
		if (isset($_POST['del']) && $_POST['del'] == "LINK"){
			$sql="update ressources set link=NULL where id in (".implode(',',$ids_ressources).")";
			$reponse=$bdd->prepare($sql);
			$reponse->execute();
		}
		$sql="select count(id) cnt,link from ressources where id in (".implode(',',$ids_ressources).") group by link";
		$reponse=$bdd->prepare($sql);
		$reponse->execute();
		$donnees=$reponse->fetch();
		if ($donnees['cnt'] == count($ids_ressources) && $donnees['link'] != NULL){
			$javascript_suppr="document.getElementById('del').value = 'LINK'; document.formulaire.submit();";
			$msg_info_admin.= '<br><i class="far fa-trash-alt" title="Supprimer" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer ce lien?\')){'.$javascript_suppr.'};"></i>';
			$msg_info_admin.= "&nbsp;<font color=green><b><u>Lien visualisation : </u></b></font><br><a href='".$link."?link=".$donnees['link']."'  target='_blank'>lien mois</a>
                                <br><a href='".$link."?link=".$donnees['link']."&type_aff=timeGridWeek'  target='_blank'>lien semaine</a>
                        <br><a href='".$link."?link=".$donnees['link']."&type_aff=timeGridDay'  target='_blank'>lien jour</a>";
		}else
			$msg_info_admin.= '<br><input type="button" id ="creat_lien" name="creat_lien" value="Creer Lien visuel" class="btn btn-info" onclick="document.getElementById(\'id_form\').value=\'lien\';formulaire.submit();">';
	}
}

//on affiche les ressources liées si elles existent
$liste_ressources_liees=ressources_reservables($_GET['option']);
if ($liste_ressources_liees != array()){
	$msg_info.="<small>Liste des ressources disponibles : </small><br>";
	foreach ($liste_ressources_liees['RESSOURCES'] as $k=>$v){
		$msg_info.= "<details><summary><b>".$v."</b></summary>";
		$msg_info.= $liste_ressources_liees['COMMENT'][$k]."</details>";		
	}
	$lbl_bouton="Demander une réservation";
}else{
	if (!isset($liste_ressources['EMAIL'][$_GET['option']])){
		$lbl_bouton="Faire une réservation";
		$msg_info.= "<small><font color=blue><b><u>Cette ressource ne demande pas de validation hiérarchique</u></b></font></small>";
	}else
		$lbl_bouton="Demander une réservation";	
}

if (isset($_POST['filtres_ressource']) || isset($_POST['filtres_dde']))
	$msg_info.= "<br><font color=red><b><u>Des filtres sont appliqués</u></b></font>";
	
$javascript_resa='document.getElementById(\'id_form\').value=\'ADD\';formulaire.submit();';
$javascript_list_resa='document.getElementById(\'id_form\').value=\'list_resa\';formulaire.submit();';
$javascript_filtres='document.getElementById(\'id_form\').value=\'filtres\';formulaire.submit();';
$msg_info.='<br><input type="button" id ="filtres" name="filtres" value="FILTRES" class="btn btn-warning" onclick="'.$javascript_filtres.'">';
$msg_info.='<br>';
$msg_info.='<br><input type="button" id ="resa" name="resa" value="'.$lbl_bouton.'" class="btn btn-success" onclick="'.$javascript_resa.'">';
$msg_info.='<br>';
$msg_info.='<br><input type="button" id ="list_resa" name="list_resa" value="Liste des réservations" class="btn btn-info" onclick="'.$javascript_list_resa.'">';
msg($msg_info,0,false);
if ($msg_info_admin != "")
	msg($msg_info_admin,1,false);


//on n'affiche pas le bouton de demande de réservation pour un profil visualisation
/*elseif ($_SESSION[NAME]['profil'] != 'visualisation')
	echo '<br><input type="button" id ="resa" name="resa" value="'.$lbl_bouton.'" class="btn btn-success" onclick="'.$javascript.'">';*/
echo '</div>';
//echo '</div>';
//echo '<div class="row">';
echo '<div class="col-xl-10 alert" style="background-color:#ffffff;border: 1px solid #4F3F8C;border-radius: 20px;" align=center>';
echo "<div id='calendar' style='background-color:#ffffff;height: 500px;' ></div>";
echo '</div>';
/*echo '<div class="col-xl-3" align=center>';

echo '</div>';*/
echo '</div>';//fin row
if (isset($_POST['id_form'])){	
	if ($_POST['id_form'] == "ADD")
		html_dde_resa('ADD');
	if (isset($_GET['visu']) || $_POST['modif'] == "DDE")
		html_dde_resa('VISU');
	if ($_POST['id_form'] == "filtres")
		html_filtres();
	if ($_POST['id_form'] == "list_resa")
		tableau_list_resa($_GET['option']);
}

function html_filtres(){
	global $bdd,$liste_ressources_liees;
	$msg_info="";
	//recherche de tous les nigend et les unités qui ont demandé des réservations
	$sql="select distinct nigend_demandeur,unite_demandeur from dde_resa ";
	if ($liste_ressources_liees != array())
	 	$sql .= "where id_ressource in (".implode(',',$liste_ressources_liees['ID']).")";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
    while ($donnees=$reponse->fetch()){
    	//si on n'est pas encore passé par là, on va récupérer les info nécessaires dans le ldap 
	 	if (!isset($_SESSION[NAME]['FILTRES']['NIGEND'][$donnees['nigend_demandeur']])){
		 	$info_nigend=ldap_info_user($donnees['nigend_demandeur']);
		 	//et on stock en session pour éviter d'interroger le ldap la prochaine fois
		 	$_SESSION[NAME]['FILTRES']['NIGEND'][$donnees['nigend_demandeur']]=$info_nigend;
	 	}else//si on déjà l'info en session, on la récupère
	 		$info_nigend=$_SESSION[NAME]['FILTRES']['NIGEND'][$donnees['nigend_demandeur']];
		//si on connait cet utilisateur
		if (isset($info_nigend[0]['displayname'][0]))
	 		$filtres_demandeurs[$donnees['nigend_demandeur']]=$info_nigend[0]['displayname'][0];
		//même chose pour l'unité
		if (!isset($_SESSION[NAME]['FILTRES']['NIGEND'][$donnees['unite_demandeur']])){
			$info_unite=ldap_info_cu($donnees['unite_demandeur']);
			$_SESSION[NAME]['FILTRES']['NIGEND'][$donnees['nigend_demandeur']]=$info_unite;
		}else
			$info_unite=$_SESSION[NAME]['FILTRES']['NIGEND'][$donnees['nigend_demandeur']];
		//si on connait cette unité, on récupère l'info
		if (isset($info_unite[0]['businessou'][0]))
			$filtres_demandeurs[$donnees['unite_demandeur']]= "*** ".$info_unite[0]['businessou'][0]." ***";
	}
	if (isset($filtres_demandeurs)){
		asort($filtres_demandeurs);
		$msg_info.= html_champ("", "filtres_dde",'select_100',array('Filtres demandeurs'=>$filtres_demandeurs));
	}
	
	if ($liste_ressources_liees != array())
		$msg_info.= html_champ("", "filtres_ressource",'select_100',array('Filtres ressources'=>$liste_ressources_liees['RESSOURCES']));
	$footer_popup="<input type='submit' id='val_filtre' name='val_filtre' value='Appliquer les filtres'>";
	$footer_popup.="&nbsp;<input type='submit' id='raz_filtre' name='raz_filtre' value='Effacer les filtres'>";
	$footer_popup.="&nbsp;<input type='submit' id='annuler' name='annuler' value='Annuler'>";
	//$msg_info.='<br><input type="button" id ="resa" name="resa" value="Filtres" class="btn btn-success" onclick="'.$javascript.'">';
	echo '<div id="myModal_filtres" class="modal">
      <div class="modal-dialog">
               <div class="modal-content">
                   <div class="modal-header  alert-warning">
                       <center><b>FILTRES</b></center>
                   </div>
                   <div class="modal-body alert-secondary">';
	echo $msg_info.'
                      </div>
                   <div class="modal-footer alert-secondary">
                       		'.$footer_popup.'
                      </div>
               </div>
      </div>
</div>';
	echo '<script>	$(document).ready(function(){
		$("#myModal_filtres").modal(\'show\');
	});</script>';
		
}


echo '<input type="hidden" id ="show_form" name="show_form" value="">';
echo '<input type="hidden" id ="add" name="add" value="">';
echo '<input type="hidden" id ="del" name="del" value="">';
echo '<input type="hidden" id ="valid" name="valid" value="">';
echo '<input type="hidden" id ="refuser" name="refuser" value="">';
echo '<input type="hidden" id ="modif" name="modif" value="">';
echo '<input type="hidden" id ="id_form" name="id_form" value="'.(isset($_POST['id_form'])?$_POST['id_form']:'').'">';
?>

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
@session_start();
header('Content-type: application/json');
require_once('../require/function.php');
require_once('../require/plugins/function_ldap.php');
$bdd=dbconnect('../require/conf/');
if (!isset($_GET['type']))
	die();


//type=inscription_seance&event='.$_GET['event']
if ($_GET['type'] == 'inscription_seance'){
	$sql=" select r.id,r.date_action,r.nigend,r.info_nigend,r.info_mail,r.nigend_origine,s.date_fin_inscription,s.id_cal,ts.nom as nom_seance,s.date_debut 
			from reservation r, seance s,type_seance ts where ts.id=s.id_type and s.id=r.id_seance and r.id_seance=:id_seance";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id_seance',$_GET['event'],PDO::PARAM_STR);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['nom'] = $donnees['nigend'].' - '.$donnees['info_nigend'];
		$data[$i]['date'] = datetime_to_php($donnees['date_action']);
		$data[$i]['action']="";
		//on peut supprimer une inscription à une séance si on est à l'origine de l'inscription ou si quelqu'un vous a inscrit.
		//voir l'admin du calendrier
		if ($donnees['nigend_origine'] == $_SESSION[NAME]['nigend'] || $donnees['nigend'] == $_SESSION[NAME]['nigend'] || isset($_SESSION[NAME]['cal'][$donnees['id_cal']])){
			$aff_bouton = true;
			//on vérifie si une date de fin d'inscription/désinscription a été définie
			//et on bloque l'utilisateur à la désincription
			//si on passe cette date
			if (!isset($_SESSION[NAME]['cal'][$donnees['id_cal']]) ){
				$origin = new DateTime(date("Y-m-d"));
				$target = new DateTime($donnees['date_fin_inscription']);
				$interval = $origin->diff($target);
				if ($origin > $target){
					$aff_bouton = false;
				}				
			}
			
			if ($aff_bouton){
				$javascript_suppr="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('action').value = 'DESINSCRIPTION'; document.formulaire.submit();";
				$data[$i]['action'] = '<i class="far fa-trash-alt" onclick="if(confirm(\'Etes-vous sûr de vouloir désinscrire ce personnel?\n\rUn mail lui sera envoyé automatiquement pour lui signifier son désincription\')){'.$javascript_suppr.'};"></i>&nbsp;&nbsp;';
			}
		}
		$data[$i]['action'] .='<i class="fas fa-envelope-open-text" onclick="location.href=\'mailto:'.$donnees['info_mail'].'?subject=Seance '.str_replace(array('"',"'"), ' ', $donnees['nom_seance']).' du '.date_to_php($donnees['date_debut']).'\';"></i>';
		//avec un profil visualisation, aucune action possible dans le tableau des inscriptions
		if ($_SESSION[NAME]['profil'] == 'visualisation')
			$data[$i]['action'] ='';
		$i++;
	}	
}


if ($_GET['type'] == 'list_inscriptions'){
	$sql=" select r.id_seance,s.date_debut,s.heure_debut,s.date_fin,s.heure_fin,ts.nom as nom_seance,c.nom as nom_cal,r.info_nigend as nom_demandeur
			from seance s, type_seance ts, calendrier c, reservation r  where s.id_type=ts.id and c.id=s.id_cal and r.id_seance=s.id";
	if ($_GET['id_cal'] != 0)
		$sql .= " and c.id=:id_cal ";
	$sql .= " order by date_fin";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id_cal',$_GET['id_cal'],PDO::PARAM_STR);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['demandeur'] = $donnees['nom_demandeur'];
		$data[$i]['type'] = $donnees['nom_seance'];
		$data[$i]['date'] = 'Du '.date_to_php($donnees['date_debut']).' '.$donnees['heure_debut'].'<br>Au '.date_to_php($donnees['date_fin']).' '.$donnees['heure_fin'];
		$data[$i]['action'] = '<a href="?p=1&event='.$donnees['id_seance'].'"><i class="far fa-eye" ></i></a>';	
		$i++;
	}
	
	
}



if ($_GET['type'] == 'ressources'){
	$sql=" select * from ressources ";
	if ($_SESSION[NAME]['profil'] != 'sadministrateur' && isset($_SESSION[NAME]['cal']) && is_array($_SESSION[NAME]['cal'])){
		$sql .= "where cal is null or cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
	}
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$nom[$donnees['id']]=$donnees['nom'];
		if ($donnees['cal'] == null)
			$data[$i]['nom'] = "<font color=red>".$donnees['nom']."</font>";
		else
			$data[$i]['nom'] = "<font color=green>".$donnees['nom']."</font>";
		$data[$i]['comment'] = $donnees['comment'];
		$data[$i]['type'] = $donnees['type'];
		if (isset($donnees['couleur']) && $donnees['couleur'] != '')
			$data[$i]['resa'] = 'OUI';
		else 
			$data[$i]['resa'] = 'NON';
		
		if (isset($donnees['lier']) && $donnees['lier'] != NULL && isset($nom[$donnees['lier']]))
			$data[$i]['lier'] = "<font color=green>".$nom[$donnees['lier']]."</font>";
			
		if (isset($donnees['cal']) && is_numeric($donnees['cal']) && isset($_SESSION[NAME]['cal'][$donnees['cal']]))
			$data[$i]['cal'] = $_SESSION[NAME]['cal'][$donnees['cal']];
		else 
			$data[$i]['cal'] = "Tous";
		if (($_SESSION[NAME]['profil'] == 'sadministrateur' && $_GET['prof'] == 'SADMIN')|| is_numeric($donnees['cal']) && array_key_exists($donnees['cal'],$_SESSION[NAME]['cal'])){
			$javascript_suppr="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('del').value = 'RESSOURCE'; document.formulaire.submit();";
			$javascript_modif="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('modif').value = 'RESSOURCE'; document.formulaire.submit();";
			$data[$i]['action'] = '<i class="far fa-trash-alt" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer cette ressource?\n\rEn le supprimant, vous allez supprimer TOUTES les séances de cette ressource\')){'.$javascript_suppr.'};"></i>';
			$data[$i]['action'] .= '&nbsp;<i class="far fa-edit" onclick="'.$javascript_modif.'"></i>';
		}else{
			$data[$i]['action'] ="";			
		}
		$i++;			
	}	
}

if ($_GET['type'] == 'liste_cal'){	
	$sql=" select * from calendrier";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['nom'] = $donnees['nom'];
		$data[$i]['visible'] = ($donnees['visible'] == 1? 'OUI': 'NON');
		$data[$i]['comment'] = $donnees['comment'];
		$data[$i]['unite'] = $donnees['unite'];
		$data[$i]['nigend'] = $donnees['nigend'];
		$javascript_suppr="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('del').value = 'CAL'; document.formulaire.submit();";
		$javascript_modif="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('modif').value = 'CAL'; document.formulaire.submit();";
		$data[$i]['action'] = '<i class="far fa-trash-alt" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer ce calendrier?\n\rVous supprimez aussi TOUS les types séance et les séances créés pour ce calendrier\')){'.$javascript_suppr.'};"></i>';
		$data[$i]['action'] .= '&nbsp;<i class="far fa-edit" onclick="'.$javascript_modif.'"></i>';
		$i++;			
	}
}

if ($_GET['type'] == 'type_seance'){
	$sql=" select * from type_seance where id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$javascript_dupli="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('action').value = 'VISU_PERS'; document.formulaire.submit();";
		$data[$i]['cal']=$_SESSION[NAME]['cal'][$donnees['id_cal']];
		$data[$i]['type_seance'] = '<u><font color=blue onclick="'.$javascript_dupli.'">'.$donnees['nom']."</font></u>";
		$data[$i]['couleur'] = '<svg width="60" height="20"><rect width="300" height="100" style="fill:'.$donnees['couleur'].'"/></svg>';
		$javascript_suppr="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('del').value = 'TYPE_SEANCE'; document.formulaire.submit();";
		$javascript_modif="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('modif').value = 'TYPE_SEANCE'; document.formulaire.submit();";
		$data[$i]['action'] = '<i class="far fa-trash-alt" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer ce type de séance?\')){'.$javascript_suppr.'};"></i>';
		$data[$i]['action'] .= '&nbsp;<i class="far fa-edit" onclick="'.$javascript_modif.'"></i>';
		$i++;
	}
}

if ($_GET['type'] == 'seances'){
	$sql="select s.id,c.nom as nom_cal,ts.nom as nom_seance,ts.couleur,s.comment, s.date_debut,s.date_fin from seance s,type_seance ts,calendrier c 
			where ts.id=s.id_type and c.id=s.id_cal and s.id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['id'] = $donnees['id'];
		$data[$i]['nom_cal'] = $donnees['nom_cal'];
		$data[$i]['nom_seance'] = "<font color='".$donnees['couleur']."'>".$donnees['nom_seance']."</font>";
		$data[$i]['comment'] = $donnees['comment'];
		$data[$i]['date_debut'] = date_to_php($donnees['date_debut']);
		$data[$i]['date_fin'] = date_to_php($donnees['date_fin']);
		$javascript_suppr="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('del').value = 'SEANCE'; document.formulaire.submit();";
		$javascript_modif="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('modif').value = 'SEANCE'; document.formulaire.submit();";
		$javascript_dupli="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('action').value = 'DUPLI_SEANCE'; document.formulaire.submit();";
		$data[$i]['action'] = '<i class="far fa-trash-alt" title="Supprimer" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer cette séance?\')){'.$javascript_suppr.'};"></i>';
		$data[$i]['action'] .= '<i class="far fa-edit" title="Editer" onclick="'.$javascript_modif.'"></i>';
		$data[$i]['action'] .= '<i class="far fa-copy" title="Dupliquer" onclick="'.$javascript_dupli.'"></i>';
		$i++;
	}	
}

if ($_GET['type'] == 'users' && $_SESSION[NAME]['profil'] == "sadministrateur"){
	/*$fields['nigend']='ID Utilisateur';
	$fields['displayname']='Nom';
	$fields['codeUnite']='Code service';
	$fields['unite']='Libellé service';*/
	$sql="select u.*, s.unite,s.codeUnite from users u,services s where u.codeUnite=s.codeUnite";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		//$data[$i]['id'] = $donnees['id'];
		$data[$i]['nigend'] = $donnees['nigend'];
		$data[$i]['displayname'] = "<b>".$donnees['uid']."</b>";
		$data[$i]['codeUnite'] =$donnees['codeUnite'];
		$data[$i]['unite'] = $donnees['unite'];
		$javascript_suppr="document.getElementById('id_form').value = '".$donnees['nigend']."'; document.getElementById('del').value = 'USER'; document.formulaire.submit();";
		$javascript_modif="document.getElementById('id_form').value = '".$donnees['nigend']."'; document.getElementById('modif').value = 'USER'; document.formulaire.submit();";
		$data[$i]['action'] = '<i class="far fa-trash-alt" title="Supprimer" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer cet utilisateur?\')){'.$javascript_suppr.'};"></i>';
		$data[$i]['action'] .= '<i class="far fa-edit" title="Editer" onclick="'.$javascript_modif.'"></i>';
		$i++;
	}
}
if ($_GET['type'] == 'services' && $_SESSION[NAME]['profil'] == "sadministrateur"){
	$sql="select s.*, u.codeUnite as tests from services s left join users u on s.codeUnite=u.codeUnite group by s.codeUnite";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['action'] = "";
		$data[$i]['codeunite'] =$donnees['codeUnite'];
		$data[$i]['unite'] = $donnees['unite'];		
		$javascript_suppr="document.getElementById('id_form').value = '".$donnees['codeUnite']."'; document.getElementById('del').value = 'SERVICE'; document.formulaire.submit();";
		$javascript_modif="document.getElementById('id_form').value = '".$donnees['codeUnite']."'; document.getElementById('modif').value = 'SERVICE'; document.formulaire.submit();";
		if ($donnees['tests'] == NULL)
			$data[$i]['action'] = '<i class="far fa-trash-alt" title="Supprimer" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer ce service?\')){'.$javascript_suppr.'};"></i>';
		$data[$i]['action'] .= '<i class="far fa-edit" title="Editer" onclick="'.$javascript_modif.'"></i>';
		$i++;
	}

}



if ($_GET['type'] == 'log'){
	$sql="select * from log where 1=1 ";
	if (is_array(NO_SHOW_LOG_4_NIGEND) && NO_SHOW_LOG_4_NIGEND != array()  && NO_SHOW_LOG_4_NIGEND != array(""))
		$sql .= " and nigend not in (".implode(',',NO_SHOW_LOG_4_NIGEND).")";
	
		
	if ($_SESSION[NAME]['profil'] != 'sadministrateur')
		$sql.= " and id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).") ";
	$sql .= " order by id desc";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['date_action'] = datetime_to_php($donnees['date_action']);
		$data[$i]['nigend'] = $donnees['nigend'];
		$data[$i]['comment'] = $donnees['comment'];
		$i++;
	}
}

/*
if ($_GET['type'] == 'log_admin'){
	$sql="select * from log order by id desc";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['date_action'] = datetime_to_php($donnees['date_action']);
		$data[$i]['nigend'] = $donnees['nigend'];
		$data[$i]['comment'] = $donnees['comment'];
		$i++;
	}
}*/



if ($_GET['type'] == 'list_ddes'){
	$list_id=ressources_reservables($_GET['ressource']);
	if ($list_id == array())
		$list_id['ID'][$_GET['ressource']]=$_GET['ressource'];
	$sql="select d.nigend_demandeur,d.id,d.date_debut,d.date_fin,d.heure_debut,d.heure_fin,l.nom,d.status,d.lbl_unite, d.motif_dde, d.nb_pers from dde_resa d,ressources l where l.id=d.id_ressource and l.id in(".implode(',',$list_id['ID']).") order by d.id desc";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		if ($donnees['status'] == 1)
			$color='#A9A9A9';
		elseif($donnees['status'] == 2)
			$color='green';
		elseif($donnees['status'] == 3)
			$color='red';
		$data[$i]['date_debut'] = date_to_php($donnees['date_debut']);
		if ($donnees['heure_debut'] != '00:00:00')
			$data[$i]['date_debut'].=" ".$donnees['heure_debut'];
		$data[$i]['date_fin'] = date_to_php($donnees['date_fin']);
		if ($donnees['heure_fin'] != '00:00:00')
			$data[$i]['date_fin'].=" ".$donnees['heure_fin'];
		$data[$i]['ressource'] = "<font color='".$color."'>".$donnees['nom']."</font>";
		if ($donnees['nigend_demandeur'] == $_SESSION[NAME]['nigend']){
			$javascript_suppr="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('del').value = 'DDE'; document.formulaire.submit();";
			$javascript_modif="document.getElementById('id_form').value = '".$donnees['id']."'; document.getElementById('modif').value = 'DDE'; document.formulaire.submit();";
			$data[$i]['action'] = '<i class="far fa-trash-alt" title="Supprimer" onclick="if(confirm(\'Etes-vous sûr de vouloir supprimer cette demande?\')){'.$javascript_suppr.'};"></i>';
			$data[$i]['action'] .= '<i class="far fa-edit" title="Editer" onclick="'.$javascript_modif.'"></i>';
		}
		$data[$i]['demandeur'] = $donnees['lbl_unite'];
		$data[$i]['nb_pers'] = $donnees['nb_pers'];
		$data[$i]['motif'] = $donnees['motif_dde'];
		$i++;
	}
}











if ($_GET['type'] == 'nb_inscription'){
	$sql="select count(r.id) nb,nigend,info_nigend from reservation r,seance s where s.id=r.id_seance and s.id_type=:id_seance group by nigend,info_nigend";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id_seance',$_GET['nb'],PDO::PARAM_STR);
	$reponse->execute();
	$i=0;
	while ($donnees=$reponse->fetch()){
		$data[$i]['nb_inscription'] = $donnees['nb'];
		$data[$i]['nigend'] = $donnees['nigend'];
		$data[$i]['info'] = $donnees['info_nigend'];
		$i++;
	}

}

if (isset($data))
    echo json_encode($data);
?>
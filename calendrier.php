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
if (isset($_POST['annuler2'])){
	echo "<script>window.location.replace(\"?p=1&event=".$_GET['event']."\")</script>";
}

if (isset($_GET['option'])){
	if ($_GET['option'] == 0 || isset($_SESSION[NAME]['sous_menu'][1][$_GET['option']])){
		$_SESSION[NAME]['GET_OPTION_INSCRIPTION']=$_GET['option'];
	}else{
		echo "<script>window.location.replace(\"error.php\")</script>";
	}
}elseif (isset($_SESSION[NAME]['GET_OPTION_INSCRIPTION']) 
		&& (isset($_SESSION[NAME]['sous_menu'][1][$_SESSION[NAME]['GET_OPTION_INSCRIPTION']]) || $_SESSION[NAME]['GET_OPTION_INSCRIPTION'] == 0)){
	$_GET['option']=$_SESSION[NAME]['GET_OPTION_INSCRIPTION'];	
	
}

//gestion des filtres
$filtre_type="";
$filtre_ressource="";
if (!isset($_SESSION[NAME]['FILTRES'][$_GET['option']]['type']) || isset($_POST['raz_filtre']))
	$_SESSION[NAME]['FILTRES'][$_GET['option']]['type']="";
if (!isset($_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource']) || isset($_POST['raz_filtre']))
	$_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource']="";

if (isset($_POST['val_filtre'])){
	if (isset($_POST['filtre_type']))
		$filtre_type=$_POST['filtre_type'];
	if (isset($_POST['filtre_ressource']))
		$filtre_ressource=$_POST['filtre_ressource'];
	$_SESSION[NAME]['FILTRES'][$_GET['option']]['type']=$filtre_type;
	$_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource']=$filtre_ressource;
}else{
	
	if ($_SESSION[NAME]['FILTRES'][$_GET['option']]['type'] != '')
		$filtre_type = $_SESSION[NAME]['FILTRES'][$_GET['option']]['type'];
	if ($_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource'] != '')
		$filtre_ressource = $_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource'];
}



//donnees des ressources
$ressource=array();
$id_cal_type_seance=array();
$type_seance=array();
$sql="select * from seance s,ressources r where s.id_ressource=r.id ";
if ($_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
	$sql.= " and id_cal=:id_cal";
if ($filtre_ressource != "")
	$sql .= " and r.id in (".implode(',',$filtre_ressource).")";
if ($filtre_type != "")
	$sql .= " and s.id_type in (".implode(',',$filtre_type).")";
$reponse=$bdd->prepare($sql);
//if ($_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
	$reponse->bindvalue(':id_cal',$_GET['option'],PDO::PARAM_STR);
$reponse->execute();
while ($donnees=$reponse->fetch()){
	$ressource[$donnees['id']]=$donnees['nom'];
	$type_ressource[$donnees['id']]=$donnees['type'];
	$comment_ressource[$donnees['id']]=$donnees['comment'];
}
//données du type des ressources
$nom_type_seance=array();
$sql=" select * from type_seance where 1=1";
if ($_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
	$sql .= " and id_cal=:id_cal";
if ($filtre_type != "")
	$sql .= " and id in (".implode(',',$filtre_type).")";

$reponse=$bdd->prepare($sql);
//if ($_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
	$reponse->bindvalue(':id_cal',$_GET['option'],PDO::PARAM_STR);
$reponse->execute();
while ($donnees=$reponse->fetch()){
	$nom_type_seance[$donnees['id']]=$donnees['nom'];
	$id_cal_type_seance[$donnees['id_cal']][$donnees['id']]=$donnees['nom'];
	$couleur_type_seance[$donnees['id']]=$donnees['couleur'];
	$type_seance_all[$donnees['id']]=$donnees['nom'];
}

//nombre de réservation par séance
$sql="select count(*) nb,id_seance from reservation r, seance s where r.id_seance=s.id ";
if ($_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
	$sql .= " and id_cal=:id_cal";
$sql .= " group by id_seance";
$reponse=$bdd->prepare($sql);
$reponse->bindvalue(':id_cal',$_GET['option'],PDO::PARAM_STR);
$reponse->execute();
while ($donnees=$reponse->fetch()){
	$count_resa_seance[$donnees['id_seance']]=$donnees['nb'];
}

/*affichage DES FILTRES*/
if (isset($_POST['action']) && $_POST['action'] == "filtres"){
	$data="";
	if ($_SESSION[NAME]['FILTRES'][$_GET['option']]['type'] != '')
		$_POST['filtre_type'] = $_SESSION[NAME]['FILTRES'][$_GET['option']]['type'];
	if ($_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource'] != '')
		$_POST['filtre_ressource'] = $_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource'];
	if (isset($id_cal_type_seance[$_GET['option']]))
		$type_seance=$id_cal_type_seance[$_GET['option']];
	elseif ($_GET['option'] == 0) 
		$type_seance=$type_seance_all;
		
	if ($type_seance != array())
		$data .= html_champ("Type :", "filtre_type",'select_10',array('Type de séance'=>$type_seance));
	if ($ressource != array())
		$data .= html_champ("Ressource :", "filtre_ressource",'select_10',array('Ressource de la séance'=>$ressource));
	
	$titre="Filtres";
	$footer_popup="";
	if ($data != ""){
		$footer_popup.="<input type='submit' id='val_filtre' name='val_filtre' value='Appliquer les filtres'>";
		$footer_popup.="&nbsp;<input type='submit' id='raz_filtre' name='raz_filtre' value='Effacer les filtres'>";
	}else{
		$data="Aucun filtre ne peut être appliqué car aucune donnée n'existe.";
	}
	$footer_popup.="&nbsp;<input type='submit' id='annuler' name='annuler' value='Annuler'>";
	echo '<script>	$(document).ready(function(){
		$("#myModal_filtres").modal(\'show\');
	});</script>';
	echo '<div id="myModal_filtres" class="modal fade" tabindex="-1">
      <div class="modal-dialog">
               <div class="modal-content">
                   <div class="modal-header  alert-warning">
                       <center><b>FILTRES</b></center>
                   </div>
                   <div class="modal-body alert-secondary">'.$data.'
                      </div>
                   <div class="modal-footer alert-secondary">'.$footer_popup.'
                      </div>
               </div>
      </div>
</div>';
	
}
/*
 * Affichage de la liste des inscrits
 * 
 */
if (isset($_POST['action']) && $_POST['action'] == "list_resa"){
	$data ='';
	$show['type']=1;
	$show['demandeur']=1;
	$show['date']=1;
	$show['action']=1;
	$fields['action']='';
	$fields['demandeur']='Demandeur';
	$fields['type']='Seance';
	$fields['date']='Date';
	$data .= tableau('SQL/sql_data_search.php?type=list_inscriptions&id_cal='.$_GET['option'],$fields,$show,550,false);
	$footer_popup ="<input type='submit' id='button_list_resa' name='button_list_resa' value='Ok' class='btn btn-success'>";
	echo '<div id="myModal_resa" class="modal">
      <div class="modal-dialog modal-xl">
               <div class="modal-content">
                   <div class="modal-header  alert-warning">
                       <center><b>Liste des inscrits</b></center>
                   </div>
                   <div class="modal-body">';
	echo $data.'
                      </div>
                   <div class="modal-footer alert-secondary">
                       		'.$footer_popup.'
                      </div>
               </div>
      </div>
</div>';
	echo '<script>	$(document).ready(function(){
		$("#myModal_resa").modal(\'show\');
	});</script>';
	
}



/*GESTION DES INSCRIPTIONS DANS LES SEANCES*/
if (isset($_POST['action']) && $_POST['action'] == "INSCRIPTION" && isset($_POST['id_form'])&&is_numeric($_POST['id_form'])){
	$sql="select id from reservation where nigend=:nigend and id_seance=:id_seance";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':nigend',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
	$reponse->bindvalue(':id_seance',$_POST['id_form'],PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	if (isset($donnees['id'])){
		msg('Vous êtes déjà inscrit à cette séance.',1);		
	}else{	
		$sql="insert into reservation (id_seance,date_action,nigend,info_nigend,info_mail,nigend_origine) values (:id_seance,now(),:nigend,:info_nigend,:info_mail,:nigend_origine)";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id_seance',$_POST['id_form'],PDO::PARAM_STR);
		$reponse->bindvalue(':nigend',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
		$reponse->bindvalue(':info_nigend',$_SESSION[NAME]['displayname'],PDO::PARAM_STR);
		$reponse->bindvalue(':info_mail',$_SESSION[NAME]['mail'],PDO::PARAM_STR);
		$reponse->bindvalue(':nigend_origine',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
		$reponse->execute();
		msg('Votre inscription a été prise en compte.',2);
		$cal=num_cal($_POST['id_form']);
		log_action('Inscription à la séance n°'.$_POST['id_form'],$cal);
	}	
}

/*GESTION DESINSCRIPTIONS DANS LES SEANCES POUR LES ADMIN DU CALENDRIER*/
if (isset($_POST['action']) && $_POST['action'] == "DESINSCRIPTION" && isset($_POST['id_form'])&&is_numeric($_POST['id_form'])){
		//vérification que l'utilisateur peut bien désincrire le personnel
		$sql="select r.id,r.id_seance,r.nigend,r.info_nigend,r.info_mail,r.nigend_origine,s.id_cal,s.date_debut,s.date_fin,s.heure_debut,s.heure_fin,ts.nom 
				from reservation r, seance s,type_seance ts 
				where s.id=r.id_seance  and ts.id=s.id_type and r.id=:id";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
		$reponse->execute();
		$donnees=$reponse->fetch();
		//l'utilisateur a le droit de supprimer cette inscription
		if (isset($donnees['id_cal']) && 
				(isset($_SESSION[NAME]['cal'][$donnees['id_cal']]) || $_SESSION[NAME]['nigend'] == $donnees['nigend'] || $_SESSION[NAME]['nigend'] == $donnees['nigend_origine'])){
			$txt_lbl='DESCINCRIPTION A LA SEANCE';
			$txt_mail = "Vous avez été désincrit(e) de la séance ";
			$txt_mail .= "\n\r".$donnees['nom']." du ".date_to_php($donnees['date_debut'])."-".$donnees['heure_debut']." au ".date_to_php($donnees['date_fin'])."-".$donnees['heure_fin'];
			$txt_mail .= "\n\r Action effectuée par  :".$_SESSION[NAME]['displayname'];
		//	$txt_mail .= $info_mail['TXT_MAIL'];
			$to=$donnees['info_mail'];
			envoi_mail($txt_lbl,$txt_mail,$to,'');
			//envoi_mails_desincription($_POST['id_form']);
			$sql="delete from reservation where id=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
			$reponse->execute();
			msg('Désinscription prise en compte.',2);
			$cal=num_cal($_GET['event']);
			log_action('Désinscription à la séance n°'.$_GET['event'].' du nigend => '.$donnees['nigend'],$cal);	
		}else
			msg('Désinscription impossible.',1);
}
/*GESTION DESINSCRIPTION PERSONNELLE*/
if (isset($_POST['action']) && $_POST['action'] == "ANN_INSCRIPTION" && isset($_POST['id_form'])&&is_numeric($_POST['id_form'])){
	$sql="delete from reservation where id_seance=:id_seance and nigend=:nigend";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id_seance',$_POST['id_form'],PDO::PARAM_STR);
	$reponse->bindvalue(':nigend',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
	$reponse->execute();
	msg('Votre désinscription a été prise en compte.',2);
	$cal=num_cal($_POST['id_form']);
	log_action('Désinscription à la séance n°'.$_POST['id_form'],$cal);
}

/*GESTION DES INSCRIPTIONS EN LOT DANS LES SEANCES*/
if (isset($_POST['action']) && $_POST['action'] == "INSCRIPTION_LOT" && isset($_POST['id_form'])&&is_numeric($_POST['id_form'])){	
	//Si on est sur une authentification locale
	//on récupère la liste des utilisateurs selectionnés et on les injecte 
	if (AUTH == "LOCAL"){
		//pour l'envoi de mail, on récupère les informations de la séance
		//si l'option mail est activée
		if (MAILS){
			$sql="select s.id_cal,s.date_debut,s.date_fin,s.heure_debut,s.heure_fin,ts.nom from seance s, type_seance ts where ts.id=s.id_type and s.id=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
			$reponse->execute();
			$data_seance=$reponse->fetch();
		}
		$data = traitement_array($_POST['ressource'],",");
		$sql="select nigend,uid,mail from users where nigend in (".$data.")";
		$reponse=$bdd->prepare($sql);
		$reponse->execute();
		while ($donnees=$reponse->fetch()){
				//print_r($donnees);
				$sql="insert into reservation (id_seance,date_action,nigend,info_nigend,info_mail,nigend_origine) values (:id_seance,now(),:nigend,:info_nigend,:info_mail,:nigend_origine)";
				$reponse2=$bdd->prepare($sql);
				$reponse2->bindvalue(':id_seance',$_POST['id_form'],PDO::PARAM_STR);
				$reponse2->bindvalue(':nigend',$donnees['nigend'],PDO::PARAM_STR);
				$reponse2->bindvalue(':info_nigend',$donnees['uid'],PDO::PARAM_STR);
				$reponse2->bindvalue(':info_mail',$donnees['mail'],PDO::PARAM_STR);
				$reponse2->bindvalue(':nigend_origine',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
				$reponse2->execute();
				$nigend_mail[]=$donnees['mail'];

		}
		if (MAILS){
			$txt_lbl= NAME.' -INCRIPTION A UNE SEANCE';
			$txt_mail = "Vous avez été incrit(e) à la séance ";
			$txt_mail .= "\n\r".$data_seance['nom']." du ".date_to_php($data_seance['date_debut'])."-".$data_seance['heure_debut']." au ".date_to_php($data_seance['date_fin'])."-".$data_seance['heure_fin'];
			$txt_mail .= "\n\r Action effectuée par  :".$_SESSION[NAME]['displayname'];
			//	$txt_mail .= $info_mail['TXT_MAIL'];
			$to=implode(';',$nigend_mail);
			$cc=$_SESSION[NAME]['mail'];
			envoi_mail($txt_lbl,$txt_mail,$to,$cc);
		}
	}else{	
		$i=0;
		while (isset($_POST["identite_".$i])){
			if (is_numeric($_POST["identite_".$i]))
				$nigends[$_POST["identite_".$i]]=$_POST["identite_".$i];
			$i++;
		}
		//si un nigend a bien été complété
		if (isset($nigends)){
			$sql="select id,nigend from reservation where nigend in (".implode(',',$nigends).") and id_seance=:id_seance";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id_seance',$_POST['id_form'],PDO::PARAM_STR);
			$reponse->execute();
			while ($donnees=$reponse->fetch()){
				$nigend_deja_inscrits[$donnees['nigend']]=$donnees['nigend'];
			}
			$cal=num_cal($_POST['id_form']);
			foreach ($nigends as $k=>$v_nigend){
				if (!isset($nigend_deja_inscrits[$v_nigend])){
					$ident=ldap_info_user($v_nigend);
					$data_identites = (isset($ident[0]['displayname'][0])?$ident[0]['displayname'][0]:'INCONNU');
					$data_mail = (isset($ident[0]['mail'][0])?$ident[0]['mail'][0]:'INCONNU');
					$sql="insert into reservation (id_seance,date_action,nigend,info_nigend,info_mail,nigend_origine) values (:id_seance,now(),:nigend,:info_nigend,:info_mail,:nigend_origine)";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id_seance',$_POST['id_form'],PDO::PARAM_STR);
					$reponse->bindvalue(':nigend',$v_nigend,PDO::PARAM_STR);
					$reponse->bindvalue(':info_nigend',$data_identites,PDO::PARAM_STR);
					$reponse->bindvalue(':info_mail',$data_mail,PDO::PARAM_STR);
					$reponse->bindvalue(':nigend_origine',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
					$reponse->execute();	
					if ($data_mail != 'INCONNU')
						$nigend_mail[]=$data_mail;
					log_action('Inscription du nigend '.$v_nigend.' à la séance n°'.$_POST['id_form'],$cal);
				}		
			}
			/*ENVOI DE MAIL*/
			//si on a bien récupéré les email des inscrits
			$sql="select s.id_cal,s.date_debut,s.date_fin,s.heure_debut,s.heure_fin,ts.nom from seance s, type_seance ts where ts.id=s.id_type and s.id=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			if (isset($nigend_mail)){
				$txt_lbl=NAME.' -INCRIPTION A UNE SEANCE';
				$txt_mail = "Vous avez été incrit(e) à la séance ";
				$txt_mail .= "\n\r".$donnees['nom']." du ".date_to_php($donnees['date_debut'])."-".$donnees['heure_debut']." au ".date_to_php($donnees['date_fin'])."-".$donnees['heure_fin'];
				$txt_mail .= "\n\r Action effectuée par  :".$_SESSION[NAME]['displayname'];
				//	$txt_mail .= $info_mail['TXT_MAIL'];
				$to=implode(';',$nigend_mail);
				$cc=$_SESSION[NAME]['mail'];
				envoi_mail($txt_lbl,$txt_mail,$to,$cc);
			}
		}
	}
	echo "<script>window.location.replace(\"?p=1&event=".$_GET['event']."\")</script>";
}

//récupération des séances disponibles
$sql="select s.id,s.id_cal,s.id_type,s.comment,s.nb_pers,
			 s.date_debut,s.date_fin,s.heure_debut,s.heure_fin,
			 s.date_fin_inscription,ts.nom as nom_type,
			 ts.couleur,r.id as id_ressource,r.nom as nom_ressource,r.type
 	 from seance s, type_seance ts, ressources r
 	 where s.id_type=ts.id and r.id=s.id_ressource ";
if ($_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
	$sql.= " and s.id_cal=:id_cal";
//si une restriction est demandé sur la ressource
if ($filtre_ressource != "")
	$sql.= " and r.id in (".implode(',',$filtre_ressource).")";
if ($filtre_type != "")
	$sql .= " and ts.id in (".implode(',',$filtre_type).")";
$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id_cal',$_GET['option'],PDO::PARAM_STR);
$reponse->execute();
$event=array();
while ($donnees=$reponse->fetch()){
		$seance_ressource[$donnees['id']]=$donnees['id_ressource'];
		$seance_cal[$donnees['id']]=$donnees['id_cal'];
		$seance_seance[$donnees['id']]=$donnees['id_type'];
		$seance_comment[$donnees['id']]=$donnees['comment'];
		$seance_date_debut[$donnees['id']]=$donnees['date_debut'];
		$seance_date_fin[$donnees['id']]=$donnees['date_fin'];
		$seance_date_fin_inscription[$donnees['id']]=$donnees['date_fin_inscription'];
		$seance_heure_debut[$donnees['id']]=$donnees['heure_debut'];
		$seance_heure_fin[$donnees['id']]=$donnees['heure_fin'];
		$seance_nb_pers[$donnees['id']]=$donnees['nb_pers'];
		$description=strip_tags(preg_replace("/<br>|\n|\r/", "", $donnees['comment']));
		if ($seance_nb_pers[$donnees['id']] > 0)
			$lbl_info=' ('.(isset($count_resa_seance[$donnees['id']])?$count_resa_seance[$donnees['id']]:'0').'/'.$seance_nb_pers[$donnees['id']].')';
		else
			$lbl_info='';
		$seance_type_lbl[$donnees['id']]=$donnees['nom_type'];
	$event[]=array('backgroundColor' => $donnees['couleur'],
					   'title'=>$donnees['nom_type'].$lbl_info,
					   'description'=>$description,
					   'url'=>(!isset($_GET['restriction'])?'?p=1&event='.$donnees['id']:''),
					   'start'=>$donnees['date_debut'].($donnees['heure_debut'] != '00:00:00'?'T'.$donnees['heure_debut']:''),
					   'end'=>$donnees['date_fin'].($donnees['heure_fin'] != '00:00:00'?'T'.$donnees['heure_fin']:''));
}
//cas de demande d'affichage des details d'une séance
if (isset($_GET['event']) && is_numeric($_GET['event']) && isset($seance_cal[$_GET['event']])){
	//entete du formulaire de détail de la séance
	$data_infos='<div class="container">';	
	//gestion des cas des séances à 0 personnel 
	//cette option permet d'utiliser un évenement pour information sans inscription possible.
	//par exemple, montrer les indispo des EGM, du PSIG, autres.
	//si on a des ouvertures d'inscription
	if ($seance_nb_pers[$_GET['event']] > 0){
		$comment_show=str_ireplace(array('<p>'),'',$seance_comment[$_GET['event']]);
        $comment_show=str_ireplace(array('</p>'),'<br>',$comment_show);
        $data_infos.= "<div class=\"alert alert-warning\"><center><b><u>Informations sur la séance</u></b><br>
                                         Ressource : ".$ressource[$seance_ressource[$_GET['event']]]." (".str_ireplace(array('<p>','</p>'),'',$comment_ressource[$seance_ressource[$_GET['event']]]).")<br>
                         Commentaires : ".$comment_show."</center>
                      </div>";
		//on doit vérifier si la personne qui se connecte est admin de cette séance
		//pour lui afficher des options supplémentaires
		$admin_event=false;
		if (isset($_SESSION[NAME]['cal']) && isset($_SESSION[NAME]['cal'][$seance_cal[$_GET['event']]]))
			$admin_event=true;	
		//on regarde si la personne connectée est déjà inscrite
		$sql="select id from reservation where nigend=:nigend and id_seance=:id_seance";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id_seance',$_GET['event'],PDO::PARAM_STR);
		$reponse->bindvalue(':nigend',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
		$reponse->execute();
		$inscrit=$reponse->fetch();
		//recherche du nombre de réservation déjà existantes
		$sql="select count(id) nb from reservation where id_seance=:id_seance";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id_seance',$_GET['event'],PDO::PARAM_STR);
		$reponse->execute();
		$donnees=$reponse->fetch();
		$nb_places_restantes=$seance_nb_pers[$_GET['event']] - $donnees['nb'];
		
		//demande d'inscription demandée - on affiche le formulaire
		if (isset($_POST['action']) && $_POST['action'] == "INSCRIPTION_LIST" || isset($_POST['action2']) && $_POST['action2'] == "INSCRIPTION_LIST"){
			$affich_bouton_inscription=true;
			$data_identites = '<div class="container">';
			//si le nombre d'inscription est supérieur au nbre de places restantes
			if (isset($_POST['nb_pers']) and $_POST['nb_pers']>$nb_places_restantes){
				msg('Il ne reste plus assez de place à cette séance pour inscrire <b>'.$_POST['nb_pers'].'</b> personne(s)',1);
				$_POST['nb_pers']=$nb_places_restantes;			
			}
			//si le nbre de personne n'est pas défini, on considère que c'est 1
			if (!isset($_POST['nb_pers']))
				$_POST['nb_pers']=1;
			//affichage du nbre de place restantes
			$data_identites.= "<div class=\"alert alert-info\">Il reste <b>".$nb_places_restantes."</b> places (sur ".$seance_nb_pers[$_GET['event']].").";
		    if (MAILS) 
				$data_identites.= "<br><u>Chaque personne inscrite par vous <b>recevra un mail pour lui signifier son inscription</b></u>";
			$data_identites.= "</div>";

			if (AUTH == "LOCAL"){
				$sql="select nigend,uid from users where nigend not in (select nigend from reservation where id_seance=:id_seance)";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id_seance',$_GET['event'],PDO::PARAM_STR);
				$reponse->execute();
				while ($donnees=$reponse->fetch()){
					$liste_pers_dispo[$donnees['nigend']]=$donnees['nigend'].'-'.$donnees['uid'];
					
				}
				if (isset($liste_pers_dispo))
					$data_identites .= html_champ("Personnes :", "ressource",'select_'.$nb_places_restantes,array('Personnes disponibles'=>$liste_pers_dispo));
				else{
					$data_identites.= "<div class=\"alert alert-warning\">Toutes les personnes présentes dans l'application sont déjà inscrites à cette séance</div>";
					$affich_bouton_inscription=false;
				}
				
			}else{		
				//on affiche les champs pour entrer les identités des personnes à inscrire
				$data_identites .= "<div class=\"form-group\">
			                      <label>Nombre de personnes à inscrire :</label>";
				$data_identites .= '<input type="number" id="tentacles" name="nb_pers" min="0" max="50" step="1" value="'.$_POST['nb_pers'].'" onblur="document.formulaire.submit();">';
				$data_identites .= "</div>";
				$if=0;
				$identif=0;
				while (round($_POST['nb_pers']) != $if){
					$data_identites .= "<div class=\"form-group\">
					                      <label>Identité :</label>";
					$data_identites.= '<input type="number" name="identite_'.$if.'" id="identite_'.$if.'"
					value="'.(isset($_POST['identite_'.$if])?$_POST['identite_'.$if]:'').'"
							placeholder="ID du personnel" onblur="document.formulaire.submit();">';
					if (isset($_POST['identite_'.$if]) && is_numeric($_POST['identite_'.$if])){
						$ident=ldap_info_user($_POST['identite_'.$if]);
						$data_identites .='&nbsp;<i><b>'.(isset($ident[0]['displayname'][0])?$ident[0]['displayname'][0]:'INCONNU').'</b></i>';
						$identif++;
					}
					$data_identites .= "</div>";
					$if++;
				}
			}
			$javascript_inscription="document.getElementById('id_form').value = '".$_GET['event']."'; document.getElementById('action').value = 'INSCRIPTION_LOT'; document.formulaire.submit();";
			$lbl_popup='Etes-vous sûr de vouloir inscrire les personnels ci-dessus pour cette séance?';
			if (MAILS)
				$lbl_popup.='\n\rRAPPEL: chaque personnel recevra un mail de confirmation';
			if ($affich_bouton_inscription)
				$data_identites .= '<br><input type="button" id ="inscription" name="inscription" value="Inscrire les personnels ci-dessus" class="btn btn-success" onclick="if(confirm(\''.$lbl_popup.'\')){'.$javascript_inscription.'};">&nbsp;&nbsp;';
			$data_identites .= '<input type="submit" id ="annuler2" name="annuler2" value="Annuler" class="btn btn-danger">';
			$data_identites .= '</div>';
			//affichage du formulaire
			html_cadre("Personne(s) à inscrire à la séance",$data_identites);
			echo '<input type="hidden" id ="action2" name="action2" value="INSCRIPTION_LIST">';		
		}else{	//si on n'est pas dans une demande d'inscription à une séance
			//on affiche le infos de la séance et un tableau des personnes déjà inscrites
			$show['nom']=1;
			$show['date']=1;
			$show['action']=1;
			$fields['nom']='Nom';
			$fields['date']='Date inscription';
			$fields['action']='';		
			
			$data_infos.= "<div class=\"alert alert-info\">
		                 Il reste <b>".$nb_places_restantes."</b> places (sur ".$seance_nb_pers[$_GET['event']].")
		              </div>";
			$data_infos .= tableau('SQL/sql_data_search.php?type=inscription_seance&event='.$_GET['event'],$fields,$show,350,false);
			
			$titre="Inscription/Désincription à la séance ".$seance_type_lbl[$_GET['event']]." du <font color=red>".
					(isset($seance_date_debut[$_GET['event']])?date_to_php($seance_date_debut[$_GET['event']]):'').
					($seance_heure_debut[$_GET['event']] != '00:00:00'?"-".$seance_heure_debut[$_GET['event']]:'').
					"</font> au <font color=red>".
					(isset($seance_date_fin[$_GET['event']])?date_to_php($seance_date_fin[$_GET['event']]):'').
					($seance_heure_fin[$_GET['event']] != '00:00:00'?"-".$seance_heure_fin[$_GET['event']]:'')."</font>";
			//gestion de la fin d'inscription/désincription
			$aff_bouton=true;
			if (isset($seance_date_fin_inscription[$_GET['event']])){
				$origin = new DateTime(date("Y-m-d"));
				$target = new DateTime($seance_date_fin_inscription[$_GET['event']]);
				$interval = $origin->diff($target);
				if ($origin < $target)
					$titre .="<br><font color=green><u>ATTENTION:</u> Fin d'inscription le ".date_to_php($seance_date_fin_inscription[$_GET['event']])."</font>";
				if ($origin == $target)
					$titre .="<br><font color=DarkRed><u>ATTENTION:</u> DERNIER JOUR POUR S'INSCRIRE/DESINSCRIRE</font>";
				if ($origin > $target){
					$titre .="<br><font color=red><u>ATTENTION:</u> VOUS NE POUVEZ PLUS VOUS INSCRIRE/DESINSCRIRE A CETTE SEANCE<br>Contacter l'administrateur de ce calendrier pour une inscription/désincription</font>";
					$aff_bouton = false;
				}
			}
			//si le personnel connecté a un profil différent que la visualisation 
			//et que l'on demande à afficher les boutons
			if ($_SESSION[NAME]['profil'] != 'visualisation' && ($aff_bouton || $admin_event)){
				if ($nb_places_restantes > 0){
					$javascript_inscription="document.getElementById('id_form').value = '".$_GET['event']."'; document.getElementById('action').value = 'INSCRIPTION'; document.formulaire.submit();";
					$javascript_inscription_list="document.getElementById('id_form').value = '".$_GET['event']."'; document.getElementById('action').value = 'INSCRIPTION_LIST'; document.formulaire.submit();";
				}
				$javascript_desincription="document.getElementById('id_form').value = '".$_GET['event']."'; document.getElementById('action').value = 'ANN_INSCRIPTION'; document.formulaire.submit();";
				if ($nb_places_restantes > 0 && !isset($inscrit['id']))
					$data_infos .= '<br><input type="button" id ="inscription" name="inscription" value="M\'inscrire" class="btn btn-success" onclick="if(confirm(\'Etes-vous sûr de vouloir vous inscrire pour cette séance?\')){'.$javascript_inscription.'};">';
				if (isset($inscrit['id']))
					$data_infos .= '&nbsp;&nbsp;<input type="button" id ="desinscription" name="desinscription" value="Me desinscrire" class="btn btn-warning" onclick="if(confirm(\'Etes-vous sûr de vouloir vous désinscrire pour cette séance?\')){'.$javascript_desincription.'};">';
				if ($nb_places_restantes > 0)
					$data_infos .= '&nbsp;&nbsp;<input type="button" id ="inscription" name="inscription" value="Inscrire des personnels" class="btn btn-info" onclick="'.$javascript_inscription_list.';">';
			}
			$data_infos .= '&nbsp;&nbsp;<input type="submit" id ="annuler" name="annuler" value="Terminer" class="btn btn-danger">';
			$data_infos .= '<input type="hidden" name="date_seance" id="date_seance" value="'.$seance_date_debut[$_GET['event']].'">';
			if ($admin_event){
				$sql="select r.info_mail,ts.nom,s.date_debut from reservation r,type_seance ts,seance s where r.id_seance=s.id and ts.id=s.id_type and r.id_seance=:id_seance";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id_seance',$_GET['event'],PDO::PARAM_STR);
				$reponse->execute();
				while ($donnees=$reponse->fetch()){
					$mail[]=$donnees['info_mail'];
					$nom_type_seance=$donnees['nom'];
					$date_debut=$donnees['date_debut'];
				}
				if (isset($mail)){
					$texte_mail="Bonjour%0D%0A%0D%0AVous êtes inscrits à la séance citée en objet.%0D%0A%0D%0ACordialement";
					$data_infos .= '&nbsp;&nbsp;<button onclick="location.href=\'mailto:'.implode(',',$mail).'?subject=Seance '.str_replace(array('"',"'"), ' ', $nom_type_seance).' du '.date_to_php($date_debut).'&body= '.$texte_mail.'\';" class="btn btn-secondary">Générer @mail collectif</button>';
				}
			}
			
		}			
	}else{
		$titre = "Informations sur l'évenement<br>";
		$titre.="<font color=green>".
				(isset($seance_date_debut[$_GET['event']])?date_to_php($seance_date_debut[$_GET['event']]):'').
				($seance_heure_debut[$_GET['event']] != '00:00:00'?"-".$seance_heure_debut[$_GET['event']]:'').
				"</font> au <font color=green>".
				(isset($seance_date_fin[$_GET['event']])?date_to_php($seance_date_fin[$_GET['event']]):'').
				($seance_heure_fin[$_GET['event']] != '00:00:00'?"-".$seance_heure_fin[$_GET['event']]:'')."</font>";
		$data_infos.= "<div class=\"alert alert-warning\"><center><b><u>Informations</u></b><br>
					 Ressource : ".$ressource[$seance_ressource[$_GET['event']]]." (".$comment_ressource[$seance_ressource[$_GET['event']]].")<br>
	                 Commentaires : ".$seance_comment[$_GET['event']]."</center>
	              </div>";
		$data_infos .= '<center><input type="submit" id ="annuler" name="annuler" value="Terminer" class="btn btn-danger"></center>';
	}
	$data_infos .= '</div>';
	if (isset($titre))
		html_cadre($titre,$data_infos);
}else{	
	aff_calendrier($event);
	echo '<div class="row">';
	if (!isset($_GET['restriction'])){
		echo '<div class="col-xl-2" align=center >';
		
		
		if (defined('LOGO')){
			echo '<img src="img/'.LOGO.'" style="width:100%"><br><br>';
		}
		if (isset($_SESSION[NAME]['calendrier'])){
			if ($_GET['option'] == 0)
				$msg_info="Vous êtes sur les calendriers <b><u>Mutualisés</u></b>";
			else
				$msg_info="Vous êtes sur le calendrier <b><u>INSCRIPTION ".$_SESSION[NAME]['sous_menu'][1][$_GET['option']]."</u></b>";
			//si plusieurs calendriers sont disponibles, on propose la mutualisation de calendrier
			if (count($_SESSION[NAME]['sous_menu'][1]) > 1 && $_SESSION[NAME]['GET_OPTION_INSCRIPTION'] != 0)
				$msg_info.= "<br><b><u><a href='?p=1&option=0'>Mutualiser les calendriers</a></u></b>";		
			//si des filtres sont appliqués, on affiche l'information
			if ($_SESSION[NAME]['FILTRES'][$_GET['option']]['type'] != '' || $_SESSION[NAME]['FILTRES'][$_GET['option']]['ressource'] != '')				
				$msg_info.= "<br><font color=red><b><u>Des filtres sont appliqués</u></b></font>";				
			$javascript_list_resa='document.getElementById(\'action\').value=\'list_resa\';form.submit();';
			$javascript_filtres='document.getElementById(\'action\').value=\'filtres\';form.submit();';
			$msg_info.='<br><input type="button" id ="filtres" name="filtres" value="FILTRES" class="btn btn-warning" onclick="'.$javascript_filtres.'">';
			$msg_info.='<br>';
			$msg_info.='<br><input type="button" id ="resa" name="resa" value="Liste des réservations" class="btn btn-info" onclick="'.$javascript_list_resa.'">';
			msg($msg_info,0,false);
			echo '</div><div class="col-xl-10 alert" style="background-color:#FFFFFF; border: 2px solid #4F3F8C; border-radius: 20px;" align=center>';
			/*msg('Filtres appliqués :<br>'.$filtres,0,false);
				*/
		}else{
			msg('Aucun calendrier disponible',1,false);
				
		}
		
		
		
	}else 
		echo '<div class="col-xl-12" align=center>';
	echo "<div id='calendar' style=\"background-color:#ffffff;\"></div>";
	echo '</div>';
}
echo '<input type="hidden" id ="action" name="action" value="">';
echo '<input type="hidden" id ="id_form" name="id_form" value="">';
?>

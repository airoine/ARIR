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
 * Cette page permet d'offrir un visuel sur un calendrier sans passer par une authentification
 * Cela peut être utile pour afficher des données en info à un ensemble de personne
 * ex: vous avez un calendrier de réservation de salle. Vous affichez la page de ce calendrier devant la salle (sans besoin d'authentification)
 * Il est possible d'afficher les calendriers au mois, à la semaine, à la journée 
 * 
 * 
 * 
 */


//on ne peux pas appeler cette page sans passer par index.php
if (!defined('NAME')){
	require_once('error.php');
	die();
}
//on ne veut pas afficher les menus
$_GET['restriction']=true;
$event=array();
//on reprend les entêtes et on fait un refresh de 15s
entete(15);

//récupération des ressources liées à ce lien
$sql= "select id,nom,comment,time_modif from ressources where link=:id";
$reponse=$bdd->prepare($sql);
$reponse->bindvalue(':id',$_GET['link'],PDO::PARAM_STR);
$reponse->execute();
while ($donnees=$reponse->fetch()){
	$ressource_cal[]=array('id'=>$donnees['id'], 'title' => $donnees['nom']);
	//on réinitialise
	$ids_ressources[]=$donnees['id'];
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
					//libellé du service qui a fait la demande
					$comment.="(".$donnees2['lbl_unite'].')';
			}
					
			if ($donnees2['status'] == 1){
				$color= '#A9A9A9';
				$comment.=" - En attente";
			}else{
				$color=$donnees2['couleur'];
			}
			$event[]=array('backgroundColor' => $color,'title'=>$description."-".$comment,'description'=>$description,'url'=>'','resourceId'=>$donnees['id'],
							'start'=>$donnees2['date_debut'].($donnees2['heure_debut'] != '00:00:00'?'T'.$donnees2['heure_debut']:''),
							'end'=>$donnees2['date_fin'].($donnees2['heure_fin'] != '00:00:00'?'T'.$donnees2['heure_fin']:''));
	}

}
//si on a demandé un affichage particulier, sinon par défaut c'est au mois.
if (isset($_GET['type_aff']) &&  in_array($_GET['type_aff'], array("dayGridMonth","timeGridWeek","timeGridDay","listYear","multiMonthYear")))
	$type_aff=$_GET['type_aff'];
else
	$type_aff="dayGridMonth";

//le Timeline n'est pas disponible en version opensource. 
//si vous souhaitez l'activer, rendez-vous sur le site de fullcalendar
if (defined('TYPE_CAL_RESA') && TYPE_CAL_RESA == 'TIMELINE')
	aff_calendrier_timeline($event,$ressource_cal);
else
	aff_calendrier($event,$type_aff);
echo "<div id='calendar' style='background-color:#ffffff;' ></div>";
pied_page();
?>
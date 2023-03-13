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
$menu_page=array();
$menu_name=array();
$menu_page[100]='accueil.php';
$_SESSION[NAME]['sous_menu'][10]=array();
if ($_SESSION[NAME]['profil'] == 'utilisateur' || $_SESSION[NAME]['profil'] == 'administrateur' || $_SESSION[NAME]['profil'] == 'sadministrateur' || $_SESSION[NAME]['profil'] == 'visualisation'){
		//l'option d'activation des inscriptions est active dans dans le fichier de conf (require/conf)
		if (INSCRIPTION && isset($_SESSION[NAME]['sous_menu'][1])){
			$menu_page[1]='calendrier.php';
    		$menu_name[1]='Inscriptions';
		}
		//l'option d'activation des réservations est active dans dans le fichier de conf (require/conf)
    	if (RESA_SALLES){    		
    		$liste_ressources=ressources_reservables();
    		if (isset($liste_ressources['RESSOURCES'])){
    			$menu_page[2]='ressources.php';
    			$menu_name[2]='Réservation';
    			$_SESSION[NAME]['sous_menu'][2]=$liste_ressources['RESSOURCES']; 
    			
    		}
    	}
}
//page des admin d'un calendrier
if ($_SESSION[NAME]['profil'] == 'administrateur' || $_SESSION[NAME]['profil'] == 'sadministrateur'){
    	$menu_page[10]='admin.php';
    	$menu_name[10]='Administration';
    	//l'option d'activation des inscriptions est active dans dans le fichier de conf (require/conf)
    	if (INSCRIPTION && isset($_SESSION[NAME]['cal_visible']))
    		$_SESSION[NAME]['sous_menu'][10]=array('synthese'=>'Synthèse','seance'=>'Ajout d\'une séance','type_seance'=>'Ajout d\'un type séance','ressource'=>'Ajout d\'une ressource');
    	else 
    		$_SESSION[NAME]['sous_menu'][10]=array('synthese'=>'Synthèse','ressource'=>'Ajout d\'une ressource');
}
//page des superadmin
if($_SESSION[NAME]['profil'] == 'sadministrateur'){
    	$menu_page[11]='sadmin.php';
    	$menu_name[11]='Super Admin';
    	if (AUTH == "LOCAL")
    		$_SESSION[NAME]['sous_menu'][11]=array('synthese'=>'Synthèse','ressource'=>'Ajout d\'une ressource','cal'=>'Ajout de calendrier','user'=>'Ajout Utilisateur','service'=>'Ajout d\'un service','conf'=>'Config générale');
    	else 
    		$_SESSION[NAME]['sous_menu'][11]=array('synthese'=>'Synthèse','ressource'=>'Ajout d\'une ressource','cal'=>'Ajout de calendrier','conf'=>'Config générale');
}	
ksort($menu_name);
ksort($menu_page);
ksort($_SESSION[NAME]['sous_menu']);
?>
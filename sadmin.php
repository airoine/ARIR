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
 * Page des Super admin de l'application
 * 
 * 
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
//redirection vers la page d'installation/configuration
if (isset($_GET['add']) && $_GET['add'] == "conf"){
	require_once('install.php');
	die();	
}
//Modification du formulaire validée
if (isset($_POST['modif'])){
	switch ($_POST['modif']){
		//cas de modification d'une ressource
		case 'ressource':
			$error=ressource_seance('MODIF',$_POST['id_form']);
			$pass=true;
			break;
		//cas de modification d'un calendrier
		case 'cal':
			$error=calendrier('MODIF',$_POST['id_form']);
			$pass=true;
			break;
		//cas de modification d'un utilisateur
		case 'user':
			$error=user('MODIF',$_POST['id_form']);
			$pass=true;
			break;
		//cas de modification d'un service
		case 'service':
			$error=services('MODIF',$_POST['id_form']);
			$pass=true;
			break;
	}
}
//demande de suppression validée
if (isset($_POST['del'])){
	switch ($_POST['del']){
		//cas de suppression d'une ressource
		case 'RESSOURCE':
			//suppression de la ressource
			ressource_seance('DEL',$_POST['id_form']);
			$pass=true;
			break;
		//cas de suppression d'un calendrier
		case 'CAL':
			calendrier('DEL',$_POST['id_form']);
			$pass=true;
			break;
		//cas de suppression d'un utilisateur
		case 'USER':
			$error=user('DEL',$_POST['id_form']);
			$pass=true;
			break;
		//cas de suppression d'un service
		case 'SERVICE':
			$error=services('DEL',$_POST['id_form']);
			$pass=true;
			break;
	}
}
//demande d'ajout validée	
if (isset($_POST['add'])){
	switch ($_POST['add']){
		//cas ajout d'une ressource
		case 'ressource':
			$error=ressource_seance('ADD');
			$pass=true;
			break;
		//cas ajout d'un calendrier
		case 'cal':
			$error=calendrier('ADD');
			$pass=true;
			break;	
		//cas ajout d'un utilisateur
		case 'user':
			$error=user('ADD');
			$pass=true;
			break;
		//cas ajout d'un service
		case 'service':
			$error=services('ADD');
			$pass=true;
			break;
	}
}

if ($error == array() && isset($pass)){
	redirection();
}elseif (isset($pass))
	$_POST['modif'] = mb_strtoupper($_POST['modif']);

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
			break;
		case 'CAL':
			//modif d'un calendrier
			$titre="Modification d'un calendrier";
			$data=html_calendrier('MODIF');
			break;
		case 'USER':
			//modif d'un utilisateur
			$titre="Modification d'un utilisateur";
			$data=html_user('MODIF');
			break;
		case 'SERVICE':
			//modif d'un service
			$titre="Modification d'un service";
			$data=html_service('MODIF');
			break;
	}
}
//formulaire html d'ajout
if (isset($_GET['add']) && $_GET['add'] != ''){
	switch ($_GET['add']){
		case 'ressource':
			//formulaire d' ajout de ressource
			$titre="Ajout d'une ressource";
			$data=html_ressource('ADD');
			break;
		case 'cal':
			//ajout d'une séance
			$titre="Ajout d'un calendrier";
			$data=html_calendrier('ADD');
			break;		
		case 'user':
			//ajout d'un utilisateur
			$titre="Ajout d'un utilisateur";
			$data=html_user('ADD');
			break;
		case 'service':
			//modif d'un service
			$titre="Ajout d'un service";
			$data=html_service('ADD');
			break;
	}
}

if (isset($data)){	
	echo '<div class="row">';
	echo '<div class="col-xl-3">';
	echo '</div><div class="col-xl-6" align=center>';
	html_cadre($titre,$data);
	echo '</div>';
	echo '<div class="col-xl-3">';
	echo '</div>';
}else{
	//affichage des tableaux de données
	echo '<div class="row">';
		echo '<div class="col-xl-4">';
		tableau_ressource('SADMIN');
		echo '</div>';
		echo '<div class="col-xl-4" align=center>';
		tableau_calendrier();
		echo '</div>';
		echo '<div class="col-xl-4" align=center>';
		tableau_log();
		echo '</div>';
	echo '</div>';
	echo "<br>";
	echo '<div class="row">';
	if (AUTH == "LOCAL"){
		echo '<div class="col-xl-6" align=center>';
		tableau_users();
		echo '</div>';
		echo '<div class="col-xl-6" align=center>';
		tableau_services();
		echo '</div>';
	}

	echo '</div>';	
}
//champs des actions demandées
echo '<input type="hidden" id ="add" name="add" value="'.(isset($_POST['add'])?$_POST['add']:'').'">';
echo '<input type="hidden" id ="modif" name="modif" value="'.(isset($_POST['modif'])?$_POST['modif']:'').'">';
echo '<input type="hidden" id ="del" name="del" value="">';
echo '<input type="hidden" id ="action" name="action" value="">';
echo '<input type="hidden" id ="id_form" name="id_form" value="'.(isset($_POST['id_form'])?$_POST['id_form']:'').'">';
?>
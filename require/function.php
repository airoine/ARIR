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
 * Fonction de génération d'un id aléatoire 
 * sur 20 caractères
 *
 */

function url_public(){
	$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$string = '';
	for($i=0; $i<20; $i++){
		$string .= $chars[rand(0, strlen($chars)-1)];
	}
	return $string;
}

/*
 * fonction array_key_first pour une version PHP < 7.3.0
 * 
 * 
 */
if (!function_exists('array_key_first')) {
	function array_key_first(array $arr) {
		foreach($arr as $key => $unused) {
			return $key;
		}
		return NULL;
	}
}

/*
 * fonction array_key_last pour une version PHP < 7.3.0
 *
 *
 */
if (! function_exists("array_key_last")) {
	function array_key_last($array) {
		if (!is_array($array) || empty($array)) {
			return NULL;
		}
			
		return array_keys($array)[count($array)-1];
	}
}

/*
 * fontion qui permet d'excécuter un fichier SQL
 * $file => fichier SQL a excécuter
 * 
 * 
 */
function executeSqlFile($file="SQL/calendriers.sql"){
	if (function_exists("dbconnect_install"))
		$bdd=dbconnect_install();
	else
		global $bdd;
	if ($bdd){
		$req = file_get_contents($file);
		$array = explode(PHP_EOL, $req);
		$requete="";
		foreach ($array as $sql) {
			//echo $sql;
			if ($sql != '' && substr(trim($sql),0,2)!="--" && substr(trim($sql),0,3)!="/*!"){// suppression des commentaires
				$requete.= $sql;
				if (substr(trim($sql),-1) == ";"){
					$reponse=$bdd->prepare($requete);
					$reponse->execute();
					$requete="";
				}
			}
		}
	}
}



//fonction qui permet de purger 
//les demandes de réservation sur une ressource
function purge_ressource($id_ressource){
	global $bdd;
	if (!is_numeric($id_ressource))
		return false;
	$donnees=info_ressource($id_ressource);
	//une purge sur cette ressource est activée
	if ($donnees['purge_cycle'] != 0){
		$sql="select count(id) nb from dde_resa where date_fin < :date_fin";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':date_fin',date('Y-m-d', strtotime(' - '.$donnees['purge_cycle'].' days')),PDO::PARAM_STR);
		$reponse->execute();
		$donnees2=$reponse->fetch();
		if ($donnees2['nb'] > 0){
			$sql="delete from dde_resa where date_fin < :date_fin";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':date_fin',date('Y-m-d', strtotime(' - '.$donnees['purge_cycle'].' days')),PDO::PARAM_STR);
			$reponse->execute();
			log_action("Purge de la ressource ".$donnees['nom'].". ".$donnees2['nb']." demandes supprimées",$donnees['cal']);
			unset($_SESSION[NAME]['EVENT_RESSOURCES'][$id_ressource]);
		}
	}

}




/*
 * 
 * Cette fonction sert juste à mettre à jour le timestamp
 * de la table ressource.
 * Cette action permet de savoir si un élement de cette ressource
 * ou une demande de réservation de cette ressource a été fait
 * afin d'optimiser le temps de chargement des calendriers
 * (on ne charge les données que si elles ont changé)
 * 
 */
function maj_ressource_timestamp($id_ressource){
	global $bdd;
	$sql="update ressources set time_modif=CURRENT_TIMESTAMP where id=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$id_ressource,PDO::PARAM_STR);
	$reponse->execute();		

}

/*
 * function pour signifer qu'une donnée a été modifiée
 * 
 */
function maj_admin_timestamp(){
	global $bdd;
	$sql="insert into admin (name,tvalue) values ('ADMIN_MODIF',now()) ON DUPLICATE KEY UPDATE tvalue=now()";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();	
}

/*
 * fonction de redirection après validation
 * 
 */
function redirection($reset=false){
	$location="";
	if (!$reset){
		if (isset($_GET['p'])){
			$location .="?p=".$_GET['p'];
			//si on est dans la partie admin/sadmin de l'application
			if (in_array($_GET['p'], array(10,11))){
				if (isset($_GET['option'])){
					$location .="&add=".$_GET['option'];
					unset($_GET['option']);
				}
			}				
		}
		
		if (isset($_GET['option']))
			$location .="&option=".$_GET['option'];
		if (isset($_GET['restriction']))
			$location .="&restriction=true";
		if (isset($_POST['date_seance']))
			$location .="&date_seance=".$_POST['date_seance'];
		if (isset($_GET['date_seance']))
			$location .="&date_seance=".$_GET['date_seance'];
	}else{
		$url=explode('/',$_SERVER['REQUEST_URI']);
	    $nb_values=count($url);
	    array_pop($url);
	    $location=implode('/',$url);
	}
	echo "<script>window.location.replace(\"$location\")</script>";
}

/*
 * Génération d'un mail
 * 
 */
function envoi_mails_dde_resa($id,$status=''){
    if (!MAILS)
        return false;
	$info_mail=recup_mail($id);
	if ($status == ''){
		$txt_lbl=NAME.' - Demande de réservation de ressource : '.$info_mail['LBL_RESSOURCE'];
		$txt_mail = "Une nouvelle demande de réservation a été effectuée";
		$txt_mail .= "<br> Demande effectuée par  :<b>".$_SESSION[NAME]['displayname']."</b>";
		$txt_mail .= "<br>".$info_mail['TXT_MAIL'];
		$to=$info_mail['MAIL_UNIT_ADMIN'];
		$cc=$info_mail['MAIL_DDE'];
		if (MAIL_CC_ORGA)
			$cc .= ';'.$info_mail['MAIL_UNIT_DDE'];
	}else{
		$txt_lbl=NAME.' - Suivi de réservation de ressource '.$info_mail['LBL_RESSOURCE'];
		$txt_mail="La demande effectuée a été ";
		$to=$info_mail['MAIL_DDE'];
		$cc=$info_mail['MAIL_UNIT_ADMIN'];
		if (MAIL_CC_ORGA)
			$cc .= ';'.$info_mail['MAIL_UNIT_DDE'];
		if ($status == 2){
			$txt_lbl .= ' ACCEPTEE';
			$txt_mail.=" <b><font color=green>ACCEPTEE</font></b>";			
		}elseif ($status == 3){
			$txt_lbl .= ' REFUSEE';
			$txt_mail.= ' <b><font color=red>REFUSEE</font></b>';			
		}elseif ($status == 4){
			$txt_lbl .= ' SUPPRIMEE';
			$txt_mail.= ' <b><font color=red>SUPPRIMEE</font></b>';			
		}
		$txt_mail .= $info_mail['TXT_MAIL'];
		$txt_mail.="<br>".$_SESSION[NAME]['displayname'];
	}
	envoi_mail($txt_lbl,$txt_mail,$to,$cc);

}

/*
 * 
 * fonction d'envoi de mail
 * en fonction de la configuration (local/sso)
 * 
 */

function envoi_mail($titre,$txt,$to,$cc){
	if (MAILS){
		// Formatage et envoi du message
		$txt_html=format_mail_html($txt);
		if (SEND_MAILS == "MAIL_SSO"){
			require_once('require/plugins/SSO.php');
			sso_mail($titre,$txt_html,$to,$cc);		
		}elseif(SEND_MAILS == "MAIL_SRV"){

			// Pour envoyer un mail HTML, l'en-tête Content-type doit être défini
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-type: text/html; charset=iso-8859-1';
			
			// En-têtes additionnels
			$headers[] = 'To: '.str_replace(';', ',', $to);
			$headers[] = 'Cc: '.str_replace(';', ',', $cc);			
			// Envoi
			mail($to, $titre, $txt_html, implode("\r\n", $headers));
			
		}else{
			log_action("MAIL NON ENVOYE ".$titre."///".$txt."///".$to."///".$cc);
			return false;
			
		}

		
		log_action("MAIL ENVOYE ".$titre."///".$txt."///".$to."///".$cc);
		return true;
		
	}else{
		
		log_action("MAIL NON ENVOYE ".$titre."///".$txt."///".$to."///".$cc);
		return false;
	}
	
	
}

/*
 * génération d'un mail au format html
 * 
 * 
 */

function format_mail_html($txt){
	$txt_html='<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Demande de réservation</title>
    <style>
      /* -------------------------------------
          GLOBAL RESETS
      ------------------------------------- */
	
      /*All the styling goes here*/
	
      img {
        border: none;
        -ms-interpolation-mode: bicubic;
        max-width: 20%;
      }
	
      body {
        background-color: #f6f6f6;
        font-family: sans-serif;
        -webkit-font-smoothing: antialiased;
        font-size: 14px;
        line-height: 1.4;
        margin: 0;
        padding: 0;
        -ms-text-size-adjust: 100%;
        -webkit-text-size-adjust: 100%;
      }
	
      table {
        border-collapse: separate;
        mso-table-lspace: 0pt;
        mso-table-rspace: 0pt;
        width: 100%; }
        table td {
          font-family: sans-serif;
          font-size: 14px;
          vertical-align: top;
      }
	
      /* -------------------------------------
          BODY & CONTAINER
      ------------------------------------- */
	
      .body {
        background-color: #f6f6f6;
        width: 100%;
      }
	
      /* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
      .container {
        display: block;
        margin: 0 auto !important;
        /* makes it centered */
        max-width: 580px;
        padding: 10px;
        width: 580px;
      }
	
      /* This should also be a block element, so that it will fill 100% of the .container */
      .content {
        box-sizing: border-box;
        display: block;
        margin: 0 auto;
        max-width: 580px;
        padding: 10px;
      }
	
      /* -------------------------------------
          HEADER, FOOTER, MAIN
      ------------------------------------- */
      .main {
        background: #ffffff;
        border-radius: 3px;
        width: 100%;
      }
	
      .wrapper {
        box-sizing: border-box;
        padding: 20px;
      }
	
      .content-block {
        padding-bottom: 10px;
        padding-top: 10px;
      }
	
      .footer {
        clear: both;
        margin-top: 10px;
        text-align: center;
        width: 100%;
      }
        .footer td,
        .footer p,
        .footer span,
        .footer a {
          color: #999999;
          font-size: 12px;
          text-align: center;
      }
	
      /* -------------------------------------
          TYPOGRAPHY
      ------------------------------------- */
      h1,
      h2,
      h3,
      h4 {
        color: #000000;
        font-family: sans-serif;
        font-weight: 400;
        line-height: 1.4;
        margin: 0;
        margin-bottom: 30px;
      }
	
      h1 {
        font-size: 35px;
        font-weight: 300;
        text-align: center;
        text-transform: capitalize;
      }
	
      p,
      ul,
      ol {
        font-family: sans-serif;
        font-size: 14px;
        font-weight: normal;
        margin: 0;
        margin-bottom: 15px;
      }
        p li,
        ul li,
        ol li {
          list-style-position: inside;
          margin-left: 5px;
      }
	
      a {
        color: #3498db;
        text-decoration: underline;
      }
	
      /* -------------------------------------
          BUTTONS
      ------------------------------------- */
      .btn {
        box-sizing: border-box;
        width: 100%; }
        .btn > tbody > tr > td {
          padding-bottom: 15px; }
        .btn table {
          width: auto;
      }
        .btn table td {
          background-color: #ffffff;
          border-radius: 5px;
          text-align: center;
      }
        .btn a {
          background-color: #ffffff;
          border: solid 1px #3498db;
          border-radius: 5px;
          box-sizing: border-box;
          color: #3498db;
          cursor: pointer;
          display: inline-block;
          font-size: 14px;
          font-weight: bold;
          margin: 0;
          padding: 12px 25px;
          text-decoration: none;
          text-transform: capitalize;
      }
	
      .btn-primary table td {
        background-color: #3498db;
      }
	
      .btn-primary a {
        background-color: #3498db;
        border-color: #3498db;
        color: #ffffff;
      }
	
      /* -------------------------------------
          OTHER STYLES THAT MIGHT BE USEFUL
      ------------------------------------- */
      .last {
        margin-bottom: 0;
      }
	
      .first {
        margin-top: 0;
      }
	
      .align-center {
        text-align: center;
      }
	
      .align-right {
        text-align: right;
      }
	
      .align-left {
        text-align: left;
      }
	
      .clear {
        clear: both;
      }
	
      .mt0 {
        margin-top: 0;
      }
	
      .mb0 {
        margin-bottom: 0;
      }
	
      .preheader {
        color: transparent;
        display: none;
        height: 0;
        max-height: 0;
        max-width: 0;
        opacity: 0;
        overflow: hidden;
        mso-hide: all;
        visibility: hidden;
        width: 0;
      }
	
      .powered-by a {
        text-decoration: none;
      }
	
      hr {
        border: 0;
        border-bottom: 1px solid #f6f6f6;
        margin: 20px 0;
      }
	
      /* -------------------------------------
          RESPONSIVE AND MOBILE FRIENDLY STYLES
      ------------------------------------- */
      @media only screen and (max-width: 620px) {
        table.body h1 {
          font-size: 28px !important;
          margin-bottom: 10px !important;
        }
        table.body p,
        table.body ul,
        table.body ol,
        table.body td,
        table.body span,
        table.body a {
          font-size: 16px !important;
        }
        table.body .wrapper,
        table.body .article {
          padding: 10px !important;
        }
        table.body .content {
          padding: 0 !important;
        }
        table.body .container {
          padding: 0 !important;
          width: 100% !important;
        }
        table.body .main {
          border-left-width: 0 !important;
          border-radius: 0 !important;
          border-right-width: 0 !important;
        }
        table.body .btn table {
          width: 100% !important;
        }
        table.body .btn a {
          width: 100% !important;
        }
        table.body .img-responsive {
          height: auto !important;
          max-width: 100% !important;
          width: auto !important;
        }
      }
	
      /* -------------------------------------
          PRESERVE THESE STYLES IN THE HEAD
      ------------------------------------- */
      @media all {
        .ExternalClass {
          width: 100%;
        }
        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
          line-height: 100%;
        }
        .apple-link a {
          color: inherit !important;
          font-family: inherit !important;
          font-size: inherit !important;
          font-weight: inherit !important;
          line-height: inherit !important;
          text-decoration: none !important;
        }
        #MessageViewBody a {
          color: inherit;
          text-decoration: none;
          font-size: inherit;
          font-family: inherit;
          font-weight: inherit;
          line-height: inherit;
        }
        .btn-primary table td:hover {
          background-color: #34495e !important;
        }
        .btn-primary a:hover {
          background-color: #34495e !important;
          border-color: #34495e !important;
        }
      }
	
    </style>
  </head>
  <body class="">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body">
      <tr>
        <td>&nbsp;</td>
        <td class="container">
          <div class="content">
	
            <!-- START CENTERED WHITE CONTAINER -->
            <table role="presentation" class="main">
	
              <!-- START MAIN CONTENT AREA -->
              <tr>
                <td class="wrapper">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                      <td align="center">';
	if (defined("LOGO_MAIL"))
		$txt_html.= '<img  id="logo_bep" src="'.LOGO_MAIL.'" alt=""><br>';
			
	$txt_html.= '       <p>Bonjour,</p>
                        <p>'.$txt.'</p>
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary">
                          <tbody>
                            <tr>
                              <td align="center">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                  <tbody>
                                    <tr>
                                      <td> <a href="'.$_SERVER['HTTP_REFERER'].'" target="_blank">Se rendre sur l\'application</a> </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                        <p>Bonne journée</p>
                        <p></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            <!-- END MAIN CONTENT AREA -->
            </table>
            <!-- END CENTERED WHITE CONTAINER -->
	
            <!-- START FOOTER -->
            <div class="footer">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td class="content-block">
                    <span class="apple-link">Mail généré par l\'application ARIR <br><i> Application de Réservations d\'Inscriptions et de Ressources</i></span>
                  </td>
                </tr>
                <tr>
                  <td class="content-block powered-by">
                  </td>
                </tr>
              </table>
            </div>
            <!-- END FOOTER -->
	
          </div>
        </td>
        <td>&nbsp;</td>
      </tr>
    </table>
  </body>
</html>
';
	return $txt_html;
	
}

/*
 * Récupération des @mails
 * 
 * 
 */


function recup_mail($id_demande){
	global $bdd;
	$sql="select d.date_debut,d.heure_debut,d.date_fin,d.heure_fin,r.nom,d.nigend_demandeur,d.unite_demandeur,r.cu_admin,c.unite,r.email 
		  from dde_resa d, ressources r left join calendrier c on c.id=r.cal  
		  where r.id=d.id_ressource and d.id=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$id_demande,PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	$mail_unite_admin = $donnees['email'];
	$mail_demandeur = '';
	$mail_unite_demandeur = '';
	if (AUTH == "LOCAL"){
	    $sql="select u.mail user_mail, s.mail service_mail from users u,services s where u.codeUnite=s.codeUnite and nigend= :id_demandeur";
	    $reponse=$bdd->prepare($sql);
	    $reponse->bindvalue(':id_demandeur',$donnees['nigend_demandeur'],PDO::PARAM_STR);
	    $reponse->execute();
	    $donnees2=$reponse->fetch();
	    $mail_demandeur = $donnees2['user_mail'];
	    $mail_unite_demandeur = $donnees2['service_mail'];
	}else{
    	//récupération du mail du demandeur
    	$info_user=ldap_info_user($donnees['nigend_demandeur']);
    	if (isset($info_user[0]['mail'][0]))
		  $mail_demandeur=$info_user[0]['mail'][0];
    	//récupération du mail unité de rattachement du demandeur
    	if (isset($info_user[0]['codeuniteservice'][0]))
    		$info_cu_demandeur=ldap_info_cu($info_user[0]['codeuniteservice'][0]);
    	if (isset($info_cu_demandeur[0]['mail'][0]))
    		$mail_unite_demandeur = $info_cu_demandeur[0]['mail'][0];	
    }
	$lbl_ressource = $donnees['nom'];
	$text_mail = "<br>Réservation : <b>".$donnees['nom']."</b>";
	$text_mail .= "<br>Dates : <b>".date_to_php($donnees['date_debut'])." ".$donnees['heure_debut']." => ".date_to_php($donnees['date_fin'])." ".$donnees['heure_fin']."</b>";
	return array('MAIL_UNIT_ADMIN'=>$mail_unite_admin,'MAIL_UNIT_DDE'=>$mail_unite_demandeur,'MAIL_DDE'=>$mail_demandeur,'TXT_MAIL'=>$text_mail,'LBL_RESSOURCE'=>$lbl_ressource);
}

/*
 * Tableau des calendriers (super admin)
 * 
 * 
 */

function tableau_calendrier(){
	$data ='<div class="container">';
	$show=array();
	$fields=array();
	$data ='<div class="container">';
	$show['nom']=1;
	$show['visible']=1;
	$show['comment']=1;
	$show['unite']=1;
	$show['nigend']=1;
	$show['action']=1;

	$fields['nom']='Nom';
	$fields['visible']='Visible';
	$fields['comment']='Comment';
	$fields['unite']='Service';
	$fields['nigend']='Id Utilisateur';
	$fields['action']='';
	$data .= tableau('SQL/sql_data_search.php?type=liste_cal',$fields,$show,350,false);
	$data .= '</div>';
	$titre="Liste des calendriers  <a href='?p=11&add=cal'><i class=\"far fa-plus-square\" ></i></a><br><small>Gestion des calendriers dans l'application</small>";
	html_cadre($titre,$data);
}

/*
 * Tableau des types de séances (admin)
 * 
 * 
 */

function tableau_type_seance(){
	$data ='<div class="container">';
	//si la personne connectée gère plusieurs calendriers
	if (count($_SESSION[NAME]['cal']) > 1){
		$show['cal']=1;
		$fields['cal']='Cal';
	}
	$show['type_seance']=1;
	$show['couleur']=1;
	$show['action']=1;
	$fields['type_seance']='Type de séance';
	$fields['couleur']='Couleur';
	$fields['action']='';
	$data .= tableau('SQL/sql_data_search.php?type=type_seance',$fields,$show,350,false);
	$data .= '</div>';
	$titre="Type de séance  <a href='?p=10&add=type_seance'><i class=\"far fa-plus-square\" ></i></a><br><small>les types de séance sont liés à un calendrier</small>";
	html_cadre($titre,$data);
}

/*
 * tableau des ressources (Sadmin et admin)
 * 
 */

function tableau_ressource($type='ADMIN'){
	$data ='<div class="container">';	
	if (isset($_SESSION[NAME]['cal']) && count($_SESSION[NAME]['cal']) > 1){
		$show['cal']=1;
		$fields['cal']='Cal';
	}
	$show['nom']=1;
	$show['comment']=1;
	$show['type']=1;
	if (RESA_SALLES){
		$show['resa']=1;
		$show['lier']=1;
	}
	$show['action']=1;
	$fields['nom']='nom';
	$fields['comment']='Comment';
	$fields['type']='Type';
	$fields['resa']='Résa';
	$fields['lier']='Lié à ';
	$fields['action']='';
	$data .= tableau('SQL/sql_data_search.php?type=ressources&prof='.$type,$fields,$show,350,false);
	$data .= '';
	$data .= '</div>';
	$titre="Ressource de séance  <a href='?p=".$_GET['p']."&add=ressource'><i class=\"far fa-plus-square\" ></i></a><br><small>les ressources en rouge sont gérées par le profil super admin <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Les ressources créés par un super admin sont communes à tous les calendriers\"></i></small>";
	html_cadre($titre,$data);
}

/*
 * tableau de la liste des réservations
 * 
 */

function tableau_list_resa($id_ressource){

	$data ='';
	$show['date_debut']=1;
	$show['demandeur']=1;
	$show['date_fin']=1;
	$show['ressource']=1;
	$show['action']=1;
	$show['motif']=1;
	$show['nb_pers']=1;
	$fields['action']='';
	$fields['demandeur']='Demandeur';
	$fields['ressource']='Nom';
	$fields['date_debut']='Début';
	$fields['date_fin']='Fin';
	$fields['nb_pers']='Nb personnes';
	$fields['motif']='Motif';
	$data .= tableau('SQL/sql_data_search.php?type=list_ddes&ressource='.$id_ressource,$fields,$show,550,false);
	$footer_popup ="<input type='submit' id='button_list_resa' name='button_list_resa' value='Ok' class='btn btn-success'>";
	echo '<div id="myModal_resa" class="modal">
      <div class="modal-dialog modal-xl">
               <div class="modal-content">
                   <div class="modal-header  alert-warning">
                       <center><b>Liste des demandes</b></center>
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

/*
 * Tableau montrant les séances
 * 
 * 
 */

function tableau_mes_seances(){

	$data ='';
	if (count($_SESSION[NAME]['cal']) > 1)
		$show['id']=1;
		$show['nom_cal']=1;
		$show['nom_seance']=1;
		$show['comment']=1;
		$show['date_debut']=1;
		$show['date_fin']=1;
		$show['action']=1;
		$fields['action']='';
		$fields['id']='N°';
		$fields['nom_cal']='Cal';
		$fields['nom_seance']='Type de séance';
		$fields['comment']='Comment';
		$fields['date_debut']='Début';
		$fields['date_fin']='Fin';
		$data .= tableau('SQL/sql_data_search.php?type=seances',$fields,$show,350,false);
		$titre="Mes séances  <a href='?p=10&add=seance'><i class=\"far fa-plus-square\" ></i></a><br><small>Les séances sont liées à votre/vos calendrier(s)</small>";
		html_cadre($titre,$data);
}

/*
 * 
 * tableau affichant les logs de l'application
 * 
 */
function tableau_log(){
	$data ='<div class="container">';
	$show['date_action']=1;
	$show['nigend']=1;
	$show['comment']=1;
	$fields['date_action']='Date';
	$fields['nigend']='ID Utilisateur';
	$fields['comment']='Comment';
	$data .= tableau('SQL/sql_data_search.php?type=log',$fields,$show,350,false);
	$data .= '</div>';
	$titre="Logs calendriers<br><small>Actions effectuées sur les calendriers</small>";
	html_cadre($titre,$data);
}

/*
 * Tableau des services - gestion locale des comptes
 * 
 */
function tableau_services(){
	$data ='<div class="container">';
	$show['codeunite']=1;
	$show['unite']=1;
	$show['action']=1;
	$fields['codeunite']='Code service';
	$fields['unite']='Libellé service';
	$fields['action']='';
	$data .= tableau('SQL/sql_data_search.php?type=services',$fields,$show,350,false);
	$data .= '</div>';
	$titre="Services utilisant l'application<a href='?p=11&add=service'><i class=\"far fa-plus-square\" ></i></a><br><small>Gestion locale  <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Un service ne peut être supprimé si des utilisateurs sont dedans\"></i></small>";
	html_cadre($titre,$data);	
}
/*
 * Tableau des utilisateurs - gestion locale des comptes
 * 
 */
function tableau_users(){
	$data ='<div class="container">';
	$show['nigend']=1;
	$show['displayname']=1;
	$show['codeUnite']=1;
	$show['unite']=1;
	$show['action']=1;
	
	$fields['nigend']='ID Utilisateur';
	$fields['displayname']='Nom';
	$fields['codeUnite']='Code service';
	$fields['unite']='Libellé service';
	$fields['action']='';
	$data .= tableau('SQL/sql_data_search.php?type=users',$fields,$show,350,false);
	$data .= '</div>';
	$titre="Utilisateurs de l'application<a href='?p=11&add=user'><i class=\"far fa-plus-square\" ></i></a><br><small>Gestion locale  <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"La gestion des utilisateurs/services ne sont activés que si l'application est gérée en local (Configuration Générale)\"></i></small>";
	html_cadre($titre,$data);
}

/*
 * 
 * liste des inscriptions à partir d'un type de séance
 * 
 */

function tableau_pers_by_type(){
	global $bdd;
	$sql="select id_cal,nom from type_seance where id=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	$data ='<div class="container">';
	$show['nigend']=1;
	$show['info']=1;
	$show['nb_inscription']=1;
	$fields['nigend']='ID utilisateur';
	$fields['info']='Info';
	$fields['nb_inscription']='Nb inscriptions';
	$data .= tableau('SQL/sql_data_search.php?type=nb_inscription&nb='.$_POST['id_form'],$fields,$show,350,false);
	$data .= '</div>';
	$titre="Liste des inscriptions sur le type séance ".$donnees['nom'];
	html_cadre($titre,$data);

}

/*
 *
 * Fonction pour affichier un calendrier
 * et les évenements liés
 *$typeaff peut prendre les valeurs dayGridMonth,timeGridWeek,timeGridDay,listYear
 */

function aff_calendrier($event=array(),$typeaff='dayGridMonth'){
	$date_aujourdhui=date("Y-m-d");
	if (!isset($event)){
		$event=array();
	}
	//print_r($event);
	//die();
	echo "
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var initialLocaleCode = 'fr';
			var localeSelectorEl = document.getElementById('locale-selector');
			var calendarEl = document.getElementById('calendar');
		
			var calendar = new FullCalendar.Calendar(calendarEl, {
				headerToolbar: {
					left: 'prev,next today',
					center: 'title',
					right: 'dayGridMonth,timeGridWeek,timeGridDay,listYear'
				},
				 eventDidMount: function (info) {
      				$(info.el).tooltip({
        			title: info.event.extendedProps.description,
        			placement: 'top',
        			trigger: 'hover',
        			container: 'body' });
			    },
			    contentHeight: 780,
				schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
				initialView: '".$typeaff."',
				initialDate: '".$date_aujourdhui."',
				locale: initialLocaleCode,
				buttonIcons: false, // show the prev/next text
				weekNumbers: true,
				navLinks: true, // can click day/week names to navigate views
				editable: false,
				dayMaxEvents: ".((!defined('DAYMAXEVENTS') || !DAYMAXEVENTS)?'false':'true').", // allow more link when too many events
						";
	echo "events: [";
	foreach ($event as $k=>$v){
		echo "{";
			foreach ($v as $k1=>$v1){
				if ($v1 != '')
					echo $k1." : '".addslashes($v1)."',";
	
			}

		
		echo "},";
	}
	echo "]";
	echo "});
			calendar.render();";
	if (isset($_GET['date_seance'])){
		$date_goto=explode('-',$_GET['date_seance']);
		if (count($date_goto) == 3){
			if (checkdate($date_goto[1],$date_goto[2],$date_goto[0]))
				echo "calendar.gotoDate('".$_GET['date_seance']."');";
		}
	}
	echo "});
		</script>";

}
/*
 * 
 * fonction qui permet d'afficher un calendrier en format timeline
 * ATTENTION : Cette version nécessite une version non open-source 
 * de la librairie fullcalendar! Par défaut, cette version n'est pas 
 * présente dans ce code. Cette fonction ne peut donc pas être utilisée
 * 
 * 
 */

function aff_calendrier_timeline($event=array(),$ressource_cal=array()){
	$date_aujourdhui=date("Y-m-d");
	if (!isset($event)){
		$event=array();
	}
	echo "
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var initialLocaleCode = 'fr';
			var localeSelectorEl = document.getElementById('locale-selector');
			var calendarEl = document.getElementById('calendar');

			var calendar = new FullCalendar.Calendar(calendarEl, {
					headerToolbar: {
					left: 'prev,next today',
					center: 'title',
					right: 'resourceTimelineDay,resourceTimelineWeek,resourceTimelineMonth,dayGridMonth'
				},
				eventDidMount: function (info) {
      				$(info.el).tooltip({
        			title: info.event.extendedProps.description,
        			placement: 'top',
        			trigger: 'hover',
        			container: 'body' });
			    },
				schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
				initialView: 'resourceTimelineDay',
				initialDate: '".$date_aujourdhui."',
				locale: initialLocaleCode,
				buttonIcons: false, // show the prev/next text
				weekNumbers: true,
				navLinks: true, // can click day/week names to navigate views
				editable: false,
				dayMaxEvents: ".((!defined('DAYMAXEVENTS') || !DAYMAXEVENTS)?'false':'true').", // allow more link when too many events
						";
	echo "resources: [";
	foreach ($ressource_cal as $k=>$v){
		echo "{";
		foreach ($v as $k1=>$v1){
			if ($v1 != '')
				echo $k1." : '".addslashes($v1)."',";
	
		}	
		echo "},";
	}
	echo "], ";
	echo "events: [";
	foreach ($event as $k=>$v){
		echo "{";
		foreach ($v as $k1=>$v1){
			if ($v1 != '')
				echo $k1." : '".addslashes($v1)."',";

		}
		echo "},";
	}
	echo "]";
	echo "});
			calendar.render();";
	if (isset($_GET['date_seance'])){
		$date_goto=explode('-',$_GET['date_seance']);
		if (count($date_goto) == 3){
			if (checkdate($date_goto[1],$date_goto[2],$date_goto[0]))
				echo "calendar.gotoDate('".$_GET['date_seance']."');";
		}
	}
	echo "});
		</script>";

}





/*
 *
 * Html du formulaire de réservation de ressources
 *
 */
function html_dde_resa($add_modif){
	global $bdd,$error;
	//si la variable de "qui peut supprimer/modifier une demande" n'a pas été définie dans le fichier de conf
	//on la force à "UNITE" (Tous les personnels du service du demandeur peuvent supprimer une demande)
	if (!defined('UP_DEL_DDE'))
		$up_del_dde='UNITE';
	else 
		$up_del_dde=UP_DEL_DDE;
	//on est sur une demande de visualisation
	if ($add_modif == "VISU"){		
		$liste_ressources=ressources_reservables($_POST['ressource']);
		$titre="";
		$lbl_bouton=array();
		$color_bouton=array();
		$javascript=array();
		$sql=" select * from dde_resa where id=:id";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
		$reponse->execute();
		$donnees=$reponse->fetch();
		$_POST['ressource']=$donnees['id_ressource'];
		$_POST['date_debut']=$donnees['date_debut'];
		$_POST['heure_debut']=$donnees['heure_debut'];
		$_POST['date_fin']=$donnees['date_fin'];
		$_POST['heure_fin']=$donnees['heure_fin'];
		$_POST['motif_dde']=$donnees['motif_dde'];		
		$_POST['nb_pers']=$donnees['nb_pers'];	
		$info_ressource=info_ressource($_POST['ressource']);

		/*
		 * Si la variable de conf est au service, on regarde si la personne connectée appartient à ce service
		 * Si la variable de conf est à la personne, on regarde juste si la personne connectée est celle qui a fait la demande
		 * elle pourra modifier (si demande pas encore traitée) et supprimer la demande	 
		 * 
		 */
		
		if (($up_del_dde == "UNITE" && $donnees['unite_demandeur'] == $_SESSION[NAME]['codeUnite']) || ($up_del_dde == "DEMANDEUR" && $donnees['nigend_demandeur'] == $_SESSION[NAME]['nigend'])){
			if ($donnees['status'] == 1 || !isset($info_ressource['email'])){
					$titre="Modifier la réservation";
					$lbl_bouton[]="Modifier";
					$color_bouton[]="success";
					$javascript[]='document.getElementById(\'modif\').value=\'demande\';document.getElementById(\'id_form\').value=\''.$_POST['id_form'].'\';form.submit();';
					$readonly= false;	
			}
			$lbl_bouton[]="Supprimer";
			$color_bouton[]="warning";
			$javascript[]='if(confirm(\'Etes-vous sûr de vouloir supprimer cette demande?\')){document.getElementById(\'id_form\').value = \''.$_POST['id_form'].'\'; document.getElementById(\'del\').value = \'DDE\'; document.formulaire.submit();}';
		}
		/**Admin de la ressource**/
		if (isset($_SESSION[NAME]['admin_ressource']) && in_array($donnees['id_ressource'],$_SESSION[NAME]['admin_ressource']) && isset($info_ressource['email'])){			
	
			if ($donnees['status'] == 1){
				$titre= "Valider la demande";
				$lbl_bouton[]="Valider";
				$color_bouton[]='success';
				$javascript[]='document.getElementById(\'valid\').value=\'valider\';document.getElementById(\'id_form\').value=\''.$_POST['id_form'].'\';form.submit();';
				$lbl_bouton[]="Refuser";
				$color_bouton[]='dark';
				$javascript[]='document.getElementById(\'refuser\').value=\'refuser\';document.getElementById(\'id_form\').value=\''.$_POST['id_form'].'\';form.submit();';
			}else{
				$titre= "Visualisation de la demande";
				$lbl_bouton[]="Refuser";
				$color_bouton[]='dark';
				$javascript[]='document.getElementById(\'refuser\').value=\'refuser\';document.getElementById(\'id_form\').value=\''.$_POST['id_form'].'\';form.submit();';				
			}						
		}		
		/****VISU SIMPLE*****/		
		if (!isset($readonly)){
			$readonly=true;
			$_POST['date_debut'] = date_to_php($_POST['date_debut']);
			$_POST['date_fin'] = date_to_php($_POST['date_fin']);
		}
		$titre.="<br>faite par ".$donnees['lbl_unite'];
	}elseif ($add_modif == "ADD"){
		$liste_ressources=ressources_reservables($_GET['option']);
		if (!isset($liste_ressources['EMAIL'][$_GET['option']])){
			$titre="Faire une réservation";
			$lbl_bouton[]="Faire la réservation";
		}
		$readonly= false;
		
		$color_bouton[]='success';
		$javascript[]='document.getElementById(\'add\').value=\'demande\';form.submit();';
		
	}
	$data ='<div class="container">';
	if ($liste_ressources == array()){
		$liste_ressources=ressources_reservables();
		$_POST['ressource'].=' - '.(isset($liste_ressources['RESSOURCES'][$_POST['ressource']])?$liste_ressources['RESSOURCES'][$_POST['ressource']]:'');
		$data .= html_champ("Ressource :", "ressource",'text','','',true);
	}else{
		if ($readonly){
			$_POST['ressource'].=' - '.(isset($liste_ressources['RESSOURCES'][$_POST['ressource']])?$liste_ressources['RESSOURCES'][$_POST['ressource']]:'');
			$data .= html_champ("Ressource :", "ressource",'text','','',$readonly);
		}else
			$data .= html_champ("Ressource :", "ressource",'select_1',array('Ressources'=>$liste_ressources['RESSOURCES']),'',$readonly);
	}

		$data .= "<div class=\"form-group\">
                   <label>Date de début</label>";
		if ($readonly)
			$data .= "<input type=\"text\" class=\"form-control\" id=\"date_debut\" name=\"date_debut\" value=\"".(isset($_POST["date_debut"])?$_POST["date_debut"]:'')."\" disabled>";
		else
			$data .= "<input type=\"date\" class=\"form-control\" id=\"date_debut\" name=\"date_debut\" value=\"".(isset($_POST["date_debut"])?$_POST["date_debut"]:'')."\" onChange=\"getElementById('date_fin').value = getElementById('date_debut').value;\">";
		$data .= "</div>";
		$data .= html_champ("heure de début", "heure_debut",'time','','',$readonly);
		$data .= html_champ("Date de fin", "date_fin",'date','','',$readonly);
		$data .= html_champ("heure de fin", "heure_fin",'time','','',$readonly);
		$data .= html_champ("Nb personnes", "nb_pers",'number','','',$readonly);
		$data .= html_champ("Motif de la demande", "motif_dde",'textarea','','',$readonly);
		//si le profil est de visualisation, on n'affiche JAMAIS aucun bouton dans le formulaire
		$footer_popup="";
		if ($_SESSION[NAME]['profil'] != 'visualisation'){
			foreach ($lbl_bouton as $k=>$v){
				$footer_popup .= '&nbsp;<input type="button" id ="resa" name="resa" value="'.$v.'" class="btn btn-'.$color_bouton[$k].'" onclick="'.$javascript[$k].'">';			
			}
		}
		$footer_popup .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
	$data .= '</div>';
	
	echo '<div id="myModal2" class="modal">
      <div class="modal-dialog">
               <div class="modal-content">
                   <div class="modal-header  alert-warning">
                       <center><b>'.$titre.'</b></center>
                   </div>
                   <div class="modal-body alert-secondary">';
	if ($error != array()){
		msg("Les champs suivants ne sont pas complétés:<br>".implode(";",$error),1);
	}
             echo $data.'
                      </div>
                   <div class="modal-footer alert-secondary">
                       		'.$footer_popup.'
                      </div>
               </div>
      </div>
</div>';
    echo '<script>	$(document).ready(function(){
		$("#myModal2").modal(\'show\');
	});</script>';
	
	return $data;
}






/*
 *
 * Html du formulaire du type séance (ADMIN SADMIN)
 *
 */
function html_type_seance($add_modif){
	global $bdd;
	if ($add_modif == "MODIF"){
		$titre="Modifier un type séance";
		$lbl_bouton="Modifier";
		$javascript='document.getElementById(\'modif\').value=\'type_seance\';document.getElementById(\'id_form\').value=\''.$_POST['id_form'].'\';form.submit();';
		$sql=" select * from type_seance where id=:id and id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal_visible'])).")";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
		$reponse->execute();
		$i=0;
		$donnees=$reponse->fetch();
		$_POST['nom_type']=$donnees['nom'];
		$_POST['couleur_type']=$donnees['couleur'];
		$_POST['cal']=$donnees['id_cal'];
	}elseif ($add_modif == "ADD"){
		$titre="Ajouter un type séance";
		$lbl_bouton="Ajouter";
		$javascript='document.getElementById(\'add\').value=\'type_seance\';form.submit();';
	}	
	$data ='<div class="container">';
	$data .= html_champ("Nom :", "nom_type",'input','','');
	if (count($_SESSION[NAME]['cal']) > 1)
		$data .= html_champ("Calendrier :", "cal",'select_1',array('Calendrier'=>$_SESSION[NAME]['cal_visible']));
	else
		$data .= '<input type="hidden" id ="cal" name="cal" value="'.array_key_first($_SESSION[NAME]['cal_visible']).'">';
	$data .= html_champ("Couleur à l'affichage:", "couleur_type",'color','');
	$data .= '<br><input type="button" id ="ajouter_type_seance" name="ajouter_type_seance" value="'.$lbl_bouton.'" class="btn btn-success" onclick="'.$javascript.'">';
	$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
	$data .= '</div>';
	return $data;
}

/*
 *
 * Html du formulaire ADMIN des séances
 * 
 */
function html_seance($add_modif){
	global $bdd;
	if ($add_modif == "ADD"){
		$lbl_bouton='Ajouter';
		$javascript="document.getElementById('add').value='seance';form.submit();";
	}elseif($add_modif == "MODIF" || $add_modif == "DUPLI"){
		$sql=" select * from seance where id=:id and id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
		$reponse->execute();
		$i=0;
		$donnees=$reponse->fetch();
		if (!isset($_POST['reload_ressource']))
			$_POST['reload_ressource']=$donnees['id_ressource'];
		if (!isset($_POST['type_seance']))
			$_POST['type_seance']=$donnees['id_type'];
		if (!isset($_POST['reload_cal']))
			$_POST['reload_cal']=$donnees['id_cal'];
		if (!isset($_POST['comment']))
			$_POST['comment']=$donnees['comment'];
		if (!isset($_POST['nb_pers']))
			$_POST['nb_pers']=$donnees['nb_pers'];
		if (!isset($_POST['date_debut']))
			$_POST['date_debut']=$donnees['date_debut'];
		if (!isset($_POST['date_fin']))
			$_POST['date_fin']=$donnees['date_fin'];
		if ($donnees['heure_debut'] != "00:00:00")
			$_POST['heure_debut']=$donnees['heure_debut'];
		if ($donnees['heure_fin'] != "00:00:00")
			$_POST['heure_fin']=$donnees['heure_fin'];
		if (!isset($_POST['date_fin_inscription']))
			$_POST['date_fin_inscription']=$donnees['date_fin_inscription'];
		
		if ($add_modif == "DUPLI"){
			$lbl_bouton="Ajouter";
			$javascript="document.getElementById('add').value='seance';form.submit();";
		}else{
			$lbl_bouton='Modifier';
			$javascript="document.getElementById('modif').value='seance';form.submit();";			
		}
	}
	$data ='<div class="container">';
	if (count($_SESSION[NAME]['cal_visible']) > 1)
		$data .= html_champ("Calendrier :", "reload_cal",'select_1',array('Calendrier'=>$_SESSION[NAME]['cal_visible']));
		else{
			$_POST['reload_cal']=array_key_first($_SESSION[NAME]['cal_visible']);
			$data .= '<input type="hidden" id ="reload_cal" name="reload_cal" value="'.array_key_first($_SESSION[NAME]['cal_visible']).'">';
		}
		if (isset($_POST['reload_cal'])){
			$sql=" select * from type_seance where id_cal = :id_cal";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id_cal',$_POST['reload_cal'],PDO::PARAM_STR);
			$reponse->execute();
			$i=0;
			while ($donnees=$reponse->fetch()){
				$type_seance[$donnees['id']]=$donnees['nom'];
			}
		}
		//on récupère toutes les ressources sauf celles qui servent à "lier" les autres ressources
		$sql="select r2.* from ressources r1 right join ressources r2 on r1.lier=r2.id where r1.id is null ";
		if (isset($_SESSION[NAME]['cal_visible']) && is_array($_SESSION[NAME]['cal_visible'])){
			$sql .= "and (r2.cal is null or r2.cal in (".implode(",",array_flip($_SESSION[NAME]['cal_visible']))."))";
		}
		$reponse=$bdd->prepare($sql);
		$reponse->execute();
		$i=0;
		while ($donnees=$reponse->fetch()){			
			if ($donnees['cal'] == NULL)
				$comment_ressource='(gestion générale)';
			else
				$comment_ressource='(gestion locale)';
			$ressource[$donnees['id']]=$donnees['nom'].$comment_ressource;
		}
		//aucune ressource disponible dans l'application
		if (!isset($ressource)){
			$data .= '<div class="alert alert-danger">Aucune ressource n\'existe pour ce calendrier!<br>Veuillez commencer par en créer une.</div>';
			$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger"></div>';			
			return $data;
		}
		//aucun type séance pour ce calendrier
		if (isset($type_seance)){
			$data .= html_champ("Type de la séance :", "type_seance",'select_1',array('Type de séance'=>$type_seance));
		}
		 
		if (isset($_POST['reload_cal']) && !isset($type_seance)){
			$data .= '<div class="alert alert-danger">Aucun type séance n\'existe pour ce calendrier!<br>Veuillez commencer par en créer un.</div>';
			$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger"></div>';
			return $data;
		}else {
			$data .= html_champ("Ressource de la séance :", "reload_ressource",'select_1',array('Ressource de la séance'=>$ressource));
			$data .= html_champ("Commentaires :", "comment",'textarea','','');
			$data .= html_champ("Nombre de personnes :", "nb_pers",'number','','');
			$data .= "<div class=\"form-group\">
                   <label>Date de début</label>";
			$data .= "<input type=\"date\" class=\"form-control\" id=\"date_debut\" name=\"date_debut\" value=\"".(isset($_POST["date_debut"])?$_POST["date_debut"]:'')."\" onChange=\"getElementById('date_fin').value = getElementById('date_debut').value;\">";
			$data .= "</div>";
			$data .= html_champ("heure de début", "heure_debut",'time','','');
			$data .= html_champ("Date de fin", "date_fin",'date','','');
			$data .= html_champ("heure de fin", "heure_fin",'time','','');
			$data .= html_champ("Date de fin d'inscription/désinscription (<font color=green>Facultatif</font>)
							<br><small>Les utilisateurs ne pourront plus s'inscrire après cette date. Seul l'administrateur du calendrier pourra appliquer les modifications</small>",
					"date_fin_inscription",'date','','');
			$data .= '<br><input type="button" id ="ajouter_seance" name="ajouter_seance" value="'.$lbl_bouton.'" class="btn btn-success" onclick="'.$javascript.'">';
			$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
		}
	
		$data .= '</div>';
	return $data;
}
/*
 *
 * Html du formulaire SADMIN des users
 *
 */
function html_user($add_modif){	
	global $bdd;
	$sql="select codeUnite,unite from services";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	while ($donnees=$reponse->fetch()){
		$list_services[$donnees['codeUnite']]=$donnees['codeUnite']." - ".$donnees['unite'];
	}
	if (!isset($list_services)){
		msg("Avant de créer des utilisateurs, vous devez créer des services pour y inclure vos utilisateurs,1");
		return false;		
	}
	$data ='<div class="container">';
	if ($add_modif == "ADD"){
		$lbl_button='Ajouter';
		$javascript="document.getElementById('add').value='user';form.submit();";
		$password_field="txt";
		$nigend_field='number';
		$login_field='input';
	}elseif($add_modif == "MODIF"){
		$lbl_button='Modifier';
		$javascript="document.getElementById('modif').value='user';form.submit();";
		if (!isset($_POST['nigend'])){
			$sql=" select nigend,uid,codeUnite,mail,login from users where nigend = :id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			foreach($donnees as $k=>$v){
				$_POST[$k]=$v;			
			}
		}
		global $placeholder;
		$placeholder=array("password" => "Entrer un nouveau mot de passe si vous souhaiter le changer");
		$password_field="password";
		$nigend_field='text_disable';
		$login_field='text_disable';
		
	}
	$data .= html_champ("Login de connexion:", "login",$login_field,'','');
	$data .= html_champ("Password de connexion:", "password",$password_field,'','');
	$data .= html_champ("ID Utilisateur: <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"L'ID NUMERIQUE utilisateur permet d'identifier une personne au sein de votre organisme\"></i>", "nigend",$nigend_field,'','');
	$data .= html_champ("Nom Utilisateur:", "uid",'input','','');
	$data .= html_champ("Service:  <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"Le code service NUMERIQUE va permettre d'attribuer des droits à toutes les personnes ayant ce même code\"></i>", "codeUnite",'select',array('Services'=> $list_services),'');
	$data .= html_champ("Mail:", "mail",'input','','');
	$_POST['profil']='UTILISATEUR';
	$data .= html_champ("Profil: <i class=\"fa-solid fa-circle-info\" data-bs-toggle=\"tooltip\" title=\"A la création d'un utilisateur, son profil est automatiquement défini comme utilisateur de l'application.
				Le profil Super-Admin est défini dans la conf générale de l'application
				Le profil Admin d'un calendrier est défini lors de la création du calendrier\"></i>", "profil",'text_disable','','','');
	$data .= '<br><input type="button" id ="ajouter_user" name="ajouter_user" value="'.$lbl_button.'" class="btn btn-success" onclick="'.$javascript.'">';
	$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
	$data .= '</div>';
	$data .= "<script>
				// Initialize tooltips
				var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=\"tooltip\"]'))
				var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				  return new bootstrap.Tooltip(tooltipTriggerEl)
				})
				</script>";
	return $data;	
}

/*
 *
 * Html du formulaire SADMIN des services
 *
 */
function html_service($add_modif){
	global $bdd;
	$data ='<div class="container">';
	if ($add_modif == "ADD"){
		$lbl_button='Ajouter';
		$javascript="document.getElementById('add').value='service';form.submit();";
		$data .= html_champ("Code Service :", "codeUnite",'number','','');
	}elseif($add_modif == "MODIF"){
		$lbl_button='Modifier';
		$javascript="document.getElementById('modif').value='service';form.submit();";
		if (!isset($_POST['codeUnite'])){
			$sql=" select unite,codeUnite,mail from services where codeUnite = :codeUnite";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':codeUnite',$_POST['id_form'],PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			foreach($donnees as $k=>$v){
				$_POST[$k]=$v;
			}
			
		}
		$data .= html_champ("Code Service :", "codeUnite",'text_disable','','');
	}
	
	$data .= html_champ("Libellé du service :", "unite",'text','','');
	$data .= html_champ("Mail (facultatif):", "mail",'input','','');
	$data .= '<br><input type="button" id ="ajouter_service" name="ajouter_service" value="'.$lbl_button.'" class="btn btn-success" onclick="'.$javascript.'">';
	$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
	$data .= '</div>';
	return $data;
}


/*
 * fonction pour récupérer les infos des services
 * gestion locale
 */
function services_infos($id=array()){
	global $bdd;
	$sql="select codeUnite,unite from services ";
	if ($id != array()){		
		$sql.= "where codeUnite in (".implode(',',$id).")";
	}
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	while ($donnees=$reponse->fetch()){
		$data[$donnees['codeUnite']]=$donnees['codeUnite'].' - '.$donnees['unite'];		
	}
	return $data;
	
}

/*
 * fonction pour récupérer les infos d'un user
 * Gestion locale
 * 
 */

function services_personnes($id=array()){
	global $bdd;
	$sql="select * from users ";
	if ($id != array()){
		$sql.= "where nigend in (".implode(',',$id).")";
	}
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	while ($donnees=$reponse->fetch()){
		$data[$donnees['nigend']]=$donnees['nigend'].' - '.$donnees['uid'];
	}
	return $data;
	
	
}

/*
 *
 * Html du formulaire SADMIN des calendriers
 *
 */
function html_calendrier($add_modif){
	global $bdd;
	if ($add_modif == "ADD"){		
		$lbl_button='Ajouter';
		$javascript="document.getElementById('add').value='cal';form.submit();";
	}elseif($add_modif == "MODIF"){
		$lbl_button='Modifier';
		$javascript="document.getElementById('modif').value='cal';form.submit();";
		$sql=" select * from calendrier where id = :id";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
		$reponse->execute();
		$donnees=$reponse->fetch();
		//variable qui vont permettre de savoir comment est administré le calendrier
		//soit par id utilisateur, soit par service soit les deux.
		$admin_unite=false;
		$admin_nigend=false;
		//si on a de la donnée dans le champ unité, on va récupérer les données
		if (isset($donnees['unite']) && $donnees['unite'] != NULL){
			if (!isset($_POST['reload_admin']))
				$_POST['reload_admin']= "unites";
			if (!isset($_POST['unites'])){
				//si on est sur auth locale, nous avons des listes déroulantes. Il faut donc 
				//modifier les données pour les prendre en compte dans le champ
				if (AUTH == "LOCAL"){
					$_POST['unites']=explode(';',$donnees['unite']);					
				}else				
					$_POST['unites']=$donnees['unite'];
				$admin_unite=true;
			}
		}
		//si on a de la donnée dans le champ id_utilisateur, on va récupérer les données
		if (isset($donnees['nigend']) && $donnees['nigend'] != NULL){
			if (!isset($_POST['reload_admin']))
				$_POST['reload_admin']= "nigends";
			if (!isset($_POST['nigends'])){
				if (AUTH == "LOCAL"){
					$_POST['nigends']=explode(';',$donnees['nigend']);
				}else
					$_POST['nigends']=$donnees['nigend'];
				$admin_nigend=true;
			}
		}
		//si on des données dans les deux champs, on les affiches
		if ($admin_nigend && $admin_unite)
			$_POST['reload_admin']= "unit_nigend";
		

		if (!isset($_POST['nom_calendrier']))
			$_POST['nom_calendrier']=$donnees['nom'];
		if (!isset($_POST['comment']))
		    $_POST['comment']=$donnees['comment'];
		if (!isset($_POST['visible']))
			$_POST['visible']=$donnees['visible'];
	}
	$data ='<div class="container">';
	$data .= html_champ("Nom du calendrier:", "nom_calendrier",'input','','');
	$data .= html_champ("Calendrier visible ? (Partie inscription)", "visible",'select_1',array('Visibilité'=>array(1=>'OUI',0=>'NON')));
	$data .= html_champ("Commentaires :", "comment",'textarea','','');
	$data .= html_champ("Administré par :", "reload_admin",'select_1',array('Administration'=>array(''=>'','unites'=>'CODE SERVICE','nigends'=>'ID UTILISATEUR','unit_nigend'=>'CODE SERVICE + ID UTILISATEUR')));
	if (isset($_POST['reload_admin']) && in_array($_POST['reload_admin'],array('unites','nigends','unit_nigend'))){
		if ($_POST['reload_admin'] == "unites" || $_POST['reload_admin'] == "unit_nigend"){
			if (AUTH == "LOCAL"){
				$list_services=services_infos();
				$nb_value=count($list_services);
				$data .= html_champ("Service(s) administrateur(s) :", "unites",'select_'.$nb_value,array('Services'=>$list_services));	
			}else{
				$data .= html_champ("Service(s) administrateur(s) (séparer les Codes Services par des ;) :", "unites",'input','','');
			}
		}
		if ($_POST['reload_admin'] == "nigends" || $_POST['reload_admin'] == "unit_nigend"){
			if (AUTH == "LOCAL"){
				$list_personnes=services_personnes();
				$nb_value=count($list_personnes);
				$data .= html_champ("Utilisateur(s) administratreur(s) :", "nigends",'select_'.$nb_value,array('Utilisateur'=>$list_personnes));
			}else{
				$data .= html_champ("Utilisateur(s) administratreur(s) (séparer les Codes Utilisateur par des ;) :", "nigends",'input','','');
			}
		}
		
		$data .= '<br><input type="button" id ="ajouter_calendrier" name="ajouter_calendrier" value="'.$lbl_button.'" class="btn btn-success" onclick="'.$javascript.'">';
	}
	$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
	$data .= '</div>';
	return $data;
}

/*
 * 
 * Html du formulaire de ressources
 * 
 */
function html_ressource($add_modif){
	global $bdd;
	//ajout d'une nouvelle ressource
	if ($add_modif == "ADD"){
		$lbl_button='Ajouter';
		$javascript="document.getElementById('add').value='ressource';form.submit();";
	//modification d'une ressource existante
	}elseif($add_modif == "MODIF"){
		$lbl_button='Modifier';
		$javascript="document.getElementById('modif').value='ressource';form.submit();";	
		//recherche des infos sur cette ressource
		$donnees=info_ressource($_POST['id_form']);
		//si le champs couleur est non vide, c'est que la ressource est réservable
		if (!isset($_POST['reload_reservable_ressource'])){
			if (isset($donnees['couleur']) && $donnees['couleur']!='')
				$_POST['reload_reservable_ressource']=1;
			else 
				$_POST['reload_reservable_ressource']=0;
		}		
		//si le champs email est non vide, c'est que la ressource demande une validation hierarchique
		if (!isset($_POST['reload_valide_hierarchique'])){
			if (isset($donnees['email']) && $donnees['email']!=NULL)
				$_POST['reload_valide_hierarchique']=1;
			else
				$_POST['reload_valide_hierarchique']=0;			
		}
		
		if (!isset($_POST['nom_ressource']))
			$_POST['nom_ressource']=$donnees['nom'];
		if (!isset($_POST['comment']))
			$_POST['comment']=$donnees['comment'];
		if (!isset($_POST['type_ressource']))
			$_POST['type_ressource']=$donnees['type'];
		if (!isset($_POST['couleur_ressource']))
			$_POST['couleur_ressource']=$donnees['couleur'];
		if (!isset($_POST['cu_admin_ressource']))
			$_POST['cu_admin_ressource']=$donnees['cu_admin'];
		if (!isset($_POST['email']))
			$_POST['email']=$donnees['email'];
		if (!isset($_POST['cal']))
			$_POST['cal']=$donnees['cal'];	
		if (!isset($_POST['purge']))
			$_POST['purge']=$donnees['purge_cycle'];
		if (!isset($_POST['lier']))
			$_POST['lier']=$donnees['lier'];
	}
	
	if (!isset($_POST['lier']))
		$_POST['lier']=0;	
	$data ='<div class="container">';
	$data .= html_champ("Nom :", "nom_ressource",'input','','');
	if ($_GET['p'] == 10){
		if (count($_SESSION[NAME]['cal']) > 1)
			$data .= html_champ("Calendrier :",'cal','select_1',array('Calendrier'=>$_SESSION[NAME]['cal']));
		else 
			$data .= '<input type="hidden" id="cal" name="cal" value="'.array_key_first($_SESSION[NAME]['cal']).'">';		
	}
		
	$data .= html_champ("Commentaires :", "comment",'textarea','','');
	$data .= html_champ("Type de ressource:", "type_ressource",'select_1',array('Type'=>array('unique'=>'Réservation unique (la date/heure de la réservation de cette ressource est unique)','multiple'=>'Réservations Multiples (Cette ressource peut être utilisé en même temps)')));
	//si l'option de réservation de salle est mise en place
	if (RESA_SALLES){
		if (!isset($_POST['reload_reservable_ressource']))
			$_POST['reload_reservable_ressource']=0;
		$data .= html_champ("Cette ressource est réservable par tous les personnels:", "reload_reservable_ressource",'select_1',array('Réservation'=>array(1=>'OUI',0=>'NON')));
		if ($_POST['reload_reservable_ressource'] == 1){		
				$data .= "<div class=\"alert alert-success\">Les champs ci-dessous permettent l'administration des réservations (option activée dans l'application)</div>";	
				if (!isset($_POST['purge']))
					$_POST['purge'] = 0;
				//s'il existe des ressources déjà ouvertes à la réservation
				//on propose de lier cette ressource à une autre
				if (isset($_SESSION[NAME]['sous_menu'][2]))
					$lier_ressource=$_SESSION[NAME]['sous_menu'][2];
				$lier_ressource[0]='NE PAS LIER';
				$data .= html_champ("Lier cette ressource :<br><small><i>(La ressource sera liée au calendrier de réservation de la ressource)</i></small>", "lier",
							'select_1',array('Lier'=>$lier_ressource));
				$data .= html_champ("Purge des demandes :<br><small><i>(les demandes passées sont effacées pour gagner en visibilité/rapidité d'affichage)</i></small>", "purge",
									'select_1',array('Purge'=>array(0=>'JAMAIS',1=>'JOUR - 1',7=>'JOUR - 1 SEMAINE',31=>'JOUR - 1 MOIS')));
				$data .= html_champ("Couleur à l'affichage :", "couleur_ressource",'color','');
				if (!isset($_POST['reload_valide_hierarchique']))
					$_POST['reload_valide_hierarchique']=0;
				$data .= html_champ("Cette ressource demande une validation par le gestionnaire :<br> <small>(en mettant NON, toutes les demandes seront validées d'office)</small>", "reload_valide_hierarchique",'select_1',array('Demande validation'=>array(1=>'OUI',0=>'NON')));

				if (isset($_POST['reload_valide_hierarchique']) && $_POST['reload_valide_hierarchique'] == 1){
					$data .= html_champ("Email recevant les notifications :<br> <small>(boîte personnel ou boîte service)<br>Lors d'une demande de réservation, un mail sera envoyé à cette boite mail</small>", "email",'email','');
					if ($_GET['p'] == 11){
						$data .= html_champ("unité(s) administratrice(s) des réservations (séparer les CU par des ;) :
										<br><small>Ces unités recevront un mail lors d'une demande de réservation extérieure et pourront valider la demande </small>", "cu_admin_ressource",'input','','');
					}
				}
		}
	}
	$data .= '<br><input type="button" id ="ajouter_ressource" name="ajouter_ressource" value="'.$lbl_button.'" class="btn btn-success" onclick="'.$javascript.'">';
	$data .= '&nbsp;<input type="submit" id="annuler" name="annuler" value="Annuler" class="btn btn-danger">';
	$data .= '</div>';
	return $data;
}






/*
 * Champs à vérifier pour les formulaire
 * $type_form prend la valeur nom du formulaire
 * Par ex: SEANCE,RESSOURCE,TYPE,CALENDRIER
 * 
 */
function verif_champs($type_form,$id_modif=''){
	global $_POST;
	$error = array();
	switch ($type_form){
		case 'DDE_RESA':
			$verif=array('ressource'=>'Choix de la ressource','motif_dde'=>'Motif de la demande','date_debut'=>'Date de début',
						'heure_debut'=>'Heure de début','date_fin'=>'Date de fin','heure_fin'=>'Heure de fin');
			break;
		case 'SEANCE':
			$verif=array('reload_cal'=>'Choix du calendrier','type_seance'=>'Choix du type de séance','reload_ressource'=>'Choix de la ressource de séance',
						 'comment'=>'Commentaire','nb_pers'=>'Nombre de personnes','date_debut'=>'Date de début','heure_debut'=>'Heure de début','date_fin'=>'Date de fin','heure_fin'=>'Heure de fin');
			break;
		case 'RESSOURCE':
			//print_r($_POST);
			$verif=array('type_ressource'=>'Type de ressource','comment'=>'Commentaire','nom_ressource'=>'Nom de la ressource');
			if($_GET['p'] == 10)
				$verif['cal']="Calendrier";
			if (RESA_SALLES && isset($_POST['reload_reservable_ressource']) && $_POST['reload_reservable_ressource'] == 1){
				$verif['couleur_ressource']='Couleur de la ressource';
				if ($_POST['reload_valide_hierarchique'] == 1){
					$verif['email']='Email recevant les notifications';
					if($_GET['p'] == 11)
					$verif['cu_admin_ressource']='Unités admin de la ressource';
				}
			}
			break;
		case 'TYPE':
			$verif=array('nom_type'=>'Type du ressource','cal'=>'Calendrier');
			break;
		case 'CALENDRIER':
			$verif=array('nom_calendrier'=>'Nom du calendrier','visible'=>'Visibilité','comment'=>'Commentaire','unites'=>'Unité administrice du calendrier','nigends'=>'Nigend administrateur du calendrier');
			break;	
		case 'USER':
			$verif=array('login'=>'Login de connexion','nigend'=>'ID Utilisateur','uid'=>'Nom Utilisateur','codeUnite'=>'Service','mail'=>'Mail');
			break;
		case 'SERVICE':
			$verif=array('codeUnite'=>'Code Service','unite'=>'Libellé service');
			break;
	}
	if (isset($verif) && is_array($verif)){
		foreach ($verif as $k=>$v){
			//gestion des exceptions de vérification
			$exceptions=explode('_',$k);
			//si on est sur un champs date
			if (isset($exceptions[0]) && $exceptions[0] == 'date'){				
					if (!validateDate($_POST[$k],'Y-m-d'))
						$error[]=$v." n'est pas une date valide";
					else{
						$dteStart = new DateTime($_POST['date_debut'].' '.$_POST['heure_debut']);
						$dteEnd   = new DateTime($_POST['date_fin'].' '.$_POST['heure_fin']);
						if($dteStart > $dteEnd)
							return array('0'=>"La date de début est supérieure à la date de fin!");						
					}
			}
			//gestion de l'administration par une unité ou un nigend
			elseif ($exceptions[0] == 'unites' || $exceptions[0] == 'nigends'){
					if ((!isset($_POST[$exceptions[0]]) || $_POST[$exceptions[0]]) == "" && $_POST['reload_admin'] == $exceptions[0]){
						$error[]=$v;
					}
			//gestion classique des vérifications
			}elseif (!isset($_POST[$k]) || trim($_POST[$k]) == ""){
					$error[]=$v;				
			}				
		}
		if ($error == array() && ($type_form == 'SEANCE' || $type_form == 'DDE_RESA')){
			$error=verif_dispo_ressource($_POST,$id_modif);
			if ($error == array() && RESA_SALLES)
				$error=	verif_dispo_ressource($_POST,$id_modif,'DDE_RESA');
		}
		if ($error == array() && $type_form == 'RESSOURCE' && $_SESSION[NAME]['profil'] != 'sadministrateur'){
				if (!isset($_POST['cal']))
					$error[]="Choix du calendrier";			
		}
	}
	return $error;

}

function admin_ressource($id,$status=''){
	global $bdd;
	//vérification des droits sur cette séance
	$sql="select id_ressource from dde_resa where id=:id ";
	if ($status != '')
		$sql .= " and status=:status";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
	if ($status != '')
		$reponse->bindvalue(':status',$status,PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	return $donnees;	
	
}

function update_status_dde($id,$status){
	global $bdd;
	$sql="update dde_resa set status=:status where id=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
	$reponse->bindvalue(':status',$status,PDO::PARAM_STR);
	$reponse->execute();	
}

/*
 * fonction pour la gestion des séances
 * $type doit avoir la valeur ADD,MODIF,DEL
 *
 */
function resa($type,$id=null){
	global $bdd,$_POST;
	$sql="select id_ressource from dde_resa where id=:id_resa";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id_resa',$id,PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	if (isset($donnees['id_ressource']) && $type != 'ADD'){		
		maj_ressource_timestamp($donnees['id_ressource']);
		//cas des updates avec changement de ressources liées
		if (is_numeric($_POST['ressource']))
			maj_ressource_timestamp($_POST['ressource']);
	}elseif ($type == 'ADD')
		maj_ressource_timestamp($id);
	switch ($type){
			case 'REFUS':
				//vérification des droits sur cette séance
				$donnees=admin_ressource($id);
				//l'utilsateur connecté est bien admin de la ressource
				if (isset($donnees['id_ressource']) && isset($_SESSION[NAME]['admin_ressource'][$donnees['id_ressource']])){
					update_status_dde($id,3);
					envoi_mails_dde_resa($id,3);
				}
				return true;
				break;
			case 'VALID':
				//vérification des droits sur cette séance
				$donnees=admin_ressource($id,1);
				//l'utilsateur connecté est bien admin de la ressource
				if (isset($donnees['id_ressource']) && isset($_SESSION[NAME]['admin_ressource'][$donnees['id_ressource']])){
					update_status_dde($id,2);
					envoi_mails_dde_resa($id,2);
				}
				return true;
				break;		
			case 'MODIF':
				$error=	verif_dispo_ressource($_POST,$_POST['id_form'],'DDE_RESA');
				if ($error == array()){
					$sql="update dde_resa set id_ressource=:id_ressource, date_debut=:date_debut, date_fin=:date_fin,
										heure_debut=:heure_debut, heure_fin=:heure_fin, motif_dde=:motif_dde
									where id=:id";
					$reponse=$bdd->prepare($sql);
					//cas des updates avec changement de ressources liées
					if (is_numeric($_POST['ressource']))
						$reponse->bindvalue(':id_ressource',$_POST['ressource'],PDO::PARAM_STR);
					else
						$reponse->bindvalue(':id_ressource',$donnees['id_ressource'],PDO::PARAM_STR);
					$reponse->bindvalue(':date_debut',$_POST['date_debut'],PDO::PARAM_STR);
					$reponse->bindvalue(':date_fin',$_POST['date_fin'],PDO::PARAM_STR);
					$reponse->bindvalue(':heure_debut',$_POST['heure_debut'],PDO::PARAM_STR);
					$reponse->bindvalue(':heure_fin',$_POST['heure_fin'],PDO::PARAM_STR);
					$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
					$reponse->bindvalue(':motif_dde',$_POST['motif_dde'],PDO::PARAM_STR);
					$reponse->execute();
				}
				return $error;
				break;
			case 'DEL':
					//vérification des droits sur cette séance
					if (defined('UP_DEL_DDE')){
						$type_del_dde=UP_DEL_DDE;
					}else
						$type_del_dde="UNITE";
					$sql="select dd.id,dd.date_debut,dd.date_fin,dd.heure_debut,dd.heure_fin,dd.lbl_unite,r.nom
							from dde_resa dd, ressources r where dd.id_ressource=r.id and dd.id=:id ";
					if ($type_del_dde == "UNITE"){
						$sql.= "and dd.unite_demandeur = :up_del_dde";
						$up_del_dde = $_SESSION[NAME]['codeUnite'];
					}else{
						$sql.= "and dd.nigend_demandeur = :up_del_dde";
						$up_del_dde = $_SESSION[NAME]['nigend'];						
					}
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
					$reponse->bindvalue(':up_del_dde',$up_del_dde,PDO::PARAM_STR);
					$reponse->execute();
					$donnees=$reponse->fetch();					
				//l'utilisateur a bien les droits sur cette séance
				if (isset($donnees['id'])){
					//on log la demande de suppression et on envoi les mails
					log_action("DDE RESA SUPPRIMEE : ".$donnees['nom']."///demandeur : ".$donnees['lbl_unite']."///"
                                                        .date_to_php($donnees['date_debut'])." ".$donnees['heure_debut']."///"
                                                        .date_to_php($donnees['date_fin'])." ".$donnees['heure_fin']);
					envoi_mails_dde_resa($donnees['id'],4);
					//on supprime la demande. Plus de trace en base.
					$sql="delete from dde_resa where id=:id ";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id',$donnees['id'],PDO::PARAM_STR);
					$reponse->execute();
					return true;
				}
				return false;
			case 'ADD':
				$error=	verif_dispo_ressource($_POST,'','DDE_RESA');
				if ($error == array()){
					$donnees=info_ressource($id);
					//si l'utilisateur est dans une unité admin de la ressource 
					if ((isset($donnees['cu_admin']) && in_array($_SESSION[NAME]['codeUnite'],explode(';',$donnees['cu_admin'])))
							|| (isset($donnees['cal']) && in_array($_SESSION[NAME]['calendrier'],explode(';',$donnees['cal']))))
						$status=2;
					
					if (isset($donnees['email']) && !isset($status))
						$status=1;
					else 
						$status=2;
					$sql="insert into dde_resa (id_ressource,nigend_demandeur,unite_demandeur,lbl_unite,date_dde,date_debut,date_fin,heure_debut,heure_fin,nb_pers,motif_dde,status)
								values (:id_ressource,:nigend_demandeur,:unite_demandeur,:lbl_unite,NOW(),:date_debut,:date_fin,:heure_debut,:heure_fin,:nb_pers,:motif_dde,:status)";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id_ressource',$id,PDO::PARAM_STR);
					$reponse->bindvalue(':nigend_demandeur',$_SESSION[NAME]['nigend'],PDO::PARAM_STR);
					$reponse->bindvalue(':unite_demandeur',$_SESSION[NAME]['codeUnite'],PDO::PARAM_STR);
					/*******ON INSERT DANS LE CHAMP LBL_UNITE SOIT L UNITE SOIT LE GRADE/NOM/PRENOM/UNITE********/
					$reponse->bindvalue(':lbl_unite',$_SESSION[NAME][DETAIL_RESA],PDO::PARAM_STR);
					$reponse->bindvalue(':date_debut',$_POST['date_debut'],PDO::PARAM_STR);
					$reponse->bindvalue(':date_fin',$_POST['date_fin'],PDO::PARAM_STR);
					$reponse->bindvalue(':heure_debut',$_POST['heure_debut'],PDO::PARAM_STR);
					$reponse->bindvalue(':heure_fin',$_POST['heure_fin'],PDO::PARAM_STR);
					$reponse->bindvalue(':nb_pers',(is_numeric($_POST['nb_pers'])?$_POST['nb_pers']:0),PDO::PARAM_STR);
					$reponse->bindvalue(':motif_dde',$_POST['motif_dde'],PDO::PARAM_STR);
					$reponse->bindvalue(':status',$status,PDO::PARAM_STR);
					$reponse->execute();
					envoi_mails_dde_resa($bdd->lastInsertId());
				}
				return $error;
				break;
		}
}

/*
 * fonction pour récupérer les informations d'une ressource
 * prend en argument l'id de la ressource et une éventuelle restriction
 * CAL => pour lkes profils non sadmin pour que l'utilisateur ne puisse récupérer les informations
 * que des ressources qu'il gère.
 * En retour, on envoi un tableau de résultats
 *
 */
function info_ressource($id,$restriction=''){
	global $bdd;
	$sql="select nom,comment,type,cal,couleur,cu_admin,email,time_modif,purge_cycle,lier from ressources where id=:id ";
	if ($restriction == "CAL")
		$sql .=" and cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	return $donnees;
	
}




/*
 * fonction pour la gestion des séances
 * $type doit avoir la valeur ADD,MODIF,DEL
 *
 */
function seance($type,$id=null){
	global $bdd,$_POST;
	
	//un utilisateur simple ne peut accéder à cette fonction
	//normalement, il ne peut entrer mais on assure... ;)
	if ($_SESSION[NAME]['profil'] == 'utilisateur')
		return false;
	
	//mise à jour du timestamp de la ressource
	//pour signaler un changement sur cette resource
	if (isset($_POST['reload_ressource']))
		maj_ressource_timestamp($_POST['reload_ressource']);
		
		
	switch ($type){
		case 'MODIF':
			$error=verif_champs('SEANCE',$_POST['id_form']);
			if ($error == array()){
				$sql="update seance set id_cal=:id_cal, id_type=:id_type, id_ressource=:id_ressource, comment=:comment,
									nb_pers=:nb_pers, date_debut=:date_debut, date_fin=:date_fin,
									heure_debut=:heure_debut, heure_fin=:heure_fin, date_fin_inscription=:date_fin_inscription
								where id=:id";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id_cal',$_POST['reload_cal'],PDO::PARAM_STR);
				$reponse->bindvalue(':id_type',$_POST['type_seance'],PDO::PARAM_STR);
				$reponse->bindvalue(':id_ressource',$_POST['reload_ressource'],PDO::PARAM_STR);
				$reponse->bindvalue(':comment',$_POST['comment'],PDO::PARAM_STR);
				$reponse->bindvalue(':nb_pers',$_POST['nb_pers'],PDO::PARAM_STR);
				$reponse->bindvalue(':date_debut',$_POST['date_debut'],PDO::PARAM_STR);
				$reponse->bindvalue(':date_fin',$_POST['date_fin'],PDO::PARAM_STR);
				$reponse->bindvalue(':heure_debut',$_POST['heure_debut'],PDO::PARAM_STR);
				$reponse->bindvalue(':heure_fin',$_POST['heure_fin'],PDO::PARAM_STR);
				$reponse->bindvalue(':id',$_POST['id_form'],PDO::PARAM_STR);
				$reponse->bindvalue(':date_fin_inscription',($_POST['date_fin_inscription'] == ""?NULL:$_POST['date_fin_inscription']),PDO::PARAM_STR);
				$reponse->execute();
				log_action('Modification de la séance '.$_POST['date_debut'].'/'.$_POST['date_fin'],$_POST['reload_cal']);
			}
			return $error;
			break;
		case 'DEL':
			//vérification des droits sur cette séance
			$sql="select id,id_ressource,date_debut,date_fin,id_cal from seance where id=:id and id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			//l'utilisateur a bien les droits sur cette séance
			if (isset($donnees['id'])){
				//on efface toutes les incriptions
				$sql="delete from reservation where id_seance=:id ";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Suppression des réservations de la séance '.$donnees['date_debut'].'/'.$donnees['date_fin'],$donnees['id_cal']);
				$sql="delete from seance where id=:id and id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Suppression de la séance '.$donnees['date_debut'].'/'.$donnees['date_fin'],$donnees['id_cal']);
				//mise à jour du timestamp de la ressource
				//pour signaler un changement sur cette resource
				maj_ressource_timestamp($donnees['id_ressource']);				
				return true;
			}
			return false;
		case 'ADD':
			$error=verif_champs('SEANCE');
			if ($error == array()){
				$sql="insert into seance (id_cal,id_type,id_ressource,comment,nb_pers,date_debut,date_fin,heure_debut,heure_fin,date_fin_inscription)
								values (:id_cal,:id_type,:id_ressource,:comment,:nb_pers,:date_debut,:date_fin,:heure_debut,:heure_fin,:date_fin_inscription)";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id_cal',$_POST['reload_cal'],PDO::PARAM_STR);
				$reponse->bindvalue(':id_type',$_POST['type_seance'],PDO::PARAM_STR);
				$reponse->bindvalue(':id_ressource',$_POST['reload_ressource'],PDO::PARAM_STR);
				$reponse->bindvalue(':comment',$_POST['comment'],PDO::PARAM_STR);
				$reponse->bindvalue(':nb_pers',$_POST['nb_pers'],PDO::PARAM_STR);
				$reponse->bindvalue(':date_debut',$_POST['date_debut'],PDO::PARAM_STR);
				$reponse->bindvalue(':date_fin',$_POST['date_fin'],PDO::PARAM_STR);
				$reponse->bindvalue(':heure_debut',$_POST['heure_debut'],PDO::PARAM_STR);
				$reponse->bindvalue(':heure_fin',$_POST['heure_fin'],PDO::PARAM_STR);
				$reponse->bindvalue(':date_fin_inscription',($_POST['date_fin_inscription'] == ""?NULL:$_POST['date_fin_inscription']),PDO::PARAM_STR);
				$reponse->execute();
				log_action('Ajout de la séance '.$_POST['date_debut'].'/'.$_POST['date_fin'],$_POST['reload_cal']);
			}
			return $error;
			break;
	}
}






/*
 * fonction pour la gestion des ressources de séance
 * $type doit avoir la valeur ADD,MODIF,DEL
 *
 */
function ressource_seance($type,$id=null){
	global $bdd,$_POST;
	if ($_SESSION[NAME]['profil'] == 'utilisateur')
		return false;
	//mise à jour du timestamp admin
	maj_admin_timestamp();
	switch ($type){
		case 'MODIF':
			$error=verif_champs('RESSOURCE');
			if ($error == array()){
				$sql="update ressources set nom=:nom, comment=:comment, type=:type";
				if (isset($_POST['cal']))
					$sql.=",cal=:cal ";
				if (RESA_SALLES)
					$sql.=",couleur=:couleur,cu_admin=:cu_admin,email=:email,purge_cycle=:purge,lier=:lier ";
				$sql.=" where id=:id";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':nom',$_POST['nom_ressource'],PDO::PARAM_STR);
				$reponse->bindvalue(':comment',$_POST['comment'],PDO::PARAM_STR);
				$reponse->bindvalue(':type',$_POST['type_ressource'],PDO::PARAM_STR);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				if (isset($_POST['cal']))
					$reponse->bindvalue(':cal',$_POST['cal'],PDO::PARAM_STR);
				if (RESA_SALLES){
					$reponse->bindvalue(':couleur',(isset($_POST['couleur_ressource'])?$_POST['couleur_ressource']:NULL),PDO::PARAM_STR);
					$reponse->bindvalue(':cu_admin',(isset($_POST['cu_admin_ressource'])?$_POST['cu_admin_ressource']:NULL),PDO::PARAM_STR);
					$reponse->bindvalue(':email',(isset($_POST['email'])?$_POST['email']:NULL),PDO::PARAM_STR);
					$reponse->bindvalue(':purge',(isset($_POST['purge'])?$_POST['purge']:0),PDO::PARAM_STR);
					$reponse->bindvalue(':lier',((isset($_POST['lier'])&&$_POST['lier']!=0)?$_POST['lier']:NULL),PDO::PARAM_STR);
					
				}
				$reponse->execute();
				log_action('Modification de la ressource '.$_POST['nom_ressource'],(isset($_POST['cal'])?$_POST['cal']:0));
			}
			return $error;
			break;
		case 'DEL':
			//vérification des droits sur cette ressource
			if ($_SESSION[NAME]['profil'] != 'sadministrateur'){
				$donnees=info_ressource($id,'CAL');
			}else 
				$donnees=info_ressource($id);
			//l'utilisateur a bien les droits sur ce type
			if (isset($donnees['id']) || $_SESSION[NAME]['profil'] == 'sadministrateur'){
				//suppression de la ressource
				$sql="delete from ressources where id=:id ";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Suppression de la ressource '.$donnees['nom'],((isset($donnees['cal'])&& $donnees['cal']!= null)?$donnees['cal']:0));
				//suppression des inscriptions aux séances liées à cette ressource
				$sql="delete from reservation where id_seance in (select id from seance where id_ressource=:id)";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Suppression des inscriptions aux séances liées à la ressource '.$donnees['nom'],((isset($donnees['cal'])&& $donnees['cal']!= null)?$donnees['cal']:0));
				//suppression des séances liées à cette ressource
				$sql="delete from seance where id_ressource=:id";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Suppression des séances liées à la ressource '.$donnees['nom'],((isset($donnees['cal'])&& $donnees['cal']!= null)?$donnees['cal']:0));
				//suppression des demandes de réservation liées à cette ressource
				$sql="delete from dde_resa where id_ressource=:id";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();			
				log_action('Suppression des demandes de réservation liées à la ressource '.$donnees['nom'],((isset($donnees['cal'])&& $donnees['cal']!= null)?$donnees['cal']:0));
				//réinitialisation des ressources liées
				$sql="update ressources set lier=NULL where lier=:id";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Réinitialisation des ressources liées appartenant à la ressource '.$donnees['nom'],((isset($donnees['cal'])&& $donnees['cal']!= null)?$donnees['cal']:0));
				return true;
			}
			return false;
			break;
		case 'ADD':
			$error=verif_champs('RESSOURCE');
			if ($error == array()){
				$sql="insert into ressources (nom,comment,type";
				if (isset($_POST['cal']))
					$sql.=",cal";
				if (RESA_SALLES)
					$sql.=",couleur,cu_admin,email,purge_cycle,lier";
				$sql.=") values (:nom,:comment,:type";
				if (isset($_POST['cal']))
					$sql.=",:cal";
				if (RESA_SALLES)
					$sql.=",:couleur,:cu_admin,:email,:purge,:lier";
				$sql.=")";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':nom',$_POST['nom_ressource'],PDO::PARAM_STR);
				$reponse->bindvalue(':comment',$_POST['comment'],PDO::PARAM_STR);
				$reponse->bindvalue(':type',$_POST['type_ressource'],PDO::PARAM_STR);
				if (isset($_POST['cal']))
					$reponse->bindvalue(':cal',$_POST['cal'],PDO::PARAM_STR);
				if (RESA_SALLES){
					$reponse->bindvalue(':couleur',(isset($_POST['couleur_ressource'])?$_POST['couleur_ressource']:NULL),PDO::PARAM_STR);
					$reponse->bindvalue(':cu_admin',(isset($_POST['cu_admin_ressource'])?$_POST['cu_admin_ressource']:NULL),PDO::PARAM_STR);
					$reponse->bindvalue(':email',(isset($_POST['email'])?$_POST['email']:NULL),PDO::PARAM_STR);
					$reponse->bindvalue(':purge',(isset($_POST['purge'])?$_POST['purge']:0),PDO::PARAM_STR);
					$reponse->bindvalue(':lier',((isset($_POST['lier'])&&$_POST['lier']!=0)?$_POST['lier']:NULL),PDO::PARAM_STR);
				}
				$reponse->execute();
				log_action('Ajout de la ressource '.$_POST['nom_ressource'],(isset($_POST['cal'])?$_POST['cal']:0));
			}
			return $error;
			break;
	}
}


/*
 * fonction pour la gestion des calendriers
 * $type doit avoir la valeur ADD,MODIF,DEL
 *
 */
function calendrier($type,$id=null){
	global $bdd,$_POST;
	if ($_SESSION[NAME]['profil'] != 'sadministrateur')
		return false;
	//mise à jour du timestamp admin
	maj_admin_timestamp();
	switch ($type){
		case 'DEL':
			//info calendrier
			$sql="select nom from calendrier where id=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			
			//suppression du calendrier
			$sql="delete from calendrier where id=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression du calendrier '.$donnees['nom']);
			
			//suppression des inscriptions aux séances liées aux ressources du calendrier
			$sql="delete from reservation where id_seance in (select id from seance where id_cal=:id)";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression des séances liées au calendrier '.$donnees['nom']);
			
			//suppression des séances de ce calendrier
			$sql="delete from seance where id_cal=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression des inscriptions aux séances liées aux ressources du calendrier '.$donnees['nom']);
			
			//suppression des types de séance de ce calendrier
			$sql="delete from type_seance where id_cal=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression des types séance liés au calendrier '.$donnees['nom']);
			
			//suppression des logs de ce calendrier
			$sql="delete from log where id_cal=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression des logs liés au calendrier '.$donnees['nom']);
			
			//suppression des demandes de réservation liées aux ressources de ce calendrier
			$sql="delete from dde_resa where id_ressource in (select id from ressources where cal=:id)";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression des inscriptions aux séances liées aux ressources du calendrier '.$donnees['nom']);
			
			//suppression des ressources local de ce calendrier
			$sql="delete from ressources where cal=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			log_action('Suppression des ressources liées au calendrier '.$donnees['nom']);
			
			//suppression des inscriptions à ce calendrier
			$sql="select r.id from reservation r,seance s where s.id=r.id_seance and s.id_cal=:id";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
			$reponse->execute();
			while ($donnees2=$reponse->fetch()){
				$sql2="delete from reservation where id_seance=:id";
				$reponse2=$bdd->prepare($sql2);
				$reponse2->bindvalue(':id',$donnees['id'],PDO::PARAM_STR);
			}
			log_action('Suppression des réservations liées aux ressources du calendrier '.$donnees['nom']);
			return true;
			break;
		case 'ADD':
			$error = verif_champs('CALENDRIER');
			if ($error == array()){
				$sql="insert into calendrier (nom,visible,comment,unite,nigend) values (:nom,:visible,:comment,:unite,:nigend)";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':nom',$_POST['nom_calendrier'],PDO::PARAM_STR);
				$reponse->bindvalue(':visible',$_POST['visible'],PDO::PARAM_STR);
				$reponse->bindvalue(':comment',$_POST['comment'],PDO::PARAM_STR);
				if (is_array($_POST['unites'])){
					foreach($_POST['unites'] as $k=>$v){
						if (is_numeric($v))
							$unite_filtre[]=$v;						
					}
					$reponse->bindvalue(':unite',implode(',',$unite_filtre),PDO::PARAM_STR);					
				}else
					$reponse->bindvalue(':unite',(isset($_POST['unites'])?$_POST['unites']:''),PDO::PARAM_STR);
				$reponse->bindvalue(':nigend',(isset($_POST['nigends'])?$_POST['nigends']:''),PDO::PARAM_STR);
				$reponse->execute();
				log_action('Ajout du calendrier '.$_POST['nom_calendrier']);
			}
			return $error;
			break;
		case 'MODIF':
			$error = verif_champs('CALENDRIER');
			if ($error == array()){
				$sql="update calendrier set nom=:nom,visible=:visible,comment=:comment,unite=:unite,nigend=:nigend where id=:id";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':nom',$_POST['nom_calendrier'],PDO::PARAM_STR);
				$reponse->bindvalue(':visible',$_POST['visible'],PDO::PARAM_STR);
				$reponse->bindvalue(':comment',$_POST['comment'],PDO::PARAM_STR);
				if (isset($_POST['unites']) && is_array($_POST['unites'])){
					$unite_filtre=traitement_array($_POST['unites']);
					$reponse->bindvalue(':unite',$unite_filtre,PDO::PARAM_STR);
				}else
					$reponse->bindvalue(':unite',(isset($_POST['unites'])?$_POST['unites']:''),PDO::PARAM_STR);
				if (isset($_POST['nigends']) && is_array($_POST['nigends'])){
					$id_filtre=traitement_array($_POST['nigends']);
					$reponse->bindvalue(':nigend',$id_filtre,PDO::PARAM_STR);				
				}else
					$reponse->bindvalue(':nigend',(isset($_POST['nigends'])?$_POST['nigends']:''),PDO::PARAM_STR);
				$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
				$reponse->execute();
				log_action('Modification du calendrier '.$_POST['nom_calendrier']);
			}
		    return $error;
			break;
							
	}
}
/*
 * Fonction qui permet de faire un traitement sur un tableau 
 * contenant des numeriques.
 * En retour, on retourne un string des valeurs numériques du tableau séparées par la variable $separateur (; ou , ou autre)
 * 
 */
function traitement_array($data,$sepateur=";"){
	if (!is_array($data))
		return false;
	foreach($data as $k=>$v){
		if (is_numeric($v))
			$traite[]=$v;
	}
	return implode($sepateur,$traite);
	
}

/*
 * fonction pour la gestion des type de séance
 * $type doit avoir la valeur ADD,MODIF,DEL
 *
 */
function type_seance($type,$id=null){
	global $bdd,$_POST;
	if ($_SESSION[NAME]['profil'] == 'utilisateur')
		return false;
		switch ($type){
			case 'MODIF':
				$error = verif_champs('TYPE');
				if ($error == array()){
					$sql="update type_seance set id_cal=:id_cal, nom=:nom, couleur=:couleur where id=:id";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':nom',$_POST['nom_type'],PDO::PARAM_STR);
					$reponse->bindvalue(':couleur',$_POST['couleur_type'],PDO::PARAM_STR);
					$reponse->bindvalue(':id_cal',$_POST['cal'],PDO::PARAM_STR);
					$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
					$reponse->execute();
					log_action('Modification du type séance '.$_POST['nom_type'],$_POST['cal']);
				}
				return $error;
				break;
			case 'DEL':
					//vérification des droits sur ce type
					$sql="select id,nom,id_cal from type_seance where id=:id and id_cal in (".implode(',',array_flip($_SESSION[NAME]['cal'])).")";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
					$reponse->execute();
					$donnees=$reponse->fetch();
				//l'utilisateur a bien les droits sur ce type
				if (isset($donnees['id']) || $_SESSION[NAME]['profil'] == 'sadministrateur'){
					log_action('suppression du type séance '.$donnees['nom'],$donnees['id_cal']);
					//suppression du type_seance
					$sql="delete from type_seance where id=:id and id_cal";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
					$reponse->execute();
					log_action('suppression des séances liées au type séance '.$donnees['nom'],$donnees['id_cal']);
					//suppression de la séance liée à ce type
					$sql="delete from seance where id_type=:id";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':id',$id,PDO::PARAM_STR);
					$reponse->execute();
					return array();
				}
				return false;
				break;
			case 'ADD':
				$error = verif_champs('TYPE');
				if ($error == array()){
					$sql="insert into type_seance (id_cal,nom,couleur) values (:id_cal,:nom,:couleur)";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':nom',$_POST['nom_type'],PDO::PARAM_STR);
					$reponse->bindvalue(':couleur',$_POST['couleur_type'],PDO::PARAM_STR);
					$reponse->bindvalue(':id_cal',$_POST['cal'],PDO::PARAM_STR);
					$reponse->execute();
					log_action('Ajout du type séance '.$_POST['nom_type'],$_POST['cal']);
				}
				return $error;
				break;
		}
}

/*
 * fonction pour la gestion des utilisateurs
 * $type doit avoir la valeur ADD,MODIF,DEL
 *
 */
function user($type,$id=null){
	global $bdd,$_POST;
	if ($_SESSION[NAME]['profil'] != 'sadministrateur')
		return false;
	switch ($type){
		case 'MODIF':
			$error = verif_champs('USER');
			if ($error == array()){
				$sql="update users set uid=:uid, codeUnite=:codeUnite, mail=:mail";
				if (isset($_POST['password']) && $_POST['password'] != '')
					$sql.=",password=PASSWORD(:password)";
				$sql .=" where nigend=:nigend";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':nigend',$_POST['nigend'],PDO::PARAM_STR);
				$reponse->bindvalue(':uid',$_POST['uid'],PDO::PARAM_STR);
				$reponse->bindvalue(':codeUnite',$_POST['codeUnite'],PDO::PARAM_STR);
				$reponse->bindvalue(':mail',$_POST['mail'],PDO::PARAM_STR);
				if (isset($_POST['password']) && $_POST['password'] != '')
					$reponse->bindvalue(':password',$_POST['password'],PDO::PARAM_STR);
				$reponse->execute();
				log_action('Modification de l\'utilisateur '.$_POST['nigend'].'-'.$_POST['uid']);
			}
			return $error;
			break;
		case 'DEL':
			log_action('suppression de l\'utilisateur');
			//suppression de la séance liée à ce type
			$sql="delete from users where nigend=:nigend";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':nigend',$id,PDO::PARAM_STR);
			$reponse->execute();
			return array();
			break;
		case 'ADD':
			$error = verif_champs('USER');
			//on vérifie que l'id utilisateur n'est pas déjà pris
			$sql="select count(nigend) nb from users where nigend = :nigend";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':nigend',$_POST['nigend'],PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			if ($donnees['nb'] > 0)
				$error[]='<br><b>Cet ID Utilisateur est déjà pris. Vous ne pouvez pas l\'utiliser</b>';
			//on vérifie que le login utilisateur n'est pas déjà pris
			$sql="select count(nigend) nb from users where login = :login";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':login',$_POST['login'],PDO::PARAM_STR);
			$reponse->execute();
			$donnees=$reponse->fetch();
			if ($donnees['nb'] > 0)
				$error[]='<br><b>Ce login Utilisateur est déjà pris. Vous ne pouvez pas l\'utiliser</b>';
			if ($error == array()){
				if (trim($_POST['password']) == '')
					return array('Password de connexion');
				$sql="insert into users (nigend,uid,codeUnite,mail,login,password,profil) values (:nigend,:uid,:codeUnite,:mail,:login,PASSWORD(:password),'utilisateur')";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':nigend',$_POST['nigend'],PDO::PARAM_STR);
				$reponse->bindvalue(':uid',$_POST['uid'],PDO::PARAM_STR);
				$reponse->bindvalue(':codeUnite',$_POST['codeUnite'],PDO::PARAM_STR);
				$reponse->bindvalue(':mail',$_POST['mail'],PDO::PARAM_STR);
				$reponse->bindvalue(':login',$_POST['login'],PDO::PARAM_STR);
				$reponse->bindvalue(':password',$_POST['password'],PDO::PARAM_STR);
				$reponse->execute();
				log_action('Ajout de l\'utilisateur '.$_POST['nigend'].'-'.$_POST['uid']);
			}
			return $error;
			break;
	}
}


function services($type,$id=null){
	global $bdd,$_POST;
	if ($_SESSION[NAME]['profil'] != 'sadministrateur')
		return false;
		switch ($type){
			case 'MODIF':
				$error = verif_champs('SERVICE');
				if ($error == array()){
					$sql="update services set unite=:unite, mail=:mail where codeUnite=:codeUnite";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':codeUnite',$_POST['codeUnite'],PDO::PARAM_STR);
					$reponse->bindvalue(':unite',$_POST['unite'],PDO::PARAM_STR);
					$reponse->bindvalue(':mail',$_POST['mail'],PDO::PARAM_STR);
					$reponse->execute();
					log_action('Modification du service '.$_POST['codeUnite'].'-'.$_POST['unite']);
				}
				return $error;
				break;
			case 'DEL':
				$error=array();
				$sql="select count(id) nb from users where codeUnite=:codeUnite";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':codeUnite',$id,PDO::PARAM_STR);
				$reponse->execute();
				$donnees=$reponse->fetch();
				if ($donnees['nb'] > 0)
					$error[]='<b>Vous ne pouvez pas supprimer un service si des personnes sont dedans</b>';
				if ($error == array()){
					//suppression de la séance liée à ce type
					$sql="delete from services where codeUnite=:codeUnite";
					$reponse=$bdd->prepare($sql);
					$reponse->bindvalue(':codeUnite',$id,PDO::PARAM_STR);
					$reponse->execute();
					log_action('suppression du service');					
				}
				return $error;
				break;
			case 'ADD':
				$error = verif_champs('SERVICE');
				//on vérifie que l'id utilisateur n'est pas déjà pris
				$sql="select count(codeUnite) nb from services where codeUnite = :codeUnite";
				$reponse=$bdd->prepare($sql);
				$reponse->bindvalue(':codeUnite',$_POST['codeUnite'],PDO::PARAM_STR);
				$reponse->execute();
				$donnees=$reponse->fetch();
				if ($donnees['nb'] > 0)
					$error[]='<br><b>Ce code service est déjà pris. Vous ne pouvez pas l\'utiliser</b>';
					//on vérifie que le login utilisateur n'est pas déjà pris
				if ($error == array()){
						$sql="insert into services (codeUnite,unite,mail) values (:codeUnite,:unite,:mail)";
						$reponse=$bdd->prepare($sql);
						$reponse->bindvalue(':codeUnite',$_POST['codeUnite'],PDO::PARAM_STR);
						$reponse->bindvalue(':unite',$_POST['unite'],PDO::PARAM_STR);
						$reponse->bindvalue(':mail',$_POST['mail'],PDO::PARAM_STR);
						$reponse->execute();
						log_action('Ajout du service '.$_POST['codeUnite'].'-'.$_POST['unite']);
				}
				return $error;
				break;
		}
}






/*
 * 
 * Fonction qui permet de vérifier la disponibilité d'une ressource
 * pour une séance
 * 
 * 
 */

function verif_dispo_ressource($post,$id_modif='',$dde_resa=''){
	global $bdd;
	$error=array();
	if (isset($post['reload_ressource']))
		$ressource=$post['reload_ressource'];
	elseif (isset($post['ressource'])) 
		$ressource=$post['ressource'];
	else{
		$error[]='Problème de ressource!';
		return $error;
	}
		
	if ($dde_resa == ''){
		$sql="select s.date_debut,s.date_fin,s.heure_debut,s.heure_fin
								from seance s,ressources l
								where s.id_ressource = l.id and s.id_ressource=:id_ressource and l.type = 'unique' ";
		if (is_numeric($id_modif))
			$sql.=" and s.id != :id";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id_ressource',$ressource,PDO::PARAM_STR);
		if (is_numeric($id_modif))
			$reponse->bindvalue(':id',$id_modif,PDO::PARAM_STR);
		$reponse->execute();
	}else{
		 $sql="select dde.date_debut,dde.date_fin,dde.heure_debut,dde.heure_fin
					 from dde_resa dde,ressources l
		 			where dde.id_ressource = l.id and dde.id_ressource=:id_ressource and l.type = 'unique' and (dde.status=1 or dde.status=2)";
		 if (is_numeric($id_modif))
		 		$sql.=" and dde.id != :id";
		 $reponse=$bdd->prepare($sql);
		 $reponse->bindvalue(':id_ressource',$ressource,PDO::PARAM_STR);
		 if (is_numeric($id_modif))
		 		$reponse->bindvalue(':id',$id_modif,PDO::PARAM_STR);
		 $reponse->execute();		
	}
	while ($donnees=$reponse->fetch()){
		$date_debut=explode('-',$donnees['date_debut']);
		$date_fin=explode('-',$donnees['date_fin']);
		$heure_debut=explode(':',$donnees['heure_debut']);
		$heure_fin=explode(':',$donnees['heure_fin']);
		$debut[]=mktime($heure_debut[0]+1,$heure_debut[1],0,$date_debut[1],$date_debut[2],$date_debut[0]);
		$fin[]=mktime($heure_fin[0]+1,$heure_fin[1],0,$date_fin[1],$date_fin[2],$date_fin[0]);
	}
	$date_debut=explode('-',$post['date_debut']);
	$date_fin=explode('-',$post['date_fin']);
	if ($post['heure_debut'] != '')
		$heure_debut=explode(':',$post['heure_debut']);
	else
		$heure_debut=array(0,0,0);
				
	if ($_POST['heure_fin'] != '')
		$heure_fin=explode(':',$post['heure_fin']);
	else
		$heure_fin=array(23,59,0);
	$transf_debut=mktime($heure_debut[0]+1,$heure_debut[1],0,$date_debut[1],$date_debut[2],$date_debut[0]);
	$transf_fin=mktime($heure_fin[0]+1,$heure_fin[1],0,$date_fin[1],$date_fin[2],$date_fin[0]);
	$i=0;
	//print_r($debut);
		//echo "<br><br>date saisie = ".$transf_debut." // ".$transf_fin;
	while (isset($debut[$i]) && $error == array()){
		//echo "<br><br>date verif = ".$debut[$i]." // ".$fin[$i];
		if ($transf_debut >= $debut[$i] && $transf_debut <= $fin[$i]){
			$error[]='Cette ressource est déjà réservée à ces dates';
			//echo "Date de début entre la date de début et la date de fin KO";
		}elseif($transf_debut < $debut[$i] && $transf_fin > $fin[$i]){
			$error[]='Cette ressource est déjà réservée à ces dates';
			//echo "Date de début avant la date de début mais date de fin après la date de fin KO";
		}elseif($transf_debut < $debut[$i] && $transf_fin > $fin[$i]){
			$error[]='Cette ressource est déjà réservée à ces dates';
			//echo "Date de début avant la date de début mais date de fin après la date de fin KO";
		}elseif($transf_fin >= $debut[$i] && $transf_fin <= $fin[$i]){
			$error[]='Cette ressource est déjà réservée à ces dates';
			//echo "Date de fin entre la date de début et la date de fin KO";
		}
	$i++;
	}
	return $error;
}

function validateDate($date, $format = 'Y-m-d H:i:s')
{
	$d = DateTime::createFromFormat($format, $date);
	return $d && $d->format($format) == $date;
}



function date_to_mysql($date){
	$array=explode('/',$date);
	return $array[2].'-'.$array[1].'-'.$array[0];	
}

function date_to_php($date){
	$array=explode('-',$date);
	return $array[2].'/'.$array[1].'/'.$array[0];
}

function datetime_to_php($datetime){
	$array_date_time=explode(' ',$datetime);
	$date=date_to_php($array_date_time[0]);
	return $date.' '.$array_date_time[1];
	
	
}


function ressources_reservables($lier=false){
	global $bdd;
	$liste_ressources=array();
	$sql="select id,nom,cal,cu_admin,comment,email from ressources where couleur is not NULL ";
	if (!$lier)
		$sql.= " and lier is null";
	elseif(is_numeric($lier))
		$sql.= " and lier = ".$lier;
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	while ($donnees=$reponse->fetch()){
		$liste_ressources['COMMENT'][$donnees['id']]=$donnees['comment'];
		$liste_ressources['RESSOURCES'][$donnees['id']]=$donnees['nom'];
		$liste_ressources['EMAIL'][$donnees['id']]=$donnees['email'];
		if ($donnees['cu_admin'] != NULL)
			$liste_ressources['CU_ADMIN'][$donnees['id']]=$donnees['cu_admin'];
		else 
			$liste_ressources['CAL_ADMIN'][$donnees['id']]=$donnees['cal'];
		$liste_ressources['ID'][$donnees['id']]=$donnees['id'];
	}
	return $liste_ressources;
}

function recup_param($lbl){
	global $bdd;
	if (!isset($bdd))
		return false;
	$sql='select tvalue from parametres where lbl=:lbl';
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':lbl',$lbl,PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	return $donnees['tvalue'];
		
	
	
}

function num_cal($id_seance){
	global $bdd;
	$sql="select id_cal from seance where id=:id";
	$reponse=$bdd->prepare($sql);
	$reponse->bindvalue(':id',$id_seance,PDO::PARAM_STR);
	$reponse->execute();
	$donnees=$reponse->fetch();
	if (isset($donnees['id_cal']))
		return $donnees['id_cal'];
	return 0;
}


function log_action($comment,$cal=0){
	global $bdd;
	//if (!in_array($_SESSION[NAME]['nigend'],NOLOGS)){
		$sql="insert into log (id_cal,date_action,nigend,comment) values (:id_cal,NOW(),:nigend,:comment)";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':id_cal',$cal,PDO::PARAM_STR);
		$reponse->bindvalue(':nigend',(isset($_SESSION[NAME]['nigend'])?$_SESSION[NAME]['nigend']:0),PDO::PARAM_STR);	
		$reponse->bindvalue(':comment',$comment,PDO::PARAM_STR);
		$reponse->execute();	
	//}
}

//fonction qui permet de purger
//les logs
function purge_log(){
	global $bdd;
	if (defined('PURGE_LOG') && is_numeric(PURGE_LOG)){
		$sql="select count(id) nb from log where date_action < :date_purge";
		$reponse=$bdd->prepare($sql);
		$reponse->bindvalue(':date_purge',date('Y-m-d', strtotime(' - '.PURGE_LOG.' days')),PDO::PARAM_STR);
		$reponse->execute();
		$donnees=$reponse->fetch();
		if ($donnees['nb'] > 0){
			$sql="delete from log where date_action < :date_purge";
			$reponse=$bdd->prepare($sql);
			$reponse->bindvalue(':date_purge',date('Y-m-d', strtotime(' - '.PURGE_LOG.' days')),PDO::PARAM_STR);
			$reponse->execute();
			log_action("Purge des logs (cycle de ".PURGE_LOG." jours)");
		}
	}
}




function profil_application(){
	global $bdd;
	if (AUTH == "LDAP" || AUTH == "SSO"){	
		/*****INITIALISATION DES VARIABLES SI ELLES EXISTENT ********/
		if (isset($_SESSION[NAME]['profil'])){
			unset($_SESSION[NAME]['profil'],$_SESSION[NAME]['calendrier'],
					$_SESSION[NAME]['sous_menu'],$_SESSION[NAME]['cal'],$_SESSION[NAME]['cal_visible'],
					$_SESSION[NAME]['admin_ressource']);
		}
		//récupération des infos ldap de la personne connectée
		//cela permet de savoir si elle est affectée/détachée
		$info_user=ldap_info_user($_SESSION[NAME]['nigend']);
		if (isset($info_user[0]['departmentnumber'][0]))
			$_SESSION[NAME]['departmentNumber']=$info_user[0]['departmentnumber'][0];
		if (isset($info_user[0]['codeuniteaff'][0])){
			$_SESSION[NAME]['codeUniteAff']=$info_user[0]['codeuniteaff'][0];
			$_SESSION[NAME]['departmentUIDAff']=$info_user[0]['departmentuidaff'][0];
			$_SESSION[NAME]['codeUnite']=$info_user[0]['codeunite'][0];
			$_SESSION[NAME]['departmentUID']=$info_user[0]['departmentuid'][0];
		}else{
			$_SESSION[NAME]['codeUniteAff']=$_SESSION[NAME]['codeUnite'];
			$_SESSION[NAME]['departmentUIDAff']=$_SESSION[NAME]['departmentUID'];
		}
	}else{
		$_SESSION[NAME]['codeUniteAff']=$_SESSION[NAME]['codeUnite'];
		$_SESSION[NAME]['departmentUIDAff']=$_SESSION[NAME]['uid'];	
		if (isset($_SESSION[NAME]['profil']) && $_SESSION[NAME]['profil'] == "utilisateur")
			unset($_SESSION[NAME]['profil']);
	}
	if (!isset($_SESSION[NAME]['token']))
		$_SESSION[NAME]['token'] = md5(uniqid(mt_rand(), true));
	
	
	
	//Si l'unité de l'utilisateur appartient aux unités "visualisation" de l'application
	if (defined('VISU')){
		if (in_array($_SESSION[NAME]['codeUniteAff'],VISU))
			$_SESSION[NAME]['profil'] = 'visualisation';				
	}
	
	//Si l'unité de l'utilisateur appartient aux unités super admin de l'application
	if (defined('ADMIN')){
	   if (in_array($_SESSION[NAME]['codeUniteAff'],ADMIN)){
		  $_SESSION[NAME]['profil'] = 'sadministrateur';
		  $_SESSION[NAME]['origine_profil'] = 'unite_admin';
	   }
	}
	
	if (defined('NIGEND_ADMIN')){
		if (in_array($_SESSION[NAME]['nigend'],NIGEND_ADMIN)){
			$_SESSION[NAME]['profil'] = 'sadministrateur';
			$_SESSION[NAME]['origine_profil'] = 'nigend_admin';
		}		
	}

	/*ACTIONS A MENER QUAND UN SUPERADMIN SE CONNECTE*/
	if (isset($_SESSION[NAME]['profil']) &&  $_SESSION[NAME]['profil'] == 'sadministrateur'){
		purge_log();	
	}
	
	//récupération des différents calendriers de l'application
	$sql="select id,nom,unite,nigend,visible from calendrier";
	$reponse=$bdd->prepare($sql);
	$reponse->execute();
	while ($donnees=$reponse->fetch()){
		$_SESSION[NAME]['calendrier'][$donnees['id']]=$donnees['nom'];
		//si le calenbdrier est visible, on le prend pour l'afficher dans les menus
		if ($donnees['visible'] == 1)
			$_SESSION[NAME]['sous_menu'][1][$donnees['id']]=$donnees['nom'];
		$unit=explode(';',$donnees['unite']);
		$nig=explode(';',$donnees['nigend']);
		/*GESTION DES CALENDRIERS PAR CODE UNITE*/
		//si la personne connectée est dans une unité admin d'un calendrier ou si superadmin
		if (in_array($_SESSION[NAME]['codeUniteAff'],$unit) || (isset($_SESSION[NAME]['profil']) &&  $_SESSION[NAME]['profil'] == 'sadministrateur')){
			$_SESSION[NAME]['cal'][$donnees['id']]=$donnees['nom'];
			if ($donnees['visible'] == 1)
				$_SESSION[NAME]['cal_visible'][$donnees['id']]=$donnees['nom'];
		}
		/*GESTION DES CALENDRIERS PAR NIGEND*/
		//si la personne connecté est dans la liste des nigends admin d'un calendrier ou si superadmin
		if (in_array($_SESSION[NAME]['nigend'],$nig) || (isset($_SESSION[NAME]['profil']) &&  $_SESSION[NAME]['profil'] == 'sadministrateur')){
			$_SESSION[NAME]['cal'][$donnees['id']]=$donnees['nom'];
			if ($donnees['visible'] == 1)
				$_SESSION[NAME]['cal_visible'][$donnees['id']]=$donnees['nom'];
		}
	}
	//si l'utilisateur a le profil 'visualisation' on s'arrête là
	if (isset($_SESSION[NAME]['profil']) &&  $_SESSION[NAME]['profil'] == 'visualisation')
		return;
	
	
	
	//profil soit admin (si admin d'au moins 1 calendrier)
	// soit utilisateur (doit d'utilisation - inscription)
	// soit visualisation (droit d'accès uniquement)
	if (!isset($_SESSION[NAME]['profil'])){
		if (isset($_SESSION[NAME]['cal'])){
			$_SESSION[NAME]['profil'] = 'administrateur';
		}else{
			//on regarde si une restriction à l'affichage est mise (fichier _ini.php)
			if (!defined('REST_ACCES') || REST_ACCES == array() || REST_ACCES == array("") || REST_ACCES == "")
				$_SESSION[NAME]['profil'] = 'utilisateur';
			else{				
				$mes_restrictions=REST_ACCES;
				foreach($mes_restrictions as $v){
					/*cas de restriction par code unité*/
					if (is_numeric($v)){
						if ($_SESSION[NAME]['codeUniteAff'] == $v)
							$_SESSION[NAME]['profil'] = 'utilisateur';
						//on regarde si on est sur un personnel détaché. Si oui, on regarde si son unité de détachement passe la restriction
						//si oui, il a les droits de visualisation
						if ($_SESSION[NAME]['codeUniteAff'] != $_SESSION[NAME]['codeUnite'] && $_SESSION[NAME]['codeUnite'] == $v)
							$_SESSION[NAME]['profil'] = 'visualisation';
					/*cas de restriction par libellé code unité*/
					}else{
						$existe_UID=stripos($_SESSION[NAME]['departmentUIDAff'], $v);
						if ($existe_UID !== false)
							$_SESSION[NAME]['profil'] = 'utilisateur';
						//on regarde si on est sur un personnel détaché. Si oui, on regarde si son libellé de détachement passe la restriction
						//si oui, il a les droits de visualisation
						if ($_SESSION[NAME]['departmentUID'] != $_SESSION[NAME]['departmentUIDAff']){
							$existe_UID=stripos($_SESSION[NAME]['departmentUID'], $v);
							if ($existe_UID !== false){
								$_SESSION[NAME]['profil'] = 'visualisation';	
							}
						}
					}
					
				}
				

			}
			
		}
	}
	//echo $_SESSION[NAME]['codeUniteAff'];
	//recherche des ressources qui sont ouvertes à la réservation
	//et on regarde si l'unité de l'utilisateur connecté administre cette ressource
	$liste_ressources=ressources_reservables('TOUS');
	//print_r($liste_ressources);
	if (isset($liste_ressources['RESSOURCES'])){
		foreach ($liste_ressources['RESSOURCES'] as $k=>$v){
			if (isset($liste_ressources['CU_ADMIN'][$k])){
				$mes_admin=explode(';',$liste_ressources['CU_ADMIN'][$k]);
				//echo "toto=>".$_SESSION[NAME]['codeuniteaff'];
				if (isset($_SESSION[NAME]['codeUniteAff']) && in_array($_SESSION[NAME]['codeUniteAff'],$mes_admin)){
					//print_r($mes_admin);
					$_SESSION[NAME]['admin_ressource'][$k]=$k;
				}
			}
			if (isset($liste_ressources['CAL_ADMIN'][$k])){
				if (isset($_SESSION[NAME]['cal'][$liste_ressources['CAL_ADMIN'][$k]]))
					$_SESSION[NAME]['admin_ressource'][$k]=$k;	
			}
		}	
	}
	
	
}


function entete($refresh=0){
    global $menu_page;
    if (isset($_POST) && $_POST != array() && isset($_SESSION[NAME]['token'])){
    	if (!isset($_POST['token']) ||  $_POST['token'] != $_SESSION[NAME]['token']){
    		require_once('error.php');
    		die();
    	}
    }
    //on ne charge pas les entêtes car une redirection est prévu pour les liens vers les menus d'admin
  /*  if (isset($_GET['option']) && !is_numeric($_GET['option']))
    	return false;*/
    //chargement de l'entete html et de toutes les librairies
    if ($refresh > 0 || defined('NAME'))
    	$tit=NAME;
    else
    	$tit="Installation";
	echo "<!DOCTYPE html>
	<html lang=\"fr\">
	<head>
	  <title>".$tit."</title>
	  <meta charset=\"utf-8\">
	  <meta name=\"viewport\" content=\"width=device-width\">";
	if ($refresh > 0)
		echo "<meta http-equiv='refresh' content='".$refresh."'>";
	
	//chargement des CSS
	echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"img/logo.png\">
			 <link rel=\"stylesheet\" href=\"bootstrap-5.3.0-alpha1-dist/css/bootstrap.min.css\">
	<link rel=\"stylesheet\" href=\"bootstrap-select-1.14.0-beta2/dist/css/bootstrap-select.min.css\">
	  <link rel=\"stylesheet\" href=\"bootstrap-table-master/dist/bootstrap-table.min.css\">
	
	<link rel=\"stylesheet\" href=\"fontawesome-free-6.3.0-web/css/all.min.css\">	
			<link rel=\"stylesheet\" href=\"css/new_css.css\">
			<link rel=\"stylesheet\" href=\"css/mon_css.css\">";
	
	
	echo "<script src=\"js/jquery-3.6.0.min.js\"></script>
	  <script src=\"bootstrap-5.3.0-alpha1-dist/js/bootstrap.bundle.min.js\"></script>			
	  <script src=\"bootstrap-select-1.14.0-beta2/dist/js/bootstrap-select.min.js\"></script>				
	  <script src=\"bootstrap-table-master/dist/bootstrap-table.min.js\"></script>
	  <script src=\"bootstrap-table-master/dist/extensions/export/bootstrap-table-export.min.js\"></script> 
	  <script src=\"js/tableExport.min.js\"></script>
	  <script src=\"fullcalendar-6.1.4/dist/index.global.min.js\"></script>
	  <script src=\"fullcalendar-6.1.4/packages/core/locales-all.global.min.js\"></script>
	  <script src=\"ckeditor5-build-classic-36.0.0/ckeditor5-build-classic/ckeditor.js\"></script>
	  <script src=\"js/mon_js.js\"></script>
	";
	echo '  <script>
	  window.console = window.console || function(t) {};
	  if (document.location.search.match(/type=embed/gi)) {
	    window.parent.postMessage("resize", "*");
	  }
	</script>';
	echo "
	</head>
	<body translate=\"no\">";

	if (!isset($_GET['restriction']) && isset($_SESSION[NAME])){
		
		menu3();
	}
	echo '<article>';
	
//	echo '<div class="content">';
	//ouverture du formulaire post des pages
	echo "<form method=\"POST\" role=\"form\" name=\"formulaire\" id=\"formulaire\" class=\"form-horizontal\">";
	echo "<input type=\"hidden\" name=\"dcnx\" id=\"dcnx\" value=\"\">";
	echo "<br>";
	if (DEMO){
		echo '<p style="color: red;" align="right"><b>MODE DEMO';
		$msg_admin="Le(s) personnel(s) de(s) unité(s) <b>".(is_array(ADMIN)?implode(', ',ADMIN):ADMIN)."</b> sont Super Admin de l'application";		
		echo "&nbsp;ID à usurper: <input type=\"input\"  id=\"nigend_fuser\" name=\"nigend_fuser\" value=\"".(isset($_SESSION[NAME]['nigend'])?$_SESSION[NAME]['nigend']:'')."\">";
		echo '&nbsp;<input type="submit" id="valid_fuser" name="valid_fuser" value="OK" class="btn btn-danger"></b><br>';
		echo $msg_admin;
		echo '</p>';
	}
	

//	echo "<div class=\"form-group\">";
//echo "<div class=\"clearfix visible-xs-block\"></div><div class=\"col-xs-12 col-sm-12\">";

}


//fonction pour l'affichage des menus
function menu3(){
	global $name,$menu_name,$menu_icon;//939ef9s

	echo  '<input type="checkbox" id="check"/>';
	
	echo '<label for="check" style="color: #ffffff;">
  <svg viewBox="0 0 30 30" width="30" height="30">
    <path id="one" d="M4 10h22M4" stroke="#fff" stroke-width="2" stroke-linecap="round"></path>
    <path id="two" d="M4 20h22M4" stroke="#fff" stroke-width="2" stroke-linecap="round"></path>
  </svg> Menus
</label>
<aside>';
	
	echo '
  <div class="top">
    <h2>';
	if (isset($_SESSION[NAME]['nigend'])){
		$javascript='document.getElementById("dcnx").value="dcnx";formulaire.submit();';
		echo "<img src='img/dcxn.png' style='width:10%' align=right onclick='".$javascript."'>";
	}
    echo '</h2>';	
	if (isset($menu_name)){
		foreach($menu_name as $k=>$v){
			echo '<ul>
			<h5 style="color: #ffffff;">'.$v.'</h5>';
			if (isset($_SESSION[NAME]['sous_menu'][$k])){
				echo '<ul>';
				foreach ($_SESSION[NAME]['sous_menu'][$k] as $k1=>$v1){
					echo '<li><a href="?p='.$k.'&option='.$k1.'" style="color: #ffffff;">'.$v1.'</a></li>';				
				}
				echo '</ul>';
			}
			echo '		</ul>';		
		}
	}
 echo ' </div>
  <div class="bottom">';
 echo '<br>';
 echo '<p style="color: #ffffff;">'.(isset($_SESSION[NAME]['displayname'])?$_SESSION[NAME]['displayname']:'');


 //cas d'un personnel détaché
 if (isset($_SESSION[NAME]['codeUniteAff']) && isset($_SESSION[NAME]['codeUnite']) && $_SESSION[NAME]['codeUniteAff']!=$_SESSION[NAME]['codeUnite']){
 	echo '<font color=red>Détaché : <b>'.$_SESSION[NAME]['departmentNumber'].'</b></font><br>'; 	
 }

 echo '<br>'.NAME.' '.VERSION;
 echo " <i>(Profil : ".(isset($_SESSION[NAME]['profil'])?$_SESSION[NAME]['profil']:'NON AUTORISE').")</i>";
 echo '</p>';
 echo'   <p style="color: #ffffff;">🄯 2020-'.date("Y").' Copyleft by <img src="img/logo.png" title="Rone"></p> 
  </div>
</aside>';
 
 
};

//fonction pour afficher des messages à l'utilisateur
//$txt => texte à afficher
//$type => info : 0
//         danger:1
//         succes:2
//         warning:3
function msg($txt,$type=0,$class_msg_aff=true){
    switch ($type){
        case 0:
            $class="alert alert-info";
            $class_msg="Info : ";
            break;
        case 1:
            $class="alert alert-danger";
            $class_msg="Erreur! ";
            break;
        case 2:
            $class="alert alert-success";
            $class_msg="Succès! ";
            break;
        case 3:
            $class="alert alert-warning";
            $class_msg="Attention! ";
            break;
        case 4:
        
    }
  
        echo "<div class=\"".$class."\" style=\"border-radius: 10px;\">";
        if ($class_msg_aff)
            echo "<strong>".$class_msg."</strong> ";
         echo $txt."
              </div>";
	return $type;
    
    
}
//fermeture de la page
function pied_page(){
	echo '';
    echo "<div class='footer'></div>
    		<input type='hidden' name='token' value='".(isset($_SESSION[NAME]['token'])?$_SESSION[NAME]['token']:'')."'>
    		</form>   	
		</article>";
   echo "</body>
    </html>";    
    
}

//Connexion a la base de données
//Retourne un objet PDO
function dbconnect($directory=false) {
        try {
        	$url=array();
        	//on crée le nom lié à l'url pour un futur fichier de conf
        	$url=explode('/',$_SERVER['REQUEST_URI']);
        	$nb_values=count($url);
        	if ($directory){
        		$racine =$nb_values-3;
        		$file_conf=$directory;
        	}else{
        		$racine =$nb_values-2;
        		$file_conf='require/conf/';
        	}
        	if (isset($url[$racine])){
        		$fichier_conf=$url[$racine];
        		if (!file_exists($file_conf.$url[$racine].'_ini.php'))
        			$fichier_conf="default";
        	}
        	if (isset($fichier_conf)){
        		require_once('conf/'.$fichier_conf.'_ini.php');
        		$_SESSION['FILE_CONF']=$fichier_conf;
        		
        	}
            $pdo_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
            $bdd = new PDO('mysql:host='.HOST.';dbname='.DBNAME.'', LOGIN, MDP, $pdo_options);
            $bdd->exec("SET CHARACTER SET utf8");
        } catch (Exception $e) {
        	//en mode démo, on peut se passer de bdd
        	if (!DEMO)
        		return FALSE;
          	 	//die('Erreur de connexion à la base de données: '.$e->getMessage());// En cas d'erreur précédemment, on affiche un message et on arrête tout
        	
        }
        
        if (isset($bdd))
      	  return $bdd;   
        return false;
    
}


function tableau($table,$field,$show=array(),$height="460",$data_pagination="true",$nosortable=array()){
    $html_table= ' <table id="table"
        data-toggle="table"
        data-toolbar="#toolbar"
        data-height="'.$height.'"$status
        data-click-to-select="true"
        data-pagination="'.$data_pagination.'"
		data-page-list="[10,20,50,100,all]"
        data-search="true"
        data-show-export="true"
        data-show-refresh="false"
        data-show-toggle="false"
        data-show-columns="false"
        data-url="'.$table;
    $html_table .= '"><thead><tr>';
    if ($show == array())
        $html_table .= '<th data-field="state" data-checkbox="true"></th>';
    foreach ($field as $k=>$v){
        $html_table .= '<th data-field="'.$k.'" data-align="center" data-sortable="true"';
        if ($show != array()){
            if (isset($show[$k]))
                $html_table .= 'data-visible="true"';
            else
                $html_table .= 'data-visible="false"';
        }
        $html_table .= '>'.$v.'</th>';
    }
    $html_table .= '</tr>
        </thead>
        </table>';
    
    return $html_table;
    
    
    
}






function html_cadre($nom_cadre,$data){
    $cadre='<div class="card" style="border-radius: 15px;">
         <div class="card-header" style="background-color:#d3c9c9;border-radius: 15px 15px 0px 0px;" align=center><b>'.$nom_cadre.'</b></div>
         <div class="card-body" style="background-color:transparent;">';
    $cadre.=$data;
    
    echo $cadre."</div></div>";
}


function html_multi_fields($nom,$id,$type,$placeholder,$readonly=false){
	//     $data_tir .= html_3_fields(?p=1'H+L :',array('h','<span class="glyphicon glyphicon-plus"></span>','l'),
	//array('text','glyphicon','text'),array('H en cm','glyphicon-plus','L en cm'));
	global $_POST;
	$nb_field=count($id);
	$taille_grid = 12/$nb_field;
	// echo "<br><br>".$taille_grid."<br><br>";
	//on laisse toujours la colonne du libellé
	$champ = "<div class=\"form-group\">
                <div class=\"col-xs-4 col-sm-4\">
                      <label>".$nom."</label>
                 </div>
              <div class=\"col-xs-4 col-sm-4\">";
	$champ .= " <div class=\"row\">";
	foreach($id as $k=>$v){

		$champ .= "<div class=\"col-lg-".$taille_grid."\">";
		if ($type[$k] == 'glyphicon'){
			$champ .= $v;
		}elseif ($type[$k] == "toggle"){
			$disabled ="";
			if ($readonly)
				$disabled = "disabled";
				if (isset($_POST[$v]) and $_POST[$v] == 'on')
					$checked = "checked";
					else
						$checked = "";
						$champ .= "<input ".$disabled." name=\"".$v."\"  id=\"".$v."\" data-toggle=\"toggle\" data-on=\"OUI\"
                            data-onstyle=\"success\" data-off=\"NON\"  data-style=\"ios\" type=\"checkbox\" ".$checked.">";
						$champ .= "";

		}else{
			$champ .= "<input type=\".$type[$k].\" class=\"form-control\" id=\"".$v."\" name=\"".$v."\" value=\"".(isset($_POST[$v])?$_POST[$v]:'')."\"
                    placeholder=\"".$placeholder[$k]."\"";
			if ($readonly)
				$champ .= "readonly=\"readonly\"";
				$champ .= ">";
		}
		$champ .= "</div>";
	}
	$champ .= "</div>
                </div>
            <div class=\"col-xs-4 col-sm-4\">
            </div>";
	$champ .= "</div>";
	return $champ;

}



function html_champ($nom,$id,$type,$data_list=array(),$admin="",$readonly=false){
    global $_POST,$glyphicon,$placeholder;
    if ($readonly && $type != 'select' && $type !='maps' && $type != 'etoiles' && $type != 'textarea')
        $type='text_readonly';
    $reload=substr($id,0,6);
    if (!isset($placeholder) || $placeholder == array())
    	$placeholder=array('email'=>'Enter email','password'=>'Entrer mot de passe');
    $champ = "<div class=\"form-group\">
                      <label>".$nom."</label>";
    if ($type == "checkbox"){
    	foreach ($data_list as $k=>$v){
    		$champ .= '<div class="checkbox">
	    	<label><input type="checkbox" id="'.$id.'[]" name="'.$id.'[]" value="'.$k.'" '.(isset($_POST[$id]) && in_array($k,$_POST[$id])?'checked':'').' >'.$v.'</label>
	    	</div>';
    	}    	
    }elseif ($type == "radio"){
    	foreach ($data_list as $k=>$v){
	    	$champ .= '<div class="radio">
	    	<label><input type="radio" id="'.$id.'" name="'.$id.'" value="'.$k.'" '.((isset($_POST[$id]) && $_POST[$id] == $k)?'checked':'').' >'.$v.'</label>
	    	</div>';
    	}    	
    }elseif (substr($type,0,6) == "select"){
    	$id_post=$id;
    	$max_select=explode('_',$type);
    	if (isset($max_select[1]) and is_numeric($max_select[1])){
    		$max_check=$max_select[1];
    		$search="data-live-search=\"true\"";
    	}else{
    		$max_check=1;
    		$search="";    		
    	}
    	if ($max_check>1){
    		$id=$id.'[]';    		
    	}
    	
    	
        $champ .= "<select class=\"selectpicker\"
                     data-width=\"100%\"".($max_check>1?" multiple data-max-options=\"".$max_check."\" ".$search:"")."\"                     		
                     id=\"".$id."\"
                     name=\"".$id."\" ".(($reload == 'reload')?'onchange="this.form.submit()"':'');
        if ($readonly)
            $champ .= " disabled";
         $champ .= ">";
        foreach ($data_list as $k=>$v){
            $champ .= "<optgroup label=\"\" data-subtext=\"".$k."\">";
            foreach ($v as $k1=>$v1){
            	if (isset($_POST[$id_post]) and is_array($_POST[$id_post]) and in_array($k1,$_POST[$id_post]))
            		$champ .= "<option selected";
            	else 
                	$champ .= "<option ".((isset($_POST[$id_post]) && $_POST[$id_post] == $k1)?'selected':'');
            //	$champ .= " value='".$k1."' ".(isset($glyphicon[$k1])?"data-icon=\"glyphicon ".$glyphicon[$k1]."\"":"").">".$v1."</option>";
                	$champ .= " value='".$k1."' ".(isset($glyphicon[$k1])?"data-content=\"".$glyphicon[$k1]." - ".$v1."\"":"").">".$v1."</option>";
            	//	$champ .= " value='".$k1."' data-content=\"<img src='leaflet/images/marker-icon-blue.png' > - ".$v1."\")></option>";
            }
            $champ .= "</optgroup>";
        }
        $champ .= "</select>";
       
        if ($admin != "" and !$readonly)
            $champ .= " <button type=\"button\" class=\"btn btn-success btn-sm\" data-toggle=\"modal\" data-target=\"#myModal_".$id."\">
          <span class=\"glyphicon glyphicon-cog\"></span>
        </button>";
       
    }elseif ($type == "text_disable"){
            $champ .= "<input type=\"".$type."\" class=\"form-control\" id=\"\" name=\"\" value=\"".(isset($_POST[$id])?$_POST[$id]:'')."\" disabled>";
            $champ .= "<input type=\"hidden\" class=\"form-control\" id=\"".$id."\" name=\"".$id."\" value=\"".(isset($_POST[$id])?$_POST[$id]:'')."\">";
    }elseif ($type == "text_readonly"){
        $champ .= "<input type=\"".$type."\" class=\"form-control\" id=\"".$id."\" name=\"".$id."\" value=\"".(isset($_POST[$id])?$_POST[$id]:'')."\" readonly=\"readonly\">";
    }elseif ($type == "maps"){
    	
        if (!isset($data_list['width']))
            $data_list['width'] = '500px';
        if (!isset($data_list['height']))
            $data_list['height'] = '850px';
        $champ = "<center>$nom<div id=\"$id\" style=\"width: ".$data_list['width']."; height: ".$data_list['height'].";\"></div></center>";//<div id=\"googleMap\" style=\"width:100%;height:400px;\"></div>";
        $champ .= "";
        $champ .= "<script>";
        if (isset($_POST['coordonnees_'.$id]) and $_POST['coordonnees_'.$id] != ""){
            $coordonnees=explode(";",$_POST['coordonnees_'.$id]);
            $zoom=20;
          //  $champ .= "var mymap$id = L.map('$id').setView([$coordonnees[0], $coordonnees[1]], 20);";
        }else{
        	if (defined('MAPS_CENTER')){
        		$coordonnees=explode(",",MAPS_CENTER);
        		
        		if (!isset($coordonnees[1]))
        			$coordonnees=array('47.044','2.7136');
        		else 
        			$zoom=9;
        	}else        
        		$coordonnees=array('47.044','2.7136');
            	//si pas de centrage de carte défini, on centre sur la brigade
            	//$champ .= "var mymap$id = L.map('$id').setView([47.044, 2.7136], 5);";
        }
        if (!isset($zoom))
        	$zoom=5;
        $champ .= "var mymap$id = L.map('$id').setView([$coordonnees[0], $coordonnees[1]], $zoom);";
        
        $champ .= 'L.tileLayer(\'http://osm.psi.minint.fr/{z}/{x}/{y}.png\', {
	attribution: \'<a href="http://osm.psi.minint.fr/">OSM MI</a>, <a href="http://silorgcor.local.gendarmerie.fr/redaction-bleu3/121-ccc-cellule-de-coordination-des-cybermenaces">CCC RGCOR</a>\',
	detectRetina: true, reuseTiles: true
	}).addTo(mymap'.$id.');';
                    
        $champ .= " var popup$id = L.popup();
	                var new_event_marker$id;";
        if (!$readonly){
            $champ .= "mymap$id.on('click', function(e) {
                    	 if(typeof(new_event_marker$id)==='undefined')
                    	 {
                    	  new_event_marker$id = new L.marker(e.latlng,{ draggable: true});
                    	  new_event_marker$id.addTo(mymap$id);        
                    	 
                    	 }
                    	 else 
                    	 {
                    	  new_event_marker$id.setLatLng(e.latlng);         
                    	 }
                    	 document.getElementById('coordonnees_$id').value=e.latlng.lat + ';' + e.latlng.lng;
                    	})
                    ";
        }
        if (isset($_POST['coordonnees_'.$id]) and $_POST['coordonnees_'.$id] != ""){
            $coordonnees=explode(";",$_POST['coordonnees_'.$id]);
            $champ .= "var new_event_marker$id = L.marker([$coordonnees[0], $coordonnees[1]]";
            if (isset($_POST['ICON_'.$id]))
            	$champ .=",{icon: ".$_POST['ICON_'.$id]."}";
            $champ .= ").addTo(mymap$id)";
            if (isset($_POST['POPUP_'.$id]))
            	$champ .=".bindPopup(\"<center>".$_POST['POPUP_'.$id]."</center>\");";
            $champ .= ";";
            
          /*  $champ .= "var myLatLng = { lat: $coordonnees[0], lng: $coordonnees[1] };";
            $champ .= "addMarker(myLatLng, map);";*/
        }
        
        $champ .= "</script><br>";
        return $champ;
    }elseif ($type == "carousel"){
        // Caroussel photo
        $champ .= '<div id="myCarousel" class="carousel slide" data-ride="carousel" data-interval="false" style="margin: 0 auto">
  <!-- Indicators -->
  <ol class="carousel-indicators">';
        $nb=count($data_list);
        $i=0;
        while ($i<$nb){
            $champ .= '<li data-target="#myCarousel" data-slide-to="'.$i.'" ';
            if ($i == 0)
                $champ .= 'class="active"';
            $champ .= '></li>';
            $i++;
        }
        $champ .= '
  </ol>
    <div class="carousel-inner">
            ';
        $i=0;
        foreach ($data_list as $k=>$v){
            if ($i==0)
                $champ .= ' <div class="item active">';
            else
                $champ .= '<div class="item">';
            $champ .= '<img src="'.$k.'" alt="'.$v.'">';
            $champ .= '</div>';    
            $i++;
        }
        $champ .= '</div>
                    <!-- Left and right controls -->
          <a class="left carousel-control" href="#myCarousel" data-slide="prev">
            <span class="glyphicon glyphicon-chevron-left"></span>
            <span class="sr-only">Previous</span>
          </a>
          <a class="right carousel-control" href="#myCarousel" data-slide="next">
            <span class="glyphicon glyphicon-chevron-right"></span>
            <span class="sr-only">Next</span>
          </a>
        </div> </div>
                    <div class=\"col-xs-4 col-sm-4\">
                    </div>';
        
    }elseif ($type == 'image'){
        foreach ($data_list as $k=>$v){
            $champ .= '<div class="item"><img src="'.$k.'" alt="'.$v.'" class="img-responsive"></div>';
        }
      
    }elseif ($type == 'textarea'){
    		if (!$readonly){
    			$champ .= "<textarea class=\"form-control\" id=\"".$id."\" name=\"".$id."\" placeholder=\"\" rows=\"".(isset($_POST[$id])?3+strlen($_POST[$id])/200:'3')."\">".(isset($_POST[$id])?$_POST[$id]:'')."</textarea>";        
	    		$champ .= "<script>
	    		ClassicEditor
	    		.create( document.querySelector( '#".$id."' ), {
	    			toolbar: ['heading', '|','bold', 'italic', '|','link', '|','bulletedList', 'numberedList', '|','insertTable', '|','outdent', 'indent', '|','blockQuote', '|','undo', 'redo']
	    		} )
	    		.then( editor => {
	    			window.editor = editor;
	    		} )
	    		.catch( err => {
	    			console.error( err.stack );
	    		} );
	    			</script>";
    		}else{
    			$class="alert alert-info";
    			$champ .= "<div class=\"".$class."\" style=\"border-radius: 10px;\">".$_POST[$id]."</div>";    			
    		}
    		
    
    }elseif ($type == 'download'){
        $champ .= "
        <input type=\"file\" name=\"inputFile\" id=\"exampleInputFile\">
        <p class=\"help-block\">Vous pouvez placer ici tous les fichiers liés à ce tir</p>";        
    }elseif ($type == 'date'){
    	$champ .= "<input type=\"".$type."\" class=\"form-control\" id=\"".$id."\" name=\"".$id."\" value=\"".(isset($_POST[$id])?$_POST[$id]:'')."\"
                    placeholder=\"".(isset($placeholder[$type])?$placeholder[$type]:'')."\">";
       
    }elseif ($type == 'number'){
    	$champ .= "<input type=\"text\" class=\"form-control\" id=\"".$id."\" name=\"".$id."\" value=\"".(isset($_POST[$id])?$_POST[$id]:'')."\"
                    placeholder=\"".(isset($placeholder[$type])?$placeholder[$type]:'')."\" onkeypress=\"return validate(event)\">";
    	
    }else{
    
        $champ .= "<input type=\"".$type."\" class=\"form-control\" id=\"".$id."\" name=\"".$id."\" value=\"".(isset($_POST[$id])?$_POST[$id]:'')."\" 
                    placeholder=\"";
        if (isset($placeholder[$id]))
        	$champ .= $placeholder[$id];
        elseif (isset($placeholder[$type]))
        	$champ .= $placeholder[$type];
        $champ .=		"\">";
        
        
    }
    $champ .= "</div>";
    return $champ;
}
?>

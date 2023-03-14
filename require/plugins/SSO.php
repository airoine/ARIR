<?php
function sso_mail($titre,$txt_html,$to,$cc){
	return SSO::mail($titre,$txt_html,
				array(
						array('type' => 'To', 'mail' => $to,true),
						array('type' => 'Cc', 'mail' => $cc,true),
				),true
				);
}



function cnx_sso(){
	global $bdd;
	if (!isset($_SESSION[NAME]['profil']) or !in_array($_SESSION[NAME]['profil'], array('visualisation','utilisateur','administrateur','sadministrateur'))){
		$data = array('sn'=>'sn','givenName'=>'givenName','login'=>'uid','nigend'=>'nigend','mail'=>'mail',
				'identifiant'=>'unite','departmentUID'=>'departmentUID','codeUnite'=>'codeUnite','dptUnite'=>'dptUnite','title'=>'title',
				'employeeType'=>'employeeType','displayname'=>'displayname','anonymousId'=>'anonymousId');
		$_SESSION[NAME]['type_cnx']='SSO_LOCAL';

		//Pour tous les champs retournés qui nous intéressent
		//on met les valeurs en $_SESSION[NAME]
		foreach ($data as $k=>$v){
			//on récupère les valeurs dans l'objet retourné
			if (!isset($_SERVER['HTTP_NIGEND']) && SSO::user() != null){
				//si la variable existe dans le SSO
				if (isset(SSO::user()->$v))
					$_SESSION[NAME][$k]=SSO::user()->$v;
				else
					$_SESSION[NAME][$k]="";
			}
		}
		if(!isset($_SESSION[NAME]['login'])||!isset($_SESSION[NAME]['identifiant'])){
			return __LINE__;
		} else {
			profil_application();
		}
		log_action('Connexion');
		return 'OK';
	}
}
class SSO {

	const COOKIE_NAME   = "lemonlocal";
	const COOKIE_DOMAIN = ".local.xxx.fr";
	const PORTAL_URL    = "https://auth2.local.xxx.fr/getcookie.pl";
	const REST_URL      = "https://auth2.local.xxx.fr/getuser.pl";
	const MAIL_URL      = "https://auth2.local.xxx.fr/mail";
	const GRP_URL       = "https://auth2.local.xxx.fr/getgroups.pl";

	static public function authenticate() {
		$arrContextOptions=array(
				"ssl"=>array(
						"verify_peer"=>false,
						"verify_peer_name"=>false,
				),
		);
		if (isset($_COOKIE[self::COOKIE_NAME])) {
			$url = self::REST_URL."?id=".$_COOKIE[self::COOKIE_NAME]."&host=".$_SERVER['HTTP_HOST'];
			// supprimer le cookie pour éviter qu'il ne soit détourné par une autre appli dans le même domaine
			setcookie(self::COOKIE_NAME, "", time()-3600, "/", self::COOKIE_DOMAIN);
			if ($json = file_get_contents($url, false, stream_context_create($arrContextOptions))) {
				$_SESSION[NAME]['user'] = json_decode($json);
			} else {
				echo '<html><body>BAD<pre>X '.$url.' X</pre>'.file_get_contents($url, false, stream_context_create($arrContextOptions)).'</body></html>';
			}
		} else {
			self::redirect();
		}
	}

	static private function redirect() {
		$url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		header('Location: '.self::PORTAL_URL.'?url='.base64_encode($url));
		exit;
	}

	static public function user() {
		return $_SESSION[NAME]['user'];
	}

	static public function mail( $subject, $body, $recipients, $throwExceptionIfExpired ) {
		if ( $_SESSION[NAME]['user']->mailTokenExp < time() )
			if (isset($throwExceptionIfExpired) && $throwExceptionIfExpired)
				throw new Exception("Jeton caduc");
				else
					self::authenticate();

					# pour envoyer le jeton dans un en-tête de requête HTTP "MailToken"
					$stream_context = stream_context_create(array("ssl"=>array("verify_peer"=>false,"verify_peer_name"=>false,),'http' => array('header' => 'MailToken: ' . $_SESSION[NAME]['user']->mailToken)));
					$client = new SoapClient(NULL, array(
							'stream_context' => $stream_context,
							'location'       => self::MAIL_URL,
							'uri'            => 'SOAPService/Mail'
					));

					try {
						$result = $client->__soapCall("send", array(
								'subject' => $subject,
								'body'    => $body,
								'recipients' => $recipients
						), NULL);
					} catch (SoapFault $e) {
						throw new Exception($e->getMessage());
					}
	}

	static public function groups($motif ="") {
		$arrContextOptions=array(
				"ssl"=>array(
						"verify_peer"=>false,
						"verify_peer_name"=>false,
				),
		);
		if ( !isset( $_SESSION[NAME]['user']->groups ) ) {
			$opts = array(
					"ssl"=>array(
							"verify_peer"=>false,
							"verify_peer_name"=>false,
					),
					'http'=>array(
							'method'=>"GET",
							'header'=>"mailToken:". $_SESSION[NAME]['user']->mailToken."\r\n"
					)
			);
			#formate les entêtes de la requêtes
			$context = stream_context_create($opts);
			$url = self::GRP_URL;
			if ($json = file_get_contents($url, false, $context)){
				$_SESSION[NAME]['user']->groups = json_decode($json);}
				else{
					print '<html><body><pre>'.$url."\n"."mailToken:". $_SESSION[NAME]['user']->mailToken.'</pre></body></html>';
				}
		}
		if ($motif) {
			return preg_grep("/$motif/", $_SESSION[NAME]['user']->groups);
		} else {
			return $_SESSION[NAME]['user']->groups;
		}

	}
}

if (!isset($_SESSION[NAME]['user']))
	SSO::authenticate();

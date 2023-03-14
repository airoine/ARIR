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

function search_ldap_session($data){
	$fields_ref = array('codeUnite'=>'codeunite','unite'=>'ou','nigend'=>'employeenumber','uid'=>'displayname','mail'=>'mail','id'=>'employeenumber','displayname'=>'displayname','departmentUID'=>'departmentuid');
	foreach ($fields_ref as $k=>$v){
		if (isset($data[0][$v][0])){
			$_SESSION[NAME][$k]=$data[0][$v][0];			
		}		
	}	
}



function ldap_test($ds){
	$ds = ldap_connect(DSLDAP);
	if (!$ds){
		echo "Problème de connexion au LDAP";
		return false;
	}
	return true;
}

function cnx_user($rootdn,$rootpw){
	$user="uid=".$rootdn.",".DNPERSONNESLDAP;
	$pass=$rootpw;
	$ds = ldap_connect(DSLDAP);
	ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ds , LDAP_OPT_REFERRALS, 0);
	if ($ds)
	{
		$ldapbind = @ldap_bind($ds, $user, $pass);
		if ($ldapbind){
			ldap_close($ds);
			return true;
		}
		ldap_close($ds);
		return false;
	}
}

function ldap_info_user($search,$filter='employeenumber'){
	$ds = ldap_connect(DSLDAP);
	$filter .= '='.$search;
	$sr2 = @ldap_search($ds,DNPERSONNESLDAP,$filter);
	if (!ldap_test($sr2)){
		return false;
	}
	$ldap_personne = @ldap_get_entries($ds,$sr2);
	return $ldap_personne;
}

function ldap_info_cu($cu){
	$ds = ldap_connect(DSLDAP);
	$filter = 'codeunite='.$cu;
	$sr2 = @ldap_search($ds,DNSERVICELDAP,$filter);
	if (!ldap_test($sr2)){
		return false;
	}
	$ldap_unite = ldap_get_entries($ds,$sr2);
	return $ldap_unite;
	if (isset($ldap_unite[0]))
		return array('NOM'=>$ldap_unite[0]['displayname'][0],'CU'=>$cu);
		else
			return false;
}


?>
<?php
	
	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	
	$login = $_REQUEST["login"];
	$password = $_REQUEST["password"];
	//echo "login<pre>"; print_r($login); echo "</pre>";
	//echo "password<pre>"; print_r($password); echo "</pre>";
	

	$objTmp = CBinitObject("Address");
	//echo "objTmp<pre>"; print_r($objTmp); echo "</pre>";
	
	
	$address_id = "";
	
	if ( $login != "" AND $password != "" ) {
		
		//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
				
		$sql  = "SELECT address_uid, customer_number FROM capps_address WHERE login = '".mysqli_real_escape_string($objTmp->objDatabase->intDBHandler,$login)."' AND password = '".mysqli_real_escape_string($objTmp->objDatabase->intDBHandler,$password)."' AND active = 1";
		$result = $objTmp->get($sql);
		//echo "$sql<pre>"; print_r($result); echo "</pre>"; 
		$address_id = $result[0]['address_uid'];
		
		//
		// fallback: user and password form post are md5 encoded
		//
		if ( $address_id == "" ) {
			
					$sql  = "SELECT address_uid, customer_number, login, password FROM capps_address WHERE active = 1";
			$result = $objTmp->get($sql);
			if ( is_array($result) && count($result) >= 1 ) {
				foreach ( $result as $rR=>$vR ) {
					
					if ( strtolower(md5($vR['login'])) == strtolower($login) && strtolower(md5($vR['password'])) == strtolower($password) ) $address_id = $vR['address_id'];
					
				}
			}

		}
		
		
		if($address_id != ""){
				
				
				// TODO vika, pscode und basedir in Session reinnehmen ? so ist bei mehreren Anwendungen möglich eingeloggt zu bleiben
				
				// set session
				$_SESSION['verfified'] = 'true';
				//$_SESSION['address_id'] = $address_id;
				// vika 2008_11_28 address_id durch aid ersetzt
				$_SESSION['user_id'] = $address_id;
// 				$_SESSION['customer_number'] = $result[0]['customer_number'];
				
/*
				// set groups
				$objTmp = connectClass('caddress/Address.class.php',$address_id);
				$arrGroups = $objTmp->getAddressgroupsOfAddress();
				//echo "$address_id<pre>arrGroups"; print_r($arrGroups); echo "</pre>";
				$_SESSION['user_groups'] = $arrGroups;
				
				// update lastlogin
				$arrSave = array();
				$_SESSION['user_lastlogin'] = $objTmp->getAttribute('lastlogin');
				$arrSave['lastlogin'] = time();
				// token
				$uniqid = uniqid();
				$arrSave['data_login_token'] = $uniqid;
				$arrSave['data_login_time'] = time();
				$arrSave['data_login_ip'] = $_SERVER['REMOTE_ADDR'];
				$s = $this->saveContentUpdate($address_id,$arrSave);
				
				// write event
				// set groups
				$objE = connectClass('caddress/AddressEvent.class.php');
				$arrSave = array();
				$arrSave['date'] = time();
				$arrSave['address_id'] = $address_id;
				$arrSave['system_user_id'] = "1";
				$arrSave['description'] = "Eingeloggt!";
				$s = $objE->saveContentNew($arrSave);
				
				// set onject
				$objA = $objTmp;
*/

				echo "success";
			}
			else{
				$_SESSION['verfified'] = "";
				//$_SESSION['address_id'] = "";
				// vika 2008_11_28 address_id durch aid ersetzt
				$_SESSION['user_id'] = "";
// 				$_SESSION['customer_number'] = "";
// 				$_SESSION['user_groups'] = "";
// 				return false;
			}
				


	}

?>
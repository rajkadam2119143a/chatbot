<?php

require_once get_stylesheet_directory() . '/custom/api/class-api-manager.php'; 

class apiAuthentication{

  public function curlrequest($username, $password) {

  try{
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => AUTHENTICATION_API,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_HEADER => 1,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      // CURLOPT_POSTFIELDS => "emailId=sachin.kale%40timesgroup.com&password=Password%401",
      CURLOPT_POSTFIELDS => "emailId=".$username."&password=".$password,
      CURLOPT_HTTPHEADER => array(
        "appnName: ZING",
        "authKey: 1lxVJZ/89KCNhoQzfds+URrveGLhwPcGap2w5F6WeIMPVNU8i6a/tldiWHQOfYnNn8xs8Y",
        "Content-Type: application/x-www-form-urlencoded",
        "Origin: " .API_ORIGIN_NAME,
      ),
    ));
    $response = curl_exec($curl); 

	  
    if (curl_errno($curl)) {
      $error_msg = curl_error($curl);
      error_log("Response1 curl logged error-----");
      error_log($error_msg);
  	}

    //error_log("Response1 logged error-----");
    //error_log($response);

    $header_size1 = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header1 = substr($response, 0, $header_size1);
    $body1 = substr($response, $header_size1);
    // print_r("header size -----------------".$header_size1);
    // print_r("<br>");
    // print_r("-----------------------------------------------");
    // print_r("<br>");
    // print_r("header-----------------".$header1);
    // print_r("<br>");
    // print_r("-----------------------------------------------");
    // print_r("<br>");
    // print_r("body------------".$body1); 
    // print_r("<br>");
    // print_r("-----------------------------------------------");
    // print_r("<br>");die;
    curl_close($curl); 

    // preg_match('/i_s=.*";/', $response, $matches);
    preg_match('/i_s=.*;/', $header1, $matches);
    preg_match('/c_in=.*;/', $header1, $record);
    preg_match('/Expires=.*GMT/', $header1, $expiry);
    preg_match('/JSESSIONID=.*;/', $header1, $jsessionid);
    //print_r($expiry);die;
    /*$cookie_is=explode(";", $matches[0]);
    $cookiei_s=explode("=", $cookie_is[0]);
    $i_s= $cookiei_s[1];

    $i_s_val = substr($cookie_is[0], strpos($cookie_is[0], "=") + 1);*/

    preg_match('/Authorizationkey=.*=/', $header1, $authorizationkey);

    list($headers, $content) = explode("\r\n\r\n",$header1,2);
    $authorizationkey = '';
    foreach (explode("\r\n",$headers) as $hdr) {
      if (strstr($hdr, "Authorizationkey:") !== false) {
        $authorizationkey = $hdr;
      }
    }

    $authkey_val = explode(":", $authorizationkey);
    $authorization2 = $authkey_val[1];

    $i_s_val = trim($authorization2);

    // print_r("<br>");
    // print_r("is-------------------".$i_s_val);
    // print_r("<br>");
    // print_r("----------------------------");
    // print_r("<br>");die;
    // print_r($body1);
    $cookie_cin=explode(";", $record[0]);
    $cookiec_in=explode("=", $cookie_cin[0]);
    $c_in= $cookiec_in[1];
    // print_r("cin------------".$c_in);
    // print_r("<br>");
    // print_r("----------------------------");
    // print_r("<br>");
     //print_r($expiry);die;
    // print_r("<br>");
    // print_r($matches);
    // print_r("<br>");
    // print_r("i_s-----------------------------------".$i_s_expiry);
    // print_r("<br>");
    // print_r($record);
    // print_r("<br>");
    // print_r("c_in-----------------------------------".$c_in_expiry);
    // print_r("<br>");
    // die;
    $cookie_expiry = explode("=", $expiry[0]);
    $expiry = $cookie_expiry[1];

    $cookie_jsessionid=explode(";", $jsessionid[0]);
    $cookiejsessionid=explode("=", $cookie_jsessionid[0]);
    $jsessionid_val= $cookiejsessionid[1];

    $result = json_decode($body1, true);
    

    if( !empty($result) ){

      foreach ($result as $val) {
        $user_info = array();
        
        if(isset($jsessionid_val)) {
          $user_info['jsessionid_val'] = $jsessionid_val;
        }

    //Message
        if (!empty($val['message'])) {
          error_log("UsernamePWD API Response : message attribute value : ".$val['message']." for user = ".$username);
          $user_info['message'] = $val['message'];
        }

    //First Name    
        if (!empty($val['data']['firstName'])) {
          $user_info['first_name'] = $val['data']['firstName'];
        }else{
          error_log("UsernamePWD API Response : data.firstname  is empty for user = ".$username);
        }

    //Last Name
        if (!empty($val['data']['surname'])) {
          $user_info['last_name'] = $val['data']['surname'];
        }else{
          error_log("UsernamePWD API Response : data.surname  is empty for user = ".$username);
        }

    //Email
        if (!empty($val['data']['emailId'])) {
          $user_info['email'] = $val['data']['emailId'];  
        }else{
          error_log("UsernamePWD API Response : data.emailId  is empty for user = ".$username);
        }

    //Branch
        if (!empty($val['data']['branchName'])) {
          $user_info['branch'] = $val['data']['branchName'];   
        }else{
          error_log("UsernamePWD API Response : data.branchName  is empty for user = ".$username);
        }

    //Location
        if (!empty($val['data']['locationName'])) {
          $user_info['location'] = $val['data']['locationName'];  
        }else{
          error_log("UsernamePWD API Response : data.locationName  is empty for user = ".$username);
        }

    //Designation 
        if (!empty($val['data']['designation'])) {
          $user_info['designation'] = $val['data']['designation'];   
        }else{
          error_log("UsernamePWD API Response : data.designation  is empty for user = ".$username);
        }

    //Department    
        if (!empty($val['data']['deptGroupName'])) {
          $user_info['department'] = $val['data']['deptGroupName'];
        }else{
          error_log("UsernamePWD API Response : data.deptGroupName  is empty for user = ".$username);
        }

    //Sap
        if (!empty($val['data']['sapNumber'])) {
          $user_info['sap'] = $val['data']['sapNumber'];
        }else{
          error_log("UsernamePWD API Response : data.sapNumber  is empty for user = ".$username);
        }

    //DeptGroup
        if(!empty($val['data']['deptGroup'])) {
          $user_info['deptGroup'] = $val['data']['deptGroup'];
        }else {
          error_log("UsernamePWD API Response : data.deptGroup is empty for user = ".$username);
        }

        //Cookie i_s
        //if (!empty($i_s)) {
        if (!empty($i_s_val)) {
          $user_info['i_s'] = $i_s_val;
          $user_info['i_s_expiry'] = strtotime($expiry);
          //$user_info['i_s_expiry'] = '1612433600';
        }else{
           error_log("UsernamePWD API Response : i_s  is empty for user = ".$username);
        }

        //Cookie c_in
        if (!empty($c_in)) {
          $user_info['c_in'] = $c_in;
          $user_info['c_in_expiry'] = strtotime($expiry);
          //$user_info['c_in_expiry'] = '1612433600';
        }else{
           error_log("UsernamePWD API Response : c_in  is empty for user = ".$username);
        }

        // try{
        //   $apiManager = new apiManager();
        //   $api_manager_response = $apiManager->curlrequest($i_s_val,$jsessionid_val);

        //   if (!empty($api_manager_response)) {
        //     $user_info['manager_email'] = $api_manager_response;
        //     error_log("Response received from Authentication API");
        //   }else {
        //     error_log("Manager API error from Authentication API");
        //   }
        // }catch(Exception $e){
        //     error_log("Manager API error");
        // }

        return $user_info;

      }
    }else {
        error_log("UsernamePWD API Response :--No data fetched for username".$username);
    }

    }catch (Exception $e){
      error_log("UsernamePWD API Response :--Exception thrown for username".$username."-Exception-".$e->getMessage());
      throw new Exception("Some error");
    }

  }
}

?>
<?php

class apiAuthentication{

  public function curlrequest($username, $password) {
   //print_r($username);die;
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
//         "Origin: https://zing.timesgroup.com"
      "Origin: ".API_ORIGIN_NAME,
	  ),
    ));
    $response = curl_exec($curl); 


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
 //print_r($expiry);die;
    $cookie_is=explode(";", $matches[0]);
    $cookiei_s=explode("=", $cookie_is[0]);
    $i_s= $cookiei_s[1];

    $i_s_val = substr($cookie_is[0], strpos($cookie_is[0], "=") + 1); 
// print_r($body1);
// print_r("<br>");
// print_r("<br>");
// print_r("is-------------------".$i_s_val);
// print_r("<br>");
// print_r("----------------------------");
// print_r("<br>");die;
    $cookie_cin=explode(";", $record[0]);
    $cookiec_in=explode("=", $cookie_cin[0]);
    $c_in= $cookiec_in[1];
// print_r("cin------------".$c_in);
// print_r("<br>");
// print_r("--------------------------------------");
// print_r("<br>");
    //$i_s_expiry= $cookiei_s_expiry[1];
    //$i_s_expiry=1925895883;
// print_r("is expiry------------------".$i_s_expiry);
// print_r("<br>");
// print_r("----------------------------");
// print_r("<br>");
    $cookie_expiry = explode("=", $expiry[0]);
    $expiry = $cookie_expiry[1];
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
    $result = json_decode($body1, true);
    
    if( !empty($result) ){

      foreach ($result as $val) {
        $user_info = array();
        
    //Message
        if (!empty($val['message'])) {
          $user_info['message'] = $val['message'];
        }

    //First Name    
        if (!empty($val['data']['firstName'])) {
          $user_info['first_name'] = $val['data']['firstName'];
        }

    //Last Name
        if (!empty($val['data']['surname'])) {
          $user_info['last_name'] = $val['data']['surname'];
        }

    //Email
        if (!empty($val['data']['emailId'])) {
          $user_info['email'] = $val['data']['emailId'];  
        }

    //Branch
        if (!empty($val['data']['branchName'])) {
          $user_info['branch'] = $val['data']['branchName'];   
        }

    //Location
        if (!empty($val['data']['locationName'])) {
          $user_info['location'] = $val['data']['locationName'];  
        }

    //Designation 
        if (!empty($val['data']['designation'])) {
          $user_info['designation'] = $val['data']['designation'];   
        }

    //Department    
        if (!empty($val['data']['deptGroupName'])) {
          $user_info['department'] = $val['data']['deptGroupName'];
        }

    //Sap
        if (!empty($val['data']['sapNumber'])) {
          $user_info['sap'] = $val['data']['sapNumber'];
        }

        //Cookie i_s
         if (!empty($i_s)) {
          $user_info['i_s'] = $i_s_val;
          $user_info['i_s_expiry'] = strtotime($expiry);
          //$user_info['i_s_expiry'] = '1612433600';
          //print_r($c_in_expiry);die;
        }

        //Cookie c_in
         if (!empty($c_in)) {
          $user_info['c_in'] = $c_in;
          $user_info['c_in_expiry'] = strtotime($expiry);
          //$user_info['c_in_expiry'] = '1612433600';
        }

        return $user_info;

      }
    }
  }
}

?>
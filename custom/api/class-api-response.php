<?php

require_once get_stylesheet_directory() . '/custom/api/class-api-manager.php'; 

class apiResponse{

  public function curlresponse() {

  try{
  $i_s1 = $_COOKIE['i_s'];
  //$c_in1 = $_COOKIE['c_in'];
  // print_r($i_s1);
  // print_r("<br>");
  // print_r($c_in1);
  $i_s2 = stripslashes(trim($i_s1,'"'));
  //$c_in2 = stripslashes(trim($c_in1,'"'));

  $i_s = trim($i_s2,'"');
  //$c_in = trim($c_in2,'"');
  //print_r($i_s);
  // print_r("<br>");
  // print_r($c_in);
  //die;

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => RESPONSE_API,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_HEADER => 1,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_COOKIE => "i_s=".$i_s,
    //CURLOPT_COOKIE => 'i_s="hM/RMtJxyLE1lxVJZ/89KLdpZKLLtSY328kh0AdsEfw=";c_in=true',
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

  curl_close($curl);

  //print_r($response);die;
  $result = json_decode($body1, true);

  preg_match('/JSESSIONID=.*;/', $header1, $jsessionid);

  $cookie_jsessionid=explode(";", $jsessionid[0]);
  $cookiejsessionid=explode("=", $cookie_jsessionid[0]);
  $jsessionid_val= $cookiejsessionid[1];

  //print_r($jsessionid_val);die;

  if( !empty($result) ){

      foreach ($result as $val) {
        $api_response = array();

        if(isset($jsessionid_val)) {
          $api_response['jsessionid_val'] = $jsessionid_val;
        }

    //DeptGroup
        if(!empty($val['data']['deptGroup'])) {
          $api_response['deptGroup'] = $val['data']['deptGroup'];
        }else {
          error_log("Cookie API Response : data.deptGroup is empty for $i_s : ".$i_s." |$c_in".$c_in);
        }
        
    //First Name    
        if (!empty($val['data']['firstName'])) {
          $api_response['first_name'] = $val['data']['firstName'];
        }else{
          error_log("Cookie API Response : data.firstname  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Last Name
        if (!empty($val['data']['surname'])) {
          $api_response['last_name'] = $val['data']['surname'];
        }else{
          error_log("Cookie API Response : data.surname  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Email
        if (!empty($val['data']['emailId'])) {
          $api_response['email'] = $val['data']['emailId'];  
        }else{
          error_log("Cookie API Response : data.emailId  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Branch
        if (!empty($val['data']['branchName'])) {
          $api_response['branch'] = $val['data']['branchName'];   
        }else{
          error_log("Cookie API Response : data.branchName  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Location
        if (!empty($val['data']['locationName'])) {
          $api_response['location'] = $val['data']['locationName'];  
        }else{
          error_log("Cookie API Response : data.locationName  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Designation 
        if (!empty($val['data']['designation'])) {
          $api_response['designation'] = $val['data']['designation'];   
        }else{
          error_log("Cookie API Response : data.designation  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Department    
        if (!empty($val['data']['deptGroupName'])) {
          $api_response['department'] = $val['data']['deptGroupName'];
        }else{
          error_log("Cookie API Response : data.deptGroupName  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

    //Sap
        if (!empty($val['data']['sapNumber'])) {
          $api_response['sap'] = $val['data']['sapNumber'];
        }else{
          error_log("Cookie API Response : data.sapNumber  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

        //Cookie i_s
        if (!empty($i_s)) {
          $api_response['i_s'] = $i_s;
        }else{
          error_log("Cookie API Response : i_s  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }

        //Cookie c_in
        //  if (!empty($c_in)) {
        //   $api_response['c_in'] = $c_in;
        // }else{
        //   error_log("Cookie API Response : c_in  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        // }

        //Response string
        if (!empty($val['status'])) {
          if($val['status'] == 'success') {
              $api_response['resp'] = 'true';
            }else{
              error_log("Cookie API Response : status  is ".$val['status']." for $i_s = ".$i_s." |$c_in".$c_in);
            }
        }else{
          error_log("Cookie API Response : status  is empty for $i_s = ".$i_s." |$c_in".$c_in);
        }
    }


    // try {
    //   $apiManager = new apiManager();
    //   $api_manager_response = $apiManager->curlrequest($i_s,$jsessionid_val);

    //   if (!empty($api_manager_response)) {
    //     $api_response['manager_email'] = $api_manager_response;
    //   }else {
    //      error_log("Manager API error from Reponse API");
    //   }
    // }catch(Exception $e){
    //   error_log("Manager API error");
    // }

    return $api_response;

  }else {
    error_log("Cookie API Response :--No data fetched for i_s".$i_s." and c_in values.".$c_in);
  }

   }catch(Exception $e) {
      error_log("Cookie API Response :--Exception thrown for i_s".$i_s." and c_in values.".$c_in."-Exception-".$e->getMessage());
      throw new Exception("Some error");
   }
}
}
?>
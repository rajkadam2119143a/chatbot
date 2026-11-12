<?php

class apiResponse{

  public function curlresponse($i_s,$c_in) {
  //print_r("api response");die;
  $i_s1 = $_COOKIE['i_s'];
  $c_in1 = $_COOKIE['c_in'];
  // print_r($i_s1);
  // print_r("<br>");
  // print_r($c_in1);
  $i_s2 = stripslashes(trim($i_s1,'"'));
  $c_in2 = stripslashes(trim($c_in1,'"'));

  $i_s = trim($i_s2,'"');
  $c_in = trim($c_in2,'"');
  // print_r($i_s);
  // print_r("<br>");
  // print_r($c_in);
  // die;

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
    //CURLOPT_COOKIE => "i_s=".$i_s.";c_in=".$c_in,
    CURLOPT_COOKIE => "i_s=".$i_s.";c_in=".$c_in,
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

  //print_r($body1);die;
  $result = json_decode($body1, true);

  if( !empty($result) ){

      foreach ($result as $val) {
        $api_response = array();

    //First Name    
        if (!empty($val['data']['firstName'])) {
          $api_response['first_name'] = $val['data']['firstName'];
        }

    //Last Name
        if (!empty($val['data']['surname'])) {
          $api_response['last_name'] = $val['data']['surname'];
        }

    //Email
        if (!empty($val['data']['emailId'])) {
          $api_response['email'] = $val['data']['emailId'];  
        }

    //Branch
        if (!empty($val['data']['branchName'])) {
          $api_response['branch'] = $val['data']['branchName'];   
        }

    //Location
        if (!empty($val['data']['locationName'])) {
          $api_response['location'] = $val['data']['locationName'];  
        }

    //Designation 
        if (!empty($val['data']['designation'])) {
          $api_response['designation'] = $val['data']['designation'];   
        }

    //Department    
        if (!empty($val['data']['deptGroupName'])) {
          $api_response['department'] = $val['data']['deptGroupName'];
        }

    //Sap
        if (!empty($val['data']['sapNumber'])) {
          $api_response['sap'] = $val['data']['sapNumber'];
        }

        //Cookie i_s
        if (!empty($i_s)) {
          $api_response['i_s'] = $i_s;
        }

        //Cookie c_in
         if (!empty($c_in)) {
          $api_response['c_in'] = $c_in;
        }

        //Response string
        if (!empty($val['status'])) {
          if($val['status'] == 'success') {
           $api_response['resp'] = 'true';
          }
        }

        return $api_response;
        //print_r( $api_response);die;

    }
  }
}
}
?>
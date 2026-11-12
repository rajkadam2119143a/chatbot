<?php

class apiManager{

	public function curlrequest($i_s,$jsessionid) {

		$url_decoded_i_s = urldecode($i_s);
		$curl = curl_init();
		//echo "i_s----".$i_s;die;

		curl_setopt_array($curl, array(
			CURLOPT_URL => MANAGER_API,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_HEADER => 1,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_HTTPHEADER => array(
				//'Authorization:'.$i_s,
				'Authorization:'.$url_decoded_i_s,
				//'Cookie:JSESSIONID=2568F4C0F3BA24D5E3F2264640568882.tomcat192',
				//'Cookie:JSESSIONID=3B58497C0E28E17F956BBB8D073E26E2.tomcat112',
				'Cookie: JSESSIONID='.$jsessionid
			),
		));


		$response1 = curl_exec($curl);
		error_log("response1=".$response1);
		$header_size1 = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$header1 = substr($response1, 0, $header_size1);
		$body1 = substr($response1, $header_size1);

		curl_close($curl);
		$result = json_decode($body1, true);

		$manager_data = $result[0]['data'];

		if( !empty($result) ){
			$user_manager = array();
			foreach ($manager_data[0] as $val) {
				$user_manager[] =  $val;
			}
		}

		if(!empty($user_manager)) {
			$manager_email = $user_manager[1];
		}

		//echo "manager email--".$manager_email;die;


		$result = json_decode($body1, true);

		if( !empty($result)) {

			foreach ($result as $val) {
				$user_info = array();

				if(isset($manager_email)) {
					$user_manager_email = $manager_email;
				}


				return $user_manager_email;

			}
		}


	}
}

?>
<?php


// Usage:
// mctest.php?
// apikey = apikey
// dc=dc
// listid=listid
// interests=yes
// lists=yes
// mergevals=yes
// tags=yes
// email=yourname@domain.com


$mcapikey = $_GET['apikey'];
$mandrillkey = $_GET['mandrillkey'];

if ( !$_GET['listid'] ){
	$listid = 'c580a5bbc9';
} else {
	$listid = $_GET['listid'];
}

if ( !$_GET['dc'] ) {
	$dc = "us1";
} else {
	$dc = $_GET['dc'];
}

require_once('mailchimp/vendor/autoload.php');
require_once('includes/mctransaction/vendor/autoload.php');

$url = 'https://'. $dc .'.api.mailchimp.com/3.0/lists/'. $listid;

$mailchimp = new \MailchimpMarketing\ApiClient();
$mailchimp->setConfig([
	'apiKey' => $mcapikey,
	'server' => $dc
]);

$mctransaction = new MailchimpTransactional\ApiClient();
$mctransaction->setApiKey( $mandrillkey );



try {
	$response = $mailchimp->ping->get();
	$response = $mctransaction->users->ping();
	echo 'code ran';
	//echo var_export( $response, true);
	echo '<br />';
} catch (Exception $e) {
	echo '<pre>';
	echo 'Caught exception: ',  $e->getMessage(), "\n";
	$exception = (string) $e->getResponse()->getBody();
  $exception = json_decode($exception);
	echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
	echo '</pre>';
} finally {
	echo 'More code here';
}

if ( !$_GET ['email'] ) {
	// $email = "helpdesk+test113@askcharlyleetham.com";
	$email = "hd+28072201@askcharlyleetham.com";
	$subemailhash = md5($email);
} else {
	$email = $_GET['email'];
	$emailcoded = htmlentities( $_GET['email'] );
	$emaildecoded = html_entity_decode ( $_GET['email'] );
	$subemailhash = md5( $email );
}

if ( $_GET['interests'] ) {
	try {
		$response1 = $mailchimp->lists->getListInterestCategories($listid);
		$mccats = $response1->categories;
		$catarr = array();
		$intarr = array();
		$catnum = 0;
		echo '<pre>';
		foreach ($mccats as $k) {
			$catarr[$k->title]['id'] = $k->id;
			$catarr[$k->title]['title'] = $k->title;
			$interests = $mailchimp->lists->listInterestCategoryInterests( $listid, $k->id );
			$ia = $interests->interests;
			$intnum = 0;
			foreach ( $ia as $v ) {
				$catarr[$k->title]['groups'][$intnum]['name'] = $v->name;
				$catarr[$k->title]['groups'][$intnum]['id'] = $v->id;
				$catarr[$k->title]['groups'][$intnum]['catid'] = $v->category_id;
				$intnum++;
			}
		}

		// echo var_export( $catarr, true ).'<br />';
		echo '<select2 multiple="multiple" name="$level[id]" class="mclist">';
			foreach ( $catarr as $key => $value ) {
				echo '<option disabled="disabled">** '.$key.' **</option>';
				foreach ( $value['groups'] as $k => $v ) {
					// echo $v['id'].' - '.$v['name'].'<br />';
					echo '<option value="'.$v['id'].'" >';
					echo $v['name'].'</option>';
				}
			}
		echo '</select2>';


		echo '</pre>';
	} catch (Exception $e) {
        	echo '<pre>';
	        $exception = (string) $e->getResponse()->getBody();
        	$exception = json_decode($exception);
		echo var_export( $exception ).'<br />';
        	echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
        	echo '</pre>';
	} finally {
		echo 'Here.';
	}
}

if ( $_GET['lists'] ) {
	try {
		$response1 = $mailchimp->lists->getAllLists();
		$listarr = array();
		$listnum = 0;
		echo '<pre>';
		$mclists = $response1->lists;
	//	echo var_export( $mclists, true ).'<br />';;
		foreach ( $mclists as $list1 ) {
			echo 'List 1: '.$list1->id.'<br />';
			//echo var_export( $list1, true ).'<br />';
			echo 'List: '.$list1->id.' - Name: '.$list1->name.'<br />';
		}
		echo '</pre>';
	} catch (Exception $e) {
        	echo '<pre>';
	        $exception = (string) $e->getResponse()->getBody();
        	$exception = json_decode($exception);
					echo var_export( $exception ).'<br />';
        	echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
        	echo '</pre>';
	} finally {

	}
}

if ( $_GET['mergevals'] ) {
	try {
		$response1 = $mailchimp->lists->getListMergeFields($listid);
		$listarr = array();
		$listnum = 0;
		echo '<pre>';
		echo var_export( $response1, true ).'<br />';
		$mclists = $response1->merge_fields;
		foreach ( $mclists as $list1 ) {
			echo 'Field: '.$list1->tag.'<br />';
			echo 'Name: '.$list1->name.'<br />';
			// if ( $list1->id == $listid ){
				// echo 'List: '.$list1->id.' - Name: '.$list1->name.'<br />';
			// echo 'ID: '.$list1[0]['id'].'<br />';
			// echo 'name: '.$list1->name.'<br />';
		}
		echo '</pre>';
	} catch (Exception $e) {
        	echo '<pre>';
	        $exception = (string) $e->getResponse()->getBody();
        	$exception = json_decode($exception);
		echo var_export( $exception ).'<br />';
        	echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
        	echo '</pre>';
	} finally {

	}
}

if ( $_GET['tags'] ) {
	try {
		$data = array (
			"count" => 1000
		);

		$url = $url."/tag-search/";

		$request_type = "GET";

		$response1 = json_decode( acl_mc_curl_connect( $url, $request_type, $mcapikey, $data ) );

		echo 'here';
		$totalitems = $response1->total_items;
		echo '<pre>';
		$mclists = $response1->tags;
		foreach ( $mclists as $list1 ) {
					echo 'List: '.$list1->id.' - Name: '.$list1->name.'<br />';
		}
		echo '</pre>';
	} catch (Exception $e) {
        	echo '<pre>';
	        $exception = (string) $e->getResponse()->getBody();
        	$exception = json_decode($exception);
					echo var_export( $exception ).'<br />';
        	echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
        	echo '</pre>';
	} finally {

	}
}



if ( $_GET['add'] ) {

	try {
		$response = $mailchimp->lists->setListMember( $listid, $subemailhash, [
		    "email_address" => $email,
		    "status_if_new" => "subscribed",
				"merge_fields" => [
					"FNAME" => "Test",
					"LNAME" => "User"
				],
				"interests" => [
					"1e6ead23f9" => true,
				],
			]
		);

	} catch (Exception $e) {
		echo '<pre>';
		$exception = (string) $e->getResponse()->getBody();
		$exception = json_decode($exception);
		echo var_export( $exception ).'<br />';
		echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
		echo '</pre>';
	}

/*
	try {
			$response1 = $mailchimp->lists->updateListMemberTags($listid, $subemailhash, [
	    "tags" => [
					["name" => "Test Tag",
					"status" => "inactive"]
				],
			]);
			echo '<pre>';
					echo 'Result: <br />';
					var_dump ( $response1 );
			echo '</pre>';
	} catch (Exception $e) {
		echo '<pre>';
		$exception = (string) $e->getResponse()->getBody();
		$exception = json_decode($exception);
		echo var_export( $exception ).'<br />';
		echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
		echo '</pre>';
	}
*/

}

if ( $_GET['addtest'] ) {

	$email = "hd+28072202@askcharlyleetham.com";

	try {
		$response = $mailchimp->lists->setListMember("9f47bd4d97", "e31206c0d38f9e1461c5a5ac12cab0c8", [
	      "email_address" => $email,
	      "status_if_new" => "subscribed",
	                  "merge_fields" => [
	                          "FNAME" => "Test",
	                          "LNAME" => "User"
	                  ]
	          ]
	  );
	} catch (Exception $e) {
		echo '<pre>';
		$exception = (string) $e->getResponse()->getBody();
		$exception = json_decode($exception);
		echo var_export( $exception ).'<br />';
		echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
		echo '</pre>';
	}
}



function acl_mc_curl_connect( $url, $request_type, $api_key, $data = array() ) {
	if( $request_type == 'GET' )
		$url .= '?' . http_build_query($data);

	$mch = curl_init();
	$headers = array(
		'Content-Type: application/json',
		'Authorization: Basic '.base64_encode( 'user:'. $api_key )
	);
	curl_setopt($mch, CURLOPT_URL, $url );
	curl_setopt($mch, CURLOPT_HTTPHEADER, $headers);
	//curl_setopt($mch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0');
	curl_setopt($mch, CURLOPT_RETURNTRANSFER, true); // do not echo the result, write it into variable
	curl_setopt($mch, CURLOPT_CUSTOMREQUEST, $request_type); // according to MailChimp API: POST/GET/PATCH/PUT/DELETE
	curl_setopt($mch, CURLOPT_TIMEOUT, 10);
	curl_setopt($mch, CURLOPT_SSL_VERIFYPEER, false); // certificate verification for TLS/SSL connection

	if( $request_type != 'GET' ) {
		curl_setopt($mch, CURLOPT_POST, true);
		curl_setopt($mch, CURLOPT_POSTFIELDS, json_encode($data) ); // send data in json
	}

	return curl_exec($mch);

}
?>

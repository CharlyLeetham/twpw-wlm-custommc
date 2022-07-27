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
$mailchimp = new \MailchimpMarketing\ApiClient();
$mailchimp->setConfig([
	'apiKey' => $mcapikey,
	'server' => $dc
]);

try {
	$response = $mailchimp->ping->get();
	echo 'code ran';
	echo var_export( $response, true);
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
	$email = "helpdesk+test113@askcharlyleetham.com";
	$subemailhash = md5('helpdesk+test113@askcharlyleetham.com');
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
		$response1 = $mailchimp->lists->tagSearch($listid);
		$listarr = array();
		$listnum = 0;
		echo '<pre>';
		// echo var_export( $response1, true ).'<br />';
		$mclists = $response1->tags;
		echo var_export( $mclists, true ).'<br />';
		foreach ( $mclists as $list1 ) {
			echo 'List 1: '.$list1->id.'<br />';
			echo var_export( $list1, true ).'<br />';
			echo 'List: '.$list1->id.' - Name: '.$list1->name.'<br />';
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



if ( $_GET['add'] ) {

	var_dump ( $email );
	var_dump ( $emailcoded );
	var_dump ( $emaildecoded );
	var_dump ( $subemailhash );
	var_dump ($listid);

	try {
		$response = $mailchimp->lists->setListMember( $listid, $subemailhash, [
		    "email_address" => $email,
		    "status_if_new" => "subscribed",
				"merge_fields" => [
					"FNAME" => "Test",
					"LNAME" => "User"
				]
			]
			[
				"skip_merge_validation" => false
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


?>

<?php
$dc = "us1";
$mcapikey = $_GET['apikey'];
$listid = 'c580a5bbc9';

require_once('mailchimp/vendor/autoload.php');
$mailchimp = new \MailchimpMarketing\ApiClient();
$mailchimp->setConfig([
	'apiKey' => $mcapikey,
	'server' => $dc
]);

//try {
	//$response = $mailchimp->ping->get();
	//echo var_export( $response, true);

//} catch (Exception $e) {
//	echo '<pre>';
//	$exception = (string) $e->getResponse()->getBody();
//        $exception = json_decode($exception);
//	echo 'An error has occurred: '.$exception->title.' - '.$exception->detail;
//	echo '</pre>';
//} finally {
	//echo 'More code here';
//}

$subemailhash = md5('helpdesk+test113@askcharlyleetham.com');

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
				$catarr[$k->title][$intnum]['title'] = $v->name;
				$catarr[$k->title][$intnum]['id'] = $v->id;
				$catarr[$k->title][$intnum]['catid'] = $v->category_id;
				$intnum++;
			}
		}

		// echo var_export( $catarr, true ).'<br />';
		// echo '<select multiple="multiple" name="$level[id]" class="mclist">';
			foreach ( $catarr as $key => $value ) {
				// echo '<option disabled="disabled">** '.$key.' **</option>';
				// echo '** '.$key.' ** '.var_export ( $value, true ). '<br />';
				foreach ( $value as $k => $v ) {
					echo 'Key: '.$k.' Val: '.var_export( $v, true ).'<br />';
					// echo '<option value="'.$v['id'].'" >';
					// echo $v['name'].'</option>';
				}
			}
		// echo '</select>';


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
		// echo var_export( $mclists, true ).'<br />';;
		foreach ( $mclists as $list1 ) {
			// echo 'List 1: '.$list1->id.'<br />';
			// echo var_export( $list1, true ).'<br />';
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


?>
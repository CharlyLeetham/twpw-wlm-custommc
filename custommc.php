<?php 
/*
Plugin Name: TWPW Custom MC signup
Plugin URI: http://askcharlyleetham.com
Description: Mailchimp Signup
Version: 2.01
Author: Morgan & Charly Leetham
Author URI: http://thewpwarrior.com
License: GPL

Changelog
Version 1.0 - Original Version
Version 1.1 - Added WooCommerce support and Delete User support
Version 1.2 - Added Mailchimp Group support
Version 1.3 - Fixed Sequential Add
Version 2a - Tidy up.
Version 2.01 - Clean up the code being output to the screen.
*/

/* WP Version Check */
global $wp_version;

$exit_msg='Mailchimp Signup requires WordPress 3.3.1 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please upgrade!</a> You have version: '.$wp_version.'';
if (version_compare($wp_version, "3.3.1","<"))
{
	exit ($exit_msg);
}

require_once(dirname(__FILE__) . '/admin/menu.php');

/*	--------------------------------------------------
	Load the admin stylesheet
 ----------------------------------------------------- */
function twpw_custommc_admin_register_head() {
	$siteurl = get_option('siteurl');
	$url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/admin/style.css';
	
	echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
}

add_action('admin_head', 'twpw_custommc_admin_register_head');

function showmcapi1($id,$levels) {
	$debug=true;
	ob_start();
	$settings = get_option('twpw_custommc',false);
	$mcapikey = $settings['mcapikey'];	
	define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
	if ( !class_exists ( 'Mailchimp' ) ) require_once ( 'includes/Mailchimp.php' );
	
	
	$levels = wlmapi_get_member_levels($id); //Using the member ID, get the membership level details. We're going to use this information to find those that need approval.
	
	if ($debug) {
		// $output = var_export( $_POST,true );
		echo "User ID: " .$id;
		echo "\r\n\r\n";
		var_dump( $_POST );
		echo "\r\n\r\n";		
		var_dump( $levels );
		echo "\r\n\r\n";
		echo "----------";
		echo "\r\n\r\n";		
		$logfile = fopen( LOGPATH."mcintlog.log", "a" );
		$out = ob_get_clean();
		fwrite( $logfile, $out );
		fclose( $logfile );		
	}
	
	//get the user object so we can grab their details to add to Mailchimp
	$user = get_user_by('id',$id);
	$firstname = $user->user_firstname;
	$lastname = $user->user_lastname;
	$useremail = $user->user_email;

	//Using the POST variable, find the level that the member was added to

	foreach($levels as $level) {

		if($_POST['wpm_membership_to']) {
			$level = $_POST['wpm_membership_to'];
			$no_repeat = true;
		} elseif ($_POST['wpm_id']) {
			$level = $_POST['wpm_id'];
			$no_repeat = true;
		}
		
		/* Find the appropriate MC Settings from the database */

		$mclistid = (empty($settings[$level]['mclistid']))?false:$settings[$level]['mclistid'];
		
		if ($mclistid==false) { /*echo "No List";*/ break; } else { /*echo "List: " . $mclistid;*/ }
		$double_optin = (empty($settings[$level]['dblopt']))?true:false;
		$unsub = (empty($settings[$level]['unsub']))?false:true;
		$send_welcome = (empty($settings[$level]['sendwel']))?false:true;
		$send_goodbye = (empty($settings[$level]['sendbye']))?false:true;
		$send_notify = (empty($settings[$level]['sendnotify']))?false:true;
		$groupings = array(); // create groupings array
		if( !empty( $settings[$level]['mcgroup'] ) ) { // if there are groups
			foreach( $settings[$level]['mcgroup'] as $group ) { // go through each group that's been set
				$group = explode('::',$group); // divide the group as top id and bottom name
				$groups[$group[0]][] = $group[1]; 
			}
			foreach($groups as $group_id => $group) {
				$groupings[] = array('id'=>$group_id, 'groups' => $group);
			}
		}
		// Setup the array to send to Mailchimp
		global $wpdb;
		$mailchimp = new Mailchimp ( $mcapikey );
		$merge_vars = array (
							 'FNAME' => $firstname,
							 'LNAME' => $lastname,
							 'GROUPINGS' => $groupings,
							);
		$merge_vars = array_merge($merge_vars, $settings[$level]['merge_vars']);
		// For PDT ONLY
		$merge_vars['JOINED'] = current_time('Y-m-d');
		$email_type = 'html';
		$update_existing = TRUE;
		$replace_interests = TRUE;	
		$delete_member = FALSE;
				
		if ( $debug ) {
			echo "\r\n\r\n";
			echo "Post: ";
			var_dump($_POST);
			echo '<br />';
			var_dump ($level);
		}
		
		// Assign $action based on the WLM call used
		if ( $_POST['wpm_action'] ) {
			$action = $_POST['wpm_action'];
		} elseif ( $_POST['action'] ) {
			$action = $_POST['action'];
		} elseif ( $_POST['WishListMemberAction'] ){
			$action = $_POST['WishListMemberAction'];
		} else {
			$action = 'wpm_add_membership';
		}
		
		
		
		if( $debug ) {
			$logfile = fopen( LOGPATH."mcintlog.log", "a" );
			$out =ob_get_clean();
			fwrite( $logfile, $out );
			fclose( $logfile );
			$logfile = fopen( LOGPATH."mcintlog-1.log", "a" );
            fwrite( $logfile, $msg1 );
            fclose( $logfile );
		}		

		//Add or Remove from Mailchimp list based on WLM action and Mailchimp settings
		if ( $action=='wpm_add_membership' || $action == 'wpm_register' || $action=='wpm_change_membership' || $action=='admin_actions' || $action=='schedule_user_level' ) {
							
			$result = $mailchimp->call( '/lists/subscribe', array(
				'apikey' => $mcapikey,
				'id' => $mclistid,
				'email' => array('email' => $useremail),
				'merge_vars' => $merge_vars,
				'email_type' => $email_type,
				'double_optin' => $double_optin,
				'update_existing' => $update_existing,
				'replace_interests' => $replace_interests,
				'send_welcome' => $send_welcome
			));
									
			if ( $debug ) {
				if ($mailchimp->errorCode){
					echo "Unable to load listUnsubscribe()!\n";
					echo "\tCode=".$mailchimp->errorCode."\n";
					echo "\tMsg=".$mailchimp->errorMessage."\n";
					$msg1 .= "Unable to load listUnsubscribe()!\n";
					$msg1 .= "\tCode=".$mailchimp->errorCode."\n";
					$msg1 .= "\tMsg=".$mailchimp->errorMessage."\n";					
					
				} else {
					echo "\r\n\r\n";
					echo 'Add to Mailchimp Success';
					$msg1 .= 'Add Success'."\n";					
				}
			}
		} elseif ($action == 'wpm_del_membership' && $unsub == true) {
			
			$result = $mailchimp->call( '/lists/unsubscribe', array(
				'apikey' => $mcapikey,
				'id' => $mclistid,
				'email' => array('email' => $useremail),
				'delete_member' => $delete_member,
				'send_goodbye' => $send_goodbye,
				'send_notify' => $send_notify
			));	
			if ($debug) {
				if ($mailchimp->errorCode){
					echo "Unable to load listUnsubscribe()!\n";
					echo "\tCode=".$mailchimp->errorCode."\n";
					echo "\tMsg=".$mailchimp->errorMessage."\n";
					$msg1 .= "Unable to load listUnsubscribe()!\n";
					$msg1 .= "\tCode=".$mailchimp->errorCode."\n";
					$msg1 .= "\tMsg=".$mailchimp->errorMessage."\n";					
				} else {
					echo "\r\n\r\n";
					echo 'Del from Mailchimp Success';					
					$msg1 .= 'Del Success'."\n";
				}
			}
		}
		
		if( $debug ) {
			$logfile = fopen( LOGPATH."mcintlog.log", "a" );
			$out =ob_get_clean();
			fwrite( $logfile, $out );
			fclose( $logfile );
			$logfile = fopen( LOGPATH."mcintlog-1.log", "a" );
            fwrite( $logfile, $msg1 );
            fclose( $logfile );
		}
		
		if($no_repeat) break; 		
	}
	return $result;
}
add_action ('wishlistmember_remove_user_levels','showmcapi1',30,2);
add_action ('wishlistmember_add_user_levels','showmcapi1',30,2);
// add_action ('wishlistmember_approve_user_levels','showmcapi1',30,2);
// add_action ('wishlistmember_after_registration','showmcapi1',100,2);


function showmcapi2($id) {

	//get the user object so we can grab their details to add to Mailchimp
	/* Find the appropriate MC Settings from the database */
	$debug=false;
	$settings = get_option('twpw_custommc',false);
	$mcapikey = $settings['mcapikey'];
	if ( !class_exists ( 'Mailchimp' ) ) require_once ( 'includes/Mailchimp.php' );
	$mailchimp = new Mailchimp ( $mcapikey );
		
	$wlmapi = twpw_verify_api();		
	$user = get_user_by('id',$id);
	$firstname = $user->user_firstname;
	$lastname = $user->user_lastname;
	$useremail = $user->user_email;

	$levels = unserialize($wlmapi->get('/members/'.$id));
	$levels = $levels['member'][0]['Levels'];
	$levels = array_keys($levels);
	
	foreach ($levels as $level) {
		$mclistid = (empty($settings[$level]['mclistid']))?false:$settings[$level]['mclistid'];
			if ($mclistid==false) { break; }
		$unsub = (empty($settings[$level]['unsub']))?false:true;
		$send_goodbye = (empty($settings[$level]['sendbye']))?false:true;
		$send_notify = (empty($settings[$level]['sendnotify']))?false:true;
	
		// Setup the array to send to Mailchimp
		$merge_vars = array (
							 'FNAME' => $firstname,
							 'LNAME' => $lastname,
							);									
		$email_type = 'html';
		$update_existing = TRUE;
		$replace_interests = TRUE;	
		$delete_member = FALSE;
		
		//echo $mcapikey.' '.$mclistid.' |'.$useremail.'| '.$delete_member.' '.$send_goodbye.' '.$send_notify;


		$result = $mailchimp->call( '/lists/unsubscribe', array(
			'apikey' => $mcapikey,
			'id' => $mclistid,
			'email' => array('email' => $useremail),
			'delete_member' => $delete_member,
			'send_goodbye' => $send_goodbye,
			'send_notify' => $send_notify
		));	
		//var_dump($result);
		//die();
		if ($debug) {
			if ($mailchimp->errorCode){
				echo "Unable to load listUnsubscribe()!\n";
				echo "\tCode=".$mailchimp->errorCode."\n";
				echo "\tMsg=".$mailchimp->errorMessage."\n";
			} else {
				echo 'Success';
			}
			die();
		}
		
	}
	return $result;

}
//add_action( 'delete_user', 'showmcapi2' );

//For Woo Commerce / WLM integration
function showmcapi3($id,$levels) {
	$debug=false;
	$settings = get_option('twpw_custommc',false);
	$mcapikey = $settings['mcapikey'];	if ( !class_exists ( 'Mailchimp' ) ) require_once ( 'includes/Mailchimp.php' );


	$mailchimp = new Mailchimp ( $mcapikey );		

	//get the user object so we can grab their details to add to Mailchimp
	$user = get_user_by('id',$id);
	$firstname = $user->user_firstname;
	$lastname = $user->user_lastname;
	$useremail = $user->user_email;
	
	
	
	/* Find the appropriate MC Settings from the database */

	foreach ($levels as $level) {	
		$mclistid = (empty($settings[$level]['mclistid']))?false:$settings[$level]['mclistid'];
			if ($mclistid==false) { return; }
		$double_optin = (empty($settings[$level]['dblopt']))?true:false;
		$unsub = (empty($settings[$level]['unsub']))?false:true;
		$send_welcome = (empty($settings[$level]['sendwel']))?false:true;
		$send_goodbye = (empty($settings[$level]['sendbye']))?false:true;
		$send_notify = (empty($settings[$level]['sendnotify']))?false:true;
		
		$groupings = array(); // create groupings array
		if( !empty( $settings[$level]['mcgroup'] ) ) { // if there are groups
			foreach( $settings[$level]['mcgroup'] as $group ) { // go through each group that's been set
				$group = explode('::',$group); // divide the group as top id and bottom name
				$groups[$group[0]][] = $group[1]; 
			}
			foreach($groups as $group_id => $group) {
				$groupings[] = array('id'=>$group_id, 'groups' => implode(',',$group));
			}
		}
		// Setup the array to send to Mailchimp
		$merge_vars = array (
							 'FNAME' => $firstname,
							 'LNAME' => $lastname,
							 'GROUPINGS' => $groupings,
							);
											
		$email_type = 'html';
		$update_existing = TRUE;
		$replace_interests = TRUE;	 

		$result = $mailchimp->call( '/lists/subscribe', array(
			'apikey' => $mcapikey,
			'id' => $mclistid,
			'email' => array('email' => $useremail),
			'merge_vars' => $merge_vars,
			'email_type' => $email_type,
			'double_optin' => $double_optin,
			'update_existing' => $update_existing,
			'replace_interests' => $replace_interests,
			'send_welcome' => $send_welcome
		));
		$debug="off";
		if ($debug == "on") {
    		if ($mailchimp->errorCode){
    			$msg1 = "Unable to load listUnsubscribe()!\n";
    			$msg1 .= "\tCode=".$mailchimp->errorCode."\n";
    			$msg1 .= "\tMsg=".$mailchimp->errorMessage."\n";
    			/*die();*/
    		} else {
    			$msg1 = 'Success';
    			/*die();*/
    		}
		$logfile = fopen("/home/ad747432/public_html/pdt/wp-content/plugins/twpw-wlm-custommc/mcintlog-1.log", "a");
        fwrite($logfile, $msg1);
        fclose($logfile);
		}
	}
	return $result;
}


function get_mailchimp_lists($mclistid,$wlmlevelid) {
	//Setup the mailchimp api
	global $twpw_custommc_mcapi;
	$settings = get_option("twpw_custommc");
	$api_key = $settings['mcapikey'];
	if (($api_key <> "")) {
		$list_info = $twpw_custommc_mcapi->call('/lists/list',array(
			'api_key' => $api_key,
		));
		$alllists = $list_info['data'];
		$mailchimplists = '<select class="mclistid" name="twpw_custommc['.$wlmlevelid.'][mclistid]">
						<option value="0">No list</option>';
						foreach ($alllists as $key=>$value) {
							$mailchimplists.='<option value="'.$alllists[$key]['id'].'"';
								if ($alllists[$key]['id'] == $mclistid) { $mailchimplists.=' selected="yes" '; }
							$mailchimplists.='>'.$alllists[$key]['name'].'</option>';
						}
		$mailchimplists .= '</select>';
	} else {
		$mailchimplists.= 'Please enter your mailchimp API before continuing<br>';
	}
	return $mailchimplists;
}


function twpw_custommc_createMCAPI() {
	global $twpw_custommc_mcapi;
	if (isset($twpw_custommc_mcapi)) return;
 if ( !class_exists ( 'Mailchimp' ) ) require_once ( 'includes/Mailchimp.php' );		
	$settings = get_option("twpw_custommc");
	$api_key = $settings['mcapikey'];	
	$twpw_custommc_mcapi = new Mailchimp ( $api_key );
}

function twpw_create_merge_vars_feilds($level_id,$settings) {
//Create the cell and table for the merge vars; and the row for the meger headings ?>
<td><table><tr>
<?php
	twpw_custommc_createMCAPI();
	global $twpw_custommc_mcapi;
		$mc_merge_vars = $twpw_custommc_mcapi->call('/lists/merge-vars',array('id'=>array($settings[$level_id]['mclistid'])));
	$mc_merge_vars = $mc_merge_vars['data'][0]['merge_vars'];
/**/
	foreach($mc_merge_vars as $merge_var){
		if(!in_array($merge_var['tag'],array('EMAIL','FNAME','LNAME'))) { ?>
			<td><strong><?php echo $merge_var['name']; ?></strong></td>
<?php
		}
	}
	//Close the heading row, open the options row?>
</tr><tr>
	<?php
	foreach($mc_merge_vars as $merge_var){
		if(!in_array($merge_var['tag'],array('EMAIL','FNAME','LNAME'))) { ?>
		<td>
			<?php $feild_name = 'twpw_custommc['. $level_id .'][merge_vars]['.$merge_var['tag'].']'; ?>
				<?php 
				switch($merge_var['field_type']) {
					case 'date':
						//echo '<input type="date" name="'.$level_id.'::'.$feild_name.'" id="'.$level_id.'::'.$merge_var['tag'].'" value="'.$settings[$level_id]['merge_vars'][$merge_var['tag']].'" disabled="disabled"/>';
						break;
					case 'radio':
						ob_start();
						foreach($merge_var['choices'] as $key => $choices) {
							if( $choices == $settings[$level_id]['merge_vars'][$merge_var['tag']] ) {
								$checkthis = 'nocheckthis';
								$optionfound = true;
							} elseif (  $key == 0  ) {
								$checkthis = 'checkthis';
							}
							echo '<input type="radio" name="'.$feild_name.'" id="'.$level_id.'::'.$merge_var['tag'].'::'.$key.'" value="'.$choices.'" '.$checkthis.'/>';
							echo '<label for="'.$level_id.'::'.$merge_var['tag'].'::'.$key.'">'.$choices.'</label><br />';
							$checkthis = '';
						}
						$output = ob_get_contents();
						if( $optionfound ) {
							$output = str_replace( array( 'nocheckthis','checkthis'), array( 'checked="checked" ',''), $output);
						} else { 
							$output = str_replace( array( 'checkthis'), array( 'checked="checked"'), $output);
						
						}
						ob_end_clean();
						echo $output;
						$optionfound = false;
						break;
					default:
						echo '<input type="text" name="'.$merge_var['tag'].'" id="'.$merge_var['tag'].'" />';
				}
				?>
		</td>
<?php		}
	}
?>
</tr></table></td>
<?php	/**/
}

if (!function_exists('twpw_verify_api')) {
	function twpw_verify_api() {
	global $twpw_wlm_api;	
	if (isset($twpw_wlm_api)) {
		return $twpw_wlm_api;
	}
	global $wpdb;
	$wlmapikey =  $wpdb->get_results("SELECT `option_value` FROM `{$wpdb->prefix}wlm_options` WHERE `option_name` = 'WLMAPIKey'",ARRAY_N);
	$wlmapikey = $wlmapikey[0][0];
	$wlmapiset = 'no';
	if ($wlmapiset) {
		$x = WP_PLUGIN_DIR.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
		$x = $x.'/includes/wlmapiclass.php';
		if (!file_exists(trim($x))) {
			return 'No File';
		} else {
			/* include the class */
			if (!class_exists('wlmapiclass')) {
				include ($x);
			}
			if (get_option('wlposturl')) {
				$siteurl = get_option('wlposturl');
			} else {
				$siteurl = get_bloginfo('url'); // get the site url 
			}		
			$siteurl = str_replace('https:','http:',$siteurl);
			$siteurl = $siteurl.'/'; // append a / to the end of the url 
			$twpw_wlm_api = new wlmapiclass($siteurl,$wlmapikey); // initialise the api
			$twpw_wlm_api->return_format = 'php';			// set the return format to php	
			$levelid = '/levels/';
			$alllevels = $twpw_wlm_api->get($levelid);
			$alllevels = unserialize($alllevels);
			if ($alllevels['success'] === 0) {
			// Check if the api is authenticated
				return 'No Auth';
			} else {
				return $twpw_wlm_api;
			} //end authentication test
		} // (end file_exists)
	} // End Function twpw_get_mem_levels
}
}

function gethere() {
	var_dump($_POST);
	die();
}
//add_action('init','gethere');


?>
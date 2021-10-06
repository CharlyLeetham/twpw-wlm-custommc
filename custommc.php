<?php 
/*
Plugin Name: TWPW Custom MC signup
Plugin URI: http://askcharlyleetham.com
Description: Mailchimp Signup
Version: 2.03
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
Version 2.02 - Rewrite to stop people being moved after being added
Version 2.03 - Adding a class
*/


class twpw_custom_mc {
	
	function twpw_custom_mc_activate() {
		/* WP Version Check */
		global $wp_version;

		$exit_msg='Mailchimp Signup requires WordPress 3.3.1 or newer. <a href="http://codex.wordpress.org/Upgrading_WordPress">Please upgrade!</a> You have version: '.$wp_version.'';
		if (version_compare($wp_version, "3.3.1","<"))
		{
			exit ($exit_msg);
		}
	}

	public static function init() {
		if ( is_admin() ) {
			require_once(dirname(__FILE__) . '/admin/menu.php');
		}	
	}

	/*	--------------------------------------------------
		Load the admin stylesheet
	 ----------------------------------------------------- */
	function twpw_custommc_admin_register_head() {
		$siteurl = get_option('siteurl');
		$url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/admin/style.css';
		
		echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
	}

	function acl_wlm_test( $id, $levels ) {	
		ob_start();
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		date_default_timezone_set("US/Hawaii");
		$logging = get_option("twpw_custommc_logging");
		if ( $logging == "yes") {
			$logging = true;
		} else {
			$logging = false;
		}

		$debug = get_option("twpw_custommc_listdebug");
		if ( $debug == "yes") {
			$debug = true;
		} else {
			$debug = false;
		}
		
		if ( !$debug ) {
			return;
		}
		
		$live = get_option("twpw_custommc_livetest");
		if ( $live == "yes") {
			$live = true;
		} else {
			$live = false;
		}		

		$logger = '';	
		$settings = get_option("twpw_custommc");
		$api_key = $settings['mcapikey'];	
		
		/* Setup Logging */
		if (!file_exists(dirname( __FILE__ ).'/logs')) {
			mkdir(dirname( __FILE__ ).'/logs', 0775, true);
		}
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		/* End logging setup */
		
		$wlmlevels = wlmapi_get_member_levels($id); //Using the member ID, get the membership level details. We're going to use this information to find those that need approval.	
		
		$logger .= 'Date: '. date("m/d/Y H:i:s").' ('.date("O").') GMT'."\r\n\r\n";
		$logger .= "User ID: " .$id;
		$logger .= "\r\n\r\n";		
		$logger .= 'Post: ';
		$postexp = var_export( $_POST, true );
		$logger .= $postexp;
		$logger .= "\r\n\r\n";
		$logger .= 'Added by: ';
		$logger .= $_POST['WishListMemberAction'];
		$logger .= "\r\n\r\n";	
		$userdata = get_user_meta( $id );	
		$userexp = var_export ( $userdata, true);
		$logger .= 'User Array: '.$userexp;
		$logger .= "\r\n\r\n";
		$logger .= "***  ***  ***\r\n\r\n";	
		
		$logfile = fopen( LOGPATH."moving.log", "a" );
		fwrite( $logfile, $logger );
		fclose( $logfile );	
	}

	function acl_wlm_approve_user( $id, $levels ) {
		
		ob_start();
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		/* Setup Logging */
		if (!file_exists(dirname( __FILE__ ).'/logs')) {
			mkdir(dirname( __FILE__ ).'/logs', 0775, true);
		}
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		/* End logging setup */
		
		date_default_timezone_set("US/Hawaii");
		$logging = get_option("twpw_custommc_logging");
		if ( $logging == "yes") {
			$logging = true;
		} else {
			$logging = false;
		}

		$debug = get_option("twpw_custommc_listdebug");
		if ( $debug == "yes") {
			$debug = true;
		} else {
			$debug = false;
		}
		
		$live = get_option("twpw_custommc_livetest");
		if ( $live == "yes") {
			$live = true;
		} else {
			$live = false;
		}
		
		$logger = '';
		
		$settings = get_option("twpw_custommc");
		$api_key = $settings['mcapikey'];
		
		if ( !class_exists ( 'Mailchimp' ) ) require_once ( 'includes/Mailchimp.php' );			

		$mailchimp = new Mailchimp( $api_key );
		
		if ( $debug ) {
			echo '$mailchimp:';
			echo var_export( $mailchimp, true );
			echo "\r\n";
		}						
		

		
		$wlmlevels = wlmapi_get_member_levels($id); //Using the member ID, get the membership level details. We're going to use this information to find those that need approval.	

		if ( $debug ) {
			echo 'Date: '. date("m/d/Y H:i:s").' ('.date("O").') GMT'."\r\n\r\n";
			echo "User ID: " .$id;
			echo "\r\n\r\n";		
			echo 'Post: ';
			$postexp = var_export( $_POST, true );
			echo $postexp;
			echo "\r\n\r\n";		
			$levexp = var_export( $levels, true );
			echo 'Levels: '.$levexp;
			echo "\r\n\r\n";
			$levexp = var_export ( $wlmlevels, true );
			echo 'WLM Levels: '.$levexp;
			echo "\r\n\r\n";
			$sett = var_export ( $settings, true );
			echo 'TWPW CustomMC:';
			echo $sett."\r\n\r\n";
		}

		//get the user object so we can grab their details to add to Mailchimp
		$user = get_user_by( 'id', $id );
		$firstname = $user->user_firstname;
		$lastname = $user->user_lastname;
		$useremail = $user->user_email;
		$wlmaction = $_POST['WishListMemberAction'];
		$levelaction = $_POST['level_action'];

		foreach( $levels as $k => $levid ) {

			if ( ( $settings[$levid]['mclistid'] ) ) {
				$mclistid = $settings[$levid]['mclistid'];
			} else {
				$mclistid = false;
			}
			
			if ( $mclistid == false ) {
				if ( $debug ) {
					echo "No List"; 
					echo "\r\n\r\n"; 
					$logfile = fopen( LOGPATH."mcapplog.log", "a" );
					$out =ob_get_clean();
					fwrite( $logfile, $out );
					fclose( $logfile );			
				}
				
				if ( $logging ) {
					$logger = date("m/d/Y H:i:s"). '('. date ("O") .' GMT) '.$firstname.' '.$lastname.'('.$id.' '.$levid.') not added to Mailchimp.'."\r\n";
					$logfile = fopen( LOGPATH."approvemember.log", "a" );
					fwrite( $logfile, $logger );
					fclose( $logfile );				
				}
			} else { 
				if ( $debug ) {
					echo "List: " . $mclistid; 
					echo "\r\n\r\n"; 
				}

				$double_optin = (empty($settings[$levid]['dblopt']))?true:false;
				$unsub = (empty($settings[$level]['unsub']))?false:true;
				$send_welcome = (empty($settings[$levid]['sendwel']))?false:true;
				$send_goodbye = (empty($settings[$levid]['sendbye']))?false:true;
				$send_notify = (empty($settings[$levid]['sendnotify']))?false:true;
				$groupings = array(); // create groupings array
				if( !empty( $settings[$levid]['mcgroup'] ) ) { // if there are groups
					foreach( $settings[$levid]['mcgroup'] as $group ) { // go through each group that's been set
						$group = explode('::',$group); // divide the group as top id and bottom name
						$groups[$group[0]][] = $group[1]; 
					}
					foreach($groups as $group_id => $group) {
						$groupings[] = array('id'=>$group_id, 'groups' => $group);
					}
				}
				// Setup the array to send to Mailchimp
				global $wpdb;
				
				$merge_vars = array (
									 'FNAME' => $firstname,
									 'LNAME' => $lastname,
									 'GROUPINGS' => $groupings,
									);
				$merge_vars = array_merge($merge_vars, $settings[$levid]['merge_vars']);

				// For PDT ONLY
				$merge_vars['JOINED'] = current_time('Y-m-d');
				$previous_join_date = get_user_meta( $id, 'wlm_join_date', false );
				if ( empty ( $previous_join_date ) ) {
					add_user_meta ( $id, 'wlm_join_date', $merge_vars['JOINED'] );
					echo 'join date of '.$merge_vars['JOINED'].' added'."\r\n\r\n";
					$logger1 = 'join date of '.$merge_vars['JOINED'].' added';
				} elseif ( $settings[$level[$levid]]['update_join_date'] == 'yes' ) {
					update_user_meta ( $id, 'wlm_join_date', $merge_vars['JOINED'] );
					echo 'join date updated from '.$previous_join_date.' to: '.$merge_vars['JOINED']."\r\n";			
					$logger1 = 'join date updated from '.$previous_join_date.' to: '.$merge_vars['JOINED'];			
				} else {
					echo 'join date not updated from '.$previous_join_date[0]."\r\n";
					$logger1 = 'join date not updated from '.$previous_join_date[0];
				}

				$email_type = 'html';
				$update_existing = TRUE;
				$replace_interests = TRUE;	
				$delete_member = FALSE;
						
				if ( $debug ) {
					$myarray = array(
						'apikey' => $mcapikey,
						'id' => $mclistid,
						'email' => array('email' => $useremail),
						'merge_vars' => $merge_vars,
						'email_type' => $email_type,
						'double_optin' => $double_optin,
						'update_existing' => $update_existing,
						'replace_interests' => $replace_interests,
						'send_welcome' => $send_welcome
					);
					$myarr = var_export ( $myarray, true );
					echo 'Mailchimp settings: '."\r\n\r\n";
					echo $myarr."\r\n";
				}
				
				if ( $live ) {		
					
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
											
					if ($mailchimp->errorCode){
						if ( $logging ) {
							$logger .= "Unable to load listUnsubscribe()!\n\r";
							$logger .= "\tCode=".$mailchimp->errorCode."\n\r";
							$logger .= "\tMsg=".$mailchimp->errorMessage."\n\r";				
						}
						
					} else {
						if ( $logging ) {
							$logger .= date("m/d/Y H:i:s"). '('. date ("O") .' GMT) Added '.$firstname .'('.$id.') for Level: '.$levid.' to Mailchimp List: '.$mclistid. 'by '.$wlmaction.' ('.$levelaction.')'."\r\n";
							if ( $groupings ) {
								$logger .= 'for groups: '.var_export( $groupings, true )."\r\n";
							}
							$logger .= ' '.$logger1;
							$logger .= "\n\r---\n\r";
						}
					}
				} else {
					if ( $debug ) {
						echo 'Call made: $mailchimp->call( /lists/subscribe, '. $myarr .')';
						echo "\r\n";
					}
					
					if ( $logging ) {
						$logger .= date("m/d/Y H:i:s"). '('. date ("O") .' GMT) Added as test '.$firstname .'('.$id.') for Level: '.$levid.' to Mailchimp List: '.$mclistid. 'by '.$wlmaction.' ('.$levelaction.')'."\r\n";
						if ( $groupings ) {
							$logger .= 'for groups: '.var_export( $groupings, true )."\r\n";
						}
						$logger .= ' '.$logger1;				
						$logger .= "\r\n---\r\n";
					}			
				}
			
				if( $logging ) {
					$logfile = fopen( LOGPATH."approvemember.log", "a" );
					fwrite( $logfile, $logger );
					fclose( $logfile );
				}

				if ( $debug ) {
					$logfile = fopen( LOGPATH."mcapplog.log", "a" );
					$out =ob_get_clean();
					fwrite( $logfile, $out );
					fclose( $logfile );		
				}
			}
		}
	}

	function acl_wlm_unapprove_user( $id, $levels ) {
		
		ob_start();
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		/* Setup Logging */
		if (!file_exists(dirname( __FILE__ ).'/logs')) {
			mkdir(dirname( __FILE__ ).'/logs', 0775, true);
		}
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		/* End logging setup */		
		date_default_timezone_set("US/Hawaii");
		
		$logging = get_option("twpw_custommc_logging");
		if ( $logging == "yes") {
			$logging = true;
		} else {
			$logging = false;
		}

		$debug = get_option("twpw_custommc_listdebug");
		if ( $debug == "yes") {
			$debug = true;
		} else {
			$debug = false;
		}
		
		$live = get_option("twpw_custommc_livetest");
		if ( $live == "yes") {
			$live = true;
		} else {
			$live = false;
		}
		
		$logger = '';
		require_once('mailchimp/vendor/autoload.php');
		$settings = get_option("twpw_custommc");
		$api_key = $settings['mcapikey'];
		$dc = $settings['mcdc'];
		
		$mailchimp = new \MailchimpMarketing\ApiClient();
		$mailchimp->setConfig([
				'apiKey' => $api_key,
				'server' => $dc
		]);

		try {
				$response = $mailchimp->ping->get();	
		} catch (Exception $e) {
				$exception = (string) $e->getResponse()->getBody();
				$exception = json_decode($exception);
				if ( $debug ){
					echo 'An error has occurred: '.$exception->title.' - '.$exception->detail. "\r\n\r\n";
				}
		} finally {			
			$wlmlevels = wlmapi_get_member_levels($id); //Using the member ID, get the membership level details. We're going to use this information to find those that need approval.	

			if ( $debug ) {
				echo 'Date: '. date("m/d/Y H:i:s").' ('.date("O").') GMT'."\r\n\r\n";
				echo "User ID: " .$id;
				echo "\r\n\r\n";		
				echo 'Post: ';
				$postexp = var_export( $_POST, true );
				echo $postexp;
				echo "\r\n\r\n";		
				$levexp = var_export( $levels, true );
				echo 'Levels: '.$levexp;
				echo "\r\n\r\n";
				$levexp = var_export ( $wlmlevels, true );
				echo 'WLM Levels: '.$levexp;
				echo "\r\n\r\n";
				$sett = var_export ( $settings, true );
				// echo 'TWPW CustomMC:';
				// echo $sett."\r\n\r\n";
			}

			//get the user object so we can grab their details to add to Mailchimp
			$user = get_user_by( 'id', $id );
			$firstname = $user->user_firstname;
			$lastname = $user->user_lastname;
			$useremail = $user->user_email;
			$wlmaction = $_POST['WishListMemberAction'];
			$levelaction = $_POST['level_action'];

			foreach( $levels as $k => $levid ) {
				if ( ( $settings[$levid]['mclistid'] ) ) {
					$mclistid = $settings[$levid]['mclistid'];
				} else {
					$mclistid = false;
				}
				
				if ( $mclistid == false ) {
					if ( $debug ) {
						echo "No List"; 
						echo "\r\n\r\n"; 
						$logfile = fopen( LOGPATH."mcremlog.log", "a" );
						$out =ob_get_clean();
						fwrite( $logfile, $out );
						fclose( $logfile );			
					}
					
					if ( $logging ) {
						$logger = date("m/d/Y H:i:s"). '('. date ("O") .' GMT) '.$firstname.' '.$lastname.'('.$id.' '.$levid.') not removed from Mailchimp.'."\r\n";
						$logfile = fopen( LOGPATH."removemember.log", "a" );
						fwrite( $logfile, $logger );
						fclose( $logfile );				
					}
				} else { 
					if ( $debug ) {
						echo "List: " . $mclistid; 
						echo "\r\n\r\n"; 
						$logfile = fopen( LOGPATH."mcremlog.log", "a" );
						$out =ob_get_clean();
						fwrite( $logfile, $out );
						fclose( $logfile );					
					}

					$double_optin = (empty($settings[$levid]['dblopt']))?true:false;
					$unsub = (empty($settings[$level]['unsub']))?false:true;
					$send_welcome = (empty($settings[$levid]['sendwel']))?false:true;
					$send_goodbye = (empty($settings[$levid]['sendbye']))?false:true;
					$send_notify = (empty($settings[$levid]['sendnotify']))?false:true;
					$groupings = array(); // create groupings array
					
					if( !empty( $settings[$levid]['mcgroup'] ) ) { // if there are groups
						foreach( $settings[$levid]['mcgroup'] as $group ) { // go through each group that's been set
							$group = explode('::',$group); // divide the group as top id and bottom name
							$groups[$group[0]][] = $group[1]; 
						}
						foreach($groups as $group_id => $group) {
							$groupings[] = array('id'=>$group_id, 'groups' => $group);
						}
						
					}
					// Setup the array to send to Mailchimp
					global $wpdb;
					$merge_vars = array (
										 'FNAME' => $firstname,
										 'LNAME' => $lastname,
										 'GROUPINGS' => $groupings,
										);
					$merge_vars = array_merge($merge_vars, $settings[$levid]['merge_vars']);

					// For PDT ONLY
					$merge_vars['JOINED'] = current_time('Y-m-d');
					$previous_join_date = get_user_meta( $id, 'wlm_join_date', false );
					if ( !empty ( $previous_join_date ) ) {
						echo 'join date of '.$merge_vars['JOINED'].' still set'."\r\n\r\n";
						$logger1 = 'join date of '.$merge_vars['JOINED'].' still set';
					} 

					$email_type = 'html';
					$update_existing = TRUE;
					$replace_interests = TRUE;	
					$delete_member = FALSE;
					$emailmd5 = md5( $useremail );
							
					if ( $debug ) { 
						// echo '$mailchimp->patch(\'lists/mclistid/members/\'. $emailmd5, ['."\r\n";
						// echo '\'status\' => \'subscribed\','."\r\n";
						// echo '\'merge_fields\' => array ('."\r\n";	
						// echo '\'FNAME\' => '.$firstname .','."\r\n";
						// echo '\'LNAME\' => '.$lastname .','."\r\n";
						// echo '),'."\r\n";
						// echo '\'interests\' => array('."\r\n";
						// $inum = 0;
						// $interestgroup = array();
						// foreach ( $groupings[$inum]['groups'] as $k => $v ) {
							// echo 'Key: '.$k.' Value: '.$v."\r\n";
							// echo $v .' =>  false,' . "\r\n";
							// $inum ++;							
						// }
						// echo '),'."\r\n";
						// echo ']);'."\r\n";
						// $logfile = fopen( LOGPATH."mcremlog.log", "a" );
						// $out =ob_get_clean();
						// fwrite( $logfile, $out );
						// fclose( $logfile );					
					}
					
					
					
					if ( $live ) {	

						$mcstring = '';
						$inum = 0;
						$interestgroup = array();
						foreach ( $groupings[$inum]['groups'] as $k => $v ) {
							$mcstring .= $v .' =>  false,' . "\r\n";
							$inum ++;							
						}

						try {
							echo 'Here: '."\r\n";
							$logfile = fopen( LOGPATH."mcremlog.log", "a" );
							$out =ob_get_clean();
							fwrite( $logfile, $out );
							fclose( $logfile );								
							$result = $mailchimp->post('lists/'.$mclistid.'/members/'. $emailmd5, [ 'status' => 'subscribed', 'merge_fields' => array('FNAME' => $firstname,'LNAME' => $lastname)]);
							echo var_export ( $result, true)."\r\n";
							$logfile = fopen( LOGPATH."mcremlog.log", "a" );
							$out =ob_get_clean();
							fwrite( $logfile, $out );
							fclose( $logfile );								
						} catch (Exception $e) {
							// $exception = (string) $e->getResponse()->getBody();
							// $exception = json_decode($exception);
							if ( $debug ){
								// echo 'An error has occurred: '.$exception->title.' - '.$exception->detail. "\r\n\r\n";
								echo 'An error has occurred: ';
								var_export ( $e, true). "\r\n\r\n";
							}
						}
												
						if ( $debug ) {
							$logfile = fopen( LOGPATH."mcremlog.log", "a" );
							$out =ob_get_clean();
							fwrite( $logfile, $out );
							fclose( $logfile );							
						}					
														
						if ( $mailchimp->errorCode ){
							
							if ( $debug ) {
								echo "Unable to load listUnsubscribe()!\n\r"; 
								echo "\tCode=".$mailchimp->errorCode."\n\r";
								echo "\tMsg=".$mailchimp->errorMessage."\n\r";
								echo "\r\n"; 
								$logfile = fopen( LOGPATH."mcremlog.log", "a" );
								$out =ob_get_clean();
								fwrite( $logfile, $out );
								fclose( $logfile );								
							}
							
							if ( $logging ) {
								$logger .= "Unable to load listUnsubscribe()!\n\r";
								$logger .= "\tCode=".$mailchimp->errorCode."\n\r";
								$logger .= "\tMsg=".$mailchimp->errorMessage."\n\r";				
							}
							
						} else {
							if ( $logging ) {
								$logger .= date("m/d/Y H:i:s"). '('. date ("O") .' GMT) Removed '.$firstname .'('.$id.') for Level: '.$levid.' from Mailchimp List: '.$mclistid. 'by '.$wlmaction.' ('.$levelaction.')'."\r\n";
								if ( $groupings ) {
									$logger .= 'for groups: '.var_export( $groupings, true )."\r\n";
								}
								$logger .= ' '.$logger1;
								$logger .= "\n\r---\n\r";
							}
						}
					} else {
						if ( $debug ) {
							echo 'Call made: $mailchimp->call( /lists/unsubscribe, '. $myarr .')';
							echo "\r\n";
						}
						
						if ( $logging ) {
							$logger .= date("m/d/Y H:i:s"). '('. date ("O") .' GMT) Removed as test '.$firstname .'('.$id.') for Level: '.$levid.' from Mailchimp List: '.$mclistid. 'by '.$wlmaction.' ('.$levelaction.')'."\r\n";
							if ( $groupings ) {
								$logger .= 'for groups: '.var_export( $groupings, true )."\r\n";
							}
							$logger .= ' '.$logger1;				
							$logger .= "\r\n---\r\n";
						}			
					}
				
					if( $logging ) {
						$logfile = fopen( LOGPATH."removemember.log", "a" );
						fwrite( $logfile, $logger );
						fclose( $logfile );
					}

					if ( $debug ) {
						$logfile = fopen( LOGPATH."mcremlog.log", "a" );
						$out =ob_get_clean();
						fwrite( $logfile, $out );
						fclose( $logfile );		
					}
				}
			}
		} // finally
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
	
}

if ( !isset ($twpw_custom_mc) ){
	//setup our extension class
	$twpw_custom_mc = new twpw_custom_mc;
	twpw_custom_mc::init();
}

register_activation_hook ( __FILE__, array(&$twpw_custom_mc, 'twpw_custom_mc_activate' ) );
add_action('admin_head', array (&$twpw_custom_mc, 'twpw_custommc_admin_register_head' ) );

add_action ( 'wishlistmember_approve_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
add_action ( 'wishlistmember_add_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
// add_action ( 'wishlistmember_remove_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
add_action ( 'wishlistmember_unapprove_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
add_action ( 'wishlistmember_unconfirm_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
add_action ( 'wishlistmember_confirm_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
add_action ( 'wishlistmember_cancel_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );
add_action ( 'wishlistmember_uncancel_user_levels', array( &$twpw_custom_mc, 'acl_wlm_test' ), 30, 2 );

add_action ( 'wishlistmember_approve_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );
add_action ( 'wishlistmember_add_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );
add_action ( 'wishlistmember_confirm_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );
add_action ( 'wishlistmember_uncancel_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );

// add_action ( 'wishlistmember_remove_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );
add_action ( 'wishlistmember_unapprove_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );
add_action ( 'wishlistmember_unconfirm_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );
add_action ( 'wishlistmember_cancel_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );

?>
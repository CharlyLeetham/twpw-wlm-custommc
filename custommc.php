<?php
/*
Plugin Name: TWPW Custom MC signup
Plugin URI: http://askcharlyleetham.com
Description: Mailchimp Signup
Version: 2.04
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
Version 2.03 - Adding a class, removed WooCommerce support. upgrading to Mailchimp 3.0 api, adding support for mailchimp tags.
Version 2.04 - Added support for Mailchimp Workflow
*/


class twpw_custom_mc {

	public $acl_plugin_dir = WP_PLUGIN_DIR . '/twpw-wlm-custommc';

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
			require_once( dirname(__FILE__) . '/admin/menu.php' );
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

	function acl_wlm_approve_user( $id, $levels ) {

		ob_start();
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );

		/* Setup Logging */
		if (!file_exists(dirname( __FILE__ ).'/logs')) {
			mkdir(dirname( __FILE__ ).'/logs', 0775, true);
		}

		/* End logging setup */

		date_default_timezone_set("US/Hawaii");
		$logging = get_option("twpw_custommc_logging");
		if ( $logging == "yes") {
			$logging = true;
		} else {
			$logging = false;
		}

		$logger = '';
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

		/*Initialise the Mailchmip API */
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();

		/* Get the settings for this plugin */
		$settings = (array) get_option("twpw_custommc");

		$wlmlevels = wlmapi_get_member_levels($id); //Using the member ID, get the membership level details. We're going to use this information to find those that need approval.

		/* Get the User details */
		//get the user object so we can grab their details to add to Mailchimp
		$user = get_user_by( 'id', $id );
		$firstname = $user->user_firstname;
		$lastname = $user->user_lastname;
		$useremail = $user->user_email;
		$subemailhash = md5 ( $useremail );

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

		if ( $logging ) {
			$logger .= "Add Member Action triggered \r\n";
			$logger .= 'Date: '. date("m/d/Y H:i:s").' ('.date("O").') GMT'."\r\n";
		  $logger .= "User ID: " .$id."\r\n\r\n";
		}

		//set our actions
		$wlmaction = $_POST['WishListMemberAction'];
		$levelaction = $_POST['level_action'];
		$memaction = "add";

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
					$logger .= date("m/d/Y H:i:s"). '('. date ("O") .' GMT) '.$firstname.' '.$lastname.'('.$id.' '.$levid.') not added to Mailchimp.'."\r\n";
					$logfile = fopen( LOGPATH."approvemember.log", "a" );
					fwrite( $logfile, $logger );
					fclose( $logfile );
				}

			} else {
				if ( $debug ) {
					echo "List: " . $mclistid;
					echo "\r\n\r\n";
				}

				$groupings = twpw_custom_mc::acl_get_mem_groups( $levid, $mclistid, $memaction );

				$tags = twpw_custom_mc::acl_get_mem_tags( $levid, $mclistid, $memaction );

				// Setup the array to send to Mailchimp
				global $wpdb; //is thi needed?

				$merge_vars = array (
									 'FNAME' => $firstname,
									 'LNAME' => $lastname,
									);
				// For PDT ONLY
				$merge_vars['JOINED'] = current_time('Y-m-d');
				$merge_vars['TRIGGER'] = $settings[$levid]['mcworkflow'][0];
				$logger .= 'Trigger: '.var_export( $settings[$levid]['mcworkflow'][0], true)."\r\n";
				$previous_join_date = get_user_meta( $id, 'wlm_join_date', false );
				if ( empty ( $previous_join_date ) ) {
					add_user_meta ( $id, 'wlm_join_date', $merge_vars['JOINED'] );
					echo 'join date of '.$merge_vars['JOINED'].' added'."\r\n\r\n";
					$logger .= 'join date of '.$merge_vars['JOINED'].' added';
				} elseif ( $settings[$level[$levid]]['update_join_date'] == 'yes' ) {
					update_user_meta ( $id, 'wlm_join_date', $merge_vars['JOINED'] );
					echo 'join date updated from '.$previous_join_date.' to: '.$merge_vars['JOINED']."\r\n";
					$logger .= 'join date updated from '.$previous_join_date.' to: '.$merge_vars['JOINED'];
				} else {
					echo 'join date not updated from '.$previous_join_date[0]."\r\n";
					$logger .= 'join date not updated from '.$previous_join_date[0];
				}

				if ( $live ) {
					$userchange = twpw_custom_mc::acl_change_user_mc ( 'add', $levid, $mclistid, $id, $groupings, $tags, $merge_vars );
					/*
					add - the action, add or remove a users
					$levid - WLM level
					$mclistid - the Mailchimp we're adding to.
					$groupings - the Interest groups
					$tags - Tags for the member
					$merge_vals - Merge_vals needed by mailchimp.
					*/

					if ( $logging ) {
						$logger .= "Memaction: ".var_export( $memaction, true )."\r\n\r\n";
						$logger .= "Groups for export \r\n\r\n";
						$logger .= var_export( $groupings, true )."\r\n\r\n";
						$logger .= var_export ( $userchange, true )."\r\n\r\n";
					}

					if( $logging ) {
						$logfile = fopen( LOGPATH."approvemember.log", "a" );
						fwrite( $logfile, $logger );
						fclose( $logfile );
					}
				} /* End live function */


			} /*If there is no list / list tested */

			if( $logging ) {
				$logfile = fopen( LOGPATH."approvemember.log", "a" );
				fwrite( $logfile, $logger );
				fclose( $logfile );
			}

			if ( $debug ) {
				$logfile = fopen( LOGPATH."mcapplog.log", "a" );
				$out = ob_get_clean();
				fwrite( $logfile, $out );
				fclose( $logfile );
			}
		} /* List loop */
	} /* End Approve Member */

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

		/*Initialise the Mailchmip API */
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();

		/* Get the settings for this plugin */
		$settings = (array) get_option("twpw_custommc");

		/* Get the User details */
		//get the user object so we can grab their details to add to Mailchimp
		$user = get_user_by( 'id', $id );
		$firstname = $user->user_firstname;
		$lastname = $user->user_lastname;
		$useremail = $user->user_email;
		$subemailhash = md5 ( $useremail );

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
		}

		//Setting our actions for testing
		$wlmaction = $_POST['WishListMemberAction'];
		$levelaction = $_POST['level_action'];
		$memaction = "remove";

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
				}

				if ( $logging ) {
					$logger .= date("m/d/Y H:i:s"). '('. date ("O") .' GMT) '.$firstname.' '.$lastname.'('.$id.' '.$levid.') not removed from Mailchimp.'."\r\n";
				}
			} else {
				if ( $debug ) {
					echo "List: " . $mclistid;
					echo "\r\n\r\n";
				}

				$groupings = twpw_custom_mc::acl_get_mem_groups( $levid, $mclistid, $memaction );
				$tags = twpw_custom_mc::acl_get_mem_tags( $levid, $mclistid, $memaction );

				// Setup the array to send to Mailchimp
				global $wpdb; // is this needeD?


				// For PDT ONLY
				$merge_vars['JOINED'] = current_time('Y-m-d');
				$previous_join_date = get_user_meta( $id, 'wlm_join_date', false );
				if ( !empty ( $previous_join_date ) ) {
					echo 'join date of '.$merge_vars['JOINED'].' still set'."\r\n\r\n";
					$logger .= 'join date of '.$merge_vars['JOINED'].' still set';
				}

				if ( $live ) {

					$userchange = twpw_custom_mc::acl_change_user_mc ( 'remove', $levid, $mclistid, $id, $groupings, $tags, $merge_vars );
					/*
					add - the action, add or remove a users
					$levid - WLM level
					$mclistid - the Mailchimp we're adding to.
					$groupings - the Interest groups
					$tags - Tags for the member
					$merge_vars - Merge_vars needed by mailchimp.
					*/

					if( $logging ) {
						$logger = date("m/d/Y H:i:s"). '('. date ("O") .' GMT) '.$firstname.' '.$lastname.'('.$id.' '.$levid.') removed from Mailchimp.'."\r\n\r\n";
					}
				}
			} /* there was something to do with the list */

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
		} /* list loop */
	}


	public static function get_mailchimp_lists( $mclistid, $wlmlevelid ) {
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();
		$settings = (array) get_option( 'twpw_custommc' );
		$api_key = isset( $settings['mcapikey'] ) ? trim( $settings['mcapikey'] ) : '';

		if ( $api_key === '' ) {
			return 'Please enter your Mailchimp API before continuing<br />';
		}

		$alllists = array();

		try {
			$list_info = $twpw_custommc_mcapi->lists->getAllLists();
			if ( isset( $list_info->lists ) && is_array( $list_info->lists ) ) {
				$alllists = $list_info->lists;
			}
		} catch ( Exception $e ) {
			$exception = (string) $e->getResponse()->getBody();
			$exception = json_decode( $exception );
			$title = isset( $exception->title ) ? $exception->title : 'Unknown error';
			$detail = isset( $exception->detail ) ? $exception->detail : '';
			return 'An error has occurred: ' . esc_html( $title . ( $detail ? ' - ' . $detail : '' ) ) . '<br />';
		}

		$mailchimplists = '<select class="mclistid" name="twpw_custommc[' . $wlmlevelid . '][mclistid]">';
		$mailchimplists .= '<option value="0">No list</option>';

		foreach ( $alllists as $list1 ) {
			if ( is_object( $list1 ) ) {
				$list_id = isset( $list1->id ) ? (string) $list1->id : '';
				$list_name = isset( $list1->name ) ? (string) $list1->name : $list_id;
			} else {
				$list_id = (string) $list1;
				$list_name = $list_id;
			}

			if ( $list_id === '' ) {
				continue;
			}

			$selected_attr = ( (string) $mclistid === $list_id ) ? ' selected="selected"' : '';
			$mailchimplists .= '<option value="' . esc_attr( $list_id ) . '"' . $selected_attr . '>' . esc_html( $list_name ) . '</option>';
		}

		$mailchimplists .= '</select>';

		return $mailchimplists;
	}

	public static function acl_get_interest_groups( $listid, $levelid = NULL, $ajax = null ) {
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();
		$settings = (array) get_option( 'twpw_custommc' );
		$api_key = isset( $settings['mcapikey'] ) ? trim( $settings['mcapikey'] ) : '';
		$dc = isset( $settings['mcdc'] ) ? trim( $settings['mcdc'] ) : '';

		if ( empty( $listid ) || $api_key === '' || $dc === '' ) {
			return array();
		}

		try {
			$response1 = $twpw_custommc_mcapi->lists->getListInterestCategories( $listid );
		} catch ( Exception $e ) {
			return array();
		}

		$mccats = array();
		if ( isset( $response1->categories ) && is_array( $response1->categories ) ) {
			$mccats = $response1->categories;
		}

		$catarr = array();

		foreach ( $mccats as $category ) {
			if ( ! is_object( $category ) || empty( $category->id ) ) {
				continue;
			}

			$cat_id = (string) $category->id;
			$title = isset( $category->title ) && $category->title !== '' ? (string) $category->title : $cat_id;

			$catarr[ $title ] = array(
				'id' => $cat_id,
				'title' => $title,
				'groups' => array(),
			);

			$data = array(
				'count' => 1000,
			);

			$url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $listid . '/interest-categories/' . $cat_id . '/interests';
			$response_raw = twpw_custom_mc::acl_mc_curl_connect( $url, 'GET', $api_key, $data );

			if ( empty( $response_raw ) ) {
				continue;
			}

			$response_json = json_decode( $response_raw );
			$interests = array();

			if ( isset( $response_json->interests ) && is_array( $response_json->interests ) ) {
				$interests = $response_json->interests;
			}

			foreach ( $interests as $interest ) {
				if ( ! is_object( $interest ) || empty( $interest->id ) ) {
					continue;
				}

				$catarr[ $title ]['groups'][] = array(
					'name' => isset( $interest->name ) ? (string) $interest->name : (string) $interest->id,
					'id' => (string) $interest->id,
				);
			}
		}

		return $catarr;
	}

	public static function acl_get_tags( $listid, $levelid = NULL, $ajax = null ) {
		$settings = (array) get_option( 'twpw_custommc' );
		$api_key = isset( $settings['mcapikey'] ) ? trim( $settings['mcapikey'] ) : '';
		$dc = isset( $settings['mcdc'] ) ? trim( $settings['mcdc'] ) : '';

		if ( empty( $listid ) || $api_key === '' || $dc === '' ) {
			return '';
		}

		$data = array(
			'count' => 1000,
		);
		$url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $listid . '/tag-search/';

		$response_raw = twpw_custom_mc::acl_mc_curl_connect( $url, 'GET', $api_key, $data );

		if ( empty( $response_raw ) ) {
			return '';
		}

		$response1 = json_decode( $response_raw );
		$mclists = array();

		if ( isset( $response1->tags ) && is_array( $response1->tags ) ) {
			$mclists = $response1->tags;
		}

		$selected_tags = array();
		if ( $levelid !== NULL && ! empty( $settings[ $levelid ]['mctag'] ) ) {
			$selected_tags = (array) $settings[ $levelid ]['mctag'];
		}

		$mailchimptags = '<select multiple="multiple" class="mctag" name="twpw_custommc[' . $levelid . '][mctag][]">';

		foreach ( $mclists as $list1 ) {
			if ( is_object( $list1 ) ) {
				$tag_value = isset( $list1->name ) ? (string) $list1->name : ( isset( $list1->id ) ? (string) $list1->id : '' );
				$tag_label = isset( $list1->name ) ? (string) $list1->name : $tag_value;
			} else {
				$tag_value = (string) $list1;
				$tag_label = $tag_value;
			}

			if ( $tag_value === '' ) {
				continue;
			}

			$selected_attr = in_array( $tag_value, $selected_tags, true ) ? ' selected="selected"' : '';
			$mailchimptags .= '<option value="' . esc_attr( $tag_value ) . '"' . $selected_attr . '>' . esc_html( $tag_label ) . '</option>';
		}

		$mailchimptags .= '</select>';
		return $mailchimptags;
	}

	public static function acl_get_workflow( $listid, $levelid = NULL, $ajax = null ) {
		$settings = (array) get_option( 'twpw_custommc' );
		$api_key = isset( $settings['mcapikey'] ) ? trim( $settings['mcapikey'] ) : '';
		$dc = isset( $settings['mcdc'] ) ? trim( $settings['mcdc'] ) : '';

		if ( empty( $listid ) || $api_key === '' || $dc === '' ) {
			return '';
		}

		$data = array(
			'count' => 1000,
		);
		$url = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $listid . '/merge-fields/4';

		$response_raw = twpw_custom_mc::acl_mc_curl_connect( $url, 'GET', $api_key, $data );

		if ( empty( $response_raw ) ) {
			return '';
		}

		$response1 = json_decode( $response_raw );
		$mclists = array();

		if ( isset( $response1->options->choices ) && is_array( $response1->options->choices ) ) {
			$mclists = $response1->options->choices;
		}

		$selected_workflows = array();
		if ( $levelid !== NULL && ! empty( $settings[ $levelid ]['mcworkflow'] ) ) {
			$selected_workflows = (array) $settings[ $levelid ]['mcworkflow'];
		}

		$mailchimptags = '<select class="mcworkflow" name="twpw_custommc[' . $levelid . '][mcworkflow][]">';

		foreach ( $mclists as $list1 ) {
			if ( is_object( $list1 ) ) {
				$label = isset( $list1->name ) ? (string) $list1->name : (string) $list1;
				$value = isset( $list1->value ) ? (string) $list1->value : $label;
			} else {
				$label = (string) $list1;
				$value = $label;
			}

			if ( $value === '' ) {
				continue;
			}

			$selected_attr = in_array( $value, $selected_workflows, true ) ? ' selected="selected"' : '';
			$mailchimptags .= '<option value="' . esc_attr( $value ) . '"' . $selected_attr . '>' . esc_html( $label ) . '</option>';
		}

		$mailchimptags .= '</select>';
		return $mailchimptags;
	}

	public static function acl_change_user_mc ( $action, $levid, $listid, $user, array $groupings, array $tags, array $merge_vals ) {

	/*
	add - the action, add or remove a users
	$levid - WLM level
	$mclistid - the Mailchimp we're adding to.
	$groupings - the Interest groups
	$tags - Tags for the member
	$merge_vals - Merge_vals needed by mailchimp.

		/* this function will modify a Mailchimp entry for a given user. It can be called by either the Add Level or Remove Level action hooks */


		$logger = "\r\n"."Groupings: ". var_export( $groupings, true )."\r\n";
		$logger .= "\r\n\r\n";

		if ( $logger ) {
			$logfile = fopen( LOGPATH."acladdmember.log", "a" );
			fwrite( $logfile, $logger );
			fclose( $logfile );
		}

		if ( !$action || !$listid || !$levid || !$user ) {
			$ret = "Something isn't set: \r\n";
			$ret = "Action: ".var_export ( $action, true)." listid: ".var_export( $action, true )." levid: ". var_export( $levid, true )." user: ". var_export ( $user, true )."\r\n";
			return $ret; }

		/* Get the settings and setup the Mailchimp API */
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();
		$settings = (array) get_option("twpw_custommc");

		/* Get the User details */
		//get the user object so we can grab their details to add to Mailchimp
		$user = get_user_by( 'id', $user );
		$useremail = $user->user_email;
		$subemailhash = md5 ( $useremail );

		try {
			$response = $twpw_custommc_mcapi->lists->setListMember( $listid, $subemailhash, [
			    "email_address" => $useremail,
			    "status_if_new" => "subscribed",
					"merge_fields" => $merge_vals,
					"interests" => $groupings,
				]
			);
		} catch (Exception $e) {
			$logger = "\r\n"."Something went wrong"."\r\n";
			$exception = (string) $e->getResponse()->getBody();
			$logger .= var_export ($exception, true );
			$logger .= "\r\n\r\n";
		}

		try {
				$response1 = $twpw_custommc_mcapi->lists->updateListMemberTags($listid, $subemailhash, [
		    "tags" => $tags,
				]);
		} catch (Exception $e) {
			$logger .= $e->getMessage(). "\n";
			$exception = (string) $e->getResponse()->getBody();
			$logger .= var_export ($exception, true );
			$logger .= "\r\n\r\n";
		}
	}

	public static function twpw_custommc_createMCAPI() {
		require_once( dirname(__FILE__) . '/mailchimp/vendor/autoload.php' );
		$settings = (array) get_option("twpw_custommc");
		$api_key = $settings['mcapikey'];
		$dc = $settings['mcdc'];
		$twpw_custommc_mcapi = new \MailchimpMarketing\ApiClient();
		$twpw_custommc_mcapi->setConfig([
				'apiKey' => $api_key,
				'server' => $dc
		]);

		return $twpw_custommc_mcapi;

	}

	public static function acl_mc_curl_connect( $url, $request_type, $api_key, $data = array() ) {

		if( $request_type == 'GET' ) {
			$url .= '?' . http_build_query($data);
		}

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

	public static function acl_get_mem_groups ( $levid = NULL, $listid = NULL, $memaction = NULL ) {

		$logging = get_option("twpw_custommc_logging");
		if ( $logging == "yes") {
			$logging = true;
		} else {
			$logging = false;
		}

	  if ( !$levid || !$listid || !$memaction ) { return; }

		/* Get all the Interest Groups from Mailchimp, so we can populate a full list when updating the member. This will take into consideratio being removed from levels as well. */
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();
	  $settings = (array) get_option("twpw_custommc");
		$api_key = $settings['mcapikey'];
		$dc = $settings['mcdc'];

		$response1 = $twpw_custommc_mcapi->lists->getListInterestCategories($listid);
		$mccats = $response1->categories;
		$catarr = array();

		foreach ($mccats as $k) {
			$newcatarr = array();
			$catarr[$k->title]['id'] = $k->id;
			$catarr[$k->title]['title'] = $k->title;

			$data = array (
				"count" => 1000
			);
			$url = 'https://'. $dc .'.api.mailchimp.com/3.0/lists/'. $listid.'/interest-categories/'.$k->id.'/interests';
			$request_type = "GET";
			$response1 = twpw_custom_mc::acl_mc_curl_connect( $url, $request_type, $api_key, $data );
		  $interests = json_decode( $response1 );
			$ia = $interests->interests;
			foreach ( $ia as $v ) {
				$newcatarr[] = array ( "name" => $v->name, "id" => $v->id );
			}
		}

		$logger = "\r\n"."New Catt: ". var_export( $newcatarr, true )."\r\n";
		$logger .= "\r\n\r\n";
		$logger .= "MCGroup: ". var_export( $settings[$levid]['mcgroup'], true)."\r\n\r\n";

		// if ( $logger ) {
			$logfile = fopen( LOGPATH."aclgroups.log", "a" );
			fwrite( $logfile, $logger );
			fclose( $logfile );
		// }

	  $groupings = array(); // create groupings array
	  if( !empty( $settings[$levid]['mcgroup'] ) ) { // if there are groups
			$mygroups = $settings[$levid]['mcgroup'];
			foreach ( $newcatarr as $k => $v ) {
				if ( in_array ( $v["id"] , $mygroups )) {
					if ( $memaction == 'add' ) {
						$groupings[$v["id"]] = true;
					} elseif ($memaction == 'remove') {
						$groupings[$v["id"]] = false;
					}
				} else {
					$groupings[$v["id"]] = false;
				}
			}
	  }

	  return $groupings;

	}

	public static function acl_get_mem_tags ( $levid = NULL, $listid = NULL, $memaction = NULL ) {

		$logging = get_option("twpw_custommc_logging");
		if ( $logging == "yes") {
			$logging = true;
		} else {
			$logging = false;
		}

	  if ( !$levid || !$listid || !$memaction ) { return; }

		/* Get all the Interest Groups from Mailchimp, so we can populate a full list when updating the member. This will take into consideratio being removed from levels as well. */

	  $settings = (array) get_option("twpw_custommc");
		$twpw_custommc_mcapi = twpw_custom_mc::twpw_custommc_createMCAPI();

		$tags = array(); // create a tag
		if( !empty( $settings[$levid]['mctag'] ) ) { // if there are tag
			foreach( $settings[$levid]['mctag'] as $tag ) { // go through each tag that's been set
				if ( $memaction == "add" ) {
					$tags[] = array ( 'name' => $tag, 'status' => 'active');
				} elseif ( $memaction == "remove" ) {
					$tags[] = array ( 'name' => $tag, 'status' => 'inactive');
				}
			}
		}
	  return $tags;
	}

} /* End of Class */

if ( !isset ($twpw_custom_mc) ){
	//setup our extension class
	$twpw_custom_mc = new twpw_custom_mc;
	twpw_custom_mc::init();
}

register_activation_hook ( __FILE__, array(&$twpw_custom_mc, 'twpw_custom_mc_activate' ) );
add_action('admin_head', array (&$twpw_custom_mc, 'twpw_custommc_admin_register_head' ) );

add_action ( 'wishlistmember_approve_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );
add_action ( 'wishlistmember_add_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );
add_action ( 'wishlistmember_confirm_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );
add_action ( 'wishlistmember_uncancel_user_levels', array( &$twpw_custom_mc, 'acl_wlm_approve_user' ), 30, 2 );

add_action ( 'wishlistmember_remove_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );
add_action ( 'wishlistmember_unapprove_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );
add_action ( 'wishlistmember_unconfirm_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );
add_action ( 'wishlistmember_cancel_user_levels', array( &$twpw_custom_mc, 'acl_wlm_unapprove_user' ), 30, 2 );

?>








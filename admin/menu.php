<?php

add_action('admin_menu', 'twpw_admin_menu');
add_filter('twpw_admin_plugin_menu', 'addThisMenu');

function twpw_admin_menu() {
	$firstmenu="wp_twpw_admin";

	if(!defined('TWPWTOPMENU')){
		add_menu_page('TWPW Plugins', 'TWPW Plugins', 8, 'WPTWPW', $firstMenu, '' );
		define('TWPWTOPMENU','WPTWPW');
	}
	
	add_submenu_page(TWPWTOPMENU, 'Administration - TWPW Plugin Controls', 'TWPW Custom Mailchimp', 8, 'tab=custommctab', 'twpw_admin_subpage');

	unset($GLOBALS['submenu']['WPTWPW'][0]);
	
}

function twpw_admin_subpage() {
	//Get parameter for tab navigation
	$tab = $_GET['tab'];
	$mode = $_GET['mode'];
	
	if ( empty ( $tab ) || $tab == 'custommctab' ) {	
		//Our main div... common to all tabs
		echo '<div class="wrap twpw-wrap">';
		echo '<div class="twpw-admin-header">';
		echo '</div>';
		twpw_admin_plugin_menu();
		twpw_sub_menu();
		if(empty($mode) || $mode == 'twpwcustommcgen'){
			twpwcustommcgen();
		} elseif ( $mode == 'twpwcustommclists' ) {
			twpwcustommclists();
		} elseif ( $mode == 'twpwcustommcdoc' ) {
			twpwcustommcdoc();
		} elseif ( $mode == 'twpwcustommcdeb' ) {
			twpwcustommcdeb();
		} elseif ( $mode == 'twpwcustommclog' ) {
			twpwcustommclog();
		}
		echo "</div>";
	}
	
	//Closing main div
	echo '</div>';
}

function addThisMenu($menus) {
	$menus['TWPW Custom Mailchimp'] = 'custommctab';
	return $menus;
}

function twpw_admin_plugin_menu() {
	$adminurl = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=tab=';
	$menus = array();
	$menus = apply_filters('twpw_admin_plugin_menu',$menus);
	
	echo '<div class="twpw-admin-navigation">';
		echo '<ul>';
		foreach($menus as $menuText => $menuTab) {
			echo '<li ';
			echo ($menuTab == "custommctab")? 'class="current"':"";
			echo'><a href="'. $adminurl.$menuTab.'">'.$menuText.'</a></li>';
		}
		echo '</ul>';
	echo '</div>';
}

function twpw_sub_menu() {
	$adminUrl = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=tab=custommctab';
	//$adminUrl = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=custommctab';
	echo '<div class="twpw-admin-tab-nav">';
		echo '<ul>';
			echo '<li><a href="'.$adminUrl. '&mode=twpwcustommcgen">General Options</a></li>';
			echo '<li><a href="'. $adminUrl. '&mode=twpwcustommclists">List Setup</a></li>';
			echo '<li><a href="'. $adminUrl. '&mode=twpwcustommcdoc">How To Use</a></li>';
			echo '<li><a href="'. $adminUrl. '&mode=twpwcustommcdeb">Debug</a></li>';
			echo '<li><a href="'. $adminUrl. '&mode=twpwcustommclog">Change Log</a></li>';
		echo '</ul>';
	echo '</div>';
}

function twpwcustommcgen() {
	echo '<div class="twpw-admin-content">';
	echo '<div id="icon-options-general" class="icon32"></div><h2>Options - TWPW Custom Mailchimp Plugin</h2>';
	echo '<p>Here you can set the general display options for the TWPW Custom Mailchimp plugin. Please <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wp_twpw_admin&tab=custommctab&mode=twpwcustommcdoc">refer to the documentation</a> for detailed instructions for using this plugin.</p>';

	if(isset($_POST["submit"])){
		$settings = get_option('twpw_custommc');
		$settings['mcapikey'] = $_POST['mcapikey'];
		$settings['mcdc'] = $_POST['mcdc'];
		update_option('twpw_custommc', $settings);
	}
	?>

	<form method="post">
		<?php
		$settings = get_option("twpw_custommc");
		$twpw_mcapikey = $settings['mcapikey'];
		$twpw_mcdc = $settings['mcdc'];
		?>
		<table class="form-table">
			<tr><td align="right"><strong>Mailchimp API Key:</strong></td><td><input type="text" size="25" name="mcapikey" value="<?php echo $twpw_mcapikey;?>" /> <span class="smalltext">(Get your API key from: <a href="http://admin.mailchimp.com/account/api/" target="_blank">http://admin.mailchimp.com/account/api/</a>)</span></td></tr>
			
			<tr><td align="right"><strong>Mailchimp Data Centre:</strong></td><td><input type="text" size="4" name="mcdc" value="<?php echo $twpw_mcdc;?>" /> <span class="smalltext">Log into your Mailchimp account and look at the URL in your browser. You’ll see something like https://us19.admin.mailchimp.com/; the <strong>us19</strong> part is the server prefix. Note that your specific value may be different.</span></td></tr>			
		</table>
		
		<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e('Save General Options') ?>" /></p>
	</form>
	<?php 
}

function twpwcustommclists() {
	global $twpw_custommc_mcapi;
	$debug = get_option( 'twpw_custommc_debug', 'no' );
	$logger = '';
	
	if ( $debug == 'yes' ) {
		/* Setup Logging */		
		date_default_timezone_set("US/Hawaii");

		if (!file_exists(dirname( __FILE__ ).'/logs')) {
			mkdir(dirname( __FILE__ ).'/logs', 0775, true);
		}
		define( 'LOGPATH', dirname( __FILE__ ) . '/logs/' );
		$logger .= 'Date: '. date("m/d/Y H:i:s").' ('.date("O").') GMT'."\r\n\r\n";
	}
?>
	<div class="twpw-admin-content">
	<div id="icon-options-general" class="icon32"></div><h2>List Selection - TWPW Custom Mailchimp Plugin</h2>
	<p>Here you can set the Mailchimp list for each level, whether to use double optin; send the welcome email; and unsubscribe from list when removed from level</p>

	<?php
	if( isset($_POST["submit"] ) ){
		$count = 0;
		$err_msg = array();
		$error_occured = false;

		if ( $debug == 'yes' ) {
			$logger .= '$_POST: ';
			$logger .= var_export( $_POST['twpw_custommc'], true );
			$logger .= "\r\n";
		}
		
		$newsettings = $_POST['twpw_custommc'];
		$settings = get_option('twpw_custommc');
		if ( $debug == 'yes' ) {
			$logger .= '$newsettings: ';
			$logger .= var_export( $newsettings, true );
			$logger .= "\r\n";
			$logger .= '$settings: ';
			$logger .= var_export( $settings, true );
			$logger .= "\r\n";
		}
		
		$newsettings['mcapikey']=$settings['mcapikey'];
		if ( $debug == 'yes' ) {
			$logger .= '$newsettings: ';
			$logger .= var_export( $newsettings, true );
			$logger .= "\r\n";
		}			
		update_option('twpw_custommc', $newsettings);
	}

	twpw_custom_mc::twpw_custommc_createMCAPI();  // initialise the Mailchimp api
	
	if( $error_occured ){
		echo '<div align="center" style="font-weight: bold; font-size: 16px; color: #FF0000; margin-bottom: 10px;">Your changes have not been saved. Please scroll down to see the error message(s).</div>';
	} else {
		if ( isset($_POST["submit"] ) ) {
			echo '<div align="center" style="font-weight: bold; font-size: 16px; color: #FF0000; margin-bottom: 10px;">Your changes have been saved!</div>';}
		} ?>

	<form method="post">
			<div style="margin-bottom: 6px; margin-top: 10px; font-size: 16px;"><strong>How To Use This Form</strong></div>
			<div style="margin-bottom: 6px; margin-top: 10px;">For each of your membership levels below, please select the mailchimp you want to use, whether to use double optin; send the welcome email message from mailchimp; and whether to remove the subscriber from the Mailchimp list when they are removed from the associated WLM level.</div>
			
		<table class="form-table">
			<tr>
				<td><strong>Membership Level</strong></td>
				<td><strong>Mailchimp List</strong></td>
				<td><strong>Interest Group</strong></td>
				<td><strong>Update Join Date?</strong></td>
				<td><strong>Disable Double Optin?</strong></td>
				<td><strong>Send Welcome Email</strong></td>
				<td><strong>Unsubscribe on remove?</strong></td>
				<td><strong>Send Goodbye message?</strong></td>
				<td><strong>Notify List Owner?</strong></td>
			</tr>

			<?php
			$settings = get_option('twpw_custommc',false);
			if ( $debug == 'yes' ) {
				$logger .= var_export( $settings, true );
				$logger .= "\r\n";
			}
			$levels = wlmapi_get_levels();
			$levels = $levels["levels"]["level"];
			$count = 0;				
		
			foreach( $levels as $level ) {
				$count += 1;
				?>
				<tr valign="top">
					<?php echo'<td>' . $level['name'] . '</td>'; ?>

					<!-- List all Mailchimp Lists -->
					<td><?php echo twpw_custom_mc::get_mailchimp_lists($settings[$level['id']]['mclistid'],$level['id']) ?></td>
					
					<!-- List groups for Mailchimp List selected -->
					<td class="grouplisting" levelid="<?php echo $level['id']; ?>">
						<?php
						
						if ( $debug == 'yes' ) {
							$logger .= var_export ( $level, true );
							$logger .= "\r\n***\r\n";
							$logger .= var_export( $settings[$level['id']], true );
							$logger .= "\r\n***\r\n";
							$logger .= var_export ( $settings[$level['id']]['mcgroup'], true );
							$logger .= "\r\n***\r\n";
							$logger .= var_export ( $settings[$level['id']]['mclistid'], true );
							$logger .= "\r\n***\r\n";
						}
						
						if ( empty( $settings[$level['id']]['mclistid'] ) ) {
							$settings[$level['id']]['mcgroup'] ='';
						}
						
						if ( !empty( $settings[$level['id']]['mcgroup'] ) ) {
							echo 'LEVEL: '.$settings[$level['id']]['mclistid'].'<br />';
							$mclists = twpw_custom_mc::acl_get_interest_groups( $settings[$level['id']]['mclistid'] );
							// echo 'Lists: '.var_export( $mclists, true ).'<br />';
							// $mclists = $twpw_custommc_mcapi->call('/lists/interest-groupings', array('id'=>$settings[$level['id']]['mclistid']) );
							if ( $debug == 'yes' ) {
								$logger .= "MCGroups: ";
								$logger .= var_export( $mclists, true );
								$logger .= "\r\n";
							}
							
							echo '<select multiple="multiple" name="twpw_custommc['. $level['id'] .'][mcgroup][]" class="mclist">';
								foreach ( $mclists as $mclist ) {
									echo '<option disabled="disabled">** '.$mclist['name'].' **</option>';
									foreach ( $mclist['groups'] as $group => $gvalue ) {
										echo '<option value="'.$gvalue['id'].'" ';
										if( in_array($gvalue['id'], $settings[$level['id']]['mcgroup'] ) )
											echo 'selected="selected" ';
										echo '>'.$gvalue['name'].'</option>';
									}
								}
							echo '</select>';
						}
						?>
					</td>
										
					<td><input type="checkbox" name="twpw_custommc[ <?php echo $level['id']; ?>][update_join_date]" value="yes" 
						<?php 
							if ( $settings[$level['id']]['update_join_date'] == 'yes' ) { 
								echo ' checked="checked" '; 
							} 
						?>
					/>
					</td>					
					
					<td><input type="checkbox" name="twpw_custommc[ <?php echo $level['id']; ?>][dblopt]" value="yes" 
						<?php 
							if ( $settings[$level['id']]['dblopt'] == 'yes' ) { 
							 echo ' checked="checked" '; 
							} 
						?>
					/>
					</td>
					

					<td><input type="checkbox" name="twpw_custommc[ <?php echo $level['id']; ?>][sendwel]" value="yes" 
						<?php if ( $settings[$level['id']]['sendwel'] == 'yes' ) { 
								echo ' checked="checked" '; 
							} 
						?>
					/></td>
					
					<td><input type="checkbox" name="twpw_custommc[<?php echo $level['id']; ?>][unsub]" value="yes" 
						<?php if ( $settings[$level['id']]['unsub'] == 'yes' ) {  
								echo ' checked="checked" '; 
							} 
						?>
					/></td>

					<td><input type="checkbox" name="twpw_custommc[<?php echo $level['id']; ?>][sendbye]" value="yes" 
						<?php if ( $settings[$level['id']]['sendbye'] == 'yes' ) {  
								echo ' checked="checked" '; 
							} 
						?>
					/></td>

					<td><input type="checkbox" name="twpw_custommc[<?php echo $level['id']; ?>][sendnotify]" value="yes" 
						<?php if ( $settings[$level['id']]['sendnotify'] == 'yes' ) {  
							echo ' checked="checked" '; 
							} 
						?>
					/></td>					

				</tr>
				<?php if ( $err_msg[$count] != '' ) { ?>
				<tr><td colspan="4" align="right"><span style="font-weight:bold; color:#FF0000;"><?php echo $err_msg[$count]; ?></span></td></tr>
				<?php } ?>
			<?php
			}
			?>
		</table>
		
		<p class="submit">
		<input type="submit" name="submit" class="button-primary" value="<?php _e('Save Mailchimp Settings') ?>" />
		</p>
	</form>
	<script type="text/javascript">
		(function($){
			$("select.mclistid").change(function() {
				var groupobject=$(this).parent().next("td.grouplisting");
				$.post("<?php echo admin_url("admin-ajax.php"); ?>",{
					action:"twpw_custommc_ig",
					mclistid: $(this).val(),
					levelid: groupobject.attr('levelid')
				},
				function(msg) {
					msg = msg.trim();
					groupobject.html(msg);
					<?php if ( $debug == 'yes' ) {
							echo "console.log(msg);";
						} ?>
				});		
			});
		})(jQuery);
	</script>
	<?php 
	
		if ( $debug == 'yes' ) {
			$logfile = fopen( LOGPATH."twpw-mc-admin-debug.log", "a" );
			fwrite( $logfile, $logger );
			fclose( $logfile );	
		}
	
	}
	
	function twpwcustommcdoc(){
	?>
		<div class="twpw-admin-content">
			<div id="icon-themes" class="icon32"></div><h2>Usage Instructions - TWPW Custom Mailchimp Plugin</h2>
		
			<h3 style="border-bottom: 1px solid #000; width: 75%;">Purpose of the Plugin</h3>	
				<p>The purpose of the TWPW Custom Mailchimp Plugin is to provide site owners with greater control over how members are added to mailchimp when used with the Wishlist Member Plugin.  The integration provided with Wishlist Member does not provide control over the Double Optin or Send Welcome Message settings - and this can be valuable when creating a membership site.  This plugin provides these controls.</p>
				
			<h3 style="border-bottom: 1px solid #000; width: 75%;">Requirements</h3>
				<p>The TWPW Custom Mailchimp Plugin <b>requires Wordpress 3.3.1 or higher and Wishlist Member API Version 2.0</b>. It has been tested for compatibility up to Wordpress 3.3.1 and Wishlist Member ver 2.71.1094</p>
			
			<h3 style="border-bottom: 1px solid #000; width: 75%;">Usage Instructions</h3>
			<p>This plugin provides the ability to select a Mailchimp list for each Wishlist Member level as well as whether to use Double Optin, Send the Mailchimp Welcome Message and whether to unsubscribe a member from that list when they are removed from the level.</p>
				
			<p style="font-size: 14px; text-align:center;"><b>General Options</b></p>
			<p>To use this plugin, you need the Mailchimp api key and the data centre for your account.  You can get the api key by visiting: <a href="http://admin.mailchimp.com/account/api/" target="_blank">http://admin.mailchimp.com/account/api/</a>. To get your data centre, log into your Mailchimp account and look at the URL in your browser. You’ll see something like https://us19.admin.mailchimp.com/; the <strong>us19</strong> part is the server prefix. Note that your specific value may be different. </p>
				
			<p style="font-size: 14px; text-align:center;"><b>List Selection</b></p>
			<p>You will find a row for each of your defined membership levels.</p>
			<p>For each level please select a Mailchimp list from the drop down menu.</p>
			<p>If you wish to disable Double Optin, place a tick in the Double Optin Box</p>
			<p>If you wish to send the Mailchimp Welcome Message, place a tick in the Welcome Message box</p>
			<p>If you wish to remove the member from the list when they are removed from the level, place a tick in the Unsubscribe box</p>
			<p>If you wish to send the member the "goodbye" message when they are removed from the level, place a tick in the Send Goodbye box</p>
			<p>If you wish to let the list owner know a member has been unsubscribed from the list when they are removed from the level, place a tick in the Send Notify box</p>
	<?php }
	
	function twpwcustommcdeb() {?>
	<div class="twpw-admin-content">
	<div id="icon-options-general" class="icon32"></div><h2>Debug Options - TWPW Custom MC Plugin</h2>
	<p>If for some reason this plugin isn't working on your site, this is the tab we will ask you to go to, to set some debug options so we can quickly see what's going on and solve the problem.</p>

	<?php
	if(isset($_POST["submit"])){
		// Get the options into a local display options array
		$debugsetting = $_POST['twpw_custommc_debug'];
		$livetestsetting = $_POST['twpw_custommc_livetest'];
		$listdebugsetting = $_POST['twpw_custommc_listdebug'];
		$loggingsetting = $_POST['twpw_custommc_logging'];
		update_option( 'twpw_custommc_debug', $debugsetting );
		update_option( 'twpw_custommc_livetest', $livetestsetting );
		update_option( 'twpw_custommc_listdebug', $listdebugsetting );
		update_option( 'twpw_custommc_logging', $loggingsetting );
	}
	?>

	<form method="post">
		<?php
		$twpw_custommc_db = get_option("twpw_custommc_debug");
		$twpw_custommc_livetest = get_option("twpw_custommc_livetest");
		$twpw_custommc_listdebug = get_option("twpw_custommc_listdebug");
		$twpw_custommc_logging = get_option("twpw_custommc_logging");
		?>
		<table class="form-table">
			<tr><td align="right"><strong>Settings Debug Mode:</strong></td><td><label>On</label><input type="radio" name="twpw_custommc_debug" value="yes" <?php if ( $twpw_custommc_db == "yes" ) { echo 'checked'; } ?> />&nbsp;&nbsp;<label>Off&nbsp;</label><input type="radio" name="twpw_custommc_debug" value="no" <?php if ( $twpw_custommc_db == "no" ) { echo 'checked="checked"'; } ?> /></td></tr>

			<tr><td align="right"><strong>List Debug Mode:</strong></td><td><label>On</label><input type="radio" name="twpw_custommc_listdebug" value="yes" <?php if ( $twpw_custommc_listdebug == "yes" ) { echo 'checked'; } ?> />&nbsp;&nbsp;<label>Off&nbsp;</label><input type="radio" name="twpw_custommc_listdebug" value="no" <?php if ( $twpw_custommc_listdebug == "no" ) { echo 'checked="checked"'; } ?> /></td></tr>

			<tr><td align="right"><strong>Live Or Testing Mode:</strong></td><td><label>Live</label><input type="radio" name="twpw_custommc_livetest" value="yes" <?php if ( $twpw_custommc_livetest == "yes" ) { echo 'checked'; } ?> />&nbsp;&nbsp;<label>Testing&nbsp;</label><input type="radio" name="twpw_custommc_livetest" value="no" <?php if ( $twpw_custommc_livetest == "no" ) { echo 'checked="checked"'; } ?> /></td></tr>

			<tr><td align="right"><strong>Logging:</strong></td><td><label>On</label><input type="radio" name="twpw_custommc_logging" value="yes" <?php if ( $twpw_custommc_logging == "yes" ) { echo 'checked'; } ?> />&nbsp;&nbsp;<label>Off&nbsp;</label><input type="radio" name="twpw_custommc_logging" value="no" <?php if ( $twpw_custommc_logging == "no" ) { echo 'checked="checked"'; } ?> /></td></tr>
		</table>

		<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e('Save Debug Options') ?>" /></p>
	</form>
	<?php }
	
	function twpwcustommclog(){?>
		<div class="twpw-admin-content">
			<?php
			$change_file = 'http://www.thewpwarrior.com/plugins/twpwcustommc.html';
		
			if(!@readfile($change_file)){
				echo'<div id="icon-themes" class="icon32"></div><h2>Changelog - TWPW Custom Mailchimp Plugin</h2>
						<strong>Version 1.0</strong> - Original Version <br />
						<strong>Version 1.1</strong - Added WooCommerce support and Delete User support <br />
						<strong>Version 1.2</strong> - Added Mailchimp Group support<br />
						<strong>Version 1.3</strong> - Fixed Sequential Add <br />
						<strong>Version 2a</strong> - Tidy up.<br />
						<strong>Version 2.01</strong> - Clean up the code being output to the screen.<br />
						<strong>Version 2.02</strong> - Rewrite to stop people being moved after being added<br />
						<strong>Version 2.03</strong> - Adding a class. Removed WooCommerce Support. Added more detailed logging.<br />			
				<h3 style="border-bottom: 1px solid #000; width: 75%;">Known Issues</h3>
				<p>1 October 2020: Wishlistmember wishlistmember_remove_user_levels action fires when user is added. Reported to Wishlist Products</p>'; 
			}
		}
		
	function twpw_get_interest_groups() {
		twpw_custom_mc::twpw_custommc_createMCAPI();
		global $twpw_custommc_mcapi;
		try {
			$mclists = $twpw_custommc_mcapi->call('/lists/interest-groupings',array('id'=>$_POST['mclistid']));
		} catch (Exception $e) {
			if($e->getCode() == 211) {
				echo "<p>This list has no interest groups.</p>";
			} 
			die();
		}
		echo '<select multiple="multiple" name="twpw_custommc['.$_POST['levelid'].'][mcgroup][]" class="mclist">';
		foreach ($mclists as $mclist) {
			echo '<option disabled="disabled">** '.$mclist['name'].' **</option>';
			foreach ($mclist['groups'] as $group) {				
				$val = str_replace(',','\,',$group['name']);
				$val = $mclist['id'].'::'.$val;
				echo '<option value="'.$val.'">'.$group['name'].'</option>';
			}
		}
		echo '</select>';
		die();
	}
	add_action( 'wp_ajax_twpw_custommc_ig', 'twpw_get_interest_groups' );
	
?>
<?php
/*
	Plugin Name: List.ly
	Plugin URI:  http://wordpress.org/extend/plugins/listly/
	Description: Plugin to easily integrate List.ly lists to Posts and Pages. It allows publishers to add/edit lists, add items to list and embed lists using shortcode. <a href="mailto:support@list.ly">Contact Support</a>
	Version:     1.3
	Author:      Milan Kaneria
	Author URI:  http://brandintellect.in/
*/


if (!class_exists('Listly'))
{
	class Listly
	{
		function __construct()
		{
			$this->Version = 1.3;
			$this->PluginFile = __FILE__;
			$this->PluginName = 'Listly';
			$this->PluginPath = dirname($this->PluginFile) . '/';
			$this->PluginURL = get_bloginfo('wpurl') . '/wp-content/plugins/' . dirname(plugin_basename($this->PluginFile)) . '/';
			$this->SettingsURL = 'options-general.php?page='.dirname(plugin_basename($this->PluginFile)).'/'.basename($this->PluginFile);
			$this->SettingsName = 'Listly';
			$this->Settings = get_option($this->SettingsName);
			//$this->SiteURL = 'http://api.list.ly/v1/';
			$this->SiteURL = 'http://listly-staging.herokuapp.com/v2/';

			$this->SettingsDefaults = array(
				'PublisherKey' => '',
				'Theme' => 'light',
				'Layout' => 'full',
				'Numbered' => 'yes',
				'Image' => 'yes',
				'Items' => 'all',
			);

			$this->PostDefaults = array(
				'method' => 'POST',
				'timeout' => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'decompress' => true,
				'headers' => array('Content-Type' => 'application/json', 'Accept-Encoding' => 'gzip, deflate'),
				'body' => array(),
				'cookies' => array()
			);


			register_activation_hook($this->PluginFile, array(&$this, 'Activate'));

			add_filter('plugin_action_links', array(&$this, 'ActionLinks'), 10, 2);
			add_filter('contextual_help', array(&$this, 'ContextualHelp'), 10, 3);
			add_action('admin_menu', array(&$this, 'AdminMenu'));
			add_action('wp_head', array(&$this, 'WPHead'));
			add_action('wp_ajax_AJAXPublisherAuth', array(&$this, 'AJAXPublisherAuth'));
			add_action('wp_ajax_AJAXListSearch', array(&$this, 'AJAXListSearch'));
			add_shortcode('listly', array(&$this, 'ShortCode'));

			if ($this->Settings['PublisherKey'] == '')
			{
				add_action('admin_notices', create_function('', "print '<div class=\'error\'><p><strong>$this->PluginName:</strong> Please enter Publisher Key on <a href=\'$this->SettingsURL\'>Settings</a> page.</p></div>';"));
			}
		}


		function Activate()
		{
			if (is_array($this->Settings))
			{
				$Settings = array_merge($this->SettingsDefaults, $this->Settings);
				$Settings = array_intersect_key($Settings, $this->SettingsDefaults);

				update_option($this->SettingsName, $Settings);
			}
			else
			{
				add_option($this->SettingsName, $this->SettingsDefaults);
			}
		}


		function ActionLinks($Links, $File)
		{
			static $FilePlugin;

			if (!$FilePlugin)
			{
				$FilePlugin = plugin_basename($this->PluginFile);
			}
	
			if ($File == $FilePlugin)
			{
				$Link = "<a href='$this->SettingsURL'>Settings</a>";

				array_push($Links, $Link);
			}

			return $Links;
		}


		function ContextualHelp($Help, $ScreenId, $Screen)
		{
			global $ListlyPageSettings;

			if ($ScreenId == $ListlyPageSettings)
			{
				$Help = '<p><a href="mailto:support@list.ly">Contact Support</a></p> <p><a target="_blank" href="http://list.ly/publishers/landing">Request Publisher Key</a></p>';
			}

			return $Help;
		}


		function WPHead()
		{
			wp_enqueue_script('jquery');
			//wp_enqueue_script($this->SettingsName, $this->PluginURL.'scripts.js', array('jquery'), '1.0', false);
			//wp_enqueue_style($this->SettingsName, $this->PluginURL.'style.css', false, '1.0', 'screen');
		}


		function WPFooter()
		{
			global $ListlyListStyle;

			print '<script type="text/javascript"> jQuery(document).ready(function ($) { if (!$("#listly-list-style").length) { $("head").append(\'<link id="listly-list-style" rel="stylesheet" href="'.$ListlyListStyle.'" type="text/css" />\'); } }); </script>';
		}


		function AdminPrintScripts()
		{
			wp_enqueue_script('jquery');
			wp_enqueue_script('listly-script', $this->PluginURL.'script.js', false, $this->Version, false);
			wp_localize_script('listly-script', 'Listly', array('Nounce' => wp_create_nonce('ListlyNounce')));
		}

		function AdminPrintStyles()
		{
			wp_enqueue_style('listly-style', $this->PluginURL.'style.css', false, $this->Version, 'screen');
		}


		function AdminMenu()
		{
			global $ListlyPageSettings;

			$ListlyPageSettings = add_submenu_page('options-general.php', 'Listly &rsaquo; Settings', 'Listly', 'manage_options', $this->PluginFile, array(&$this, 'Admin'));

			add_action("admin_print_scripts", array(&$this, 'AdminPrintScripts'));
			add_action("admin_print_styles", array(&$this, 'AdminPrintStyles'));

			add_meta_box('ListlyMetaBox', 'Listly', array(&$this, 'MetaBox'), 'page', 'side', 'default');
			add_meta_box('ListlyMetaBox', 'Listly', array(&$this, 'MetaBox'), 'post', 'side', 'core');
		}


		function Admin()
		{
			if (!current_user_can('manage_options'))
			{
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}

			if (isset($_POST['action']) && $_POST['action'] == 'ListlySaveSettings')
			{
				if (wp_verify_nonce($_POST['nonce'], $this->SettingsName))
				{
					foreach ($_POST as $Key => $Value)
					{
						if (array_key_exists($Key, $this->SettingsDefaults))
						{
							if (is_array($Value))
							{
								array_walk_recursive($Value, array(&$this, 'TrimByReference'));
							}
							else
							{
								$Value = trim($Value);
							}

							$Settings[$Key] = $Value;
						}
					}

					if (update_option($this->SettingsName, $Settings))
					{
						print '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
					}
				}
				else
				{
					print '<div class="error"><p><strong>Security check failed! Settings not saved.</strong></p></div>';
				}
			}

			$Settings = get_option($this->SettingsName);

		?>

			<style type="text/css">

				input.large-text
				{
					width: 98%;
				}

			</style>

			<div class="wrap">

				<h2>Listly Settings</h2>

				<form method="post" action="">

					<table class="form-table listly-table">

						<tr valign="top">
							<th scope="row">
								Publisher Key
								<br /> <a target="_blank" href="http://list.ly/publishers/landing">Request Publisher Key</a>
							</th>
							<td>
								<input name="PublisherKey" type="text" value="<?php print $Settings['PublisherKey']; ?>" class="large-text" />
								<a id="ListlyAdminAuthStatus" href="#">Check Key Status</a> : <span></span>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Theme</th>
							<td>
								<select name="Theme">
									<option value="light" <?php $this->CheckSelected($Settings['Theme'], 'light'); ?>>Light</option>
									<option value="dark" <?php $this->CheckSelected($Settings['Theme'], 'dark'); ?>>Dark</option>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Layout</th>
							<td>
								<select name="Layout">
									<option value="full" <?php $this->CheckSelected($Settings['Layout'], 'full'); ?>>Full</option>
									<option value="short" <?php $this->CheckSelected($Settings['Layout'], 'short'); ?>>Short</option>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Numbered</th>
							<td>
								<select name="Numbered">
									<option value="yes" <?php $this->CheckSelected($Settings['Numbered'], 'yes'); ?>>Yes</option>
									<option value="no" <?php $this->CheckSelected($Settings['Numbered'], 'no'); ?>>No</option>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Image</th>
							<td>
								<select name="Image">
									<option value="yes" <?php $this->CheckSelected($Settings['Image'], 'yes'); ?>>Yes</option>
									<option value="no" <?php $this->CheckSelected($Settings['Image'], 'no'); ?>>No</option>
								</select>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Items</th>
							<td>
								<input name="Items" type="text" value="<?php print $Settings['Items']; ?>" class="regular-text" />
								<span class="description">"all" or any number greater than 0.</span>
							</td>
						</tr>

					</table>

					<input name="nonce" type="hidden" value="<?php print wp_create_nonce($this->SettingsName); ?>" />
					<input name="action" type="hidden" value="ListlySaveSettings" />

					<div class="submit"><input name="" type="submit" value="Save Settings" class="button-primary" /></div>

				</form>

			</div>

		<?php

		}


		function AJAXPublisherAuth()
		{
			define('DONOTCACHEPAGE', true);

			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print '<span class="error">Authorisation failed.</span>';
				exit;
			}

			if (isset($_POST['action'], $_POST['Key']))
			{
				if ($_POST['Key'] == '')
				{
					print '<span class="error">Please enter Publisher Key.</span>';
				}
				else
				{
					$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('key' => $_POST['Key']))));
					$Response = wp_remote_post($this->SiteURL.'publisher/auth.json', $PostParms);

					if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
					{
						print '<span class="error">No connectivity or Listly service not available. Try later.</span>';
					}
					else
					{
						$ResponseJson = json_decode($Response['body'], true);

						if ($ResponseJson['status'] == 'ok')
						{
							print '<span class="info">'.$ResponseJson['message'].'</span>';
						}
						else
						{
							print '<span class="error">'.$ResponseJson['message'].'</span>';
						}
					}
				}
			}

			exit;
		}


		function AJAXListSearch()
		{
			define('DONOTCACHEPAGE', true);

			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print "<div class='ui-state ui-state-error ui-corner-all'><p>Authorisation failed!</p></div>";
				exit;
			}

			if (isset($_POST['Term']))
			{
				if ($_POST['SearchAll'] == 'true')
				{
					$PostParms = array_merge($this->PostDefaults, array('timeout' => 2, 'body' => json_encode(array('term' => $_POST['Term'], 'key' => $this->Settings['PublisherKey']))));
				}
				else
				{
					$PostParms = array_merge($this->PostDefaults, array('timeout' => 2, 'body' => json_encode(array('term' => $_POST['Term'], 'type' => 'publisher', 'key' => $this->Settings['PublisherKey']))));
				}

				$Response = wp_remote_post($this->SiteURL.'autocomplete/list.json', $PostParms);

				if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
				{
					print json_encode(array('message' => '<p class="error">No connectivity or Listly service not available. Try later.</p>'));
				}
				else
				{
					$ResponseJson = json_decode($Response['body'], true);

					if ($ResponseJson['results'] && count($ResponseJson['results']))
					{
						$Results = array();

						foreach ($ResponseJson['results'] as $Result)
						{
							$Results[] = '
							<p>
								<img class="avatar" src="'.$Result['user_image'].'" alt="" />
								<a class="ListlyAdminListEmbed" target="_blank" href="http://list.ly/preview/'.$Result['list_id'].'?key='.$this->Settings['PublisherKey'].'&source=wp_plugin" title="Get Short Code"><img src="'.$this->PluginURL.'images/shortcode.png" alt="" /></a>
								<a class="strong" target="_blank" href="http://list.ly/'.$Result['list_id'].'?source=wp_plugin" title="Go to List on List.ly">'.$Result['title'].'</a>
							</p>';
						}

						$ResponseJson['results'] = $Results;

						print json_encode($ResponseJson);
					}
					else
					{
						print $Response['body'];
					}
				}
			}

			exit;
		}


		function MetaBox()
		{
			if ($this->Settings['PublisherKey'] == '')
			{
				print "<p>Please enter Publisher Key on <a href='$this->SettingsURL'>Settings</a> page.</p>";
			}
			else
			{
				$UserURL = '#';

				$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('key' => $this->Settings['PublisherKey']))));

				if (false === ($Response = get_transient('Listly-Auth')))
				{
					$Response = wp_remote_post($this->SiteURL.'publisher/auth.json', $PostParms);

					if (!is_wp_error($Response) && isset($Response['body']) && $Response['body'] != '')
					{
						set_transient('Listly-Auth', $Response, 86400);
					}
				}

				if (!is_wp_error($Response) && isset($Response['body']) && $Response['body'] != '')
				{
					$ResponseJson = json_decode($Response['body'], true);

					if ($ResponseJson['status'] == 'ok')
					{
						$UserURL = $ResponseJson['user_url'].'?action=makelist';
					}
				}

			?>

				<h2 style="margin: 0; padding: 0; text-align: right;"><a target="_blank" href="<?php print $UserURL; ?>">Make New List</a></h2>

				<p>
					<div class="ListlyAdminListSearchWrap">
						<input type="text" name="ListlyAdminListSearch" placeholder="Start typing to search..." autocomplete="off" style="width: 100%; margin: 0 0 5px;" />
						<a class="ListlyAdminListSearchClear" href="#">X</a>
					</div>
					<input type="checkbox" name="ListlyAdminListSearchAll" value="1" /> <small>Search All Listly</small>
				</p>

				<div id="ListlyAdminYourList" style="min-height: 250px;">

			<?php

				$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('key' => $this->Settings['PublisherKey']))));
				$Response = wp_remote_post($this->SiteURL.'publisher/lists', $PostParms);

				if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
				{
					print '<p class="error">No connectivity or Listly service not available. Try later.</p>';
				}
				else
				{
					$ResponseJson = json_decode($Response['body'], true);

					if ($ResponseJson['status'] == 'ok')
					{
						$Count = 0;
						$Lists = $ResponseJson['lists'];

					?>

							<?php foreach ($Lists as $Key => $List) : $Count++; if ($Count > 10) { break; } ?>
								<p>
									<img class="avatar" src="<?php print $List['user_image']; ?>" alt="" />
									<a class="ListlyAdminListEmbed" target="_blank" href="http://list.ly/preview/<?php print $List['list_id']; ?>?key=<?php print $this->Settings['PublisherKey']; ?>&source=wp_plugin" title="Get Short Code"><img src="<?php print $this->PluginURL; ?>images/shortcode.png" alt="" /></a>
									<a class="strong" target="_blank" href="http://list.ly/<?php print $List['list_id']; ?>?source=wp_plugin" title="Go to List on List.ly"><?php print $List['title']; ?></a>
								</p>
							<?php endforeach; ?>

					<?php

					}
					else
					{
						print "<div class='ui-state ui-state-error ui-corner-all'><p>{$ResponseJson['message']}</p></div>";
					}
				}

				print '</div>';
			}
		}


		function ShortCode($Attributes, $Content = null, $Code = '')
		{
			$ListId = $Attributes['id'];
			$Theme = (isset($Attributes['theme']) && $Attributes['theme']) ? $Attributes['theme'] : $this->Settings['Theme'];
			$Layout = (isset($Attributes['layout']) && $Attributes['layout']) ? $Attributes['layout'] : $this->Settings['Layout'];
			$Numbered = (isset($Attributes['numbered']) && $Attributes['numbered']) ? $Attributes['numbered'] : $this->Settings['Numbered'];
			$Image = (isset($Attributes['image']) && $Attributes['image']) ? $Attributes['image'] : $this->Settings['Image'];
			$Items = (isset($Attributes['items']) && $Attributes['items']) ? $Attributes['items'] : $this->Settings['Items'];

			if (empty($ListId))
			{
				return 'Listly: Required parameter List ID is missing.';
			}

			$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('list' => $ListId, 'theme' => $Theme, 'layout' => $Layout, 'numbered' => $Numbered, 'image' => $Image, 'items' => $Items, 'key' => $this->Settings['PublisherKey'], 'user-agent' => $_SERVER['HTTP_USER_AGENT']))));

			if (false === ($Response = get_transient("Listly-$ListId")))
			{
				$Response = wp_remote_post($this->SiteURL.'list/embed.json', $PostParms);

				if (!is_wp_error($Response) && isset($Response['body']) && $Response['body'] != '')
				{
					set_transient("Listly-$ListId", $Response, 86400);
				}
			}

			if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
			{
				return "<!-- Listly error! --><p><a href=\"http://list.ly/$ListId\">View List on List.ly</a></p>";
			}
			else
			{
				if (false !== ($Timeout = get_option("_transient_timeout_Listly-$ListId")) && $Timeout < time() + 82800)
				{
					$Response = wp_remote_post($this->SiteURL.'list/embed.json', $PostParms);

					if (!is_wp_error($Response) && isset($Response['body']) && $Response['body'] != '')
					{
						delete_transient("Listly-$ListId");
						set_transient("Listly-$ListId", $Response, 86400);
					}
				}

				$ResponseJson = json_decode($Response['body'], true);

				if ($ResponseJson['status'] == 'ok')
				{
					global $ListlyListStyle;
					$ListlyListStyle = $ResponseJson['styles'][0];
					add_action('wp_footer', array(&$this, 'WPFooter'), 100);

					return $ResponseJson['list-dom'];
				}
				else
				{
					return $ResponseJson['message'];
				}
			}
		}


		function CheckSelected($SavedValue, $CurrentValue, $Type = 'select')
		{
			if ( (is_array($SavedValue) && in_array($CurrentValue, $SavedValue)) || ($SavedValue == $CurrentValue) )
			{
				switch ($Type)
				{
					case 'select':
						print 'selected="selected"';
						break;
					case 'radio':
					case 'checkbox':
						print 'checked="checked"';
						break;
				}
			}
		}


		function TrimByReference(&$String)
		{
			$String = trim($String);
		}
	}

	$Listly = new Listly();
}


?>
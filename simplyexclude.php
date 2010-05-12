<?php
/*
Plugin Name: Simply Exclude
Plugin URI: http://www.codehooligans.com/projects/wordpress/simply-exclude/
Description: Provides an interface to selectively exclude/include categories, tags and page from the 4 actions used by WordPress. is_front, is_archive, is_search, is_feed.
Author: Paul Menard
Version: 1.7.7
Author URI: http://www.codehooligans.com

Revision history
1.0 - 2007-11-20: Initial release
1.1 - 2008-12-15: Added logic to work with WP version greater than 2.2
1.5 - 20008-04-27 Fixed display issues. Changes 'List' to 'Archive'. Added tags inclusion/exclusion login. Works only with WP 2.3 and greater.
1.6 - 2008-05-22 Fixed various items. Added format display for Categories and Pages to reveal heirarchy, Disable plugin functions when searching in admin. This also corrected a display exclusion bug when showing categories and pages. 
1.7 - 2008-05-29 Added Author to the Include/Exclude logic. Now you can exclude Author's Posts from Search, Home, RSS, Archive.
1.7.1 - 2008-07-16 Fixed an issue with WP 2.6 where it automatically decided to unserialize the option data structure. 
1.7.2 - 2009-02-05 Fixed some PHP warning by checking variable is set. Also added style to 2.7 interface. 
1.7.2.1 - 2009-07-01 Fixed some PHP warning by checking variable is set. Also added style for 2.8 interface. Very minor changes. 
1.7.5 - 2009-07015 Fixed some PHP warning by checking variable is set. Also added style for 2.8 interface. Very minor changes. 
1.7.6 - 2009-11-14 Fixes: Issue with the Pages exclusion. Many users reporting a permissions issue. Additions: Added handler logic to interface with two other plugins. One of the often used Google XML Sitemaps. When setting Page or Category exclusions you now have the option to update the Google XML Sitemaps exclude pages and categories automatically. The other plugin is Search Unleashed. 

*/

class SimplyExclude
{
	var $se_cfg;
	var $options_key;
	var $default_IsActions;
	
	var $categories;
	var $pages;
	
	var $wp_version;
	
	var $in_admin;
	
	var $GA_generatorObject;
	
	function SimplyExclude()
	{
		global $wp_version;
		$this->wp_version = $wp_version;
		$this->in_admin = false;
		
		$this->admin_menu_label	= "Simply Exclude";
		$this->options_key			= "simplyexclude";

		$this->se_load_config();

		add_action('admin_menu', array(&$this,'admin_init_proc'));

		// Add our own admin menu
		add_action('admin_menu', array(&$this,'se_add_nav'));

	  	if ((isset($_REQUEST['page'])) && ($_REQUEST['page'] == "se_manage_categories")
		 || (isset($_REQUEST['page'])) && ($_REQUEST['page'] == "se_manage_tags")
		 || (isset($_REQUEST['page'])) && ($_REQUEST['page'] == "se_manage_authors")
		 || (isset($_REQUEST['page'])) && ($_REQUEST['page'] == "se_manage_pages")
		 || (isset($_REQUEST['page'])) && ($_REQUEST['page'] == "se_manage_options"))
			add_action('admin_head', array(&$this,'se_admin_head'));

		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'activate')
			add_action('init', array(&$this,'se_install'));
			
		// Used to limit the categories displayed on the home page. Simple
		add_filter('pre_get_posts', array(&$this,'se_filters'));
		
		add_action('save_post', array(&$this,'save_page_exclude_answer'));		

		//add_filter('posts_request', array(&$this,'posts_request_proc'));
	}


	function posts_request_proc($request)
	{
		//echo "request=[". $request. "]<br />";
		return $request;
	}

	function admin_init_proc()
	{
		// Means we are in the wp-admin backend and not running from the front site end. 
		$this->in_admin = true;
		
		if (function_exists('add_meta_box')) {
			add_meta_box($this->options_key, $this->admin_menu_label,
				 array(&$this,'add_page_exclude_sidebar_dbx'), 'page');
		}
		else { 
			add_filter('dbx_page_sidebar', array(&$this,'add_page_exclude_sidebar_dbx'));
		}
	}


	function se_load_config()
	{
		// This is the pre-defined WordPress is_* actions.
		$this->default_IsActions = array();

		// Define the actions allow on Post Categories
		$this->default_IsActions['cats'] = array();
		$this->default_IsActions['cats']['is_home']['name'] 		
			= "Front";
		$this->default_IsActions['cats']['is_home']['description']
			= "Visibility on the front/main page.";
		$this->default_IsActions['cats']['is_home']['action']
			= "i";
				
		$this->default_IsActions['cats']['is_archive']['name']			
			= "Archive";
		$this->default_IsActions['cats']['is_archive']['description']
			= "Visibility on the archive of categories on the sidebar";
		$this->default_IsActions['cats']['is_archive']['action']
			= "e";

		$this->default_IsActions['cats']['is_search']['name']
			= "Search";
		$this->default_IsActions['cats']['is_search']['description']
			= "Visibility in search results.";
		$this->default_IsActions['cats']['is_search']['action']
			= "e";

		$this->default_IsActions['cats']['is_feed']['name']
			= "Feed";
		$this->default_IsActions['cats']['is_feed']['description']
			= "Visibility in RSS/RSS2/Atom feeds.";
		$this->default_IsActions['cats']['is_feed']['action']
			= "e";

		$this->default_IsActions['cats']['is_archive']['name']
			= "Archive";			
		$this->default_IsActions['cats']['is_archive']['description']
			= "Visibility in archive links (i.e., calendar links).";
		$this->default_IsActions['cats']['is_archive']['action'] 	= "e";			

		
		// Tag Definitions
		$this->default_IsActions['tags'] = array();
		$this->default_IsActions['tags']['is_home']['name'] 		
			= "Front";
		$this->default_IsActions['tags']['is_home']['description']
			= "Visibility on the front/main page.";
		$this->default_IsActions['tags']['is_home']['action']
			= "i";
				
		$this->default_IsActions['tags']['is_archive']['name']			
			= "Archive";
		$this->default_IsActions['tags']['is_archive']['description']
			= "Visibility on the archive of tags on the sidebar";
		$this->default_IsActions['tags']['is_archive']['action']
			= "e";

		$this->default_IsActions['tags']['is_search']['name']
			= "Search";
		$this->default_IsActions['tags']['is_search']['description']
			= "Visibility in search results.";
		$this->default_IsActions['tags']['is_search']['action']
			= "e";

		$this->default_IsActions['tags']['is_feed']['name']
			= "Feed";
		$this->default_IsActions['tags']['is_feed']['description']
			= "Visibility in RSS/RSS2/Atom feeds.";
		$this->default_IsActions['tags']['is_feed']['action']
			= "e";

		$this->default_IsActions['tags']['is_archive']['name']
			= "Archive";			
		$this->default_IsActions['tags']['is_archive']['description']
			= "Visibility in archive links (i.e., calendar links).";
		$this->default_IsActions['tags']['is_archive']['action'] 	= "e";			

		// Authors
		$this->default_IsActions['authors'] = array();
		$this->default_IsActions['authors']['is_home']['name'] 		
			= "Front";
		$this->default_IsActions['authors']['is_home']['description']
			= "Visibility on the front/main page.";
		$this->default_IsActions['authors']['is_home']['action']
			= "i";
				
		$this->default_IsActions['authors']['is_archive']['name']			
			= "Archive";
		$this->default_IsActions['authors']['is_archive']['description']
			= "Visibility on the archive of categories on the sidebar";
		$this->default_IsActions['authors']['is_archive']['action']
			= "e";

		$this->default_IsActions['authors']['is_search']['name']
			= "Search";
		$this->default_IsActions['authors']['is_search']['description']
			= "Visibility in search results.";
		$this->default_IsActions['authors']['is_search']['action']
			= "e";

		$this->default_IsActions['authors']['is_feed']['name']
			= "Feed";
		$this->default_IsActions['authors']['is_feed']['description']
			= "Visibility in RSS/RSS2/Atom feeds.";
		$this->default_IsActions['authors']['is_feed']['action']
			= "e";

		$this->default_IsActions['authors']['is_archive']['name']
			= "Archive";			
		$this->default_IsActions['authors']['is_archive']['description']
			= "Visibility in archive links (i.e., calendar links).";
		$this->default_IsActions['authors']['is_archive']['action'] 	= "e";			
	
		// Pages Definitions
		$this->default_IsActions['pages'] = array();
		$this->default_IsActions['pages']['is_search']['name']			= "Search";
		$this->default_IsActions['pages']['is_search']['description']	= "Visibility in search results.";
		$this->default_IsActions['pages']['is_search']['action']		= "e";
			
		$this->se_cfg['cfg']['page_name']			= "simplyexclude";
		
		$tmp_se_cfg = get_option($this->options_key);
		if ($tmp_se_cfg)
		{
			//if (!is_array($tmp_se_cfg))
			//	$this->se_cfg = unserialize($tmp_se_cfg);

			// something new in WP 2.6. 
			// It might decide to unseralize the option data for you! Fuckers!!
			// So check the return.
			if (is_serialized($tmp_se_cfg))
				$this->se_cfg = unserialize($tmp_se_cfg);
			else
				$this->se_cfg = $tmp_se_cfg;
		}	

		$plugindir_node 				= dirname(plugin_basename(__FILE__));	
		$plugindir_url 					= get_bloginfo('wpurl') . "/wp-content/plugins/". $plugindir_node;
		$this->se_cfg['cfg']['myurl'] 	= $plugindir_url;

		if (!isset($this->se_cfg['cats']['actions']))
		{
			foreach($this->default_IsActions['cats'] as $cat_key => $cat_action)
			{
				$this->se_cfg['cats']['actions'][$cat_key] = $cat_action['action'];
			}
		}

		if (!isset($this->se_cfg['tags']['actions']))
		{
			foreach($this->default_IsActions['tags'] as $tag_key => $tag_action)
			{
				$this->se_cfg['tags']['actions'][$tag_key] = $tag_action['action'];
			}
		}

		if (!isset($this->se_cfg['authors']['actions']))
		{
			foreach($this->default_IsActions['authors'] as $author_key => $author_action)
			{
				$this->se_cfg['authors']['actions'][$author_key] = $author_action['action'];
			}
		}
		
		if (!isset($this->se_cfg['pages']['actions']))
		{
			foreach($this->default_IsActions['pages'] as $page_key => $page_action)
			{
				$this->se_cfg['pages']['actions'][$page_key] = $page_action['action'];
			}
		}
		
		
		if (!isset($this->se_cfg['options']))
		{
			$this->se_cfg['options'] 										= array();
		
			$this->se_cfg['options']['google-sitemap-generator'] 			= array();
			$this->se_cfg['options']['google-sitemap-generator']['name'] 	= "Google XML Sitemaps";
			$this->se_cfg['options']['google-sitemap-generator']['url'] 	= "http://wordpress.org/extend/plugins/google-sitemap-generator/";
			$this->se_cfg['options']['google-sitemap-generator']['desc'] 	= "Warning: Page ID listed in the Sitemap plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored";
			$this->se_cfg['options']['google-sitemap-generator']['version'] = "3.1.6";
			$this->se_cfg['options']['google-sitemap-generator']['status'] 	= false;
			$this->se_cfg['options']['google-sitemap-generator']['active'] 	= true;
			
			$this->se_cfg['options']['google-sitemap-generator']['actions'] = array();
			$this->se_cfg['options']['google-sitemap-generator']['actions']['pages']['desc'] = "Update Excluded Pages";		
			$this->se_cfg['options']['google-sitemap-generator']['actions']['pages']['update'] = false;		
			$this->se_cfg['options']['google-sitemap-generator']['actions']['categories']['desc'] = "Update Excluded Categories";		
			$this->se_cfg['options']['google-sitemap-generator']['actions']['categories']['update'] = false;		
		
		
			$this->se_cfg['options']['search-unleashed']							= array();
			$this->se_cfg['options']['search-unleashed']['name']					= "Search Unleashed";
			$this->se_cfg['options']['search-unleashed']['url']						= "http://wordpress.org/extend/plugins/search-unleashed/";
			$this->se_cfg['options']['search-unleashed']['desc']					= "Warning: Page ID listed in the Search Unleashed plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored";
			$this->se_cfg['options']['search-unleashed']['version']					= "1.0.5";
			$this->se_cfg['options']['search-unleashed']['status']					= false;
			$this->se_cfg['options']['search-unleashed']['active']					= true;
			
			$this->se_cfg['options']['search-unleashed']['actions'] 				= array();
			$this->se_cfg['options']['search-unleashed']['actions']['pages']['desc'] 		= "Update Excluded Pages";		
			$this->se_cfg['options']['search-unleashed']['actions']['pages']['update'] 	= false;		
			$this->se_cfg['options']['search-unleashed']['actions']['categories']['desc'] 		= "Update Excluded Categories";		
			$this->se_cfg['options']['search-unleashed']['actions']['categories']['update'] 	= false;		
		}
		$this->se_cfg['options']['google-sitemap-generator']['desc'] 	= "Warning: Page ID listed in the Sitemap plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored";
		$this->se_cfg['options']['search-unleashed']['desc']					= "Warning: Page ID listed in the Search Unleashed plugin will be removed and replaced with Page ID from the Simply Exclude plugin. Post ID values will be ignored";

		asort($this->se_cfg['options']);
	}	

	function se_save_config()
	{
		$ret = update_option($this->options_key, serialize($this->se_cfg));
	}
	
	
	function se_add_nav() 
	{
    	// Add a new menu under Manage:
    	//add_options_page('Simply Exclude', 'Simply Exclude', 8, 
		//	$this->options_key, array(&$this, 'se_manage_page'));
		
		add_menu_page( 'Simply Exclude', 'Simply Exclude', 7, 'se_manage_categories', array(&$this, 'se_manage_categories'));

		add_submenu_page( 'se_manage_categories', 'Exclude Categories', 'Exclude Categories', 7, 
			'se_manage_categories', array(&$this, 'se_manage_categories'));

		add_submenu_page( 'se_manage_categories', 'Exclude Tags', 'Exclude Tags', 7, 
			'se_manage_tags', array(&$this, 'se_manage_tags'));

		add_submenu_page( 'se_manage_categories', 'Exclude Authors', 'Exclude Authors', 7, 
			'se_manage_authors', array(&$this, 'se_manage_authors'));

		add_submenu_page( 'se_manage_categories', 'Exclude Pages', 'Exclude Pages', 7, 
			'se_manage_pages', array(&$this, 'se_manage_pages'));

		add_submenu_page( 'se_manage_categories', 'Exclude Options', 'Exclude Options', 7, 
			'se_manage_options', array(&$this, 'se_manage_options'));

	}

	function se_admin_head()
	{
		?>
		<link rel="stylesheet" href="<?php echo $this->se_cfg['cfg']['myurl'] ?>/simplyexclude_style_admin.css"
		 type="text/css" media="screen" />
		<?php 
		if ($this->wp_version >= 2.7)
		{
			?>
			<link rel="stylesheet" href="<?php echo $this->se_cfg['cfg']['myurl'] ?>/simplyexclude_style_admin_27.css"
				type="text/css" media="screen" />
			<?php
		}
	}

	function se_install()
	{
		add_option($this->options_key, 
				serialize($this->se_cfg), 
				"This is the serialized config structures used.");
	}
	
	function se_manage_page()
	{
		//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
		
		if (isset($_REQUEST['se_admin']))
		{
			$se_admin = $_REQUEST['se_admin'];
			$se_admin['action'] = $_GET['se_admin']['action'];
		}
		else
			$se_admin['action'] = 'edit_categories';

		$this->se_display_navigation($se_admin);

		?>
		<div class="wrap">
		<?php

			switch ($se_admin['action'])
			{
				case 'edit_pages':
				case 'save_pages':
					$this->se_display_pages_panel($se_admin);
					break;
	
				case 'edit_tags':
				case 'save_tags':
					$this->se_display_tags_panel($se_admin);
					break;

				case 'edit_authors':
				case 'save_authors':
					$this->se_display_authors_panel($se_admin);
					break;

				default:
				case 'edit_categories':
				case 'save_categories':
					$this->se_display_categories_panel($se_admin);
					break;
			}
		?>
		</div>
		<?php
	}
	
	function se_manage_categories()
	{
		?><div class="wrap"><?php
		if (isset($_REQUEST['se_admin']))
		{
			// echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";			
			$se_admin = $_REQUEST['se_admin'];
			//$se_admin['action'] = $_GET['se_admin']['action'];
			$this->se_display_categories_panel($se_admin);		
		}
		else
			$this->se_display_categories_panel();		
		?></div><?php
	}

	function se_manage_tags()
	{
		?><div class="wrap"><?php
		if (isset($_REQUEST['se_admin']))
		{
			//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";			
			$se_admin = $_REQUEST['se_admin'];
			//$se_admin['action'] = $_GET['se_admin']['action'];
			$this->se_display_tags_panel($se_admin);
		}
		else
			$this->se_display_tags_panel();
		
		?></div><?php
	}

	function se_manage_authors()
	{
		?><div class="wrap"><?php
		if (isset($_REQUEST['se_admin']))
		{
			//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";			
			$se_admin = $_REQUEST['se_admin'];
			//$se_admin['action'] = $_GET['se_admin']['action'];
			$this->se_display_authors_panel($se_admin);
		}
		else		
			$this->se_display_authors_panel();
		?></div><?php
	}

	function se_manage_pages()
	{
		?><div class="wrap"><?php
		if (isset($_REQUEST['se_admin']))
		{
			//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";			
			$se_admin = $_REQUEST['se_admin'];
			//$se_admin['action'] = $_GET['se_admin']['action'];
			$this->se_display_pages_panel($se_admin);
		}
		else
			$this->se_display_pages_panel();
		?></div><?php
	}
	
	function se_manage_options()
	{
		?><div class="wrap"><?php
		if (isset($_REQUEST['se_admin']))
		{
			//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
			$se_admin = $_REQUEST['se_admin'];
			//$se_admin['action'] = $_REQUEST['se_admin']['action'];
			$this->se_display_options_panel($se_admin);
		}
		else
			$this->se_display_options_panel();
		?></div><?php
		
	}
	
	
	function se_display_navigation($se_admin)
	{
		?>
		<div id="se_admin_nav">
			<ul>
				<li><a href="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=edit_categories"
					<?php
					if (($se_admin['action'] == 'edit_categories')
					 || ($se_admin['action'] == 'save_categories'))
						echo 'class="current"';
					?>
 					title="Manage Category Exclusions">Manage Categories</a></li>
				<?php
					if ($this->wp_version >= 2.3)
					{
						?>
						<li><a href="?page=<?php 
							echo $this->options_key ?>&amp;se_admin[action]=edit_tags"
							<?php
							if (($se_admin['action'] == 'edit_tags')
							 || ($se_admin['action'] == 'save_tags'))
								echo 'class="current"';
							?>
							title="Manage Tag Exclusions">Manage Tags</a></li><?php
					}
				?>						
				<li><a href="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=edit_authors"
					<?php
					if (($se_admin['action'] == 'edit_authors')
					 || ($se_admin['action'] == 'save_authors'))
						echo 'class="current"';
					?>
 					title="Manage Author Exclusions">Manage Authors</a></li>

				<li><a href="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=edit_pages"
					<?php
					if (($se_admin['action'] == 'edit_pages')
					 || ($se_admin['action'] == 'save_pages'))
						echo 'class="current"';
					?>
					title="Manage Page Exclusions">Manage Pages</a></li>
			</ul>
		</div>
		<?php
	}
		
	// CATEGORY FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_categories_panel($se_admin='')
	{
		//$this->se_check_google_xml_sitemap_exclude_cats();
		//echo "_REQUEST<pre>"; print_r($_REQUEST); echo "</pre>";
		?>
		
		<h2>Manage Category Exclusions</h2>
		<?php
		if ((isset($se_admin['action'])) && ($se_admin['action'] == "save_categories"))
		{
			$this->se_update_google_xml_sitemap_exclude_cats();
			$this->se_update_search_unleashed_exclude_cats();

			//echo "se_admin<pre>"; print_r($se_admin); echo "</pre>";
			if (isset($se_admin['cats']))
				$this->se_cfg['cats'] = $se_admin['cats'];
			else
				unset($this->se_cfg['cats']);
			
			$this->se_save_config();				
			?>
			<div class="updated">
				<p>Category Exclusions successfully updated.</p>
			</div>
			<?php
		}
		$this->se_show_categories_form();
	}

	function se_load_categories()
	{
		global $wpdb;
		if (!$this->categories)
		{
			$this->categories = get_categories('hide_empty=0&orderby=name&order=ASC');
		}
	}
	
	
	function get_cat_parent_tree_array($cat_id=0, $level=0)
	{
		$cat_info = get_category($cat_id);
		
		$parent_array = array();
		$parent_array[$level] = $cat_info;

		if (intval($cat_info->parent) > 0)
		{
			$cat_array_tmp = $this->get_cat_parent_tree_array($cat_info->parent, $level+1);
			if ($cat_array_tmp)
				$parent_array = array_merge($parent_array, $cat_array_tmp);
		}
		return $parent_array;
	}
	
	
	function se_show_categories_form()
	{
		$this->se_load_categories();
		if ($this->categories)
		{
			$this->display_instructions('cats');
			?>
			<form name="cat_exclusion" id="cat_exclusion" 
				action="?page=se_manage_categories&amp;se_admin[action]=save_categories" method="post">
				<table class="widefat" width="80%" cellpadding="0" cellspacing="2" border="0">
				<thead>
		        <tr>
		        	<th class="action"><?php _e('Action Name') ?></th>
		        	<th class="description"><?php _e('Description ') ?></th>
		        	<th class="inc-excl"><?php _e('Inclusion/Exclusion') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				$class = "";
				foreach ($this->default_IsActions['cats'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td class="action"><?php echo $action_val['name'] ?></td>
						<td class="description"><?php echo $action_val['description'] ?></td>
						<td class="inc-excl">
							<input type="radio" 
								name="se_admin[cats][actions][<?php echo $action_key ?>]" value="i" 
								<?php if ((isset($this->se_cfg['cats']['actions'][$action_key]))
									   && ($this->se_cfg['cats']['actions'][$action_key] == 'i')) 
									echo "checked='checked'"; ?> /> Include only<br />
							<input type="radio" 
								name="se_admin[cats][actions][<?php echo $action_key ?>]" value="e" 
								<?php if ((isset($this->se_cfg['cats']['actions'][$action_key]))
									   && ($this->se_cfg['cats']['actions'][$action_key] == 'e')) 
									echo "checked='checked'"; ?> /> Exclude
						</td>
					<tr>
					<?php
				}
				?>
				</tbody>
				</table>
				<br />
				<table class="widefat" width="80%" cellpadding="0" cellspacing="2" border="0">
				<thead>
		        <tr>
		        	<th class="cat-id" scope="col"><?php _e('ID') ?></th>
		        	<th class="cat-name" scope="col"><?php _e('Category Name') ?></th>
		        	<th class="cat-action" scope="col"><?php _e('Exclude from...') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
					$class="";
					foreach($this->categories as $cat_info)
					{	
						$class = ('alternate' == $class) ? '' : 'alternate';
						$this->se_show_cat_item_row($cat_info, $class);
					}
				?>		
				<tr>
					<td colspan="3">
						<p class="submit">
							<input type="hidden" name="se_admin[action]" value="save_categories" />							
							<input type="submit" name="submit"  value="<?php _e('Save Changes &raquo;') ?>" />
						</p>
					</td>
				</tr>
				</tbody>
				</table>
				</p></div>				
			</form>
			<?php
		}
		else
		{
			?><p>You don't have any Categories defined.</p><?php
		}
	}
	
	function se_show_cat_item_row($cat_info, $class)
	{
		$cat_parents = $this->get_cat_parent_tree_array($cat_info->cat_ID, 0);
		$level_spacer = "";
		foreach($cat_parents as $cat_parent)
		{
			if ($cat_parent->cat_ID == $cat_info->cat_ID)
				continue;
				
			$level_spacer .= "&ndash;";
		}
		
		?>
		<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
			<td class="cat-id"><?php echo $cat_info->cat_ID ?></td>
			<td class="cat-name"><?php echo $level_spacer . $cat_info->cat_name ?></td>
			<td class="cat-action"><?php $this->se_display_cat_action_row($cat_info->cat_ID) ?></td>
		</tr>
		<?php
	}
	
	function se_display_cat_action_row($cat_id)
	{
		foreach ($this->default_IsActions['cats'] as $action_key => $action_val)
		{
			?>
			<label for="cats-<?php echo $action_key ?>-<?php echo $cat_id ?>">
				<?php echo $action_val['name'] ?></label>&nbsp;
			<input type="checkbox" 
				name="se_admin[cats][<?php echo $action_key ?>][<?php echo $cat_id ?>]"
				id="cats-<?php echo $action_key ?>-<?php echo $cat_id ?>"
				<?php

				if ((isset($this->se_cfg['cats'][$action_key][$cat_id])) && ($this->se_cfg['cats'][$action_key][$cat_id] == "on"))
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
	
	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////


	// TAG FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_tags_panel($se_admin='')
	{
		?>
		<h2>Manage Tag Exclusions</h2>
		<?php
		if ((isset($se_admin['action'])) && ($se_admin['action'] == "save_tags"))
		{
			if (isset($se_admin['tags']))
				$this->se_cfg['tags'] = $se_admin['tags'];
			else
				unset($this->se_cfg['tags']);
			
			$this->se_save_config();				
			?>
			<div class="updated">
				<p>Tag Exclusions successfully updated.</p>
			</div>
			<?php
		}
		$this->se_show_tags_form();
	}
	
	function se_load_tags()
	{
		global $wpdb;
		if (!isset($this->tags))
		{
			$this->tags = get_tags('hide_empty=0&orderby=name&order=ASC');			
		}
	}
	
	
	function se_show_tags_form()
	{
		$this->se_load_tags();
		if ($this->tags)
		{
			$this->display_instructions('tags');
			?>
			<form name="tag_exclusion" id="tag_exclusion" 
				action="?page=se_manage_tags&amp;se_admin[action]=save_tags" method="post">

				<table class="widefat" width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th class="action"><?php _e('Action Name') ?></th>
		        	<th class="description"><?php _e('Description ') ?></th>
		        	<th class="inc-excl"><?php _e('Inclusion/Exclusion') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				$class="";
				foreach ($this->default_IsActions['tags'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td class="action"><?php echo $action_val['name'] ?></td>
						<td class="description"><?php echo $action_val['description'] ?></td>
						<td class="inc-excl">
							<input type="radio" 
								name="se_admin[tags][actions][<?php echo $action_key ?>]" value="i" 
								<?php if ($this->se_cfg['tags']['actions'][$action_key] == 'i') 
									echo "checked='checked'"; ?> /> Include only<br />
							<input type="radio" 
								name="se_admin[tags][actions][<?php echo $action_key ?>]" value="e" 
								<?php if ($this->se_cfg['tags']['actions'][$action_key] == 'e') 
									echo "checked='checked'"; ?> /> Exclude
						</td>
					<tr>
					<?php
				}
				?>
				</tbody>
				</table>
				<br />
				<table class="widefat" width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th class="cat-id" scope="col"><?php _e('ID') ?></th>
		        	<th class="cat-name" scope="col"><?php _e('Tag Name') ?></th>
		        	<th class="cat-action" scope="col"><?php _e('Exclude from...') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
					foreach($this->tags as $tag_info)
					{	
						$class = ('alternate' == $class) ? '' : 'alternate';
						$this->se_show_tag_item_row($tag_info, $class);
					}
				?>		
				<tr>
					<td colspan="3">
						<p class="submit">
							<input type="hidden" name="se_admin[action]" value="save_tags" />							
							<input type="submit" name="submit"  value="<?php _e('Save Changes &raquo;') ?>" />
						</p>
					</td>
				</tr>
				</tbody>
				</table>
				</p></div>				
			</form>
			<?php
		}
		else
		{
			?><p>You don't have any Tags defined.</p><?php
		}
	}
	
	function se_show_tag_item_row($tag_info, $class)
	{
		?>
		<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
			<td class="tag-id"><?php echo $tag_info->term_id ?></td>
			<td class="tag-name"><?php echo $tag_info->name ?></td>
			<td class="tag-action"><?php $this->se_display_tag_action_row($tag_info->term_id) ?></td>
		</tr>
		<?php
	}
	
	function se_display_tag_action_row($tag_id)
	{
		foreach ($this->default_IsActions['tags'] as $action_key => $action_val)
		{
			?>
			<label for="tags-<?php echo $action_key ?>-<?php echo $tag_id ?>">
				<?php echo $action_val['name'] ?></label>&nbsp;
			<input type="checkbox" 
				name="se_admin[tags][<?php echo $action_key ?>][<?php echo $tag_id ?>]"
				id="tags-<?php echo $action_key ?>-<?php echo $tag_id ?>"
				<?php
				if ((isset($this->se_cfg['tags'][$action_key][$tag_id])) && ($this->se_cfg['tags'][$action_key][$tag_id] == "on"))
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
	

	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////


	// AUTHOR FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_authors_panel($se_admin='')
	{
		?>
		<h2>Manage Author Exclusions</h2>
		<?php
		if ((isset($se_admin['action'])) && ($se_admin['action'] == "save_authors"))
		{
			if (isset($se_admin['authors']))
				$this->se_cfg['authors'] = $se_admin['authors'];
			else
				unset($this->se_cfg['authors']);
			
			$this->se_save_config();				
			?>
			<div class="updated">
				<p>Author Exclusions successfully updated.</p>
			</div>
			<?php
		}
		$this->se_show_authors_form();
	}

	function se_load_authors()
	{
		global $wpdb;
		if (!isset($this->authors))
		{
			$this->authors = get_users_of_blog();
		}
	}	
	
	function se_show_authors_form()
	{
		$this->se_load_authors();
		if ($this->authors)
		{
			$this->display_instructions('authors');
			?>
			<form name="author_exclusion" id="author_exclusion" 
				action="?page=se_manage_authors&amp;se_admin[action]=save_authors" method="post">

				<table class="widefat" width="80%" cellpadding="0" cellspacing="2" border="0">
				<thead>
		        <tr>
		        	<th class="action"><?php _e('Action Name') ?></th>
		        	<th class="description"><?php _e('Description ') ?></th>
		        	<th class="inc-excl"><?php _e('Inclusion/Exclusion') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				$class="";
				foreach ($this->default_IsActions['authors'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td class="action"><?php echo $action_val['name'] ?></td>
						<td class="description"><?php echo $action_val['description'] ?></td>
						<td class="inc-excl">
							<input type="radio" 
								name="se_admin[authors][actions][<?php echo $action_key ?>]" value="i" 
								<?php if ($this->se_cfg['authors']['actions'][$action_key] == 'i') 
									echo "checked='checked'"; ?> /> Include only<br />
							<input type="radio" 
								name="se_admin[authors][actions][<?php echo $action_key ?>]" value="e" 
								<?php if ($this->se_cfg['authors']['actions'][$action_key] == 'e') 
									echo "checked='checked'"; ?> /> Exclude
						</td>
					<tr>
					<?php
				}
				?>
				</tbody>
				</table>
				<br />
				<table class="widefat" width="80%" cellpadding="0" cellspacing="2" border="0">
				<thead>
		        <tr>
		        	<th class="author-id" scope="col"><?php _e('ID') ?></th>
		        	<th class="author-name" scope="col"><?php _e('Author Name') ?></th>
		        	<th class="cat-action" scope="col"><?php _e('Exclude from...') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
					foreach($this->authors as $author_info)
					{	
						$class = ('alternate' == $class) ? '' : 'alternate';
						$this->se_show_author_item_row($author_info, $class);
					}
				?>		
				<tr>
					<td colspan="3">
						<p class="submit">
							<input type="hidden" name="se_admin[action]" value="save_authors" />
							<input type="submit" name="submit"  value="<?php _e('Save Changes &raquo;') ?>" />
						</p>
					</td>
				</tr>
				</tbody>
				</table>
				</p></div>				
			</form>
			<?php
		}
		else
		{
			?><p>You don't have any Authors defined.</p><?php
		}
	}
	
	function se_show_author_item_row($author_info, $class)
	{
		?>
		<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
			<td class="author-id"><?php echo $author_info->user_id ?></td>
			<td class="author-name"><?php echo $author_info->display_name ?></td>
			<td class="author-action"><?php $this->se_display_author_action_row($author_info->user_id) ?></td>
		</tr>
		<?php
	}
	
	function se_display_author_action_row($author_id)
	{
		foreach ($this->default_IsActions['authors'] as $action_key => $action_val)
		{
			?>
			<label for="authors-<?php echo $action_key ?>-<?php echo $author_id ?>">
				<?php echo $action_val['name'] ?></label>&nbsp;
			<input type="checkbox" 
				name="se_admin[authors][<?php echo $action_key ?>][<?php echo $author_id ?>]"
				id="authors-<?php echo $action_key ?>-<?php echo $author_id ?>"
				<?php
				if ((isset($this->se_cfg['authors'][$action_key][$author_id])) 
				 && ($this->se_cfg['authors'][$action_key][$author_id] == "on"))
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
	
	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////

	// PAGE FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_pages_panel($se_admin='')
	{
		//$this->se_check_google_sitemap_exclude_pages();
		?>
		<h2>Manage Page Exclusions</h2>
		<?php
		if ((isset($se_admin['action'])) && ($se_admin['action'] == "save_pages"))
		{
			if (isset($se_admin['pages']))
			{
				// Need to update the third party items before updating the master. This will allow for 
				// comparison checking
				$this->se_update_google_xml_sitemap_exclude_pages();
				$this->se_update_search_unleashed_exclude_pages();				

				$this->se_cfg['pages'] = $se_admin['pages'];
				$this->se_save_config();
				?>
				<div class="updated">
					<p>Page Exclusions successfully updated.</p>
				</div>
				<?php
			}
		}
		$this->se_show_pages_form();
		
	}

	function se_load_pages()
	{
		global $wpdb;
		if (!$this->pages)
			$this->pages = get_pages();
	}
		
	function get_page_parent_tree_array($page_id=0, $level=0)
	{
		$page_info = get_page($page_id);

		$parent_array = array();
		$parent_array[$level] = $page_info;

		if (intval($page_info->post_parent) > 0)
		{
			$page_array_tmp = $this->get_page_parent_tree_array($page_info->post_parent, $level+1);
			if ($page_array_tmp)
				$parent_array = array_merge($parent_array, $page_array_tmp);
		}
		return $parent_array;
	}
	
	

	function se_show_pages_form()
	{
		$this->se_load_pages();
		if ($this->pages)
		{
			$this->display_instructions('pages');
			?>			
			<form name="page_exclusion" id="page_exclusion" 
				action="?page=se_manage_pages&amp;se_admin[action]=save_pages" method="post">
				<table class="widefat" width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th class="action"><?php _e('Action Name') ?></th>
		        	<th class="description"><?php _e('Description ') ?></th>
		        	<th class="inc-excl"><?php _e('Inclusion/Exclusion Default') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				$class = "";
				foreach ($this->default_IsActions['pages'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td class="action"><?php echo $action_val['name'] ?></td>
						<td class="description"><?php echo $action_val['description'] ?></td>
						<td class="inc-excl">
							<input type="radio" 
								name="se_admin[pages][actions][<?php echo $action_key ?>]" value="i" 
								<?php if ((isset($this->se_cfg['pages']['actions'][$action_key]))
									    && ($this->se_cfg['pages']['actions'][$action_key] == 'i')) 
									echo "checked='checked'"; ?> /> Include only<br />
							
							<input type="radio" 
								name="se_admin[pages][actions][<?php echo $action_key ?>]" value="e" 
								<?php if ((isset($this->se_cfg['pages']['actions'][$action_key]))
									   && ($this->se_cfg['pages']['actions'][$action_key] == 'e')) 
									echo "checked='checked'"; ?> /> Exclude
						</td>
					<tr>
					<?php
				}
				?>
				</tbody>
				</table>
				<br />


				<table class="widefat" width="80%" cellpadding="3" cellspacing="3">
				<thead>
		        <tr>
		        	<th class="page-id" scope="col"><?php _e('ID') ?></th>
		        	<th class="page-name" scope="col"><?php _e('Title') ?></th>
		        	<th class="page-action" scope="col"><?php _e('Exclude from...') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
					foreach($this->pages as $page_info)
					{
						$class = ('alternate' == $class) ? '' : 'alternate';						
						$this->se_show_page_item_row($page_info, $class);
					}
				?>	
				<tr>
					<td colspan="3">
						<p class="submit">
							<input type="hidden" name="se_admin[action]" value="save_pages" />
							<input type="submit" name="submit"  value="<?php _e('Save Changes &raquo;') ?>" />
						</p>
					</td>
				</tr>
				</tbody>	
				</table>
				
			</form>
			<?php
			//$this->se_check_google_sitemap_exclude(1);
		}
		else
		{
			?><p>You don't have any Pages.</p><?php
		}
		
	}

	function se_show_page_item_row($page_info, $class = '')
	{
		$page_parents = $this->get_page_parent_tree_array($page_info->ID, 0);
		$level_spacer = "";
		foreach($page_parents as $page_parent)
		{
			if ($page_parent->ID == $page_info->ID)
				continue;

			$level_spacer .= "&ndash;";
		}
		
		
		?>
		<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
			<td class="page-id"><?php echo $page_info->ID ?></td>
			<td class="page-name"><?php echo $level_spacer. $page_info->post_title ?></td>
			<td class="page-action"><?php $this->se_display_page_action_row($page_info->ID) ?></td>
		</tr>
		<?php
	}
	
	
	function se_display_page_action_row($page_id)
	{
		foreach ($this->default_IsActions['pages'] as $action_key => $action_val)
		{
			?>
			<label for="pages-<?php echo $action_key ?>-<?php echo $page_id ?>">
				<?php echo $action_val['name'] ?></label>&nbsp;
			<input type="checkbox" 
				name="se_admin[pages][<?php echo $action_key ?>][<?php echo $page_id ?>]"
				id="pages-<?php echo $action_key ?>-<?php echo $page_id ?>"
				<?php
					
				if ((isset($this->se_cfg['pages'][$action_key][$page_id]))
				 && ($this->se_cfg['pages'][$action_key][$page_id] == "on"))
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
		
	function get_pages_list($sep, $ids)
	{		
		foreach($ids as $id_key => $id_val)
		{
			if (strlen($id_list))
				$id_list .= ",";
			$id_list .= $id_key;
		}
		return $id_list;
		
	}
	
	// The following 2 function we taken from the wonderful SearchEverything plugin. 
	// http://wordpress.org/extend/plugins/search-everything/
	function SE4_exclude_posts($where) {
		global $wp_query, $wpdb;

		$action_key = "is_search";

		if ((!empty($wp_query->query_vars['s'])) 
		 && (count($this->se_cfg['pages'][$action_key]) > 0))
		{
			//echo __FUNCTION__ ." before : where=[".$where."]<br />";
			$excl_list = $this->get_pages_list(',', $this->se_cfg['pages'][$action_key]);
			//$excl_list = implode(',', explode(',', trim($this->options['SE4_exclude_posts_list'])));
			
			$where = str_replace('"', "'", $where);
			$where = 'AND ('. substr($where, strpos($where, 'AND')+3). ' )';
			if ($this->se_cfg['pages']['actions'][$action_key] == 'e')
				$where .= ' AND ('.$wpdb->posts.'.ID NOT IN ( '.$excl_list.' ))';
			else
				$where .= ' AND ('.$wpdb->posts.'.ID IN ( '.$excl_list.' ))';			
			//echo __FUNCTION__ ." after: where=[".$where."]<br />";
		}
		return $where;
	}

	//search pages (except password protected pages provided by loops)
	function SE4_search_pages($where) {
		global $wp_query;

		if (!empty($wp_query->query_vars['s'])) {
			//echo __FUNCTION__ ." before: where=[".$where."]<br />";

			$where = str_replace('"', "'", $where);
			if ('true' == $this->options['SE4_approved_pages_only']) {
				$where = str_replace("post_type = 'post' AND ", "post_password = '' AND ", $where);
			}
			else { // < v 2.1
				$where = str_replace("post_type = 'post' AND ", "", $where);
			}
			//echo __FUNCTION__ ." after: where=[".$where."]<br />";
		}
		
		return $where;
	}

	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////
	
	
	
	// OPTIONS FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_options_panel($se_admin='')
	{
		?>
		<h2>Manage Simply Exclude Options and Third-Party hooks</h2>
		<?php
		
		if (isset($_REQUEST['se_admin']))
			$se_admin = $_REQUEST['se_admin'];

		$this->se_load_options();
			
		if ((isset($se_admin['action'])) && ($se_admin['action'] == "save_options"))
		{
			if (isset($se_admin['options']))
			{
				foreach ($this->se_cfg['options'] as $option_key => $options_set)
				{
					if ($options_set['status'] === true)
					{
						if (count($options_set['actions']))
						{
							foreach($options_set['actions'] as $option_actions_idx => $option_actions_set)
							{
								if (isset($se_admin['options'][$option_key]['actions'][$option_actions_idx]['update']))
									$this->se_cfg['options'][$option_key]['actions'][$option_actions_idx]['update'] = true;
								else
									$this->se_cfg['options'][$option_key]['actions'][$option_actions_idx]['update'] = false;
							}
						}
					}
				}
				$this->se_save_config();				
				?>
				<div class="updated">
					<p>Exclusion Options successfully updated.</p>
				</div>
				<?php
				
			}
		}
		$this->se_show_options_form();
	}
	
	function se_load_options()
	{
		$check_plugins = get_option('active_plugins');
		if ($check_plugins)
		{
			foreach($check_plugins as $plugin_item)
			{
				$plugin_path_prefix = explode('/', $plugin_item);
				if (isset($this->se_cfg['options'][$plugin_path_prefix[0]]))
					$this->se_cfg['options'][$plugin_path_prefix[0]]['status'] = true;
			}
		}
	}
	function se_show_options_form()
	{
		$this->display_instructions('options');
				
		?>			
		<form name="option_exclusion" id="option_exclusion" 
			action="?page=se_manage_options" method="post">
			<table class="widefat" width="80%" cellpadding="3" cellspacing="3" border="0">
			<thead>
	        <tr>
	        	<th class="name"><?php _e('Plugin Name') ?></th>
	        	<th class="description"><?php _e('Description of Functionality') ?></th>
	        	<th class="actions"><?php _e('Actions') ?></th>
	        </tr>
			</thead>
			<tbody>
			<?php
			$class = "";
			foreach ($this->se_cfg['options'] as $option_key => $options_set)
			{
				//echo "options_set<pre>"; print_r($options_set); echo "</pre>";
				
				$class = ('alternate' == $class) ? '' : 'alternate';
				?>
				<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
					<td class="name"><a href="<?php echo $options_set['url'] ?>"><?php echo $options_set['name'] ?></a></td>
					<td class="description"><?php echo $options_set['desc'] ?></td>
					<td class="actions" nowrap="nowrap">
						<?php
						if ($options_set['active'] == true)
						{
							if ($options_set['status'] !== true)
							{
								?>This plugin is not installed or not active.<?php
							}
							else
							{
								if (count($options_set['actions']))
								{
									foreach($options_set['actions'] as $option_actions_idx => $option_actions_set)
									{
										?>
										<input type="checkbox" 
											name="se_admin[options][<?php echo $option_key; ?>][actions][<?php 
												echo $option_actions_idx; ?>][update]"
											<?php if ($option_actions_set['update'] === true) 
												echo "checked='checked'"; ?> /> <?php echo $option_actions_set['desc']?><br />
										<?php
									}
								}
							}
						}	
						?>

					</td>
				<tr>					
				<?php
			}
			?>
			<tr>
				<td colspan="2">
					<p class="submit">
						<input type="hidden" name="se_admin[action]" value="save_options" />
						<input type="submit" name="submit"  value="<?php _e('Save Changes &raquo;') ?>" />
					</p>
				</td>
			</tr>
			
			</tbody>
			</table>
			
		</form>
		<?php
	}
	
	
	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////
	
	
	
	function se_filters($query) 
	{
		if ($this->in_admin == true)
			return;
			
		if (count($this->default_IsActions['cats']) > 0)
		{
			foreach ($this->default_IsActions['cats'] as $action_key => $action_val)
			{
				$cats_list = "";
				if ($query->{$action_key})
				{
					if (isset($this->se_cfg['cats'][$action_key]))
					{
						if (count($this->se_cfg['cats'][$action_key]))
						{
							$cats_list = $this->se_listify_ids( $this->se_cfg['cats']['actions'][$action_key],
															$this->se_cfg['cats'][$action_key]);
						}
					}
					if (strlen($cats_list))
						$query->set('cat', $cats_list);
				}
			}
		}

		if ($this->wp_version >= 2.3)
		{
			if (count($this->default_IsActions['tags']) > 0)
			{
				foreach ($this->default_IsActions['tags'] as $action_key => $action_val)
				{
					if ($query->{$action_key})
					{
						if (isset($this->se_cfg['tags'][$action_key]))
						{
							if (isset($tag_array_list))
								unset($tag_array_list);
							
							$tag_array_list = array();
							if (count($this->se_cfg['tags'][$action_key]) > 0)
							{
								foreach($this->se_cfg['tags'][$action_key] as $key => $val)
								{
									$tag_array_list[] = $key; 
								}

								if ($this->se_cfg['tags']['actions'][$action_key] == "e")
								{
									$query->set('tag__not_in', $tag_array_list);
								}
								else
								{
									$query->set('tag__in', $tag_array_list);
								}
							}
						}
					}
				}
			}

			if (count($this->default_IsActions['authors']) > 0)
			{
				foreach ($this->default_IsActions['authors'] as $action_key => $action_val)
				{
					$authors_list = "";
					if ($query->{$action_key})
					{
						if (isset($this->se_cfg['authors'][$action_key]))
						{
							if (count($this->se_cfg['authors'][$action_key]))
							{
								$authors_list = $this->se_listify_ids(
									$this->se_cfg['authors']['actions'][$action_key],
									$this->se_cfg['authors'][$action_key]);
							}
						}
						if (strlen($authors_list))
							$query->set('author', $authors_list);
					}
				}
			}
		}

		if (count($this->default_IsActions['pages']) > 0)
		{
			foreach ($this->default_IsActions['pages'] as $action_key => $action_val)
			{
				if ($query->{$action_key})
				{
					add_filter('posts_where', array(&$this, 'SE4_search_pages'));
					add_filter('posts_where', array(&$this, 'SE4_exclude_posts'));

/*
					$pages_list;
					if (isset($this->se_cfg['pages'][$action_key]))
					{
						//echo "this->se_cfg['pages'][$action_key]=[". $this->se_cfg['pages'][$action_key]. "]<br />";
					
						$pages_list = $this->se_listify_ids($this->se_cfg['pages']['actions'][$action_key], 
															$this->se_cfg['pages'][$action_key]);
						//echo "pages_list=[". $pages_list."]<br />";
					}
					if (strlen($pages_list))
						$query->set('page', $pages_list);
*/
				}
			}
		}
		//echo "query after<pre>"; print_r($query); echo "</pre>";
		return $query;
	}

	function se_listify_ids($action, $ids)
	{
		$id_list = "";
		if ($action == "e")
			$action_value = "-";
		else
			$action_value = "";
		foreach($ids as $id_key => $id_val)
		{
			if (strlen($id_list))
				$id_list .= ",";
			$id_list .= $action_value.$id_key;
		}
		return $id_list;
	}


	function display_instructions($type)
	{
		if ($type == "cats")
		{
			?>
			<p>Set the checkbox to exclude the respective page from the action</p>
			<p>So what is the difference between Exclusion and Inclusion?<br />
				<strong>Exclude</strong>: Select this action to exclude Categories from WP 
					action. For example you may wish to exclude the Category 'Blogroll' from Searches.<br />
				<strong>Include</strong>: Select the Categories you wish to be included for certain 
					WP actions. For example you want only a certain category displayed on the home 
					page. Note that with Include only those checked items will be included in the 
					WP action. </p>
			<?php
		}
		else if ($type == "tags")
		{
			?>
			<p>Set the checkbox to exclude the respective page from the action</p>
			<p>So what is the difference between Exclusion and Inclusion?<br />
				<strong>Exclude</strong>: Select this action to exclude Tags from WP 
					action. For example you may wish to exclude the Tag 'Blogroll' from Searches.<br />
				<strong>Include</strong>: Select the Tag you wish to be included for certain 
					WP actions. For example you want only a certain tag displayed on the home 
					page. Note that with Include only those checked items will be included in the 
					WP action. </p>
			<?php
		}
		else if ($type == "authors")
		{
			?>
			<p>Set the checkbox to exclude the respective author from the action</p>
			<p>So what is the difference between Exclusion and Inclusion?<br />
				<strong>Exclude</strong>: Select this action to exclude Authors from WP 
					action. For example you may wish to exclude the Author 'jim' from Searches.<br />
				<strong>Include</strong>: Select the Author you wish to be included for certain 
					WP actions. For example you want only a certain author(s) displayed on the home 
					page. Note that with Include only those checked items will be included in the 
					WP action. </p>
			<?php
		}
		else if ($type == "pages")
		{
		
			?>
			<p style="color: red"><strong>WARNING: There is a known conflict when excluding pages here and via the plugin Search Everything. The problem is related to how each plugin will modify the SQL query used by WordPress. If you are using Search Everything plugin please do not make any exclusions here.</p>
			<p>Set the checkbox to exclude the respective page from the action</p>
			<p>So what is the difference between Exclusion and Inclusion?<br />
				<strong>Exclude</strong>: Select this action to exclude Pages from WP 
					action. For example you may wish to exclude a Page from Searches. Most common use.<br />
				<strong>Include</strong>: Select the Page you wish to be included for certain 
					WP actions. For example you want only certain Pages displayed from a Search. Note with Include only those checked items will be included in the 
					WP action. And as new Pages are added they will need to be checked here. </p>

			<p><strong>Note</strong>: The Pages section of this plugin is at best experimental until Page Search is included 
				by default into WordPress core. Also, you might consider using the SearchEverything plugin which offers much more
				 functionality on Searched. http://wordpress.org/extend/plugins/search-everything/</p>
			<?php
		
		/*
		?>
		<p>This is a placeholder section for Pages Exclusion. Since WordPress does not yet include Pages in searches this section is pointless. From various sources version 2.6 of WordPress should include native support for including Pages in search results. Look for changes to this plugin shortly after that.</p>
		<?php	
		*/
		}
		else if ($type == "options")
		{
			?>
			<p>The Simply Exclude plugin now works with a few other plugins. Check the box for support of the listed third 
				party plugins options below</p>
			<p>When you update this section you will then also need to go back into the Simply Exclude Category or Pages section and re-save the settings. This re-save will then update the third-party plugin settings with the update excluded values. On the respective Category or Pages sections of Simply Exclude you can use either include or exclude action.</p>
			<p style="color: #ff0000">Warning: Once enabled it is suggested you make edits to the exclusion/inclusion via Simply Exclude. Any PAge or Category exclusion made in the third-party plugins will be over written by changed from Simply Exclude. </p>
			<?php
		
		/*
		?>
		<p>This is a placeholder section for Pages Exclusion. Since WordPress does not yet include Pages in searches this section is pointless. From various sources version 2.6 of WordPress should include native support for including Pages in search results. Look for changes to this plugin shortly after that.</p>
		<?php	
		*/
		}



	}
	
	function add_page_exclude_sidebar_dbx()
	{
		global $post;
		
		$action_key = "is_search";
		
		if ($this->se_cfg['pages'][$action_key][$post->ID] == "on")
			$exclude_page = "yes";
		else
			$exclude_page = "no";
		


		if ($this->wp_version < 2.5)
		{
			?>
			<fieldset id="exclude_search_page" class="dbx-box">
				<h3 class="dbx-handle"><?php _e('Exclude from Search?') ?></h3> 
				<div class="dbx-content">
			<?php
		}
		?>
				<p><?php
				if ($this->wp_version >= 2.5)
				{
					?>Select this option 'Yes' to exclude this page from Searches or visit <a href="<?php echo get_option('siteurl') ?>/wp-admin/options-general.php?page=simplyexclude&amp;se_admin[action]=edit_pages">Simply Exclude</a> Settings page to mass edit all Pages.<br /><?php
				} ?>
					<select name="se_page_exclude">
						<option value='No' selected><?php echo _e('No'); ?></option>
						<option value='Yes' <?php if ($exclude_page == "yes") echo "selected"; ?>><?php _e('Yes'); ?></option>
					</select>
				</p>
		<?php 
		if ($this->wp_version < 2.5)
		{
			?>
				</div>
			</fieldset>
			<?php
		}
	}
	

	function save_page_exclude_answer()
	{
		if (!isset($_REQUEST['post_ID']))
			return;
		
		if (!isset($_REQUEST['se_page_exclude']))
			return;
		
		$post_id = 	$_REQUEST['post_ID'];
		$action_key = "is_search";
		
		if ($_REQUEST['se_page_exclude'] == "Yes")
			$this->se_cfg['pages'][$action_key][$post_id] = "on";
		else
			$this->se_cfg['pages'][$action_key][$post_id] = "";

		$this->se_save_config();				
	}
	
	
	// THIRD PARTY FUNCTIONS
	/////////////////////////////////////////////////////////////////
		
/*
	function se_check_google_xml_sitemap_exclude_cats()
	{
		return;
		
		
		if (!$this->GA_generatorObject) return;

		if ((!$this->GA_generatorObject->_options["sm_b_exclude_cats"])
		 || (count($this->GA_generatorObject->_options["sm_b_exclude_cats"]) == 0))
			return;

		// We only care about 
		if ((!isset($this->se_cfg['cats']['actions']['is_search']))
		 || ($this->se_cfg['cats']['actions']['is_search'] != "e"))
			return;

		if (!isset($this->se_cfg['cats']['is_search']))
			$this->se_cfg['cats']['is_search'] = array();

		foreach($this->GA_generatorObject->_options["sm_b_exclude_cats"] as $google_sitemap_cat_id) {
			$this->se_cfg['cats']['is_search'][$google_sitemap_cat_id] = "on"; 

		}
	}
*/
	function se_update_google_xml_sitemap_exclude_cats()
	{
		// If the user didn't elect to sync the excluded page with the Google Xml Sitemap -- return;
		if ((!isset($this->se_cfg['options']['google-sitemap-generator']['actions']['categories']['update']))
		 || ($this->se_cfg['options']['google-sitemap-generator']['actions']['categories']['update'] !== true))
			return;

		unset($this->GA_generatorObject->_options['sm_b_exclude_cats']);
		$this->GA_generatorObject->_options['sm_b_exclude_cats'] = array();

		if ($_REQUEST['se_admin']['cats']['actions']['is_search'] == "e")
		{		
			foreach($_REQUEST['se_admin']['cats']['is_search'] as $cat_idx => $cal_status)
			{
				if (array_search($cat_idx, $this->GA_generatorObject->_options['sm_b_exclude_cats']) === false)
					$this->GA_generatorObject->_options['sm_b_exclude_cats'][] = $cat_idx;
			}
		}
		else
		{
			$all_cat_ids = get_all_category_ids();
			if (!$all_cat_ids)
				$all_cat_ids = array();

			foreach($all_cat_ids as $cat_idx)
			{
				if (!isset($_REQUEST['se_admin']['cats']['is_search'][$cat_idx]))
					$this->GA_generatorObject->_options['sm_b_exclude_cats'][] = $cat_idx;
			}
		}
		update_option("sm_options", $this->GA_generatorObject->_options);
	}		


/*
	function se_check_google_sitemap_exclude_pages()
	{
		return;

		if (!$this->GA_generatorObject) return;

		if ((!$this->GA_generatorObject->_options["sm_b_exclude"])
		 || (count($this->GA_generatorObject->_options["sm_b_exclude"]) == 0))
			return;

		// We only care about 
		if ((!isset($this->se_cfg['pages']['actions']['is_search']))
		 || ($this->se_cfg['pages']['actions']['is_search'] != "e"))
			return;

		if (!isset($this->se_cfg['pages']['is_search']))
			$this->se_cfg['pages']['is_search'] = array();

		$all_page_ids = get_all_page_ids();
		if (!$all_page_ids)
			$all_page_ids = array();

		foreach($this->GA_generatorObject->_options["sm_b_exclude"] as $google_sitemap_page_id) {

			if (array_search($google_sitemap_page_id, $all_page_ids) === false)
				continue;

			if (count($this->se_cfg['pages']['is_search'])) {
				if (!isset($this->se_cfg['pages']['is_search'][$google_sitemap_page_id])) {
					$this->se_cfg['pages']['is_search'][$google_sitemap_page_id] = "on";
				}
			}
			else 
				$this->se_cfg['pages']['is_search'][$google_sitemap_page_id] = "on";
		}			
		if (count($this->se_cfg['pages']))
			asort($this->se_cfg['pages']);
	}
*/
	function se_update_google_xml_sitemap_exclude_pages()
	{
		if ((!isset($this->se_cfg['options']['google-sitemap-generator']['actions']['pages']['update']))
		 || ($this->se_cfg['options']['google-sitemap-generator']['actions']['pages']['update'] !== true))
			return;

		// If both arrays are empty then we don't have anything to do -- return
		//echo "sm_b_exclude<pre>"; print_r($this->GA_generatorObject->_options["sm_b_exclude"]); echo "</pre>";
		//exit;
		
		if ((count($this->GA_generatorObject->_options["sm_b_exclude"]) == 0)
 		 && (count($_REQUEST['se_admin']['pages']['is_search']) == 0))
			return;

		$all_page_ids = get_all_page_ids();
		if (!$all_page_ids)
			$all_page_ids = array();

		if ($_REQUEST['se_admin']['pages']['actions']['is_search'] == "e") {

			// Remove all Pages from the Google XML Sitemap exclude array. Then we will add the new ones back. 
			foreach($this->GA_generatorObject->_options["sm_b_exclude"] as $idx => $google_sitemap_page_id) {

				if (array_search($google_sitemap_page_id, $all_page_ids) !== false)
					unset($this->GA_generatorObject->_options["sm_b_exclude"][$idx]);
			}
			foreach($_REQUEST['se_admin']['pages']['is_search'] as $se_pages_idx => $se_page_status)
			{
				if (array_search($se_pages_idx, $this->GA_generatorObject->_options["sm_b_exclude"]) === false) 
					$this->GA_generatorObject->_options["sm_b_exclude"][] = $se_pages_idx;
			}
		}
		else {

			foreach($this->GA_generatorObject->_options["sm_b_exclude"] as $idx => $google_sitemap_page_id) {
				if (array_search($google_sitemap_page_id, $all_page_ids) !== false)
					unset($this->GA_generatorObject->_options["sm_b_exclude"][$idx]);
			}		

			foreach($all_page_ids as $page_idx => $page_id) {
				if (array_key_exists($page_id, $_REQUEST['se_admin']['pages']['is_search']) === false)
				{
					if (array_search($page_id, $this->GA_generatorObject->_options["sm_b_exclude"]) === false) 
						$this->GA_generatorObject->_options["sm_b_exclude"][] = $page_id;
				}
			}
		}
		//echo "GA_generatorObject : after<pre>"; print_r($this->GA_generatorObject->_options["sm_b_exclude"]); echo "</pre>";

		update_option("sm_options", $this->GA_generatorObject->_options);
	}	
	
		
	function se_update_search_unleashed_exclude_cats()
	{
		if ((!isset($this->se_cfg['options']['search-unleashed']['actions']['categories']['update']))
		 || ($this->se_cfg['options']['search-unleashed']['actions']['categories']['update'] !== true))
			return;
		
		$search_unleashed_options = get_option( 'search_unleashed', $options );
		if (strlen($search_unleashed_options['exclude_cat']))
		{
			$search_unleashed_exclude = explode(',', $search_unleashed_options['exclude_cat']);
			if ($search_unleashed_exclude)
			{
				foreach($search_unleashed_exclude as $ex_idx => $ex_item)
				{
					$search_unleashed_exclude[$ex_idx] = trim($ex_item);
				}
			}
			else
				$search_unleashed_exclude = array();			
		}
		else
			$search_unleashed_exclude = array();

		$all_cat_ids = get_all_category_ids();
		if (!$all_cat_ids)
			$all_cat_ids = array();
			
			
		if ($_REQUEST['se_admin']['cats']['actions']['is_search'] == "e")
		{	
			foreach($search_unleashed_exclude as $idx => $search_exclude_cat_id) {
				if (array_search($search_exclude_cat_id, $all_cat_ids) !== false)
					unset($search_unleashed_exclude[$idx]);
			}

			foreach($_REQUEST['se_admin']['cats']['is_search'] as $se_cat_idx => $se_cat_status)
			{
				if (array_search($se_cat_idx, $search_unleashed_exclude) === false) 
					$search_unleashed_exclude[] = $se_cat_idx;
			}
			
		}
		else
		{			
			foreach($search_unleashed_exclude as $idx => $search_exclude_cat_id) {
				if (array_search($search_exclude_cat_id, $all_cat_ids) !== false)
					unset($search_unleashed_exclude[$idx]);
			}
			
			foreach($all_cat_ids as $cat_idx => $cat_id) {
				if (array_key_exists($cat_id, $_REQUEST['se_admin']['cats']['is_search']) === false)
				{
					if (array_search($cat_id, $search_unleashed_exclude) === false) 
						$search_unleashed_exclude[] = $cat_id;
				}
			}
		}
		$search_unleashed_options['exclude_cat'] = "";
		if (count($search_unleashed_exclude))
		{
			$search_unleashed_options['exclude_cat'] = implode(',', $search_unleashed_exclude);
		}
		update_option( 'search_unleashed', $search_unleashed_options );
	}
	
	function se_update_search_unleashed_exclude_pages()
	{
		if ((!isset($this->se_cfg['options']['search-unleashed']['actions']['pages']['update']))
		 || ($this->se_cfg['options']['search-unleashed']['actions']['pages']['update'] !== true))
			return;
		
		$search_unleashed_options = get_option( 'search_unleashed', $options );
		
		if (strlen($search_unleashed_options['exclude']))
		{
			$search_unleashed_exclude = explode(',', $search_unleashed_options['exclude']);
			if ($search_unleashed_exclude)
			{
				foreach($search_unleashed_exclude as $ex_idx => $ex_item)
				{
					$search_unleashed_exclude[$ex_idx] = trim($ex_item);
				}
			}
			else
				$search_unleashed_exclude = array();			
		}
		else
			$search_unleashed_exclude = array();
		
		
		$all_page_ids = get_all_page_ids();
		if (!$all_page_ids)
			$all_page_ids = array();
		
		if ($_REQUEST['se_admin']['pages']['actions']['is_search'] == "e") {

			// Remove all Pages from the Google XML Sitemap exclude array. Then we will add the new ones back. 
			foreach($search_unleashed_exclude as $idx => $search_exclude_page_id) {
				if (array_search($search_exclude_page_id, $all_page_ids) !== false)
					unset($search_unleashed_exclude[$idx]);
			}
			
			foreach($_REQUEST['se_admin']['pages']['is_search'] as $se_pages_idx => $se_page_status)
			{
				if (array_search($se_pages_idx, $search_unleashed_exclude) === false) 
					$search_unleashed_exclude[] = $se_pages_idx;
			}
		}
		else {

			foreach($search_unleashed_exclude as $idx => $search_exclude_page_id) {
				if (array_search($search_exclude_page_id, $all_page_ids) !== false)
					unset($search_unleashed_exclude[$idx]);
			}		

			foreach($all_page_ids as $page_idx => $page_id) {
				if (array_key_exists($page_id, $_REQUEST['se_admin']['pages']['is_search']) === false)
				{
					if (array_search($page_id, $search_unleashed_exclude) === false) 
						$search_unleashed_exclude[] = $page_id;
				}
			}
		}
		
		$search_unleashed_options['exclude'] = "";
		if (count($search_unleashed_exclude))
		{
			$search_unleashed_options['exclude'] = implode(',', $search_unleashed_exclude);
		}
		update_option( 'search_unleashed', $search_unleashed_options );
	}
}
$simplyexclude = new SimplyExclude();

// Need to determine of the site uses the Google XML Sitemap Plugin
if (is_file(WP_PLUGIN_DIR . '/google-sitemap-generator/sitemap-core.php'))
{
	include (WP_PLUGIN_DIR. '/google-sitemap-generator/sitemap-core.php');
	$simplyexclude->GA_generatorObject = new GoogleSitemapGenerator();
	$simplyexclude->GA_generatorObject->LoadOptions();
}

/*
function myBlogPostsFilter($query) 
{
	global $wp_query;
	
	if ($query->is_search)
	{
		$query->set('cat','-3,-4,-5,-6,-7');
		$query->set('page','-33');
	}
	return $query;
}
add_filter('pre_get_posts','myBlogPostsFilter');
*/
?>
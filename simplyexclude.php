<?php
/*
Plugin Name: Simply Exclude
Plugin URI: http://www.codehooligans.com/2008/04/27/simply-exclude-plugin/
Description: Provides an interface to selectively exclude/include categories, tags and page from the 4 actions used by WordPress. is_front, is_archive, is_search, is_feed.
Author: Paul Menard
Version: 1.2
Author URI: http://www.codehooligans.com

Revision history
1.0 - 2007-11-20: Initial release
1.1 - 2008-12-15: Added logic to work with WP version greater than 2.2
1.5 - 20008-04-27 Fixed display issues. Changes 'List' to 'Archive'. Added tags inclusion/exclusion login. Works only with WP 2.3 and greater.
*/

class SimplyExclude
{
	var $se_cfg;
	var $options_key;
	var $default_IsActions;
	
	var $categories;
	var $pages;
	
	var $wp_version;
	
	function SimplyExclude()
	{
		global $wp_version;
		$this->wp_version = $wp_version;
		
		$this->_admin_menu_label	= "Simply Exclude";
		$this->options_key			= "simplyexclude";

		$this->se_load_config();

		add_action('admin_menu', array(&$this,'admin_init_proc'));

		// Add our own admin menu
		add_action('admin_menu', array(&$this,'se_add_nav'));

	  	if ($_REQUEST['page'] == $this->options_key)
			add_action('admin_head', array(&$this,'se_admin_head'));

		if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'activate')
			add_action('init', array(&$this,'se_install'));
			
		// Used to limit the categories displayed on the home page. Simple
		add_filter('pre_get_posts', array(&$this,'se_filters'));
		
		add_action('save_post', array(&$this,'save_page_exclude_answer'));		
	}

	function admin_init_proc()
	{
		if (function_exists('add_meta_box')) {
			add_meta_box($this->_options_key, $this->_admin_menu_label, array(&$this,'add_page_exclude_sidebar_dbx'), 'page');
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


		// Pages Definitions
		$this->default_IsActions['pages'] = array();
		$this->default_IsActions['pages']['is_search']['name']			= "Search";
		$this->default_IsActions['pages']['is_search']['description']	= "Visibility in search results.";
		$this->default_IsActions['pages']['is_search']['action']		= "e";
			
		$this->se_cfg['cfg']['page_name']			= "simplyexclude";
		$tmp_se_cfg = get_option($this->options_key);
		if ($tmp_se_cfg)
		{
			if (!is_array($tmp_se_cfg))
				$this->se_cfg = unserialize($tmp_se_cfg);
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
		if (!isset($this->se_cfg['pages']['actions']))
		{
			foreach($this->default_IsActions['pages'] as $page_key => $page_action)
			{
				$this->se_cfg['pages']['actions'][$page_key] = $page_action['action'];
			}
		}
	}
	
	function se_save_config()
	{
		$ret = update_option($this->options_key, serialize($this->se_cfg));
	}
		
	
	function se_add_nav() 
	{
    	// Add a new menu under Manage:
    	add_options_page('Simply Exclude', 'Simply Exclude', 8, 
			$this->options_key, array(&$this, 'se_manage_page'));
	}

	function se_admin_head()
	{
		?>
		<link rel="stylesheet" href="<?php echo $this->se_cfg['cfg']['myurl'] ?>/simplyexclude_style_admin.css"
		 type="text/css" media="screen" />
		<?php 
	}

	function se_install()
	{
		add_option($this->options_key, 
				serialize($this->se_cfg), 
				"This is the serialized config structures used.");
	}

	function se_manage_page()
	{
		$this->se_display_navigation();

		?>
		<div class="wrap">
		<?php

			if (isset($_REQUEST['se_admin']))
				$se_admin = $_REQUEST['se_admin'];
	
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
	
	
	function se_display_navigation()
	{
		
		?>
		<div id="se_admin_nav">
			<ul>
				<li><a href="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=edit_categories"
					title="Manage Category Exclusions">Manage Categories</a></li>
				<?php
					if ($this->wp_version >= 2.3)
					{
						?>
						<li><a href="?page=<?php 
							echo $this->options_key ?>&amp;se_admin[action]=edit_tags"
							title="Manage Tag Exclusions">Manage Tags</a></li><?php
					}
				?>						
				<li><a href="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=edit_pages"
					title="Manage Page Exclusions">Manage Pages</a></li>
			</ul>
		</div>
		<?php
	}
		
	// CATEGORY FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_categories_panel($se_admin)
	{
		?>
		<h2>Manage Category Exclusions</h2>
		<?php
		if ($se_admin['action'] == "save_categories")
		{
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
			//$this->categories = $wpdb->get_results("SELECT cat_ID, cat_name 
			//						FROM $wpdb->categories ORDER BY cat_name");
										
			$this->categories = get_categories();			
		}
	}
	
	function se_show_categories_form()
	{
		$this->se_load_categories();
		if ($this->categories)
		{
			$this->display_instructions('cats');
			?>
			<form name="cat_exclusion" id="cat_exclusion" 
				action="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=save_categories" method="post">

				<table  width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th><?php _e('Action Name') ?></th>
		        	<th><?php _e('Description ') ?></th>
		        	<th><?php _e('Inclusion/Exclusion') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				foreach ($this->default_IsActions['cats'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td><?php echo $action_val['name'] ?></td>
						<td><?php echo $action_val['description'] ?></td>
						<td>
							<input type="radio" 
								name="se_admin[cats][actions][<?php echo $action_key ?>]" value="i" 
								<?php if ($this->se_cfg['cats']['actions'][$action_key] == 'i') 
									echo "checked='checked'"; ?> /> Include only<br />
							<input type="radio" 
								name="se_admin[cats][actions][<?php echo $action_key ?>]" value="e" 
								<?php if ($this->se_cfg['cats']['actions'][$action_key] == 'e') 
									echo "checked='checked'"; ?> /> Exclude
						</td>
					<tr>
					<?php
				}
				?>
				</tbody>
				</table>
				<br />
				<table  width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th class="cat-id" scope="col"><?php _e('ID') ?></th>
		        	<th class="cat-name" scope="col"><?php _e('Category Name') ?></th>
		        	<th class="cat-action" scope="col"><?php _e('Exclude from...') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
					foreach($this->categories as $cat_info)
					{	
						$class = ('alternate' == $class) ? '' : 'alternate';
						$this->se_show_cat_item_row($cat_info, $class);
					}
				?>		
				<tr>
					<td>
						<div style="float:right;"><p class="submit"><input type="hidden" name="action" value="editcatvis" /><input type="submit" name="submit" value="<?php _e('Save Changes &raquo;') ?>" />
						
					</td>
				</tr>
				</tbody>
				</table>
				</p></div>				
			</form>
			<?php
		}
	}
	
	function se_show_cat_item_row($cat_info, $class)
	{
		?>
		<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
			<td class="cat-id"><?php echo $cat_info->cat_ID ?></td>
			<td class="cat-name"><?php echo $cat_info->cat_name ?></td>
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
				if ($this->se_cfg['cats'][$action_key][$cat_id] == "on")
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
	
	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////


	// TAG FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_tags_panel($se_admin)
	{
		?>
		<h2>Manage Tag Exclusions</h2>
		<?php
		if ($se_admin['action'] == "save_tags")
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
		if (!$this->tags)
		{
			$this->tags = get_tags();			
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
				action="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=save_tags" method="post">

				<table  width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th><?php _e('Action Name') ?></th>
		        	<th><?php _e('Description ') ?></th>
		        	<th><?php _e('Inclusion/Exclusion') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				foreach ($this->default_IsActions['tags'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td><?php echo $action_val['name'] ?></td>
						<td><?php echo $action_val['description'] ?></td>
						<td>
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
				<table  width="80%" cellpadding="3" cellspacing="3" border="0">
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
					<td>
						<div style="float:right;"><p class="submit"><input type="hidden" name="action" value="editcatvis" /><input type="submit" name="submit" value="<?php _e('Save Changes &raquo;') ?>" />
						
					</td>
				</tr>
				</tbody>
				</table>
				</p></div>				
			</form>
			<?php
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
				if ($this->se_cfg['tags'][$action_key][$tag_id] == "on")
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
	

	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////


	// PAGE FUNCTIONS
	/////////////////////////////////////////////////////////////////
	function se_display_pages_panel($se_admin)
	{
		?>
		<h2>Manage Page Exclusions</h2>
		<?php
		if ($se_admin['action'] == "save_pages")
		{
			if (isset($se_admin['pages']))
			{
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
			$this->pages = $wpdb->get_results("SELECT ID, post_title 
							FROM $wpdb->posts 
							WHERE post_type='page'
							AND post_status='publish'
							ORDER BY post_title");
	}

	function se_show_pages_form()
	{
		$this->display_instructions('pages');
		return;

		$this->se_load_pages();
		if ($this->pages)
		{
			$this->display_instructions('pages');
			?>			
			<form name="page_exclusion" id="page_exclusion" 
				action="?page=<?php 
					echo $this->options_key ?>&amp;se_admin[action]=save_pages" method="post">
				<table  width="80%" cellpadding="3" cellspacing="3" border="0">
				<thead>
		        <tr>
		        	<th><?php _e('Action Name') ?></th>
		        	<th><?php _e('Description ') ?></th>
		        	<th><?php _e('Inclusion/Exclusion Default') ?></th>
		        </tr>
				</thead>
				<tbody>
				<?php
				foreach ($this->default_IsActions['pages'] as $action_key => $action_val)
				{
					$class = ('alternate' == $class) ? '' : 'alternate';
					?>
					<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
						<td><?php echo $action_val['name'] ?></td>
						<td><?php echo $action_val['description'] ?></td>
						<td>
							<input type="radio" 
								name="se_admin[pages][actions][<?php echo $action_key ?>]" value="i" 
								<?php if ($this->se_cfg['pages']['actions'][$action_key] == 'i') 
									echo "checked='checked'"; ?> /> Include only<br />
							
							<input type="radio" 
								name="se_admin[pages][actions][<?php echo $action_key ?>]" value="e" 
								<?php if ($this->se_cfg['pages']['actions'][$action_key] == 'e') 
									echo "checked='checked'"; ?> /> Exclude
						</td>
					<tr>
					<?php
				}
				?>
				</tbody>
				</table>
				<br />


				<table width="80%" cellpadding="3" cellspacing="3">
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
					<td>
						<div style="float:right;"><p class="submit"><input type="hidden" name="action" value="editcatvis" /><input type="submit" name="submit" value="<?php _e('Save Changes &raquo;') ?>" />
						</p></div>
						
					</td>
				</tr>
				</tbody>	
				</table>
				
			</form>
			<?php
		}
	}

	function se_show_page_item_row($page_info, $class = '')
	{
		?>
		<tr <?php if (strlen($class)) echo "class='".$class."'" ?>>
			<td class="page-id"><?php echo $page_info->ID ?></td>
			<td class="page-name"><?php echo $page_info->post_title ?></td>
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
					
				if ($this->se_cfg['pages'][$action_key][$page_id] == "on")
					echo "checked='checked' ";
				?> />
			<?php
		}
	}
	

	// END CONFIG FUNCTIONS
	/////////////////////////////////////////////////////////////////
	
	
	function se_filters($query) 
	{
		//echo "query before<pre>"; print_r($query); echo "</pre>";
		
		foreach ($this->default_IsActions['cats'] as $action_key => $action_val)
		{
			if ($query->{$action_key})
			{
				if (isset($this->se_cfg['cats'][$action_key]))
				{
					$cats_list = $this->se_listify_ids( $this->se_cfg['cats']['actions'][$action_key],
														$this->se_cfg['cats'][$action_key]);
				}
				if (strlen($cats_list))
					$query->set('cat', $cats_list);
			}
		}
		if ($this->wp_version >= 2.3)
		{
			foreach ($this->default_IsActions['tags'] as $action_key => $action_val)
			{
				if ($query->{$action_key})
				{
					if (isset($this->se_cfg['tags'][$action_key]))
					{
						$array_list = array();
						foreach($this->se_cfg['tags'][$action_key] as $key => $val)
						{
							$array_list[] = $key; 
						}

						if ($this->se_cfg['tags']['actions'][$action_key] == "e")
						{
							$query->set('tag__not_in', $array_list);
						}
						else
						{
							$query->set('tag__in', $array_list);
						}

					}
				}
			}
		}
		foreach ($this->default_IsActions['pages'] as $action_key => $action_val)
		{
			//echo "action_key=[". $action_key. "]<br />";
			if ($query->{$action_key})
			{
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
			}
		}
		
		return $query;
	}

	function se_listify_ids($action, $ids)
	{
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
		else if ($type == "pages")
		{
		/*
			?>
			<p>Set the checkbox to exclude the respective page from the action</p>
			<p>So what is the difference between Exclusion and Inclusion?<br />
				<strong>Exclude</strong>: Select this action to exclude Pages from WP 
					action. For example you may wish to exclude a Page from Searches.<br />
				<strong>Include</strong>: Select the Page you wish to be included for certain 
					WP actions. For example you want only certain Pages displayed from a Search. Note with Include only those checked items will be included in the 
					WP action. And as new Pages are added they will need to be checked here. </p>
			<?php
		*/
		?>
		<p>This is a placeholder section for Pages Exclusion. Since WordPress does not yet include Pages in searches this section is pointless. From various sources version 2.6 of WordPress should include native support for including Pages in search results. Look for changes to this plugin shortly after that.</p>
		<?php	
		}
	}
	
	function add_page_exclude_sidebar_dbx()
	{
		global $post;
		
		$action_key = "is_search";
		
		if ($this->se_cfg['pages'][$action_key][$post->ID] == "on")
			$exclude_page = "yes";

		if ($this->wp_version < 2.5)
		{
			?>
			<fieldset id="use_ssl" class="dbx-box">
				<h3 class="dbx-handle"><?php _e('Exclude from Search?') ?></h3> 
				<div class="dbx-content">
			<?php
		}
		?>
				<p><?php
				if ($this->wp_version >= 2.5)
				{
					?>Select this option 'Yes' to exclude this page from Searches or visit <a href="<?php echo get_option('siteurl') ?>/wp-admin/edit.php?page=simplyexclude&amp;se_admin[action]=edit_pages">Simply Exclude</a> Settings page to mass edit all Pages.<br /><?php
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
		if (!$_REQUEST['post_ID'])
			return;
		
		if (!$_REQUEST['se_page_exclude'])
			return;
		
		$post_id = 	$_REQUEST['post_ID'];
		$action_key = "is_search";
		
		if ($_REQUEST['se_page_exclude'] == "Yes")
			$this->se_cfg['pages'][$action_key][$post_id] = "on";
		else
			$this->se_cfg['pages'][$action_key][$post_id] = "";

		$this->se_save_config();				
	}
}
$simplyexclude = new SimplyExclude();

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
<?php
/***************************************************************************
 *										admin_forums.php
 *                            -------------------
 *   begin                : Thursday, Jul 12, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id$  
 *
 *
 ***************************************************************************/

if($setmodules==1)
{
        $file = basename(__FILE__);
        $module['Forums']['Manage'] = $file;
        return;
}

function check_forum_name($forumname)
{
	global $db;
	
	$sql = "SELECT * from " . FORUMS_TABLE . "WHERE forum_name = '$forumname'";
	$result = $db->sql_query($sql);
	if( !$result )
	{  
		message_die(GENERAL_ERROR, "Couldn't get list of Categories", "", __LINE__, __FILE__, $sql);
	}
	if ($db->sql_numrows($result) > 0)
	{
		message_die(GENERAL_ERROR, "A forum with that name already exists", "", __LINE__, __FILE__, $sql);
	}
}

function get_info($mode, $id)
{
	global $db;

	switch($mode)
	{
		case 'category':
			$table = CATEGORIES_TABLE;
			$idfield = 'cat_id';
			$namefield = 'cat_title';
			break;
		case 'forum':
			$table = FORUMS_TABLE;
			$idfield = 'forum_id';
			$namefield = 'forum_name';
			break;
		default:
			message_die(GENERAL_ERROR, "Wrong mode for generating select list", "", __LINE__, __FILE__);
	}
	$sql = "	SELECT *
				FROM $table
				WHERE $idfield = $id";
	if( !$result = $db->sql_query($sql) )
	{  
		message_die(GENERAL_ERROR, "Couldn't get Forum/Category information", "", __LINE__, __FILE__, $sql);
	}
	if( $db->sql_numrows($result) != 1 )
	{
		message_die(GENERAL_ERROR, "Forum/Category doesn't exist or multiple forums/categories with ID $id", "", __LINE__, __FILE__);
	}
	return $db->sql_fetchrow($result);
}

function get_list($mode, $id, $select)
{
	global $db;

	switch($mode)
	{
		case 'category':
			$table = CATEGORIES_TABLE;
			$idfield = 'cat_id';
			$namefield = 'cat_title';
			break;
		case 'forum':
			$table = FORUMS_TABLE;
			$idfield = 'forum_id';
			$namefield = 'forum_name';
			break;
		default:
			message_die(GENERAL_ERROR, "Wrong mode for generating select list", "", __LINE__, __FILE__);
	}
	
	$sql = "SELECT * FROM $table";
	if( $select == FALSE)
	{
		$sql .= " WHERE $idfield != '$id'";
	}
	if( !$result = $db->sql_query($sql) )
	{  
		message_die(GENERAL_ERROR, "Couldn't get list of Categories/Forums", "", __LINE__, __FILE__, $sql);
	}
	$cat_list = "";
	while( $row = $db->sql_fetchrow($result) )
	{
		$s = "";
		if ($row[$idfield] == $id)
		{
			$s = " SELECTED";
		}
		$catlist .= "<OPTION VALUE=\"$row[$idfield]\"$s>$row[$namefield]</OPTION>\n";
	}
	return($catlist);
}

//
// Include required files, get $phpEx and check permissions
//
require('pagestart.inc');

if (isset($HTTP_POST_VARS['mode']))
{
	$mode = $HTTP_POST_VARS['mode'];
}
elseif (isset($HTTP_GET_VARS['mode']))
{
	$mode = $HTTP_GET_VARS['mode'];
}
else
{
	unset($mode);
}

if(isset($mode))  // Are we supposed to do something?
{
	switch($mode)
	{
		case 'forum_sync':
			sync('forum', $HTTP_GET_VARS['forum_id']);
			$show_index = TRUE;
			break;
		case 'createforum':  // Create a forum in the DB
			$sql = "SELECT 
							max(forum_order) as max_order
						FROM ".FORUMS_TABLE." 
						WHERE cat_id = '".$HTTP_POST_VARS['cat_id']."'";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't get order number from forums table", "", __LINE__, __FILE__, $sql);
			}
			$row = $db->sql_fetchrow($result);
			$max_order = $row['max_order'];
			$next_order = $max_order + 1;

			// There is no problem having duplicate forum names so we won't check for it.
			$sql = "INSERT 
						INTO ".FORUMS_TABLE."(
							forum_name,
							cat_id,
							forum_desc,
							forum_order,
							forum_status)
						VALUES (
							'".$HTTP_POST_VARS['forumname']."',
							'".$HTTP_POST_VARS['cat_id']."',
							'".$HTTP_POST_VARS['forumdesc']."',
							'".$next_order."',
							'".$HTTP_POST_VARS['forumstatus']."')";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't insert row in forums table", "", __LINE__, __FILE__, $sql);
			}
			$show_index = TRUE;
			break;
		case 'modforum':  // Modify a forum in the DB
			$sql = "UPDATE ".FORUMS_TABLE." SET 
							forum_name = '".$HTTP_POST_VARS['forumname']."',
							cat_id = '".$HTTP_POST_VARS['cat_id']."',
							forum_desc = '".$HTTP_POST_VARS['forumdesc']."',
							forum_status = '".$HTTP_POST_VARS['forumstatus']."'
						WHERE forum_id = '".$HTTP_POST_VARS['forum_id']."'";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't update forum information", "", __LINE__, __FILE__, $sql);
			}
			$show_index = TRUE;
			break;
							
		case 'addcat':
			$sql = "SELECT 
							max(cat_order) as max_order
						FROM ".CATEGORIES_TABLE;
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't get order number from categories table", "", __LINE__, __FILE__, $sql);
			}
			$row = $db->sql_fetchrow($result);
			$max_order = $row['max_order'];
			$next_order = $max_order + 1;
			// There is no problem having duplicate forum names so we won't check for it.
			$sql = "INSERT INTO ".CATEGORIES_TABLE."(
							cat_title,
							cat_order)
						VALUES (
							'".$HTTP_POST_VARS['catname']."',
							'".$next_order."')";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't insert row in categories table", "", __LINE__, __FILE__, $sql);
			}
			$show_index = TRUE;
			break;
		case 'addforum':
		case 'editforum':
			if ($mode == 'editforum')
			{
				// $newmode determines if we are going to INSERT or UPDATE after posting?
				$newmode = 'modforum';
				$buttonvalue = 'Change';
				
				$forum_id = $HTTP_GET_VARS['forum_id'];

				$row = get_info('forum', $forum_id);
				$forumname = $row['forum_name'];
				$cat_id = $row['cat_id'];
				$forumdesc = $row['forum_desc'];
				$forumstatus = $row['forum_status'];
			}
			else
			{
				$newmode = 'createforum';
				$buttonvalue = 'Create';

				$forumname = stripslashes($HTTP_POST_VARS['forumname']);
				$cat_id = $HTTP_POST_VARS['cat_id'];
				$forumdesc = '';
				$forumstatus = FORUM_UNLOCKED;
				$forum_id = '';
			}
				
			$catlist = get_list('category', $cat_id, TRUE);
			
			$forumstatus == FORUM_LOCKED ? $forumlocked = "selected" : $forumunlocked = "selected";
			$statuslist = "<OPTION VALUE=\"".FORUM_UNLOCKED."\" $forumunlocked>Unlocked</OPTION>\n";
			$statuslist .= "<OPTION VALUE=\"".FORUM_LOCKED."\" $forumlocked>Locked</OPTION>\n";
			
			$template->set_filenames(array(
				"body" => "admin/forum_edit_body.tpl")
			);
			$template->assign_vars(array(
				'FORUMNAME' => $forumname,
				'DESCRIPTION' => $forumdesc,
				'S_CATLIST' => $catlist,
				'S_STATUSLIST' => $statuslist,
				'S_FORUMID' => $forum_id,
				'S_NEWMODE' => $newmode,
				'BUTTONVALUE' => $buttonvalue)
			);
			$template->pparse("body");
			
			
			break;
		case 'editcat':
			$newmode = 'modcat';
			$buttonvalue = 'Change';
			
			$cat_id = $HTTP_GET_VARS['cat_id'];
			$row = get_info('category', $catid);
			$cat_title = $row['cat_title'];
			
			$template->set_filenames(array(
				"body" => "admin/category_edit_body.tpl")
			);
			$template->assign_vars(array(
				'CAT_TITLE' => $cat_title,
				'S_CATID' => $cat_id,
				'S_NEWMODE' => $newmode,
				'BUTTONVALUE' => $buttonvalue)
			);
			$template->pparse("body");
		
			break;
		case 'modcat':
			$sql = "UPDATE ".CATEGORIES_TABLE." SET 
							cat_title = '".$HTTP_POST_VARS['cat_title']."'
						WHERE cat_id = '".$HTTP_POST_VARS['cat_id']."'";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't update forum information", "", __LINE__, __FILE__, $sql);
			}
			print "Modforum: ". $HTTP_POST_VARS['forumname']." sql= <pre>$sql</pre>";
			$show_index = TRUE;
			break;
		case 'movedelforum':
			$from_id = $HTTP_POST_VARS['from_id'];
			$to_id = $HTTP_POST_VARS['to_id'];
			$delete_old = $HTTP_POST_VARS['delete_old'];
			
			print "move '$from_id' to '$to_id'";
			
			$sql = "SELECT * FROM ".FORUMS_TABLE." WHERE forum_id IN ($from_id, $to_id)";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't verify existence of forums", "", __LINE__, __FILE__, $sql);
			}
			if($db->sql_numrows($result) != 2)
			{
				message_die(GENERAL_ERROR, "Ambiguous forum ID's", "", __LINE__, __FILE__);
			}
			
			// Either delete or move all posts in a forum
			if($delete_old == 1)
			{
				include($phpbb_root_path . "/include/prune.$phpEx");
				prune($from_id, FALSE); // Delete everything from forum
			}
			else
			{
				$sql = "UPDATE ".TOPICS_TABLE." SET 
								forum_id = '$to_id'
							WHERE forum_id = '$from_id'";
				if( !$result = $db->sql_query($sql) )
				{  
					message_die(GENERAL_ERROR, "Couldn't move topics to other forum", "", __LINE__, __FILE__, $sql);
				}
				$sql = "UPDATE ".POSTS_TABLE." SET 
								forum_id = '$to_id'
							WHERE forum_id = '$from_id'";
				if( !$result = $db->sql_query($sql) )
				{  
					message_die(GENERAL_ERROR, "Couldn't move posts to other forum", "", __LINE__, __FILE__, $sql);
				}
				sync('forum', $to_id);
			}
			
			$sql = "DELETE FROM ".FORUMS_TABLE."
						WHERE forum_id = '$from_id'";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't delete forum", "", __LINE__, __FILE__, $sql);
			}
		
			$show_index = TRUE;
			break;
		case 'movedelcat':
			$from_id = $HTTP_POST_VARS['from_id'];
			$to_id = $HTTP_POST_VARS['to_id'];
			print "move '$from_id' to '$to_id'";
			
			$sql = "SELECT * FROM ".CATEGORIES_TABLE." WHERE cat_id IN ($from_id, $to_id)";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't verify existence of categories", "", __LINE__, __FILE__, $sql);
			}
			if($db->sql_numrows($result) != 2)
			{
				message_die(GENERAL_ERROR, "Ambiguous category ID's", "", __LINE__, __FILE__);
			}
			
			$sql = "UPDATE ".FORUMS_TABLE." SET 
							cat_id = '$to_id'
						WHERE cat_id = '$from_id'";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't move forums to other category", "", __LINE__, __FILE__, $sql);
			}
			
			$sql = "DELETE FROM ".CATEGORIES_TABLE."
						WHERE cat_id = '$from_id'";
			if( !$result = $db->sql_query($sql) )
			{  
				message_die(GENERAL_ERROR, "Couldn't delete category", "", __LINE__, __FILE__, $sql);
			}
		
			$show_index = TRUE;
			break;
		case 'deletecat':
			print "Deletecat";
			$cat_id = $HTTP_GET_VARS['cat_id'];
			$to_ids = get_list('category', $cat_id, FALSE);
			$buttonvalue = "Move&Delete";
			$newmode = 'movedelcat';
			$catinfo = get_info('category', $cat_id);
			$name = $catinfo['cat_title'];
			
			$template->set_filenames(array(
				"body" => "admin/forum_delete_body.tpl")
			);
			$template->assign_vars(array(
				'NAME' => $name,
				'S_FORUM_ACTION' => $PHP_SELF,
				'S_FROM_ID' => $cat_id,
				'S_TO_IDS' => $to_ids,
				'S_NEWMODE' => $newmode,
				'BUTTONVALUE' => $buttonvalue)
			);
			$template->pparse("body");
			break;
		case 'deleteforum':
			print 'Deleteforum';
			$forum_id = $HTTP_GET_VARS['forum_id'];
			$to_ids = get_list('forum', $forum_id, FALSE);
			$buttonvalue = "Move&Delete";
			$newmode = 'movedelforum';
			$foruminfo = get_info('forum', $forum_id);
			$name = $foruminfo['forum_name'];
			
			$template->set_filenames(array(
				"body" => "admin/forum_delete_body.tpl")
			);
			$template->assign_vars(array(
				'NAME' => $name,
				'S_FORUM_ACTION' => $PHP_SELF,
				'S_FROM_ID' => $forum_id,
				'S_TO_IDS' => $to_ids,
				'S_NEWMODE' => $newmode,
				'BUTTONVALUE' => $buttonvalue)
			);
			$template->pparse("body");
			break;
		case 'cat_order':
		case 'forum_order':
			message_die(GENERAL_ERROR, "Sorry, not implemented yet");
			break;
		default:
			print "Oops! Wrong mode..";
	}
	if ($show_index != TRUE)
	{
		include('page_footer_admin.'.$phpEx);
		exit;
	}
}

//
// Start page proper
//
$template->set_filenames(array(
	"body" => "admin/forums_body.tpl")
);

$sql = "SELECT cat_id, cat_title, cat_order
	FROM " . CATEGORIES_TABLE . "
	ORDER BY cat_order";
if(!$q_categories = $db->sql_query($sql))
{
	message_die(GENERAL_ERROR, "Could not query categories list", "", __LINE__, __FILE__, $sql);
}

if($total_categories = $db->sql_numrows($q_categories))
{
	$category_rows = $db->sql_fetchrowset($q_categories);

	$sql = "SELECT *
					FROM " . FORUMS_TABLE . "
					ORDER BY cat_id, forum_order";

	if(!$q_forums = $db->sql_query($sql))
	{
		message_die(GENERAL_ERROR, "Could not query forums information", "", __LINE__, __FILE__, $sql);
	}

	if( !$total_forums = $db->sql_numrows($q_forums) )
	{
		message_die(GENERAL_MESSAGE, $lang['No_forums']);
	}
	$forum_rows = $db->sql_fetchrowset($q_forums);

	//
	// Okay, let's build the index
	//
	$gen_cat = array();


	for($i = 0; $i < $total_categories; $i++)
	{
		$cat_id = $category_rows[$i]['cat_id'];

		for($j = 0; $j < $total_forums; $j++)
		{
			$forum_id = $forum_rows[$j]['forum_id'];

			if(!$gen_cat[$cat_id])
			{
				$template->assign_block_vars("catrow", array(
					"CAT_ID" => $cat_id,
					"CAT_DESC" => stripslashes($category_rows[$i]['cat_title']),
					"CAT_EDIT" => "<a href='$PHP_SELF?mode=editcat&cat_id=$cat_id'>Edit</a>",
					"CAT_DELETE" => "<a href='$PHP_SELF?mode=deletecat&cat_id=$cat_id'>Delete</a>",
					"CAT_UP" => "<a href='$PHP_SELF?mode=cat_order&pos=1&cat_id=$cat_id'>Move up</a>",
					"CAT_DOWN" => "<a href='$PHP_SELF?mode=cat_order&pos=-1&forum_id=$cat_id'>Move down</a>",
					"U_VIEWCAT" => append_sid("index.$phpEx?viewcat=$cat_id"),
					"U_ADDFORUM" => append_sid("$PHP_SELF?mode=addforum&cat_id=$cat_id"),
					"ADDFORUM" => "Add Forum")
				);
				$gen_cat[$cat_id] = 1;
			}
			if( $forum_rows[$j]['cat_id'] != $cat_id)
			{
				continue;
			}

			//
			// This should end up in the template using IF...ELSE...ENDIF
			//
			$row_color == "#DDDDDD" ?	$row_color = "#CCCCCC" : $row_color = "#DDDDDD";

			$template->assign_block_vars("catrow.forumrow",	array(
				"FORUM_NAME" => stripslashes($forum_rows[$j]['forum_name']),
				"FORUM_DESC" => stripslashes($forum_rows[$j]['forum_desc']),
				"ROW_COLOR" => $row_color,
				"NUM_TOPICS" => $forum_rows[$j]['forum_topics'],
				"NUM_POSTS" => $forum_rows[$j]['forum_posts'],
				"U_VIEWFORUM" => append_sid($phpbb_root_path."viewforum.$phpEx?" . POST_FORUM_URL . "=$forum_id&" . $forum_rows[$j]['forum_posts']),
				"FORUM_EDIT" => "<a href='".append_sid("$PHP_SELF?mode=editforum&forum_id=$forum_id")."'>Edit</a>",
				"FORUM_DELETE" => "<a href='".append_sid("$PHP_SELF?mode=deleteforum&forum_id=$forum_id")."'>Delete</a>",
				"FORUM_UP" => "<a href='".append_sid("$PHP_SELF?forum_mode=order&pos=1&forum_id=$forum_id")."'>Move up</a>",
				"FORUM_DOWN" => "<a href='".append_sid("$PHP_SELF?mode=forum_order&pos=-1&forum_id=$forum_id")."'>Move down</a>",
				"FORUM_SYNC" => "<a href='".append_sid("$PHP_SELF?mode=forum_sync&forum_id=$forum_id")."'>Sync</a>")
			);
		} // for ... forums
		$template->assign_block_vars("catrow.forumrow", array(
			"S_ADDFORUM" => '<FORM METHOD="POST" ACTION="'.append_sid($PHP_SELF).'">
					<INPUT TYPE="text" NAME="forumname">
					<INPUT TYPE="hidden" NAME="cat_id" VALUE="'.$cat_id.'">
					<INPUT TYPE="hidden" NAME="mode" VALUE="addforum">
					<INPUT TYPE="submit" NAME="submit" VALUE="Create new Forum">',
			"S_ADDFORUM_ENDFORM" => "</FORM>")
		);
	} // for ... categories
	// Extra 'category' to create new categories at the end of the list.
	$template->assign_block_vars("catrow", array(
		"S_ADDCAT" => '<FORM METHOD="POST" ACTION="'.append_sid($PHP_SELF).'">
				<INPUT TYPE="text" NAME="catname">
				<INPUT TYPE="hidden" NAME="mode" VALUE="addcat">
				<INPUT TYPE="submit" NAME="submit" VALUE="Create new category">',
		"S_ADDCAT_ENDFORM" => "</FORM>")
	);

}// if ... total_categories
else
{
	message_die(GENERAL_MESSAGE, "There are no Categories or Forums on this board", "", __LINE__, __FILE__, $sql);
}

//
// Generate the page
//
$template->pparse("body");

//
// Page Footer
//
include('page_footer_admin.'.$phpEx);
?>

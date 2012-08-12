<?php
/**
*
* @package phpBB3
* @copyright (c) 2007 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

//define('CAMO_KEY', '');
define('ASSETS_DOMAIN', 'camo.phpbb.com');

/**
 * Rewrites an image tag into a version that can be used by a Camo asset server
 *
 * @param	array	$object	The array containing the data to rewrite
 * @param	string	$key	The key into the array. The element to rewrite.
 * @return	void
 */
function rewrite_images(&$object, $key)
{
	if (!empty($object[$key]))
	{
		if (preg_match_all('#<img src="([^"]+)" [^/]+ />#', $object[$key], $matches))
		{
			foreach ($matches[1] as $url)
			{
				// Skip smilies
				if ($url[0] == '.')
				{
					continue;
				}

				// Don't rewrite requests for .com to camo, just change the protocol
				if (stripos($url, 'http://www.phpbb.com/') !== false)
				{
					$object[$key] = preg_replace('#http:#', 'https:', $object[$key]);
				}
				// Nothing cyclical please
				else if (stripos($url, 'https://camo.phpbb.com/') !== false || stripos($url, 'https://www.phpbb.com/') !== false)
				{
					continue;
				}
				else
				{
					$digest = hash_hmac('sha1', $url, CAMO_KEY);
					$object[$key] = str_replace($url, 'https://' . ASSETS_DOMAIN . '/' . $digest . '/' . bin2hex($url), $object[$key]);
				}
			}
		}
	}
}

/**
 * A hook to rewrite image URLs to our asset server before compiling the template
 * @param	phpbb_hook	$hook	the phpBB hook object
 * @param	string		$handle	the file name handle (not used)
 * @param	phpbb_template	$template	the template object containing the template variables
 * @return	void
 */
function assets_template_hook(&$hook, $handle, $template)
{
	global $request;
	if (!$request->is_secure())
	{
		return;
	}

	$rootref = &$template->context->get_root_ref();
	$tpldata = &$template->context->get_data_ref();

	//Viewtopic
	if (isset($tpldata['postrow']))
	{
		foreach ($tpldata['postrow'] as $index => &$postrow)
		{
			rewrite_images($postrow, 'MESSAGE');
			rewrite_images($postrow, 'SIGNATURE');
			rewrite_images($postrow, 'POSTER_AVATAR');
		}
	}
	
	rewrite_images($rootref, 'AVATAR');				//UCP - Profile - Avatar
	rewrite_images($rootref, 'SIGNATURE_PREVIEW');	//UCP - Profile - Signature
	rewrite_images($rootref, 'PREVIEW_MESSAGE');	//UCP - PM - Compose - Message
	rewrite_images($rootref, 'PREVIEW_SIGNATURE');	//UCP - PM - Compose - Signature
	rewrite_images($rootref, 'AUTHOR_AVATAR');		//UCP - PM - View - Author avatar
	rewrite_images($rootref, 'MESSAGE');			//UCP - PM - View - Author message
	rewrite_images($rootref, 'SIGNATURE');			//UCP - PM - View - Author signature, Memberlist - Profile - Signature
	rewrite_images($rootref, 'POST_PREVIEW');		//MCP - Reported post
	rewrite_images($rootref, 'AVATAR_IMG');			//Memberlist - Profile - Avatar

	//UCP - PM - Message history, MCP - Reported Post - Topic Review
	if (isset($tpldata['topic_review_row']))
	{
		foreach ($tpldata['topic_review_row'] as $index => &$topic_review_row)
		{
			rewrite_images($topic_review_row, 'MESSAGE');
		}
	}

	//UCP - PM - Message history (Sent messages)
	if (isset($tpldata['history_row']))
	{
		foreach ($tpldata['history_row'] as $index => &$history_row)
		{
			rewrite_images($history_row, 'MESSAGE');
		}
	}

	//Search results
	if (isset($tpldata['searchresults']))
	{
		foreach ($tpldata['searchresults'] as $index => &$search_results)
		{
			rewrite_images($search_results, 'MESSAGE');
		}
	}
}

// Register
$phpbb_hook->register(array('phpbb_template', 'display'), 'assets_template_hook');

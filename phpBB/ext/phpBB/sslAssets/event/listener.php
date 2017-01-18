<?php
/**
*
* @package sslAssets
* @copyright (c) 2007 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpBB\sslAssets\event;

//define('CAMO_KEY', '');
define('ASSETS_DOMAIN', 'camo.phpbb.com');

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.page_footer'	=> 'rewrite_assets',
		);
	}

	/**
	 * Rewrites an image tag into a version that can be used by a Camo asset server
	 *
	 * @param	array	$object	The array containing the data to rewrite
	 * @param	string	$key	The key into the array. The element to rewrite.
	 * @return	void
	 */
	private function rewrite_images(&$object, $key)
	{
		if (!empty($object[$key]))
		{
			if (preg_match_all('#<img [^>]*src="(http://[^"]+)"[^>]*>#', $object[$key], $matches))
			{
				foreach ($matches[1] as $url)
				{
					// Don't rewrite requests for .com to camo, just change the protocol
					if (stripos($url, 'http://www.phpbb.com/') !== false)
					{
						$object[$key] = preg_replace('#http:#', 'https:', $object[$key]);
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

	public function rewrite_assets($event)
	{
		global $request;
		global $phpbb_container;

		$context = $phpbb_container->get('template_context');
		$rootref = &$context->get_root_ref();
		$tpldata = &$context->get_data_ref();

		// Viewtopic
		if (isset($tpldata['postrow']))
		{
			foreach ($tpldata['postrow'] as $index => &$postrow)
			{
				$this->rewrite_images($postrow, 'MESSAGE');
				$this->rewrite_images($postrow, 'SIGNATURE');
				$this->rewrite_images($postrow, 'POSTER_AVATAR');
			}
		}

		$this->rewrite_images($rootref, 'AVATAR');				//UCP - Profile - Avatar
		$this->rewrite_images($rootref, 'SIGNATURE_PREVIEW');	//UCP - Profile - Signature
		$this->rewrite_images($rootref, 'PREVIEW_MESSAGE');	//UCP - PM - Compose - Message
		$this->rewrite_images($rootref, 'PREVIEW_SIGNATURE');	//UCP - PM - Compose - Signature
		$this->rewrite_images($rootref, 'AUTHOR_AVATAR');		//UCP - PM - View - Author avatar
		$this->rewrite_images($rootref, 'MESSAGE');			//UCP - PM - View - Author message
		$this->rewrite_images($rootref, 'SIGNATURE');			//UCP - PM - View - Author signature, Memberlist - Profile - Signature
		$this->rewrite_images($rootref, 'POST_PREVIEW');		//MCP - Reported post
		$this->rewrite_images($rootref, 'AVATAR_IMG');			//Memberlist - Profile - Avatar
		$this->rewrite_images($rootref, 'CURRENT_USER_AVATAR');	//Header - Avatar

		//UCP - PM - Message history, MCP - Reported Post - Topic Review
		if (isset($tpldata['topic_review_row']))
		{
			foreach ($tpldata['topic_review_row'] as $index => &$topic_review_row)
			{
				$this->rewrite_images($topic_review_row, 'MESSAGE');
			}
		}

		//UCP - PM - Message history (Sent messages)
		if (isset($tpldata['history_row']))
		{
			foreach ($tpldata['history_row'] as $index => &$history_row)
			{
				$this->rewrite_images($history_row, 'MESSAGE');
			}
		}

		//Search results
		if (isset($tpldata['searchresults']))
		{
			foreach ($tpldata['searchresults'] as $index => &$search_results)
			{
				$this->rewrite_images($search_results, 'MESSAGE');
			}
		}

		//Notifications - Nav
		if (isset($tpldata['notifications']))
		{
			foreach ($tpldata['notifications'] as $index => &$notification)
			{
				$this->rewrite_images($notification, 'AVATAR');
			}
		}

		//Notifications - UCP
		if (isset($tpldata['notification_list']))
		{
			foreach ($tpldata['notification_list'] as $index => &$notification)
			{
				$this->rewrite_images($notification, 'AVATAR');
			}
		}
	}
}
<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace phpbb\notification\method;

use phpbb\notification\type\type_interface;
use phpbb\di\service_collection;
use phpbb\user_loader;

/**
* Abstract notification method handling email and jabber notifications
* using the phpBB messenger.
*/
abstract class messenger_base extends \phpbb\notification\method\base
{
	/** @var service_collection */
	protected $messenger;

	/** @var user_loader */
	protected $user_loader;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Notification Method Board Constructor
	 *
	 * @param service_collection $messenger
	 * @param user_loader $user_loader
	 * @param string $phpbb_root_path
	 * @param string $php_ext
	 */
	public function __construct(service_collection $messenger, user_loader $user_loader, $phpbb_root_path, $php_ext)
	{
		$this->messenger = $messenger;
		$this->user_loader = $user_loader;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	* Is this method available for the user?
	* This is checked on the notifications options
	*
	* @param type_interface|null $notification_type	An optional instance of a notification type. This method returns false
	*											only if the type is provided and if it doesn't provide an email template.
	* @return bool
	*/
	public function is_available(type_interface $notification_type = null)
	{
		return $notification_type === null || $notification_type->get_email_template() !== false;
	}

	/**
	* Notify using phpBB messenger
	*
	* @param int $notify_method				Notify method for messenger (e.g. \phpbb\messenger\method\messenger_interface::NOTIFY_IM)
	* @param string $template_dir_prefix	Base directory to prepend to the email template name
	*
	* @return void
	*/
	protected function notify_using_messenger($notify_method, $template_dir_prefix = '')
	{
		if (empty($this->queue))
		{
			return;
		}

		// Load all users we want to notify (we need their email address)
		$user_ids = [];
		foreach ($this->queue as $notification)
		{
			$user_ids[] = $notification->user_id;
		}

		// We do not notify banned users
		if (!function_exists('phpbb_get_banned_user_ids'))
		{
			include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
		}
		$banned_users = phpbb_get_banned_user_ids($user_ids);

		// Load all the users we need
		$this->user_loader->load_users(array_diff($user_ids, $banned_users), array(USER_IGNORE));

		// Time to go through the queue and send notifications
		$messenger_collection_iterator = $this->messenger->getIterator();

		/** @var type_interface $notification */
		foreach ($this->queue as $notification)
		{
			if ($notification->get_email_template() === false)
			{
				continue;
			}

			$user = $this->user_loader->get_user($notification->user_id);

			if ($user['user_type'] == USER_INACTIVE && $user['user_inactive_reason'] == INACTIVE_MANUAL)
			{
				continue;
			}

			foreach ($messenger_collection_iterator as $messenger_method)
			{
				if ($messenger_method->get_id() == $notify_method || $notify_method == $messenger_method::NOTIFY_BOTH)
				{
					$messenger_method->template($notification->get_email_template(), $user['user_lang'], '', $template_dir_prefix);
					$messenger_method->set_addresses($user);
					$messenger_method->assign_vars(array_merge([
						'USERNAME'					=> $user['username'],
						'U_NOTIFICATION_SETTINGS'	=> generate_board_url() . '/ucp.' . $this->php_ext . '?i=ucp_notifications&mode=notification_options',
					], $notification->get_email_template_variables()));

					$messenger_method->send();
				}
			}
		}

		// Save the queue in the messenger method class (has to be called or these messages could be lost)
		foreach ($messenger_collection_iterator as $messenger_method)
		{
			$messenger_method->save_queue();
		}

		// We're done, empty the queue
		$this->empty_queue();
	}
}

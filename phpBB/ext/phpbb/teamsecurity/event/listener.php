<?php
/**
 *
 * Team Security Measures extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\teamsecurity\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener
 */
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config $config Config object
	 * @param \phpbb\log\log $log The phpBB log system
	 * @param \phpbb\user $user User object
	 * @param string $phpbb_root_path phpBB root path
	 * @param string $phpEx phpEx
	 * @access public
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\log\log $log, \phpbb\user $user, $phpbb_root_path, $phpEx)
	{
		$this->config = $config;
		$this->log = $log;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'						=> 'load_language_on_setup',

			// Stronger passwords
			'core.acp_users_overview_before'		=> 'set_team_password_configs',
			'core.ucp_display_module_before'		=> 'set_team_password_configs',

			// Logs protection
			'core.delete_log'						=> 'delete_logs_security',

			// Login detection
			'core.login_box_failed'					=> 'log_failed_login_attempts',
			'core.login_box_redirect'				=> 'acp_login_notification',

			// Email changes
			'core.acp_users_overview_modify_data'	=> 'email_change_notification',
			'core.ucp_profile_reg_details_sql_ary'	=> 'email_change_notification',
		);
	}

	/**
	 * Load common language files during user setup
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 * @access public
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'phpbb/teamsecurity',
			'lang_set' => 'info_acp_teamsecurity',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Set stronger password requirements for members of specific groups
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 * @access public
	 */
	public function set_team_password_configs($event)
	{
		if (!$this->config['sec_strong_pass'])
		{
			return;
		}

		// reg_details = UCP Account Settings // overview = ACP User Overview
		if ($event['mode'] === 'reg_details' || $event['mode'] === 'overview')
		{
			// The user the new password settings apply to
			$user_id = isset($event['user_row']['user_id']) ? $event['user_row']['user_id'] : $this->user->data['user_id'];

			if ($this->in_watch_group($user_id))
			{
				$this->config['pass_complex'] = 'PASS_TYPE_SYMBOL';
				$this->config['min_pass_chars'] = ($this->config['min_pass_chars'] > $this->config['sec_min_pass_chars']) ? $this->config['min_pass_chars'] : $this->config['sec_min_pass_chars'];
			}
		}
	}

	/**
	 * Prevent deletion of Admin/Moderator/User logs and notify board security contact
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 * @access public
	 */
	public function delete_logs_security($event)
	{
		if (in_array($event['mode'], array('admin', 'mod', 'user', 'users')))
		{
			// Set log_type to false to prevent deletion of logs
			$event['log_type'] = false;

			// Get information on the user and their action
			$user_data = array(
				'USERNAME'		=> $this->user->data['username'],
				'IP_ADDRESS'	=> $this->user->ip,
				'TIME'			=> $this->user->format_date(time(), $this->config['default_dateformat'], true),
				'LOG_MODE'		=> $event['mode'],
			);

			// Send an email to the board security contact identifying the logs
			if (isset($event['conditions']['keywords']))
			{
				// Delete All was selected
				$this->send_message(array_merge($user_data, array(
					'LOGS_SELECTED' => $this->user->lang('LOG_DELETE_ALL')
				)), 'acp_logs');
			}
			else if (isset($event['conditions']['log_id']['IN']))
			{
				// Marked logs were selected
				$this->send_message(array_merge($user_data, array(
					'LOGS_SELECTED' => $this->user->lang('LOG_DELETE_MARKED', implode(', ', $event['conditions']['log_id']['IN']))
				)), 'acp_logs');
			}
		}
	}

	/**
	 * Log failed login attempts for members of specific groups
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 * @access public
	 */
	public function log_failed_login_attempts($event)
	{
		if (!$this->config['sec_login_attempts'])
		{
			return;
		}

		if ($this->in_watch_group($event['result']['user_row']['user_id']))
		{
			$this->log->add('user', $event['result']['user_row']['user_id'], $this->user->ip, 'LOG_TEAM_AUTH_FAIL', time(), array('reportee_id' => $event['result']['user_row']['user_id']));
		}
	}

	/**
	 * Send an email notification when a user logs into the ACP
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 * @access public
	 */
	public function acp_login_notification($event)
	{
		if (!$this->config['sec_login_email'])
		{
			return;
		}

		if ($event['admin'])
		{
			$this->send_message(array(
				'USERNAME'		=> $this->user->data['username'],
				'IP_ADDRESS'	=> $this->user->ip,
				'LOGIN_TIME'	=> $this->user->format_date(time(), $this->config['default_dateformat'], true),
			), 'acp_login', $this->user->data['user_email']);
		}
	}

	/**
	 * Send an email notification when an email address
	 * is changed for members of specific groups
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 * @access public
	 */
	public function email_change_notification($event)
	{
		if (!$this->config['sec_email_changes'])
		{
			return;
		}

		$user_id = isset($event['user_row']['user_id']) ? $event['user_row']['user_id'] : $this->user->data['user_id'];
		$old_email = isset($event['user_row']['user_email']) ? $event['user_row']['user_email'] : $this->user->data['user_email'];
		$new_email = $event['data']['email'];

		if ($old_email != $new_email && $this->in_watch_group($user_id))
		{
			$this->send_message(array(
				'USERNAME'		=> $this->user->data['username'],
				'NEW_EMAIL'		=> $new_email,
				'OLD_EMAIL'		=> $old_email,
				'IP_ADDRESS'	=> $this->user->ip,
				'CONTACT'		=> (!empty($this->config['sec_contact_name'])) ? $this->config['sec_contact_name'] : $this->user->lang('ACP_CONTACT_ADMIN'),
			), 'email_change', $old_email);
		}
	}

	/**
	 * Is user in a specified watch group
	 *
	 * @param int $user_id User identifier
	 * @return bool True if in group, false otherwise
	 * @access protected
	 */
	protected function in_watch_group($user_id)
	{
		$group_id_ary = (!$this->config['sec_usergroups']) ? array() : json_decode(trim($this->config['sec_usergroups']), true);

		if (empty($group_id_ary))
		{
			return false;
		}

		if (!function_exists('group_memberships'))
		{
			include $this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext;
		}

		return group_memberships($group_id_ary, $user_id, true);
	}

	/**
	 * Send email messages to defined board security contact
	 *
	 * @param array $message_data Array of message data
	 * @param string $template The template file to use
	 * @param string $cc_user CC email address
	 * @return void
	 * @access protected
	 */
	protected function send_message($message_data, $template, $cc_user = '')
	{
		if (!class_exists('messenger'))
		{
			include $this->phpbb_root_path . 'includes/functions_messenger.' . $this->php_ext;
		}

		$messenger = new \messenger(false);
		$messenger->template('@phpbb_teamsecurity/' . $template);
		$messenger->to((!empty($this->config['sec_contact'])) ? $this->config['sec_contact'] : $this->config['board_contact'], $this->config['board_contact_name']);
		$messenger->cc($cc_user);
		$messenger->assign_vars($message_data);
		$messenger->send();
	}
}

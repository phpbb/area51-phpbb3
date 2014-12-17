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
namespace phpbb\console\command\passwords;

use phpbb\console\command\command;
use phpbb\db\driver\driver_interface;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class reset extends command
{
	/**
	 * @var driver_interface
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param \phpbb\user $user The user object (used to get language information)
	 * @param driver_interface $db Database connection
	 */
	public function __construct(\phpbb\user $user, driver_interface $db)
	{
		$this->db = $db;

		parent::__construct($user);
	}

	/**
	 * Sets the command name and description
	 *
	 * @return null
	 */
	protected function configure()
	{
		$this
			->setName('passwords:reset')
			->setDescription($this->user->lang('CLI_DESCRIPTION_PASSWORDS_RESET'))
			->addOption('dry-run')
			->addOption('print-usernames')
			->addOption('groups-ids', null, InputOption::VALUE_REQUIRED, '', '')
			->addOption('users-ids', null, InputOption::VALUE_REQUIRED, '', '')
			->addOption('excluded-users-ids', null, InputOption::VALUE_REQUIRED, '', '1')
			->addOption('usernames', null, InputOption::VALUE_REQUIRED, '', '')
			->addOption('excluded-usernames', null, InputOption::VALUE_REQUIRED, '', '')
		;
	}

	/**
	 * Executes the command attachments:check.
	 *
	 * Rename all the attachments, compare their size with the one in the database and check the orphans.
	 * Note: all the thumbnails will be removed.
	 *
	 * @param InputInterface $input The input stream used to get the argument and verbose option.
	 * @param OutputInterface $output The output stream, used for printing verbose-mode and error information.
	 *
	 * @return int 0 if all is ok, 1 otherwise
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$dry_run = $input->getOption('dry-run');

		$groups_ids = $input->getOption('groups-ids');
		$users_ids = $input->getOption('users-ids');
		$usernames = $input->getOption('usernames');
		$excluded_users_ids = $input->getOption('excluded-users-ids');
		$excluded_usernames = $input->getOption('excluded-usernames');

		$groups_ids = $groups_ids === '' ? array() : explode(',', $groups_ids);
		$users_ids = $users_ids === '' ? array() : explode(',', $users_ids);
		$usernames = $usernames === '' ? array() : explode(',', $usernames);
		$excluded_users_ids = $excluded_users_ids === '' ? array() : explode(',', $excluded_users_ids);
		$excluded_usernames = $excluded_usernames === '' ? array() : explode(',', $excluded_usernames);

		$sql_ary = array(
			'SELECT'    => 'u.user_id',
			'FROM'      => array(USERS_TABLE => 'u'),
			'LEFT_JOIN' => array(
				array(
					'FROM' => array(USER_GROUP_TABLE => 'ug'),
					'ON'   => 'u.user_id = ug.user_id',
				),
			),
			'WHERE'     => '(' .
								$this->db->sql_in_set('u.username', $usernames, false, true) .
								' OR ' . $this->db->sql_in_set('ug.group_id', $groups_ids, false, true) .
								' OR ' . $this->db->sql_in_set('u.user_id', $users_ids, false, true) .
							')' .
							' AND ' . $this->db->sql_in_set('u.username', $excluded_usernames, true, true) .
							' AND ' . $this->db->sql_in_set('u.user_id', $excluded_users_ids, true, true),
		);
		$sql = $this->db->sql_build_query('SELECT', $sql_ary);

		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$users_ids[] = $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		$users_ids = array_unique($users_ids);

		if ($input->getOption('print-usernames'))
		{
			$users = array();

			$sql = 'SELECT username FROM ' . USERS_TABLE . ' WHERE ' . $this->db->sql_in_set('user_id', $users_ids, false, trueÂ²);
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$users[] = $row['username'];
			}

			$this->db->sql_freeresult($result);
			$users = implode(',', $users);
		}
		else
		{
			$users = implode(',', $users_ids);
		}

		$output->writeln('<info>Resetting the password of the users [' . $users . ']</info>');

		if (!$dry_run)
		{
			$sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', array('user_password'  => '', 'user_newpasswd' => '')) . ' WHERE ' . $this->db->sql_in_set('user_id', $users_ids, false, true);
			$this->db->sql_query($sql);
		}

		return 0;
	}
}

<?php
/**
 *
 * phpBB Team Security Measures
 *
 * @copyright (c) 2014 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\teamsecurity\migrations;

class m2_email_config extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\phpbb\teamsecurity\migrations\m1_initial');
	}

	public function effectively_installed()
	{
		return $this->config->offsetExists('sec_contact');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('sec_contact', '')),
		);
	}
}

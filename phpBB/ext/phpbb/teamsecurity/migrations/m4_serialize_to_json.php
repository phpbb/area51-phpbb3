<?php
/**
 *
 * phpBB Team Security Measures
 *
 * @copyright (c) 2015 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\teamsecurity\migrations;

class m4_serialize_to_json extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array('\phpbb\teamsecurity\migrations\m1_initial');
	}

	public function update_data()
	{
		return array(
			array('config.update', array('sec_usergroups', $this->serialize_to_json('sec_usergroups'))),
		);
	}

	/**
	 * Convert stored serialized config data to json_encoded data
	 *
	 * @param string $cfg Name of the config var
	 * @return string json_encoded config data
	 * @access public
	 */
	public function serialize_to_json($cfg)
	{
		// This check is needed to prevent errors when uninstalling this migration/extension
		json_decode($this->config[$cfg], false);
		if (json_last_error() === JSON_ERROR_NONE)
		{
			return '';
		}

		$data = unserialize(trim($this->config[$cfg]), ['allowed_classes' => false]);

		return $data ? json_encode($data) : '';
	}
}

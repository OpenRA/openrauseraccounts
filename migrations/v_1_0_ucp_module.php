<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\migrations;

class v_1_0_ucp_module extends \phpbb\db\migration\migration
{
	/**
	 * Check if the migration is effectively installed
	 *
	 * @return bool	True if this migration is installed, False if this migration is not installed
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_effectively_installed
	 */
	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . $this->table_prefix . 'modules' . '
			WHERE module_class = "ucp"
			AND module_langname = "UCP_TITLE"';
		$result = $this->db->sql_query($sql);
		$module_id = $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return $module_id !== false;
	}

	/**
	 * Defines other migrations to be applied first
	 *
	 * @return array An array of migration class names
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_depends_on
	 */
	static public function depends_on()
	{
		return array('\openra\openrauseraccounts\migrations\v_1_0_db_schema');
	}

	/**
	 * Updates data by returning a list of instructions to be executed
	 *
	 * @return array Array of data update instructions
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_update_data
	 */
	public function update_data()
	{
		return array(
			array('module.add', array(
				'ucp', 0, 'UCP_TITLE'
			)),
			array('module.add', array(
				'ucp',
				'UCP_TITLE',
				array(
					'module_basename' => '\openra\openrauseraccounts\ucp\useraccounts_module',
					'modes'	=> array(
						'add_key',
						'manage_keys',
						'select_badges',
        					'order_badges'
					)
				)
			))
		);
	}
}

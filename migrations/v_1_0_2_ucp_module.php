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

class v_1_0_2_ucp_module extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\openra\openrauseraccounts\migrations\v_1_0_ucp_module');
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'update_module'))),
		);
	}

	public function update_module()
	{
		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "UCP_ACCOUNTS"
			WHERE module_langname = "UCP_TITLE"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "UCP_ACCOUNTS_ADD_KEY"
			WHERE module_langname = "UCP_ADD_KEY"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "UCP_ACCOUNTS_MANAGE_KEYS"
			WHERE module_langname = "UCP_MANAGE_KEYS"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "UCP_ACCOUNTS_SELECT_BADGES"
			WHERE module_langname = "UCP_SELECT_BADGES"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "UCP_ACCOUNTS_ORDER_BADGES"
			WHERE module_langname = "UCP_ORDER_BADGES"'
		);
	}

	public function revert_data()
	{
		return array(
			array('module.remove', array(
				'ucp',
				'UCP_ACCOUNTS',
				'UCP_ACCOUNTS_ADD_KEY'
			)),
			array('module.remove', array(
				'ucp',
				'UCP_ACCOUNTS',
				'UCP_ACCOUNTS_MANAGE_KEYS'
			)),
			array('module.remove', array(
				'ucp',
				'UCP_ACCOUNTS',
				'UCP_ACCOUNTS_SELECT_BADGES'
			)),
			array('module.remove', array(
				'ucp',
				'UCP_ACCOUNTS',
				'UCP_ACCOUNTS_ORDER_BADGES'
			)),
			array('module.remove', array(
				'ucp',
				0,
				'UCP_ACCOUNTS'
			))
		);
	}
}

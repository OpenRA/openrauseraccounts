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

class v_1_0_2_acp_module extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return array('\openra\openrauseraccounts\migrations\v_1_0_acp_module');
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
			SET module_langname = "ACP_BADGES"
			WHERE module_langname = "ACP_TITLE"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "ACP_BADGES_SETTINGS"
			WHERE module_langname = "ACP_SETTINGS"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "ACP_BADGES_TYPES"
			WHERE module_langname = "ACP_TYPES"'
		);

		$this->db->sql_query(
			'UPDATE ' . MODULES_TABLE . '
			SET module_langname = "ACP_BADGES_BADGES"
			WHERE module_langname = "ACP_BADGES"
			AND module_mode = "badges"'
		);
	}

	public function revert_data()
	{
		return array(
			array('module.remove', array(
				'acp',
				'ACP_BADGES',
				'ACP_BADGES_SETTINGS'
			)),
			array('module.remove', array(
				'acp',
				'ACP_BADGES',
				'ACP_BADGES_TYPES'
			)),
			array('module.remove', array(
				'acp',
				'ACP_BADGES',
				'ACP_BADGES_BADGES'
			)),
			array('module.remove', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_BADGES'
			))
		);
	}
}

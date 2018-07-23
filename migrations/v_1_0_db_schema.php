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

class v_1_0_db_schema extends \phpbb\db\migration\migration
{
	/**
	 * Check if the migration is effectively installed
	 *
	 * @return bool	True if this migration is installed, False if this migration is not installed
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_effectively_installed
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'openra_keys', $this->table_prefix . 'openra_badge_types', $this->table_prefix . 'openra_badges', $this->table_prefix . 'openra_user_badges', $this->table_prefix . 'openra_badge_availability');
	}

	/**
	 * Defines other migrations to be applied first
	 *
	 * @return array An array of migration class names
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_depends_on
	 */
	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v31x\v314');
	}

	/**
	 * Updates the database schema by providing a set of change instructions
	 *
	 * @return array Array of schema changes
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_update_schema
	 */
	public function update_schema()
	{
		return array(
			'add_tables' => array(
				$this->table_prefix . 'openra_keys' => array(
					'COLUMNS' => array(
						'item_id'	=> array('UINT', null, 'auto_increment'),
						'user_id'	=> array('UINT', 0),
						'public_key'	=> array('MTEXT_UNI', ''),
						'fingerprint'	=> array('VCHAR:255', ''),
						'registered'	=> array('TIMESTAMP', 0),
						'last_accessed'	=> array('TIMESTAMP', 0),
						'revoked'	=> array('BOOL', 0, FALSE)
					),
					'PRIMARY_KEY' => 'item_id'
				),
				$this->table_prefix . 'openra_badge_types' => array(
					'COLUMNS' => array(
						'badge_type_id'		=> array('UINT', null, 'auto_increment'),
						'badge_type_name'	=> array('VCHAR:255', ''),
					),
					'PRIMARY_KEY' => 'badge_type_id'
				),
				$this->table_prefix . 'openra_badges' => array(
					'COLUMNS' => array(
						'badge_id'	=> array('UINT', null, 'auto_increment'),
						'badge_label'	=> array('VCHAR:255', ''),
						'badge_icon_24'	=> array('VCHAR:255', ''),
						'badge_type_id'	=> array('UINT', 0),
						'badge_default'	=> array('BOOL', 0, FALSE),
					),
					'PRIMARY_KEY' => 'badge_id'
				),
				$this->table_prefix . 'openra_user_badges' => array(
					'COLUMNS' => array(
						'item_id'	=> array('UINT', null, 'auto_increment'),
						'user_id'	=> array('UINT', 0),
						'badge_id'	=> array('UINT', 0),
						'badge_default'	=> array('BOOL', 0, FALSE),
						'badge_order'	=> array('UINT'),
					),
					'PRIMARY_KEY' => 'item_id'
				),
				$this->table_prefix . 'openra_badge_availability' => array(
					'COLUMNS' => array(
						'item_id'	=> array('UINT', null, 'auto_increment'),
						'badge_id'	=> array('UINT', 0),
						'user_id'	=> array('VCHAR:255', ''),
					),
					'PRIMARY_KEY' => 'item_id'
				),
			)
		);
	}

	/**
	 * Reverts the database schema by providing a set of change instructions
	 *
	 * @return array Array of schema changes
	 * @link https://area51.phpbb.com/docs/code/3.2.x/phpbb/db/migration/migration.html#method_revert_schema
	 */
	public function revert_schema()
	{
		return array(
			'drop_tables' => array(
				$this->table_prefix . 'openra_keys',
				$this->table_prefix . 'openra_badge_types',
				$this->table_prefix . 'openra_badges',
				$this->table_prefix . 'openra_user_badges',
				$this->table_prefix . 'openra_badge_availability'
			),
		);
	}
}

<?php declare(strict_types=1);
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General abstract License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\services\helper;

/**
 * Helper service class.
 */
class helper
{
	protected $db;
	protected $path_helper;
	protected $ext_manager;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\path_helper
	 *
	 * @return \openra\openrauseraccounts\services\helper\helper
	 */
	function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\path_helper $path_helper, \phpbb\extension\manager $ext_manager)
	{
		$this->db = $db;
		$this->path_helper = $path_helper;
		$this->ext_manager = $ext_manager;
	}

	/**
	 * Change the order of items.
	 *
	 * @param string $table Item table.
	 * @param string $fieldname Field containing the order.
	 * @param string $direction Valid values are 'move_up' or 'move_down'.
	 * @param int $item_id The item to move up or down, field must be 'item_id'.
	 * @param int $user_id Optionally order items of a user.
	 *
	 * @return bool Returns true on success.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function move_item(string $table, string $fieldname, string $direction, int $item_id, int $user_id = null): bool
	{
		if (!($direction == 'move_up' || 'move_down'))
		{
			return false;
		}

		$this->db->sql_query(
			'SELECT ' . $fieldname . ' AS current_order
			FROM ' . $table . '
			WHERE item_id = ' . $item_id
		);
		$current_order = (int)$this->db->sql_fetchfield('current_order');
		$this->db->sql_freeresult();

		if ($current_order == 1 && $direction == 'move_up')
		{
			return false;
		}

		$isql = '';

		if (isset($user_id))
		{
			$isql = ' AND user_id = ' . $user_id;
		}

		$switch_order = $direction == 'move_down' ? $current_order + 1 : $current_order - 1;
		$this->db->sql_query(
			'UPDATE ' . $table . '
			SET ' . $fieldname . ' = ' . $current_order . '
			WHERE ' . $fieldname . ' = ' . $switch_order . '
			AND item_id <> ' . $item_id . $isql
		); // Try updating the next/previous item.

		if (!$this->db->sql_affectedrows())
		{
			throw new \InvalidArgumentException('CAN_NOT_MOVE_ITEM');
		}

		$this->db->sql_query(
			'UPDATE ' . $table . '
			SET ' . $fieldname . ' = ' . $switch_order . '
			WHERE ' . $fieldname . ' = ' . $current_order . '
			AND item_id = ' . $item_id
		); // Update selected item.

		if (!$this->db->sql_affectedrows())
		{
			$this->fix_order($user_id);

			throw new \InvalidArgumentException('CAN_NOT_MOVE_ITEM');
		}

		return true;
	}

	/**
	 * Check the order of items and fix it if necessary.
	 *
	 * @param string $table Item table.
	 * @param string $fieldname Field containing the order.
	 * @param int $user_id Optionally check the order for a user.
	 *
	 * @return null
	 */
	public function fix_item_order(string $table, string $fieldname, int $user_id = null)
	{
		$isql = '';

		if (isset($user_id))
		{
			$isql = ' WHERE user_id = ' . $user_id;
		}

		$result = $this->db->sql_query(
			'SELECT item_id, ' . $fieldname . '
			FROM ' . $table . $isql . '
			ORDER BY ISNULL(' . $fieldname . '), ' . $fieldname
		); // NULL at the end.

		$order = 0;
		$this->db->sql_transaction('begin');

		if (isset($user_id))
		{
			$isql = ' AND user_id = ' . $user_id;
		}

		if ($row = $this->db->sql_fetchrow($result))
		{
			$order = 0;
			do
			{
				++$order;
				if ($row[$fieldname] != $order)
				{
					$this->db->sql_query(
						'UPDATE ' . $table . '
						SET '. $fieldname . ' = ' . $order . '
						WHERE item_id = ' . $row['item_id'] . $isql
					);
				}
			}
			while ($row = $this->db->sql_fetchrow($result));
		}

		$this->db->sql_freeresult();
		$this->db->sql_transaction('commit');
	}

	/**
	 * Returns the relative path for custom images used in the extension.
	 *
	 * @return string
	 */
	public function get_ext_img_path()
	{
		return $this->path_helper->get_phpbb_root_path() . $this->ext_manager->get_extension_path('openra/openrauseraccounts') . 'images/';
	}
}

<?php declare(strict_types=1);
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\services\badge;

/**
 * Class for handling badge types.
 */
class badge_type extends base
{
	/**
	 * Count badge types.
	 *
	 * Count all: Default, $options = []
	 * Count by badge type name: $options = ['badge_type_name' => $badge_type_name]
	 *
	 * @return int
	 *
	 * @throws \InvalidArgumentException
	 */
	public function get_count(array $options = []): int
	{
		$isql = '';

		switch ($options)
		{
			case !isset($options):
				break;

			case isset($options['badge_type_name']):
				$isql = ' WHERE badge_type_name = "' . $this->db->sql_escape($options['badge_type_name']) . '"';
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_COUNT_BT');
		}

		$sql = 'SELECT COUNT(*) AS count FROM ' . $this->bt_tbl . $isql;
		$this->db->sql_query('SELECT COUNT(*) AS count FROM ' . $this->bt_tbl . $isql);
		$count = (int)$this->db->sql_fetchfield('count');

		return $count;
	}

	/**
	 * Insert or update badge type data.
	 *
	 * @param array $data
	 * @param int $type_id Optional parameter, default null inserts data.
	 *
	 * @return string $mssg Language key
	 */
	public function make(array $data, int $type_id = 0): string
	{
		$mode = $type_id ? 'UPDATE ' : 'INSERT ';
		$isql = $mode . ($type_id ? '' : 'INTO ') .  $this->bt_tbl . ($type_id ? ' SET' : '');

		if (!$type_id)
		{
			$sql = $isql . ' ' . $this->db->sql_build_array('INSERT', $data);
			$mssg = 'BADGE_TYPE_ADDED';
		}
		else
		{
			$sql = $isql . ' ' . $this->db->sql_build_array('UPDATE', $data) . ' WHERE badge_type_id = ' . $type_id;
			$mssg = 'BADGE_TYPE_UPDATED';
		}

		$this->db->sql_query($sql);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $mssg, false, [$data['badge_type_name']]);

		return $mssg;
	}

	/**
	 * Get data for specified or all badge types.
	 *
	 * @param array $options
	 * Get all: Default, $options = []
	 * Get by type id: $options = ['badge_type_id' => $badge_type_id]
	 * @param int $limit Optional parameter to limit rows.
	 * @param int $start Optional parameter to set offset for rows when limit is set.
	 *
	 * @return $result Query result object success, false on failure.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function get_data(array $options = [], int $limit = 0, int $start = 0)
	{
		$isql = '';

		switch ($options)
		{
			case !isset($options):
				break;

			case is_int($options['badge_type_id']):
				$isql = ' WHERE badge_type_id = ' . $options['badge_type_id'];
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_GET_BT');
		}

		$sql = 'SELECT * FROM ' . $this->bt_tbl . $isql . ' ORDER BY badge_type_name';

		if ($limit)
		{
		   $result = $this->db->sql_query_limit($sql, $limit, $start);
		}
		else
		{
			$result = $this->db->sql_query($sql);
		}

		return $result;
	}

	/**
	 * Delete badge type.
	 *
	 * @param int $type_id
	 *
	* @return string $mssg Language key
	 */
	public function delete(int $type_id): string
	{
		$typename = $this->db->sql_fetchfield('badge_type_name', false, $this->get_data(['badge_type_id' => $type_id]));
		$this->db->sql_query('DELETE FROM ' . $this->bt_tbl . ' WHERE badge_type_id = ' . $type_id);
		$mssg = 'BADGE_TYPE_DELETED';
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $mssg, false, array($typename));

		return $mssg;
	}

	function add_user_data(array $user, array $item)
	{
		return;
	}

	function remove_user_data(array $user, array $item, bool $negate = false)
	{
		return;
	}
}

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
 * Class for handling badges.
 */
class badge extends base
{
	/**
	 * Count badges by options.
	 *
	 * @param array $options
	 * Count all: Default, $options = []
	 * Count by type: $options = ['badge_type_id' => $badge_type_id]
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

			case is_int($options['badge_type_id']):
				$isql = ' WHERE badge_type_id = ' . $options['badge_type_id'];
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_COUNT_BADGES');
		}

		$this->db->sql_query('SELECT COUNT(*) AS count FROM ' . $this->b_tbl . $isql);
		$count = (int)$this->db->sql_fetchfield('count');

		return $count;
	}

	/**
	 * Get badge data by options.
	 *
	 * @param array $options
	 * Get all: Default, $options = []
	 * Get by badge id: $options = ['badge_id' => $badge_id]
	 * Get available for user: $options = ['user_id' => $user_ids]
	 * @param int $limit Optional parameter to limit rows.
	 * @param int $start Optional parameter to set offset for rows when limit is set.
	 *
	 * @return $result $result Query result object success, false on failure.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function get_data(array $options = [], int $limit = 0, int $start = 0)
	{
		$bqsql = [
			'SELECT' => 'b.*, bt.*',
			'FROM' => [$this->b_tbl => 'b', $this->bt_tbl => 'bt'],
			'WHERE' => 'b.badge_type_id = bt.badge_type_id',
			'ORDER_BY' => 'bt.badge_type_name, b.badge_label'
		];
 		switch ($options)
		{
			case !isset($options):
				break;

 			case is_int($options['badge_id']):
				$bqsql['WHERE'] .= ' AND b.badge_id = ' . $options['badge_id'];
				break;

 			case is_int($options['user_id']):
				$bqsql['SELECT'] .= ', bu.badge_id IS NOT NULL AS badge_selected';
				$bqsql['LEFT_JOIN'] = [
					['FROM' => [$this->bu_tbl => 'bu'], 'ON' => 'b.badge_id = bu.badge_id AND bu.user_id = ' . $options['user_id']],
					['FROM' => [$this->ba_tbl => 'ba'], 'ON' => 'b.badge_id = ba.badge_id AND ba.user_id = ' . $options['user_id']]
				];
				$bqsql['WHERE'] .= ' AND (b.badge_default = 1 OR ba.user_id = ' . $options['user_id'] . ')';
				// Badges are available if either:
				//  - a default badge (b.badge_default = true)
				//  - have been awarded to the user (ba.user_id = $options['user_id'])
				break;

 			default: throw new \InvalidArgumentException('INVALID_OPTIONS_GET_BADGES');
		}

		$sql = $this->db->sql_build_query('SELECT', $bqsql);

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
	 * Insert or update badge data.
	 *
	 * @param array $data
	 * @param int $badge_id Optional parameter, default 0 inserts data.
	 *
	 * @return string $mssg Language key
	 */
	public function make(array $data, int $badge_id = 0): string
	{
		$mode = $badge_id ? 'UPDATE ' : 'INSERT ';
		$isql = $mode . ($badge_id ? '' : 'INTO ') .  $this->b_tbl . ($badge_id ? ' SET' : '');

		if (!$badge_id)
		{
			$sql = $isql . ' ' . $this->db->sql_build_array('INSERT', $data);
			$mssg = 'BADGE_ADDED';
		}
		else
		{
			$sql = $isql . ' ' . $this->db->sql_build_array('UPDATE', $data) . ' WHERE badge_id = ' . $badge_id;
			$mssg = 'BADGE_UPDATED';
		}

		$this->db->sql_query($sql);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $mssg, false, [$data['badge_label']]);

		return $mssg;
	}

	/**
	 * Delete badge from all tables.
	 *
	 * @param int $badge_id
	 *
	 * @return string $mssg Language key
	 */
	public function delete(int $badge_id): string
	{
		$label = $this->db->sql_fetchfield('badge_label', false, $this->get_data(['badge_id' => $badge_id]));
		$badge_manager = $this->container->get('openra.openrauseraccounts.badge_manager');
		$helper = $this->container->get('openra.openrauseraccounts.helper');
		$user_ids = [];
		$result = $badge_manager->get_data('badge_user', ['badge_id' => $badge_id]);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = (int)$row['user_id'];
		}

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->ba_tbl . ' WHERE badge_id = ' . $badge_id);
		$this->db->sql_query('DELETE FROM ' . $this->bu_tbl . ' WHERE badge_id = ' . $badge_id);
		$this->db->sql_query('DELETE FROM ' . $this->b_tbl . ' WHERE badge_id = ' . $badge_id);
		$this->db->sql_transaction('commit');

		foreach ($user_ids as $id)
		{
			$helper->fix_item_order($this->bu_tbl, 'badge_order', $id);
		}

		$mssg = 'BADGE_DELETED';
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_' . $mssg, false, [$label]);

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

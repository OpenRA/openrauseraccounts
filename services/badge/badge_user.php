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
 * Class for handling user badges.
 */
class badge_user extends base
{
	/**
	 * Count user badges by options.
	 *
	 * @param array $options
	 * Count all: Default, $options = []
	 * Count by user id: $options = ['user_id' => $user_id]
	 * Count by badge id: $options = ['badge_id' => $badge_id]
	 * Count by badge id and badge order <= order limit: $options = ['badge_id' => $badge_id, 'badge_order' => $order_limit]
	 * Count by badge type id: $options = ['badge_type_id' => $badge_type_id]
	 *
	 * @return int $count
	 *
	 * @throws \InvalidArgumentException
	 */
	public function get_count(array $options = []): int
	{
		$tables = $this->bu_tbl . ' AS bu';
		$isql = '';

		switch ($options)
		{
			case !isset($options):
				break;

			case is_int($options['user_id']):
				$isql = ' WHERE bu.user_id = ' . $options['user_id'];
				break;

			case is_int($options['badge_id']) && !is_int($options['badge_order']):
				$isql = ' WHERE bu.badge_id = ' . $options['badge_id'];
				break;

			case is_int($options['badge_id']) && is_int($options['badge_order']):
				$isql = ' WHERE bu.badge_id = ' . $options['badge_id'] . ' AND bu.badge_order <= ' . $options['badge_order'];
				break;

			case is_int($options['badge_type_id']):
				$tables .= ', ' . $this->b_tbl . ' AS b';
				$isql = ' WHERE b.badge_type_id = ' . $options['badge_type_id'] . ' AND b.badge_id = bu.badge_id';
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_COUNT_BU');
		}

		$this->db->sql_query('SELECT COUNT(*) AS count FROM ' . $tables . $isql);
		$count = (int)$this->db->sql_fetchfield('count');

		return $count;
    }

    /**
	 * Get user badge data and username by options.
	 *
	 * @param array $options
	 * Get all: Default, $options = []
	 * Get by user id: $options = ['user_id' => $user_id]
	 * Get by fingerprint: $options = ['fingerprint' => $fingerprint]
	 * Get by item id: $options = ['item_id' => $item_id]
	 * Get by badge id: $options = ['badge_id' => $badge_id]
	 * @param int $limit Optional parameter to limit rows.
	 * @param int $start Optional parameter to set offset for rows when limit is set.
	 *
	 * @return $result Query result object.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function get_data(array $options = [], int $limit = 0, int $start = 0)
	{
		$tables = $this->b_tbl . ' AS b, ' . $this->bu_tbl . ' AS bu, ' . USERS_TABLE . ' AS u';
		$isql = '';
		$operation = ' ORDER BY bu.badge_order';

		switch ($options)
		{
			case !isset($options):
				$operation = ' ORDER BY b.badge_label';
				break;

			case is_int($options['user_id']):
				$isql = ' AND bu.user_id = ' . $options['user_id'];
				break;

			case is_string($options['fingerprint']):
				$tables .= ', ' . $this->k_tbl . ' AS k';
				$isql = ' AND k.fingerprint = "' . $this->db->sql_escape($options['fingerprint']) . '" AND k.user_id = bu.user_id';
				break;

			case is_int($options['item_id']):
				$isql = ' AND bu.item_id = ' . $options['item_id'];
				$operation = '';
				break;

			case is_int($options['badge_id']):
				$isql = ' AND bu.badge_id = ' . $options['badge_id'];
				$operation = ' ORDER BY b.badge_label';
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_GET_BU');
		}

		$sql = 'SELECT b.*, bu.*, u.username FROM ' . $tables . ' WHERE u.user_id = bu.user_id AND bu.badge_id = b.badge_id ' . $isql . $operation;

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
	 * Validate and add user badges.
	 *
	 * @param array $user_id A single user id.
	 * @param array $badge_ids Badges to add for user.
	 *
	 * @return string $mssg Language key.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function add_user_data(array $user_id, array $badge_ids): string
	{
         if (count($user_id) > 1 || !is_int($user_id[0]))
        {
            throw new \InvalidArgumentException('INVALID_OPTIONS_ADD_BU');
        }

		$badge_ids = array_filter($badge_ids, 'is_int');

		$bqsql = [
			'SELECT' => 'b.badge_id',
			'FROM' => [$this->b_tbl => 'b'],
			'LEFT_JOIN' => [
				['FROM' => [$this->bu_tbl => 'bu'], 'ON' => 'b.badge_id = bu.badge_id AND bu.user_id = ' . $user_id[0]],
				['FROM' => [$this->ba_tbl => 'ba'], 'ON' => 'b.badge_id = ba.badge_id AND ba.user_id = ' . $user_id[0]]],
			// Badges are valid if they are not already selected (bu.badge_id is null) and are either:
			//  - a default badge (b.badge_default is true)
			//  - have been awarded to the user (ba.user_id is not null)
			'WHERE' => 'bu.badge_id IS NULL AND (b.badge_default = TRUE OR ba.user_id = ' . $user_id[0] . ')'
		];

		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $bqsql));
		$valid_ids = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$valid_ids[] = (int)$row['badge_id'];
		}

		$data = [];

		foreach ($badge_ids as $id)
		{
			if (in_array($id, $valid_ids))
			{
				$data[] = ['user_id' => $user_id[0], 'badge_id' => $id, 'badge_order' => null];
			}
		}

		$this->db->sql_multi_insert($this->bu_tbl, $data);
		$this->container->get('openra.openrauseraccounts.helper')->fix_item_order($this->bu_tbl, 'badge_order', $user_id[0]);

		return 'SELECTED_BADGES_SAVED';
    }

    /**
	 * Remove all or specified badges from user.
	 *
	 * @param array $user_id A single user id.
	 * @param array $badge_ids Empty deletes all badges from user.
	 * @param bool $negate Optional parameter, true for NOT IN, false (default) for IN.
	 *
	 * @return string $mssg Language key.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function remove_user_data(array $user_id, array $badge_ids, bool $negate = false): string
	{
        if (count($user_id) > 1 || !is_int($user_id[0]))
        {
            throw new \InvalidArgumentException('INVALID_OPTIONS_RM_BU');
        }

		$badge_ids = array_filter($badge_ids, 'is_int');
		$isql = '';

		if (!empty($badge_ids))
		{
			$isql = ' AND '. $this->db->sql_in_set('badge_id', $badge_ids, $negate);
		}

		$this->db->sql_query('DELETE FROM ' . $this->bu_tbl . ' WHERE user_id = ' . $user_id[0] . $isql);

		if ($isql)
		{
			$this->container->get('openra.openrauseraccounts.helper')->fix_item_order($this->bu_tbl, 'badge_order', $user_id[0]);
		}

		$mssg = !$isql ? 'ALL_USER_BADGES_REMOVED' : (!$negate ? '' : '');

		return $mssg;
	}

	function make(array $data, int $item = null)
	{
		return;
	}

	function delete(int $item)
	{
		return;
	}
}

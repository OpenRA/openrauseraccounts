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
 * Class for handling badge availability.
 */
class badge_availability extends base
{
	/**
	 * Count badge availabilities.
	 *
	 * Count all: Default, $options = []
	 * Count by user id: $options = ['user_id' => $user_id]
	 * Count by badge id: $options = ['badge_id' => $badge_id]
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

			case is_int($options['user_id']):
				$isql = ' WHERE user_id = ' . $options['user_id'];
				break;

			case is_int($options['badge_id']):
				$isql = ' WHERE badge_id = ' . $options['badge_id'];
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_COUNT_BA');
		}

		$this->db->sql_query('SELECT COUNT(*) AS count FROM ' . $this->ba_tbl . $isql);
		$count = (int)$this->db->sql_fetchfield('count');

		return $count;
	}

	/**
	 * Get badge availability data.
	 *
	 * @param array $options
	 * Get all: Default, $options = []
	 * Get by user id: $options = ['user_id' => $user_id]
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
		$isql = '';

		switch ($options)
		{
			case !isset($options):
				break;

			case is_int($options['user_id']):
				$isql = ' AND user_id = ' . $options['user_id'];
				break;

			case is_int($options['badge_id']):
				$isql = ' AND ba.badge_id = ' . $options['badge_id'];
				break;

			default: throw new \InvalidArgumentException('INVALID_OPTIONS_GET_BA');
		}

		$sql = 'SELECT ba.*, u.username
				FROM ' . $this->ba_tbl . ' AS ba, ' . USERS_TABLE . ' AS u
				WHERE ba.user_id = u.user_id' .  $isql . '
				ORDER BY u.username';

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
	 * Add badge availabillty for users.
	 *
	 * @param array $usernames Usernames entered in the textfield.
	 * @param array $badge_id A single badge id.
	 *
	 * @return string $mssg Returns the formatted languag key with added or rejected usernames on success.
	 *
	 * @throws \InvalidArgumentException
	 */
	public function add_user_data(array $usernames, array $badge_id): string
	{

		if (count($badge_id) > 1 || !is_int($badge_id[0]))
		{
			throw new \InvalidArgumentException('INVALID_OPTIONS_ADD_BA');
		}

		$badge_manager = $this->container->get('openra.openrauseraccounts.badge_manager');
		$label = $this->db->sql_fetchfield('badge_label', false, $badge_manager->get_data('badge', ['badge_id' => $badge_id[0]]));
		$added = $rejected = [];

		foreach ($usernames as $name)
		{
			$this->db->sql_query(
				'SELECT u.user_id
				FROM ' . USERS_TABLE . ' AS u
				LEFT JOIN ' . $this->ba_tbl . ' AS ba
				ON ba.user_id = u.user_id AND ba.badge_id = ' . $badge_id[0] . '
				WHERE u.username = "' . $this->db->sql_escape($name) . '"
				AND ba.user_id IS NULL'
			); // Filter users from input that have the availability already.
			$user_id = (int)$this->db->sql_fetchfield('user_id');
			$this->db->sql_freeresult();

			if (!$user_id)
			{
				$rejected[] = $name;
			}
			else
			{
				$added[] = $name;
				$data[] = ['user_id' => $user_id,'badge_id' => $badge_id[0]];
			}
		}

		$this->db->sql_multi_insert($this->ba_tbl, $data);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AVAIL_ADDED', false, array($label, implode(', ', $added)));
		$mssg = '';
		$mssg .= ($added ? $this->language->lang('AVAIL_ADDED', $label, implode(', ', $added)) : '');
		$mssg .= ($rejected ? $this->language->lang('AVAIL_NOT_ADDED', implode(', ', $rejected)) : '');

		return $mssg;
	}

	/**
	 * Remove badge availability and user badge for specified or all users.
	 *
	 * @param array $user_ids Empty deletes all availabilities for badge.
	 * @param array $badge_id A single badge id.
	 * @param bool $negate Optional parameter, true for NOT IN, false (default) for IN.
	 *
	 * @return string $mssg Language key.
	 *
	 * @throws \InvalidArgumentException
	 */
	function remove_user_data(array $user_ids, array $badge_id, bool $negate = false): string
	{
		if (count($badge_id) > 1 || !is_int($badge_id[0]))
		{
			throw new \InvalidArgumentException('INVALID_OPTIONS_ADD_BA');
		}

		$user_ids = array_filter($user_ids, 'is_int');
		$usernames = [];
		$badge_manager = $this->container->get('openra.openrauseraccounts.badge_manager');
		$label = $this->db->sql_fetchfield('badge_label', false, $badge_manager->get_data('badge', ['badge_id' => $badge_id[0]]));
		$isql = '';

		if (!empty($user_ids))
		{
			$isql = ' AND ' . $this->db->sql_in_set('ba.user_id', $user_ids, $negate);
		}

		$result = $this->db->sql_query(
			'SELECT u.username, ba.user_id
			FROM ' . USERS_TABLE . ' AS u, ' . $this->ba_tbl . ' AS ba
			WHERE u.user_id = ba.user_id ' . $isql
		);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$usernames[] = $row['username'];
			$this->db->sql_query('DELETE FROM ' . $this->ba_tbl . ' WHERE badge_id = ' . $badge_id[0] . ' AND user_id = ' . $row['user_id']);
			$this->db->sql_query('DELETE FROM ' . $this->bu_tbl . ' WHERE badge_id = ' . $badge_id[0] . ' AND user_id = ' . $row['user_id']);
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AVAIL_DELETED' . $mssg, false, array($label, implode(', ', $usernames)));

		$mssg = !$isql ? 'ALL_AVAILS_DELETED' : (!$negate ? 'SELECTED_AVAILS_DELETED' : '');

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

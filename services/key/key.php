<?php declare(strict_types=1);
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\services\key;

/**
 * Authentication key service class.
 */
class key
{
	protected $db;
	protected $k_tbl;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param string $k_tbl
	 *
	 * @return \openra\openrauseraccounts\services\key\key
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, $k_tbl)
	{
		$this->db = $db;
		$this->k_tbl = $k_tbl;
	}

	/**
	 * Count keys by options.
	 *
	 * @param array $options
	 * Count all: Default, $options = []
	 * Count by user id:
     *  All: ['user_id' => $user_id]
     *  Exclude revoked: ['user_id' => $user_id, 'revoked' => false]
     * Count by last accessed:
     *  To count by distinct field include: ['distinct' => 'fieldname']
     *  Since: ['accessed_since' => mktime()]
     *  Until: ['accessed_until' => mktime()]
     *  Between: ['accessed_since' => mktime(), 'accessed_until' => mktime()]
     * Count by registered:
     *  To count by distinct field include: ['distinct' => 'fieldname']
     *  Since: ['registered_since' => mktime()]
     *  Until: ['registered_until' => mktime()]
     *  Between: ['registered_since' => mktime(), 'registered_until' => mktime()]
	 *
	 * @return int
     *
     * @throws \InvalidArgumentException
	 */
	public function count_keys(array $options = []): int
	{
        $mode = '*';
        $isql = '';

        while (list($key, ) = each($options))
        {
            $reg = is_int(stripos($key, 'registered'));
            $acc = is_int(stripos($key, 'accessed'));
            $since = is_int(stripos($key, '_since'));
            $until = is_int(stripos($key, '_until'));
        }

        $op = $since ? ($until ? ' BETWEEN' : ' <=') : ' >=';

        if (($reg && $acc) || (($reg || $acc) && !$since && !$until))
        {
            throw new \InvalidArgumentException('INVALID_OPTIONS_COUNT_KEYS');
        }

        switch ($options)
        {
            case !isset($options):
                break;

            case is_int($options['user_id']):
                $isql = ' WHERE user_id = ' . $options['user_id'] . (isset($options['revoked']) ? ' AND revoked = false' : '');
                break;

            case $acc:
            case $reg:

                if (isset($options['distinct']))
                {
                    $mode = 'DISTINCT ' . $options['distinct'];
                }

                $isql = ' WHERE ' . $reg ? 'registered' : 'last_accessed' . $op . (int)$options[$reg ? 'registered' : 'accessed' . $since ? '_since' : '_until'] . $op == 'BETWEEN' ? ' AND ' . (int)$options[$reg ? 'registered' : 'accessed' . '_until'] : '';
                break;

            default: throw new \InvalidArgumentException('INVALID_OPTIONS_COUNT_KEYS');
        }

        $this->db->sql_query('SELECT COUNT(' . $mode . ') AS count FROM ' . $this->k_tbl . $isql);
        $count = (int)$this->db->sql_fetchfield('count');

		return $count;
	}

	/**
	 * Validate and insert key data.
	 *
	 * @param string $key OpenSSL public key.
	 * @param int $user_id
	 *
	 * @return string Language key.
     *
     * @throws \InvalidArgumentException
	 */
	public function add_key(string $key, int $user_id, int $limit = 0, $start = 0): string
	{
		$pkey_r = openssl_pkey_get_public($key);

		if (!$pkey_r)
		{
			throw new \InvalidArgumentException('INVALID_KEY');
		}

		$pkey_d = openssl_pkey_get_details($pkey_r);

		if (!isset($pkey_d['rsa']['n'], $pkey_d['rsa']['e'], $pkey_d['key']))
		{
			throw new \InvalidArgumentException('INVALID_KEY');
		}

		$fp = sha1($pkey_d['rsa']['n'] . $pkey_d['rsa']['e']);
        $pk = $pkey_d['key'];

        $this->db->sql_query(
            'SELECT  revoked AS duplicate
            FROM ' . $this->k_tbl . '
            WHERE fingerprint = "' . $this->db->sql_escape($fp) . '"
            OR public_key = "' . $this->db->sql_escape($pk) . '"
            AND revoked IS NOT NULL'
        );

        $duplicate = $this->db->sql_fetchfield('duplicate');
        $this->db->sql_freeresult();

		if ($duplicate === '0')
		{
			throw new \InvalidArgumentException('DUPLICATE_KEY');
		}
		elseif ($duplicate === '1')
		{
			throw new \InvalidArgumentException('DUPLICATE_KEY_REVOKED');
		}
		elseif ($duplicate === false)
		{
			$data = ['user_id' => $user_id, 'public_key' => $pk, 'fingerprint' => $fp, 'registered' => time()];
			$this->db->sql_query('INSERT INTO ' . $this->k_tbl . $this->db->sql_build_array('INSERT', $data));

			return 'KEY_SAVED';
        }
	}

	/**
	 * Get key data and username by options.
	 *
	 * @param array $options
	 * Get all: Default, $options = []
	 * Get by user id: $options = ['user_id' => $user_id]
	 * Get by fingerprint: $options = ['fingerprint' => $fingerprint]
	 * Get by item id: $options = ['item_id' => $item_id]
	 * @param int $limit Optional parameter to limit rows.
	 * @param int $start Optional parameter to set offset for rows when limit is set.
	 *
	 * @return $result Query result object.
     *
     * @throws \InvalidArgumentException
	 */
	public function get_key_data(array $options = [], int $limit = 0, int $start = 0)
	{
		$tables = USERS_TABLE . ' AS u, ' . $this->k_tbl . ' AS k';
		$isql = '';
		$operation = ' GROUP BY k.user_id ORDER BY k.registered DESC';

        switch ($options)
        {
            case !isset($options):
                break;

            case is_int($options['user_id']):
                $isql = 'AND u.user_id = ' . $options['user_id'] . (isset($options['revoked']) ? ' AND revoked = false' : '');
                $operation = ' ORDER BY registered DESC';
                break;

            case is_string($options['fingerprint']):
                $isql = 'AND k.fingerprint = "' . $this->db->sql_escape($options['fingerprint']);
                $operation = '';
                break;

            case is_int($options['item_id']):
                $isql = 'AND k.item_id = ' . $options['item_id'];
                $operation = '';
                break;

            default: throw new \InvalidArgumentException('INVALID_OPTIONS_GET_KEYS');
        }

        $sql = 'SELECT k.*, u.username FROM ' . $tables . ' WHERE k.user_id = u.user_id ' . $isql . $operation;

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
	 * Revoke all or specified keys from user.
	 *
	 * @param int $user_id
	 * @param array $item_ids Optional, default empty revokes all keys from user.
	 *
	 * @return string Language key.
	 */
	public function revoke_keys(int $user_id, array $item_id = []): string
	{
		$item_id = array_filter($item_id, 'is_int');
		$isql = '';

		if (!empty($item_id))
		{
			$isql = ' AND ' . $this->db->sql_in_set('item_id', $item_id);
        }

		$this->db->sql_query('UPDATE ' . $this->k_tbl . ' SET revoked = true WHERE user_id = ' . $user_id . $isql);

		if (!$this->db->sql_affectedrows())
		{
			throw new \InvalidArgumentException('CAN_NOT_REVOKE_KEY');
		}

		$mssg = (!$isql ? 'ALL_' : 'SELECTED_') . 'KEYS_REVOKED';

		return $mssg;
	}

	/**
	 * Update last accessed time.
	 *
	 * @param string $fingerprint
	 *
	 * @return bool Returns true on success.
	 */
	public function update_key_access(string $fingerprint): bool
	{
		$this->db->sql_query(
			'UPDATE ' . $this->k_tabl . '
			SET last_accessed = ' . time() . '
			WHERE fingerprint = "' . $this->db->sql_escape($fingerprint) . '"'
		);

		if (!$this->db->sql_affectedrows())
		{
			throw new \InvalidArgumentException('CAN_NOT_UPDATE_KEY_ACCESS');
		}

		return true;
	}
}

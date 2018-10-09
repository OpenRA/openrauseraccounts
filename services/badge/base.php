<?php declare(strict_types=1);
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General abstract License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\services\badge;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Badge base class.
 */
abstract class base
{
	protected $db;
	protected $user;
	protected $language;
	protected $log;
	protected $container;
	protected $b_tbl;
	protected $ba_tbl;
	protected $bu_tbl;
	protected $bt_tbl;

	/**
	 * Constructor
	 *
	 * @return \openra\openrauseraccounts\services\badge\base
	 */
	function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\language\language $language, \phpbb\log\log $log, ContainerInterface $container, $b_tbl, $ba_tbl, $bu_tbl, $bt_tbl)
	{
		$this->db = $db;
		$this->user = $user;
		$this->language = $language;
		$this->log = $log;
		$this->container = $container;
		$this->b_tbl = $b_tbl;
		$this->ba_tbl = $ba_tbl;
		$this->bu_tbl = $bu_tbl;
		$this->bt_tbl = $bt_tbl;
	}

	abstract function get_count(array $options);

	abstract function get_data(array $options, int $limit, int $start);

	abstract function add_user_data(array $user, array $item);

	abstract function remove_user_data(array $user, array $item, bool $negate);

	abstract function make(array $data, int $item);

	abstract function delete(int $item);
}

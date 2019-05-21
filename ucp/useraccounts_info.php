<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\ucp;

/**
 * OpenRAUserAccounts UCP module info.
 */
class useraccounts_info
{
	public function module()
	{
		return array(
			'filename' => '\openra\openrauseraccounts\ucp\useraccounts_module',
			'title' => 'UCP_ACCOUNT',
			'modes' => array(
				'add_key' => array(
					'title'	=> 'UCP_ACCOUNT_ADD_KEY',
					'auth'	=> 'ext_openra/openrauseraccounts',
					'cat'	=> 'UCP_ACCOUNT'
				),
				'manage_keys' => array(
					'title'	=> 'UCP_ACCOUNT_MANAGE_KEYS',
					'auth'	=> 'ext_openra/openrauseraccounts',
					'cat'	=> 'UCP_ACCOUNT'
				),
				'select_badges' => array (
					'title'	=> 'UCP_ACCOUNT_SELECT_BADGES',
					'auth'	=> 'ext_openra/openrauseraccounts',
                    'cat'	=> 'UCP_ACCOUNT'
				),
                'order_badges' => array(
					'title'	=> 'UCP_ACCOUNT_ORDER_BADGES',
					'auth'	=> 'ext_openra/openrauseraccounts',
                   	'cat'	=> 'UCP_ACCOUNT'
				)
			)
		);
	}
}

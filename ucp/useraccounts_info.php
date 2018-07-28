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
			'title' => 'UCP_TITLE',
			'modes' => array(
				'add_key' => array(
					'title'	=> 'UCP_ADD_KEY',
					'auth'	=> 'ext_openra/openrauseraccounts',
					'cat'	=> 'UCP_TITLE'
				),
				'manage_keys' => array(
					'title'	=> 'UCP_MANAGE_KEYS',
					'auth'	=> 'ext_openra/openrauseraccounts',
					'cat'	=> 'UCP_TITLE'
				),
				'select_badges' => array (
					'title'	=> 'UCP_SELECT_BADGES',
					'auth'	=> 'ext_openra/openrauseraccounts',
                    			'cat'	=> 'UCP_TITLE'
				),
                		'order_badges' => array(
					'title'	=> 'UCP_ORDER_BADGES',
					'auth'	=> 'ext_openra/openrauseraccounts',
                   			 'cat'	=> 'UCP_TITLE'
				)
			)
		);
	}
}

<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\acp;

/**
 * OpenRAUserAccounts ACP module info.
 */
class badges_info
{
	public function module()
	{
		return array(
			'filename' => '\openra\openrauseraccounts\acp\badges_module',
			'title' => 'ACP_TITLE',
			'modes' => array(
				'settings' => array(
					'title'	=> 'ACP_SETTINGS',
					'auth'	=> 'ext_openra/openrauseraccounts && acl_a_board',
					'cat'	=> array('ACP_TITLE')
				),
				'types' => array(
					'title'	=> 'ACP_TYPES',
					'auth'	=> 'ext_openra/openrauseraccounts && acl_a_board',
					'cat'	=> array('ACP_TITLE')
				),
				'badges' => array(
					'title'	=> 'ACP_BADGES',
					'auth'	=> 'ext_openra/openrauseraccounts && acl_a_board',
					'cat'	=> array('ACP_TITLE')
				)
			)
		);
	}
}

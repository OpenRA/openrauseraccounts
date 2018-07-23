<?php
/**
 *
 * OpenRAUserAccounts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, The OpenRAUserAccounts authors
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace openra\openrauseraccounts\controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenRAUserAccounts main controller.
 */
class main
{
	/* @var \openra\openrauseraccounts\core\core */
	protected $core;

	/* @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\config\config */
	protected $config;

	protected $table_prefix;

	/**
	 * Constructor
	 *
	 * @param \openra\openrauseraccounts\core\core $core
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param \phpbb\config\config $config
	 */
	public function __construct(\openra\openrauseraccounts\core\core $core, \phpbb\db\driver\driver_interface $db, \phpbb\config\config $config)
	{
		global $phpbb_container;
		$this->core = $core;
		$this->db = $db;
		$this->config = $config;
		$this->table_prefix = $phpbb_container->getParameter('core.table_prefix');
	}

	/**
	 * Controller for route /openra/{$type}/{$fingerprint}
	 *
	 * @param string $type, $fingerprint
	 *
	 * @return \Symfony\Component\HttpFoundation\Response A Symfony Response object
	 */
	public function fetchinfo($type, $fingerprint)
	{
		switch ($type)
		{
			case 'info':
			{
				// Retrieve profile data
				$sql = $this->core->get_info_sql($fingerprint);
				if (!($result = $this->db->sql_query($sql)))
				{
					echo "Error: Failed to query profile data";
						exit;
				}
				$data = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
				if (!$data)
				{
					echo "Error: No profile data";
						exit;
				}

				// Retrieve badge data
				$sql = $this->core->get_badge_sql($fingerprint, '');
				if (!($result = $this->db->sql_query_limit($sql, $this->config['max_profile_badges'])))
				{
					echo "Error: Failed to query badge data";
						exit;
				}
				// Store all the badge data in an array to loop over it later
				$badges = array();
				while ($row = $this->db->sql_fetchrow($result))
				{
					$badges[] = $row;
				}
				$this->db->sql_freeresult($result);

				// Update last accessed time
				$sql = $this->core->get_update_sql($fingerprint);
				if (!($result = $this->db->sql_query($sql)))
				{
					echo "Error: Failed to update last accessed time";
						exit;
				}

				$yaml = "Player:\n";
				$yaml .= "\tFingerprint: " . $data['fingerprint'] . "\n";
				$yaml .=  "\tPublicKey: " . base64_encode($data['public_key']) . "\n";
				$yaml .=  "\tKeyRevoked: " . ($data['revoked'] ? 'true' : 'false') . "\n";
				$yaml .=  "\tProfileID: " . $data['user_id'] . "\n";
				$yaml .=  "\tProfileName: " . $data['username'] . "\n";
				$yaml .=  "\tProfileRank: Registered User\n";
				$yaml .=  "\tBadges:\n";
				if ($badges)
				{
					$i = 0;
					foreach ($badges as $badge)
					{
						$yaml .=  "\t\tBadge@$i:\n";
						$yaml .=  "\t\t\tLabel: " . $badge['badge_label'] . "\n";
						$yaml .=  "\t\t\tIcon24: " . $badge['badge_icon_24'] . "\n";
						$i++;
					}
				}

				$response = new Response($yaml);
				$response->headers->set('Content-Type', 'Content-type: text/plain; charset=utf-8');
				return $response;

				break;
			}

			default:
			{
				echo "Error: Unknown route";
				return new Response();
			}
		}
	}
}

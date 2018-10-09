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

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Badge manager service class.
 */
class manager
{
    protected $container;
	protected $collection;

	/**
	 * Constructor
	 *
	 * @return \openra\openrauseraccounts\services\badge\manager
	 */
	public function __construct(ContainerInterface $container, $collection)
	{
		$this->container = $container;
    }

    public function get_count(string $classname, array $options = [])
    {
       return $this->load_object($classname)->get_count($options);
    }

    public function get_data(string $classname, array $options = [], int $limit = 0, int $start = 0)
    {
       return $this->load_object($classname)->get_data($options, $limit, $start);
    }

    public function add_user_data(string $classname, array $user, array $item)
    {
        return $this->load_object($classname)->add_user_data($user, $item);
    }

    public function remove_user_data(string $classname, array $user, array $item, bool $negate = false)
    {
        return $this->load_object($classname)->remove_user_data($user, $item, $negate);
    }

    public function make(string $classname, array $data, int $item = 0)
    {
        return $this->load_object($classname)->make($data, $item);
    }

    public function delete(string $classname, int $item)
    {
        return $this->load_object($classname)->delete($item);
    }

    protected function load_object(string $classname)
	{
		$object = $this->container->get('openra.openrauseraccounts.' . $classname);

		return $object;
	}
}

<?php

/**
 * @license LGPLv3, http://www.gnu.org/licenses/lgpl.html
 * @copyright Metaways Infosystems GmbH, 2014
 * @copyright Aimeos (aimeos.org), 2015-2016
 * @package MAdmin
 * @subpackage Cache
 */


namespace Aimeos\MAdmin\Cache\Manager;


/**
 * Redis cache manager implementation.
 *
 * @package MAdmin
 * @subpackage Cache
 */
class Redis
	extends \Aimeos\MAdmin\Common\Manager\Base
	implements \Aimeos\MAdmin\Cache\Manager\Iface
{
	private $object;
	private $searchConfig = array(
		'cache.id' => array(
			'code' => 'cache.id',
			'internalcode' => '"id"',
			'label' => 'Cache ID',
			'type' => 'string',
			'internaltype' => \Aimeos\MW\DB\Statement\Base::PARAM_STR,
		),
	);


	/**
	 * Returns the cache object
	 *
	 * @return \Aimeos\MW\Cache\Iface Cache object
	 */
	public function getCache()
	{
		if( !isset( $this->object ) )
		{
			$context = $this->getContext();
			$config = $context->getConfig();

			$conn = $config->get( 'resource/cache/redis/connection' );
			$conf = $config->get( 'resource/cache/redis', [] );

			if( !class_exists( '\\Predis\\Client' ) ) {
				throw new \Aimeos\MAdmin\Cache\Exception( sprintf( 'Please install "%1$s" via composer first', 'predis/predis' ) );
			}

			$client = new \Predis\Client( $conn, $conf );
			$conf = array( 'siteid' => $context->getLocale()->getSiteId() );

			$this->object = \Aimeos\MW\Cache\Factory::createManager( 'Redis', $conf, $client );
		}

		return $this->object;
	}


	/**
	 * Create new cache item object.
	 *
	 * @return \Aimeos\MAdmin\Cache\Item\Iface
	 */
	public function createItem()
	{
		return $this->createItemBase();
	}


	/**
	 * Adds a new cache to the storage.
	 *
	 * @param \Aimeos\MAdmin\Cache\Item\Iface $item Cache item that should be saved to the storage
	 * @param boolean $fetch True if the new ID should be returned in the item
	 */
	public function saveItem( \Aimeos\MShop\Common\Item\Iface $item, $fetch = true )
	{
		$iface = '\\Aimeos\\MAdmin\\Cache\\Item\\Iface';
		if( !( $item instanceof $iface ) ) {
			throw new \Aimeos\MAdmin\Cache\Exception( sprintf( 'Object is not of required type "%1$s"', $iface ) );
		}

		if( ! $item->isModified() ) {
			return;
		}

		$id = $item->getId();
		$cache = $this->getCache();

		$cache->delete( $id );
		$cache->set( $id, $item->getValue(), $item->getTimeExpire(), $item->getTags() );
	}


	/**
	 * Removes multiple items specified by ids in the array.
	 *
	 * @param array $ids List of IDs
	 */
	public function deleteItems( array $ids )
	{
		$this->getCache()->deleteMultiple( $ids );
	}


	/**
	 * Creates the cache object for the given cache id.
	 *
	 * @param integer $id Cache ID to fetch cache object for
	 * @param array $ref List of domains to fetch list items and referenced items for
	 * @param boolean $default Add default criteria
	 * @return \Aimeos\MAdmin\Cache\Item\Iface Returns the cache item of the given id
	 * @throws \Aimeos\MAdmin\Cache\Exception If item couldn't be found
	 */
	public function getItem( $id, array $ref = [], $default = false )
	{
		if( ( $value = $this->getCache()->get( $id ) ) === null ) {
			throw new \Aimeos\MAdmin\Cache\Exception( sprintf( 'Item with ID "%1$s" not found', $id ) );
		}

		return $this->createItemBase( array( 'id' => $id, 'value' => $value ) );
	}


	/**
	 * Search for cache entries based on the given criteria.
	 *
	 * @param \Aimeos\MW\Criteria\Iface $search Search object containing the conditions
	 * @param integer &$total Number of items that are available in total
	 *
	 * @return array List of cache items implementing \Aimeos\MAdmin\Cache\Item\Iface
	 */
	public function searchItems( \Aimeos\MW\Criteria\Iface $search, array $ref = [], &$total = null )
	{
		/** Not available in a reasonable implemented way by Redis */
		return [];
	}


	/**
	 * Returns the available manager types
	 *
	 * @param boolean $withsub Return also the resource type of sub-managers if true
	 * @return array Type of the manager and submanagers, subtypes are separated by slashes
	 */
	public function getResourceType( $withsub = true )
	{
		$path = 'madmin/cache/manager/submanagers';

		return $this->getResourceTypeBase( 'cache', $path, [], $withsub );
	}


	/**
	 * Returns the attributes that can be used for searching.
	 *
	 * @param boolean $withsub Return also attributes of sub-managers if true
	 * @return array Returns a list of attribtes implementing \Aimeos\MW\Criteria\Attribute\Iface
	 */
	public function getSearchAttributes( $withsub = true )
	{
		$path = 'madmin/cache/manager/submanagers';

		return $this->getSearchAttributesBase( $this->searchConfig, $path, [], $withsub );
	}


	/**
	 * Returns a new manager for cache extensions
	 *
	 * @param string $manager Name of the sub manager type in lower case
	 * @param string|null $name Name of the implementation, will be from configuration (or Default) if null
	 * @return mixed Manager for different extensions, e.g stock, tags, locations, etc.
	 */
	public function getSubManager( $manager, $name = null )
	{
		return $this->getSubManagerBase( 'cache', $manager, $name );
	}


	/**
	 * Create new admin cache item object initialized with given parameters.
	 *
	 * @param array $values Associative list of key/value pairs of a job
	 * @return \Aimeos\MAdmin\Cache\Item\Iface
	 */
	protected function createItemBase( array $values = [] )
	{
		$values['siteid'] = $this->getContext()->getLocale()->getSiteId();

		return new \Aimeos\MAdmin\Cache\Item\Standard( $values );
	}
}

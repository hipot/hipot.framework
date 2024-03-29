<?php

namespace Hipot\Types\Container;

/**
 * Implements magic access to container
 *
 * @method   void  doSetContainer(string $key, $value)
 * @method   mixed doGetContainer(string $key)
 * @method   bool  doContainsContainer(string $key)
 * @method   void  doDeleteContainer(string $key)
 */
trait MagicAccess
{
	/**
	 * Magic alias for set() regular method
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return void
	 */
	public function __set(string $key, $value): void
	{
		$this->doSetContainer($key, $value);
	}

	/**
	 * Magic alias for get() regular method
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get(string $key)
	{
		return $this->doGetContainer($key);
	}

	/**
	 * Magic alias for contains() regular method
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function __isset(string $key): bool
	{
		return $this->doContainsContainer($key);
	}

	/**
	 * Magic alias for delete() regular method
	 *
	 * @param string $key
	 *
	 * @return void
	 */
	public function __unset(string $key): void
	{
		$this->doDeleteContainer($key);
	}
}

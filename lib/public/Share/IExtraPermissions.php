<?php
/**
 * @author Piotr Mrowczynski <piotr@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCP\Share;

/**
 * Interface IExtraPermission
 *
 * @package OCP\Share
 * @since 10.2.0
 */
interface IExtraPermissions {

	/**
	 * Sets a permission. If the key did not exist before it will be created.
	 *
	 * @param string $app app
	 * @param string $key key
	 * @param bool $enabled enabled
	 * @since 10.2.0
	 */
	public function setPermission($app, $key, $enabled);

	/**
	 * Checks if permission for given app and key is enabled.
	 * If permission does not exist, returns null
	 *
	 * @param string $app app
	 * @param string $key key
	 * @return bool|null
	 * @since 10.2.0
	 */
	public function getPermission($app, $key);

	/**
	 * Get all apps for which extra permissions are set
	 *
	 * @return string[] apps
	 * @since 10.2.0
	 */
	public function getApps();

	/**
	 * Get all permission keys for specific app
	 *
	 * @param string $app
	 * @return string[]
	 * @since 10.2.0
	 */
	public function getKeys($app);

}
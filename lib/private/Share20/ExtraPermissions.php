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
namespace OC\Share20;

use OCP\Share\IExtraPermissions;

class ExtraPermissions implements IExtraPermissions {

	/** @var array */
	private $permissions;

	public function __construct() {
		$this->permissions = [];
	}

	/**
	 * @inheritdoc
	 */
	public function setPermission($app, $key, $enabled) {
		if (!\array_key_exists($app, $this->permissions)) {
			$this->permissions[$app] = [];
		}
		$this->permissions[$app][$key] = $enabled;
	}

	/**
	 * @inheritdoc
	 */
	public function getPermission($app, $key) {
		if (\array_key_exists($app, $this->permissions) &&
			\array_key_exists($key, $this->permissions[$app])) {
			return $this->permissions[$app][$key];
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function getApps() {
		return \array_keys($this->permissions);
	}

	/**
	 * @inheritdoc
	 */
	public function getKeys($app) {
		if (!\array_key_exists($app, $this->permissions)) {
			return [];
		}
		return \array_keys($this->permissions[$app]);
	}
}

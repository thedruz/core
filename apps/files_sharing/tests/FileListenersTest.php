<?php
/**
 * @author Piotr Mrowczynski piotr@owncloud.com
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
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

namespace OCA\Files_Sharing\Tests;

use OC\Files\View;
use OCA\Files_Sharing\Hooks;
use OCA\Files_Sharing\Service\NotificationPublisher;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\Share\IManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\Traits\UserTrait;

/**
 * Class FileListenersTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests
 */
class FileListenersTest extends TestCase {
	use UserTrait;

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/**
	 * @var Hooks
	 */
	private $hooks;

	protected function setUp() {
		parent::setUp();
		\OC_Hook::clear();

		$this->eventDispatcher = \OC::$server->getEventDispatcher();

		$this->hooks = new Hooks(
			\OC::$server->getRootFolder(),
			$this->createMock(IURLGenerator::class),
			$this->eventDispatcher,
			\OC::$server->getShareManager(),
			$this->createMock(NotificationPublisher::class)
		);
		$this->hooks->registerListeners();

		$this->setupShares();
	}

	protected function tearDown() {
		// Clean-up events from global event dispatcher
		foreach ($this->eventDispatcher->getListeners('file.beforeCreateZip') as $listener) {
			$this->eventDispatcher->removeListener('file.beforeCreateZip', $listener);
		}
		foreach ($this->eventDispatcher->getListeners('file.beforeGetDirect') as $listener) {
			$this->eventDispatcher->removeListener('file.beforeGetDirect', $listener);
		}
		parent::tearDown();
	}

	private function setupShares() {
		$rootFolder = \OC::$server->getRootFolder();
		$shareManager = \OC::$server->getShareManager();

		$this->createUser(self::TEST_FILES_SHARING_API_USER1);
		$this->createUser(self::TEST_FILES_SHARING_API_USER2);
		$senderView = new View('/' . self::TEST_FILES_SHARING_API_USER1 . '/files');

		// Prepare test for sender
		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER1);
		$senderView->file_put_contents('normal-share.txt', 'test');
		$node = $rootFolder->getUserFolder(self::TEST_FILES_SHARING_API_USER1)->get('normal-share.txt');
		$share = $shareManager->newShare();
		$share = $share->setNode($node)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setPermissions(\OCP\Constants::PERMISSION_READ);
		$shareManager->createShare($share);

		$senderView->mkdir('normal-dir');
		$senderView->file_put_contents('normal-dir/secure-share.txt', 'test');
		$node = $rootFolder->getUserFolder(self::TEST_FILES_SHARING_API_USER1)->get('normal-dir/secure-share.txt');
		$share = \OC::$server->getShareManager()->newShare();
		$share = $share->setNode($node)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setAttributes(
				$share->newAttributes()->setAttribute(
					'core',
					'can-download',
					false
				)
			);
		$shareManager->createShare($share);

		$senderView->mkdir('secure-share');
		$senderView->file_put_contents('secure-share/bar.txt', 'bar');
		$senderView->file_put_contents('secure-share/foo.txt', 'foo');
		$node = $rootFolder->getUserFolder(self::TEST_FILES_SHARING_API_USER1)->get('secure-share');
		$share = $shareManager->newShare();
		$share->setNode($node)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedBy(self::TEST_FILES_SHARING_API_USER1)
			->setSharedWith(self::TEST_FILES_SHARING_API_USER2)
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setAttributes(
				$share->newAttributes()->setAttribute(
					'core',
					'can-download',
					false
				)
			);
		$shareManager->createShare($share);
	}

	public function providesDataForCanZip() {
		return [
			// normal file (sender) - can download zip
			[ self::TEST_FILES_SHARING_API_USER1, '/', ['normal-share.txt'], true ],

			// shared file (receiver) - can download zip
			[ self::TEST_FILES_SHARING_API_USER2, '/', ['normal-share.txt'], true ],

			// normal files (sender) - can download zipped 2 files
			[ self::TEST_FILES_SHARING_API_USER1, '/secure-share', ['bar.txt', 'foo.txt'], true ],

			// shared files (receiver) with attribute can-download set false -
			// cannot download zipped 2 files
			[ self::TEST_FILES_SHARING_API_USER2, '/secure-share', ['bar.txt', 'foo.txt'], false ],

			// normal folder with file inside (sender) - can download zipped 1 file
			[ self::TEST_FILES_SHARING_API_USER1, '/', 'normal-dir', true ],

			// normal folder with shared files inside (receiver) which one has attribute can-download set false -
			// cannot download zipped 1 file
			[ self::TEST_FILES_SHARING_API_USER2, '/', '/', false ],

			// normal files (sender) - can download zipped folder
			[ self::TEST_FILES_SHARING_API_USER1, '/', 'secure-share', true ],

			// shared files (receiver) with attribute can-download set false -
			// cannot download zipped folder
			[ self::TEST_FILES_SHARING_API_USER2, '/', 'secure-share', false ],
		];
	}

	public function testFilesSharingAppRegisteredRequiredHooks() {
		$this->assertTrue($this->eventDispatcher->hasListeners('file.beforeCreateZip'));
		$this->assertTrue($this->eventDispatcher->hasListeners('file.beforeGetDirect'));
	}

	/**
	 * @dataProvider providesDataForCanZip
	 */
	public function testCheckZipCanBeDownloaded($user, $dir, $files, $run) {
		$this->loginAsUser($user);

		// Simulate zip download of folder folder
		$event = new GenericEvent(null, ['dir' => $dir, 'files' => $files, 'run' => true]);
		$this->eventDispatcher->dispatch('file.beforeCreateZip', $event);

		$this->assertEquals($run, $event->getArgument('run'));
		$this->assertEquals($run, !$event->hasArgument('errorMessage'));
	}

	public function providesDataForCanGet() {
		return [
			// normal file (sender) - can download directly
			[ self::TEST_FILES_SHARING_API_USER1, '/secure-share/bar.txt', true ],

			// shared file (receiver) with attribute can-download set false -
			// cannot download directly
			[ self::TEST_FILES_SHARING_API_USER2, '/secure-share/bar.txt', false ],
		];
	}

	/**
	 * @dataProvider providesDataForCanGet
	 */
	public function testCheckDirectCanBeDownloaded($user, $path, $run) {
		$this->loginAsUser($user);

		// Simulate direct download of file
		$event = new GenericEvent(null, [ 'path' => $path ]);
		$this->eventDispatcher->dispatch('file.beforeGetDirect', $event);

		$this->assertEquals($run, !$event->hasArgument('errorMessage'));
	}
}

<?php
/**
 * @author Piotr Mrowczynski piotr@owncloud.com
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
namespace OCA\DAV\Tests\unit\DAV;

use OCA\DAV\DAV\ViewOnlyPlugin;
use OCA\Files_Sharing\SharedStorage;
use OCA\DAV\Connector\Sabre\File as DavFile;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Storage\IStorage;
use OCP\Share\IExtraPermissions;
use OCP\Share\IShare;
use Sabre\DAV\Tree;
use Test\TestCase;
use Sabre\HTTP\RequestInterface;
use OCA\DAV\Connector\Sabre\Exception\Forbidden;

class ViewOnlyPluginTest extends TestCase {

	/** @var ViewOnlyPlugin */
	private $plugin;
	/** @var Tree | \PHPUnit_Framework_MockObject_MockObject */
	private $tree;
	/** @var RequestInterface | \PHPUnit_Framework_MockObject_MockObject */
	private $request;

	public function setUp() {
		$this->plugin = new ViewOnlyPlugin();
		$this->request = $this->getMockBuilder('Sabre\HTTP\RequestInterface')->getMock();
		$this->tree = $this->createMock(Tree::class);

		$server = $this->getMockBuilder('Sabre\DAV\Server')->getMock();
		$server->tree = $this->tree;

		$this->plugin->initialize($server);
	}

	public function testCanGetNonDav() {
		$this->request->expects($this->exactly(1))->method('getPath')->willReturn('files/test/target');
		$this->tree->method('getNodeForPath')->willReturn('this is not Dav File');

		$this->assertTrue($this->plugin->checkViewOnly($this->request));
	}

	public function testCanGetNonFileFolder() {
		$this->request->expects($this->exactly(1))->method('getPath')->willReturn('files/test/target');
		$davNode = $this->createMock(DavFile::class);
		$this->tree->method('getNodeForPath')->willReturn($davNode);

		$davNode->method('getNode')->willReturn('this is not File or Folder');

		$this->assertTrue($this->plugin->checkViewOnly($this->request));
	}

	public function testCanGetNonShared() {
		$this->request->expects($this->exactly(1))->method('getPath')->willReturn('files/test/target');
		$davNode = $this->createMock(DavFile::class);
		$this->tree->method('getNodeForPath')->willReturn($davNode);

		$node = $this->createMock(File::class);
		$davNode->method('getNode')->willReturn($node);

		$storage = $this->createMock(IStorage::class);
		$node->method('getStorage')->willReturn($storage);
		$storage->method('instanceOfStorage')->with(SharedStorage::class)->willReturn(false);

		$this->assertTrue($this->plugin->checkViewOnly($this->request));
	}

	public function nodeReturns() {
		return [
			// can download and is updatable - can get file
			[ $this->createMock(File::class), true, true, true],
			[ $this->createMock(Folder::class), true, true, true],
			// extra permission can download is for some reason disabled,
			// but file is updatable - so can get file
			[ $this->createMock(File::class), false, true, true],
			[ $this->createMock(Folder::class), false, true, true],
			// has extra permission can download, and read-only is set - can get file
			[ $this->createMock(File::class), true, false, true],
			[ $this->createMock(Folder::class), true, false, true],
			// has no extra permission can download, and read-only is set - cannot get the file
			[ $this->createMock(File::class), false, false, false],
			[ $this->createMock(Folder::class), false, false, false],
		];
	}

	/**
	 * @dataProvider nodeReturns
	 */
	public function testCanGet($node, $canDownloadPerm, $isUpdatable, $expected) {
		$this->request->expects($this->exactly(1))->method('getPath')->willReturn('files/test/target');

		$davNode = $this->createMock(DavFile::class);
		$this->tree->method('getNodeForPath')->willReturn($davNode);

		$davNode->method('getNode')->willReturn($node);

		$storage = $this->createMock(SharedStorage::class);
		$share = $this->createMock(IShare::class);
		$node->method('getStorage')->willReturn($storage);
		$storage->method('instanceOfStorage')->with(SharedStorage::class)->willReturn(true);
		$storage->method('getShare')->willReturn($share);

		$extPerms = $this->createMock(IExtraPermissions::class);
		$share->method('getExtraPermissions')->willReturn($extPerms);
		$extPerms->method('getPermission')->with('dav', 'can-download')->willReturn($canDownloadPerm);
		$node->method('isUpdateable')->willReturn($isUpdatable);

		try {
			// with these permissions / with this type of node user can download
			$ret = $this->plugin->checkViewOnly($this->request);
			$this->assertEquals($expected, $ret);
		} catch (Forbidden $e) {
			// this node is share, with read-only and without can-download permission
			$this->assertFalse($expected);
		}
	}
}

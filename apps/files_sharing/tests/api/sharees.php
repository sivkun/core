<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace OCA\Files_Sharing\Tests\API;

use OCA\Files_Sharing\API\Sharees;
use OCA\Files_sharing\Tests\TestCase;

class ShareesTest extends TestCase {
	/** @var Sharees */
	protected $sharees;

	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;

	/** @var \OCP\IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;

	/** @var \OCP\Contacts\IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $contactsManager;

	/** @var \OCP\IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $session;

	protected function setUp() {
		parent::setUp();

		$this->userManager = $this->getMockBuilder('OCP\IUserManager')
			->disableOriginalConstructor()
			->getMock();

		$this->groupManager = $this->getMockBuilder('OCP\IGroupManager')
			->disableOriginalConstructor()
			->getMock();

		$this->contactsManager = $this->getMockBuilder('OCP\Contacts\IManager')
			->disableOriginalConstructor()
			->getMock();

		$this->session = $this->getMockBuilder('OCP\IUserSession')
			->disableOriginalConstructor()
			->getMock();

		$this->sharees = new Sharees(
			$this->groupManager,
			$this->userManager,
			$this->contactsManager,
			$this->getMockBuilder('OCP\IAppConfig')->disableOriginalConstructor()->getMock(),
			$this->session,
			$this->getMockBuilder('OCP\IURLGenerator')->disableOriginalConstructor()->getMock()
		);
	}

	protected function getUserMock($uid, $displayName) {
		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->any())
			->method('getUID')
			->willReturn($uid);

		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn($displayName);

		return $user;
	}

	protected function getGroupMock($gid) {
		$group = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();

		$group->expects($this->any())
			->method('getGID')
			->willReturn($gid);

		return $group;
	}

	public function dataGetUsers() {
		return [
			['test', false, [], [], []],
			['test', true, [], [], []],
			[
				'test',
				false,
				[],
				[
					$this->getUserMock('test1', 'Test One'),
				],
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				]
			],
			[
				'test',
				false,
				[],
				[
					$this->getUserMock('test1', 'Test One'),
					$this->getUserMock('test2', 'Test Two'),
				],
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'Test Two', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				]
			],
			[
				'test',
				true,
				['abc', 'xyz'],
				[
					['abc', 'test', -1, 0, ['test1' => 'Test One']],
					['xyz', 'test', -1, 0, []],
				],
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				]
			],
			[
				'test',
				true,
				['abc', 'xyz'],
				[
					['abc', 'test', -1, 0, [
						'test1' => 'Test One',
						'test2' => 'Test Two',
					]],
					['xyz', 'test', -1, 0, [
						'test1' => 'Test One',
						'test2' => 'Test Two',
					]],
				],
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'Test Two', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				]
			],
			[
				'test',
				true,
				['abc', 'xyz'],
				[
					['abc', 'test', -1, 0, [
						'test1' => 'Test One',
					]],
					['xyz', 'test', -1, 0, [
						'test2' => 'Test Two',
					]],
				],
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'Test Two', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				]
			],
		];
	}

	/**
	 * @dataProvider dataGetUsers
	 *
	 * @param string $searchTerm
	 * @param bool $shareWithGroupOnly
	 * @param array $groupResponse
	 * @param array $userResponse
	 * @param array $expected
	 */
	public function testGetUsers($searchTerm, $shareWithGroupOnly, $groupResponse, $userResponse, $expected) {
		if (!$shareWithGroupOnly) {
			$this->userManager->expects($this->once())
				->method('searchDisplayName')
				->with($searchTerm)
				->willReturn($userResponse);
		} else {
			$user = $this->getUserMock('admin', 'Administrator');
			$this->session->expects($this->any())
				->method('getUser')
				->willReturn($user);

			$this->groupManager->expects($this->once())
				->method('getUserGroupIds')
				->with($user)
				->willReturn($groupResponse);

			$this->groupManager->expects($this->exactly(sizeof($groupResponse)))
				->method('displayNamesInGroup')
				->with($this->anything(), $searchTerm)
				->willReturnMap($userResponse);
		}

		$users = $this->invokePrivate($this->sharees, 'getUsers', [$searchTerm, $shareWithGroupOnly]);

		$this->assertEquals($expected, $users);
	}

	public function dataGetGroups() {
		return [
			['test', false, [], [], []],
			[
				'test', false,
				[$this->getGroupMock('test1')],
				[],
				[['label' => 'test1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'test1']]],
			],
			['test', true, [], [], []],
			[
				'test', true,
				[
					$this->getGroupMock('test1'),
					$this->getGroupMock('test2'),
				],
				[$this->getGroupMock('test1')],
				[['label' => 'test1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'test1']]],
			],
		];
	}

	/**
	 * @dataProvider dataGetGroups
	 *
	 * @param string $searchTerm
	 * @param bool $shareWithGroupOnly
	 * @param array $groupResponse
	 * @param array $userGroupsResponse
	 * @param array $expected
	 */
	public function testGetGroups($searchTerm, $shareWithGroupOnly, $groupResponse, $userGroupsResponse, $expected) {
		$this->groupManager->expects($this->once())
			->method('search')
			->with($searchTerm)
			->willReturn($groupResponse);

		if ($shareWithGroupOnly) {
			$user = $this->getUserMock('admin', 'Administrator');
			$this->session->expects($this->any())
				->method('getUser')
				->willReturn($user);

			$numGetUserGroupsCalls = empty($groupResponse) ? 0 : 1;
			$this->groupManager->expects($this->exactly($numGetUserGroupsCalls))
				->method('getUserGroups')
				->with($user)
				->willReturn($userGroupsResponse);
		}

		$users = $this->invokePrivate($this->sharees, 'getGroups', [$searchTerm, $shareWithGroupOnly]);

		$this->assertEquals($expected, $users);
	}

	public function dataGetRemote() {
		return [
			['test', [], []],
			[
				'test@remote',
				[],
				[
					['label' => 'test@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'test@remote']],
				],
			],
			[
				'test',
				[
					[
						'FN' => 'User3 @ Localhost',
					],
					[
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				[
					['label' => 'User @ Localhost (username@localhost)', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'username@localhost']],
				],
			],
			[
				'test@remote',
				[
					[
						'FN' => 'User3 @ Localhost',
					],
					[
						'FN' => 'User2 @ Localhost',
						'CLOUD' => [
						],
					],
					[
						'FN' => 'User @ Localhost',
						'CLOUD' => [
							'username@localhost',
						],
					],
				],
				[
					['label' => 'test@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'test@remote']],
					['label' => 'User @ Localhost (username@localhost)', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'username@localhost']],
				],
			],
		];
	}

	/**
	 * @dataProvider dataGetRemote
	 *
	 * @param string $searchTerm
	 * @param array $contacts
	 * @param array $expected
	 */
	public function testGetRemote($searchTerm, $contacts, $expected) {
		$this->contactsManager->expects($this->any())
			->method('search')
			->with($searchTerm, ['CLOUD', 'FN'])
			->willReturn($contacts);

		$users = $this->invokePrivate($this->sharees, 'getRemote', [$searchTerm]);

		$this->assertEquals($expected, $users);
	}

	public function dataSearchSharees() {
		return [
			['test', 'folder', [], null, 1, 2, false, [], [], [], [], 0, false],
			['test', 'folder', [0 => ['test1'], 1 => ['test2 group']], null, 1, 2, false, [], [], [], [], 0, false],
			// First page with 2 of 3 results
			[
				'test', 'folder', [], null, 1, 2, false, [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], [
					['label' => 'testgroup1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
				], [
					['label' => 'testz@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
				], [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'testgroup1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
				], 3, true,
			],
			// Second page with the 3rd result
			[
				'test', 'folder', [], null, 2, 2, false, [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], [
					['label' => 'testgroup1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
				], [
					['label' => 'testz@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
				], [
					['label' => 'testz@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
				], 3, false,
			],
			// Ingnoring already shared user
			[
				'test', 'folder', [\OCP\Share::SHARE_TYPE_USER => ['test1']], null, 1, 2, false, [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], [
					['label' => 'testgroup1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
				], [
					['label' => 'testz@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
				], [
					['label' => 'testgroup1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
					['label' => 'testz@remote', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
				], 2, false,
			],
			// Share type restricted to user - Only one user
			[
				'test', 'folder', [], \OCP\Share::SHARE_TYPE_USER, 1, 2, false, [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], null, null, [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], 1, false,
			],
			// Share type restricted to user - Multipage result
			[
				'test', 'folder', [], \OCP\Share::SHARE_TYPE_USER, 1, 2, false, [
					['label' => 'test 1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'test 2', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
					['label' => 'test 3', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test3']],
				], null, null, [
					['label' => 'test 1', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'test 2', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				], 3, true,
			],
			// Share type restricted to user - Only user already shared
			[
				'test', 'folder', [\OCP\Share::SHARE_TYPE_USER => ['test1']], \OCP\Share::SHARE_TYPE_USER, 1, 2, false, [
					['label' => 'test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], null, null, [], 0, false,
			],
		];
	}

	/**
	 * @dataProvider dataSearchSharees
	 *
	 * @param string $searchTerm
	 * @param string $itemType
	 * @param array $existingShares
	 * @param int $shareType
	 * @param int $page
	 * @param int $perPage
	 * @param bool $shareWithGroupOnly
	 * @param array $expected
	 */
	public function testSearchSharees($searchTerm, $itemType, array $existingShares, $shareType, $page, $perPage, $shareWithGroupOnly,
									  $mockedUserResult, $mockedGroupsResult, $mockedRemotesResult, $expected, $totalItems, $nextLink) {
		/** @var \PHPUnit_Framework_MockObject_MockObject|\OCA\Files_Sharing\API\Sharees $sharees */
		$sharees = $this->getMockBuilder('\OCA\Files_Sharing\API\Sharees')
			->setConstructorArgs([
				$this->groupManager,
				$this->userManager,
				$this->contactsManager,
				$this->getMockBuilder('OCP\IAppConfig')->disableOriginalConstructor()->getMock(),
				$this->session,
				$this->getMockBuilder('OCP\IURLGenerator')->disableOriginalConstructor()->getMock()
			])
			->setMethods(array('getUsers', 'getGroups', 'getRemote'))
			->getMock();
		$sharees->expects(($mockedUserResult === null) ? $this->never() : $this->once())
			->method('getUsers')
			->with($searchTerm, $shareWithGroupOnly)
			->willReturn($mockedUserResult);
		$sharees->expects(($mockedGroupsResult === null) ? $this->never() : $this->once())
			->method('getGroups')
			->with($searchTerm, $shareWithGroupOnly)
			->willReturn($mockedGroupsResult);
		$sharees->expects(($mockedRemotesResult === null) ? $this->never() : $this->once())
			->method('getRemote')
			->with($searchTerm)
			->willReturn($mockedRemotesResult);

		/** @var \OC_OCS_Result $ocs */
		$ocs = $this->invokePrivate($sharees, 'searchSharees', [$searchTerm, $itemType, $existingShares, $shareType, $page, $perPage, $shareWithGroupOnly]);

		$this->assertEquals($expected, $ocs->getData());

		// Check number of total results
		$meta = $ocs->getMeta();
		$this->assertArrayHasKey('totalitems', $meta);
		$this->assertSame($totalItems, $meta['totalitems']);

		// Check if next link is set
		if ($nextLink) {
			$headers = $ocs->getHeaders();
			$this->assertArrayHasKey('Link', $headers);
			$this->assertStringStartsWith('<', $headers['Link']);
			$this->assertStringEndsWith('> rel="next"', $headers['Link']);
		}
	}

	public function testSearchShareesNoItemType() {
		/** @var \OC_OCS_Result $ocs */
		$ocs = $this->invokePrivate($this->sharees, 'searchSharees', ['', null, [], null, 0, 0, false]);

		$this->assertSame(400, $ocs->getStatusCode(), 'Expected status code 400');
		$this->assertSame([], $ocs->getData(), 'Expected that no data is send');

		$meta = $ocs->getMeta();
		$this->assertNotEmpty($meta);
		$this->assertArrayHasKey('message', $meta);
		$this->assertSame('missing itemType', $meta['message']);
	}


	public function dataFilterSharees() {
		return [
			[[], [], []],
			[
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				],
				[],
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				],
			],
			[
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'Test Two', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				],
				['test1'],
				[
					1 => ['label' => 'Test Two', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				],
			],
			[
				[
					['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'Test Two', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				],
				['test2'],
				[
					0 => ['label' => 'Test One', 'value' => ['shareType' => \OCP\Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				],
			],
		];
	}

	/**
	 * @dataProvider dataFilterSharees
	 *
	 * @param array $potentialSharees
	 * @param array $existingSharees
	 * @param array $expectedSharees
	 */
	public function testFilterSharees($potentialSharees, $existingSharees, $expectedSharees) {
		$this->assertEquals($expectedSharees, $this->invokePrivate($this->sharees, 'filterSharees', [$potentialSharees, $existingSharees]));
	}
}

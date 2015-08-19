<?php
/**
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
namespace OCA\Files_Sharing\API;

use OC\Share\SearchResultSorter;
use OCP\Contacts\IManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Share;

class Sharees {

	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IManager */
	private $contactsManager;

	/** @var IConfig */
	private $config;

	/** @var IUserSession */
	private $userSession;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ILogger */
	private $logger;

	/**
	 * @param IGroupManager $groupManager
	 * @param IUserManager $userManager
	 * @param IManager $contactsManager
	 * @param IConfig $config
	 * @param IUserSession $userSession
	 * @param IURLGenerator $urlGenerator
	 * @param ILogger $logger
	 */
	public function __construct(IGroupManager $groupManager,
								IUserManager $userManager,
								IManager $contactsManager,
								IConfig $config,
								IUserSession $userSession,
								IURLGenerator $urlGenerator,
								ILogger $logger) {
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->contactsManager = $contactsManager;
		$this->config = $config;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	/**
	 * @param string $search
	 * @param bool $shareWithGroupOnly
	 *
	 * @return array possible sharees
	 */
	protected function getUsers($search, $shareWithGroupOnly) {
		$sharees = [];
		
		$users = [];
		if ($shareWithGroupOnly) {
			// Search in all the groups this user is part of
			$userGroups = $this->groupManager->getUserGroupIds($this->userSession->getUser());
			foreach ($userGroups as $userGroup) {
				$users = array_merge($users, $this->groupManager->displayNamesInGroup($userGroup, $search));
			}
			$users = array_unique($users);
		} else {
			// Search in all users
			$users_tmp = $this->userManager->searchDisplayName($search);

			// Put in array that maps uid => displayName
			foreach($users_tmp as $user) {
				$users[$user->getUID()] = $user->getDisplayName();
			}
		}


		foreach ($users as $uid => $displayName) {
			$sharees[] = [
				'label' => $displayName,
				'value' => [
					'shareType' => Share::SHARE_TYPE_USER,
					'shareWith' => $uid,
				],
			];
		}

		return $sharees;
	}

	/**
	 * @param string $search
	 * @param bool $shareWithGroupOnly
	 *
	 * @return array possible sharees
	 */
	protected function getGroups($search, $shareWithGroupOnly) {
		$sharees = [];
		$groups = $this->groupManager->search($search);
		$groups = array_map(function (IGroup $group) { return $group->getGID(); }, $groups);

		if (!empty($groups) && $shareWithGroupOnly) {
			// Intersect all the groups that match with the groups this user is a member of
			$userGroups = $this->groupManager->getUserGroups($this->userSession->getUser());
			$userGroups = array_map(function (IGroup $group) { return $group->getGID(); }, $userGroups);
			$groups = array_intersect($groups, $userGroups);
		}

		foreach ($groups as $gid) {
			$sharees[] = [
				'label' => $gid,
				'value' => [
					'shareType' => Share::SHARE_TYPE_GROUP,
					'shareWith' => $gid,
				],
			];
		}

		return $sharees;
	}

	/**
	 * @param string $search
	 *
	 * @return array possible sharees
	 */
	protected function getRemote($search) {
		$sharees = [];

		if (substr_count($search, '@') >= 1) {
			$sharees[] = [
				'label' => $search,
				'value' => [
					'shareType' => Share::SHARE_TYPE_REMOTE,
					'shareWith' => $search,
				],
			];
		}

		// Search in contacts
		$addressBookContacts = $this->contactsManager->search($search, ['CLOUD', 'FN']);
		foreach ($addressBookContacts as $contact) {
			if (isset($contact['CLOUD'])) {
				foreach ($contact['CLOUD'] as $cloudId) {
					$sharees[] = [
						'label' => $contact['FN'] . ' (' . $cloudId . ')',
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $cloudId
						]
					];
				}
			}
		}

		return $sharees;
	}

	/**
	 * @return \OC_OCS_Result
	 */
	public function search() {
		$search = isset($_GET['search']) ? (string) $_GET['search'] : '';
		$itemType = isset($_GET['itemType']) ? (string) $_GET['itemType'] : null;
		$existingShares = isset($_GET['existingShares']) ? (array) $_GET['existingShares'] : [];
		$page = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
		$perPage = !empty($_GET['limit']) ? max(1, (int) $_GET['limit']) : 200;

		$shareTypes = [
			Share::SHARE_TYPE_USER,
			Share::SHARE_TYPE_GROUP,
			Share::SHARE_TYPE_REMOTE,
		];
		if (isset($_GET['shareType']) && is_array($_GET['shareType'])) {
			$shareTypes = array_intersect($shareTypes, $_GET['shareType']);
			sort($shareTypes);

		} else if (isset($_GET['shareType']) && is_numeric($_GET['shareType'])) {
			$shareTypes = array_intersect($shareTypes, [(int) $_GET['shareType']]);
			sort($shareTypes);
		}

		if (in_array(Share::SHARE_TYPE_REMOTE, $shareTypes) && !$this->isRemoteSharingAllowed($itemType)) {
			// Remove remote shares from type array, because it is not allowed.
			$shareTypes = array_diff($shareTypes, [Share::SHARE_TYPE_REMOTE]);
		}

		$shareWithGroupOnly = $this->config->getAppValue('core', 'shareapi_only_share_with_group_members', 'no') === 'yes';

		return $this->searchSharees($search, $itemType, $existingShares, $shareTypes, $page, $perPage, $shareWithGroupOnly);
	}

	/**
	 * Method to get out the static call for better testing
	 *
	 * @param string $itemType
	 * @return bool
	 */
	protected function isRemoteSharingAllowed($itemType) {
		try {
			$backend = Share::getBackend($itemType);
			return $backend->isShareTypeAllowed(Share::SHARE_TYPE_REMOTE);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Testable search function that does not need globals
	 *
	 * @param string $search
	 * @param string $itemType
	 * @param array $existingShares
	 * @param array $shareTypes
	 * @param int $page
	 * @param int $perPage
	 * @param bool $shareWithGroupOnly
	 * @return \OC_OCS_Result
	 */
	protected function searchSharees($search, $itemType, array $existingShares, array $shareTypes, $page, $perPage, $shareWithGroupOnly) {

		$sharedUsers = $sharedGroups = [];
		if (!empty($existingShares)) {
			if (!empty($existingShares[Share::SHARE_TYPE_USER]) &&
				is_array($existingShares[Share::SHARE_TYPE_USER])) {
				$sharedUsers = $existingShares[Share::SHARE_TYPE_USER];
			}

			if (!empty($existingShares[Share::SHARE_TYPE_GROUP]) &&
				is_array($existingShares[Share::SHARE_TYPE_GROUP])) {
				$sharedGroups = $existingShares[Share::SHARE_TYPE_GROUP];
			}
		}

		// Verify arguments
		if ($itemType === null) {
			return new \OC_OCS_Result(null, 400, 'missing itemType');
		}

		$sharees = [];
		// Get users
		if (in_array(Share::SHARE_TYPE_USER, $shareTypes)) {
			$potentialSharees = $this->getUsers($search, $shareWithGroupOnly);
			$sharees = array_merge($sharees, $this->filterSharees($potentialSharees, $sharedUsers));
		}

		// Get groups
		if (in_array(Share::SHARE_TYPE_GROUP, $shareTypes)) {
			$potentialSharees = $this->getGroups($search, $shareWithGroupOnly);
			$sharees = array_merge($sharees, $this->filterSharees($potentialSharees, $sharedGroups));
		}

		// Get remote
		if (in_array(Share::SHARE_TYPE_REMOTE, $shareTypes)) {
			$sharees = array_merge($sharees, $this->getRemote($search));
		}


		// Sort sharees
		$sorter = new SearchResultSorter($search, 'label', $this->logger);
		usort($sharees, array($sorter, 'sort'));

		//Pagination
		$start = ($page - 1) * $perPage;
		$total = sizeof($sharees);

		$sharees = array_slice($sharees, $start, $perPage);

		$response = new \OC_OCS_Result($sharees);
		$response->setTotalItems($total);
		$response->setItemsPerPage($perPage);

		$links = $this->getPaginationLinks($page, $total, [
			'search' => $search,
			'itemType' => $itemType,
			'existingShares' => $existingShares,
			'shareType' => $shareTypes,
			'limit' => $perPage,
		]);

		if (!empty($links)) {
			$response->addHeader('Link', implode(', ', $links));
		}

		return $response;
	}

	/**
	 * Filter out already existing shares from a list of potential sharees
	 *
	 * @param array $potentialSharees
	 * @param array $existingSharees
	 * @return array
	 */
	protected function filterSharees($potentialSharees, $existingSharees) {
		$sharees = array_filter($potentialSharees, function ($sharee) use ($existingSharees) {
			return in_array($sharee['value']['shareWith'], $existingSharees) ? null : $sharee;
		});

		return $sharees;
	}

	/**
	 * Generates a bunch of pagination links for the current page
	 *
	 * @param int $page Current page
	 * @param int $total Number of total items that need to be paginated
	 * @param array $params Parameters for the URL
	 * @return array
	 */
	protected function getPaginationLinks($page, $total, array $params) {
		$url = $this->urlGenerator->getAbsoluteURL('/ocs/v1.php/apps/files_sharing/api/v1/sharees') . '?';

		$links = [];
		if ($page > 1) {
			$params['page'] = 1;
			$links[] = '<' . $url . http_build_query($params) . '>; rel="first"';
			$params['page'] = $page - 1;
			$links[] = '<' . $url . http_build_query($params) . '>; rel="prev"';
		}
		if ($page * $params['limit'] < $total) {
			$params['page'] = $page + 1;
			$links[] = '<' . $url . http_build_query($params) . '>; rel="next"';
			$params['page'] = ceil($total / $params['limit']);
			$links[] = '<' . $url . http_build_query($params) . '>; rel="last"';
		}
		return $links;
	}
}

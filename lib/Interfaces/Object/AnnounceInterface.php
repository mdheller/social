<?php
declare(strict_types=1);


/**
 * Nextcloud - Social Support
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Social\Interfaces\Object;


use daita\MySmallPhpTools\Exceptions\CacheItemNotFoundException;
use daita\MySmallPhpTools\Traits\TArrayTools;
use Exception;
use OCA\Social\Db\StreamRequest;
use OCA\Social\Exceptions\InvalidOriginException;
use OCA\Social\Exceptions\ItemNotFoundException;
use OCA\Social\Exceptions\ItemUnknownException;
use OCA\Social\Exceptions\SocialAppConfigException;
use OCA\Social\Exceptions\StreamNotFoundException;
use OCA\Social\Interfaces\IActivityPubInterface;
use OCA\Social\Model\ActivityPub\ACore;
use OCA\Social\Model\ActivityPub\Activity\Undo;
use OCA\Social\Model\ActivityPub\Object\Announce;
use OCA\Social\Model\ActivityPub\Stream;
use OCA\Social\Model\StreamQueue;
use OCA\Social\Service\CacheActorService;
use OCA\Social\Service\MiscService;
use OCA\Social\Service\StreamQueueService;


/**
 * Class AnnounceInterface
 *
 * @package OCA\Social\Interfaces\Object
 */
class AnnounceInterface implements IActivityPubInterface {


	use TArrayTools;


	/** @var StreamRequest */
	private $streamRequest;

	/** @var StreamQueueService */
	private $streamQueueService;

	/** @var CacheActorService */
	private $cacheActorService;

	/** @var MiscService */
	private $miscService;


	/**
	 * AnnounceInterface constructor.
	 *
	 * @param StreamRequest $streamRequest
	 * @param StreamQueueService $streamQueueService
	 * @param CacheActorService $cacheActorService
	 * @param MiscService $miscService
	 */
	public function __construct(
		StreamRequest $streamRequest, StreamQueueService $streamQueueService,
		CacheActorService $cacheActorService, MiscService $miscService
	) {
		$this->streamRequest = $streamRequest;
		$this->streamQueueService = $streamQueueService;
		$this->cacheActorService = $cacheActorService;
		$this->miscService = $miscService;
	}


	/**
	 * @param ACore $activity
	 * @param ACore $item
	 *
	 * @throws InvalidOriginException
	 */
	public function activity(Acore $activity, ACore $item) {
		$item->checkOrigin($activity->getId());

		if ($activity->getType() === Undo::TYPE) {
			$item->checkOrigin($item->getId());
			$this->delete($item);
		}
	}


	/**
	 * @param ACore $item
	 *
	 * @throws InvalidOriginException
	 * @throws Exception
	 */
	public function processIncomingRequest(ACore $item) {
		/** @var Stream $item */
		$item->checkOrigin($item->getId());

		$this->save($item);
	}


	/**
	 * @param ACore $item
	 */
	public function processResult(ACore $item) {
	}


	/**
	 * @param string $id
	 *
	 * @return ACore
	 * @throws ItemNotFoundException
	 */
	public function getItemById(string $id): ACore {
		throw new ItemNotFoundException();
	}


	/**
	 * @param ACore $item
	 *
	 * @throws Exception
	 */
	public function save(ACore $item) {
		/** @var Announce $item */
		try {
			$knownItem =
				$this->streamRequest->getStreamByObjectId($item->getObjectId(), Announce::TYPE);

			if ($item->hasActor()) {
				$actor = $item->getActor();
			} else {
				$actor = $this->cacheActorService->getFromId($item->getActorId());
			}

			if (!$knownItem->hasCc($actor->getFollowers())) {
				$knownItem->addCc($actor->getFollowers());
				$this->streamRequest->update($knownItem);
			}
			
		} catch (StreamNotFoundException $e) {
			$objectId = $item->getObjectId();
			$item->addCacheItem($objectId);
			$this->streamRequest->save($item);

			$this->streamQueueService->generateStreamQueue(
				$item->getRequestToken(), StreamQueue::TYPE_CACHE, $item->getId()
			);
		}
	}


	/**
	 * @param ACore $item
	 */
	public function update(ACore $item) {
	}


	/**
	 * @param ACore $item
	 */
	public function delete(ACore $item) {
		try {
			$knownItem =
				$this->streamRequest->getStreamByObjectId($item->getObjectId(), Announce::TYPE);

			$actor = $item->getActor();
			$knownItem->removeCc($actor->getFollowers());

			if (empty($knownItem->getCcArray())) {
				$this->streamRequest->deleteStreamById($knownItem->getId(), Announce::TYPE);
			} else {
				$this->streamRequest->update($knownItem);
			}
		} catch (StreamNotFoundException $e) {
		} catch (ItemUnknownException $e) {
		} catch (SocialAppConfigException $e) {
		}
	}


	/**
	 * @param ACore $item
	 * @param string $source
	 */
	public function event(ACore $item, string $source) {
		/** @var Stream $item */
		switch ($source) {
			case 'updateCache':
				$objectId = $item->getObjectId();
				try {
					$cachedItem = $item->getCache()
									   ->getItem($objectId);
				} catch (CacheItemNotFoundException $e) {
					return;
				}

				$to = $this->get('attributedTo', $cachedItem->getObject(), '');
				if ($to !== '') {
					$this->streamRequest->updateAttributedTo($item->getId(), $to);
				}

				break;
		}
	}

}


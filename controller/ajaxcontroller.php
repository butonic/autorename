<?php
/**
 * ownCloud - autorename
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING.agpl-v3 file.
 *
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @copyright Jörn Friedrich Dreyer 2016
 */

namespace OCA\AutoRename\Controller;

use OCA\AutoRename\Renamer;
use OCP\AppFramework\Controller;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserManager;
use OCP\IUserSession;

class AjaxController extends Controller {

	/**
	 * @var Folder
	 */
	private $userFolder;

	/**
	 * @var Renamer
	 */
	private $renamer;

	public function __construct($appName, IRequest $request, $UserId, IRootFolder $root, Renamer $renamer) {
		parent::__construct($appName, $request);
		$this->userFolder = $root->getUserFolder($UserId);
		$this->renamer = $renamer;
	}
	/**
	 * @NoAdminRequired
	 * rename a file or all files inside a folder by its metadata
	 *
	 * @param int $fileId
	 * @return JSONResponse
	 */
	public function renameByMetaTime($fileId) {
		$sourceNode = $this->userFolder->getById($fileId)[0];
		$targetFolder = $this->userFolder->get('target');
		if ($sourceNode instanceof Folder) {
			$this->renamer->autorenameFolder($sourceNode, $targetFolder);
		} else if($sourceNode instanceof File) {
			$this->renamer->autorenameFile($sourceNode, $targetFolder);
		}
		return new JSONResponse([]);
	}
}

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

namespace OCA\AutoRename\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\AutoRename\Command\Autorename;
use OCP\Command\ICommand;

/**
 * Wrap the autorename command in the background job interface
 */
class AutorenameJob extends TimedJob  {
	protected function run($argument) {
		$command = new Autorename(\OC::$server->getUserManager());
		//$command->
	}
}

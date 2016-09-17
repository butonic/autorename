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

use \OCA\AutoRename\Command\Show;

$renamer = new \OCA\AutoRename\Renamer();

/** @var Symfony\Component\Console\Application $application */
$application->add(new Show(\OC::$server->getUserManager(), $renamer));
$application->add(new \OCA\AutoRename\Command\Autorename(\OC::$server->getUserManager(), $renamer));

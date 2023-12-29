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

namespace OCA\AutoRename\Command;

use OC\User\Manager;
use OCA\AutoRename\Renamer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Autorename extends Command {

	/**
	 * @var \OC\User\Manager $userManager
	 */
	private $userManager;

	/**
	 * @var Renamer $renamer
	 */
	private $renamer;

	public function __construct(Manager $userManager, Renamer $renamer) {
		$this->userManager = $userManager;
		$this->renamer = $renamer;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('autorename:autorename')
			->setDescription('autorename all files in configured source directories (based on getid3)')
			->addArgument(
				'userid',
				InputArgument::OPTIONAL,
				'limit to the given user'
			)
			->addArgument(
				'source',
				InputArgument::OPTIONAL,
				'rename files in this folder, eg. --source="/Images/IMG_123.jpg"'
			)
			->addArgument(
				'target',
				InputArgument::OPTIONAL,
				'rename files to folder eg. --target="/Images/IMG_123.jpg"'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Do everything except actually rename files.'
			);
	}




	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int  0 if everything went fine
	 */
	public function execute(InputInterface $input, OutputInterface $output) {

		$userIds = $input->getArgument('userid');
		if (!is_array($userIds)) {
			$userIds = [$userIds];
		}

		$source = $input->getArgument('source');
		$target = $input->getArgument('target');
		$dryRun = $input->getOption('dry-run');
		foreach ($userIds as $userId) {
			if ($this->userManager->userExists($userId)) {
				// TODO check if user logged in
				$home = \OC::$server->getUserFolder($userId);
				if (empty($source)) {
					$sourceFolder = $home;
				} else {
					$sourceFolder = $home->get($source);
				}
				if (empty($target)) {
					$targetFolder = $sourceFolder;
				} else {
					$targetFolder = $home->get($target);
				}
				$this->renamer->autorenameFolder($sourceFolder, $targetFolder, $dryRun, $output);
			} else {
				$output->writeln("<error>Unknown user $userId</error>");
				return 1;
			}
		}
		return 0;
	}

}

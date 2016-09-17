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

use OC\Files\Node\File;
use \OC\User\Manager;
use OCA\AutoRename\Renamer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Show extends Command {

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
			->setName('autorename:show')
			->setDescription('Show file metadata (based on getid3)')
			->addArgument(
				'user_id',
				InputArgument::REQUIRED
			)
			->addArgument(
				'path',
				InputArgument::REQUIRED,
				'show this file, eg. --path="/Images/IMG_123.jpg"'
			);
	}


	protected function showMetadata($userId, $path, OutputInterface $output) {
		$home = \OC::$server->getUserFolder($userId);
		$file = $home->get($path);
		if ($file instanceof File) {
			$output->writeln("<info>".json_encode($file->getFileInfo())."</info>");

			// getid3 needs a local file
			$tmp = \OC::$server->getTempManager()->getTemporaryFile();
			$h = fopen($tmp, 'w+');
			//stream_copy_to_stream($file->fopen('r'), $h, 8192*3); // increase if no timestamp found?
			stream_copy_to_stream($file->fopen('r'), $h); // increase if no timestamp found?
			fclose($h);

			$output->writeln("<info>getID3</info>");
			$getID3 = new \getID3();
			$tags = $getID3->analyze($tmp);
			//\getid3_lib::CopyTagsToComments($tags);
			$output->writeln("<info>".print_r($tags, true)."</info>");
			$time = null;
			// use heuristic to collect timestamps:
			// 1. convert to 2016-06-19T19:19:10.880Z (where do we find the timezone ? otherwise assume server timezone? owner timezone? config option?)
			// 2. increase score for every datetime
			// 3. pick item with best score
			//l
			if (isset($tags['xmp']['xmp']['CreateDate'])) {
				$time = $tags['xmp']['xmp']['CreateDate']; // 2016-06-19T19:19:10.88
			} else if (isset($tags['xmp']['photoshop']['DateCreated'])) {
				$time = $tags['xmp']['photoshop']['DateCreated']; // 2016-06-19T19:19:10.88
			} else if (isset($tags['jpg']['exif']['EXIF']['DateTimeOriginal'])) {
				$time = $tags['jpg']['exif']['EXIF']['DateTimeOriginal']; // 2016:06:19 19:19:10
				$time = preg_replace('/(\d\d\d\d):(\d\d):(\d\d) (\d\d):(\d\d):(\d\d)/','$1-$2-$3T$4:$5:$6', $time);
				if (isset($tags['jpg']['exif']['EXIF']['SubSecTimeOriginal'])) {
					$time .= '.'.$tags['jpg']['exif']['EXIF']['SubSecTimeOriginal']; // 88
				}
			} else if (isset($tags['quicktime']['moov']['subatoms'])) {
				foreach ($tags['quicktime']['moov']['subatoms'] as $subatom) {
					if (isset($subatom['creation_time_unix'])) {
						$time = $subatom['creation_time_unix']; // 1472851226
					} else if (isset($subatom['modify_time_unix'])) {
						$time = $subatom['modify_time_unix']; // 1472851226
					}
					if ($time) {
						break;
					}
				}
			}
			$output->writeln("<info>found</info>");
			$output->writeln("<info>".$time."</info>");

			if (is_numeric($time)) {
				$parsedTime = \DateTime::createFromFormat('U', $time, new \DateTimeZone('Z'));
			} else {
				$parsedTime = new \DateTime($time, new \DateTimeZone('Z'));
			}
			$output->writeln("<info>parsed</info>");
			$output->writeln("<info>".$parsedTime->format("Ymd_His_")."</info>");

			$exif = exif_read_data($tmp, 'FILE', true);
			$output->writeln("<info>FILE</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");

			$exif = exif_read_data($tmp, 'COMPUTED', true);
			$output->writeln("<info>COMPUTED</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");

			$exif = exif_read_data($tmp, 'ANY_TAG', true);
			$output->writeln("<info>ANY_TAG</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");

			$exif = exif_read_data($tmp, 'IFD0', true);
			$output->writeln("<info>IFD0</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");

			$exif = exif_read_data($tmp, 'THUMBNAIL', true);
			$output->writeln("<info>THUMBNAIL</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");

			$exif = exif_read_data($tmp, 'COMMENT', true);
			$output->writeln("<info>COMMENT</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");

			$exif = exif_read_data($tmp, 'EXIF', true);
			$output->writeln("<info>EXIF</info>");
			$output->writeln("<info>".json_encode($exif)."</info>");
		} else {
			$output->writeln("<error>$path is not a file</error>");
		}
	}
	
	public function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user_id');
		$path = $input->getArgument('path');
		if ($this->userManager->userExists($userId)) {
			$this->showMetadata($userId, $path, $output);
		} else {
			$output->writeln("<error>Unknown user $userId</error>");
		}
	}

}

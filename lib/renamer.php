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

namespace OCA\AutoRename;

use OCP\Files\File;
use OCP\Files\Folder;
use Symfony\Component\Console\Output\OutputInterface;

class Renamer {

	public function isAnalyzable(File $node) {
		$ext = strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
		if (in_array($ext, ['jpg', 'jpeg', 'mp4', 'mov', 'mpeg', 'ts'])) {
			return true;
		}
		return false;
	}

	public function extractRawTimestampFromMetadata(File $file) {
		// getid3 needs a local file
		$tmp = \OC::$server->getTempManager()->getTemporaryFile();
		$h = fopen($tmp, 'w+');
		//stream_copy_to_stream($file->fopen('r'), $h, 8192*3); // increase if no timestamp found?
		stream_copy_to_stream($file->fopen('r'), $h);
		fclose($h);

		$getID3 = new \getID3();
		$tags = $getID3->analyze($tmp);
		//\getid3_lib::CopyTagsToComments($tags);
		$time = null;
		// TODO use heuristic to collect timestamps:
		// 1. convert to 2016-06-19T19:19:10.880Z (where do we find the timezone ? otherwise assume server timezone? owner timezone? config option?)
		// 2. increase score for every datetime
		// 3. pick item with best score
		//
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
		if (empty($time)) {
			throw new \Exception('Time not found in '.print_r($tags, true));
		}
		return $time;
	}

	public function parseDate($time) {
		if (is_numeric($time)) {
			return \DateTime::createFromFormat('U', $time, new \DateTimeZone('Z'));
		} else {
			return new \DateTime($time, new \DateTimeZone('Z'));
		}
	}

	public function autorenameFile(File $sourceFile, Folder $targetFolder, $dryRun = false, OutputInterface $output = null) {
		if ($this->isAnalyzable($sourceFile)) {
			/** @var File $node */
			try {
				$rawTime = $this->extractRawTimestampFromMetadata($sourceFile);
			} catch (\Exception $e) {
				// TODO try regex on the filename, eg IMG_(yyyymmdd-hhmmss)
				if ($output) {
					$output->writeln("<warn>{$sourceFile->getPath()}: {$e->getMessage()}</warn>");
				}
				return false;
			}
			// try parsing with datetime
			$dateTime = $this->parseDate($rawTime);
			if ($dateTime) {
				// build new filename
				$newName = $dateTime->format('Ymd_His_').$sourceFile->getName();
				$subFolder = $dateTime->format('/Y/Y-m/');
				// TODO mkdir -p
				$newPath = $targetFolder->getPath().$subFolder.$newName;
				// write new name to output
				if ($output) {
					$output->writeln("<info>moving {$sourceFile->getPath()} to $newPath</info>");
				}
				// TODO if target exists don't overwrite but append number
				if ($dryRun === false) {
					$sourceFile->move($newPath);
				}
			} else {
				if ($output) {
					$output->writeln("<error>{$sourceFile->getPath()}: could not create DateTime from $rawTime, skipping</error>");
				}
			}

		} else if ($output) {
			$output->writeln("<debug>skipping {$sourceFile->getPath()}</debug>");
		}
		return true;
	}

	public function autorenameFolder(Folder $sourceFolder, Folder $targetFolder, $dryRun = false, OutputInterface $output = null) {
		foreach ($sourceFolder->getDirectoryListing() as $node) {
			if ($node instanceof File) {
				$this->autorenameFile($node, $targetFolder, $dryRun, $output);
			} else if ($node instanceof Folder) {
				$this->autorenameFolder($node, $targetFolder, $dryRun, $output);
			} else if ($output) {
				$output->writeln("<warn>{$node->getPath()} is neither file nor folder, ignoring</warn>");
			}
		}
	}

}
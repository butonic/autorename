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
		if (in_array($ext, ['jpg', 'jpeg', 'mp4', 'mov', 'mpeg', 'ts', '3gp'])) {
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

		//print_r($tags);
		//\getid3_lib::CopyTagsToComments($tags);
		$time = null;
		// TODO use heuristic to collect timestamps:
		// 1. convert to 2016-06-19T19:19:10.880Z (where do we find the timezone ? otherwise assume server timezone? owner timezone? config option?)
		// 2. increase score for every datetime
		// 3. pick item with best score
		//
		if (isset($tags['xmp']['xmp']['CreateDate'])) {
			$time = $tags['xmp']['xmp']['CreateDate']; // 2016-06-19T19:19:10.88
			if (!empty($time)) {
				return $time;
			}
		} 
		if (isset($tags['xmp']['photoshop']['DateCreated'])) {
			$time = $tags['xmp']['photoshop']['DateCreated']; // 2016-06-19T19:19:10.88
			if (!empty($time)) {
				return $time;
			}
		}
		if (isset($tags['jpg']['exif']['EXIF']['DateTimeOriginal'])) {
			$time = $tags['jpg']['exif']['EXIF']['DateTimeOriginal']; // 2016:06:19 19:19:10
			$time = preg_replace('/(\d\d\d\d):(\d\d):(\d\d) (\d\d):(\d\d):(\d\d)/','$1-$2-$3T$4:$5:$6', $time);
			if (isset($tags['jpg']['exif']['EXIF']['SubSecTimeOriginal'])) {
				$time .= '.'.$tags['jpg']['exif']['EXIF']['SubSecTimeOriginal']; // 88
			}
			if (!empty($time)) {
				return $time;
			}
		}
		if (isset($tags['quicktime']['timestamps_unix']['create']['moov mvhd'])) {
			$time = $tags['quicktime']['timestamps_unix']['create']['moov mvhd']; // 1676208759
			if ($time > -1) {
				return $time;
			}
		} 
		if (isset($tags['quicktime']['timestamps_unix']['create']['moov trak tkhd'])) {
			$time = $tags['quicktime']['timestamps_unix']['create']['moov trak tkhd']; // 1676208759
			if ($time > -1) {
				return $time;
			}
		} 
		if (isset($tags['quicktime']['timestamps_unix']['create']['moov trak mdia mdhd'])) {
			$time = $tags['quicktime']['timestamps_unix']['create']['moov trak mdia mdhd']; // 1676208759
			if ($time > -1) {
				return $time;
			}
		} 
		if (isset($tags['quicktime']['timestamps_unix']['modify']['moov mvhd'])) {
			$time = $tags['quicktime']['timestamps_unix']['modify']['moov mvhd']; // 1676208759
			if ($time > -1) {
				return $time;
			}
		} 
		if (isset($tags['quicktime']['timestamps_unix']['modify']['moov trak tkhd'])) {
			$time = $tags['quicktime']['timestamps_unix']['modify']['moov trak tkhd']; // 1676208759
			if ($time > -1) {
				return $time;
			}
		} 
		if (isset($tags['quicktime']['timestamps_unix']['modify']['moov trak mdia mdhd'])) {
			$time = $tags['quicktime']['timestamps_unix']['modify']['moov trak mdia mdhd']; // 1676208759
			if ($time > -1) {
				return $time;
			}
		} 
		if (isset($tags['quicktime']['moov']['subatoms'])) {
			foreach ($tags['quicktime']['moov']['subatoms'] as $subatom) {
				if (isset($subatom['creation_time_unix'])) {
					$time = $subatom['creation_time_unix']; // 1472851226
				} else if (isset($subatom['modify_time_unix'])) {
					$time = $subatom['modify_time_unix']; // 1472851226
				}
				if ($time > -1) {
					return $time;
				}
			}
		}
		throw new \Exception('Time not found in '.print_r($tags, true));
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
				$output->writeln("<info>trying metadata</info>");
				$rawTime = $this->extractRawTimestampFromMetadata($sourceFile);
				$output->writeln("<info>got $rawTime</info>");
				// try parsing with datetime
				$dateTime = $this->parseDate($rawTime);
			} catch (\Exception $e) {
				// fall back to parsing the filename
				$dateTime = false;
			}
			
			if ($dateTime === false) {
				$output->writeln("<info>trying as whatsapp</info>");
				// Whatsapp images: IMG-20220930-WA0014.jpg we replace the WE with 12 so the images get ordered roughly at 12h
				$rawTime = preg_replace("/(IMG|VID)[-_](\d{8})[-_]WA(\d\d)(\d)(\d)( .\d.)?\.(jpg|mp4)/", "$2 $3:0$4:0$5", $sourceFile->getName());
				if ($rawTime !== $sourceFile->getName()) {
					if ($output) {
						$output->writeln("<info>detected whatsapp image/video {$sourceFile->getName()}, trying to parse $rawTime as 'Ymd H:i:s'</info>");
					}
					$dateTime = \DateTimeImmutable::createFromFormat('Ymd H:i:s', $rawTime);
					if ($dateTime === false && $output) {
						$output->writeln("<error>failed to parse $rawTime as whatsapp image</error>");
					}
				}
			}
			if ($dateTime === false) {
				$output->writeln("<info>trying unix timestamp</info>");
				// unix timestamp, eg. 1652562090760.jpg
				$rawTime = preg_replace("/(\d{10})(\d{3})\..*/", "$1.$2", $sourceFile->getName());
				if ($rawTime !== $sourceFile->getName()) {
					if ($output) {
						$output->writeln("<info>detected unixtime filename {$sourceFile->getName()}, trying to parse $rawTime as 'U.u'</info>");
					}
					$dateTime = \DateTimeImmutable::createFromFormat('U.u', $rawTime);
				}
			}

			if ($dateTime === false) {
				// other crap
				$rawTime = preg_replace("/.*(IMG|VID)[_-](\d{8})[_-](\d{6})( .\d.)?\..*/", "$2 $3", $sourceFile->getName());
				if ($rawTime !== $sourceFile->getName()) {
					if ($output) {
						$output->writeln("<info>detected IMG/VID filename {$sourceFile->getName()}, trying to parse $rawTime as 'Ymd His'</info>");
					}
					$dateTime = \DateTimeImmutable::createFromFormat('Ymd His', $rawTime);
				}
			}

			if ($dateTime === false) {
				// other crap
				$rawTime = preg_replace("/.*image-(\d{8})-(\d{6}).*/", "$1 $2", $sourceFile->getName());
				if ($rawTime !== $sourceFile->getName()) {
					if ($output) {
						$output->writeln("<info>detected image filename {$sourceFile->getName()}, trying to parse $rawTime as 'Ymd His'</info>");
					}
					$dateTime = \DateTimeImmutable::createFromFormat('Ymd His', $rawTime);
				}
			}

			if ($dateTime === false) {
				if ($output) {
					$output->writeln("<error>{$sourceFile->getPath()}: could not create DateTime from $rawTime, skipping</error>");
				}
			} else {
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

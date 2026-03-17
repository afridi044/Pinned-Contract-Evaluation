<?php

declare(strict_types=1);

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
///                                                            //
// module.tag.lyrics3.php                                      //
// module for analyzing Lyrics3 tags                           //
// dependencies: module.tag.apetag.php (optional)              //
//                                                            ///
/////////////////////////////////////////////////////////////////


class getid3_lyrics3 extends getid3_handler
{

	public function Analyze(): bool
    {
		$info = &$this->getid3->info;

		// http://www.volweb.cz/str/tags.htm

		if (!getid3_lib::intValueSupported($info['filesize'])) {
			$info['warning'][] = 'Unable to check for Lyrics3 because file is larger than '.round(PHP_INT_MAX / 1073741824).'GB';
			return false;
		}

		fseek($this->getid3->fp, -(128 + 9 + 6), SEEK_END);          // end - ID3v1 - "LYRICSEND" - [Lyrics3size]
		$lyrics3_id3v1 = fread($this->getid3->fp, 128 + 9 + 6);
		$lyrics3lsz    = substr($lyrics3_id3v1,  0,   6); // Lyrics3size
		$lyrics3end    = substr($lyrics3_id3v1,  6,   9); // LYRICSEND or LYRICS200
		$id3v1tag      = substr($lyrics3_id3v1, 15, 128); // ID3v1

		$lyrics3offset = null; // Initialize to null
		$lyrics3size = 0;      // Initialize
		$lyrics3version = 0;   // Initialize

		if ($lyrics3end === 'LYRICSEND') {
			// Lyrics3v1, ID3v1, no APE
			$lyrics3size    = 5100;
			$lyrics3offset  = $info['filesize'] - 128 - $lyrics3size;
			$lyrics3version = 1;
		} elseif ($lyrics3end === 'LYRICS200') {
			// Lyrics3v2, ID3v1, no APE
			// LSZ = lyrics + 'LYRICSBEGIN'; add 6-byte size field; add 'LYRICS200'
			$lyrics3size    = (int) $lyrics3lsz + 6 + strlen('LYRICS200');
			$lyrics3offset  = $info['filesize'] - 128 - $lyrics3size;
			$lyrics3version = 2;
		} elseif (str_ends_with($lyrics3_id3v1, 'LYRICSEND')) { // Use str_ends_with (PHP 8+)
			// Lyrics3v1, no ID3v1, no APE
			$lyrics3size    = 5100;
			$lyrics3offset  = $info['filesize'] - $lyrics3size;
			$lyrics3version = 1;
			// Original code had $lyrics3offset = $info['filesize'] - $lyrics3size; twice. Removed redundant line.
		} elseif (str_ends_with($lyrics3_id3v1, 'LYRICS200')) { // Use str_ends_with (PHP 8+)
			// Lyrics3v2, no ID3v1, no APE
			$lyrics3size    = (int) substr($lyrics3_id3v1, -15, 6) + 6 + strlen('LYRICS200'); // LSZ = lyrics + 'LYRICSBEGIN'; add 6-byte size field; add 'LYRICS200'
			$lyrics3offset  = $info['filesize'] - $lyrics3size;
			$lyrics3version = 2;
		} else {
			if (isset($info['ape']['tag_offset_start']) && ($info['ape']['tag_offset_start'] > 15)) {
				fseek($this->getid3->fp, $info['ape']['tag_offset_start'] - 15, SEEK_SET);
				$lyrics3lsz = fread($this->getid3->fp, 6);
				$lyrics3end = fread($this->getid3->fp, 9);

				if ($lyrics3end === 'LYRICSEND') {
					// Lyrics3v1, APE, maybe ID3v1
					$lyrics3size    = 5100;
					$lyrics3offset  = $info['ape']['tag_offset_start'] - $lyrics3size;
					$info['avdataend'] = $lyrics3offset;
					$lyrics3version = 1;
					$info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatability';
				} elseif ($lyrics3end === 'LYRICS200') {
					// Lyrics3v2, APE, maybe ID3v1
					$lyrics3size    = (int) $lyrics3lsz + 6 + strlen('LYRICS200'); // LSZ = lyrics + 'LYRICSBEGIN'; add 6-byte size field; add 'LYRICS200'
					$lyrics3offset  = $info['ape']['tag_offset_start'] - $lyrics3size;
					$lyrics3version = 2;
					$info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatability';
				}
			}
		}

		if ($lyrics3offset !== null) { // Check for null as it's initialized to null
			$info['avdataend'] = $lyrics3offset;
			$this->getLyrics3Data($lyrics3offset, $lyrics3version, $lyrics3size);

			if (!isset($info['ape'])) {
				$GETID3_ERRORARRAY = &$info['warning']; // Keep reference for functional equivalence with getID3 library
				if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.apetag.php', __FILE__, false)) {
					$getid3_temp = new getID3();
					$getid3_temp->openfile($this->getid3->filename);
					$getid3_apetag = new getid3_apetag($getid3_temp);
					$getid3_apetag->overrideendoffset = $info['lyrics3']['tag_offset_start'];
					$getid3_apetag->Analyze();
					if (!empty($getid3_temp->info['ape'])) {
						$info['ape'] = $getid3_temp->info['ape'];
					}
					if (!empty($getid3_temp->info['replay_gain'])) {
						$info['replay_gain'] = $getid3_temp->info['replay_gain'];
					}
					unset($getid3_temp, $getid3_apetag);
				}
			}
		}

		return true;
	}

	public function getLyrics3Data(int $endoffset, int $version, int $length): bool
    {
		$info = &$this->getid3->info; // Keep reference for functional equivalence with getID3 library

		if (!getid3_lib::intValueSupported($endoffset)) {
			$info['warning'][] = 'Unable to check for Lyrics3 because file is larger than '.round(PHP_INT_MAX / 1073741824).'GB';
			return false;
		}

		fseek($this->getid3->fp, $endoffset, SEEK_SET);
		if ($length <= 0) {
			return false;
		}
		$rawdata = fread($this->getid3->fp, $length);

		$ParsedLyrics3 = []; // Initialize array
		$ParsedLyrics3['raw']['lyrics3version'] = $version;
		$ParsedLyrics3['raw']['lyrics3tagsize'] = $length;
		$ParsedLyrics3['tag_offset_start']      = $endoffset;
		$ParsedLyrics3['tag_offset_end']        = $endoffset + $length - 1;

		if (!str_starts_with($rawdata, 'LYRICSBEGIN')) { // Use str_starts_with (PHP 8+)
			$lyricsBeginPos = strpos($rawdata, 'LYRICSBEGIN');
			if ($lyricsBeginPos !== false) {
				$info['warning'][] = '"LYRICSBEGIN" expected at '.$endoffset.' but actually found at '.($endoffset + $lyricsBeginPos).' - this is invalid for Lyrics3 v'.$version;
				$info['avdataend'] = $endoffset + $lyricsBeginPos;
				$rawdata = substr($rawdata, $lyricsBeginPos);
				$length = strlen($rawdata);
				$ParsedLyrics3['tag_offset_start'] = $info['avdataend'];
				$ParsedLyrics3['raw']['lyrics3tagsize'] = $length;
			} else {
				$info['error'][] = '"LYRICSBEGIN" expected at '.$endoffset.' but found "'.substr($rawdata, 0, 11).'" instead';
				return false;
			}
		}

		switch ($version) {
			case 1:
				if (str_ends_with($rawdata, 'LYRICSEND')) { // Use str_ends_with (PHP 8+)
					$ParsedLyrics3['raw']['LYR'] = trim(substr($rawdata, 11, -9)); // Use negative length for substr
					$this->Lyrics3LyricsTimestampParse($ParsedLyrics3);
				} else {
					$info['error'][] = '"LYRICSEND" expected at '.(ftell($this->getid3->fp) - 11 + $length - 9).' but found "'.substr($rawdata, strlen($rawdata) - 9, 9).'" instead';
					return false;
				}
				break;

			case 2:
				if (str_ends_with($rawdata, 'LYRICS200')) { // Use str_ends_with (PHP 8+)
					// LYRICSBEGIN (11) + LYRICS200 (9) + LSZ (6) = 26 bytes to remove from total length
					$ParsedLyrics3['raw']['unparsed'] = substr($rawdata, 11, strlen($rawdata) - 11 - 9 - 6);
					$rawdata = $ParsedLyrics3['raw']['unparsed'];
					while (strlen($rawdata) > 0) {
						$fieldname = substr($rawdata, 0, 3);
						$fieldsize = (int) substr($rawdata, 3, 5); // Cast to int
						$ParsedLyrics3['raw'][$fieldname] = substr($rawdata, 8, $fieldsize);
						$rawdata = substr($rawdata, 3 + 5 + $fieldsize);
					}

					if (isset($ParsedLyrics3['raw']['IND'])) {
						$flagnames = ['lyrics', 'timestamps', 'inhibitrandom']; // Use short array syntax
						foreach ($flagnames as $i => $flagname) { // Use foreach with index for cleaner iteration
							if (isset($ParsedLyrics3['raw']['IND'][$i])) { // Check if character exists at index
								$ParsedLyrics3['flags'][$flagname] = $this->IntString2Bool($ParsedLyrics3['raw']['IND'][$i]); // Access character directly
							}
						}
					}

					$fieldnametranslation = ['ETT'=>'title', 'EAR'=>'artist', 'EAL'=>'album', 'INF'=>'comment', 'AUT'=>'author']; // Use short array syntax
					foreach ($fieldnametranslation as $key => $value) {
						if (isset($ParsedLyrics3['raw'][$key])) {
							$ParsedLyrics3['comments'][$value][] = trim($ParsedLyrics3['raw'][$key]);
						}
					}

					if (isset($ParsedLyrics3['raw']['IMG'])) {
						$imagestrings = explode("\r\n", $ParsedLyrics3['raw']['IMG']);
						foreach ($imagestrings as $key => $imagestring) {
							if (strpos($imagestring, '||') !== false) {
								$imagearray = explode('||', $imagestring);
								// Use null coalescing operator (PHP 7+) for safer array access
								$ParsedLyrics3['images'][$key]['filename']     = $imagearray[0] ?? '';
								$ParsedLyrics3['images'][$key]['description']  = $imagearray[1] ?? '';
								$ParsedLyrics3['images'][$key]['timestamp']    = $this->Lyrics3Timestamp2Seconds($imagearray[2] ?? '');
							}
						}
					}
					if (isset($ParsedLyrics3['raw']['LYR'])) {
						$this->Lyrics3LyricsTimestampParse($ParsedLyrics3);
					}
				} else {
					$info['error'][] = '"LYRICS200" expected at '.(ftell($this->getid3->fp) - 11 + $length - 9).' but found "'.substr($rawdata, strlen($rawdata) - 9, 9).'" instead';
					return false;
				}
				break;

			default:
				$info['error'][] = 'Cannot process Lyrics3 version '.$version.' (only v1 and v2)';
				return false;
		}


		if (isset($info['id3v1']['tag_offset_start']) && ($info['id3v1']['tag_offset_start'] <= $ParsedLyrics3['tag_offset_end'])) {
			$info['warning'][] = 'ID3v1 tag information ignored since it appears to be a false synch in Lyrics3 tag data';
			unset($info['id3v1']);
			foreach ($info['warning'] as $key => $value) {
				if ($value === 'Some ID3v1 fields do not use NULL characters for padding') { // Use strict comparison
					unset($info['warning'][$key]);
					sort($info['warning']); // Re-index array after unset
					break;
				}
			}
		}

		$info['lyrics3'] = $ParsedLyrics3;

		return true;
	}

	public function Lyrics3Timestamp2Seconds(string $rawtimestamp): int|false
    {
		if (preg_match('#^\\[([0-9]{2}):([0-9]{2})\\]$#', $rawtimestamp, $regs)) {
			return ((int) $regs[1] * 60) + (int) $regs[2]; // Cast to int for arithmetic
		}
		return false;
	}

	public function Lyrics3LyricsTimestampParse(array &$Lyrics3data): bool
    {
		$lyricsarray = explode("\r\n", $Lyrics3data['raw']['LYR']);
		$notimestamplyricsarray = []; // Initialize array
		foreach ($lyricsarray as $key => $lyricline) {
			$regs = []; // Initialize for each iteration
			unset($thislinetimestamps); // Unset for each line to prevent bleed-over
			while (preg_match('#^(\\[[0-9]{2}:[0-9]{2}\\])#', $lyricline, $regs)) {
				$thislinetimestamps[] = $this->Lyrics3Timestamp2Seconds($regs[0]);
				$lyricline = str_replace($regs[0], '', $lyricline);
			}
			$notimestamplyricsarray[$key] = $lyricline;
			if (isset($thislinetimestamps) && is_array($thislinetimestamps)) { // is_array check is technically redundant here
				sort($thislinetimestamps);
				foreach ($thislinetimestamps as $timestamp) { // Renamed $timestampkey to $timestamp for clarity as it's not used
					if (isset($Lyrics3data['synchedlyrics'][$timestamp])) {
						// timestamps only have a 1-second resolution, it's possible that multiple lines
						// could have the same timestamp, if so, append
						$Lyrics3data['synchedlyrics'][$timestamp] .= "\r\n".$lyricline;
					} else {
						$Lyrics3data['synchedlyrics'][$timestamp] = $lyricline;
					}
				}
			}
		}
		$Lyrics3data['unsynchedlyrics'] = implode("\r\n", $notimestamplyricsarray);
		if (isset($Lyrics3data['synchedlyrics']) && is_array($Lyrics3data['synchedlyrics'])) {
			ksort($Lyrics3data['synchedlyrics']);
		}
		return true;
	}

	public function IntString2Bool(string $char): ?bool
    {
		if ($char === '1') { // Use strict comparison
			return true;
		} elseif ($char === '0') { // Use strict comparison
			return false;
		}
		return null;
	}
}
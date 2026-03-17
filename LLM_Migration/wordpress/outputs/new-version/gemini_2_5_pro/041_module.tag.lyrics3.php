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
		// http://www.volweb.cz/str/tags.htm

		if (!getid3_lib::intValueSupported($this->getid3->info['filesize'])) {
			$this->getid3->info['warning'][] = 'Unable to check for Lyrics3 because file is larger than '.round(PHP_INT_MAX / 1_073_741_824).'GB';
			return false;
		}

		// Lyrics3 tags are appended to the end of the file, before an ID3v1 tag (if present).
		// A Lyrics3v2 tag is identified by a 9-byte "LYRICS200" footer, preceded by a 6-byte size field.
		// A Lyrics3v1 tag is identified by a 9-byte "LYRICSEND" footer, with a fixed size of 5100 bytes.
		$post_id3v1_offset = $this->getid3->info['filesize'] - 128;
		$lyrics3_footer_offset = $post_id3v1_offset - 15; // 15 = 9-byte signature + 6-byte size

		fseek($this->getid3->fp, $lyrics3_footer_offset);
		$footer_plus_id3v1 = fread($this->getid3->fp, 143); // 143 = 15 bytes for footer + 128 for ID3v1

		$lyrics3_size_str = substr($footer_plus_id3v1, 0, 6);
		$lyrics3_sig = substr($footer_plus_id3v1, 6, 9);

		$lyrics3size = null;
		$lyrics3offset = null;
		$lyrics3version = null;

		if ($lyrics3_sig === 'LYRICSEND') {
			// Lyrics3v1, ID3v1, no APE
			$lyrics3size = 5100;
			$lyrics3offset = $post_id3v1_offset - $lyrics3size;
			$lyrics3version = 1;
		} elseif ($lyrics3_sig === 'LYRICS200') {
			// Lyrics3v2, ID3v1, no APE
			$lyrics3size = (int) $lyrics3_size_str + 15; // 15 = 6-byte size field + 9-byte signature
			$lyrics3offset = $post_id3v1_offset - $lyrics3size;
			$lyrics3version = 2;
		} elseif (str_ends_with($footer_plus_id3v1, 'LYRICSEND')) {
			// Lyrics3v1, no ID3v1, no APE
			$lyrics3size = 5100;
			$lyrics3offset = $this->getid3->info['filesize'] - $lyrics3size;
			$lyrics3version = 1;
		} elseif (str_ends_with($footer_plus_id3v1, 'LYRICS200')) {
			// Lyrics3v2, no ID3v1, no APE
			$sizeInTag = (int) substr($footer_plus_id3v1, -15, 6);
			$lyrics3size = $sizeInTag + 15; // 15 = 6-byte size field + 9-byte signature
			$lyrics3offset = $this->getid3->info['filesize'] - $lyrics3size;
			$lyrics3version = 2;
		} elseif (isset($this->getid3->info['ape']['tag_offset_start']) && ($this->getid3->info['ape']['tag_offset_start'] > 15)) {
			// Check for Lyrics3 tag before an APE tag
			fseek($this->getid3->fp, $this->getid3->info['ape']['tag_offset_start'] - 15);
			$lyrics3_size_str = fread($this->getid3->fp, 6);
			$lyrics3_sig = fread($this->getid3->fp, 9);

			if ($lyrics3_sig === 'LYRICSEND') {
				// Lyrics3v1, APE, maybe ID3v1
				$lyrics3size = 5100;
				$lyrics3offset = $this->getid3->info['ape']['tag_offset_start'] - $lyrics3size;
				$lyrics3version = 1;
				$this->getid3->info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatability';
			} elseif ($lyrics3_sig === 'LYRICS200') {
				// Lyrics3v2, APE, maybe ID3v1
				$lyrics3size = (int) $lyrics3_size_str + 15;
				$lyrics3offset = $this->getid3->info['ape']['tag_offset_start'] - $lyrics3size;
				$lyrics3version = 2;
				$this->getid3->info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatability';
			}
		}

		if (isset($lyrics3offset, $lyrics3version, $lyrics3size)) {
			$this->getid3->info['avdataend'] = $lyrics3offset;
			$this->getLyrics3Data($lyrics3offset, $lyrics3version, $lyrics3size);

			// Re-scan for APE tag if it was not found before, as it could be located before the Lyrics3 tag
			if (!isset($this->getid3->info['ape'])) {
				if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.apetag.php', __FILE__, false)) {
					$getid3_temp = new getID3();
					$getid3_temp->openfile($this->getid3->filename);
					$getid3_apetag = new getid3_apetag($getid3_temp);
					$getid3_apetag->overrideendoffset = $this->getid3->info['lyrics3']['tag_offset_start'];
					$getid3_apetag->Analyze();
					if (!empty($getid3_temp->info['ape'])) {
						$this->getid3->info['ape'] = $getid3_temp->info['ape'];
					}
					if (!empty($getid3_temp->info['replay_gain'])) {
						$this->getid3->info['replay_gain'] = $getid3_temp->info['replay_gain'];
					}
					unset($getid3_temp, $getid3_apetag);
				}
			}
		}

		return true;
	}

	public function getLyrics3Data(int $endoffset, int $version, int $length): bool
	{
		// http://www.volweb.cz/str/tags.htm

		if (!getid3_lib::intValueSupported($endoffset)) {
			$this->getid3->info['warning'][] = 'Unable to check for Lyrics3 because file is larger than '.round(PHP_INT_MAX / 1_073_741_824).'GB';
			return false;
		}

		fseek($this->getid3->fp, $endoffset, SEEK_SET);
		if ($length <= 0) {
			return false;
		}
		$rawdata = fread($this->getid3->fp, $length);

		$ParsedLyrics3 = [
			'raw' => [
				'lyrics3version' => $version,
				'lyrics3tagsize' => $length,
			],
			'tag_offset_start' => $endoffset,
			'tag_offset_end' => $endoffset + $length - 1,
		];

		if (!str_starts_with($rawdata, 'LYRICSBEGIN')) {
			$lyricsBeginPos = strpos($rawdata, 'LYRICSBEGIN');
			if ($lyricsBeginPos !== false) {
				$this->getid3->info['warning'][] = '"LYRICSBEGIN" expected at '.$endoffset.' but actually found at '.($endoffset + $lyricsBeginPos).' - this is invalid for Lyrics3 v'.$version;
				$this->getid3->info['avdataend'] = $endoffset + $lyricsBeginPos;
				$rawdata = substr($rawdata, $lyricsBeginPos);
				$length = strlen($rawdata);
				$ParsedLyrics3['tag_offset_start'] = $this->getid3->info['avdataend'];
				$ParsedLyrics3['raw']['lyrics3tagsize'] = $length;
			} else {
				$this->getid3->info['error'][] = '"LYRICSBEGIN" expected at '.$endoffset.' but found "'.substr($rawdata, 0, 11).'" instead';
				return false;
			}
		}

		switch ($version) {
			case 1:
				if (!str_ends_with($rawdata, 'LYRICSEND')) {
					$this->getid3->info['error'][] = '"LYRICSEND" expected at '.($endoffset + $length - 9).' but found "'.substr($rawdata, -9).'" instead';
					return false;
				}
				$ParsedLyrics3['raw']['LYR'] = trim(substr($rawdata, 11, -9));
				$this->Lyrics3LyricsTimestampParse($ParsedLyrics3);
				break;

			case 2:
				if (!str_ends_with($rawdata, 'LYRICS200')) {
					$this->getid3->info['error'][] = '"LYRICS200" expected at '.($endoffset + $length - 9).' but found "'.substr($rawdata, -9).'" instead';
					return false;
				}
				$unparsedData = substr($rawdata, 11, -15); // 11 for 'LYRICSBEGIN', 15 for size and 'LYRICS200'
				$ParsedLyrics3['raw']['unparsed'] = $unparsedData;

				while (strlen($unparsedData) > 0) {
					$fieldname = substr($unparsedData, 0, 3);
					$fieldsize = (int) substr($unparsedData, 3, 5);
					$ParsedLyrics3['raw'][$fieldname] = substr($unparsedData, 8, $fieldsize);
					$unparsedData = substr($unparsedData, 8 + $fieldsize);
				}

				if (isset($ParsedLyrics3['raw']['IND'])) {
					$flagnames = ['lyrics', 'timestamps', 'inhibitrandom'];
					foreach ($flagnames as $i => $flagname) {
						if (isset($ParsedLyrics3['raw']['IND'][$i])) {
							$ParsedLyrics3['flags'][$flagname] = $this->IntString2Bool($ParsedLyrics3['raw']['IND'][$i]);
						}
					}
				}

				$fieldnametranslation = ['ETT' => 'title', 'EAR' => 'artist', 'EAL' => 'album', 'INF' => 'comment', 'AUT' => 'author'];
				foreach ($fieldnametranslation as $key => $value) {
					if (isset($ParsedLyrics3['raw'][$key])) {
						$ParsedLyrics3['comments'][$value][] = trim($ParsedLyrics3['raw'][$key]);
					}
				}

				if (isset($ParsedLyrics3['raw']['IMG'])) {
					$imagestrings = explode("\r\n", $ParsedLyrics3['raw']['IMG']);
					foreach ($imagestrings as $key => $imagestring) {
						if (str_contains($imagestring, '||')) {
							$imagearray = explode('||', $imagestring);
							$ParsedLyrics3['images'][$key]['filename'] = $imagearray[0] ?? '';
							$ParsedLyrics3['images'][$key]['description'] = $imagearray[1] ?? '';
							$ParsedLyrics3['images'][$key]['timestamp'] = $this->Lyrics3Timestamp2Seconds($imagearray[2] ?? '');
						}
					}
				}
				if (isset($ParsedLyrics3['raw']['LYR'])) {
					$this->Lyrics3LyricsTimestampParse($ParsedLyrics3);
				}
				break;

			default:
				$this->getid3->info['error'][] = 'Cannot process Lyrics3 version '.$version.' (only v1 and v2)';
				return false;
		}

		if (isset($this->getid3->info['id3v1']['tag_offset_start']) && ($this->getid3->info['id3v1']['tag_offset_start'] <= $ParsedLyrics3['tag_offset_end'])) {
			$this->getid3->info['warning'][] = 'ID3v1 tag information ignored since it appears to be a false synch in Lyrics3 tag data';
			unset($this->getid3->info['id3v1']);

			$warning_to_remove = 'Some ID3v1 fields do not use NULL characters for padding';
			$key_to_remove = array_search($warning_to_remove, $this->getid3->info['warning'], true);
			if ($key_to_remove !== false) {
				unset($this->getid3->info['warning'][$key_to_remove]);
				$this->getid3->info['warning'] = array_values($this->getid3->info['warning']);
			}
		}

		$this->getid3->info['lyrics3'] = $ParsedLyrics3;
		return true;
	}

	public function Lyrics3Timestamp2Seconds(string $rawtimestamp): int|false
	{
		if (preg_match('#^\\[([0-9]{2}):([0-9]{2})\\]$#', $rawtimestamp, $regs)) {
			return (int) (($regs[1] * 60) + $regs[2]);
		}
		return false;
	}

	public function Lyrics3LyricsTimestampParse(array &$Lyrics3data): true
	{
		$lyricsarray = explode("\r\n", $Lyrics3data['raw']['LYR']);
		$notimestamplyricsarray = [];
		foreach ($lyricsarray as $lyricline) {
			$thislinetimestamps = [];
			while (preg_match('#^(\\[[0-9]{2}:[0-9]{2}\\])#', $lyricline, $regs)) {
				$thislinetimestamps[] = $this->Lyrics3Timestamp2Seconds($regs[0]);
				$lyricline = str_replace($regs[0], '', $lyricline);
			}
			$notimestamplyricsarray[] = $lyricline;
			if (!empty($thislinetimestamps)) {
				sort($thislinetimestamps);
				foreach ($thislinetimestamps as $timestamp) {
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
		if (isset($Lyrics3data['synchedlyrics'])) {
			ksort($Lyrics3data['synchedlyrics']);
		}
		return true;
	}

	public function IntString2Bool(string $char): ?bool
	{
		return match ($char) {
			'1' => true,
			'0' => false,
			default => null,
		};
	}
}
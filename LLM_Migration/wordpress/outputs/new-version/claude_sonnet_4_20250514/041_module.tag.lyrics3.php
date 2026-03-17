<?php
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

readonly class LyricsVersion
{
    public const V1 = 1;
    public const V2 = 2;
}

class getid3_lyrics3 extends getid3_handler
{
    private const LYRICS_END_V1 = 'LYRICSEND';
    private const LYRICS_END_V2 = 'LYRICS200';
    private const LYRICS_BEGIN = 'LYRICSBEGIN';
    private const ID3V1_SIZE = 128;
    private const LYRICS3V1_SIZE = 5100;

    public function Analyze(): bool
    {
        $info = &$this->getid3->info;

        // http://www.volweb.cz/str/tags.htm

        if (!getid3_lib::intValueSupported($info['filesize'])) {
            $info['warning'][] = 'Unable to check for Lyrics3 because file is larger than ' . round(PHP_INT_MAX / 1073741824) . 'GB';
            return false;
        }

        fseek($this->getid3->fp, (0 - self::ID3V1_SIZE - 9 - 6), SEEK_END);
        $lyrics3_id3v1 = fread($this->getid3->fp, self::ID3V1_SIZE + 9 + 6);
        $lyrics3lsz = substr($lyrics3_id3v1, 0, 6);
        $lyrics3end = substr($lyrics3_id3v1, 6, 9);
        $id3v1tag = substr($lyrics3_id3v1, 15, self::ID3V1_SIZE);

        $lyrics3Data = $this->detectLyrics3Format($lyrics3_id3v1, $lyrics3lsz, $lyrics3end, $info);

        if (!$lyrics3Data && isset($info['ape']['tag_offset_start']) && ($info['ape']['tag_offset_start'] > 15)) {
            $lyrics3Data = $this->checkLyrics3WithApe($info);
        }

        if ($lyrics3Data) {
            ['offset' => $lyrics3offset, 'version' => $lyrics3version, 'size' => $lyrics3size] = $lyrics3Data;
            $info['avdataend'] = $lyrics3offset;
            $this->getLyrics3Data($lyrics3offset, $lyrics3version, $lyrics3size);

            if (!isset($info['ape'])) {
                $this->processApeTag($info);
            }
        }

        return true;
    }

    private function detectLyrics3Format(string $lyrics3_id3v1, string $lyrics3lsz, string $lyrics3end, array $info): ?array
    {
        return match ($lyrics3end) {
            self::LYRICS_END_V1 => [
                'size' => self::LYRICS3V1_SIZE,
                'offset' => $info['filesize'] - self::ID3V1_SIZE - self::LYRICS3V1_SIZE,
                'version' => LyricsVersion::V1
            ],
            self::LYRICS_END_V2 => [
                'size' => (int)$lyrics3lsz + 6 + strlen(self::LYRICS_END_V2),
                'offset' => $info['filesize'] - self::ID3V1_SIZE - ((int)$lyrics3lsz + 6 + strlen(self::LYRICS_END_V2)),
                'version' => LyricsVersion::V2
            ],
            default => $this->checkReversedFormat($lyrics3_id3v1, $info)
        };
    }

    private function checkReversedFormat(string $lyrics3_id3v1, array $info): ?array
    {
        $reversed = strrev($lyrics3_id3v1);
        
        if (str_starts_with($reversed, strrev(self::LYRICS_END_V1))) {
            return [
                'size' => self::LYRICS3V1_SIZE,
                'offset' => $info['filesize'] - self::LYRICS3V1_SIZE,
                'version' => LyricsVersion::V1
            ];
        }
        
        if (str_starts_with($reversed, strrev(self::LYRICS_END_V2))) {
            $size = (int)strrev(substr($reversed, 9, 6)) + 6 + strlen(self::LYRICS_END_V2);
            return [
                'size' => $size,
                'offset' => $info['filesize'] - $size,
                'version' => LyricsVersion::V2
            ];
        }

        return null;
    }

    private function checkLyrics3WithApe(array &$info): ?array
    {
        fseek($this->getid3->fp, $info['ape']['tag_offset_start'] - 15, SEEK_SET);
        $lyrics3lsz = fread($this->getid3->fp, 6);
        $lyrics3end = fread($this->getid3->fp, 9);

        $result = match ($lyrics3end) {
            self::LYRICS_END_V1 => [
                'size' => self::LYRICS3V1_SIZE,
                'offset' => $info['ape']['tag_offset_start'] - self::LYRICS3V1_SIZE,
                'version' => LyricsVersion::V1
            ],
            self::LYRICS_END_V2 => [
                'size' => (int)$lyrics3lsz + 6 + strlen(self::LYRICS_END_V2),
                'offset' => $info['ape']['tag_offset_start'] - ((int)$lyrics3lsz + 6 + strlen(self::LYRICS_END_V2)),
                'version' => LyricsVersion::V2
            ],
            default => null
        };

        if ($result) {
            $info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatibility';
        }

        return $result;
    }

    private function processApeTag(array &$info): void
    {
        $GETID3_ERRORARRAY = &$info['warning'];
        if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH . 'module.tag.apetag.php', __FILE__, false)) {
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
        }
    }

    public function getLyrics3Data(int $endoffset, int $version, int $length): bool
    {
        $info = &$this->getid3->info;

        if (!getid3_lib::intValueSupported($endoffset)) {
            $info['warning'][] = 'Unable to check for Lyrics3 because file is larger than ' . round(PHP_INT_MAX / 1073741824) . 'GB';
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
                'lyrics3tagsize' => $length
            ],
            'tag_offset_start' => $endoffset,
            'tag_offset_end' => $endoffset + $length - 1
        ];

        if (!str_starts_with($rawdata, self::LYRICS_BEGIN)) {
            $beginPos = strpos($rawdata, self::LYRICS_BEGIN);
            if ($beginPos !== false) {
                $info['warning'][] = '"' . self::LYRICS_BEGIN . '" expected at ' . $endoffset . ' but actually found at ' . ($endoffset + $beginPos) . ' - this is invalid for Lyrics3 v' . $version;
                $info['avdataend'] = $endoffset + $beginPos;
                $rawdata = substr($rawdata, $beginPos);
                $length = strlen($rawdata);
                $ParsedLyrics3['tag_offset_start'] = $info['avdataend'];
                $ParsedLyrics3['raw']['lyrics3tagsize'] = $length;
            } else {
                $info['error'][] = '"' . self::LYRICS_BEGIN . '" expected at ' . $endoffset . ' but found "' . substr($rawdata, 0, 11) . '" instead';
                return false;
            }
        }

        $success = match ($version) {
            LyricsVersion::V1 => $this->parseLyrics3V1($rawdata, $ParsedLyrics3, $info),
            LyricsVersion::V2 => $this->parseLyrics3V2($rawdata, $ParsedLyrics3, $info),
            default => $this->handleUnsupportedVersion($version, $info)
        };

        if (!$success) {
            return false;
        }

        $this->handleId3v1Conflict($ParsedLyrics3, $info);
        $info['lyrics3'] = $ParsedLyrics3;

        return true;
    }

    private function parseLyrics3V1(string $rawdata, array &$ParsedLyrics3, array &$info): bool
    {
        if (str_ends_with($rawdata, self::LYRICS_END_V1)) {
            $ParsedLyrics3['raw']['LYR'] = trim(substr($rawdata, 11, strlen($rawdata) - 11 - 9));
            $this->Lyrics3LyricsTimestampParse($ParsedLyrics3);
            return true;
        }
        
        $info['error'][] = '"' . self::LYRICS_END_V1 . '" expected at ' . (ftell($this->getid3->fp) - 11 + strlen($rawdata) - 9) . ' but found "' . substr($rawdata, -9) . '" instead';
        return false;
    }

    private function parseLyrics3V2(string $rawdata, array &$ParsedLyrics3, array &$info): bool
    {
        if (!str_ends_with($rawdata, self::LYRICS_END_V2)) {
            $info['error'][] = '"' . self::LYRICS_END_V2 . '" expected at ' . (ftell($this->getid3->fp) - 11 + strlen($rawdata) - 9) . ' but found "' . substr($rawdata, -9) . '" instead';
            return false;
        }

        $ParsedLyrics3['raw']['unparsed'] = substr($rawdata, 11, strlen($rawdata) - 11 - 9 - 6);
        $this->parseFields($ParsedLyrics3['raw']['unparsed'], $ParsedLyrics3);
        $this->processFlags($ParsedLyrics3);
        $this->processComments($ParsedLyrics3);
        $this->processImages($ParsedLyrics3);
        
        if (isset($ParsedLyrics3['raw']['LYR'])) {
            $this->Lyrics3LyricsTimestampParse($ParsedLyrics3);
        }

        return true;
    }

    private function parseFields(string $rawdata, array &$ParsedLyrics3): void
    {
        while (strlen($rawdata) > 0) {
            $fieldname = substr($rawdata, 0, 3);
            $fieldsize = (int)substr($rawdata, 3, 5);
            $ParsedLyrics3['raw'][$fieldname] = substr($rawdata, 8, $fieldsize);
            $rawdata = substr($rawdata, 3 + 5 + $fieldsize);
        }
    }

    private function processFlags(array &$ParsedLyrics3): void
    {
        if (!isset($ParsedLyrics3['raw']['IND'])) {
            return;
        }

        $flagnames = ['lyrics', 'timestamps', 'inhibitrandom'];
        foreach ($flagnames as $i => $flagname) {
            if (strlen($ParsedLyrics3['raw']['IND']) > $i) {
                $ParsedLyrics3['flags'][$flagname] = $this->IntString2Bool($ParsedLyrics3['raw']['IND'][$i]);
            }
        }
    }

    private function processComments(array &$ParsedLyrics3): void
    {
        $fieldnametranslation = [
            'ETT' => 'title',
            'EAR' => 'artist', 
            'EAL' => 'album',
            'INF' => 'comment',
            'AUT' => 'author'
        ];
        
        foreach ($fieldnametranslation as $key => $value) {
            if (isset($ParsedLyrics3['raw'][$key])) {
                $ParsedLyrics3['comments'][$value][] = trim($ParsedLyrics3['raw'][$key]);
            }
        }
    }

    private function processImages(array &$ParsedLyrics3): void
    {
        if (!isset($ParsedLyrics3['raw']['IMG'])) {
            return;
        }

        $imagestrings = explode("\r\n", $ParsedLyrics3['raw']['IMG']);
        foreach ($imagestrings as $key => $imagestring) {
            if (str_contains($imagestring, '||')) {
                $imagearray = explode('||', $imagestring);
                $ParsedLyrics3['images'][$key] = [
                    'filename' => $imagearray[0] ?? '',
                    'description' => $imagearray[1] ?? '',
                    'timestamp' => $this->Lyrics3Timestamp2Seconds($imagearray[2] ?? '')
                ];
            }
        }
    }

    private function handleUnsupportedVersion(int $version, array &$info): bool
    {
        $info['error'][] = 'Cannot process Lyrics3 version ' . $version . ' (only v1 and v2)';
        return false;
    }

    private function handleId3v1Conflict(array $ParsedLyrics3, array &$info): void
    {
        if (isset($info['id3v1']['tag_offset_start']) && ($info['id3v1']['tag_offset_start'] <= $ParsedLyrics3['tag_offset_end'])) {
            $info['warning'][] = 'ID3v1 tag information ignored since it appears to be a false synch in Lyrics3 tag data';
            unset($info['id3v1']);
            
            $info['warning'] = array_filter($info['warning'], 
                fn($value) => $value !== 'Some ID3v1 fields do not use NULL characters for padding'
            );
            $info['warning'] = array_values($info['warning']);
        }
    }

    public function Lyrics3Timestamp2Seconds(string $rawtimestamp): int|false
    {
        if (preg_match('#^\[([0-9]{2}):([0-9]{2})\]$#', $rawtimestamp, $regs)) {
            return (int)(($regs[1] * 60) + $regs[2]);
        }
        return false;
    }

    public function Lyrics3LyricsTimestampParse(array &$Lyrics3data): bool
    {
        $lyricsarray = explode("\r\n", $Lyrics3data['raw']['LYR']);
        $notimestamplyricsarray = [];
        
        foreach ($lyricsarray as $key => $lyricline) {
            $thislinetimestamps = [];
            
            while (preg_match('#^(\[[0-9]{2}:[0-9]{2}\])#', $lyricline, $regs)) {
                $thislinetimestamps[] = $this->Lyrics3Timestamp2Seconds($regs[0]);
                $lyricline = str_replace($regs[0], '', $lyricline);
            }
            
            $notimestamplyricsarray[$key] = $lyricline;
            
            if (!empty($thislinetimestamps)) {
                sort($thislinetimestamps);
                foreach ($thislinetimestamps as $timestamp) {
                    if (isset($Lyrics3data['synchedlyrics'][$timestamp])) {
                        $Lyrics3data['synchedlyrics'][$timestamp] .= "\r\n" . $lyricline;
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
        return match ($char) {
            '1' => true,
            '0' => false,
            default => null
        };
    }
}
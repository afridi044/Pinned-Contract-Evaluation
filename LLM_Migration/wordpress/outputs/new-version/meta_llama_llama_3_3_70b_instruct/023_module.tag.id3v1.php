<?php

declare(strict_types=1);

class getid3_id3v1 extends getid3_handler
{
    public function analyze(): bool
    {
        $info = &$this->getid3->info;

        if (!getid3_lib::intValueSupported($info['filesize'])) {
            $info['warning'][] = 'Unable to check for ID3v1 because file is larger than ' . round(PHP_INT_MAX / 1073741824) . 'GB';
            return false;
        }

        fseek($this->getid3->fp, -256, SEEK_END);
        $preid3v1 = fread($this->getid3->fp, 128);
        $id3v1tag = fread($this->getid3->fp, 128);

        if (substr($id3v1tag, 0, 3) === 'TAG') {
            $info['avdataend'] = $info['filesize'] - 128;

            $parsedId3v1['title']   = $this->cutField(substr($id3v1tag, 3, 30));
            $parsedId3v1['artist']  = $this->cutField(substr($id3v1tag, 33, 30));
            $parsedId3v1['album']   = $this->cutField(substr($id3v1tag, 63, 30));
            $parsedId3v1['year']    = $this->cutField(substr($id3v1tag, 93, 4));
            $parsedId3v1['comment'] = substr($id3v1tag, 97, 30);  // can't remove nulls yet, track detection depends on them
            $parsedId3v1['genreid'] = ord(substr($id3v1tag, 127, 1));

            if (($id3v1tag[125] === "\x00") && ($id3v1tag[126] !== "\x00")) {
                $parsedId3v1['track']   = ord(substr($parsedId3v1['comment'], 29, 1));
                $parsedId3v1['comment'] = substr($parsedId3v1['comment'], 0, 28);
            }
            $parsedId3v1['comment'] = $this->cutField($parsedId3v1['comment']);

            $parsedId3v1['genre'] = $this->lookupGenreName($parsedId3v1['genreid']);
            if (!empty($parsedId3v1['genre'])) {
                unset($parsedId3v1['genreid']);
            }
            if (isset($parsedId3v1['genre']) && (empty($parsedId3v1['genre']) || ($parsedId3v1['genre'] === 'Unknown'))) {
                unset($parsedId3v1['genre']);
            }

            foreach ($parsedId3v1 as $key => $value) {
                $parsedId3v1['comments'][$key][0] = $value;
            }

            $goodFormatId3v1tag = $this->generateId3v1Tag(
                $parsedId3v1['title'],
                $parsedId3v1['artist'],
                $parsedId3v1['album'],
                $parsedId3v1['year'],
                isset($parsedId3v1['genre']) ? $this->lookupGenreId($parsedId3v1['genre']) : false,
                $parsedId3v1['comment'],
                !empty($parsedId3v1['track']) ? $parsedId3v1['track'] : ''
            );
            $parsedId3v1['padding_valid'] = true;
            if ($id3v1tag !== $goodFormatId3v1tag) {
                $parsedId3v1['padding_valid'] = false;
                $info['warning'][] = 'Some ID3v1 fields do not use NULL characters for padding';
            }

            $parsedId3v1['tag_offset_end']   = $info['filesize'];
            $parsedId3v1['tag_offset_start'] = $parsedId3v1['tag_offset_end'] - 128;

            $info['id3v1'] = $parsedId3v1;
        }

        if (substr($preid3v1, 0, 3) === 'TAG') {
            if (substr($preid3v1, 96, 8) === 'APETAGEX') {
                // an APE tag footer was found before the last ID3v1, assume false "TAG" synch
            } elseif (substr($preid3v1, 119, 6) === 'LYRICS') {
                // a Lyrics3 tag footer was found before the last ID3v1, assume false "TAG" synch
            } else {
                $info['warning'][] = 'Duplicate ID3v1 tag detected - this has been known to happen with iTunes';
                $info['avdataend'] -= 128;
            }
        }

        return true;
    }

    public static function cutField(string $str): string
    {
        return trim(substr($str, 0, strcspn($str, "\x00")));
    }

    public static function arrayOfGenres(bool $allowSCMPXExtended = false): array
    {
        static $genreLookup = [
            0    => 'Blues',
            1    => 'Classic Rock',
            2    => 'Country',
            3    => 'Dance',
            4    => 'Disco',
            5    => 'Funk',
            6    => 'Grunge',
            7    => 'Hip-Hop',
            8    => 'Jazz',
            9    => 'Metal',
            10   => 'New Age',
            11   => 'Oldies',
            12   => 'Other',
            13   => 'Pop',
            14   => 'R&B',
            15   => 'Rap',
            16   => 'Reggae',
            17   => 'Rock',
            18   => 'Techno',
            19   => 'Industrial',
            20   => 'Alternative',
            21   => 'Ska',
            22   => 'Death Metal',
            23   => 'Pranks',
            24   => 'Soundtrack',
            25   => 'Euro-Techno',
            26   => 'Ambient',
            27   => 'Trip-Hop',
            28   => 'Vocal',
            29   => 'Jazz+Funk',
            30   => 'Fusion',
            31   => 'Trance',
            32   => 'Classical',
            33   => 'Instrumental',
            34   => 'Acid',
            35   => 'House',
            36   => 'Game',
            37   => 'Sound Clip',
            38   => 'Gospel',
            39   => 'Noise',
            40   => 'Alt. Rock',
            41   => 'Bass',
            42   => 'Soul',
            43   => 'Punk',
            44   => 'Space',
            45   => 'Meditative',
            46   => 'Instrumental Pop',
            47   => 'Instrumental Rock',
            48   => 'Ethnic',
            49   => 'Gothic',
            50   => 'Darkwave',
            51   => 'Techno-Industrial',
            52   => 'Electronic',
            53   => 'Pop-Folk',
            54   => 'Eurodance',
            55   => 'Dream',
            56   => 'Southern Rock',
            57   => 'Comedy',
            58   => 'Cult',
            59   => 'Gangsta Rap',
            60   => 'Top 40',
            61   => 'Christian Rap',
            62   => 'Pop/Funk',
            63   => 'Jungle',
            64   => 'Native American',
            65   => 'Cabaret',
            66   => 'New Wave',
            67   => 'Psychedelic',
            68   => 'Rave',
            69   => 'Showtunes',
            70   => 'Trailer',
            71   => 'Lo-Fi',
            72   => 'Tribal',
            73   => 'Acid Punk',
            74   => 'Acid Jazz',
            75   => 'Polka',
            76   => 'Retro',
            77   => 'Musical',
            78   => 'Rock & Roll',
            79   => 'Hard Rock',
            80   => 'Folk',
            81   => 'Folk/Rock',
            82   => 'National Folk',
            83   => 'Swing',
            84   => 'Fast-Fusion',
            85   => 'Bebob',
            86   => 'Latin',
            87   => 'Revival',
            88   => 'Celtic',
            89   => 'Bluegrass',
            90   => 'Avantgarde',
            91   => 'Gothic Rock',
            92   => 'Progressive Rock',
            93   => 'Psychedelic Rock',
            94   => 'Symphonic Rock',
            95   => 'Slow Rock',
            96   => 'Big Band',
            97   => 'Chorus',
            98   => 'Easy Listening',
            99   => 'Acoustic',
            100  => 'Humour',
            101  => 'Speech',
            102  => 'Chanson',
            103  => 'Opera',
            104  => 'Chamber Music',
            105  => 'Sonata',
            106  => 'Symphony',
            107  => 'Booty Bass',
            108  => 'Primus',
            109  => 'Porn Groove',
            110  => 'Satire',
            111  => 'Slow Jam',
            112  => 'Club',
            113  => 'Tango',
            114  => 'Samba',
            115  => 'Folklore',
            116  => 'Ballad',
            117  => 'Power Ballad',
            118  => 'Rhythmic Soul',
            119  => 'Freestyle',
            120  => 'Duet',
            121  => 'Punk Rock',
            122  => 'Drum Solo',
            123  => 'A Cappella',
            124  => 'Euro-House',
            125  => 'Dance Hall',
            126  => 'Goa',
            127  => 'Drum & Bass',
            128  => 'Club-House',
            129  => 'Hardcore',
            130  => 'Terror',
            131  => 'Indie',
            132  => 'BritPop',
            133  => 'Negerpunk',
            134  => 'Polsk Punk',
            135  => 'Beat',
            136  => 'Christian Gangsta Rap',
            137  => 'Heavy Metal',
            138  => 'Black Metal',
            139  => 'Crossover',
            140  => 'Contemporary Christian',
            141  => 'Christian Rock',
            142  => 'Merengue',
            143  => 'Salsa',
            144  => 'Thrash Metal',
            145  => 'Anime',
            146  => 'JPop',
            147  => 'Synthpop',
            255  => 'Unknown',
            'CR' => 'Cover',
            'RX' => 'Remix'
        ];

        static $genreLookupSCMPX = [];
        if ($allowSCMPXExtended && empty($genreLookupSCMPX)) {
            $genreLookupSCMPX = $genreLookup;
            $genreLookupSCMPX[240] = 'Sacred';
            $genreLookupSCMPX[241] = 'Northern Europe';
            $genreLookupSCMPX[242] = 'Irish & Scottish';
            $genreLookupSCMPX[243] = 'Scotland';
            $genreLookupSCMPX[244] = 'Ethnic Europe';
            $genreLookupSCMPX[245] = 'Enka';
            $genreLookupSCMPX[246] = 'Children\'s Song';
            $genreLookupSCMPX[247] = 'Japanese Sky';
            $genreLookupSCMPX[248] = 'Japanese Heavy Rock';
            $genreLookupSCMPX[249] = 'Japanese Doom Rock';
            $genreLookupSCMPX[250] = 'Japanese J-POP';
            $genreLookupSCMPX[251] = 'Japanese Seiyu';
            $genreLookupSCMPX[252] = 'Japanese Ambient Techno';
            $genreLookupSCMPX[253] = 'Japanese Moemoe';
            $genreLookupSCMPX[254] = 'Japanese Tokusatsu';
        }

        return $allowSCMPXExtended ? $genreLookupSCMPX : $genreLookup;
    }

    public static function lookupGenreName($genreId, bool $allowSCMPXExtended = true): string|false
    {
        switch ($genreId) {
            case 'RX':
            case 'CR':
                break;
            default:
                if (!is_numeric($genreId)) {
                    return false;
                }
                $genreId = intval($genreId); // to handle 3 or '3' or '03'
                break;
        }
        $genreLookup = self::arrayOfGenres($allowSCMPXExtended);
        return $genreLookup[$genreId] ?? false;
    }

    public static function lookupGenreId(string $genre, bool $allowSCMPXExtended = false): int|false
    {
        $genreLookup = self::arrayOfGenres($allowSCMPXExtended);
        $lowerCaseNoSpaceSearchTerm = strtolower(str_replace(' ', '', $genre));
        foreach ($genreLookup as $key => $value) {
            if (strtolower(str_replace(' ', '', $value)) === $lowerCaseNoSpaceSearchTerm) {
                return $key;
            }
        }
        return false;
    }

    public static function standardiseId3v1GenreName(string $originalGenre): string
    {
        if (($genreId = self::lookupGenreId($originalGenre)) !== false) {
            return self::lookupGenreName($genreId);
        }
        return $originalGenre;
    }

    public static function generateId3v1Tag(
        string $title,
        string $artist,
        string $album,
        string $year,
        int|false $genreId,
        string $comment,
        string $track = ''
    ): string {
        $id3v1Tag  = 'TAG';
        $id3v1Tag .= str_pad(trim(substr($title, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        $id3v1Tag .= str_pad(trim(substr($artist, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        $id3v1Tag .= str_pad(trim(substr($album, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        $id3v1Tag .= str_pad(trim(substr($year, 0, 4)), 4, "\x00", STR_PAD_LEFT);
        if (!empty($track) && ($track > 0) && ($track <= 255)) {
            $id3v1Tag .= str_pad(trim(substr($comment, 0, 28)), 28, "\x00", STR_PAD_RIGHT);
            $id3v1Tag .= "\x00";
            if (gettype($track) === 'string') {
                $track = (int) $track;
            }
            $id3v1Tag .= chr($track);
        } else {
            $id3v1Tag .= str_pad(trim(substr($comment, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        }
        if (($genreId < 0) || ($genreId > 147)) {
            $genreId = 255; // 'unknown' genre
        }
        switch (gettype($genreId)) {
            case 'string':
            case 'integer':
                $id3v1Tag .= chr(intval($genreId));
                break;
            default:
                $id3v1Tag .= chr(255); // 'unknown' genre
                break;
        }

        return $id3v1Tag;
    }
}
<?php

class getid3_id3v1 extends getid3_handler
{
    public function Analyze(): bool
    {
        $info = &$this->getid3->info;

        if (!getid3_lib::intValueSupported($info['filesize'])) {
            $info['warning'][] = 'Unable to check for ID3v1 because file is larger than ' . round(PHP_INT_MAX / 1073741824) . 'GB';

            return false;
        }

        fseek($this->getid3->fp, -256, SEEK_END);
        $preid3v1 = fread($this->getid3->fp, 128);
        $id3v1tag = fread($this->getid3->fp, 128);

        $preid3v1 = $preid3v1 !== false ? $preid3v1 : '';
        $id3v1tag = $id3v1tag !== false ? $id3v1tag : '';

        if (strncmp($id3v1tag, 'TAG', 3) === 0) {
            $info['avdataend'] = $info['filesize'] - 128;

            $ParsedID3v1 = [];
            $ParsedID3v1['title'] = self::cutfield(substr($id3v1tag, 3, 30));
            $ParsedID3v1['artist'] = self::cutfield(substr($id3v1tag, 33, 30));
            $ParsedID3v1['album'] = self::cutfield(substr($id3v1tag, 63, 30));
            $ParsedID3v1['year'] = self::cutfield(substr($id3v1tag, 93, 4));
            $ParsedID3v1['comment'] = substr($id3v1tag, 97, 30);
            $ParsedID3v1['genreid'] = ord(substr($id3v1tag, 127, 1));

            if (($id3v1tag[125] ?? '') === "\x00" && ($id3v1tag[126] ?? '') !== "\x00") {
                $trackByte = $ParsedID3v1['comment'][29] ?? "\x00";
                $ParsedID3v1['track'] = ord($trackByte);
                $ParsedID3v1['comment'] = substr($ParsedID3v1['comment'], 0, 28);
            }

            $ParsedID3v1['comment'] = self::cutfield($ParsedID3v1['comment']);

            $ParsedID3v1['genre'] = self::LookupGenreName($ParsedID3v1['genreid']);
            if (!empty($ParsedID3v1['genre'])) {
                unset($ParsedID3v1['genreid']);
            }

            if (isset($ParsedID3v1['genre']) && ($ParsedID3v1['genre'] === '' || $ParsedID3v1['genre'] === 'Unknown')) {
                unset($ParsedID3v1['genre']);
            }

            $comments = [];
            foreach ($ParsedID3v1 as $key => $value) {
                $comments[$key] = [$value];
            }
            $ParsedID3v1['comments'] = $comments;

            $goodFormatID3v1tag = self::GenerateID3v1Tag(
                $ParsedID3v1['title'],
                $ParsedID3v1['artist'],
                $ParsedID3v1['album'],
                $ParsedID3v1['year'],
                $ParsedID3v1['genre'] ?? false ? self::LookupGenreID($ParsedID3v1['genre']) : false,
                $ParsedID3v1['comment'],
                !empty($ParsedID3v1['track']) ? $ParsedID3v1['track'] : ''
            );

            $ParsedID3v1['padding_valid'] = $id3v1tag === $goodFormatID3v1tag;
            if (!$ParsedID3v1['padding_valid']) {
                $info['warning'][] = 'Some ID3v1 fields do not use NULL characters for padding';
            }

            $ParsedID3v1['tag_offset_end'] = $info['filesize'];
            $ParsedID3v1['tag_offset_start'] = $ParsedID3v1['tag_offset_end'] - 128;

            $info['id3v1'] = $ParsedID3v1;
        }

        if (strncmp($preid3v1, 'TAG', 3) === 0) {
            if (substr($preid3v1, 96, 8) === 'APETAGEX') {
                // APE tag footer found - assume false "TAG" synch
            } elseif (substr($preid3v1, 119, 6) === 'LYRICS') {
                // Lyrics3 tag footer found - assume false "TAG" synch
            } else {
                $info['warning'][] = 'Duplicate ID3v1 tag detected - this has been known to happen with iTunes';
                $info['avdataend'] -= 128;
            }
        }

        return true;
    }

    public static function cutfield(string $str): string
    {
        return trim(substr($str, 0, strcspn($str, "\x00")));
    }

    public static function ArrayOfGenres(bool $allowSCMPXextended = false): array
    {
        static $GenreLookup = [
            0 => 'Blues',
            1 => 'Classic Rock',
            2 => 'Country',
            3 => 'Dance',
            4 => 'Disco',
            5 => 'Funk',
            6 => 'Grunge',
            7 => 'Hip-Hop',
            8 => 'Jazz',
            9 => 'Metal',
            10 => 'New Age',
            11 => 'Oldies',
            12 => 'Other',
            13 => 'Pop',
            14 => 'R&B',
            15 => 'Rap',
            16 => 'Reggae',
            17 => 'Rock',
            18 => 'Techno',
            19 => 'Industrial',
            20 => 'Alternative',
            21 => 'Ska',
            22 => 'Death Metal',
            23 => 'Pranks',
            24 => 'Soundtrack',
            25 => 'Euro-Techno',
            26 => 'Ambient',
            27 => 'Trip-Hop',
            28 => 'Vocal',
            29 => 'Jazz+Funk',
            30 => 'Fusion',
            31 => 'Trance',
            32 => 'Classical',
            33 => 'Instrumental',
            34 => 'Acid',
            35 => 'House',
            36 => 'Game',
            37 => 'Sound Clip',
            38 => 'Gospel',
            39 => 'Noise',
            40 => 'Alt. Rock',
            41 => 'Bass',
            42 => 'Soul',
            43 => 'Punk',
            44 => 'Space',
            45 => 'Meditative',
            46 => 'Instrumental Pop',
            47 => 'Instrumental Rock',
            48 => 'Ethnic',
            49 => 'Gothic',
            50 => 'Darkwave',
            51 => 'Techno-Industrial',
            52 => 'Electronic',
            53 => 'Pop-Folk',
            54 => 'Eurodance',
            55 => 'Dream',
            56 => 'Southern Rock',
            57 => 'Comedy',
            58 => 'Cult',
            59 => 'Gangsta Rap',
            60 => 'Top 40',
            61 => 'Christian Rap',
            62 => 'Pop/Funk',
            63 => 'Jungle',
            64 => 'Native American',
            65 => 'Cabaret',
            66 => 'New Wave',
            67 => 'Psychedelic',
            68 => 'Rave',
            69 => 'Showtunes',
            70 => 'Trailer',
            71 => 'Lo-Fi',
            72 => 'Tribal',
            73 => 'Acid Punk',
            74 => 'Acid Jazz',
            75 => 'Polka',
            76 => 'Retro',
            77 => 'Musical',
            78 => 'Rock & Roll',
            79 => 'Hard Rock',
            80 => 'Folk',
            81 => 'Folk/Rock',
            82 => 'National Folk',
            83 => 'Swing',
            84 => 'Fast-Fusion',
            85 => 'Bebob',
            86 => 'Latin',
            87 => 'Revival',
            88 => 'Celtic',
            89 => 'Bluegrass',
            90 => 'Avantgarde',
            91 => 'Gothic Rock',
            92 => 'Progressive Rock',
            93 => 'Psychedelic Rock',
            94 => 'Symphonic Rock',
            95 => 'Slow Rock',
            96 => 'Big Band',
            97 => 'Chorus',
            98 => 'Easy Listening',
            99 => 'Acoustic',
            100 => 'Humour',
            101 => 'Speech',
            102 => 'Chanson',
            103 => 'Opera',
            104 => 'Chamber Music',
            105 => 'Sonata',
            106 => 'Symphony',
            107 => 'Booty Bass',
            108 => 'Primus',
            109 => 'Porn Groove',
            110 => 'Satire',
            111 => 'Slow Jam',
            112 => 'Club',
            113 => 'Tango',
            114 => 'Samba',
            115 => 'Folklore',
            116 => 'Ballad',
            117 => 'Power Ballad',
            118 => 'Rhythmic Soul',
            119 => 'Freestyle',
            120 => 'Duet',
            121 => 'Punk Rock',
            122 => 'Drum Solo',
            123 => 'A Cappella',
            124 => 'Euro-House',
            125 => 'Dance Hall',
            126 => 'Goa',
            127 => 'Drum & Bass',
            128 => 'Club-House',
            129 => 'Hardcore',
            130 => 'Terror',
            131 => 'Indie',
            132 => 'BritPop',
            133 => 'Negerpunk',
            134 => 'Polsk Punk',
            135 => 'Beat',
            136 => 'Christian Gangsta Rap',
            137 => 'Heavy Metal',
            138 => 'Black Metal',
            139 => 'Crossover',
            140 => 'Contemporary Christian',
            141 => 'Christian Rock',
            142 => 'Merengue',
            143 => 'Salsa',
            144 => 'Thrash Metal',
            145 => 'Anime',
            146 => 'JPop',
            147 => 'Synthpop',
            255 => 'Unknown',
            'CR' => 'Cover',
            'RX' => 'Remix',
        ];

        static $GenreLookupSCMPX = [];
        if ($allowSCMPXextended && $GenreLookupSCMPX === []) {
            $GenreLookupSCMPX = $GenreLookup;
            $GenreLookupSCMPX[240] = 'Sacred';
            $GenreLookupSCMPX[241] = 'Northern Europe';
            $GenreLookupSCMPX[242] = 'Irish & Scottish';
            $GenreLookupSCMPX[243] = 'Scotland';
            $GenreLookupSCMPX[244] = 'Ethnic Europe';
            $GenreLookupSCMPX[245] = 'Enka';
            $GenreLookupSCMPX[246] = 'Children\'s Song';
            $GenreLookupSCMPX[247] = 'Japanese Sky';
            $GenreLookupSCMPX[248] = 'Japanese Heavy Rock';
            $GenreLookupSCMPX[249] = 'Japanese Doom Rock';
            $GenreLookupSCMPX[250] = 'Japanese J-POP';
            $GenreLookupSCMPX[251] = 'Japanese Seiyu';
            $GenreLookupSCMPX[252] = 'Japanese Ambient Techno';
            $GenreLookupSCMPX[253] = 'Japanese Moemoe';
            $GenreLookupSCMPX[254] = 'Japanese Tokusatsu';
        }

        return $allowSCMPXextended ? $GenreLookupSCMPX : $GenreLookup;
    }

    public static function LookupGenreName(mixed $genreid, bool $allowSCMPXextended = true): string|false
    {
        if (is_string($genreid) && in_array($genreid, ['RX', 'CR'], true)) {
            $genreKey = $genreid;
        } elseif (is_numeric($genreid)) {
            $genreKey = (int) $genreid;
        } else {
            return false;
        }

        $genreLookup = self::ArrayOfGenres($allowSCMPXextended);

        return $genreLookup[$genreKey] ?? false;
    }

    public static function LookupGenreID(string $genre, bool $allowSCMPXextended = false): string|int|false
    {
        $genreLookup = self::ArrayOfGenres($allowSCMPXextended);
        $lowerCaseNoSpaceSearchTerm = strtolower(str_replace(' ', '', $genre));

        foreach ($genreLookup as $key => $value) {
            if (strtolower(str_replace(' ', '', $value)) === $lowerCaseNoSpaceSearchTerm) {
                return $key;
            }
        }

        return false;
    }

    public static function StandardiseID3v1GenreName(string $OriginalGenre): string
    {
        $genreId = self::LookupGenreID($OriginalGenre);

        if ($genreId !== false) {
            $genreName = self::LookupGenreName($genreId);
            if ($genreName !== false) {
                return $genreName;
            }
        }

        return $OriginalGenre;
    }

    public static function GenerateID3v1Tag(string $title, string $artist, string $album, string $year, int|string|false $genreid, string $comment, int|string $track = ''): string
    {
        $ID3v1Tag = 'TAG';
        $ID3v1Tag .= str_pad(trim(substr($title, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        $ID3v1Tag .= str_pad(trim(substr($artist, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        $ID3v1Tag .= str_pad(trim(substr($album, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        $ID3v1Tag .= str_pad(trim(substr($year, 0, 4)), 4, "\x00", STR_PAD_LEFT);

        $trackInt = is_numeric($track) ? (int) $track : 0;
        if ($trackInt > 0 && $trackInt <= 255) {
            $ID3v1Tag .= str_pad(trim(substr($comment, 0, 28)), 28, "\x00", STR_PAD_RIGHT);
            $ID3v1Tag .= "\x00";
            $ID3v1Tag .= chr($trackInt);
        } else {
            $ID3v1Tag .= str_pad(trim(substr($comment, 0, 30)), 30, "\x00", STR_PAD_RIGHT);
        }

        if (is_numeric($genreid)) {
            $numericGenreId = (int) $genreid;
            if ($numericGenreId < 0 || $numericGenreId > 147) {
                $numericGenreId = 255;
            }
            $ID3v1Tag .= chr($numericGenreId);
        } elseif (is_string($genreid)) {
            $ID3v1Tag .= chr((int) $genreid);
        } else {
            $ID3v1Tag .= chr(255);
        }

        return $ID3v1Tag;
    }
}
?>
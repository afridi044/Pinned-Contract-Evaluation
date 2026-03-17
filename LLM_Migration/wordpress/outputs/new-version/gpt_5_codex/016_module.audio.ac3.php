<?php

declare(strict_types=1);

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.ac3.php                                        //
// module for analyzing AC-3 (aka Dolby Digital) audio files   //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

class getid3_ac3 extends getid3_handler
{
    private array $AC3header = [];
    private int $BSIoffset = 0;

    public const SYNCWORD = "\x0B\x77";

    public function Analyze(): bool
    {
        $info = &$this->getid3->info;

        $info['ac3']['raw']['bsi'] = [];
        $thisfile_ac3 = &$info['ac3'];
        $thisfile_ac3_raw = &$thisfile_ac3['raw'];
        $thisfile_ac3_raw_bsi = &$thisfile_ac3_raw['bsi'];

        $info['fileformat'] = 'ac3';

        $this->fseek($info['avdataoffset']);
        $this->AC3header['syncinfo'] = $this->fread(5);

        if (strpos($this->AC3header['syncinfo'], self::SYNCWORD) === 0) {
            $thisfile_ac3_raw['synchinfo']['synchword'] = self::SYNCWORD;
            $offset = 2;
        } else {
            if (!$this->isDependencyFor('matroska')) {
                unset($info['fileformat'], $info['ac3']);
                return (bool) $this->error(
                    'Expecting "' . getid3_lib::PrintHexBytes(self::SYNCWORD) .
                    '" at offset ' . $info['avdataoffset'] . ', found "' .
                    getid3_lib::PrintHexBytes(substr($this->AC3header['syncinfo'], 0, 2)) . '"'
                );
            }
            $offset = 0;
            $this->fseek(-2, SEEK_CUR);
        }

        $info['audio']['dataformat'] = 'ac3';
        $info['audio']['bitrate_mode'] = 'cbr';
        $info['audio']['lossless'] = false;

        $thisfile_ac3_raw['synchinfo']['crc1'] = getid3_lib::LittleEndian2Int(substr($this->AC3header['syncinfo'], $offset, 2));
        $ac3_synchinfo_fscod_frmsizecod = getid3_lib::LittleEndian2Int(substr($this->AC3header['syncinfo'], ($offset + 2), 1));
        $thisfile_ac3_raw['synchinfo']['fscod'] = ($ac3_synchinfo_fscod_frmsizecod & 0xC0) >> 6;
        $thisfile_ac3_raw['synchinfo']['frmsizecod'] = ($ac3_synchinfo_fscod_frmsizecod & 0x3F);

        $thisfile_ac3['sample_rate'] = self::sampleRateCodeLookup($thisfile_ac3_raw['synchinfo']['fscod']);
        if ($thisfile_ac3_raw['synchinfo']['fscod'] <= 3) {
            $info['audio']['sample_rate'] = $thisfile_ac3['sample_rate'];
        }

        $thisfile_ac3['frame_length'] = self::frameSizeLookup(
            $thisfile_ac3_raw['synchinfo']['frmsizecod'],
            $thisfile_ac3_raw['synchinfo']['fscod']
        );
        $thisfile_ac3['bitrate'] = self::bitrateLookup($thisfile_ac3_raw['synchinfo']['frmsizecod']);
        $info['audio']['bitrate'] = $thisfile_ac3['bitrate'];

        $this->AC3header['bsi'] = getid3_lib::BigEndian2Bin($this->fread(15));

        $thisfile_ac3_raw_bsi['bsid'] = $this->readHeaderBSI(5);
        if ($thisfile_ac3_raw_bsi['bsid'] > 8) {
            $this->error('Bit stream identification is version ' . $thisfile_ac3_raw_bsi['bsid'] . ', but getID3() only understands up to version 8');
            unset($info['ac3']);
            return false;
        }

        $thisfile_ac3_raw_bsi['bsmod'] = $this->readHeaderBSI(3);
        $thisfile_ac3_raw_bsi['acmod'] = $this->readHeaderBSI(3);

        $thisfile_ac3['service_type'] = self::serviceTypeLookup($thisfile_ac3_raw_bsi['bsmod'], $thisfile_ac3_raw_bsi['acmod']);
        $ac3_coding_mode = self::audioCodingModeLookup($thisfile_ac3_raw_bsi['acmod']);
        if (is_array($ac3_coding_mode)) {
            foreach ($ac3_coding_mode as $key => $value) {
                $thisfile_ac3[$key] = $value;
            }
        }

        switch ($thisfile_ac3_raw_bsi['acmod']) {
            case 0:
            case 1:
                $info['audio']['channelmode'] = 'mono';
                break;

            case 3:
            case 4:
                $info['audio']['channelmode'] = 'stereo';
                break;

            default:
                $info['audio']['channelmode'] = 'surround';
                break;
        }
        $info['audio']['channels'] = $thisfile_ac3['num_channels'];

        if ($thisfile_ac3_raw_bsi['acmod'] & 0x01) {
            $thisfile_ac3_raw_bsi['cmixlev'] = $this->readHeaderBSI(2);
            $thisfile_ac3['center_mix_level'] = self::centerMixLevelLookup($thisfile_ac3_raw_bsi['cmixlev']);
        }

        if ($thisfile_ac3_raw_bsi['acmod'] & 0x04) {
            $thisfile_ac3_raw_bsi['surmixlev'] = $this->readHeaderBSI(2);
            $thisfile_ac3['surround_mix_level'] = self::surroundMixLevelLookup($thisfile_ac3_raw_bsi['surmixlev']);
        }

        if ($thisfile_ac3_raw_bsi['acmod'] === 0x02) {
            $thisfile_ac3_raw_bsi['dsurmod'] = $this->readHeaderBSI(2);
            $thisfile_ac3['dolby_surround_mode'] = self::dolbySurroundModeLookup($thisfile_ac3_raw_bsi['dsurmod']);
        }

        $thisfile_ac3_raw_bsi['lfeon'] = (bool) $this->readHeaderBSI(1);
        $thisfile_ac3['lfe_enabled'] = $thisfile_ac3_raw_bsi['lfeon'];
        if ($thisfile_ac3_raw_bsi['lfeon']) {
            $info['audio']['channels'] .= '.1';
        }

        $thisfile_ac3['channels_enabled'] = self::channelsEnabledLookup($thisfile_ac3_raw_bsi['acmod'], $thisfile_ac3_raw_bsi['lfeon']);

        $thisfile_ac3_raw_bsi['dialnorm'] = $this->readHeaderBSI(5);
        $thisfile_ac3['dialogue_normalization'] = '-' . $thisfile_ac3_raw_bsi['dialnorm'] . 'dB';

        $thisfile_ac3_raw_bsi['compre_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisfile_ac3_raw_bsi['compre_flag']) {
            $thisfile_ac3_raw_bsi['compr'] = $this->readHeaderBSI(8);
            $thisfile_ac3['heavy_compression'] = self::heavyCompression($thisfile_ac3_raw_bsi['compr']);
        }

        $thisfile_ac3_raw_bsi['langcode_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisfile_ac3_raw_bsi['langcode_flag']) {
            $thisfile_ac3_raw_bsi['langcod'] = $this->readHeaderBSI(8);
        }

        $thisfile_ac3_raw_bsi['audprodie'] = (bool) $this->readHeaderBSI(1);
        if ($thisfile_ac3_raw_bsi['audprodie']) {
            $thisfile_ac3_raw_bsi['mixlevel'] = $this->readHeaderBSI(5);
            $thisfile_ac3_raw_bsi['roomtyp'] = $this->readHeaderBSI(2);

            $thisfile_ac3['mixing_level'] = (80 + $thisfile_ac3_raw_bsi['mixlevel']) . 'dB';
            $thisfile_ac3['room_type'] = self::roomTypeLookup($thisfile_ac3_raw_bsi['roomtyp']);
        }

        if ($thisfile_ac3_raw_bsi['acmod'] === 0x00) {
            $thisfile_ac3_raw_bsi['dialnorm2'] = $this->readHeaderBSI(5);
            $thisfile_ac3['dialogue_normalization2'] = '-' . $thisfile_ac3_raw_bsi['dialnorm2'] . 'dB';

            $thisfile_ac3_raw_bsi['compre_flag2'] = (bool) $this->readHeaderBSI(1);
            if ($thisfile_ac3_raw_bsi['compre_flag2']) {
                $thisfile_ac3_raw_bsi['compr2'] = $this->readHeaderBSI(8);
                $thisfile_ac3['heavy_compression2'] = self::heavyCompression($thisfile_ac3_raw_bsi['compr2']);
            }

            $thisfile_ac3_raw_bsi['langcode_flag2'] = (bool) $this->readHeaderBSI(1);
            if ($thisfile_ac3_raw_bsi['langcode_flag2']) {
                $thisfile_ac3_raw_bsi['langcod2'] = $this->readHeaderBSI(8);
            }

            $thisfile_ac3_raw_bsi['audprodie2'] = (bool) $this->readHeaderBSI(1);
            if ($thisfile_ac3_raw_bsi['audprodie2']) {
                $thisfile_ac3_raw_bsi['mixlevel2'] = $this->readHeaderBSI(5);
                $thisfile_ac3_raw_bsi['roomtyp2'] = $this->readHeaderBSI(2);

                $thisfile_ac3['mixing_level2'] = (80 + $thisfile_ac3_raw_bsi['mixlevel2']) . 'dB';
                $thisfile_ac3['room_type2'] = self::roomTypeLookup($thisfile_ac3_raw_bsi['roomtyp2']);
            }
        }

        $thisfile_ac3_raw_bsi['copyright'] = (bool) $this->readHeaderBSI(1);

        $thisfile_ac3_raw_bsi['original'] = (bool) $this->readHeaderBSI(1);

        $thisfile_ac3_raw_bsi['timecode1_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisfile_ac3_raw_bsi['timecode1_flag']) {
            $thisfile_ac3_raw_bsi['timecode1'] = $this->readHeaderBSI(14);
        }

        $thisfile_ac3_raw_bsi['timecode2_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisfile_ac3_raw_bsi['timecode2_flag']) {
            $thisfile_ac3_raw_bsi['timecode2'] = $this->readHeaderBSI(14);
        }

        $thisfile_ac3_raw_bsi['addbsi_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisfile_ac3_raw_bsi['addbsi_flag']) {
            $thisfile_ac3_raw_bsi['addbsi_length'] = $this->readHeaderBSI(6);

            $this->AC3header['bsi'] .= getid3_lib::BigEndian2Bin($this->fread($thisfile_ac3_raw_bsi['addbsi_length']));

            $thisfile_ac3_raw_bsi['addbsi_data'] = substr($this->AC3header['bsi'], $this->BSIoffset, $thisfile_ac3_raw_bsi['addbsi_length'] * 8);
            $this->BSIoffset += $thisfile_ac3_raw_bsi['addbsi_length'] * 8;
        }

        return true;
    }

    private function readHeaderBSI(int $length): int
    {
        $data = substr($this->AC3header['bsi'], $this->BSIoffset, $length);
        $this->BSIoffset += $length;

        return bindec($data);
    }

    public static function sampleRateCodeLookup(int $fscod): int|string|false
    {
        static $sampleRateCodeLookup = [
            0 => 48000,
            1 => 44100,
            2 => 32000,
            3 => 'reserved',
        ];

        return $sampleRateCodeLookup[$fscod] ?? false;
    }

    public static function serviceTypeLookup(int $bsmod, int $acmod): string|false
    {
        static $serviceTypeLookup = [];

        if ($serviceTypeLookup === []) {
            for ($i = 0; $i <= 7; $i++) {
                $serviceTypeLookup[0][$i] = 'main audio service: complete main (CM)';
                $serviceTypeLookup[1][$i] = 'main audio service: music and effects (ME)';
                $serviceTypeLookup[2][$i] = 'associated service: visually impaired (VI)';
                $serviceTypeLookup[3][$i] = 'associated service: hearing impaired (HI)';
                $serviceTypeLookup[4][$i] = 'associated service: dialogue (D)';
                $serviceTypeLookup[5][$i] = 'associated service: commentary (C)';
                $serviceTypeLookup[6][$i] = 'associated service: emergency (E)';
            }

            $serviceTypeLookup[7][1] = 'associated service: voice over (VO)';
            for ($i = 2; $i <= 7; $i++) {
                $serviceTypeLookup[7][$i] = 'main audio service: karaoke';
            }
        }

        return $serviceTypeLookup[$bsmod][$acmod] ?? false;
    }

    public static function audioCodingModeLookup(int $acmod): array|false
    {
        static $audioCodingModeLookup = [
            0 => ['channel_config' => '1+1', 'num_channels' => 2, 'channel_order' => 'Ch1,Ch2'],
            1 => ['channel_config' => '1/0', 'num_channels' => 1, 'channel_order' => 'C'],
            2 => ['channel_config' => '2/0', 'num_channels' => 2, 'channel_order' => 'L,R'],
            3 => ['channel_config' => '3/0', 'num_channels' => 3, 'channel_order' => 'L,C,R'],
            4 => ['channel_config' => '2/1', 'num_channels' => 3, 'channel_order' => 'L,R,S'],
            5 => ['channel_config' => '3/1', 'num_channels' => 4, 'channel_order' => 'L,C,R,S'],
            6 => ['channel_config' => '2/2', 'num_channels' => 4, 'channel_order' => 'L,R,SL,SR'],
            7 => ['channel_config' => '3/2', 'num_channels' => 5, 'channel_order' => 'L,C,R,SL,SR'],
        ];

        return $audioCodingModeLookup[$acmod] ?? false;
    }

    public static function centerMixLevelLookup(int $cmixlev): float|string|false
    {
        static $centerMixLevelLookup;

        if ($centerMixLevelLookup === null) {
            $centerMixLevelLookup = [
                0 => pow(2, -3.0 / 6),
                1 => pow(2, -4.5 / 6),
                2 => pow(2, -6.0 / 6),
                3 => 'reserved',
            ];
        }

        return $centerMixLevelLookup[$cmixlev] ?? false;
    }

    public static function surroundMixLevelLookup(int $surmixlev): float|int|string|false
    {
        static $surroundMixLevelLookup;

        if ($surroundMixLevelLookup === null) {
            $surroundMixLevelLookup = [
                0 => pow(2, -3.0 / 6),
                1 => pow(2, -6.0 / 6),
                2 => 0,
                3 => 'reserved',
            ];
        }

        return $surroundMixLevelLookup[$surmixlev] ?? false;
    }

    public static function dolbySurroundModeLookup(int $dsurmod): string|false
    {
        static $dolbySurroundModeLookup = [
            0 => 'not indicated',
            1 => 'Not Dolby Surround encoded',
            2 => 'Dolby Surround encoded',
            3 => 'reserved',
        ];

        return $dolbySurroundModeLookup[$dsurmod] ?? false;
    }

    public static function channelsEnabledLookup(int $acmod, bool $lfeon): array
    {
        $lookup = [
            'ch1' => (bool) ($acmod === 0),
            'ch2' => (bool) ($acmod === 0),
            'left' => (bool) ($acmod > 1),
            'right' => (bool) ($acmod > 1),
            'center' => (bool) ($acmod & 0x01),
            'surround_mono' => false,
            'surround_left' => false,
            'surround_right' => false,
            'lfe' => $lfeon,
        ];

        switch ($acmod) {
            case 4:
            case 5:
                $lookup['surround_mono'] = true;
                break;

            case 6:
            case 7:
                $lookup['surround_left'] = true;
                $lookup['surround_right'] = true;
                break;
        }

        return $lookup;
    }

    public static function heavyCompression(int $compre): float
    {
        $fourbit = str_pad(decbin(($compre & 0xF0) >> 4), 4, '0', STR_PAD_LEFT);
        if ($fourbit[0] === '1') {
            $log_gain = -8 + bindec(substr($fourbit, 1));
        } else {
            $log_gain = bindec(substr($fourbit, 1));
        }
        $log_gain = ($log_gain + 1) * getid3_lib::RGADamplitude2dB(2);

        $lin_gain = (16 + ($compre & 0x0F)) / 32;

        return $log_gain - $lin_gain;
    }

    public static function roomTypeLookup(int $roomtyp): string|false
    {
        static $roomTypeLookup = [
            0 => 'not indicated',
            1 => 'large room, X curve monitor',
            2 => 'small room, flat monitor',
            3 => 'reserved',
        ];

        return $roomTypeLookup[$roomtyp] ?? false;
    }

    public static function frameSizeLookup(int $frmsizecod, int $fscod): int|false
    {
        $padding = (bool) ($frmsizecod % 2);
        $framesizeid = (int) floor($frmsizecod / 2);

        static $frameSizeLookup = [];
        if ($frameSizeLookup === []) {
            $frameSizeLookup = [
                0 => [128, 138, 192],
                1 => [40, 160, 174, 240],
                2 => [48, 192, 208, 288],
                3 => [56, 224, 242, 336],
                4 => [64, 256, 278, 384],
                5 => [80, 320, 348, 480],
                6 => [96, 384, 416, 576],
                7 => [112, 448, 486, 672],
                8 => [128, 512, 556, 768],
                9 => [160, 640, 696, 960],
                10 => [192, 768, 834, 1152],
                11 => [224, 896, 974, 1344],
                12 => [256, 1024, 1114, 1536],
                13 => [320, 1280, 1392, 1920],
                14 => [384, 1536, 1670, 2304],
                15 => [448, 1792, 1950, 2688],
                16 => [512, 2048, 2228, 3072],
                17 => [576, 2304, 2506, 3456],
                18 => [640, 2560, 2786, 3840],
            ];
        }
        if (($fscod === 1) && $padding) {
            $frameSizeLookup[$frmsizecod] += 2;
        }

        return $frameSizeLookup[$framesizeid][$fscod] ?? false;
    }

    public static function bitrateLookup(int $frmsizecod): int|false
    {
        $framesizeid = (int) floor($frmsizecod / 2);

        static $bitrateLookup = [
            0 => 32000,
            1 => 40000,
            2 => 48000,
            3 => 56000,
            4 => 64000,
            5 => 80000,
            6 => 96000,
            7 => 112000,
            8 => 128000,
            9 => 160000,
            10 => 192000,
            11 => 224000,
            12 => 256000,
            13 => 320000,
            14 => 384000,
            15 => 448000,
            16 => 512000,
            17 => 576000,
            18 => 640000,
        ];

        return $bitrateLookup[$framesizeid] ?? false;
    }
}
?>
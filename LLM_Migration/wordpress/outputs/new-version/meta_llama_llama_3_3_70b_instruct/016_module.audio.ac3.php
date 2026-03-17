<?php
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

class GetId3Ac3 extends GetId3Handler
{
    private array $ac3Header = [];
    private int $bsiOffset = 0;

    public const SYNCWORD = "\x0B\x77";

    public function analyze(): bool
    {
        $info = &$this->getid3->info;

        $info['ac3']['raw']['bsi'] = [];
        $thisFileAc3 = &$info['ac3'];
        $thisFileAc3Raw = &$thisFileAc3['raw'];
        $thisFileAc3RawBsi = &$thisFileAc3Raw['bsi'];

        $info['fileformat'] = 'ac3';

        $this->fseek($info['avdataoffset']);
        $this->ac3Header['syncinfo'] = $this->fread(5);

        if (strpos($this->ac3Header['syncinfo'], self::SYNCWORD) === 0) {
            $thisFileAc3Raw['synchinfo']['synchword'] = self::SYNCWORD;
            $offset = 2;
        } else {
            if (!$this->isDependencyFor('matroska')) {
                unset($info['fileformat'], $info['ac3']);
                return $this->error('Expecting "'.getid3_lib::PrintHexBytes(self::SYNCWORD).'" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes(substr($this->ac3Header['syncinfo'], 0, 2)).'"');
            }
            $offset = 0;
            $this->fseek(-2, SEEK_CUR);
        }

        $info['audio']['dataformat'] = 'ac3';
        $info['audio']['bitrate_mode'] = 'cbr';
        $info['audio']['lossless'] = false;

        $thisFileAc3Raw['synchinfo']['crc1'] = getid3_lib::LittleEndian2Int(substr($this->ac3Header['syncinfo'], $offset, 2));
        $ac3SynchinfoFscodFrmsizecod = getid3_lib::LittleEndian2Int(substr($this->ac3Header['syncinfo'], ($offset + 2), 1));
        $thisFileAc3Raw['synchinfo']['fscod'] = ($ac3SynchinfoFscodFrmsizecod & 0xC0) >> 6;
        $thisFileAc3Raw['synchinfo']['frmsizecod'] = ($ac3SynchinfoFscodFrmsizecod & 0x3F);

        $thisFileAc3['sample_rate'] = self::sampleRateCodeLookup($thisFileAc3Raw['synchinfo']['fscod']);
        if ($thisFileAc3Raw['synchinfo']['fscod'] <= 3) {
            $info['audio']['sample_rate'] = $thisFileAc3['sample_rate'];
        }

        $thisFileAc3['frame_length'] = self::frameSizeLookup($thisFileAc3Raw['synchinfo']['frmsizecod'], $thisFileAc3Raw['synchinfo']['fscod']);
        $thisFileAc3['bitrate'] = self::bitrateLookup($thisFileAc3Raw['synchinfo']['frmsizecod']);
        $info['audio']['bitrate'] = $thisFileAc3['bitrate'];

        $this->ac3Header['bsi'] = getid3_lib::BigEndian2Bin($this->fread(15));
        $ac3BsiOffset = 0;

        $thisFileAc3RawBsi['bsid'] = $this->readHeaderBSI(5);
        if ($thisFileAc3RawBsi['bsid'] > 8) {
            $this->error('Bit stream identification is version '.$thisFileAc3RawBsi['bsid'].', but getID3() only understands up to version 8');
            unset($info['ac3']);
            return false;
        }

        $thisFileAc3RawBsi['bsmod'] = $this->readHeaderBSI(3);
        $thisFileAc3RawBsi['acmod'] = $this->readHeaderBSI(3);

        $thisFileAc3['service_type'] = self::serviceTypeLookup($thisFileAc3RawBsi['bsmod'], $thisFileAc3RawBsi['acmod']);
        $ac3CodingMode = self::audioCodingModeLookup($thisFileAc3RawBsi['acmod']);
        foreach ($ac3CodingMode as $key => $value) {
            $thisFileAc3[$key] = $value;
        }
        switch ($thisFileAc3RawBsi['acmod']) {
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
        $info['audio']['channels'] = $thisFileAc3['num_channels'];

        if ($thisFileAc3RawBsi['acmod'] & 0x01) {
            $thisFileAc3RawBsi['cmixlev'] = $this->readHeaderBSI(2);
            $thisFileAc3['center_mix_level'] = self::centerMixLevelLookup($thisFileAc3RawBsi['cmixlev']);
        }

        if ($thisFileAc3RawBsi['acmod'] & 0x04) {
            $thisFileAc3RawBsi['surmixlev'] = $this->readHeaderBSI(2);
            $thisFileAc3['surround_mix_level'] = self::surroundMixLevelLookup($thisFileAc3RawBsi['surmixlev']);
        }

        if ($thisFileAc3RawBsi['acmod'] == 0x02) {
            $thisFileAc3RawBsi['dsurmod'] = $this->readHeaderBSI(2);
            $thisFileAc3['dolby_surround_mode'] = self::dolbySurroundModeLookup($thisFileAc3RawBsi['dsurmod']);
        }

        $thisFileAc3RawBsi['lfeon'] = (bool) $this->readHeaderBSI(1);
        $thisFileAc3['lfe_enabled'] = $thisFileAc3RawBsi['lfeon'];
        if ($thisFileAc3RawBsi['lfeon']) {
            $info['audio']['channels'].= '.1';
        }

        $thisFileAc3['channels_enabled'] = self::channelsEnabledLookup($thisFileAc3RawBsi['acmod'], $thisFileAc3RawBsi['lfeon']);

        $thisFileAc3RawBsi['dialnorm'] = $this->readHeaderBSI(5);
        $thisFileAc3['dialogue_normalization'] = '-'.$thisFileAc3RawBsi['dialnorm'].'dB';

        $thisFileAc3RawBsi['compre_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisFileAc3RawBsi['compre_flag']) {
            $thisFileAc3RawBsi['compr'] = $this->readHeaderBSI(8);
            $thisFileAc3['heavy_compression'] = self::heavyCompression($thisFileAc3RawBsi['compr']);
        }

        $thisFileAc3RawBsi['langcode_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisFileAc3RawBsi['langcode_flag']) {
            $thisFileAc3RawBsi['langcod'] = $this->readHeaderBSI(8);
        }

        $thisFileAc3RawBsi['audprodie'] = (bool) $this->readHeaderBSI(1);
        if ($thisFileAc3RawBsi['audprodie']) {
            $thisFileAc3RawBsi['mixlevel'] = $this->readHeaderBSI(5);
            $thisFileAc3RawBsi['roomtyp'] = $this->readHeaderBSI(2);

            $thisFileAc3['mixing_level'] = (80 + $thisFileAc3RawBsi['mixlevel']).'dB';
            $thisFileAc3['room_type'] = self::roomTypeLookup($thisFileAc3RawBsi['roomtyp']);
        }

        if ($thisFileAc3RawBsi['acmod'] == 0x00) {
            $thisFileAc3RawBsi['dialnorm2'] = $this->readHeaderBSI(5);
            $thisFileAc3['dialogue_normalization2'] = '-'.$thisFileAc3RawBsi['dialnorm2'].'dB';

            $thisFileAc3RawBsi['compre_flag2'] = (bool) $this->readHeaderBSI(1);
            if ($thisFileAc3RawBsi['compre_flag2']) {
                $thisFileAc3RawBsi['compr2'] = $this->readHeaderBSI(8);
                $thisFileAc3['heavy_compression2'] = self::heavyCompression($thisFileAc3RawBsi['compr2']);
            }

            $thisFileAc3RawBsi['langcode_flag2'] = (bool) $this->readHeaderBSI(1);
            if ($thisFileAc3RawBsi['langcode_flag2']) {
                $thisFileAc3RawBsi['langcod2'] = $this->readHeaderBSI(8);
            }

            $thisFileAc3RawBsi['audprodie2'] = (bool) $this->readHeaderBSI(1);
            if ($thisFileAc3RawBsi['audprodie2']) {
                $thisFileAc3RawBsi['mixlevel2'] = $this->readHeaderBSI(5);
                $thisFileAc3RawBsi['roomtyp2'] = $this->readHeaderBSI(2);

                $thisFileAc3['mixing_level2'] = (80 + $thisFileAc3RawBsi['mixlevel2']).'dB';
                $thisFileAc3['room_type2'] = self::roomTypeLookup($thisFileAc3RawBsi['roomtyp2']);
            }
        }

        $thisFileAc3RawBsi['copyright'] = (bool) $this->readHeaderBSI(1);

        $thisFileAc3RawBsi['original'] = (bool) $this->readHeaderBSI(1);

        $thisFileAc3RawBsi['timecode1_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisFileAc3RawBsi['timecode1_flag']) {
            $thisFileAc3RawBsi['timecode1'] = $this->readHeaderBSI(14);
        }

        $thisFileAc3RawBsi['timecode2_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisFileAc3RawBsi['timecode2_flag']) {
            $thisFileAc3RawBsi['timecode2'] = $this->readHeaderBSI(14);
        }

        $thisFileAc3RawBsi['addbsi_flag'] = (bool) $this->readHeaderBSI(1);
        if ($thisFileAc3RawBsi['addbsi_flag']) {
            $thisFileAc3RawBsi['addbsi_length'] = $this->readHeaderBSI(6);

            $this->ac3Header['bsi'].= getid3_lib::BigEndian2Bin($this->fread($thisFileAc3RawBsi['addbsi_length']));

            $thisFileAc3RawBsi['addbsi_data'] = substr($this->ac3Header['bsi'], $this->bsiOffset, $thisFileAc3RawBsi['addbsi_length'] * 8);
            $this->bsiOffset += $thisFileAc3RawBsi['addbsi_length'] * 8;
        }

        return true;
    }

    private function readHeaderBSI(int $length): int
    {
        $data = substr($this->ac3Header['bsi'], $this->bsiOffset, $length);
        $this->bsiOffset += $length;

        return bindec($data);
    }

    public static function sampleRateCodeLookup(int $fscod): int|false
    {
        static $sampleRateCodeLookup = [
            0 => 48000,
            1 => 44100,
            2 => 32000,
            3 => 'reserved',
        ];
        return $sampleRateCodeLookup[$fscod]?? false;
    }

    public static function serviceTypeLookup(int $bsmod, int $acmod): string|false
    {
        static $serviceTypeLookup = [];
        if (empty($serviceTypeLookup)) {
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
        return $serviceTypeLookup[$bsmod][$acmod]?? false;
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
        return $audioCodingModeLookup[$acmod]?? false;
    }

    public static function centerMixLevelLookup(int $cmixlev): float|false
    {
        static $centerMixLevelLookup;
        if (empty($centerMixLevelLookup)) {
            $centerMixLevelLookup = [
                0 => pow(2, -3.0 / 6),
                1 => pow(2, -4.5 / 6),
                2 => pow(2, -6.0 / 6),
                3 => 'reserved',
            ];
        }
        return $centerMixLevelLookup[$cmixlev]?? false;
    }

    public static function surroundMixLevelLookup(int $surmixlev): float|false
    {
        static $surroundMixLevelLookup;
        if (empty($surroundMixLevelLookup)) {
            $surroundMixLevelLookup = [
                0 => pow(2, -3.0 / 6),
                1 => pow(2, -6.0 / 6),
                2 => 0,
                3 => 'reserved',
            ];
        }
        return $surroundMixLevelLookup[$surmixlev]?? false;
    }

    public static function dolbySurroundModeLookup(int $dsurmod): string|false
    {
        static $dolbySurroundModeLookup = [
            0 => 'not indicated',
            1 => 'Not Dolby Surround encoded',
            2 => 'Dolby Surround encoded',
            3 => 'reserved',
        ];
        return $dolbySurroundModeLookup[$dsurmod]?? false;
    }

    public static function channelsEnabledLookup(int $acmod, bool $lfeon): array
    {
        $lookup = [
            'ch1' => (bool) ($acmod == 0),
            'ch2' => (bool) ($acmod == 0),
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
        if ($fourbit[0] == '1') {
            $logGain = -8 + bindec(substr($fourbit, 1));
        } else {
            $logGain = bindec(substr($fourbit, 1));
        }
        $logGain = ($logGain + 1) * getid3_lib::RGADamplitude2dB(2);

        $linGain = (16 + ($compre & 0x0F)) / 32;

        return $logGain - $linGain;
    }

    public static function roomTypeLookup(int $roomtyp): string|false
    {
        static $roomTypeLookup = [
            0 => 'not indicated',
            1 => 'large room, X curve monitor',
            2 => 'small room, flat monitor',
            3 => 'reserved',
        ];
        return $roomTypeLookup[$roomtyp]?? false;
    }

    public static function frameSizeLookup(int $frmsizecod, int $fscod): int|false
    {
        $padding = (bool) ($frmsizecod % 2);
        $framesizeid = floor($frmsizecod / 2);

        static $frameSizeLookup = [];
        if (empty($frameSizeLookup)) {
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
        if (($fscod == 1) && $padding) {
            $frameSizeLookup[$frmsizecod] += 2;
        }
        return $frameSizeLookup[$framesizeid][$fscod]?? false;
    }

    public static function bitrateLookup(int $frmsizecod): int|false
    {
        $framesizeid = floor($frmsizecod / 2);

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
        return $bitrateLookup[$framesizeid]?? false;
    }
}
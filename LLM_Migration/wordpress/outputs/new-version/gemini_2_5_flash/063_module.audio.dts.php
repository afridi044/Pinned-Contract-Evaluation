<?php

namespace GetId3\Module\Audio;

use GetId3\Handler;
use GetId3\Lib;

/**
 * @tutorial http://wiki.multimedia.cx/index.php?title=DTS
 */
class Dts extends Handler
{
    /**
     * Default DTS syncword used in native .cpt or .dts formats
     */
    public const SYNCWORD = "\x7F\xFE\x80\x01";

    private int $readBinDataOffset = 0;

    /**
     * Possible syncwords indicating bitstream encoding
     */
    public static array $syncwords = [
        0 => "\x7F\xFE\x80\x01",  // raw big-endian
        1 => "\xFE\x7F\x01\x80",  // raw little-endian
        2 => "\x1F\xFF\xE8\x00",  // 14-bit big-endian
        3 => "\xFF\x1F\x00\xE8",  // 14-bit little-endian
    ];

    public function Analyze(): bool
    {
        $info = &$this->getid3->info;
        $info['fileformat'] = 'dts';

        $this->fseek($info['avdataoffset']);
        $dtsHeader = $this->fread(20); // Use camelCase for variable names

        // check syncword
        $sync = substr($dtsHeader, 0, 4);
        if (($encoding = array_search($sync, self::$syncwords, true)) !== false) { // Use strict comparison

            $info['dts']['raw']['magic'] = $sync;
            $this->readBinDataOffset = 32;

        } elseif ($this->isDependencyFor('matroska')) { // Assuming isDependencyFor is a method in the parent Handler class

            // Matroska contains DTS without syncword encoded as raw big-endian format
            $encoding = 0;
            $this->readBinDataOffset = 0;

        } else {

            unset($info['fileformat']);
            return $this->error(
                'Expecting "'.implode('| ', array_map([Lib::class, 'PrintHexBytes'], self::$syncwords)).
                '" at offset '.$info['avdataoffset'].', found "'.Lib::PrintHexBytes($sync).'"'
            );
        }

        // decode header
        $fhBS = '';
        for ($wordOffset = 0; $wordOffset <= strlen($dtsHeader); $wordOffset += 2) { // Use camelCase for variable names
            switch ($encoding) {
                case 0: // raw big-endian
                    $fhBS .= Lib::BigEndian2Bin(substr($dtsHeader, $wordOffset, 2));
                    break;
                case 1: // raw little-endian
                    $fhBS .= Lib::BigEndian2Bin(strrev(substr($dtsHeader, $wordOffset, 2)));
                    break;
                case 2: // 14-bit big-endian
                    $fhBS .= substr(Lib::BigEndian2Bin(substr($dtsHeader, $wordOffset, 2)), 2, 14);
                    break;
                case 3: // 14-bit little-endian
                    $fhBS .= substr(Lib::BigEndian2Bin(strrev(substr($dtsHeader, $wordOffset, 2))), 2, 14);
                    break;
            }
        }

        $info['dts']['raw']['frame_type']             = $this->readBinData($fhBS,  1);
        $info['dts']['raw']['deficit_samples']        = $this->readBinData($fhBS,  5);
        $info['dts']['flags']['crc_present']          = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['raw']['pcm_sample_blocks']      = $this->readBinData($fhBS,  7);
        $info['dts']['raw']['frame_byte_size']        = $this->readBinData($fhBS, 14);
        $info['dts']['raw']['channel_arrangement']    = $this->readBinData($fhBS,  6);
        $info['dts']['raw']['sample_frequency']       = $this->readBinData($fhBS,  4);
        $info['dts']['raw']['bitrate']                = $this->readBinData($fhBS,  5);
        $info['dts']['flags']['embedded_downmix']     = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['dynamicrange']         = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['timestamp']            = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['auxdata']              = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['hdcd']                 = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['raw']['extension_audio']        = $this->readBinData($fhBS,  3);
        $info['dts']['flags']['extended_coding']      = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['audio_sync_insertion'] = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['raw']['lfe_effects']            = $this->readBinData($fhBS,  2);
        $info['dts']['flags']['predictor_history']    = (bool) $this->readBinData($fhBS,  1);
        if ($info['dts']['flags']['crc_present']) {
            $info['dts']['raw']['crc16']              = $this->readBinData($fhBS, 16);
        }
        $info['dts']['flags']['mri_perfect_reconst']  = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['raw']['encoder_soft_version']   = $this->readBinData($fhBS,  4);
        $info['dts']['raw']['copy_history']           = $this->readBinData($fhBS,  2);
        $info['dts']['raw']['bits_per_sample']        = $this->readBinData($fhBS,  2);
        $info['dts']['flags']['surround_es']          = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['front_sum_diff']       = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['flags']['surround_sum_diff']    = (bool) $this->readBinData($fhBS,  1);
        $info['dts']['raw']['dialog_normalization']   = $this->readBinData($fhBS,  4);


        $info['dts']['bitrate']              = self::bitrateLookup($info['dts']['raw']['bitrate']);
        $info['dts']['bits_per_sample']      = self::bitPerSampleLookup($info['dts']['raw']['bits_per_sample']);
        $info['dts']['sample_rate']          = self::sampleRateLookup($info['dts']['raw']['sample_frequency']);
        $info['dts']['dialog_normalization'] = self::dialogNormalization($info['dts']['raw']['dialog_normalization'], $info['dts']['raw']['encoder_soft_version']);
        $info['dts']['flags']['lossless']    = ($info['dts']['raw']['bitrate'] === 31); // Simplified boolean assignment
        $info['dts']['bitrate_mode']         = ($info['dts']['raw']['bitrate'] === 30 ? 'vbr' : 'cbr'); // Simplified ternary
        $info['dts']['channels']             = self::numChannelsLookup($info['dts']['raw']['channel_arrangement']);
        $info['dts']['channel_arrangement']  = self::channelArrangementLookup($info['dts']['raw']['channel_arrangement']);

        $info['audio']['dataformat']          = 'dts';
        $info['audio']['lossless']            = $info['dts']['flags']['lossless'];
        $info['audio']['bitrate_mode']        = $info['dts']['bitrate_mode'];
        $info['audio']['bits_per_sample']     = $info['dts']['bits_per_sample'];
        $info['audio']['sample_rate']         = $info['dts']['sample_rate'];
        $info['audio']['channels']            = $info['dts']['channels'];
        $info['audio']['bitrate']             = $info['dts']['bitrate'];
        if (isset($info['avdataend']) && !empty($info['dts']['bitrate']) && is_numeric($info['dts']['bitrate'])) {
            $info['playtime_seconds']         = ($info['avdataend'] - $info['avdataoffset']) / ($info['dts']['bitrate'] / 8);
            if ($encoding === 2 || $encoding === 3) { // Use strict comparison
                // 14-bit data packed into 16-bit words, so the playtime is wrong because only (14/16) of the bytes in the data portion of the file are used at the specified bitrate
                $info['playtime_seconds'] *= (14 / 16);
            }
        }
        return true;
    }

    private function readBinData(string $bin, int $length): int
    {
        $data = substr($bin, $this->readBinDataOffset, $length);
        $this->readBinDataOffset += $length;

        return bindec($data);
    }

    public static function bitrateLookup(int $index): int|string|null
    {
        static $lookup = [
            0  => 32000,
            1  => 56000,
            2  => 64000,
            3  => 96000,
            4  => 112000,
            5  => 128000,
            6  => 192000,
            7  => 224000,
            8  => 256000,
            9  => 320000,
            10 => 384000,
            11 => 448000,
            12 => 512000,
            13 => 576000,
            14 => 640000,
            15 => 768000,
            16 => 960000,
            17 => 1024000,
            18 => 1152000,
            19 => 1280000,
            20 => 1344000,
            21 => 1408000,
            22 => 1411200,
            23 => 1472000,
            24 => 1536000,
            25 => 1920000,
            26 => 2048000,
            27 => 3072000,
            28 => 3840000,
            29 => 'open',
            30 => 'variable',
            31 => 'lossless',
        ];
        return $lookup[$index] ?? null; // Use null coalescing operator
    }

    public static function sampleRateLookup(int $index): int|string|null
    {
        static $lookup = [
            0  => 'invalid',
            1  => 8000,
            2  => 16000,
            3  => 32000,
            4  => 'invalid',
            5  => 'invalid',
            6  => 11025,
            7  => 22050,
            8  => 44100,
            9  => 'invalid',
            10 => 'invalid',
            11 => 12000,
            12 => 24000,
            13 => 48000,
            14 => 'invalid',
            15 => 'invalid',
        ];
        return $lookup[$index] ?? null;
    }

    public static function bitPerSampleLookup(int $index): int|null
    {
        static $lookup = [
            0  => 16,
            1  => 20,
            2  => 24,
            3  => 24,
        ];
        return $lookup[$index] ?? null;
    }

    public static function numChannelsLookup(int $index): int|null
    {
        return match ($index) { // Use match expression
            0 => 1,
            1, 2, 3, 4 => 2,
            5, 6 => 3,
            7, 8 => 4,
            9 => 5,
            10, 11, 12 => 6,
            13 => 7,
            14, 15 => 8,
            default => null, // Return null for unknown index
        };
    }

    public static function channelArrangementLookup(int $index): string
    {
        static $lookup = [
            0  => 'A',
            1  => 'A + B (dual mono)',
            2  => 'L + R (stereo)',
            3  => '(L+R) + (L-R) (sum-difference)',
            4  => 'LT + RT (left and right total)',
            5  => 'C + L + R',
            6  => 'L + R + S',
            7  => 'C + L + R + S',
            8  => 'L + R + SL + SR',
            9  => 'C + L + R + SL + SR',
            10 => 'CL + CR + L + R + SL + SR',
            11 => 'C + L + R+ LR + RR + OV',
            12 => 'CF + CR + LF + RF + LR + RR',
            13 => 'CL + C + CR + L + R + SL + SR',
            14 => 'CL + CR + L + R + SL1 + SL2 + SR1 + SR2',
            15 => 'CL + C+ CR + L + R + SL + S + SR',
        ];
        return $lookup[$index] ?? 'user-defined'; // Keep 'user-defined' as per original logic
    }

    public static function dialogNormalization(int $index, int $version): int|null
    {
        return match ($version) { // Use match expression
            7 => 0 - $index,
            6 => 0 - 16 - $index,
            default => null, // Return null for unknown version
        };
    }
}
<?php
declare(strict_types=1);

const GETID3_FLV_TAG_AUDIO = 8;
const GETID3_FLV_TAG_VIDEO = 9;
const GETID3_FLV_TAG_META  = 18;

const GETID3_FLV_VIDEO_H263         = 2;
const GETID3_FLV_VIDEO_SCREEN       = 3;
const GETID3_FLV_VIDEO_VP6FLV       = 4;
const GETID3_FLV_VIDEO_VP6FLV_ALPHA = 5;
const GETID3_FLV_VIDEO_SCREENV2     = 6;
const GETID3_FLV_VIDEO_H264         = 7;

const H264_AVC_SEQUENCE_HEADER          = 0;
const H264_PROFILE_BASELINE             = 66;
const H264_PROFILE_MAIN                 = 77;
const H264_PROFILE_EXTENDED             = 88;
const H264_PROFILE_HIGH                 = 100;
const H264_PROFILE_HIGH10               = 110;
const H264_PROFILE_HIGH422              = 122;
const H264_PROFILE_HIGH444              = 144;
const H264_PROFILE_HIGH444_PREDICTIVE   = 244;

class getid3_flv extends getid3_handler
{
    public int $max_frames = 100000;

    public function Analyze(): bool
    {
        $info = &$this->getid3->info;

        fseek($this->getid3->fp, $info['avdataoffset'], SEEK_SET);

        $FLVdataLength = $info['avdataend'] - $info['avdataoffset'];
        $FLVheader = fread($this->getid3->fp, 5) ?: '';

        $info['fileformat'] = 'flv';
        $info['flv']['header']['signature'] = substr($FLVheader, 0, 3);
        $info['flv']['header']['version']   = getid3_lib::BigEndian2Int(substr($FLVheader, 3, 1));
        $TypeFlags                          = getid3_lib::BigEndian2Int(substr($FLVheader, 4, 1));

        $magic = 'FLV';
        if ($info['flv']['header']['signature'] !== $magic) {
            $info['error'][] = 'Expecting "' . getid3_lib::PrintHexBytes($magic) . '" at offset ' . $info['avdataoffset'] . ', found "' . getid3_lib::PrintHexBytes($info['flv']['header']['signature']) . '"';
            unset($info['flv'], $info['fileformat']);
            return false;
        }

        $info['flv']['header']['hasAudio'] = (bool) ($TypeFlags & 0x04);
        $info['flv']['header']['hasVideo'] = (bool) ($TypeFlags & 0x01);

        $FrameSizeDataLength = getid3_lib::BigEndian2Int(fread($this->getid3->fp, 4) ?: '');
        $FLVheaderFrameLength = 9;
        if ($FrameSizeDataLength > $FLVheaderFrameLength) {
            fseek($this->getid3->fp, $FrameSizeDataLength - $FLVheaderFrameLength, SEEK_CUR);
        }
        $Duration = 0;
        $found_video = false;
        $found_audio = false;
        $found_meta  = false;
        $found_valid_meta_playtime = false;
        $tagParseCount = 0;
        $info['flv']['framecount'] = ['total' => 0, 'audio' => 0, 'video' => 0];
        $flv_framecount = &$info['flv']['framecount'];

        while ((ftell($this->getid3->fp) + 16) < $info['avdataend'] && (($tagParseCount++ <= $this->max_frames) || !$found_valid_meta_playtime)) {
            $ThisTagHeader = fread($this->getid3->fp, 16) ?: '';

            $PreviousTagLength = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 0, 4));
            $TagType           = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 4, 1));
            $DataLength        = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 5, 3));
            $Timestamp         = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 8, 3));
            $LastHeaderByte    = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 15, 1));
            $NextOffset        = ftell($this->getid3->fp) - 1 + $DataLength;
            if ($Timestamp > $Duration) {
                $Duration = $Timestamp;
            }

            $flv_framecount['total']++;
            switch ($TagType) {
                case GETID3_FLV_TAG_AUDIO:
                    $flv_framecount['audio']++;
                    if (!$found_audio) {
                        $found_audio = true;
                        $info['flv']['audio']['audioFormat']     = ($LastHeaderByte >> 4) & 0x0F;
                        $info['flv']['audio']['audioRate']       = ($LastHeaderByte >> 2) & 0x03;
                        $info['flv']['audio']['audioSampleSize'] = ($LastHeaderByte >> 1) & 0x01;
                        $info['flv']['audio']['audioType']       = $LastHeaderByte & 0x01;
                    }
                    break;

                case GETID3_FLV_TAG_VIDEO:
                    $flv_framecount['video']++;
                    if (!$found_video) {
                        $found_video = true;
                        $info['flv']['video']['videoCodec'] = $LastHeaderByte & 0x07;

                        $FLVvideoHeader = fread($this->getid3->fp, 11) ?: '';

                        if ($info['flv']['video']['videoCodec'] === GETID3_FLV_VIDEO_H264) {
                            $AVCPacketType = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 0, 1));
                            if ($AVCPacketType === H264_AVC_SEQUENCE_HEADER) {
                                $configurationVersion       = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 4, 1));
                                $AVCProfileIndication       = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 5, 1));
                                $profile_compatibility      = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 6, 1));
                                $lengthSizeMinusOne         = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 7, 1));
                                $numOfSequenceParameterSets = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 8, 1));

                                if (($numOfSequenceParameterSets & 0x1F) !== 0) {
                                    $spsSize = getid3_lib::LittleEndian2Int(substr($FLVvideoHeader, 9, 2));
                                    $sps = fread($this->getid3->fp, $spsSize) ?: '';
                                    if (strlen($sps) === $spsSize) {
                                        $spsReader = new AVCSequenceParameterSetReader($sps);
                                        $spsReader->readData();
                                        $info['video']['resolution_x'] = $spsReader->getWidth();
                                        $info['video']['resolution_y'] = $spsReader->getHeight();
                                    }
                                }
                            }
                        } elseif ($info['flv']['video']['videoCodec'] === GETID3_FLV_VIDEO_H263) {
                            $PictureSizeType = (getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 3, 2))) >> 7;
                            $PictureSizeType &= 0x0007;
                            $info['flv']['header']['videoSizeType'] = $PictureSizeType;
                            switch ($PictureSizeType) {
                                case 0:
                                    $PictureSizeEncX = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 4, 2));
                                    $PictureSizeEncY = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 5, 2));
                                    $PictureSizeEncX >>= 7;
                                    $PictureSizeEncY >>= 7;
                                    $info['video']['resolution_x'] = $PictureSizeEncX & 0xFF;
                                    $info['video']['resolution_y'] = $PictureSizeEncY & 0xFF;
                                    break;

                                case 1:
                                    $PictureSizeEncX = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 4, 3));
                                    $PictureSizeEncY = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 6, 3));
                                    $PictureSizeEncX >>= 7;
                                    $PictureSizeEncY >>= 7;
                                    $info['video']['resolution_x'] = $PictureSizeEncX & 0xFFFF;
                                    $info['video']['resolution_y'] = $PictureSizeEncY & 0xFFFF;
                                    break;

                                case 2:
                                    $info['video']['resolution_x'] = 352;
                                    $info['video']['resolution_y'] = 288;
                                    break;

                                case 3:
                                    $info['video']['resolution_x'] = 176;
                                    $info['video']['resolution_y'] = 144;
                                    break;

                                case 4:
                                    $info['video']['resolution_x'] = 128;
                                    $info['video']['resolution_y'] = 96;
                                    break;

                                case 5:
                                    $info['video']['resolution_x'] = 320;
                                    $info['video']['resolution_y'] = 240;
                                    break;

                                case 6:
                                    $info['video']['resolution_x'] = 160;
                                    $info['video']['resolution_y'] = 120;
                                    break;

                                default:
                                    $info['video']['resolution_x'] = 0;
                                    $info['video']['resolution_y'] = 0;
                                    break;
                            }
                        }

                        if (!empty($info['video']['resolution_y'])) {
                            $info['video']['pixel_aspect_ratio'] = $info['video']['resolution_x'] / $info['video']['resolution_y'];
                        }
                    }
                    break;

                case GETID3_FLV_TAG_META:
                    if (!$found_meta) {
                        $found_meta = true;
                        fseek($this->getid3->fp, -1, SEEK_CUR);
                        $datachunk = fread($this->getid3->fp, $DataLength) ?: '';
                        $AMFstream = new AMFStream($datachunk);
                        $reader = new AMFReader($AMFstream);
                        $eventName = $reader->readData();
                        $info['flv']['meta'][$eventName] = $reader->readData();

                        $copykeys = [
                            'framerate'     => 'frame_rate',
                            'width'         => 'resolution_x',
                            'height'        => 'resolution_y',
                            'audiodatarate' => 'bitrate',
                            'videodatarate' => 'bitrate',
                        ];
                        foreach ($copykeys as $sourcekey => $destkey) {
                            if (isset($info['flv']['meta']['onMetaData'][$sourcekey])) {
                                switch ($sourcekey) {
                                    case 'width':
                                    case 'height':
                                        $info['video'][$destkey] = (int) round($info['flv']['meta']['onMetaData'][$sourcekey]);
                                        break;

                                    case 'audiodatarate':
                                        $info['audio'][$destkey] = getid3_lib::CastAsInt(round($info['flv']['meta']['onMetaData'][$sourcekey] * 1000));
                                        break;

                                    default:
                                        $info['video'][$destkey] = $info['flv']['meta']['onMetaData'][$sourcekey];
                                        break;
                                }
                            }
                        }
                        if (!empty($info['flv']['meta']['onMetaData']['duration'])) {
                            $found_valid_meta_playtime = true;
                        }
                    }
                    break;

                default:
                    break;
            }
            fseek($this->getid3->fp, $NextOffset, SEEK_SET);
        }

        $info['playtime_seconds'] = $Duration / 1000;
        if ($info['playtime_seconds'] > 0) {
            $info['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];
        }

        if ($info['flv']['header']['hasAudio'] ?? false) {
            $info['audio']['codec']           = $this->FLVaudioFormat($info['flv']['audio']['audioFormat']);
            $info['audio']['sample_rate']     = $this->FLVaudioRate($info['flv']['audio']['audioRate']);
            $info['audio']['bits_per_sample'] = $this->FLVaudioBitDepth($info['flv']['audio']['audioSampleSize']);

            $info['audio']['channels']   = ($info['flv']['audio']['audioType'] ?? 0) + 1;
            $info['audio']['lossless']   = ($info['flv']['audio']['audioFormat'] ?? 0) === 0;
            $info['audio']['dataformat'] = 'flv';
        }
        if (!empty($info['flv']['header']['hasVideo'])) {
            $info['video']['codec']      = $this->FLVvideoCodec($info['flv']['video']['videoCodec']);
            $info['video']['dataformat'] = 'flv';
            $info['video']['lossless']   = false;
        }

        if (!empty($info['flv']['meta']['onMetaData']['duration'])) {
            $info['playtime_seconds'] = (float) $info['flv']['meta']['onMetaData']['duration'];
            if ($info['playtime_seconds'] > 0) {
                $info['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];
            }
        }
        if (isset($info['flv']['meta']['onMetaData']['audiocodecid'])) {
            $info['audio']['codec'] = $this->FLVaudioFormat($info['flv']['meta']['onMetaData']['audiocodecid']);
        }
        if (isset($info['flv']['meta']['onMetaData']['videocodecid'])) {
            $info['video']['codec'] = $this->FLVvideoCodec($info['flv']['meta']['onMetaData']['videocodecid']);
        }
        return true;
    }

    public function FLVaudioFormat(int|float|string $id): string|false
    {
        $index = (int) round((float) $id);
        $FLVaudioFormat = [
            0  => 'Linear PCM, platform endian',
            1  => 'ADPCM',
            2  => 'mp3',
            3  => 'Linear PCM, little endian',
            4  => 'Nellymoser 16kHz mono',
            5  => 'Nellymoser 8kHz mono',
            6  => 'Nellymoser',
            7  => 'G.711A-law logarithmic PCM',
            8  => 'G.711 mu-law logarithmic PCM',
            9  => 'reserved',
            10 => 'AAC',
            11 => false,
            12 => false,
            13 => false,
            14 => 'mp3 8kHz',
            15 => 'Device-specific sound',
        ];

        return $FLVaudioFormat[$index] ?? false;
    }

    public function FLVaudioRate(int|float|string $id): int|false
    {
        $index = (int) round((float) $id);
        $FLVaudioRate = [
            0 => 5500,
            1 => 11025,
            2 => 22050,
            3 => 44100,
        ];

        return $FLVaudioRate[$index] ?? false;
    }

    public function FLVaudioBitDepth(int|float|string $id): int|false
    {
        $index = (int) round((float) $id);
        $FLVaudioBitDepth = [
            0 => 8,
            1 => 16,
        ];

        return $FLVaudioBitDepth[$index] ?? false;
    }

    public function FLVvideoCodec(int|float|string $id): string|false
    {
        $index = (int) round((float) $id);
        $FLVvideoCodec = [
            GETID3_FLV_VIDEO_H263         => 'Sorenson H.263',
            GETID3_FLV_VIDEO_SCREEN       => 'Screen video',
            GETID3_FLV_VIDEO_VP6FLV       => 'On2 VP6',
            GETID3_FLV_VIDEO_VP6FLV_ALPHA => 'On2 VP6 with alpha channel',
            GETID3_FLV_VIDEO_SCREENV2     => 'Screen video v2',
            GETID3_FLV_VIDEO_H264         => 'Sorenson H.264',
        ];

        return $FLVvideoCodec[$index] ?? false;
    }
}

class AMFStream
{
    public string $bytes;
    public int $pos = 0;

    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
    }

    public function readByte(): int
    {
        $byte = substr($this->bytes, $this->pos++, 1);
        return getid3_lib::BigEndian2Int($byte === false ? '' : $byte);
    }

    public function readInt(): int
    {
        return ($this->readByte() << 8) + $this->readByte();
    }

    public function readLong(): int
    {
        return ($this->readByte() << 24) + ($this->readByte() << 16) + ($this->readByte() << 8) + $this->readByte();
    }

    public function readDouble(): float
    {
        return getid3_lib::BigEndian2Float($this->read(8));
    }

    public function readUTF(): string
    {
        $length = $this->readInt();
        return $this->read($length);
    }

    public function readLongUTF(): string
    {
        $length = $this->readLong();
        return $this->read($length);
    }

    public function read(int $length): string
    {
        $val = substr($this->bytes, $this->pos, $length);
        $this->pos += $length;
        return $val === false ? '' : $val;
    }

    public function peekByte(): int
    {
        $pos = $this->pos;
        $val = $this->readByte();
        $this->pos = $pos;
        return $val;
    }

    public function peekInt(): int
    {
        $pos = $this->pos;
        $val = $this->readInt();
        $this->pos = $pos;
        return $val;
    }

    public function peekLong(): int
    {
        $pos = $this->pos;
        $val = $this->readLong();
        $this->pos = $pos;
        return $val;
    }

    public function peekDouble(): float
    {
        $pos = $this->pos;
        $val = $this->readDouble();
        $this->pos = $pos;
        return $val;
    }

    public function peekUTF(): string
    {
        $pos = $this->pos;
        $val = $this->readUTF();
        $this->pos = $pos;
        return $val;
    }

    public function peekLongUTF(): string
    {
        $pos = $this->pos;
        $val = $this->readLongUTF();
        $this->pos = $pos;
        return $val;
    }
}

class AMFReader
{
    public AMFStream $stream;

    public function __construct(AMFStream $stream)
    {
        $this->stream = $stream;
    }

    public function readData(): mixed
    {
        $type = $this->stream->readByte();

        return match ($type) {
            0  => $this->readDouble(),
            1  => $this->readBoolean(),
            2  => $this->readString(),
            3  => $this->readObject(),
            6  => null,
            8  => $this->readMixedArray(),
            10 => $this->readArray(),
            11 => $this->readDate(),
            13 => $this->readLongString(),
            15 => $this->readXML(),
            16 => $this->readTypedObject(),
            default => '(unknown or unsupported data type)',
        };
    }

    public function readDouble(): float
    {
        return $this->stream->readDouble();
    }

    public function readBoolean(): bool
    {
        return $this->stream->readByte() === 1;
    }

    public function readString(): string
    {
        return $this->stream->readUTF();
    }

    public function readObject(): array
    {
        $data = [];

        while (true) {
            $key = $this->stream->readUTF();
            if ($key === '') {
                break;
            }
            $data[$key] = $this->readData();
        }

        if ($this->stream->peekByte() === 0x09) {
            $this->stream->readByte();
        }
        return $data;
    }

    public function readMixedArray(): array
    {
        $this->stream->readLong();

        $data = [];

        while (true) {
            $key = $this->stream->readUTF();
            if ($key === '') {
                break;
            }
            if (is_numeric($key)) {
                $key = (float) $key;
            }
            $data[$key] = $this->readData();
        }

        if ($this->stream->peekByte() === 0x09) {
            $this->stream->readByte();
        }

        return $data;
    }

    public function readArray(): array
    {
        $length = $this->stream->readLong();
        $data = [];

        for ($i = 0; $i < $length; $i++) {
            $data[] = $this->readData();
        }
        return $data;
    }

    public function readDate(): float
    {
        $timestamp = $this->stream->readDouble();
        $this->stream->readInt();
        return $timestamp;
    }

    public function readLongString(): string
    {
        return $this->stream->readLongUTF();
    }

    public function readXML(): string
    {
        return $this->stream->readLongUTF();
    }

    public function readTypedObject(): array
    {
        $this->stream->readUTF();
        return $this->readObject();
    }
}

class AVCSequenceParameterSetReader
{
    private string $sps;
    private int $currentBytes = 0;
    private int $currentBits = 0;
    private int $width = 0;
    private int $height = 0;

    public function __construct(string $sps)
    {
        $this->sps = $sps;
    }

    public function readData(): void
    {
        $this->skipBits(8);
        $this->skipBits(8);
        $profile = $this->getBits(8);
        $this->skipBits(16);
        $this->expGolombUe();
        if (in_array($profile, [H264_PROFILE_HIGH, H264_PROFILE_HIGH10, H264_PROFILE_HIGH422, H264_PROFILE_HIGH444, H264_PROFILE_HIGH444_PREDICTIVE], true)) {
            if ($this->expGolombUe() === 3) {
                $this->skipBits(1);
            }
            $this->expGolombUe();
            $this->expGolombUe();
            $this->skipBits(1);
            if ($this->getBit() === 1) {
                for ($i = 0; $i < 8; $i++) {
                    if ($this->getBit() === 1) {
                        $size = $i < 6 ? 16 : 64;
                        $lastScale = 8;
                        $nextScale = 8;
                        for ($j = 0; $j < $size; $j++) {
                            if ($nextScale !== 0) {
                                $deltaScale = $this->expGolombUe();
                                $nextScale = ($lastScale + $deltaScale + 256) % 256;
                            }
                            if ($nextScale !== 0) {
                                $lastScale = $nextScale;
                            }
                        }
                    }
                }
            }
        }
        $this->expGolombUe();
        $pocType = $this->expGolombUe();
        if ($pocType === 0) {
            $this->expGolombUe();
        } elseif ($pocType === 1) {
            $this->skipBits(1);
            $this->expGolombSe();
            $this->expGolombSe();
            $pocCycleLength = $this->expGolombUe();
            for ($i = 0; $i < $pocCycleLength; $i++) {
                $this->expGolombSe();
            }
        }
        $this->expGolombUe();
        $this->skipBits(1);
        $this->width = ($this->expGolombUe() + 1) * 16;
        $heightMap = $this->expGolombUe() + 1;
        $this->height = (2 - $this->getBit()) * $heightMap * 16;
    }

    public function skipBits(int $bits): void
    {
        $newBits = $this->currentBits + $bits;
        $this->currentBytes += intdiv($newBits, 8);
        $this->currentBits = $newBits % 8;
    }

    public function getBit(): int
    {
        $byte = substr($this->sps, $this->currentBytes, 1);
        $byteValue = $byte === false ? 0 : getid3_lib::BigEndian2Int($byte);
        $result = ($byteValue >> (7 - $this->currentBits)) & 0x01;
        $this->skipBits(1);
        return $result;
    }

    public function getBits(int $bits): int
    {
        $result = 0;
        for ($i = 0; $i < $bits; $i++) {
            $result = ($result << 1) + $this->getBit();
        }
        return $result;
    }

    public function expGolombUe(): int
    {
        $significantBits = 0;
        $bit = $this->getBit();
        while ($bit === 0) {
            $significantBits++;
            $bit = $this->getBit();

            if ($significantBits > 31) {
                return 0;
            }
        }
        return (1 << $significantBits) + $this->getBits($significantBits) - 1;
    }

    public function expGolombSe(): int
    {
        $result = $this->expGolombUe();
        if (($result & 0x01) === 0) {
            return -($result >> 1);
        }
        return ($result + 1) >> 1;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
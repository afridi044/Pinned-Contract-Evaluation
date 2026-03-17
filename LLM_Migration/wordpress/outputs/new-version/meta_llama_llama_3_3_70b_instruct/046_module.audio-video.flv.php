<?php

declare(strict_types=1);

define('GETID3_FLV_TAG_AUDIO', 8);
define('GETID3_FLV_TAG_VIDEO', 9);
define('GETID3_FLV_TAG_META', 18);

define('GETID3_FLV_VIDEO_H263', 2);
define('GETID3_FLV_VIDEO_SCREEN', 3);
define('GETID3_FLV_VIDEO_VP6FLV', 4);
define('GETID3_FLV_VIDEO_VP6FLV_ALPHA', 5);
define('GETID3_FLV_VIDEO_SCREENV2', 6);
define('GETID3_FLV_VIDEO_H264', 7);

define('H264_AVC_SEQUENCE_HEADER', 0);
define('H264_PROFILE_BASELINE', 66);
define('H264_PROFILE_MAIN', 77);
define('H264_PROFILE_EXTENDED', 88);
define('H264_PROFILE_HIGH', 100);
define('H264_PROFILE_HIGH10', 110);
define('H264_PROFILE_HIGH422', 122);
define('H264_PROFILE_HIGH444', 144);
define('H264_PROFILE_HIGH444_PREDICTIVE', 244);

class GetId3Flv extends GetId3Handler
{
    public int $max_frames = 100000;

    public function analyze(): bool
    {
        $info = &$this->getid3->info;

        fseek($this->getid3->fp, $info['avdataoffset'], SEEK_SET);

        $flvDataLength = $info['avdataend'] - $info['avdataoffset'];
        $flvHeader = fread($this->getid3->fp, 5);

        $info['fileformat'] = 'flv';
        $info['flv']['header']['signature'] = substr($flvHeader, 0, 3);
        $info['flv']['header']['version'] = getid3_lib::BigEndian2Int(substr($flvHeader, 3, 1));
        $typeFlags = getid3_lib::BigEndian2Int(substr($flvHeader, 4, 1));

        $magic = 'FLV';
        if ($info['flv']['header']['signature'] !== $magic) {
            $info['error'][] = 'Expecting "' . getid3_lib::PrintHexBytes($magic) . '" at offset ' . $info['avdataoffset'] . ', found "' . getid3_lib::PrintHexBytes($info['flv']['header']['signature']) . '"';
            unset($info['flv']);
            unset($info['fileformat']);
            return false;
        }

        $info['flv']['header']['hasAudio'] = (bool) ($typeFlags & 0x04);
        $info['flv']['header']['hasVideo'] = (bool) ($typeFlags & 0x01);

        $frameSizeDataLength = getid3_lib::BigEndian2Int(fread($this->getid3->fp, 4));
        $flvHeaderFrameLength = 9;
        if ($frameSizeDataLength > $flvHeaderFrameLength) {
            fseek($this->getid3->fp, $frameSizeDataLength - $flvHeaderFrameLength, SEEK_CUR);
        }
        $duration = 0;
        $foundVideo = false;
        $foundAudio = false;
        $foundMeta = false;
        $foundValidMetaPlaytime = false;
        $tagParseCount = 0;
        $info['flv']['framecount'] = ['total' => 0, 'audio' => 0, 'video' => 0];
        $flvFramecount = &$info['flv']['framecount'];
        while (((ftell($this->getid3->fp) + 16) < $info['avdataend']) && (($tagParseCount++ <= $this->max_frames) || !$foundValidMetaPlaytime)) {
            $thisTagHeader = fread($this->getid3->fp, 16);

            $previousTagLength = getid3_lib::BigEndian2Int(substr($thisTagHeader, 0, 4));
            $tagType = getid3_lib::BigEndian2Int(substr($thisTagHeader, 4, 1));
            $dataLength = getid3_lib::BigEndian2Int(substr($thisTagHeader, 5, 3));
            $timestamp = getid3_lib::BigEndian2Int(substr($thisTagHeader, 8, 3));
            $lastHeaderByte = getid3_lib::BigEndian2Int(substr($thisTagHeader, 15, 1));
            $nextOffset = ftell($this->getid3->fp) - 1 + $dataLength;
            if ($timestamp > $duration) {
                $duration = $timestamp;
            }

            $flvFramecount['total']++;
            match ($tagType) {
                GETID3_FLV_TAG_AUDIO => $this->handleAudioTag($flvFramecount, $foundAudio, $info, $lastHeaderByte),
                GETID3_FLV_TAG_VIDEO => $this->handleVideoTag($flvFramecount, $foundVideo, $info, $lastHeaderByte, $this->getid3->fp),
                GETID3_FLV_TAG_META => $this->handleMetaTag($flvFramecount, $foundMeta, $info, $dataLength, $this->getid3->fp),
                default => null,
            };
            fseek($this->getid3->fp, $nextOffset, SEEK_SET);
        }

        $info['playtime_seconds'] = $duration / 1000;
        if ($info['playtime_seconds'] > 0) {
            $info['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];
        }

        if ($info['flv']['header']['hasAudio']) {
            $info['audio']['codec'] = $this->flvAudioFormat($info['flv']['audio']['audioFormat']);
            $info['audio']['sample_rate'] = $this->flvAudioRate($info['flv']['audio']['audioRate']);
            $info['audio']['bits_per_sample'] = $this->flvAudioBitDepth($info['flv']['audio']['audioSampleSize']);

            $info['audio']['channels'] = $info['flv']['audio']['audioType'] + 1; // 0=mono,1=stereo
            $info['audio']['lossless'] = $info['flv']['audio']['audioFormat'] ? false : true; // 0=uncompressed
            $info['audio']['dataformat'] = 'flv';
        }
        if (!empty($info['flv']['header']['hasVideo'])) {
            $info['video']['codec'] = $this->flvVideoCodec($info['flv']['video']['videoCodec']);
            $info['video']['dataformat'] = 'flv';
            $info['video']['lossless'] = false;
        }

        // Set information from meta
        if (!empty($info['flv']['meta']['onMetaData']['duration'])) {
            $info['playtime_seconds'] = $info['flv']['meta']['onMetaData']['duration'];
            $info['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];
        }
        if (isset($info['flv']['meta']['onMetaData']['audiocodecid'])) {
            $info['audio']['codec'] = $this->flvAudioFormat($info['flv']['meta']['onMetaData']['audiocodecid']);
        }
        if (isset($info['flv']['meta']['onMetaData']['videocodecid'])) {
            $info['video']['codec'] = $this->flvVideoCodec($info['flv']['meta']['onMetaData']['videocodecid']);
        }
        return true;
    }

    private function handleAudioTag(array &$flvFramecount, bool &$foundAudio, array &$info, int $lastHeaderByte): void
    {
        $flvFramecount['audio']++;
        if (!$foundAudio) {
            $foundAudio = true;
            $info['flv']['audio']['audioFormat'] = ($lastHeaderByte >> 4) & 0x0F;
            $info['flv']['audio']['audioRate'] = ($lastHeaderByte >> 2) & 0x03;
            $info['flv']['audio']['audioSampleSize'] = ($lastHeaderByte >> 1) & 0x01;
            $info['flv']['audio']['audioType'] = $lastHeaderByte & 0x01;
        }
    }

    private function handleVideoTag(array &$flvFramecount, bool &$foundVideo, array &$info, int $lastHeaderByte, resource $fp): void
    {
        $flvFramecount['video']++;
        if (!$foundVideo) {
            $foundVideo = true;
            $info['flv']['video']['videoCodec'] = $lastHeaderByte & 0x07;

            $flvVideoHeader = fread($fp, 11);

            if ($info['flv']['video']['videoCodec'] === GETID3_FLV_VIDEO_H264) {
                // this code block contributed by: moysevichØgmail*com

                $avcPacketType = getid3_lib::BigEndian2Int(substr($flvVideoHeader, 0, 1));
                if ($avcPacketType === H264_AVC_SEQUENCE_HEADER) {
                    //	read AVCDecoderConfigurationRecord
                    $configurationVersion = getid3_lib::BigEndian2Int(substr($flvVideoHeader, 4, 1));
                    $avcProfileIndication = getid3_lib::BigEndian2Int(substr($flvVideoHeader, 5, 1));
                    $profileCompatibility = getid3_lib::BigEndian2Int(substr($flvVideoHeader, 6, 1));
                    $lengthSizeMinusOne = getid3_lib::BigEndian2Int(substr($flvVideoHeader, 7, 1));
                    $numOfSequenceParameterSets = getid3_lib::BigEndian2Int(substr($flvVideoHeader, 8, 1));

                    if (($numOfSequenceParameterSets & 0x1F) !== 0) {
                        //	there is at least one SequenceParameterSet
                        //	read size of the first SequenceParameterSet
                        $spsSize = getid3_lib::LittleEndian2Int(substr($flvVideoHeader, 9, 2));
                        //	read the first SequenceParameterSet
                        $sps = fread($fp, $spsSize);
                        if (strlen($sps) === $spsSize) {	//	make sure that whole SequenceParameterSet was red
                            $spsReader = new AvCSequenceParameterSetReader($sps);
                            $spsReader->readData();
                            $info['video']['resolution_x'] = $spsReader->getWidth();
                            $info['video']['resolution_y'] = $spsReader->getHeight();
                        }
                    }
                }
                // end: moysevichØgmail*com

            } elseif ($info['flv']['video']['videoCodec'] === GETID3_FLV_VIDEO_H263) {

                $pictureSizeType = (getid3_lib::BigEndian2Int(substr($flvVideoHeader, 3, 2))) >> 7;
                $pictureSizeType = $pictureSizeType & 0x0007;
                $info['flv']['header']['videoSizeType'] = $pictureSizeType;
                match ($pictureSizeType) {
                    0 => $this->handlePictureSizeType0($info, $flvVideoHeader),
                    1 => $this->handlePictureSizeType1($info, $flvVideoHeader),
                    2 => $info['video']['resolution_x'] = 352,
                    3 => $info['video']['resolution_x'] = 176,
                    4 => $info['video']['resolution_x'] = 128,
                    5 => $info['video']['resolution_x'] = 320,
                    6 => $info['video']['resolution_x'] = 160,
                    default => $info['video']['resolution_x'] = 0,
                };
            }
            $info['video']['pixel_aspect_ratio'] = $info['video']['resolution_x'] / $info['video']['resolution_y'];
        }
    }

    private function handlePictureSizeType0(array &$info, string $flvVideoHeader): void
    {
        $pictureSizeEnc = [
            'x' => getid3_lib::BigEndian2Int(substr($flvVideoHeader, 4, 2)),
            'y' => getid3_lib::BigEndian2Int(substr($flvVideoHeader, 5, 2)),
        ];
        $pictureSizeEnc['x'] >>= 7;
        $pictureSizeEnc['y'] >>= 7;
        $info['video']['resolution_x'] = $pictureSizeEnc['x'] & 0xFF;
        $info['video']['resolution_y'] = $pictureSizeEnc['y'] & 0xFF;
    }

    private function handlePictureSizeType1(array &$info, string $flvVideoHeader): void
    {
        $pictureSizeEnc = [
            'x' => getid3_lib::BigEndian2Int(substr($flvVideoHeader, 4, 3)),
            'y' => getid3_lib::BigEndian2Int(substr($flvVideoHeader, 6, 3)),
        ];
        $pictureSizeEnc['x'] >>= 7;
        $pictureSizeEnc['y'] >>= 7;
        $info['video']['resolution_x'] = $pictureSizeEnc['x'] & 0xFFFF;
        $info['video']['resolution_y'] = $pictureSizeEnc['y'] & 0xFFFF;
    }

    private function handleMetaTag(array &$flvFramecount, bool &$foundMeta, array &$info, int $dataLength, resource $fp): void
    {
        if (!$foundMeta) {
            $foundMeta = true;
            fseek($fp, -1, SEEK_CUR);
            $dataChunk = fread($fp, $dataLength);
            $amfStream = new AmfStream($dataChunk);
            $reader = new AmfReader($amfStream);
            $eventName = $reader->readData();
            $info['flv']['meta'][$eventName] = $reader->readData();
            unset($reader);

            $copyKeys = [
                'framerate' => 'frame_rate',
                'width' => 'resolution_x',
                'height' => 'resolution_y',
                'audiodatarate' => 'bitrate',
                'videodatarate' => 'bitrate',
            ];
            foreach ($copyKeys as $sourceKey => $destKey) {
                if (isset($info['flv']['meta']['onMetaData'][$sourceKey])) {
                    match ($sourceKey) {
                        'width', 'height' => $info['video'][$destKey] = intval(round($info['flv']['meta']['onMetaData'][$sourceKey])),
                        'audiodatarate' => $info['audio'][$destKey] = getid3_lib::CastAsInt(round($info['flv']['meta']['onMetaData'][$sourceKey] * 1000)),
                        'videodatarate', 'frame_rate' => $info['video'][$destKey] = $info['flv']['meta']['onMetaData'][$sourceKey],
                        default => null,
                    };
                }
            }
            if (!empty($info['flv']['meta']['onMetaData']['duration'])) {
                $foundValidMetaPlaytime = true;
            }
        }
    }

    public function flvAudioFormat(int $id): string|false
    {
        $flvAudioFormat = [
            0 => 'Linear PCM, platform endian',
            1 => 'ADPCM',
            2 => 'mp3',
            3 => 'Linear PCM, little endian',
            4 => 'Nellymoser 16kHz mono',
            5 => 'Nellymoser 8kHz mono',
            6 => 'Nellymoser',
            7 => 'G.711A-law logarithmic PCM',
            8 => 'G.711 mu-law logarithmic PCM',
            9 => 'reserved',
            10 => 'AAC',
            11 => false, // unknown?
            12 => false, // unknown?
            13 => false, // unknown?
            14 => 'mp3 8kHz',
            15 => 'Device-specific sound',
        ];
        return $flvAudioFormat[$id] ?? false;
    }

    public function flvAudioRate(int $id): int|false
    {
        $flvAudioRate = [
            0 => 5500,
            1 => 11025,
            2 => 22050,
            3 => 44100,
        ];
        return $flvAudioRate[$id] ?? false;
    }

    public function flvAudioBitDepth(int $id): int|false
    {
        $flvAudioBitDepth = [
            0 => 8,
            1 => 16,
        ];
        return $flvAudioBitDepth[$id] ?? false;
    }

    public function flvVideoCodec(int $id): string|false
    {
        $flvVideoCodec = [
            GETID3_FLV_VIDEO_H263 => 'Sorenson H.263',
            GETID3_FLV_VIDEO_SCREEN => 'Screen video',
            GETID3_FLV_VIDEO_VP6FLV => 'On2 VP6',
            GETID3_FLV_VIDEO_VP6FLV_ALPHA => 'On2 VP6 with alpha channel',
            GETID3_FLV_VIDEO_SCREENV2 => 'Screen video v2',
            GETID3_FLV_VIDEO_H264 => 'Sorenson H.264',
        ];
        return $flvVideoCodec[$id] ?? false;
    }
}

class AmfStream
{
    public string $bytes;
    public int $pos;

    public function __construct(string &$bytes)
    {
        $this->bytes = &$bytes;
        $this->pos = 0;
    }

    public function readByte(): int
    {
        return getid3_lib::BigEndian2Int(substr($this->bytes, $this->pos++, 1));
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
        return $val;
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

class AmfReader
{
    public AmfStream $stream;

    public function __construct(AmfStream &$stream)
    {
        $this->stream = &$stream;
    }

    public function readData()
    {
        $value = null;

        $type = $this->stream->readByte();
        return match ($type) {
            0 => $this->readDouble(),
            1 => $this->readBoolean(),
            2 => $this->readString(),
            3 => $this->readObject(),
            6 => null,
            8 => $this->readMixedArray(),
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

        while ($key = $this->stream->readUTF()) {
            $data[$key] = $this->readData();
        }
        if ($key === '' && $this->stream->peekByte() === 0x09) {
            $this->stream->readByte();
        }
        return $data;
    }

    public function readMixedArray(): array
    {
        $highestIndex = $this->stream->readLong();

        $data = [];

        while ($key = $this->stream->readUTF()) {
            if (is_numeric($key)) {
                $key = (float) $key;
            }
            $data[$key] = $this->readData();
        }
        if ($key === '' && $this->stream->peekByte() === 0x09) {
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
        $className = $this->stream->readUTF();
        return $this->readObject();
    }
}

class AvCSequenceParameterSetReader
{
    public string $sps;
    public int $start = 0;
    public int $currentBytes = 0;
    public int $currentBits = 0;
    public int $width;
    public int $height;

    public function __construct(string $sps)
    {
        $this->sps = $sps;
    }

    public function readData(): void
    {
        $this->skipBits(8);
        $this->skipBits(8);
        $profile = $this->getBits(8);	//	read profile
        $this->skipBits(16);
        $this->expGolombUe();	//	read sps id
        if (in_array($profile, [H264_PROFILE_HIGH, H264_PROFILE_HIGH10, H264_PROFILE_HIGH422, H264_PROFILE_HIGH444, H264_PROFILE_HIGH444_PREDICTIVE])) {
            if ($this->expGolombUe() === 3) {
                $this->skipBits(1);
            }
            $this->expGolombUe();
            $this->expGolombUe();
            $this->skipBits(1);
            if ($this->getBit()) {
                for ($i = 0; $i < 8; $i++) {
                    if ($this->getBit()) {
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
        $this->currentBytes += (int) floor($newBits / 8);
        $this->currentBits = $newBits % 8;
    }

    public function getBit(): int
    {
        $result = (getid3_lib::BigEndian2Int(substr($this->sps, $this->currentBytes, 1)) >> (7 - $this->currentBits)) & 0x01;
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
                // something is broken, this is an emergency escape to prevent infinite loops
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
        } else {
            return ($result + 1) >> 1;
        }
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
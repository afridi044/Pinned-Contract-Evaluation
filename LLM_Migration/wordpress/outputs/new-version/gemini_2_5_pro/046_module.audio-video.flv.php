<?php

declare(strict_types=1);

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//                                                             //
//  FLV module by Seth Kaufman <sethØwhirl-i-gig*com>          //
//                                                             //
//  * version 0.1 (26 June 2005)                               //
//                                                             //
//                                                             //
//  * version 0.1.1 (15 July 2005)                             //
//  minor modifications by James Heinrich <info@getid3.org>    //
//                                                             //
//  * version 0.2 (22 February 2006)                           //
//  Support for On2 VP6 codec and meta information             //
//    by Steve Webster <steve.websterØfeaturecreep*com>        //
//                                                             //
//  * version 0.3 (15 June 2006)                               //
//  Modified to not read entire file into memory               //
//    by James Heinrich <info@getid3.org>                      //
//                                                             //
//  * version 0.4 (07 December 2007)                           //
//  Bugfixes for incorrectly parsed FLV dimensions             //
//    and incorrect parsing of onMetaTag                       //
//    by Evgeny Moysevich <moysevichØgmail*com>                //
//                                                             //
//  * version 0.5 (21 May 2009)                                //
//  Fixed parsing of audio tags and added additional codec     //
//    details. The duration is now read from onMetaTag (if     //
//    exists), rather than parsing whole file                  //
//    by Nigel Barnes <ngbarnesØhotmail*com>                   //
//                                                             //
//  * version 0.6 (24 May 2009)                                //
//  Better parsing of files with h264 video                    //
//    by Evgeny Moysevich <moysevichØgmail*com>                //
//                                                             //
//  * version 0.6.1 (30 May 2011)                              //
//    prevent infinite loops in expGolombUe()                  //
//                                                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio-video.flv.php                                  //
// module for analyzing Shockwave Flash Video files            //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

class getid3_flv extends getid3_handler
{
	// FLV Tag Types
	private const TAG_AUDIO = 8;
	private const TAG_VIDEO = 9;
	private const TAG_META = 18;

	// FLV Video Codec IDs
	private const VIDEO_H263 = 2;
	private const VIDEO_SCREEN = 3;
	private const VIDEO_VP6FLV = 4;
	private const VIDEO_VP6FLV_ALPHA = 5;
	private const VIDEO_SCREENV2 = 6;
	private const VIDEO_H264 = 7;

	// H.264 / AVC Constants
	public const H264_AVC_SEQUENCE_HEADER = 0;
	public const H264_PROFILE_BASELINE = 66;
	public const H264_PROFILE_MAIN = 77;
	public const H264_PROFILE_EXTENDED = 88;
	public const H264_PROFILE_HIGH = 100;
	public const H264_PROFILE_HIGH10 = 110;
	public const H264_PROFILE_HIGH422 = 122;
	public const H264_PROFILE_HIGH444 = 144;
	public const H264_PROFILE_HIGH444_PREDICTIVE = 244;

	/**
	 * @var int break out of the loop if too many frames have been scanned; only scan this many if meta frame does not contain useful duration
	 */
	public int $max_frames = 100000;

	public function Analyze(): bool
	{
		$info = &$this->getid3->info;

		fseek($this->getid3->fp, $info['avdataoffset'], SEEK_SET);

		$FLVheader = fread($this->getid3->fp, 5);
		if (strlen($FLVheader) !== 5) {
			$info['error'][] = 'Failed to read 5-byte FLV header';
			return false;
		}

		$info['fileformat'] = 'flv';
		$info['flv']['header']['signature'] = substr($FLVheader, 0, 3);
		$info['flv']['header']['version'] = getid3_lib::BigEndian2Int(substr($FLVheader, 3, 1));
		$TypeFlags = getid3_lib::BigEndian2Int(substr($FLVheader, 4, 1));

		$magic = 'FLV';
		if ($info['flv']['header']['signature'] !== $magic) {
			$info['error'][] = 'Expecting "' . getid3_lib::PrintHexBytes($magic) . '" at offset ' . $info['avdataoffset'] . ', found "' . getid3_lib::PrintHexBytes($info['flv']['header']['signature']) . '"';
			unset($info['flv'], $info['fileformat']);
			return false;
		}

		$info['flv']['header']['hasAudio'] = (bool)($TypeFlags & 0x04);
		$info['flv']['header']['hasVideo'] = (bool)($TypeFlags & 0x01);

		$FrameSizeDataLength = getid3_lib::BigEndian2Int(fread($this->getid3->fp, 4));
		$FLVheaderFrameLength = 9;
		if ($FrameSizeDataLength > $FLVheaderFrameLength) {
			fseek($this->getid3->fp, $FrameSizeDataLength - $FLVheaderFrameLength, SEEK_CUR);
		}

		$Duration = 0;
		$found_video = false;
		$found_audio = false;
		$found_meta = false;
		$found_valid_meta_playtime = false;
		$tagParseCount = 0;
		$info['flv']['framecount'] = ['total' => 0, 'audio' => 0, 'video' => 0];

		while (((ftell($this->getid3->fp) + 16) < $info['avdataend']) && (($tagParseCount++ <= $this->max_frames) || !$found_valid_meta_playtime)) {
			$ThisTagHeader = fread($this->getid3->fp, 16);
			if (strlen($ThisTagHeader) < 16) {
				break; // Incomplete tag header, probably EOF
			}

			$TagType = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 4, 1));
			$DataLength = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 5, 3));
			$Timestamp = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 8, 3));
			$LastHeaderByte = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 15, 1));
			$NextOffset = ftell($this->getid3->fp) - 1 + $DataLength;

			if ($Timestamp > $Duration) {
				$Duration = $Timestamp;
			}

			$info['flv']['framecount']['total']++;

			switch ($TagType) {
				case self::TAG_AUDIO:
					$info['flv']['framecount']['audio']++;
					if (!$found_audio) {
						$found_audio = true;
						$info['flv']['audio']['audioFormat'] = ($LastHeaderByte >> 4) & 0x0F;
						$info['flv']['audio']['audioRate'] = ($LastHeaderByte >> 2) & 0x03;
						$info['flv']['audio']['audioSampleSize'] = ($LastHeaderByte >> 1) & 0x01;
						$info['flv']['audio']['audioType'] = $LastHeaderByte & 0x01;
					}
					break;

				case self::TAG_VIDEO:
					$info['flv']['framecount']['video']++;
					if (!$found_video) {
						$found_video = true;
						$info['flv']['video']['videoCodec'] = $LastHeaderByte & 0x07;

						$FLVvideoHeader = fread($this->getid3->fp, 11);

						if ($info['flv']['video']['videoCodec'] === self::VIDEO_H264) {
							// this code block contributed by: moysevichØgmail*com
							$AVCPacketType = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 0, 1));
							if ($AVCPacketType === self::H264_AVC_SEQUENCE_HEADER) {
								//	read AVCDecoderConfigurationRecord
								$numOfSequenceParameterSets = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 8, 1));

								if (($numOfSequenceParameterSets & 0x1F) !== 0) {
									//	there is at least one SequenceParameterSet
									//	read size of the first SequenceParameterSet
									$spsSize = getid3_lib::LittleEndian2Int(substr($FLVvideoHeader, 9, 2));
									//	read the first SequenceParameterSet
									$sps = fread($this->getid3->fp, $spsSize);
									if (strlen($sps) === $spsSize) {    //	make sure that whole SequenceParameterSet was read
										$spsReader = new AVCSequenceParameterSetReader($sps);
										$spsReader->readData();
										$info['video']['resolution_x'] = $spsReader->getWidth();
										$info['video']['resolution_y'] = $spsReader->getHeight();
									}
								}
							}
							// end: moysevichØgmail*com
						} elseif ($info['flv']['video']['videoCodec'] === self::VIDEO_H263) {
							$PictureSizeType = (getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 3, 2))) >> 7;
							$PictureSizeType &= 0x0007;
							$info['flv']['header']['videoSizeType'] = $PictureSizeType;

							switch ($PictureSizeType) {
								case 0:
									$PictureSizeEnc = [];
									$PictureSizeEnc['x'] = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 4, 2));
									$PictureSizeEnc['y'] = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 5, 2));
									$PictureSizeEnc['x'] >>= 7;
									$PictureSizeEnc['y'] >>= 7;
									$info['video']['resolution_x'] = $PictureSizeEnc['x'] & 0xFF;
									$info['video']['resolution_y'] = $PictureSizeEnc['y'] & 0xFF;
									break;
								case 1:
									$PictureSizeEnc = [];
									$PictureSizeEnc['x'] = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 4, 3));
									$PictureSizeEnc['y'] = getid3_lib::BigEndian2Int(substr($FLVvideoHeader, 6, 3));
									$PictureSizeEnc['x'] >>= 7;
									$PictureSizeEnc['y'] >>= 7;
									$info['video']['resolution_x'] = $PictureSizeEnc['x'] & 0xFFFF;
									$info['video']['resolution_y'] = $PictureSizeEnc['y'] & 0xFFFF;
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
						if (!empty($info['video']['resolution_y']) && $info['video']['resolution_y'] > 0) {
							$info['video']['pixel_aspect_ratio'] = ($info['video']['resolution_x'] ?? 0) / $info['video']['resolution_y'];
						}
					}
					break;

				case self::TAG_META:
					if (!$found_meta) {
						$found_meta = true;
						fseek($this->getid3->fp, -1, SEEK_CUR);
						$datachunk = fread($this->getid3->fp, $DataLength);
						$AMFstream = new AMFStream($datachunk);
						$reader = new AMFReader($AMFstream);
						$eventName = $reader->readData();
						if (is_string($eventName)) {
							$info['flv']['meta'][$eventName] = $reader->readData();
						}

						$copykeys = [
							'framerate' => 'frame_rate',
							'width' => 'resolution_x',
							'height' => 'resolution_y',
							'audiodatarate' => 'bitrate',
							'videodatarate' => 'bitrate'
						];
						foreach ($copykeys as $sourcekey => $destkey) {
							if (isset($info['flv']['meta']['onMetaData'][$sourcekey])) {
								switch ($sourcekey) {
									case 'width':
									case 'height':
										$info['video'][$destkey] = (int)round($info['flv']['meta']['onMetaData'][$sourcekey]);
										break;
									case 'audiodatarate':
										$info['audio'][$destkey] = getid3_lib::CastAsInt(round($info['flv']['meta']['onMetaData'][$sourcekey] * 1000));
										break;
									case 'videodatarate':
									case 'framerate':
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
			}
			fseek($this->getid3->fp, $NextOffset, SEEK_SET);
		}

		$info['playtime_seconds'] = $Duration / 1000;
		if ($info['playtime_seconds'] > 0) {
			$info['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];
		}

		if ($info['flv']['header']['hasAudio']) {
			$info['audio']['codec'] = $this->FLVaudioFormat($info['flv']['audio']['audioFormat'] ?? -1);
			$info['audio']['sample_rate'] = $this->FLVaudioRate($info['flv']['audio']['audioRate'] ?? -1);
			$info['audio']['bits_per_sample'] = $this->FLVaudioBitDepth($info['flv']['audio']['audioSampleSize'] ?? -1);
			$info['audio']['channels'] = ($info['flv']['audio']['audioType'] ?? 0) + 1; // 0=mono,1=stereo
			$info['audio']['lossless'] = (($info['flv']['audio']['audioFormat'] ?? -1) === 0); // 0=uncompressed
			$info['audio']['dataformat'] = 'flv';
		}
		if (!empty($info['flv']['header']['hasVideo'])) {
			$info['video']['codec'] = $this->FLVvideoCodec($info['flv']['video']['videoCodec'] ?? -1);
			$info['video']['dataformat'] = 'flv';
			$info['video']['lossless'] = false;
		}

		// Set information from meta
		if (!empty($info['flv']['meta']['onMetaData']['duration'])) {
			$info['playtime_seconds'] = $info['flv']['meta']['onMetaData']['duration'];
			if ($info['playtime_seconds'] > 0) {
				$info['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];
			}
		}
		if (isset($info['flv']['meta']['onMetaData']['audiocodecid'])) {
			$info['audio']['codec'] = $this->FLVaudioFormat((int)$info['flv']['meta']['onMetaData']['audiocodecid']);
		}
		if (isset($info['flv']['meta']['onMetaData']['videocodecid'])) {
			$info['video']['codec'] = $this->FLVvideoCodec((int)$info['flv']['meta']['onMetaData']['videocodecid']);
		}
		return true;
	}

	public function FLVaudioFormat(int $id): string|false
	{
		return match ($id) {
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
			14 => 'mp3 8kHz',
			15 => 'Device-specific sound',
			default => false,
		};
	}

	public function FLVaudioRate(int $id): int|false
	{
		return match ($id) {
			0 => 5500,
			1 => 11025,
			2 => 22050,
			3 => 44100,
			default => false,
		};
	}

	public function FLVaudioBitDepth(int $id): int|false
	{
		return match ($id) {
			0 => 8,
			1 => 16,
			default => false,
		};
	}

	public function FLVvideoCodec(int $id): string|false
	{
		return match ($id) {
			self::VIDEO_H263 => 'Sorenson H.263',
			self::VIDEO_SCREEN => 'Screen video',
			self::VIDEO_VP6FLV => 'On2 VP6',
			self::VIDEO_VP6FLV_ALPHA => 'On2 VP6 with alpha channel',
			self::VIDEO_SCREENV2 => 'Screen video v2',
			self::VIDEO_H264 => 'H.264/AVC',
			default => false,
		};
	}
}

class AMFStream
{
	public int $pos = 0;

	public function __construct(private readonly string $bytes)
	{
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
		if ($length <= 0) {
			return '';
		}
		$val = substr($this->bytes, $this->pos, $length);
		$this->pos += $length;
		return $val;
	}

	public function peekByte(): int
	{
		return getid3_lib::BigEndian2Int(substr($this->bytes, $this->pos, 1) ?: "\x00");
	}
}

class AMFReader
{
	public function __construct(private readonly AMFStream $stream)
	{
	}

	public function readData(): mixed
	{
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
		while (true) {
			$key = $this->stream->readUTF();
			if ($key === '') {
				break;
			}
			$data[$key] = $this->readData();
		}

		// Object end marker
		if ($this->stream->peekByte() === 0x09) {
			$this->stream->readByte();
		}
		return $data;
	}

	public function readMixedArray(): array
	{
		// Get highest numerical index - ignored
		$this->stream->readLong();

		return $this->readObject();
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
		$this->stream->readInt(); // timezone - ignored
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
		$this->stream->readUTF(); // className - ignored
		return $this->readObject();
	}
}

class AVCSequenceParameterSetReader
{
	private int $currentBytes = 0;
	private int $currentBits = 0;
	private ?int $width = null;
	private ?int $height = null;

	public function __construct(private readonly string $sps)
	{
	}

	public function readData(): void
	{
		$this->skipBits(8); // nal_unit_header
		$profile = $this->getBits(8);
		$this->skipBits(16);
		$this->expGolombUe(); // sps id

		if (in_array($profile, [
			getid3_flv::H264_PROFILE_HIGH,
			getid3_flv::H264_PROFILE_HIGH10,
			getid3_flv::H264_PROFILE_HIGH422,
			getid3_flv::H264_PROFILE_HIGH444,
			getid3_flv::H264_PROFILE_HIGH444_PREDICTIVE
		])) {
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
								$deltaScale = $this->expGolombSe();
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

	private function skipBits(int $bits): void
	{
		$newBits = $this->currentBits + $bits;
		$this->currentBytes += (int)floor($newBits / 8);
		$this->currentBits = $newBits % 8;
	}

	private function getBit(): int
	{
		$byte = substr($this->sps, $this->currentBytes, 1);
		if ($byte === false) {
			return 0; // Avoid errors on malformed streams
		}
		$result = (ord($byte) >> (7 - $this->currentBits)) & 0x01;
		$this->skipBits(1);
		return $result;
	}

	private function getBits(int $bits): int
	{
		$result = 0;
		for ($i = 0; $i < $bits; $i++) {
			$result = ($result << 1) + $this->getBit();
		}
		return $result;
	}

	private function expGolombUe(): int
	{
		$significantBits = 0;
		while ($this->getBit() === 0) {
			$significantBits++;
			if ($significantBits > 31) {
				// Emergency escape for malformed bitstreams
				return 0;
			}
		}
		return (1 << $significantBits) + $this->getBits($significantBits) - 1;
	}

	private function expGolombSe(): int
	{
		$result = $this->expGolombUe();
		if (($result & 0x01) === 0) {
			return -($result >> 1);
		}
		return ($result + 1) >> 1;
	}

	public function getWidth(): ?int
	{
		return $this->width;
	}

	public function getHeight(): ?int
	{
		return $this->height;
	}
}
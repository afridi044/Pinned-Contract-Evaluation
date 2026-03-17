<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio-video.riff.php                                 //
// module for analyzing RIFF files                             //
// multiple formats supported by this module:                  //
//    Wave, AVI, AIFF/AIFC, (MP3,AC3)/RIFF, Wavpack v3, 8SVX   //
// dependencies: module.audio.mp3.php                          //
//               module.audio.ac3.php                          //
//               module.audio.dts.php                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
* @todo Parse AC-3/DTS audio inside WAVE correctly
* @todo Rewrite RIFF parser totally
*/

getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.mp3.php', __FILE__, true);
getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.ac3.php', __FILE__, true);
getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.dts.php', __FILE__, true);

class getid3_riff extends getid3_handler
{

	public function Analyze() {
		$info = &$this->getid3->info;

		// initialize these values to an empty array, otherwise they default to NULL
		// and you can't append array values to a NULL value
		$info['riff'] = array('raw'=>array());

		// Shortcuts
		$thisfile_riff             = &$info['riff'];
		$thisfile_riff_raw         = &$thisfile_riff['raw'];
		$thisfile_audio            = &$info['audio'];
		$thisfile_video            = &$info['video'];
		$thisfile_audio_dataformat = &$thisfile_audio['dataformat'];
		$thisfile_riff_audio       = &$thisfile_riff['audio'];
		$thisfile_riff_video       = &$thisfile_riff['video'];

		$Original['avdataoffset'] = $info['avdataoffset'];
		$Original['avdataend']    = $info['avdataend'];

		$this->fseek($info['avdataoffset']);
		$RIFFheader = $this->fread(12);
		$offset = $this->ftell();
		$RIFFtype    = substr($RIFFheader, 0, 4);
		$RIFFsize    = substr($RIFFheader, 4, 4);
		$RIFFsubtype = substr($RIFFheader, 8, 4);

		switch ($RIFFtype) {

			case 'FORM':  // AIFF, AIFC
				$info['fileformat']   = 'aiff';
				$thisfile_riff['header_size'] = $this->EitherEndian2Int($RIFFsize);
				$thisfile_riff[$RIFFsubtype]  = $this->ParseRIFF($offset, ($offset + $thisfile_riff['header_size'] - 4));
				break;

			case 'RIFF':  // AVI, WAV, etc
			case 'SDSS':  // SDSS is identical to RIFF, just renamed. Used by SmartSound QuickTracks (www.smartsound.com)
			case 'RMP3':  // RMP3 is identical to RIFF, just renamed. Used by [unknown program] when creating RIFF-MP3s
				$info['fileformat']   = 'riff';
				$thisfile_riff['header_size'] = $this->EitherEndian2Int($RIFFsize);
				if ($RIFFsubtype == 'RMP3') {
					// RMP3 is identical to WAVE, just renamed. Used by [unknown program] when creating RIFF-MP3s
					$RIFFsubtype = 'WAVE';
				}
				$thisfile_riff[$RIFFsubtype]  = $this->ParseRIFF($offset, ($offset + $thisfile_riff['header_size'] - 4));
				if (($info['avdataend'] - $info['filesize']) == 1) {
					// LiteWave appears to incorrectly *not* pad actual output file
					// to nearest WORD boundary so may appear to be short by one
					// byte, in which case - skip warning
					$info['avdataend'] = $info['filesize'];
				}

				$nextRIFFoffset = $Original['avdataoffset'] + 8 + $thisfile_riff['header_size']; // 8 = "RIFF" + 32-bit offset
				while ($nextRIFFoffset < min($info['filesize'], $info['avdataend'])) {
					try {
						$this->fseek($nextRIFFoffset);
					} catch (getid3_exception $e) {
						if ($e->getCode() == 10) {
							//$this->warning('RIFF parser: '.$e->getMessage());
							$this->error('AVI extends beyond '.round(PHP_INT_MAX / 1073741824).'GB and PHP filesystem functions cannot read that far, playtime may be wrong');
							$this->warning('[avdataend] value may be incorrect, multiple AVIX chunks may be present');
							break;
						} else {
							throw $e;
						}
					}
					$nextRIFFheader = $this->fread(12);
					if ($nextRIFFoffset == ($info['avdataend'] - 1)) {
						if (substr($nextRIFFheader, 0, 1) == "\x00") {
							// RIFF padded to WORD boundary, we're actually already at the end
							break;
						}
					}
					$nextRIFFheaderID =                         substr($nextRIFFheader, 0, 4);
					$nextRIFFsize     = $this->EitherEndian2Int(substr($nextRIFFheader, 4, 4));
					$nextRIFFtype     =                         substr($nextRIFFheader, 8, 4);
					$chunkdata = array();
					$chunkdata['offset'] = $nextRIFFoffset + 8;
					$chunkdata['size']   = $nextRIFFsize;
					$nextRIFFoffset = $chunkdata['offset'] + $chunkdata['size'];

					switch ($nextRIFFheaderID) {

						case 'RIFF':
							$chunkdata['chunks'] = $this->ParseRIFF($chunkdata['offset'] + 4, $nextRIFFoffset);

							if (!isset($thisfile_riff[$nextRIFFtype])) {
								$thisfile_riff[$nextRIFFtype] = array();
							}
							$thisfile_riff[$nextRIFFtype][] = $chunkdata;
							break;

						case 'JUNK':
							// ignore
							$thisfile_riff[$nextRIFFheaderID][] = $chunkdata;
							break;

						case 'IDVX':
							$info['divxtag']['comments'] = self::ParseDIVXTAG($this->fread($chunkdata['size']));
							break;

						default:
							if ($info['filesize'] == ($chunkdata['offset'] - 8 + 128)) {
								$DIVXTAG = $nextRIFFheader.$this->fread(128 - 12);
								if (substr($DIVXTAG, -7) == 'DIVXTAG') {
									// DIVXTAG is supposed to be inside an IDVX chunk in a LIST chunk, but some bad encoders just slap it on the end of a file
									$this->warning('Found wrongly-structured DIVXTAG at offset '.($this->ftell() - 128).', parsing anyway');
									$info['divxtag']['comments'] = self::ParseDIVXTAG($DIVXTAG);
									break 2;
								}
							}
							$this->warning('Expecting "RIFF|JUNK|IDVX" at '.$nextRIFFoffset.', found "'.$nextRIFFheaderID.'" ('.getid3_lib::PrintHexBytes($nextRIFFheaderID).') - skipping rest of file');
							break 2;

					}

				}
				if ($RIFFsubtype == 'WAVE') {
					$thisfile_riff_WAVE = &$thisfile_riff['WAVE'];
				}
				break;

			default:
				$this->error('Cannot parse RIFF (this is maybe not a RIFF / WAV / AVI file?) - expecting "FORM|RIFF|SDSS|RMP3" found "'.$RIFFsubtype.'" instead');
				unset($info['fileformat']);
				return false;
		}

		$streamindex = 0;
		switch ($RIFFsubtype) {
			case 'WAVE':
				if (empty($thisfile_audio['bitrate_mode'])) {
					$thisfile_audio['bitrate_mode'] = 'cbr';
				}
				if (empty($thisfile_audio_dataformat)) {
					$thisfile_audio_dataformat = 'wav';
				}

				if (isset($thisfile_riff_WAVE['data'][0]['offset'])) {
					$info['avdataoffset'] = $thisfile_riff_WAVE['data'][0]['offset'] + 8;
					$info['avdataend']    = $info['avdataoffset'] + $thisfile_riff_WAVE['data'][0]['size'];
				}
				if (isset($thisfile_riff_WAVE['fmt '][0]['data'])) {

					$thisfile_riff_audio[$streamindex] = self::parseWAVEFORMATex($thisfile_riff_WAVE['fmt '][0]['data']);
					$thisfile_audio['wformattag'] = $thisfile_riff_audio[$streamindex]['raw']['wFormatTag'];
					if (!isset($thisfile_riff_audio[$streamindex]['bitrate']) || ($thisfile_riff_audio[$streamindex]['bitrate'] == 0)) {
						$info['error'][] = 'Corrupt RIFF file: bitrate_audio == zero';
						return false;
					}
					$thisfile_riff_raw['fmt '] = $thisfile_riff_audio[$streamindex]['raw'];
					unset($thisfile_riff_audio[$streamindex]['raw']);
					$thisfile_audio['streams'][$streamindex] = $thisfile_riff_audio[$streamindex];

					$thisfile_audio = getid3_lib::array_merge_noclobber($thisfile_audio, $thisfile_riff_audio[$streamindex]);
					if (substr($thisfile_audio['codec'], 0, strlen('unknown: 0x')) == 'unknown: 0x') {
						$info['warning'][] = 'Audio codec = '.$thisfile_audio['codec'];
					}
					$thisfile_audio['bitrate'] = $thisfile_riff_audio[$streamindex]['bitrate'];

					if (empty($info['playtime_seconds'])) { // may already be set (e.g. DTS-WAV)
						$info['playtime_seconds'] = (float) ((($info['avdataend'] - $info['avdataoffset']) * 8) / $thisfile_audio['bitrate']);
					}

					$thisfile_audio['lossless'] = false;
					if (isset($thisfile_riff_WAVE['data'][0]['offset']) && isset($thisfile_riff_raw['fmt ']['wFormatTag'])) {
						switch ($thisfile_riff_raw['fmt ']['wFormatTag']) {

							case 0x0001:  // PCM
								$thisfile_audio['lossless'] = true;
								break;

							case 0x2000:  // AC-3
								$thisfile_audio_dataformat = 'ac3';
								break;

							default:
								// do nothing
								break;

						}
					}
					$thisfile_audio['streams'][$streamindex]['wformattag']   = $thisfile_audio['wformattag'];
					$thisfile_audio['streams'][$streamindex]['bitrate_mode'] = $thisfile_audio['bitrate_mode'];
					$thisfile_audio['streams'][$streamindex]['lossless']     = $thisfile_audio['lossless'];
					$thisfile_audio['streams'][$streamindex]['dataformat']   = $thisfile_audio_dataformat;
				}

				if (isset($thisfile_riff_WAVE['rgad'][0]['data'])) {

					// shortcuts
					$rgadData = &$thisfile_riff_WAVE['rgad'][0]['data'];
					$thisfile_riff_raw['rgad']    = array('track'=>array(), 'album'=>array());
					$thisfile_riff_raw_rgad       = &$thisfile_riff_raw['rgad'];
					$thisfile_riff_raw_rgad_track = &$thisfile_riff_raw_rgad['track'];
					$thisfile_riff_raw_rgad_album = &$thisfile_riff_raw_rgad['album'];

					$thisfile_riff_raw_rgad['fPeakAmplitude']      = getid3_lib::LittleEndian2Float(substr($rgadData, 0, 4));
					$thisfile_riff_raw_rgad['nRadioRgAdjust']      =        $this->EitherEndian2Int(substr($rgadData, 4, 2));
					$thisfile_riff_raw_rgad['nAudiophileRgAdjust'] =        $this->EitherEndian2Int(substr($rgadData, 6, 2));

					$nRadioRgAdjustBitstring      = str_pad(getid3_lib::Dec2Bin($thisfile_riff_raw_rgad['nRadioRgAdjust']), 16, '0', STR_PAD_LEFT);
					$nAudiophileRgAdjustBitstring = str_pad(getid3_lib::Dec2Bin($thisfile_riff_raw_rgad['nAudiophileRgAdjust']), 16, '0', STR_PAD_LEFT);
					$thisfile_riff_raw_rgad_track['name']       = getid3_lib::Bin2Dec(substr($nRadioRgAdjustBitstring, 0, 3));
					$thisfile_riff_raw_rgad_track['originator'] = getid3_lib::Bin2Dec(substr($nRadioRgAdjustBitstring, 3, 3));
					$thisfile_riff_raw_rgad_track['signbit']    = getid3_lib::Bin2Dec(substr($nRadioRgAdjustBitstring, 6, 1));
					$thisfile_riff_raw_rgad_track['adjustment'] = getid3_lib::Bin2Dec(substr($nRadioRgAdjustBitstring, 7, 9));
					$thisfile_riff_raw_rgad_album['name']       = getid3_lib::Bin2Dec(substr($nAudiophileRgAdjustBitstring, 0, 3));
					$thisfile_riff_raw_rgad_album['originator'] = getid3_lib::Bin2Dec(substr($nAudiophileRgAdjustBitstring, 3, 3));
					$thisfile_riff_raw_rgad_album['signbit']    = getid3_lib::Bin2Dec(substr($nAudiophileRgAdjustBitstring, 6, 1));
					$thisfile_riff_raw_rgad_album['adjustment'] = getid3_lib::Bin2Dec(substr($nAudiophileRgAdjustBitstring, 7, 9));

					$thisfile_riff['rgad']['peakamplitude'] = $thisfile_riff_raw_rgad['fPeakAmplitude'];
					if (($thisfile_riff_raw_rgad_track['name'] != 0) && ($thisfile_riff_raw_rgad_track['originator'] != 0)) {
						$thisfile_riff['rgad']['track']['name']            = getid3_lib::RGADnameLookup($thisfile_riff_raw_rgad_track['name']);
						$thisfile_riff['rgad']['track']['originator']      = getid3_lib::RGADoriginatorLookup($thisfile_riff_raw_rgad_track['originator']);
						$thisfile_riff['rgad']['track']['adjustment']      = getid3_lib::RGADadjustmentLookup($thisfile_riff_raw_rgad_track['adjustment'], $thisfile_riff_raw_rgad_track['signbit']);
					}
					if (($thisfile_riff_raw_rgad_album['name'] != 0) && ($thisfile_riff_raw_rgad_album['originator'] != 0)) {
						$thisfile_riff['rgad']['album']['name']       = getid3_lib::RGADnameLookup($thisfile_riff_raw_rgad_album['name']);
						$thisfile_riff['rgad']['album']['originator'] = getid3_lib::RGADoriginatorLookup($thisfile_riff_raw_rgad_album['originator']);
						$thisfile_riff['rgad']['album']['adjustment'] = getid3_lib::RGADadjustmentLookup($thisfile_riff_raw_rgad_album['adjustment'], $thisfile_riff_raw_rgad_album['signbit']);
					}
				}

				if (isset($thisfile_riff_WAVE['fact'][0]['data'])) {
					$thisfile_riff_raw['fact']['NumberOfSamples'] = $this->EitherEndian2Int(substr($thisfile_riff_WAVE['fact'][0]['data'], 0, 4));

					// This should be a good way of calculating exact playtime,
					// but some sample files have had incorrect number of samples,
					// so cannot use this method

					// if (!empty($thisfile_riff_raw['fmt ']['nSamplesPerSec'])) {
					//     $info['playtime_seconds'] = (float) $thisfile_riff_raw['fact']['NumberOfSamples'] / $thisfile_riff_raw['fmt ']['nSamplesPerSec'];
					// }
				}
				if (!empty($thisfile_riff_raw['fmt ']['nAvgBytesPerSec'])) {
					$thisfile_audio['bitrate'] = getid3_lib::CastAsInt($thisfile_riff_raw['fmt ']['nAvgBytesPerSec'] * 8);
				}

				if (isset($thisfile_riff_WAVE['bext'][0]['data'])) {
					// shortcut
					$thisfile_riff_WAVE_bext_0 = &$thisfile_riff_WAVE['bext'][0];

					$thisfile_riff_WAVE_bext_0['title']          =                         trim(substr($thisfile_riff_WAVE_bext_0['data'],   0, 256));
					$thisfile_riff_WAVE_bext_0['author']         =                         trim(substr($thisfile_riff_WAVE_bext_0['data'], 256,  32));
					$thisfile_riff_WAVE_bext_0['reference']      =                         trim(substr($thisfile_riff_WAVE_bext_0['data'], 288,  32));
					$thisfile_riff_WAVE_bext_0['origin_date']    =                              substr($thisfile_riff_WAVE_bext_0['data'], 320,  10);
					$thisfile_riff_WAVE_bext_0['origin_time']    =                              substr($thisfile_riff_WAVE_bext_0['data'], 330,   8);
					$thisfile_riff_WAVE_bext_0['time_reference'] = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_bext_0['data'], 338,   8));
					$thisfile_riff_WAVE_bext_0['bwf_version']    = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_bext_0['data'], 346,   1));
					$thisfile_riff_WAVE_bext_0['reserved']       =                              substr($thisfile_riff_WAVE_bext_0['data'], 347, 254);
					$thisfile_riff_WAVE_bext_0['coding_history'] =         explode("\r\n", trim(substr($thisfile_riff_WAVE_bext_0['data'], 601)));
					if (preg_match('#^([0-9]{4}).([0-9]{2}).([0-9]{2})$#', $thisfile_riff_WAVE_bext_0['origin_date'], $matches_bext_date)) {
						if (preg_match('#^([0-9]{2}).([0-9]{2}).([0-9]{2})$#', $thisfile_riff_WAVE_bext_0['origin_time'], $matches_bext_time)) {
							list($dummy, $bext_timestamp['year'], $bext_timestamp['month'],  $bext_timestamp['day'])    = $matches_bext_date;
							list($dummy, $bext_timestamp['hour'], $bext_timestamp['minute'], $bext_timestamp['second']) = $matches_bext_time;
							$thisfile_riff_WAVE_bext_0['origin_date_unix'] = gmmktime($bext_timestamp['hour'], $bext_timestamp['minute'], $bext_timestamp['second'], $bext_timestamp['month'], $bext_timestamp['day'], $bext_timestamp['year']);
						} else {
							$info['warning'][] = 'RIFF.WAVE.BEXT.origin_time is invalid';
						}
					} else {
						$info['warning'][] = 'RIFF.WAVE.BEXT.origin_date is invalid';
					}
					$thisfile_riff['comments']['author'][] = $thisfile_riff_WAVE_bext_0['author'];
					$thisfile_riff['comments']['title'][]  = $thisfile_riff_WAVE_bext_0['title'];
				}

				if (isset($thisfile_riff_WAVE['MEXT'][0]['data'])) {
					// shortcut
					$thisfile_riff_WAVE_MEXT_0 = &$thisfile_riff_WAVE['MEXT'][0];

					$thisfile_riff_WAVE_MEXT_0['raw']['sound_information']      = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_MEXT_0['data'], 0, 2));
					$thisfile_riff_WAVE_MEXT_0['flags']['homogenous']           = (bool) ($thisfile_riff_WAVE_MEXT_0['raw']['sound_information'] & 0x0001);
					if ($thisfile_riff_WAVE_MEXT_0['flags']['homogenous']) {
						$thisfile_riff_WAVE_MEXT_0['flags']['padding']          = ($thisfile_riff_WAVE_MEXT_0['raw']['sound_information'] & 0x0002) ? false : true;
						$thisfile_riff_WAVE_MEXT_0['flags']['22_or_44']         =        (bool) ($thisfile_riff_WAVE_MEXT_0['raw']['sound_information'] & 0x0004);
						$thisfile_riff_WAVE_MEXT_0['flags']['free_format']      =        (bool) ($thisfile_riff_WAVE_MEXT_0['raw']['sound_information'] & 0x0008);

						$thisfile_riff_WAVE_MEXT_0['nominal_frame_size']        = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_MEXT_0['data'], 2, 2));
					}
					$thisfile_riff_WAVE_MEXT_0['anciliary_data_length']         = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_MEXT_0['data'], 6, 2));
					$thisfile_riff_WAVE_MEXT_0['raw']['anciliary_data_def']     = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_MEXT_0['data'], 8, 2));
					$thisfile_riff_WAVE_MEXT_0['flags']['anciliary_data_left']  = (bool) ($thisfile_riff_WAVE_MEXT_0['raw']['anciliary_data_def'] & 0x0001);
					$thisfile_riff_WAVE_MEXT_0['flags']['anciliary_data_free']  = (bool) ($thisfile_riff_WAVE_MEXT_0['raw']['anciliary_data_def'] & 0x0002);
					$thisfile_riff_WAVE_MEXT_0['flags']['anciliary_data_right'] = (bool) ($thisfile_riff_WAVE_MEXT_0['raw']['anciliary_data_def'] & 0x0004);
				}

				if (isset($thisfile_riff_WAVE['cart'][0]['data'])) {
					// shortcut
					$thisfile_riff_WAVE_cart_0 = &$thisfile_riff_WAVE['cart'][0];

					$thisfile_riff_WAVE_cart_0['version']              =                              substr($thisfile_riff_WAVE_cart_0['data'],   0,  4);
					$thisfile_riff_WAVE_cart_0['title']                =                         trim(substr($thisfile_riff_WAVE_cart_0['data'],   4, 64));
					$thisfile_riff_WAVE_cart_0['artist']               =                         trim(substr($thisfile_riff_WAVE_cart_0['data'],  68, 64));
					$thisfile_riff_WAVE_cart_0['cut_id']               =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 132, 64));
					$thisfile_riff_WAVE_cart_0['client_id']            =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 196, 64));
					$thisfile_riff_WAVE_cart_0['category']             =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 260, 64));
					$thisfile_riff_WAVE_cart_0['classification']       =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 324, 64));
					$thisfile_riff_WAVE_cart_0['out_cue']              =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 388, 64));
					$thisfile_riff_WAVE_cart_0['start_date']           =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 452, 10));
					$thisfile_riff_WAVE_cart_0['start_time']           =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 462,  8));
					$thisfile_riff_WAVE_cart_0['end_date']             =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 470, 10));
					$thisfile_riff_WAVE_cart_0['end_time']             =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 480,  8));
					$thisfile_riff_WAVE_cart_0['producer_app_id']      =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 488, 64));
					$thisfile_riff_WAVE_cart_0['producer_app_version'] =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 552, 64));
					$thisfile_riff_WAVE_cart_0['user_defined_text']    =                         trim(substr($thisfile_riff_WAVE_cart_0['data'], 616, 64));
					$thisfile_riff_WAVE_cart_0['zero_db_reference']    = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_cart_0['data'], 680,  4), true);
					for ($i = 0; $i < 8; $i++) {
						$thisfile_riff_WAVE_cart_0['post_time'][$i]['usage_fourcc'] =                  substr($thisfile_riff_WAVE_cart_0['data'], 684 + ($i * 8), 4);
						$thisfile_riff_WAVE_cart_0['post_time'][$i]['timer_value']  = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE_cart_0['data'], 684 + ($i * 8) + 4, 4));
					}
					$thisfile_riff_WAVE_cart_0['url']              =                 trim(substr($thisfile_riff_WAVE_cart_0['data'],  748, 1024));
					$thisfile_riff_WAVE_cart_0['tag_text']         = explode("\r\n", trim(substr($thisfile_riff_WAVE_cart_0['data'], 1772)));

					$thisfile_riff['comments']['artist'][] = $thisfile_riff_WAVE_cart_0['artist'];
					$thisfile_riff['comments']['title'][]  = $thisfile_riff_WAVE_cart_0['title'];
				}

				if (isset($thisfile_riff_WAVE['SNDM'][0]['data'])) {
					// SoundMiner metadata

					// shortcuts
					$thisfile_riff_WAVE_SNDM_0      = &$thisfile_riff_WAVE['SNDM'][0];
					$thisfile_riff_WAVE_SNDM_0_data = &$thisfile_riff_WAVE_SNDM_0['data'];
					$SNDM_startoffset = 0;
					$SNDM_endoffset   = $thisfile_riff_WAVE_SNDM_0['size'];

					while ($SNDM_startoffset < $SNDM_endoffset) {
						$SNDM_thisTagOffset = 0;
						$SNDM_thisTagSize      = getid3_lib::BigEndian2Int(substr($thisfile_riff_WAVE_SNDM_0_data, $SNDM_startoffset + $SNDM_thisTagOffset, 4));
						$SNDM_thisTagOffset += 4;
						$SNDM_thisTagKey       =                           substr($thisfile_riff_WAVE_SNDM_0_data, $SNDM_startoffset + $SNDM_thisTagOffset, 4);
						$SNDM_thisTagOffset += 4;
						$SNDM_thisTagDataSize  = getid3_lib::BigEndian2Int(substr($thisfile_riff_WAVE_SNDM_0_data, $SNDM_startoffset + $SNDM_thisTagOffset, 2));
						$SNDM_thisTagOffset += 2;
						$SNDM_thisTagDataFlags = getid3_lib::BigEndian2Int(substr($thisfile_riff_WAVE_SNDM_0_data, $SNDM_startoffset + $SNDM_thisTagOffset, 2));
						$SNDM_thisTagOffset += 2;
						$SNDM_thisTagDataText =                            substr($thisfile_riff_WAVE_SNDM_0_data, $SNDM_startoffset + $SNDM_thisTagOffset, $SNDM_thisTagDataSize);
						$SNDM_thisTagOffset += $SNDM_thisTagDataSize;

						if ($SNDM_thisTagSize != (4 + 4 + 2 + 2 + $SNDM_thisTagDataSize)) {
							$info['warning'][] = 'RIFF.WAVE.SNDM.data contains tag not expected length (expected: '.$SNDM_thisTagSize.', found: '.(4 + 4 + 2 + 2 + $SNDM_thisTagDataSize).') at offset '.$SNDM_startoffset.' (file offset '.($thisfile_riff_WAVE_SNDM_0['offset'] + $SNDM_startoffset).')';
							break;
						} elseif ($SNDM_thisTagSize <= 0) {
							$info['warning'][] = 'RIFF.WAVE.SNDM.data contains zero-size tag at offset '.$SNDM_startoffset.' (file offset '.($thisfile_riff_WAVE_SNDM_0['offset'] + $SNDM_startoffset).')';
							break;
						}
						$SNDM_startoffset += $SNDM_thisTagSize;

						$thisfile_riff_WAVE_SNDM_0['parsed_raw'][$SNDM_thisTagKey] = $SNDM_thisTagDataText;
						if ($parsedkey = self::waveSNDMtagLookup($SNDM_thisTagKey)) {
							$thisfile_riff_WAVE_SNDM_0['parsed'][$parsedkey] = $SNDM_thisTagDataText;
						} else {
							$info['warning'][] = 'RIFF.WAVE.SNDM contains unknown tag "'.$SNDM_thisTagKey.'" at offset '.$SNDM_startoffset.' (file offset '.($thisfile_riff_WAVE_SNDM_0['offset'] + $SNDM_startoffset).')';
						}
					}

					$tagmapping = array(
						'tracktitle'=>'title',
						'category'  =>'genre',
						'cdtitle'   =>'album',
						'tracktitle'=>'title',
					);
					foreach ($tagmapping as $fromkey => $tokey) {
						if (isset($thisfile_riff_WAVE_SNDM_0['parsed'][$fromkey])) {
							$thisfile_riff['comments'][$tokey][] = $thisfile_riff_WAVE_SNDM_0['parsed'][$fromkey];
						}
					}
				}

				if (isset($thisfile_riff_WAVE['iXML'][0]['data'])) {
					// requires functions simplexml_load_string and get_object_vars
					if ($parsedXML = getid3_lib::XML2array($thisfile_riff_WAVE['iXML'][0]['data'])) {
						$thisfile_riff_WAVE['iXML'][0]['parsed'] = $parsedXML;
						if (isset($parsedXML['SPEED']['MASTER_SPEED'])) {
							@list($numerator, $denominator) = explode('/', $parsedXML['SPEED']['MASTER_SPEED']);
							$thisfile_riff_WAVE['iXML'][0]['master_speed'] = $numerator / ($denominator ? $denominator : 1000);
						}
						if (isset($parsedXML['SPEED']['TIMECODE_RATE'])) {
							@list($numerator, $denominator) = explode('/', $parsedXML['SPEED']['TIMECODE_RATE']);
							$thisfile_riff_WAVE['iXML'][0]['timecode_rate'] = $numerator / ($denominator ? $denominator : 1000);
						}
						if (isset($parsedXML['SPEED']['TIMESTAMP_SAMPLES_SINCE_MIDNIGHT_LO']) && !empty($parsedXML['SPEED']['TIMESTAMP_SAMPLE_RATE']) && !empty($thisfile_riff_WAVE['iXML'][0]['timecode_rate'])) {
							$samples_since_midnight = floatval(ltrim($parsedXML['SPEED']['TIMESTAMP_SAMPLES_SINCE_MIDNIGHT_HI'].$parsedXML['SPEED']['TIMESTAMP_SAMPLES_SINCE_MIDNIGHT_LO'], '0'));
							$thisfile_riff_WAVE['iXML'][0]['timecode_seconds'] = $samples_since_midnight / $parsedXML['SPEED']['TIMESTAMP_SAMPLE_RATE'];
							$h = floor( $thisfile_riff_WAVE['iXML'][0]['timecode_seconds']       / 3600);
							$m = floor(($thisfile_riff_WAVE['iXML'][0]['timecode_seconds'] - ($h * 3600))      / 60);
							$s = floor( $thisfile_riff_WAVE['iXML'][0]['timecode_seconds'] - ($h * 3600) - ($m * 60));
							$f =       ($thisfile_riff_WAVE['iXML'][0]['timecode_seconds'] - ($h * 3600) - ($m * 60) - $s) * $thisfile_riff_WAVE['iXML'][0]['timecode_rate'];
							$thisfile_riff_WAVE['iXML'][0]['timecode_string']       = sprintf('%02d:%02d:%02d:%05.2f', $h, $m, $s,       $f);
							$thisfile_riff_WAVE['iXML'][0]['timecode_string_round'] = sprintf('%02d:%02d:%02d:%02d',   $h, $m, $s, round($f));
						}
						unset($parsedXML);
					}
				}



				if (!isset($thisfile_audio['bitrate']) && isset($thisfile_riff_audio[$streamindex]['bitrate'])) {
					$thisfile_audio['bitrate'] = $thisfile_riff_audio[$streamindex]['bitrate'];
					$info['playtime_seconds'] = (float) ((($info['avdataend'] - $info['avdataoffset']) * 8) / $thisfile_audio['bitrate']);
				}

				if (!empty($info['wavpack'])) {
					$thisfile_audio_dataformat = 'wavpack';
					$thisfile_audio['bitrate_mode'] = 'vbr';
					$thisfile_audio['encoder']      = 'WavPack v'.$info['wavpack']['version'];

					// Reset to the way it was - RIFF parsing will have messed this up
					$info['avdataend']        = $Original['avdataend'];
					$thisfile_audio['bitrate'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['playtime_seconds'];

					$this->fseek($info['avdataoffset'] - 44);
					$RIFFdata = $this->fread(44);
					$OrignalRIFFheaderSize = getid3_lib::LittleEndian2Int(substr($RIFFdata,  4, 4)) +  8;
					$OrignalRIFFdataSize   = getid3_lib::LittleEndian2Int(substr($RIFFdata, 40, 4)) + 44;

					if ($OrignalRIFFheaderSize > $OrignalRIFFdataSize) {
						$info['avdataend'] -= ($OrignalRIFFheaderSize - $OrignalRIFFdataSize);
						$this->fseek($info['avdataend']);
						$RIFFdata .= $this->fread($OrignalRIFFheaderSize - $OrignalRIFFdataSize);
					}

					// move the data chunk after all other chunks (if any)
					// so that the RIFF parser doesn't see EOF when trying
					// to skip over the data chunk
					$RIFFdata = substr($RIFFdata, 0, 36).substr($RIFFdata, 44).substr($RIFFdata, 36, 8);
					$getid3_riff = new getid3_riff($this->getid3);
					$getid3_riff->ParseRIFFdata($RIFFdata);
					unset($getid3_riff);
				}

				if (isset($thisfile_riff_raw['fmt ']['wFormatTag'])) {
					switch ($thisfile_riff_raw['fmt ']['wFormatTag']) {
						case 0x0001: // PCM
							if (!empty($info['ac3'])) {
								// Dolby Digital WAV files masquerade as PCM-WAV, but they're not
								$thisfile_audio['wformattag']  = 0x2000;
								$thisfile_audio['codec']       = self::wFormatTagLookup($thisfile_audio['wformattag']);
								$thisfile_audio['lossless']    = false;
								$thisfile_audio['bitrate']     = $info['ac3']['bitrate'];
								$thisfile_audio['sample_rate'] = $info['ac3']['sample_rate'];
							}
							if (!empty($info['dts'])) {
								// Dolby DTS files masquerade as PCM-WAV, but they're not
								$thisfile_audio['wformattag']  = 0x2001;
								$thisfile_audio['codec']       = self::wFormatTagLookup($thisfile_audio['wformattag']);
								$thisfile_audio['lossless']    = false;
								$thisfile_audio['bitrate']     = $info['dts']['bitrate'];
								$thisfile_audio['sample_rate'] = $info['dts']['sample_rate'];
							}
							break;
						case 0x08AE: // ClearJump LiteWave
							$thisfile_audio['bitrate_mode'] = 'vbr';
							$thisfile_audio_dataformat   = 'litewave';

							//typedef struct tagSLwFormat {
							//  WORD    m_wCompFormat;     // low byte defines compression method, high byte is compression flags
							//  DWORD   m_dwScale;         // scale factor for lossy compression
							//  DWORD   m_dwBlockSize;     // number of samples in encoded blocks
							//  WORD    m_wQuality;        // alias for the scale factor
							//  WORD    m_wMarkDistance;   // distance between marks in bytes
							//  WORD    m_wReserved;
							//
							//  //following paramters are ignored if CF_FILESRC is not set
							//  DWORD   m_dwOrgSize;       // original file size in bytes
							//  WORD    m_bFactExists;     // indicates if 'fact' chunk exists in the original file
							//  DWORD   m_dwRiffChunkSize; // riff chunk size in the original file
							//
							//  PCMWAVEFORMAT m_OrgWf;     // original wave format
							// }SLwFormat, *PSLwFormat;

							// shortcut
							$thisfile_riff['litewave']['raw'] = array();
							$riff_litewave     = &$thisfile_riff['litewave'];
							$riff_litewave_raw = &$riff_litewave['raw'];

							$flags = array(
								'compression_method' => 1,
								'compression_flags'  => 1,
								'm_dwScale'          => 4,
								'm_dwBlockSize'      => 4,
								'm_wQuality'         => 2,
								'm_wMarkDistance'    => 2,
								'm_wReserved'        => 2,
								'm_dwOrgSize'        => 4,
								'm_bFactExists'      => 2,
								'm_dwRiffChunkSize'  => 4,
							);
							$litewave_offset = 18;
							foreach ($flags as $flag => $length) {
								$riff_litewave_raw[$flag] = getid3_lib::LittleEndian2Int(substr($thisfile_riff_WAVE['fmt '][0]['data'], $litewave_offset, $length));
								$litewave_offset += $length;
							}

							//$riff_litewave['quality_factor'] = intval(round((2000 - $riff_litewave_raw['m_dwScale']) / 20));
							$riff_litewave['quality_factor'] = $riff_litewave_raw['m_wQuality'];

							$riff_litewave['flags']['raw_source']    = ($riff_litewave_raw['compression_flags'] & 0x01) ? false : true;
							$riff_litewave['flags']['vbr_blocksize'] = ($riff_litewave_raw['compression_flags'] & 0x02) ? false : true;
							$riff_litewave['flags']['seekpoints']    =        (bool) ($riff_litewave_raw['compression_flags'] & 0x04);

							$thisfile_audio['lossless']        = (($riff_litewave_raw['m_wQuality'] == 100) ? true : false);
							$thisfile_audio['encoder_options'] = '-q'.$riff_litewave['quality_factor'];
							break;

						default:
							break;
					}
				}
				if ($info['avdataend'] > $info['filesize']) {
					switch (!empty($thisfile_audio_dataformat) ? $thisfile_audio_dataformat : '') {
						case 'wavpack': // WavPack
						case 'lpac':    // LPAC
						case 'ofr':     // OptimFROG
						case 'ofs':     // OptimFROG DualStream
							// lossless compressed audio formats that keep original RIFF headers - skip warning
							break;

						case 'litewave':
							if (($info['avdataend'] - $info['filesize']) == 1) {
								// LiteWave appears to incorrectly *not* pad actual output file
								// to nearest WORD boundary so may appear to be short by one
								// byte, in which case - skip warning
							} else {
								// Short by more than one byte, throw warning
								$info['warning'][] = 'Probably truncated file - expecting '.$thisfile_riff[$RIFFsubtype]['data'][0]['size'].' bytes of data, only found '.($info['filesize'] - $info['avdataoffset']).' (short by '.($thisfile_riff[$RIFFsubtype]['data'][0]['size'] - ($info['filesize'] - $info['avdataoffset'])).' bytes)';
								$info['avdataend'] = $info['filesize'];
							}
							break;

						default:
							if ((($info['avdataend'] - $info['filesize']) == 1) && (($thisfile_riff[$RIFFsubtype]['data'][0]['size'] % 2) == 0) && ((($info['filesize'] - $info['avdataoffset']) % 2) == 1)) {
								// output file appears to be incorrectly *not* padded to nearest WORD boundary
								// Output less severe warning
								$info['warning'][] = 'File should probably be padded to nearest WORD boundary, but it is not (expecting '.$thisfile_riff[$RIFFsubtype]['data'][0]['size'].' bytes of data, only found '.($info['filesize'] - $info['avdataoffset']).' therefore short by '.($thisfile_riff[$RIFFsubtype]['data'][0]['size'] - ($info['filesize'] - $info['avdataoffset'])).' bytes)';
								$info['avdataend'] = $info['filesize'];
							} else {
								// Short by more than one byte, throw warning
								$info['warning'][] = 'Probably truncated file - expecting '.$thisfile_riff[$RIFFsubtype]['data'][0]['size'].' bytes of data, only found '.($info['filesize'] - $info['avdataoffset']).' (short by '.($thisfile_riff[$RIFFsubtype]['data'][0]['size'] - ($info['filesize'] - $info['avdataoffset'])).' bytes)';
								$info['avdataend'] = $info['filesize'];
							}
							break;
					}
				}
				if (!empty($info['mpeg']['audio']['LAME']['audio_bytes'])) {
					if ((($info['avdataend'] - $info['avdataoffset']) - $info['mpeg']['audio']['LAME']['audio_bytes']) == 1) {
						$info['avdataend']--;
						$info['warning'][] = 'Extra null byte at end of MP3 data assumed to be RIFF padding and therefore ignored';
					}
				}
				if (isset($thisfile_audio_dataformat) && ($thisfile_audio_dataformat == 'ac3')) {
					unset($thisfile_audio['bits_per_sample']);
					if (!empty($info['ac3']['bitrate']) && ($info['ac3']['bitrate'] != $thisfile_audio['bitrate'])) {
						$thisfile_audio['bitrate'] = $info['ac3']['bitrate'];
					}
				}
				break;

			case 'AVI ':
				$thisfile_video['bitrate_mode'] = 'vbr'; // maybe not, but probably
				$thisfile_video['dataformat']   = 'avi';
				$info['mime_type']      = 'video/avi';

				if (isset($thisfile_riff[$RIFFsubtype]['movi']['offset'])) {
					$info['avdataoffset'] = $thisfile_riff[$RIFFsubtype]['movi']['offset'] + 8;
					if (isset($thisfile_riff['AVIX'])) {
						$info['avdataend'] = $thisfile_riff['AVIX'][(count($thisfile_riff['AVIX']) - 1)]['chunks']['movi']['offset'] + $thisfile_riff['AVIX'][(count($thisfile_riff['AVIX']) - 1)]['chunks']['movi']['size'];
					} else {
						$info['avdataend'] = $thisfile_riff['AVI ']['movi']['offset'] + $thisfile_riff['AVI ']['movi']['size'];
					}
					if ($info['avdataend'] > $info['filesize']) {
						$info['warning'][] = 'Probably truncated file - expecting '.($info['avdataend'] - $info['avdataoffset']).' bytes of data, only found '.($info['filesize'] - $info['avdataoffset']).' (short by '.($info['avdataend'] - $info['filesize']).' bytes)';
						$info['avdataend'] = $info['filesize'];
					}
				}

				if (isset($thisfile_riff['AVI ']['hdrl']['strl']['indx'])) {
					//$bIndexType = array(
					//	0x00 => 'AVI_INDEX_OF_INDEXES',
					//	0x01 => 'AVI_INDEX_OF_CHUNKS',
					//	0x80 => 'AVI_INDEX_IS_DATA',
					//);
					//$bIndexSubtype = array(
					//	0x01 => array(
					//		0x01 => 'AVI_INDEX_2FIELD',
					//	),
					//);
					foreach ($thisfile_riff['AVI ']['hdrl']['strl']['indx'] as $streamnumber => $steamdataarray) {
						$ahsisd = &$thisfile_riff['AVI ']['hdrl']['strl']['indx'][$streamnumber]['data'];

						$thisfile_riff_raw['indx'][$streamnumber]['wLongsPerEntry'] = $this->EitherEndian2Int(substr($ahsisd,  0, 2));
						$thisfile_riff_raw['indx'][$streamnumber]['bIndexSubType']  = $this->EitherEndian2Int(substr($ahsisd,  2, 1));
						$thisfile_riff_raw['indx'][$streamnumber]['bIndexType']     = $this->EitherEndian2Int(substr($ahsisd,  3, 1));
						$thisfile_riff_raw['indx'][$streamnumber]['nEntriesInUse']  = $this->EitherEndian2Int(substr($ahsisd,  4, 4));
						$thisfile_riff_raw['indx'][$streamnumber]['dwChunkId']      =                         substr($ahsisd,  8, 4);
						$thisfile_riff_raw['indx'][$streamnumber]['dwReserved']     = $this->EitherEndian2Int(substr($ahsisd, 12, 4));

						//$thisfile_riff_raw['indx'][$streamnumber]['bIndexType_name']    =    $bIndexType[$thisfile_riff_raw['indx'][$streamnumber]['bIndexType']];
						//$thisfile_riff_raw['indx'][$streamnumber]['bIndexSubType_name'] = $bIndexSubtype[$thisfile_riff_raw['indx'][$streamnumber]['bIndexType']][$thisfile_riff_raw['indx'][$streamnumber]['bIndexSubType']];

						unset($ahsisd);
					}
				}
				if (isset($thisfile_riff['AVI ']['hdrl']['avih'][$streamindex]['data'])) {
					$avihData = $thisfile_riff['AVI ']['hdrl']['avih'][$streamindex]['data'];

					// shortcut
					$thisfile_riff_raw['avih'] = array();
					$thisfile_riff_raw_avih = &$thisfile_riff_raw['avih'];

					$thisfile_riff_raw_avih['dwMicroSecPerFrame']    = $this->EitherEndian2Int(substr($avihData,  0, 4)); // frame display rate (or 0L)
					if ($thisfile_riff_raw_avih['dwMicroSecPerFrame'] == 0) {
						$info['error'][] = 'Corrupt RIFF file: avih.dwMicroSecPerFrame == zero';
						return false;
					}

					$flags = array(
						'dwMaxBytesPerSec',       // max. transfer rate
						'dwPaddingGranularity',   // pad to multiples of this size; normally 2K.
						'dwFlags',                // the ever-present flags
						'dwTotalFrames',          // # frames in file
						'dwInitialFrames',        //
						'dwStreams',              //
						'dwSuggestedBufferSize',  //
						'dwWidth',                //
						'dwHeight',               //
						'dwScale',                //
						'dwRate',                 //
						'dwStart',                //
						'dwLength',               //
					);
					$avih_offset = 4;
					foreach ($flags as $flag) {
						$thisfile_riff_raw_avih[$flag] = $this->EitherEndian2Int(substr($avihData, $avih_offset, 4));
						$avih_offset += 4;
					}

					$flags = array(
						'hasindex'     => 0x00000010,
						'mustuseindex' => 0x00000020,
						'interleaved'  => 0x00000100,
						'trustcktype'  => 0x00000800,
						'capturedfile' => 0x00010000,
						'copyrighted'  => 0x00020010,
					);
                    foreach ($flags as $flag => $value) {
						$thisfile_riff_raw_avih['flags'][$flag] = (bool) ($thisfile_riff_raw_avih['dwFlags'] & $value);
					}

					// shortcut
					$thisfile_riff_video[$streamindex] = array();
					$thisfile_riff_video_current = &$thisfile_riff_video[$streamindex];

					if ($thisfile_riff_raw_avih['dwWidth'] > 0) {
						$thisfile_riff_video_current['frame_width'] = $thisfile_riff_raw_avih['dwWidth'];
						$thisfile_video['resolution_x']             = $thisfile_riff_video_current['frame_width'];
					}
					if ($thisfile_riff_raw_avih['dwHeight'] > 0) {
						$thisfile_riff_video_current['frame_height'] = $thisfile_riff_raw_avih['dwHeight'];
						$thisfile_video['resolution_y']              = $thisfile_riff_video_current['frame_height'];
					}
					if ($thisfile_riff_raw_avih['dwTotalFrames'] > 0) {
						$thisfile_riff_video_current['total_frames'] = $thisfile_riff_raw_avih['dwTotalFrames'];
						$thisfile_video['total_frames']              = $thisfile_riff_video_current['total_frames'];
					}

					$thisfile_riff_video_current['frame_rate'] = round(1000000 / $thisfile_riff_raw_avih['dwMicroSecPerFrame'], 3);
					$thisfile_video['frame_rate'] = $thisfile_riff_video_current['frame_rate'];
				}
				if (isset($thisfile_riff['AVI ']['hdrl']['strl']['strh'][0]['data'])) {
					if (is_array($thisfile_riff['AVI ']['hdrl']['strl']['strh'])) {
						for ($i = 0; $i < count($thisfile_riff['AVI ']['hdrl']['strl']['strh']); $i++) {
							if (isset($thisfile_riff['AVI ']['hdrl']['strl']['strh'][$i]['data'])) {
								$strhData = $thisfile_riff['AVI ']['hdrl']['strl']['strh'][$i]['data'];
								$strhfccType = substr($strhData,  0, 4);

								if (isset($thisfile_riff['AVI ']['hdrl']['strl']['strf'][$i]['data'])) {
									$strfData = $thisfile_riff['AVI ']['hdrl']['strl']['strf'][$i]['data'];

									// shortcut
									$thisfile_riff_raw_strf_strhfccType_streamindex = &$thisfile_riff_raw['strf'][$strhfccType][$streamindex];

									switch ($strhfccType) {
										case 'auds':
											$thisfile_audio['bitrate_mode'] = 'cbr';
											$thisfile_audio_dataformat      = 'wav';
											if (isset($thisfile_riff_audio) && is_array($thisfile_riff_audio)) {
												$streamindex = count($thisfile_riff_audio);
											}

											$thisfile_riff_audio[$streamindex] = self::parseWAVEFORMATex($strfData);
											$thisfile_audio['wformattag'] = $thisfile_riff_audio[$streamindex]['raw']['wFormatTag'];

											// shortcut
											$thisfile_audio['streams'][$streamindex] = $thisfile_riff_audio[$streamindex];
											$thisfile_audio_streams_currentstream = &$thisfile_audio['streams'][$streamindex];

											if ($thisfile_audio_streams_currentstream['bits_per_sample'] == 0) {
												unset($thisfile_audio_streams_currentstream['bits_per_sample']);
											}
											$thisfile_audio_streams_currentstream['wformattag'] = $thisfile_audio_streams_currentstream['raw']['wFormatTag'];
											unset($thisfile_audio_streams_currentstream['raw']);

											// shortcut
											$thisfile_riff_raw['strf'][$strhfccType][$streamindex] = $thisfile_riff_audio[$streamindex]['raw'];

											unset($thisfile_riff_audio[$streamindex]['raw']);
											$thisfile_audio = getid3_lib::array_merge_noclobber($thisfile_audio, $thisfile_riff_audio[$streamindex]);

											$thisfile_audio['lossless'] = false;
											switch ($thisfile_riff_raw_strf_strhfccType_streamindex['wFormatTag']) {
												case 0x0001:  // PCM
													$thisfile_audio_dataformat  = 'wav';
													$thisfile_audio['lossless'] = true;
													break;

												case 0x0050: // MPEG Layer 2 or Layer 1
													$thisfile_audio_dataformat = 'mp2'; // Assume Layer-2
													break;

												case 0x0055: // MPEG Layer 3
													$thisfile_audio_dataformat = 'mp3';
													break;

												case 0x00FF: // AAC
													$thisfile_audio_dataformat = 'aac';
													break;

												case 0x0161: // Windows Media v7 / v8 / v9
												case 0x0162: // Windows Media Professional v9
												case 0x0163: // Windows Media Lossess v9
													$thisfile_audio_dataformat = 'wma';
													break;

												case 0x2000: // AC-3
													$thisfile_audio_dataformat = 'ac3';
													break;

												case 0x2001: // DTS
													$thisfile_audio_dataformat = 'dts';
													break;

												default:
													$thisfile_audio_dataformat = 'wav';
													break;
											}
											$thisfile_audio_streams_currentstream['dataformat']   = $thisfile_audio_dataformat;
											$thisfile_audio_streams_currentstream['lossless']     = $thisfile_audio['lossless'];
											$thisfile_audio_streams_currentstream['bitrate_mode'] = $thisfile_audio['bitrate_mode'];
											break;


										case 'iavs':
										case 'vids':
											// shortcut
											$thisfile_riff_raw['strh'][$i]                  = array();
											$thisfile_riff_raw_strh_current                 = &$thisfile_riff_raw['strh'][$i];

											$thisfile_riff_raw_strh_current['fccType']               =                         substr($strhData,  0, 4);  // same as $strhfccType;
											$thisfile_riff_raw_strh_current['fccHandler']            =                         substr($strhData,  4, 4);
											$thisfile_riff_raw_strh_current['dwFlags']               = $this->EitherEndian2Int(substr($strhData,  8, 4)); // Contains AVITF_* flags
											$thisfile_riff_raw_strh_current['wPriority']             = $this->EitherEndian2Int(substr($strhData, 12, 2));
											$thisfile_riff_raw_strh_current['wLanguage']             = $this->EitherEndian2Int(substr($strhData, 14, 2));
											$thisfile_riff_raw_strh_current['dwInitialFrames']       = $this->EitherEndian2Int(substr($strhData, 16, 4));
											$thisfile_riff_raw_strh_current['dwScale']               = $this->EitherEndian2Int(substr($strhData, 20, 4));
											$thisfile_riff_raw_strh_current['dwRate']                = $this->EitherEndian2Int(substr($strhData, 24, 4));
											$thisfile_riff_raw_strh_current['dwStart']               = $this->EitherEndian2Int(substr($strhData, 28, 4));
											$thisfile_riff_raw_strh_current['dwLength']              = $this->EitherEndian2Int(substr($strhData, 32, 4));
											$thisfile_riff_raw_strh_current['dwSuggestedBufferSize'] = $this->EitherEndian2Int(substr($strhData, 36, 4));
											$thisfile_riff_raw_strh_current['dwQuality']             = $this->EitherEndian2Int(substr($strhData, 40, 4));
											$thisfile_riff_raw_strh_current['dwSampleSize']          = $this->EitherEndian2Int(substr($strhData, 44, 4));
											$thisfile_riff_raw_strh_current['rcFrame']               = $this->EitherEndian2Int(substr($strhData, 48, 4));

											$thisfile_riff_video_current['codec'] = self::fourccLookup($thisfile_riff_raw_strh_current['fccHandler']);
											$thisfile_video['fourcc']             = $thisfile_riff_raw_strh_current['fccHandler'];
											if (!$thisfile_riff_video_current['codec'] && isset($thisfile_riff_raw_strf_strhfccType_streamindex['fourcc']) && self::fourccLookup($thisfile_riff_raw_strf_strhfccType_streamindex['fourcc'])) {
												$thisfile_riff_video_current['codec'] = self::fourccLookup($thisfile_riff_raw_strf_strhfccType_streamindex['fourcc']);
												$thisfile_video['fourcc']             = $thisfile_riff_raw_strf_strhfccType_streamindex['fourcc'];
											}
											$thisfile_video['codec']              = $thisfile_riff_video_current['codec'];
											$thisfile_video['pixel_aspect_ratio'] = (float) 1;
											switch ($thisfile_riff_raw_strh_current['fccHandler']) {
												case 'HFYU': // Huffman Lossless Codec
												case 'IRAW': // Intel YUV Uncompressed
												case 'YUY2': // Uncompressed YUV 4:2:2
													$thisfile_video['lossless'] = true;
													break;

												default:
													$thisfile_video['lossless'] = false;
													break;
											}

											switch ($strhfccType) {
												case 'vids':
													$thisfile_riff_raw_strf_strhfccType_streamindex = self::ParseBITMAPINFOHEADER(substr($strfData, 0, 40), ($info['fileformat'] == 'riff'));
													$thisfile_video['bits_per_sample'] = $thisfile_riff_raw_strf_strhfccType_streamindex['biBitCount'];

													if ($thisfile_riff_video_current['codec'] == 'DV') {
														$thisfile_riff_video_current['dv_type'] = 2;
													}
													break;

												case 'iavs':
													$thisfile_riff_video_current['dv_type'] = 1;
													break;
											}
											break;

										default:
											$info['warning'][] = 'Unhandled fccType for stream ('.$i.'): "'.$strhfccType.'"';
											break;

									}
								}
							}

							if (isset($thisfile_riff_raw_strf_strhfccType_streamindex['fourcc'])) {

								$thisfile_video['fourcc'] = $thisfile_riff_raw_strf_strhfccType_streamindex['fourcc'];
								if (self::fourccLookup($thisfile_video['fourcc'])) {
									$thisfile_riff_video_current['codec'] = self::fourccLookup($thisfile_video['fourcc']);
									$thisfile_video['codec']              = $thisfile_riff_video_current['codec'];
								}

								switch ($thisfile_riff_raw_strf_strhfccType_streamindex['fourcc']) {
									case 'HFYU': // Huffman Lossless Codec
									case 'IRAW': // Intel YUV Uncompressed
									case 'YUY2': // Uncompressed YUV 4:2:2
										$thisfile_video['lossless']        = true;
										//$thisfile_video['bits_per_sample'] = 24;
										break;

									default:
										$thisfile_video['lossless']        = false;
										//$thisfile_video['bits_per_sample'] = 24;
										break;
								}

							}
						}
					}
				}
				break;

			case 'CDDA':
				$thisfile_audio['bitrate_mode'] = 'cbr';
				$thisfile_audio_dataformat      = 'cda';
				$thisfile_audio['lossless']     = true;
				unset($info['mime_type']);

				$info['avdataoffset'] = 44;

				if (isset($thisfile_riff['CDDA']['fmt '][0]['data'])) {
					// shortcut
					$thisfile_riff_CDDA_fmt_0 = &$thisfile_riff['CDDA']['fmt '][0];

					$thisfile_riff_CDDA_fmt_0['unknown1']           = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'],  0, 2));
					$thisfile_riff_CDDA_fmt_0['track_num']          = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'],  2, 2));
					$thisfile_riff_CDDA_fmt_0['disc_id']            = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'],  4, 4));
					$thisfile_riff_CDDA_fmt_0['start_offset_frame'] = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'],  8, 4));
					$thisfile_riff_CDDA_fmt_0['playtime_frames']    = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'], 12, 4));
					$thisfile_riff_CDDA_fmt_0['unknown6']           = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'], 16, 4));
					$thisfile_riff_CDDA_fmt_0['unknown7']           = $this->EitherEndian2Int(substr($thisfile_riff_CDDA_fmt_0['data'], 20, 4));

					$thisfile_riff_CDDA_fmt_0['start_offset_seconds'] = (float) $thisfile_riff_CDDA_fmt_0['start_offset_frame'] / 75;
					$thisfile_riff_CDDA_fmt_0['playtime_seconds']     = (float) $thisfile_riff_CDDA_fmt_0['playtime_frames'] / 75;
					$info['comments']['track']                = $thisfile_riff_CDDA_fmt_0['track_num'];
					$info['playtime_seconds']                 = $thisfile_riff_CDDA_fmt_0['playtime_seconds'];

					// hardcoded data for CD-audio
					$thisfile_audio['sample_rate']     = 44100;
					$thisfile_audio['channels']        = 2;
					$thisfile_audio['bits_per_sample'] = 16;
					$thisfile_audio['bitrate']         = $thisfile_audio['sample_rate'] * $thisfile_audio['channels'] * $thisfile_audio['bits_per_sample'];
					$thisfile_audio['bitrate_mode']    = 'cbr';
				}
				break;


			case 'AIFF':
			case 'AIFC':
				$thisfile_audio['bitrate_mode'] = 'cbr';
				$thisfile_audio_dataformat      = 'aiff';
				$thisfile_audio['lossless']     = true;
				$info['mime_type']      = 'audio/x-aiff';

				if (isset($thisfile_riff[$RIFFsubtype]['SSND'][0]['offset'])) {
					$info['avdataoffset'] = $thisfile_riff[$RIFFsubtype]['SSND'][0]['offset'] + 8;
					$info['avdataend']    = $info['avdataoffset'] + $thisfile_riff[$RIFFsubtype]['SSND'][0]['size'];
					if ($info['avdataend'] > $info['filesize']) {
						if (($info['avdataend'] == ($info['filesize'] + 1)) && (($info['filesize'] % 2) == 1)) {
							// structures rounded to 2-byte boundary, but dumb encoders
							// forget to pad end of file to make this actually work
						} else {
							$info['warning'][] = 'Probable truncated AIFF file: expecting '.$thisfile_riff[$RIFFsubtype]['SSND'][0]['size'].' bytes of audio data, only '.($info['filesize'] - $info['avdataoffset']).' bytes found';
						}
						$info['avdataend'] = $info['filesize'];
					}
				}

				if (isset($thisfile_riff[$RIFFsubtype]['COMM'][0]['data'])) {

					// shortcut
					$thisfile_riff_RIFFsubtype_COMM_0_data = &$thisfile_riff[$RIFFsubtype]['COMM'][0]['data'];

					$thisfile_riff_audio['channels']         =         getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_COMM_0_data,  0,  2), true);
					$thisfile_riff_audio['total_samples']    =         getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_COMM_0_data,  2,  4), false);
					$thisfile_riff_audio['bits_per_sample']  =         getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_COMM_0_data,  6,  2), true);
					$thisfile_riff_audio['sample_rate']      = (int) getid3_lib::BigEndian2Float(substr($thisfile_riff_RIFFsubtype_COMM_0_data,  8, 10));

					if ($thisfile_riff[$RIFFsubtype]['COMM'][0]['size'] > 18) {
						$thisfile_riff_audio['codec_fourcc'] =                                   substr($thisfile_riff_RIFFsubtype_COMM_0_data, 18,  4);
						$CodecNameSize                       =         getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_COMM_0_data, 22,  1), false);
						$thisfile_riff_audio['codec_name']   =                                   substr($thisfile_riff_RIFFsubtype_COMM_0_data, 23,  $CodecNameSize);
						switch ($thisfile_riff_audio['codec_name']) {
							case 'NONE':
								$thisfile_audio['codec']    = 'Pulse Code Modulation (PCM)';
								$thisfile_audio['lossless'] = true;
								break;

							case '':
								switch ($thisfile_riff_audio['codec_fourcc']) {
									// http://developer.apple.com/qa/snd/snd07.html
									case 'sowt':
										$thisfile_riff_audio['codec_name'] = 'Two\'s Compliment Little-Endian PCM';
										$thisfile_audio['lossless'] = true;
										break;

									case 'twos':
										$thisfile_riff_audio['codec_name'] = 'Two\'s Compliment Big-Endian PCM';
										$thisfile_audio['lossless'] = true;
										break;

									default:
										break;
								}
								break;

							default:
								$thisfile_audio['codec']    = $thisfile_riff_audio['codec_name'];
								$thisfile_audio['lossless'] = false;
								break;
						}
					}

					$thisfile_audio['channels']        = $thisfile_riff_audio['channels'];
					if ($thisfile_riff_audio['bits_per_sample'] > 0) {
						$thisfile_audio['bits_per_sample'] = $thisfile_riff_audio['bits_per_sample'];
					}
					$thisfile_audio['sample_rate']     = $thisfile_riff_audio['sample_rate'];
					if ($thisfile_audio['sample_rate'] == 0) {
						$info['error'][] = 'Corrupted AIFF file: sample_rate == zero';
						return false;
					}
					$info['playtime_seconds'] = $thisfile_riff_audio['total_samples'] / $thisfile_audio['sample_rate'];
				}

				if (isset($thisfile_riff[$RIFFsubtype]['COMT'])) {
					$offset = 0;
					$CommentCount                                   = getid3_lib::BigEndian2Int(substr($thisfile_riff[$RIFFsubtype]['COMT'][0]['data'], $offset, 2), false);
					$offset += 2;
					for ($i = 0; $i < $CommentCount; $i++) {
						$info['comments_raw'][$i]['timestamp']      = getid3_lib::BigEndian2Int(substr($thisfile_riff[$RIFFsubtype]['COMT'][0]['data'], $offset, 4), false);
						$offset += 4;
						$info['comments_raw'][$i]['marker_id']      = getid3_lib::BigEndian2Int(substr($thisfile_riff[$RIFFsubtype]['COMT'][0]['data'], $offset, 2), true);
						$offset += 2;
						$CommentLength                              = getid3_lib::BigEndian2Int(substr($thisfile_riff[$RIFFsubtype]['COMT'][0]['data'], $offset, 2), false);
						$offset += 2;
						$info['comments_raw'][$i]['comment']        =                           substr($thisfile_riff[$RIFFsubtype]['COMT'][0]['data'], $offset, $CommentLength);
						$offset += $CommentLength;

						$info['comments_raw'][$i]['timestamp_unix'] = getid3_lib::DateMac2Unix($info['comments_raw'][$i]['timestamp']);
						$thisfile_riff['comments']['comment'][] = $info['comments_raw'][$i]['comment'];
					}
				}

				$CommentsChunkNames = array('NAME'=>'title', 'author'=>'artist', '(c) '=>'copyright', 'ANNO'=>'comment');
				foreach ($CommentsChunkNames as $key => $value) {
					if (isset($thisfile_riff[$RIFFsubtype][$key][0]['data'])) {
						$thisfile_riff['comments'][$value][] = $thisfile_riff[$RIFFsubtype][$key][0]['data'];
					}
				}

				$thisfile_riff['comments']['title'][] = $thisfile_riff[$RIFFsubtype]['NAME'][0]['data'];
				$thisfile_riff['comments']['artist'][] = $thisfile_riff[$RIFFsubtype]['author'][0]['data'];
				break;

			case '8SVX':
				$thisfile_audio['bitrate_mode']    = 'cbr';
				$thisfile_audio_dataformat         = '8svx';
				$thisfile_audio['bits_per_sample'] = 8;
				$thisfile_audio['channels']        = 1; // overridden below, if need be
				$info['mime_type']                = 'audio/x-aiff';

				if (isset($thisfile_riff[$RIFFsubtype]['BODY'][0]['offset'])) {
					$info['avdataoffset'] = $thisfile_riff[$RIFFsubtype]['BODY'][0]['offset'] + 8;
					$info['avdataend']    = $info['avdataoffset'] + $thisfile_riff[$RIFFsubtype]['BODY'][0]['size'];
					if ($info['avdataend'] > $info['filesize']) {
						$info['warning'][] = 'Probable truncated AIFF file: expecting '.$thisfile_riff[$RIFFsubtype]['BODY'][0]['size'].' bytes of audio data, only found '.($info['filesize'] - $info['avdataoffset']).' bytes found';
					}
				}

				if (isset($thisfile_riff[$RIFFsubtype]['VHDR'][0]['offset'])) {
					// shortcut
					$thisfile_riff_RIFFsubtype_VHDR_0 = &$thisfile_riff[$RIFFsubtype]['VHDR'][0];

					$thisfile_riff_RIFFsubtype_VHDR_0['oneShotHiSamples']  =   getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'],  0, 4));
					$thisfile_riff_RIFFsubtype_VHDR_0['repeatHiSamples']   =   getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'],  4, 4));
					$thisfile_riff_RIFFsubtype_VHDR_0['samplesPerHiCycle'] =   getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'],  8, 4));
					$thisfile_riff_RIFFsubtype_VHDR_0['samplesPerSec']     =   getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'], 12, 2));
					$thisfile_riff_RIFFsubtype_VHDR_0['ctOctave']          =   getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'], 14, 1));
					$thisfile_riff_RIFFsubtype_VHDR_0['sCompression']      =   getid3_lib::BigEndian2Int(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'], 15, 1));
					$thisfile_riff_RIFFsubtype_VHDR_0['Volume']            = getid3_lib::FixedPoint16_16(substr($thisfile_riff_RIFFsubtype_VHDR_0['data'], 16, 4));

					$thisfile_audio['sample_rate'] = $thisfile_riff_RIFFsubtype_VHDR_0['samplesPerSec'];

					switch ($thisfile_riff_RIFFsubtype_VHDR_0['sCompression']) {
						case 0:
							$thisfile_audio['codec']    = 'Pulse Code Modulation (PCM)';
							$thisfile_audio['lossless'] = true;
							$ActualBitsPerSample        = 8;
							break;

						case 1:
							$thisfile_audio['codec']    = 'Fibonacci-delta encoding';
							$thisfile_audio['lossless'] = false;
							$ActualBitsPerSample        = 4;
							break;

						default:
							$info['warning'][] = 'Unexpected sCompression value in 8SVX.VHDR chunk - expecting 0 or 1, found "'.$thisfile_riff_RIFFsubtype_VHDR_0['sCompression'].'"';
							break;
					}
				}

				if (isset($thisfile_riff[$RIFFsubtype]['CHAN'][0]['data'])) {
					$ChannelsIndex = getid3_lib::BigEndian2Int(substr($thisfile_riff[$RIFFsubtype]['CHAN'][0]['data'], 0, 4));
					switch ($ChannelsIndex) {
						case 6: // Stereo
							$thisfile_audio['channels'] = 2;
							break;

						case 2: // Left channel only
						case 4: // Right channel only
							$thisfile_audio['channels'] = 1;
							break;

						default:
							$info['warning'][] = 'Unexpected value in 8SVX.CHAN chunk - expecting 2 or 4 or 6, found "'.$ChannelsIndex.'"';
							break;
					}

				}

				$CommentsChunkNames = array('NAME'=>'title', 'author'=>'artist', '(c) '=>'copyright', 'ANNO'=>'comment');
				foreach ($CommentsChunkNames as $key => $value) {
					if (isset($thisfile_riff[$RIFFsubtype][$key][0]['data'])) {
						$thisfile_riff['comments'][$value][] = $thisfile_riff[$RIFFsubtype][$key][0]['data'];
					}
				}

				$thisfile_audio['bitrate'] = $thisfile_audio['sample_rate'] * $ActualBitsPerSample * $thisfile_audio['channels'];
				if (!empty($thisfile_audio['bitrate'])) {
					$info['playtime_seconds'] = ($info['avdataend'] - $info['avdataoffset']) / ($thisfile_audio['bitrate'] / 8);
				}
				break;


			case 'CDXA':
				$info['mime_type'] = 'video/mpeg';
				if (!empty($thisfile_riff['CDXA']['data'][0]['size'])) {
					if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio-video.mpeg.php', __FILE__, false)) {
						$getid3_temp = new getID3();
						$getid3_temp->openfile($this->getid3->filename);
						$getid3_mpeg = new getid3_mpeg($getid3_temp);
						$getid3_mpeg->Analyze();
						if (empty($getid3_temp->info['error'])) {
							$info['audio']   = $getid3_temp->info['audio'];
							$info['video']   = $getid3_temp->info['video'];
							$info['mpeg']    = $getid3_temp->info['mpeg'];
							$info['warning'] = $getid3_temp->info['warning'];
						}
						unset($getid3_temp, $getid3_mpeg);
					}
				}
				break;


			default:
				$info['error'][] = 'Unknown RIFF type: expecting one of (WAVE|RMP3|AVI |CDDA|AIFF|AIFC|8SVX|CDXA), found "'.$RIFFsubtype.'" instead';
				unset($info['fileformat']);
				break;
		}

		switch ($RIFFsubtype) {
			case 'WAVE':
			case 'AIFF':
			case 'AIFC':
				$ID3v2_key_good = 'id3 ';
				$ID3v2_keys_bad = array('ID3 ', 'tag ');
				foreach ($ID3v2_keys_bad as $ID3v2_key_bad) {
					if (isset($thisfile_riff[$RIFFsubtype][$ID3v2_key_bad]) && !array_key_exists($ID3v2_key_good, $thisfile_riff[$RIFFsubtype])) {
						$thisfile_riff[$RIFFsubtype][$ID3v2_key_good] = $thisfile_riff[$RIFFsubtype][$ID3v2_key_bad];
						$info['warning'][] = 'mapping "'.$ID3v2_key_bad.'" chunk to "'.$ID3v2_key_good.'"';
					}
				}

				if (isset($thisfile_riff[$RIFFsubtype]['id3 '])) {
					getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.id3v2.php', __FILE__, true);
					$getid3_temp = new getID3();
					$getid3_temp->openfile($this->getid3->filename);
					$getid3_id3v2 = new getid3_id3v2($getid3_temp);
					$getid3_id3v2->StartingOffset = $thisfile_riff[$RIFFsubtype]['id3 '][0]['offset'] + 8;
					if ($thisfile_riff[$RIFFsubtype]['id3 '][0]['valid'] = $getid3_id3v2->Analyze()) {
						$info['id3v2'] = $getid3_temp->info['id3v2'];
					}
					unset($getid3_temp, $getid3_id3v2);
				}
				break;
		}

		if (isset($thisfile_riff_WAVE['DISP']) && is_array($thisfile_riff_WAVE['DISP'])) {
			$thisfile_riff['comments']['title'][] = trim(substr($thisfile_riff_WAVE['DISP'][count($thisfile_riff_WAVE['DISP']) - 1]['data'], 4));
		}
		if (isset($thisfile_riff_WAVE['INFO']) && is_array($thisfile_riff_WAVE['INFO'])) {
			self::parseComments($thisfile_riff_WAVE['INFO'], $thisfile_riff['comments']);
		}
		if (isset($thisfile_riff['AVI ']['INFO']) && is_array($thisfile_riff['AVI ']['INFO'])) {
			self::parseComments($thisfile_riff['AVI ']['INFO'], $thisfile_riff['comments']);
		}

		if (empty($thisfile_audio['encoder']) && !empty($info['mpeg']['audio']['LAME']['short_version'])) {
			$thisfile_audio['encoder'] = $info['mpeg']['audio']['LAME']['short_version'];
		}

		if (!isset($info['playtime_seconds'])) {
			$info['playtime_seconds'] = 0;
		}
		if (isset($thisfile_riff_raw['strh'][0]['dwLength']) && isset($thisfile_riff_raw['avih']['dwMicroSecPerFrame'])) {
			// needed for >2GB AVIs where 'avih' chunk only lists number of frames in that chunk, not entire movie
			$info['playtime_seconds'] = $thisfile_riff_raw['strh'][0]['dwLength'] * ($thisfile_riff_raw['avih']['dwMicroSecPerFrame'] / 1000000);
		} elseif (isset($thisfile_riff_raw['avih']['dwTotalFrames']) && isset($thisfile_riff_raw['avih']['dwMicroSecPerFrame'])) {
			$info['playtime_seconds'] = $thisfile_riff_raw['avih']['dwTotalFrames'] * ($thisfile_riff_raw['avih']['dwMicroSecPerFrame'] / 1000000);
		}

		if ($info['playtime_seconds'] > 0) {
			if (isset($thisfile_riff_audio) && isset($thisfile_riff_video)) {

				if (!isset($info['bitrate'])) {
					$info['bitrate'] = ((($info['avdataend'] - $info['avdataoffset']) / $info['playtime_seconds']) * 8);
				}

			} elseif (isset($thisfile_riff_audio) && !isset($thisfile_riff_video)) {

				if (!isset($thisfile_audio['bitrate'])) {
					$thisfile_audio['bitrate'] = ((($info['avdataend'] - $info['avdataoffset']) / $info['playtime_seconds']) * 8);
				}

			} elseif (!isset($thisfile_riff_audio) && isset($thisfile_riff_video)) {

				if (!isset($thisfile_video['bitrate'])) {
					$thisfile_video['bitrate'] = ((($info['avdataend'] - $info['avdataoffset']) / $info['playtime_seconds']) * 8);
				}

			}
		}


		if (isset($thisfile_riff_video) && isset($thisfile_audio['bitrate']) && ($thisfile_audio['bitrate'] > 0) && ($info['playtime_seconds'] > 0)) {

			$info['bitrate'] = ((($info['avdataend'] - $info['avdataoffset']) / $info['playtime_seconds']) * 8);
			$thisfile_audio['bitrate'] = 0;
			$thisfile_video['bitrate'] = $info['bitrate'];
			foreach ($thisfile_riff_audio as $channelnumber => $audioinfoarray) {
				$thisfile_video['bitrate'] -= $audioinfoarray['bitrate'];
				$thisfile_audio['bitrate'] += $audioinfoarray['bitrate'];
			}
			if ($thisfile_video['bitrate'] <= 0) {
				unset($thisfile_video['bitrate']);
			}
			if ($thisfile_audio['bitrate'] <= 0) {
				unset($thisfile_audio['bitrate']);
			}
		}

		if (isset($info['mpeg']['audio'])) {
			$thisfile_audio_dataformat      = 'mp'.$info['mpeg']['audio']['layer'];
			$thisfile_audio['sample_rate']  = $info['mpeg']['audio']['sample_rate'];
			$thisfile_audio['channels']     = $info['mpeg']['audio']['channels'];
			$thisfile_audio['bitrate']      = $info['mpeg']['audio']['bitrate'];
			$thisfile_audio['bitrate_mode'] = strtolower($info['mpeg']['audio']['bitrate_mode']);
			if (!empty($info['mpeg']['audio']['codec'])) {
				$thisfile_audio['codec'] = $info['mpeg']['audio']['codec'].' '.$thisfile_audio['codec'];
			}
			if (!empty($thisfile_audio['streams'])) {
				foreach ($thisfile_audio['streams'] as $streamnumber => $streamdata) {
					if ($streamdata['dataformat'] == $thisfile_audio_dataformat) {
						$thisfile_audio['streams'][$streamnumber]['sample_rate']  = $thisfile_audio['sample_rate'];
						$thisfile_audio['streams'][$streamnumber]['channels']     = $thisfile_audio['channels'];
						$thisfile_audio['streams'][$streamnumber]['bitrate']      = $thisfile_audio['bitrate'];
						$thisfile_audio['streams'][$streamnumber]['bitrate_mode'] = $thisfile_audio['bitrate_mode'];
						$thisfile_audio['streams'][$streamnumber]['codec']        = $thisfile_audio['codec'];
					}
				}
			}
			$getid3_mp3 = new getid3_mp3($this->getid3);
			$thisfile_audio['encoder_options'] = $getid3_mp3->GuessEncoderOptions();
			unset($getid3_mp3);
		}


		if (!empty($thisfile_riff_raw['fmt ']['wBitsPerSample']) && ($thisfile_riff_raw['fmt ']['wBitsPerSample'] > 0)) {
			switch ($thisfile_audio_dataformat) {
				case 'ac3':
					// ignore bits_per_sample
					break;

				default:
					$thisfile_audio['bits_per_sample'] = $thisfile_riff_raw['fmt ']['wBitsPerSample'];
					break;
			}
		}


		if (empty($thisfile_riff_raw)) {
			unset($thisfile_riff['raw']);
		}
		if (empty($thisfile_riff_audio)) {
			unset($thisfile_riff['audio']);
		}
		if (empty($thisfile_riff_video)) {
			unset($thisfile_riff['video']);
		}

		return true;
	}public function ParseRIFF($startoffset, $maxoffset) {
    $info = &$this->getid3->info;

    $RIFFchunk = false;
    $FoundAllChunksWeNeed = false;

    try {
        $this->fseek($startoffset);
        $maxoffset = min($maxoffset, $info['avdataend']);
        while ($this->ftell() < $maxoffset) {
            $chunknamesize = $this->fread(8);
            $chunkname = str_replace("\x00", '_', substr($chunknamesize, 0, 4));
            $chunksize = $this->EitherEndian2Int(substr($chunknamesize, 4, 4));
            if (strlen($chunkname) < 4) {
                $this->error('Expecting chunk name at offset '.($this->ftell() - 8).' but found nothing. Aborting RIFF parsing.');
                break;
            }
            if (($chunksize == 0) && ($chunkname != 'JUNK')) {
                $this->warning('Chunk ('.$chunkname.') size at offset '.($this->ftell() - 4).' is zero. Aborting RIFF parsing.');
                break;
            }
            if (($chunksize % 2) != 0) {
                $chunksize++;
            }

            switch ($chunkname) {
                case 'LIST':
                    $listname = $this->fread(4);
                    if (preg_match('#^(movi|rec )$#i', $listname)) {
                        $RIFFchunk[$listname]['offset'] = $this->ftell() - 4;
                        $RIFFchunk[$listname]['size']   = $chunksize;

                        if (!$FoundAllChunksWeNeed) {
                            $WhereWeWere      = $this->ftell();
                            $AudioChunkHeader = $this->fread(12);
                            $AudioChunkStreamNum  = substr($AudioChunkHeader, 0, 2);
                            $AudioChunkStreamType = substr($AudioChunkHeader, 2, 2);
                            $AudioChunkSize       = getid3_lib::LittleEndian2Int(substr($AudioChunkHeader, 4, 4));

                            if ($AudioChunkStreamType == 'wb') {
                                $FirstFourBytes = substr($AudioChunkHeader, 8, 4);
                                if (preg_match('/^\xFF[\xE2-\xE7\xF2-\xF7\xFA-\xFF][\x00-\xEB]/s', $FirstFourBytes)) {
                                    if (getid3_mp3::MPEGaudioHeaderBytesValid($FirstFourBytes)) {
                                        $getid3_temp = new getID3();
                                        $getid3_temp->openfile($this->getid3->filename);
                                        $getid3_temp->info['avdataoffset'] = $this->ftell() - 4;
                                        $getid3_temp->info['avdataend']    = $this->ftell() + $AudioChunkSize;
                                        $getid3_mp3 = new getid3_mp3($getid3_temp);
                                        $getid3_mp3->getOnlyMPEGaudioInfo($getid3_temp->info['avdataoffset'], false);
                                        if (isset($getid3_temp->info['mpeg']['audio'])) {
                                            $info['mpeg']['audio']         = $getid3_temp->info['mpeg']['audio'];
                                            $info['audio']                 = $getid3_temp->info['audio'];
                                            $info['audio']['dataformat']   = 'mp'.$info['mpeg']['audio']['layer'];
                                            $info['audio']['sample_rate']  = $info['mpeg']['audio']['sample_rate'];
                                            $info['audio']['channels']     = $info['mpeg']['audio']['channels'];
                                            $info['audio']['bitrate']      = $info['mpeg']['audio']['bitrate'];
                                            $info['audio']['bitrate_mode'] = strtolower($info['mpeg']['audio']['bitrate_mode']);
                                        }
                                        unset($getid3_temp, $getid3_mp3);
                                    }

                                } elseif (strpos($FirstFourBytes, getid3_ac3::syncword) === 0) {
                                    $getid3_temp = new getID3();
                                    $getid3_temp->openfile($this->getid3->filename);
                                    $getid3_temp->info['avdataoffset'] = $this->ftell() - 4;
                                    $getid3_temp->info['avdataend']    = $this->ftell() + $AudioChunkSize;
                                    $getid3_ac3 = new getid3_ac3($getid3_temp);
                                    $getid3_ac3->Analyze();
                                    if (empty($getid3_temp->info['error'])) {
                                        $info['audio']   = $getid3_temp->info['audio'];
                                        $info['ac3']     = $getid3_temp->info['ac3'];
                                        if (!empty($getid3_temp->info['warning'])) {
                                            foreach ($getid3_temp->info['warning'] as $key => $value) {
                                                $info['warning'][] = $value;
                                            }
                                        }
                                    }
                                    unset($getid3_temp, $getid3_ac3);
                                }
                            }
                            $FoundAllChunksWeNeed = true;
                            $this->fseek($WhereWeWere);
                        }
                        $this->fseek($chunksize - 4, SEEK_CUR);

                    } else {
                        if (!isset($RIFFchunk[$listname])) {
                            $RIFFchunk[$listname] = array();
                        }
                        $LISTchunkParent    = $listname;
                        $LISTchunkMaxOffset = $this->ftell() - 4 + $chunksize;
                        if ($parsedChunk = $this->ParseRIFF($this->ftell(), $LISTchunkMaxOffset)) {
                            $RIFFchunk[$listname] = array_merge_recursive($RIFFchunk[$listname], $parsedChunk);
                        }

                    }
                    break;

                default:
                    if (preg_match('#^[0-9]{2}(wb|pc|dc|db)$#', $chunkname)) {
                        $this->fseek($chunksize, SEEK_CUR);
                        break;
                    }
                    $thisindex = 0;
                    if (isset($RIFFchunk[$chunkname]) && is_array($RIFFchunk[$chunkname])) {
                        $thisindex = count($RIFFchunk[$chunkname]);
                    }
                    $RIFFchunk[$chunkname][$thisindex]['offset'] = $this->ftell() - 8;
                    $RIFFchunk[$chunkname][$thisindex]['size']   = $chunksize;
                    switch ($chunkname) {
                        case 'data':
                            $info['avdataoffset'] = $this->ftell();
                            $info['avdataend']    = $info['avdataoffset'] + $chunksize;

                            $testData = $this->fread(36);
                            if ($testData === '') {
                                break;
                            }
                            if (preg_match('/^\xFF[\xE2-\xE7\xF2-\xF7\xFA-\xFF][\x00-\xEB]/s', substr($testData, 0, 4))) {
                                if (getid3_mp3::MPEGaudioHeaderBytesValid(substr($testData, 0, 4))) {
                                    $getid3_temp = new getID3();
                                    $getid3_temp->openfile($this->getid3->filename);
                                    $getid3_temp->info['avdataoffset'] = $info['avdataoffset'];
                                    $getid3_temp->info['avdataend']    = $info['avdataend'];
                                    $getid3_mp3 = new getid3_mp3($getid3_temp);
                                    $getid3_mp3->getOnlyMPEGaudioInfo($info['avdataoffset'], false);
                                    if (empty($getid3_temp->info['error'])) {
                                        $info['audio'] = $getid3_temp->info['audio'];
                                        $info['mpeg']  = $getid3_temp->info['mpeg'];
                                    }
                                    unset($getid3_temp, $getid3_mp3);
                                }

                            } elseif (($isRegularAC3 = (substr($testData, 0, 2) == getid3_ac3::syncword)) || substr($testData, 8, 2) == strrev(getid3_ac3::syncword)) {
                                $getid3_temp = new getID3();
                                if ($isRegularAC3) {
                                    $getid3_temp->openfile($this->getid3->filename);
                                    $getid3_temp->info['avdataoffset'] = $info['avdataoffset'];
                                    $getid3_temp->info['avdataend']    = $info['avdataend'];
                                }
                                $getid3_ac3 = new getid3_ac3($getid3_temp);
                                if ($isRegularAC3) {
                                    $getid3_ac3->Analyze();
                                } else {
                                    $ac3_data = '';
                                    for ($i = 0; $i < 28; $i += 2) {
                                        $ac3_data .= substr($testData, 8 + $i + 1, 1);
                                        $ac3_data .= substr($testData, 8 + $i + 0, 1);
                                    }
                                    $getid3_ac3->AnalyzeString($ac3_data);
                                }

                                if (empty($getid3_temp->info['error'])) {
                                    $info['audio'] = $getid3_temp->info['audio'];
                                    $info['ac3']   = $getid3_temp->info['ac3'];
                                    if (!empty($getid3_temp->info['warning'])) {
                                        foreach ($getid3_temp->info['warning'] as $newerror) {
                                            $this->warning('getid3_ac3() says: ['.$newerror.']');
                                        }
                                    }
                                }
                                unset($getid3_temp, $getid3_ac3);

                            } elseif (preg_match('/^('.implode('|', array_map('preg_quote', getid3_dts::$syncwords)).')/', $testData)) {
                                $getid3_temp = new getID3();
                                $getid3_temp->openfile($this->getid3->filename);
                                $getid3_temp->info['avdataoffset'] = $info['avdataoffset'];
                                $getid3_dts = new getid3_dts($getid3_temp);
                                $getid3_dts->Analyze();
                                if (empty($getid3_temp->info['error'])) {
                                    $info['audio']            = $getid3_temp->info['audio'];
                                    $info['dts']              = $getid3_temp->info['dts'];
                                    $info['playtime_seconds'] = $getid3_temp->info['playtime_seconds'];
                                    if (!empty($getid3_temp->info['warning'])) {
                                        foreach ($getid3_temp->info['warning'] as $newerror) {
                                            $this->warning('getid3_dts() says: ['.$newerror.']');
                                        }
                                    }
                                }

                                unset($getid3_temp, $getid3_dts);

                            } elseif (substr($testData, 0, 4) == 'wvpk') {
                                $info['wavpack']['offset'] = $info['avdataoffset'];
                                $info['wavpack']['size']   = getid3_lib::LittleEndian2Int(substr($testData, 4, 4));
                                $this->parseWavPackHeader(substr($testData, 8, 28));

                            } else {
                                // This is some other kind of data (quite possibly just PCM)
                                // do nothing special, just skip it
                            }
                            $nextoffset = $info['avdataend'];
                            $this->fseek($nextoffset);
                            break;

                        case 'iXML':
                        case 'bext':
                        case 'cart':
                        case 'fmt ':
                        case 'strh':
                        case 'strf':
                        case 'indx':
                        case 'MEXT':
                        case 'DISP':
                            // always read data in
                        case 'JUNK':
                            // should be: never read data in
                            // but some programs write their version strings in a JUNK chunk (e.g. VirtualDub, AVIdemux, etc)
                            if ($chunksize < 1048576) {
                                if ($chunksize > 0) {
                                    $RIFFchunk[$chunkname][$thisindex]['data'] = $this->fread($chunksize);
                                    if ($chunkname == 'JUNK') {
                                        if (preg_match('#^([\\x20-\\x7F]+)#', $RIFFchunk[$chunkname][$thisindex]['data'], $matches)) {
                                            $info['riff']['comments']['junk'][] = trim($matches[1]);
                                        }
                                        unset($RIFFchunk[$chunkname][$thisindex]['data']);
                                    }
                                }
                            } else {
                                $this->warning('Chunk "'.$chunkname.'" at offset '.$this->ftell().' is unexpectedly larger than 1MB (claims to be '.number_format($chunksize).' bytes), skipping data');
                                $this->fseek($chunksize, SEEK_CUR);
                            }
                            break;

                        default:
                            if (!empty($LISTchunkParent) && (($RIFFchunk[$chunkname][$thisindex]['offset'] + $RIFFchunk[$chunkname][$thisindex]['size']) <= $LISTchunkMaxOffset)) {
                                $RIFFchunk[$LISTchunkParent][$chunkname][$thisindex]['offset'] = $RIFFchunk[$chunkname][$thisindex]['offset'];
                                $RIFFchunk[$LISTchunkParent][$chunkname][$thisindex]['size']   = $RIFFchunk[$chunkname][$thisindex]['size'];
                                unset($RIFFchunk[$chunkname][$thisindex]['offset']);
                                unset($RIFFchunk[$chunkname][$thisindex]['size']);
                                if (isset($RIFFchunk[$chunkname][$thisindex]) && empty($RIFFchunk[$chunkname][$thisindex])) {
                                    unset($RIFFchunk[$chunkname][$thisindex]);
                                }
                                if (isset($RIFFchunk[$chunkname]) && empty($RIFFchunk[$chunkname])) {
                                    unset($RIFFchunk[$chunkname]);
                                }
                                $RIFFchunk[$LISTchunkParent][$chunkname][$thisindex]['data'] = $this->fread($chunksize);
                            } elseif ($chunksize < 2048) {
                                $RIFFchunk[$chunkname][$thisindex]['data'] = $this->fread($chunksize);
                            } else {
                                $this->fseek($chunksize, SEEK_CUR);
                            }
                            break;
                    }
                    break;
            }
        }

    } catch (getid3_exception $e) {
        if ($e->getCode() == 10) {
            $this->warning('RIFF parser: '.$e->getMessage());
        } else {
            throw $e;
        }
    }

    return $RIFFchunk;
}

public function ParseRIFFdata(&$RIFFdata) {
    $info = &$this->getid3->info;
    if ($RIFFdata) {
        $tempfile = tempnam(GETID3_TEMP_DIR, 'getID3');
        $fp_temp  = fopen($tempfile, 'wb');
        $RIFFdataLength = strlen($RIFFdata);
        $NewLengthString = getid3_lib::LittleEndian2String($RIFFdataLength, 4);
        for ($i = 0; $i < 4; $i++) {
            $RIFFdata[($i + 4)] = $NewLengthString[$i];
        }
        fwrite($fp_temp, $RIFFdata);
        fclose($fp_temp);

        $getid3_temp = new getID3();
        $getid3_temp->openfile($tempfile);
        $getid3_temp->info['filesize']     = $RIFFdataLength;
        $getid3_temp->info['filenamepath'] = $info['filenamepath'];
        $getid3_temp->info['tags']         = $info['tags'];
        $getid3_temp->info['warning']      = $info['warning'];
        $getid3_temp->info['error']        = $info['error'];
        $getid3_temp->info['comments']     = $info['comments'];
        $getid3_temp->info['audio']        = (isset($info['audio']) ? $info['audio'] : array());
        $getid3_temp->info['video']        = (isset($info['video']) ? $info['video'] : array());
        $getid3_riff = new getid3_riff($getid3_temp);
        $getid3_riff->Analyze();

        $info['riff']     = $getid3_temp->info['riff'];
        $info['warning']  = $getid3_temp->info['warning'];
        $info['error']    = $getid3_temp->info['error'];
        $info['tags']     = $getid3_temp->info['tags'];
        $info['comments'] = $getid3_temp->info['comments'];
        unset($getid3_riff, $getid3_temp);
        unlink($tempfile);
    }
    return false;
}

public static function parseComments(&$RIFFinfoArray, &$CommentsTargetArray) {
    $RIFFinfoKeyLookup = array(
        'IARL'=>'archivallocation',
        'IART'=>'artist',
        'ICDS'=>'costumedesigner',
        'ICMS'=>'commissionedby',
        'ICMT'=>'comment',
        'ICNT'=>'country',
        'ICOP'=>'copyright',
        'ICRD'=>'creationdate',
        'IDIM'=>'dimensions',
        'IDIT'=>'digitizationdate',
        'IDPI'=>'resolution',
        'IDST'=>'distributor',
        'IEDT'=>'editor',
        'IENG'=>'engineers',
        'IFRM'=>'accountofparts',
        'IGNR'=>'genre',
        'IKEY'=>'keywords',
        'ILGT'=>'lightness',
        'ILNG'=>'language',
        'IMED'=>'orignalmedium',
        'IMUS'=>'composer',
        'INAM'=>'title',
        'IPDS'=>'productiondesigner',
        'IPLT'=>'palette',
        'IPRD'=>'product',
        'IPRO'=>'producer',
        'IPRT'=>'part',
        'IRTD'=>'rating',
        'ISBJ'=>'subject',
        'ISFT'=>'software',
        'ISGN'=>'secondarygenre',
        'ISHP'=>'sharpness',
        'ISRC'=>'sourcesupplier',
        'ISRF'=>'digitizationsource',
        'ISTD'=>'productionstudio',
        'ISTR'=>'starring',
        'ITCH'=>'encoded_by',
        'IWEB'=>'url',
        'IWRI'=>'writer',
        '____'=>'comment',
    );
    foreach ($RIFFinfoKeyLookup as $key => $value) {
        if (isset($RIFFinfoArray[$key])) {
            foreach ($RIFFinfoArray[$key] as $commentid => $commentdata) {
                if (trim($commentdata['data']) != '') {
                    if (isset($CommentsTargetArray[$value])) {
                        $CommentsTargetArray[$value][] = trim($commentdata['data']);
                    } else {
                        $CommentsTargetArray[$value] = array(trim($commentdata['data']));
                    }
                }
            }
        }
    }
    return true;
}

public static function parseWAVEFORMATex($WaveFormatExData) {
    $WaveFormatEx['raw'] = array();
    $WaveFormatEx_raw    = &$WaveFormatEx['raw'];

    $WaveFormatEx_raw['wFormatTag']      = substr($WaveFormatExData,  0, 2);
    $WaveFormatEx_raw['nChannels']       = substr($WaveFormatExData,  2, 2);
    $WaveFormatEx_raw['nSamplesPerSec']  = substr($WaveFormatExData,  4, 4);
    $WaveFormatEx_raw['nAvgBytesPerSec'] = substr($WaveFormatExData,  8, 4);
    $WaveFormatEx_raw['nBlockAlign']     = substr($WaveFormatExData, 12, 2);
    $WaveFormatEx_raw['wBitsPerSample']  = substr($WaveFormatExData, 14, 2);
    if (strlen($WaveFormatExData) > 16) {
        $WaveFormatEx_raw['cbSize']      = substr($WaveFormatExData, 16, 2);
    }
    $WaveFormatEx_raw = array_map('getid3_lib::LittleEndian2Int', $WaveFormatEx_raw);

    $WaveFormatEx['codec']           = self::wFormatTagLookup($WaveFormatEx_raw['wFormatTag']);
    $WaveFormatEx['channels']        = $WaveFormatEx_raw['nChannels'];
    $WaveFormatEx['sample_rate']     = $WaveFormatEx_raw['nSamplesPerSec'];
    $WaveFormatEx['bitrate']         = $WaveFormatEx_raw['nAvgBytesPerSec'] * 8;
    $WaveFormatEx['bits_per_sample'] = $WaveFormatEx_raw['wBitsPerSample'];

    return $WaveFormatEx;
}

public function parseWavPackHeader($WavPackChunkData) {
    $info = &$this->getid3->info;
    $info['wavpack']  = array();
    $thisfile_wavpack = &$info['wavpack'];

    $thisfile_wavpack['version']           = getid3_lib::LittleEndian2Int(substr($WavPackChunkData,  0, 2));
    if ($thisfile_wavpack['version'] >= 2) {
        $thisfile_wavpack['bits']          = getid3_lib::LittleEndian2Int(substr($WavPackChunkData,  2, 2));
    }
    if ($thisfile_wavpack['version'] >= 3) {
        $thisfile_wavpack['flags_raw']     = getid3_lib::LittleEndian2Int(substr($WavPackChunkData,  4, 2));
        $thisfile_wavpack['shift']         = getid3_lib::LittleEndian2Int(substr($WavPackChunkData,  6, 2));
        $thisfile_wavpack['total_samples'] = getid3_lib::LittleEndian2Int(substr($WavPackChunkData,  8, 4));
        $thisfile_wavpack['crc1']          = getid3_lib::LittleEndian2Int(substr($WavPackChunkData, 12, 4));
        $thisfile_wavpack['crc2']          = getid3_lib::LittleEndian2Int(substr($WavPackChunkData, 16, 4));
        $thisfile_wavpack['extension']     = substr($WavPackChunkData, 20, 4);
        $thisfile_wavpack['extra_bc']      = getid3_lib::LittleEndian2Int(substr($WavPackChunkData, 24, 1));
        for ($i = 0; $i <= 2; $i++) {
            $thisfile_wavpack['extras'][]  = getid3_lib::LittleEndian2Int(substr($WavPackChunkData, 25 + $i, 1));
        }

        $thisfile_wavpack['flags'] = array();
        $thisfile_wavpack_flags = &$thisfile_wavpack['flags'];

        $thisfile_wavpack_flags['mono']                 = (bool) ($thisfile_wavpack['flags_raw'] & 0x000001);
        $thisfile_wavpack_flags['fast_mode']            = (bool) ($thisfile_wavpack['flags_raw'] & 0x000002);
        $thisfile_wavpack_flags['raw_mode']             = (bool) ($thisfile_wavpack['flags_raw'] & 0x000004);
        $thisfile_wavpack_flags['calc_noise']           = (bool) ($thisfile_wavpack['flags_raw'] & 0x000008);
        $thisfile_wavpack_flags['high_quality']         = (bool) ($thisfile_wavpack['flags_raw'] & 0x000010);
        $thisfile_wavpack_flags['3_byte_samples']       = (bool) ($thisfile_wavpack['flags_raw'] & 0x000020);
        $thisfile_wavpack_flags['over_20_bits']         = (bool) ($thisfile_wavpack['flags_raw'] & 0x000040);
        $thisfile_wavpack_flags['use_wvc']              = (bool) ($thisfile_wavpack['flags_raw'] & 0x000080);
        $thisfile_wavpack_flags['noiseshaping']         = (bool) ($thisfile_wavpack['flags_raw'] & 0x000100);
        $thisfile_wavpack_flags['very_fast_mode']       = (bool) ($thisfile_wavpack['flags_raw'] & 0x000200);
        $thisfile_wavpack_flags['new_high_quality']     = (bool) ($thisfile_wavpack['flags_raw'] & 0x000400);
        $thisfile_wavpack_flags['cancel_extreme']       = (bool) ($thisfile_wavpack['flags_raw'] & 0x000800);
        $thisfile_wavpack_flags['cross_decorrelation']  = (bool) ($thisfile_wavpack['flags_raw'] & 0x001000);
        $thisfile_wavpack_flags['new_decorrelation']    = (bool) ($thisfile_wavpack['flags_raw'] & 0x002000);
        $thisfile_wavpack_flags['joint_stereo']         = (bool) ($thisfile_wavpack['flags_raw'] & 0x004000);
        $thisfile_wavpack_flags['extra_decorrelation']  = (bool) ($thisfile_wavpack['flags_raw'] & 0x008000);
        $thisfile_wavpack_flags['override_noiseshape']  = (bool) ($thisfile_wavpack['flags_raw'] & 0x010000);
        $thisfile_wavpack_flags['override_jointstereo'] = (bool) ($thisfile_wavpack['flags_raw'] & 0x020000);
        $thisfile_wavpack_flags['copy_source_filetime'] = (bool) ($thisfile_wavpack['flags_raw'] & 0x040000);
        $thisfile_wavpack_flags['create_exe']           = (bool) ($thisfile_wavpack['flags_raw'] & 0x080000);
    }

    return true;
}

public static function ParseBITMAPINFOHEADER($BITMAPINFOHEADER, $littleEndian=true) {
    $parsed['biSize']          = substr($BITMAPINFOHEADER,  0, 4);
    $parsed['biWidth']         = substr($BITMAPINFOHEADER,  4, 4);
    $parsed['biHeight']        = substr($BITMAPINFOHEADER,  8, 4);
    $parsed['biPlanes']        = substr($BITMAPINFOHEADER, 12, 2);
    $parsed['biBitCount']      = substr($BITMAPINFOHEADER, 14, 2);
    $parsed['biSizeImage']     = substr($BITMAPINFOHEADER, 20, 4);
    $parsed['biXPelsPerMeter'] = substr($BITMAPINFOHEADER, 24, 4);
    $parsed['biYPelsPerMeter'] = substr($BITMAPINFOHEADER, 28, 4);
    $parsed['biClrUsed']       = substr($BITMAPINFOHEADER, 32, 4);
    $parsed['biClrImportant']  = substr($BITMAPINFOHEADER, 36, 4);
    $parsed = array_map('getid3_lib::'.($littleEndian ? 'Little' : 'Big').'Endian2Int', $parsed);

    $parsed['fourcc']          = substr($BITMAPINFOHEADER, 16, 4);

    return $parsed;
}

public static function ParseDIVXTAG($DIVXTAG, $raw=false) {
    $parsed['title']     = trim(substr($DIVXTAG,   0, 32));
    $parsed['artist']    = trim(substr($DIVXTAG,  32, 28));
    $parsed['year']      = intval(trim(substr($DIVXTAG,  60,  4)));
    $parsed['comment']   = trim(substr($DIVXTAG,  64, 48));
    $parsed['genre_id']  = intval(trim(substr($DIVXTAG, 112,  3)));
    $parsed['rating_id'] = ord(substr($DIVXTAG, 115,  1));
    $parsed['genre']  = (isset(getid3_dts::$syncwords[$parsed['genre_id']])   ? getid3_dts::$syncwords[$parsed['genre_id']]   : $parsed['genre_id']);
    $parsed['rating'] = (isset(getid3_dts::$syncwords[$parsed['rating_id']]) ? getid3_dts::$syncwords[$parsed['rating_id']] : $parsed['rating_id']);

    if (!$raw) {
        unset($parsed['genre_id'], $parsed['rating_id']);
        foreach ($parsed as $key => $value) {
            if (!$value === '') {
                unset($parsed['key']);
            }
        }
    }

    foreach ($parsed as $tag => $value) {
        $parsed[$tag] = array($value);
    }

    return $parsed;
}

public static function waveSNDMtagLookup($tagshortname) {
    $begin = __LINE__;
    return getid3_lib::EmbeddedLookup($tagshortname, $begin, __LINE__, __FILE__, 'riff-sndm');
}

public static function wFormatTagLookup($wFormatTag) {
    $begin = __LINE__;
    return getid3_lib::EmbeddedLookup('0x'.str_pad(strtoupper(dechex($wFormatTag)), 4, '0', STR_PAD_LEFT), $begin, __LINE__, __FILE__, 'riff-wFormatTag');
}

public static function fourccLookup($fourcc) {
    $begin = __LINE__;
    return getid3_lib::EmbeddedLookup($fourcc, $begin, __LINE__, __FILE__, 'riff-fourcc');
}private function eitherEndian2Int(string $byteword, bool $signed = false): int {
		if ($this->getid3->info['fileformat'] == 'riff') {
			return getid3_lib::littleEndian2Int($byteword, $signed);
		}
		return getid3_lib::bigEndian2Int($byteword, false, $signed);
	}
}
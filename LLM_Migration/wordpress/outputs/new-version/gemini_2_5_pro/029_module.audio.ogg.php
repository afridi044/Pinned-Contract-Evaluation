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
// module.audio.ogg.php                                        //
// module for analyzing Ogg Vorbis, OggFLAC and Speex files    //
// dependencies: module.audio.flac.php                         //
//                                                            ///
/////////////////////////////////////////////////////////////////

getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.audio.flac.php', __FILE__, true);

class getid3_ogg extends getid3_handler
{
	private const OGG_PAGE_SYNC = 'OggS';
	private const CODEC_VORBIS_ID = 'vorbis';
	private const CODEC_FLAC_ID = 'fLaC';
	private const CODEC_SPEEX_ID = 'Speex   ';
	private const SKELETON_FISHEAD_ID = "fishead\x00";
	private const SKELETON_FISBONE_ID = "fisbone\x00";

	/**
	 * @return bool
	 */
	public function Analyze(): bool
	{
		$info = &$this->getid3->info;

		$info['fileformat'] = 'ogg';

		// Warn about illegal tags - only vorbiscomments are allowed
		if (isset($info['id3v2'])) {
			$info['warning'][] = 'Illegal ID3v2 tag present.';
		}
		if (isset($info['id3v1'])) {
			$info['warning'][] = 'Illegal ID3v1 tag present.';
		}
		if (isset($info['ape'])) {
			$info['warning'][] = 'Illegal APE tag present.';
		}

		// Page 1 - Stream Header
		$this->fseek($info['avdataoffset']);

		$oggpageinfo = $this->ParseOggPageHeader();
		if ($oggpageinfo === false) {
			$info['error'][] = 'Could not find start of Ogg page in the first '.$this->getid3->fread_buffer_size().' bytes (this might not be an Ogg-Vorbis file?)';
			unset($info['fileformat'], $info['ogg']);
			return false;
		}
		$info['ogg']['pageheader'][$oggpageinfo['page_seqno']] = $oggpageinfo;

		$filedata = $this->fread($oggpageinfo['page_length']);
		$filedataoffset = 0;

		if (str_starts_with($filedata, self::CODEC_FLAC_ID)) {

			$info['audio']['dataformat']   = 'flac';
			$info['audio']['bitrate_mode'] = 'vbr';
			$info['audio']['lossless']     = true;

		} elseif (substr($filedata, 1, 6) === self::CODEC_VORBIS_ID) {

			$this->ParseVorbisPageHeader($filedata, $filedataoffset, $oggpageinfo);

		} elseif (str_starts_with($filedata, self::CODEC_SPEEX_ID)) {

			// http://www.speex.org/manual/node10.html
			$info['audio']['dataformat']   = 'speex';
			$info['mime_type']             = 'audio/speex';
			$info['audio']['bitrate_mode'] = 'abr';
			$info['audio']['lossless']     = false;

			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['speex_string']           = substr($filedata, $filedataoffset, 8); // hard-coded to 'Speex   '
			$filedataoffset += 8;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['speex_version']          = substr($filedata, $filedataoffset, 20);
			$filedataoffset += 20;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['speex_version_id']       = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['header_size']            = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['rate']                   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['mode']                   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['mode_bitstream_version'] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['nb_channels']            = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['bitrate']                = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['framesize']              = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['vbr']                    = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['frames_per_packet']      = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['extra_headers']          = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['reserved1']              = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;
			$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['reserved2']              = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
			$filedataoffset += 4;

			$info['speex']['speex_version'] = trim($info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['speex_version']);
			$info['speex']['sample_rate']   = $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['rate'];
			$info['speex']['channels']      = $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['nb_channels'];
			$info['speex']['vbr']           = (bool) $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['vbr'];
			$info['speex']['band_type']     = self::SpeexBandModeLookup($info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['mode']);

			$info['audio']['sample_rate']   = $info['speex']['sample_rate'];
			$info['audio']['channels']      = $info['speex']['channels'];
			if ($info['speex']['vbr']) {
				$info['audio']['bitrate_mode'] = 'vbr';
			}

		} elseif (str_starts_with($filedata, self::SKELETON_FISHEAD_ID)) {

			// Ogg Skeleton version 3.0 Format Specification
			// http://xiph.org/ogg/doc/skeleton.html
			$filedataoffset += 8;
			$info['ogg']['skeleton']['fishead']['raw']['version_major']                = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 2));
			$filedataoffset += 2;
			$info['ogg']['skeleton']['fishead']['raw']['version_minor']                = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 2));
			$filedataoffset += 2;
			$info['ogg']['skeleton']['fishead']['raw']['presentationtime_numerator']   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
			$filedataoffset += 8;
			$info['ogg']['skeleton']['fishead']['raw']['presentationtime_denominator'] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
			$filedataoffset += 8;
			$info['ogg']['skeleton']['fishead']['raw']['basetime_numerator']           = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
			$filedataoffset += 8;
			$info['ogg']['skeleton']['fishead']['raw']['basetime_denominator']         = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
			$filedataoffset += 8;
			$info['ogg']['skeleton']['fishead']['raw']['utc']                          = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 20));
			$filedataoffset += 20;

			$info['ogg']['skeleton']['fishead']['version']          = $info['ogg']['skeleton']['fishead']['raw']['version_major'].'.'.$info['ogg']['skeleton']['fishead']['raw']['version_minor'];
			$info['ogg']['skeleton']['fishead']['presentationtime'] = $info['ogg']['skeleton']['fishead']['raw']['presentationtime_numerator'] / $info['ogg']['skeleton']['fishead']['raw']['presentationtime_denominator'];
			$info['ogg']['skeleton']['fishead']['basetime']         = $info['ogg']['skeleton']['fishead']['raw']['basetime_numerator'] / $info['ogg']['skeleton']['fishead']['raw']['basetime_denominator'];
			$info['ogg']['skeleton']['fishead']['utc']              = $info['ogg']['skeleton']['fishead']['raw']['utc'];

			$counter = 0;
			do {
				$oggpageinfo = $this->ParseOggPageHeader();
				$info['ogg']['pageheader'][$oggpageinfo['page_seqno'].'.'.$counter++] = $oggpageinfo;
				$filedata = $this->fread($oggpageinfo['page_length']);
				$this->fseek($oggpageinfo['page_end_offset']);

				if (str_starts_with($filedata, self::SKELETON_FISBONE_ID)) {
					$filedataoffset = 8;
					$info['ogg']['skeleton']['fisbone']['raw']['message_header_offset']   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
					$filedataoffset += 4;
					$info['ogg']['skeleton']['fisbone']['raw']['serial_number']           = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
					$filedataoffset += 4;
					$info['ogg']['skeleton']['fisbone']['raw']['number_header_packets']   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
					$filedataoffset += 4;
					$info['ogg']['skeleton']['fisbone']['raw']['granulerate_numerator']   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
					$filedataoffset += 8;
					$info['ogg']['skeleton']['fisbone']['raw']['granulerate_denominator'] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
					$filedataoffset += 8;
					$info['ogg']['skeleton']['fisbone']['raw']['basegranule']             = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
					$filedataoffset += 8;
					$info['ogg']['skeleton']['fisbone']['raw']['preroll']                 = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
					$filedataoffset += 4;
					$info['ogg']['skeleton']['fisbone']['raw']['granuleshift']            = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
					$filedataoffset += 1;
					$info['ogg']['skeleton']['fisbone']['raw']['padding']                 = substr($filedata, $filedataoffset, 3);
					$filedataoffset += 3;
				} elseif (substr($filedata, 1, 6) === 'theora') {
					$info['video']['dataformat'] = 'theora';
					$info['error'][] = 'Ogg Theora not correctly handled in this version of getID3 ['.$this->getid3->version().']';
				} elseif (substr($filedata, 1, 6) === self::CODEC_VORBIS_ID) {
					$this->ParseVorbisPageHeader($filedata, $filedataoffset, $oggpageinfo);
				} else {
					$info['error'][] = 'unexpected';
				}
			} while (($oggpageinfo['page_seqno'] === 0) && (!str_starts_with($filedata, self::SKELETON_FISBONE_ID)));

			$this->fseek($oggpageinfo['page_start_offset']);
			$info['error'][] = 'Ogg Skeleton not correctly handled in this version of getID3 ['.$this->getid3->version().']';
		} else {
			$info['error'][] = 'Expecting either "'.self::CODEC_SPEEX_ID.'" or "'.self::CODEC_VORBIS_ID.'" identifier strings, found "'.substr($filedata, 0, 8).'"';
			unset($info['ogg'], $info['mime_type']);
			return false;
		}

		// Page 2 - Comment Header
		$oggpageinfo = $this->ParseOggPageHeader();
		$info['ogg']['pageheader'][$oggpageinfo['page_seqno']] = $oggpageinfo;

		switch ($info['audio']['dataformat']) {
			case 'vorbis':
				$filedata = $this->fread($info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['page_length']);
				$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['packet_type'] = getid3_lib::LittleEndian2Int(substr($filedata, 0, 1));
				$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['stream_type'] = substr($filedata, 1, 6); // hard-coded to 'vorbis'
				$this->ParseVorbisComments();
				break;

			case 'flac':
				$flac = new getid3_flac($this->getid3);
				if (!$flac->parseMETAdata()) {
					$info['error'][] = 'Failed to parse FLAC headers';
					return false;
				}
				unset($flac);
				break;

			case 'speex':
				$this->fseek($info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['page_length'], SEEK_CUR);
				$this->ParseVorbisComments();
				break;
		}

		// Last Page - Number of Samples
		if (!getid3_lib::intValueSupported($info['avdataend'])) {
			$info['warning'][] = 'Unable to parse Ogg end chunk file (PHP does not support file operations beyond '.round(PHP_INT_MAX / 1073741824).'GB)';
		} else {
			$this->fseek(max($info['avdataend'] - $this->getid3->fread_buffer_size(), 0));
			$LastChunkOfOgg = strrev($this->fread($this->getid3->fread_buffer_size()));
			if ($LastOggSpostion = strpos($LastChunkOfOgg, strrev(self::OGG_PAGE_SYNC))) {
				$this->fseek($info['avdataend'] - ($LastOggSpostion + strlen(self::OGG_PAGE_SYNC)));
				$info['avdataend'] = $this->ftell();
				$info['ogg']['pageheader']['eos'] = $this->ParseOggPageHeader();
				$info['ogg']['samples']   = $info['ogg']['pageheader']['eos']['pcm_abs_position'];
				if ($info['ogg']['samples'] === 0) {
					$info['error'][] = 'Corrupt Ogg file: eos.number of samples == zero';
					return false;
				}
				if (!empty($info['audio']['sample_rate'])) {
					$info['ogg']['bitrate_average'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / ($info['ogg']['samples'] / $info['audio']['sample_rate']);
				}
			}
		}

		if (!empty($info['ogg']['bitrate_average'])) {
			$info['audio']['bitrate'] = $info['ogg']['bitrate_average'];
		} elseif (!empty($info['ogg']['bitrate_nominal'])) {
			$info['audio']['bitrate'] = $info['ogg']['bitrate_nominal'];
		} elseif (!empty($info['ogg']['bitrate_min']) && !empty($info['ogg']['bitrate_max'])) {
			$info['audio']['bitrate'] = ($info['ogg']['bitrate_min'] + $info['ogg']['bitrate_max']) / 2;
		}

		if (isset($info['audio']['bitrate']) && !isset($info['playtime_seconds'])) {
			if ($info['audio']['bitrate'] === 0) {
				$info['error'][] = 'Corrupt Ogg file: bitrate_audio == zero';
				return false;
			}
			$info['playtime_seconds'] = (($info['avdataend'] - $info['avdataoffset']) * 8) / $info['audio']['bitrate'];
		}

		if (isset($info['ogg']['vendor'])) {
			$info['audio']['encoder'] = preg_replace('/^Encoded with /', '', $info['ogg']['vendor']);

			// Vorbis only
			if ($info['audio']['dataformat'] === 'vorbis') {
				// Vorbis 1.0 starts with Xiph.Org
				if (preg_match('/^Xiph.Org/', $info['audio']['encoder'])) {
					if ($info['audio']['bitrate_mode'] === 'abr') {
						// Set -b 128 on abr files
						$info['audio']['encoder_options'] = '-b '.round($info['ogg']['bitrate_nominal'] / 1000);
					} elseif (($info['audio']['bitrate_mode'] === 'vbr') && ($info['audio']['channels'] === 2) && ($info['audio']['sample_rate'] >= 44100) && ($info['audio']['sample_rate'] <= 48000)) {
						// Set -q N on vbr files
						$info['audio']['encoder_options'] = '-q '.self::get_quality_from_nominal_bitrate($info['ogg']['bitrate_nominal']);
					}
				}

				if (empty($info['audio']['encoder_options']) && !empty($info['ogg']['bitrate_nominal'])) {
					$info['audio']['encoder_options'] = 'Nominal bitrate: '.round($info['ogg']['bitrate_nominal'] / 1000).'kbps';
				}
			}
		}

		return true;
	}

	/**
	 * @param string $filedata
	 * @param int    $filedataoffset
	 * @param array  $oggpageinfo
	 *
	 * @return bool
	 */
	public function ParseVorbisPageHeader(string $filedata, int &$filedataoffset, array $oggpageinfo): bool
	{
		$info = &$this->getid3->info;
		$info['audio']['dataformat'] = 'vorbis';
		$info['audio']['lossless']   = false;

		$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['packet_type'] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
		$filedataoffset += 1;
		$info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['stream_type'] = substr($filedata, $filedataoffset, 6); // hard-coded to 'vorbis'
		$filedataoffset += 6;
		$info['ogg']['bitstreamversion'] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$info['ogg']['numberofchannels'] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
		$filedataoffset += 1;
		$info['audio']['channels']       = $info['ogg']['numberofchannels'];
		$info['ogg']['samplerate']       = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		if ($info['ogg']['samplerate'] === 0) {
			$info['error'][] = 'Corrupt Ogg file: sample rate == zero';
			return false;
		}
		$info['audio']['sample_rate']    = $info['ogg']['samplerate'];
		$info['ogg']['samples']          = 0; // filled in later
		$info['ogg']['bitrate_average']  = 0; // filled in later
		$info['ogg']['bitrate_max']      = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$info['ogg']['bitrate_nominal']  = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$info['ogg']['bitrate_min']      = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$info['ogg']['blocksize_small']  = 2 ** (getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1)) & 0x0F);
		$info['ogg']['blocksize_large']  = 2 ** ((getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1)) & 0xF0) >> 4);
		$info['ogg']['stop_bit']         = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1)); // must be 1, marks end of packet

		$info['audio']['bitrate_mode'] = 'vbr'; // overridden if actually abr
		if ($info['ogg']['bitrate_max'] === 0xFFFFFFFF) {
			unset($info['ogg']['bitrate_max']);
			$info['audio']['bitrate_mode'] = 'abr';
		}
		if ($info['ogg']['bitrate_nominal'] === 0xFFFFFFFF) {
			unset($info['ogg']['bitrate_nominal']);
		}
		if ($info['ogg']['bitrate_min'] === 0xFFFFFFFF) {
			unset($info['ogg']['bitrate_min']);
			$info['audio']['bitrate_mode'] = 'abr';
		}
		return true;
	}

	/**
	 * @return array|false
	 */
	public function ParseOggPageHeader(): array|false
	{
		// http://xiph.org/ogg/vorbis/doc/framing.html
		$oggheader = [];
		$oggheader['page_start_offset'] = $this->ftell(); // where we started from in the file

		$filedata = $this->fread($this->getid3->fread_buffer_size());
		$filedataoffset = 0;
		while (substr($filedata, $filedataoffset, 4) !== self::OGG_PAGE_SYNC) {
			$filedataoffset++;
			if (($this->ftell() - $oggheader['page_start_offset']) >= $this->getid3->fread_buffer_size()) {
				// should be found before here
				return false;
			}
			if ((strlen($filedata) - $filedataoffset) < 28) {
				if ($this->feof()) {
					return false;
				}
				$newData = $this->fread($this->getid3->fread_buffer_size());
				if ($newData === false || $newData === '') {
					// get some more data, unless eof, in which case fail
					return false;
				}
				$filedata .= $newData;
			}
		}
		$filedataoffset += 4; // page, delimited by 'OggS'

		$oggheader['stream_structver']  = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
		$filedataoffset += 1;
		$oggheader['flags_raw']         = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
		$filedataoffset += 1;
		$oggheader['flags']['fresh']    = (bool) ($oggheader['flags_raw'] & 0x01); // fresh packet
		$oggheader['flags']['bos']      = (bool) ($oggheader['flags_raw'] & 0x02); // first page of logical bitstream (bos)
		$oggheader['flags']['eos']      = (bool) ($oggheader['flags_raw'] & 0x04); // last page of logical bitstream (eos)

		$oggheader['pcm_abs_position']  = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 8));
		$filedataoffset += 8;
		$oggheader['stream_serialno']   = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$oggheader['page_seqno']        = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$oggheader['page_checksum']     = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 4));
		$filedataoffset += 4;
		$oggheader['page_segments']     = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
		$filedataoffset += 1;
		$oggheader['page_length'] = 0;
		for ($i = 0; $i < $oggheader['page_segments']; $i++) {
			$oggheader['segment_table'][$i] = getid3_lib::LittleEndian2Int(substr($filedata, $filedataoffset, 1));
			$filedataoffset += 1;
			$oggheader['page_length'] += $oggheader['segment_table'][$i];
		}
		$oggheader['header_end_offset'] = $oggheader['page_start_offset'] + $filedataoffset;
		$oggheader['page_end_offset']   = $oggheader['header_end_offset'] + $oggheader['page_length'];
		$this->fseek($oggheader['header_end_offset']);

		return $oggheader;
	}

	/**
	 * @return bool
	 * http://xiph.org/vorbis/doc/Vorbis_I_spec.html#x1-810005
	 */
	public function ParseVorbisComments(): bool
	{
		$info = &$this->getid3->info;

		$OriginalOffset = $this->ftell();
		$commentdataoffset = 0;
		$VorbisCommentPage = 1;

		switch ($info['audio']['dataformat']) {
			case 'vorbis':
			case 'speex':
				$CommentStartOffset = $info['ogg']['pageheader'][$VorbisCommentPage]['page_start_offset'];  // Second Ogg page, after header block
				$this->fseek($CommentStartOffset);
				$commentdataoffset = 27 + $info['ogg']['pageheader'][$VorbisCommentPage]['page_segments'];
				$commentdata = $this->fread(self::OggPageSegmentLength($info['ogg']['pageheader'][$VorbisCommentPage], 1) + $commentdataoffset);

				if ($info['audio']['dataformat'] === 'vorbis') {
					$commentdataoffset += (strlen(self::CODEC_VORBIS_ID) + 1);
				}
				break;

			case 'flac':
				$CommentStartOffset = $info['flac']['VORBIS_COMMENT']['raw']['offset'] + 4;
				$this->fseek($CommentStartOffset);
				$commentdata = $this->fread($info['flac']['VORBIS_COMMENT']['raw']['block_length']);
				break;

			default:
				return false;
		}

		$VendorSize = getid3_lib::LittleEndian2Int(substr($commentdata, $commentdataoffset, 4));
		$commentdataoffset += 4;

		$info['ogg']['vendor'] = substr($commentdata, $commentdataoffset, $VendorSize);
		$commentdataoffset += $VendorSize;

		$CommentsCount = getid3_lib::LittleEndian2Int(substr($commentdata, $commentdataoffset, 4));
		$commentdataoffset += 4;
		$info['avdataoffset'] = $CommentStartOffset + $commentdataoffset;

		$info['ogg']['comments_raw'] = [];
		for ($i = 0; $i < $CommentsCount; $i++) {
			$comment = [];
			$comment['dataoffset'] = $CommentStartOffset + $commentdataoffset;

			if ($this->ftell() < ($comment['dataoffset'] + 4)) {
				if ($oggpageinfo = $this->ParseOggPageHeader()) {
					$info['ogg']['pageheader'][$oggpageinfo['page_seqno']] = $oggpageinfo;
					$VorbisCommentPage++;
					$AsYetUnusedData = substr($commentdata, $commentdataoffset);
					$commentdata = substr($commentdata, 0, $commentdataoffset);
					$commentdata .= str_repeat("\x00", 27 + $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['page_segments']);
					$commentdataoffset += (27 + $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['page_segments']);
					$commentdata .= $AsYetUnusedData;
					$commentdata .= $this->fread(self::OggPageSegmentLength($info['ogg']['pageheader'][$VorbisCommentPage], 1));
				}
			}
			$comment['size'] = getid3_lib::LittleEndian2Int(substr($commentdata, $commentdataoffset, 4));
			$info['avdataoffset'] = $comment['dataoffset'] + $comment['size'] + 4;
			$commentdataoffset += 4;

			while ((strlen($commentdata) - $commentdataoffset) < $comment['size']) {
				if (($comment['size'] > $info['avdataend']) || ($comment['size'] < 0)) {
					$info['warning'][] = 'Invalid Ogg comment size (comment #'.$i.', claims to be '.number_format($comment['size']).' bytes) - aborting reading comments';
					break 2;
				}

				$VorbisCommentPage++;
				$oggpageinfo = $this->ParseOggPageHeader();
				$info['ogg']['pageheader'][$oggpageinfo['page_seqno']] = $oggpageinfo;
				$AsYetUnusedData = substr($commentdata, $commentdataoffset);
				$commentdata = substr($commentdata, 0, $commentdataoffset);
				$commentdata .= str_repeat("\x00", 27 + $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['page_segments']);
				$commentdataoffset += (27 + $info['ogg']['pageheader'][$oggpageinfo['page_seqno']]['page_segments']);
				$commentdata .= $AsYetUnusedData;

				if (!isset($info['ogg']['pageheader'][$VorbisCommentPage])) {
					$info['warning'][] = 'undefined Vorbis Comment page "'.$VorbisCommentPage.'" at offset '.$this->ftell();
					break;
				}
				$readlength = self::OggPageSegmentLength($info['ogg']['pageheader'][$VorbisCommentPage], 1);
				if ($readlength <= 0) {
					$info['warning'][] = 'invalid length Vorbis Comment page "'.$VorbisCommentPage.'" at offset '.$this->ftell();
					break;
				}
				$commentdata .= $this->fread($readlength);
			}
			$comment['offset'] = $commentdataoffset;
			$commentstring = substr($commentdata, $commentdataoffset, $comment['size']);
			$commentdataoffset += $comment['size'];

			if ($commentstring === '') {
				$info['warning'][] = 'Blank Ogg comment ['.$i.']';
			} elseif (str_contains($commentstring, '=')) {
				$commentexploded = explode('=', $commentstring, 2);
				$comment['key']   = strtoupper($commentexploded[0]);
				$comment['value'] = $commentexploded[1] ?? '';

				if ($comment['key'] === 'METADATA_BLOCK_PICTURE') {
					$flac = new getid3_flac($this->getid3);
					$flac->setStringMode(base64_decode($comment['value']));
					$flac->parsePICTURE();
					$info['ogg']['comments']['picture'][] = $this->getid3->info['flac']['PICTURE'][0];
					unset($flac);
				} elseif ($comment['key'] === 'COVERART') {
					$data = base64_decode($comment['value']);
					$this->notice('Found deprecated COVERART tag, it should be replaced in honor of METADATA_BLOCK_PICTURE structure');
					$imageinfo = getid3_lib::GetDataImageSize($data);
					if ($imageinfo === false || !isset($imageinfo['mime'])) {
						$this->warning('COVERART vorbiscomment tag contains invalid image');
						continue;
					}
					$ogg = new self($this->getid3);
					$ogg->setStringMode($data);
					$info['ogg']['comments']['picture'][] = [
						'image_mime' => $imageinfo['mime'],
						'data'       => $ogg->saveAttachment('coverart', 0, strlen($data), $imageinfo['mime']),
					];
					unset($ogg);
				} else {
					$info['ogg']['comments'][strtolower($comment['key'])][] = $comment['value'];
				}
			} else {
				$info['warning'][] = '[known problem with CDex >= v1.40, < v1.50b7] Invalid Ogg comment name/value pair ['.$i.']: '.$commentstring;
			}
			$info['ogg']['comments_raw'][] = $comment;
		}

		// Replay Gain Adjustment
		// http://privatewww.essex.ac.uk/~djmrob/replaygain/
		if (isset($info['ogg']['comments']) && is_array($info['ogg']['comments'])) {
			foreach ($info['ogg']['comments'] as $index => $commentvalue) {
				switch ($index) {
					case 'rg_audiophile':
					case 'replaygain_album_gain':
						$info['replay_gain']['album']['adjustment'] = (float) $commentvalue[0];
						unset($info['ogg']['comments'][$index]);
						break;
					case 'rg_radio':
					case 'replaygain_track_gain':
						$info['replay_gain']['track']['adjustment'] = (float) $commentvalue[0];
						unset($info['ogg']['comments'][$index]);
						break;
					case 'replaygain_album_peak':
						$info['replay_gain']['album']['peak'] = (float) $commentvalue[0];
						unset($info['ogg']['comments'][$index]);
						break;
					case 'rg_peak':
					case 'replaygain_track_peak':
						$info['replay_gain']['track']['peak'] = (float) $commentvalue[0];
						unset($info['ogg']['comments'][$index]);
						break;
					case 'replaygain_reference_loudness':
						$info['replay_gain']['reference_volume'] = (float) $commentvalue[0];
						unset($info['ogg']['comments'][$index]);
						break;
				}
			}
		}

		$this->fseek($OriginalOffset);
		return true;
	}

	/**
	 * @param int $mode
	 *
	 * @return string|null
	 */
	public static function SpeexBandModeLookup(int $mode): ?string
	{
		static $SpeexBandModeLookup = [
			0 => 'narrow',
			1 => 'wide',
			2 => 'ultra-wide',
		];
		return $SpeexBandModeLookup[$mode] ?? null;
	}

	/**
	 * @param array $OggInfoArray
	 * @param int   $SegmentNumber
	 *
	 * @return int
	 */
	public static function OggPageSegmentLength(array $OggInfoArray, int $SegmentNumber = 1): int
	{
		$segmentlength = 0;
		for ($i = 0; $i < $SegmentNumber; $i++) {
			foreach ($OggInfoArray['segment_table'] as $value) {
				$segmentlength += $value;
				if ($value < 255) {
					break;
				}
			}
		}
		return $segmentlength;
	}

	/**
	 * @param float|int $nominal_bitrate
	 *
	 * @return float
	 */
	public static function get_quality_from_nominal_bitrate(float|int $nominal_bitrate): float
	{
		// decrease precision
		$nominal_bitrate /= 1000;

		if ($nominal_bitrate < 128) {
			// q-1 to q4
			$qval = ($nominal_bitrate - 64) / 16;
		} elseif ($nominal_bitrate < 256) {
			// q4 to q8
			$qval = $nominal_bitrate / 32;
		} elseif ($nominal_bitrate < 320) {
			// q8 to q9
			$qval = ($nominal_bitrate + 256) / 64;
		} else {
			// q9 to q10
			$qval = ($nominal_bitrate + 1300) / 180;
		}
		return round($qval, 1);
	}
}
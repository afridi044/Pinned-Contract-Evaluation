<?php
declare(strict_types=1);

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
///                                                            //
// module.tag.lyrics3.php                                      //
// module for analyzing Lyrics3 tags                           //
// dependencies: module.tag.apetag.php (optional)              //
//                                                            ///
/////////////////////////////////////////////////////////////////

class getid3_lyrics3 extends getid3_handler
{
    private const LYRICS_BEGIN = 'LYRICSBEGIN';
    private const LYRICS_BEGIN_LENGTH = 11;
    private const LYRICS_END_V1 = 'LYRICSEND';
    private const LYRICS_END_V2 = 'LYRICS200';
    private const LYRICS_V1_SIZE = 5100;
    private const TAG_TERMINATOR_LENGTH = 9;
    private const SIZE_FIELD_LENGTH = 6;
    private const ID3V1_TAG_LENGTH = 128;
    private const BACKSCAN_LENGTH = self::ID3V1_TAG_LENGTH + self::TAG_TERMINATOR_LENGTH + self::SIZE_FIELD_LENGTH;

    public function Analyze(): bool
    {
        $info = &$this->getid3->info;
        $info['warning'] ??= [];
        $info['error'] ??= [];

        $fileSizeValue = $info['filesize'] ?? 0;

        if (!getid3_lib::intValueSupported($fileSizeValue)) {
            $info['warning'][] = 'Unable to check for Lyrics3 because file is larger than ' . round(PHP_INT_MAX / 1073741824) . 'GB';
            return false;
        }

        $fileSize = (int) $fileSizeValue;

        $lyrics3Offset = null;
        $lyrics3Version = null;
        $lyrics3Size = null;

        fseek($this->getid3->fp, -self::BACKSCAN_LENGTH, SEEK_END);
        $lyrics3Chunk = fread($this->getid3->fp, self::BACKSCAN_LENGTH);
        if ($lyrics3Chunk === false) {
            return false;
        }

        $lyrics3SizeField = substr($lyrics3Chunk, 0, self::SIZE_FIELD_LENGTH);
        $lyrics3End = substr($lyrics3Chunk, self::SIZE_FIELD_LENGTH, self::TAG_TERMINATOR_LENGTH);
        $lyrics3EndTail = substr($lyrics3Chunk, -self::TAG_TERMINATOR_LENGTH);
        $lyrics3SizeFieldTail = substr($lyrics3Chunk, -self::TAG_TERMINATOR_LENGTH - self::SIZE_FIELD_LENGTH, self::SIZE_FIELD_LENGTH);

        if ($lyrics3End === self::LYRICS_END_V1) {
            $lyrics3Size = self::LYRICS_V1_SIZE;
            $lyrics3Offset = $fileSize - self::ID3V1_TAG_LENGTH - $lyrics3Size;
            $lyrics3Version = 1;
        } elseif ($lyrics3End === self::LYRICS_END_V2) {
            $lyrics3Size = (int) $lyrics3SizeField + self::SIZE_FIELD_LENGTH + self::TAG_TERMINATOR_LENGTH;
            $lyrics3Offset = $fileSize - self::ID3V1_TAG_LENGTH - $lyrics3Size;
            $lyrics3Version = 2;
        } elseif ($lyrics3EndTail === self::LYRICS_END_V1) {
            $lyrics3Size = self::LYRICS_V1_SIZE;
            $lyrics3Offset = $fileSize - $lyrics3Size;
            $lyrics3Version = 1;
        } elseif ($lyrics3EndTail === self::LYRICS_END_V2) {
            $lyrics3Size = (int) $lyrics3SizeFieldTail + self::SIZE_FIELD_LENGTH + self::TAG_TERMINATOR_LENGTH;
            $lyrics3Offset = $fileSize - $lyrics3Size;
            $lyrics3Version = 2;
        } elseif (isset($info['ape']['tag_offset_start']) && $info['ape']['tag_offset_start'] > (self::TAG_TERMINATOR_LENGTH + self::SIZE_FIELD_LENGTH)) {
            $apeOffsetStart = (int) $info['ape']['tag_offset_start'];
            fseek($this->getid3->fp, $apeOffsetStart - (self::TAG_TERMINATOR_LENGTH + self::SIZE_FIELD_LENGTH), SEEK_SET);
            $lyrics3SizeField = fread($this->getid3->fp, self::SIZE_FIELD_LENGTH);
            $lyrics3End = fread($this->getid3->fp, self::TAG_TERMINATOR_LENGTH);

            if ($lyrics3End === self::LYRICS_END_V1) {
                $lyrics3Size = self::LYRICS_V1_SIZE;
                $lyrics3Offset = $apeOffsetStart - $lyrics3Size;
                $info['avdataend'] = $lyrics3Offset;
                $lyrics3Version = 1;
                $info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatability';
            } elseif ($lyrics3End === self::LYRICS_END_V2) {
                $lyrics3Size = (int) $lyrics3SizeField + self::SIZE_FIELD_LENGTH + self::TAG_TERMINATOR_LENGTH;
                $lyrics3Offset = $apeOffsetStart - $lyrics3Size;
                $lyrics3Version = 2;
                $info['warning'][] = 'APE tag located after Lyrics3, will probably break Lyrics3 compatability';
            }
        }

        if ($lyrics3Offset !== null && $lyrics3Version !== null && $lyrics3Size !== null) {
            $info['avdataend'] = $lyrics3Offset;
            $this->getLyrics3Data($lyrics3Offset, $lyrics3Version, $lyrics3Size);

            if (!isset($info['ape'])) {
                $GETID3_ERRORARRAY = &$info['warning'];
                if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH . 'module.tag.apetag.php', __FILE__, false)) {
                    $getid3Temp = new getID3();
                    $getid3Temp->openfile($this->getid3->filename);
                    $getid3ApeTag = new getid3_apetag($getid3Temp);
                    if (isset($info['lyrics3']['tag_offset_start'])) {
                        $getid3ApeTag->overrideendoffset = $info['lyrics3']['tag_offset_start'];
                    }
                    $getid3ApeTag->Analyze();

                    if (!empty($getid3Temp->info['ape'])) {
                        $info['ape'] = $getid3Temp->info['ape'];
                    }
                    if (!empty($getid3Temp->info['replay_gain'])) {
                        $info['replay_gain'] = $getid3Temp->info['replay_gain'];
                    }
                    unset($getid3Temp, $getid
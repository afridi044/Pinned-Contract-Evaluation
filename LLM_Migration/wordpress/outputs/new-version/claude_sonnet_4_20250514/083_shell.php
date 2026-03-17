<?php
/**
 * Class used internally by Diff to actually compute the diffs.
 *
 * This class uses the Unix `diff` program via shell_exec to compute the
 * differences between the two input arrays.
 *
 * Copyright 2007-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @author  Milian Wolff <mail@milianw.de>
 * @package Text_Diff
 * @since   0.3.0
 */
class Text_Diff_Engine_shell {

    /**
     * Path to the diff executable
     */
    private string $_diffCommand = 'diff';

    /**
     * Returns the array of differences.
     *
     * @param array $from_lines lines of text from old file
     * @param array $to_lines   lines of text from new file
     *
     * @return array all changes made (array with Text_Diff_Op_* objects)
     */
    public function diff(array $from_lines, array $to_lines): array
    {
        array_walk($from_lines, [Text_Diff::class, 'trimNewlines']);
        array_walk($to_lines, [Text_Diff::class, 'trimNewlines']);

        $temp_dir = Text_Diff::_getTempDir();

        // Execute gnu diff or similar to get a standard diff file.
        $from_file = tempnam($temp_dir, 'Text_Diff');
        $to_file = tempnam($temp_dir, 'Text_Diff');
        
        file_put_contents($from_file, implode("\n", $from_lines));
        file_put_contents($to_file, implode("\n", $to_lines));
        
        $diff = shell_exec($this->_diffCommand . ' ' . escapeshellarg($from_file) . ' ' . escapeshellarg($to_file));
        unlink($from_file);
        unlink($to_file);

        if ($diff === null) {
            // No changes were made
            return [new Text_Diff_Op_copy($from_lines)];
        }

        $from_line_no = 1;
        $to_line_no = 1;
        $edits = [];

        // Get changed lines by parsing something like:
        // 0a1,2
        // 1,2c4,6
        // 1,5d6
        preg_match_all('#^(\d+)(?:,(\d+))?([adc])(\d+)(?:,(\d+))?$#m', $diff,
            $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $match[5] ??= false;

            if ($match[3] === 'a') {
                $from_line_no--;
            }

            if ($match[3] === 'd') {
                $to_line_no--;
            }

            if ($from_line_no < $match[1] || $to_line_no < $match[4]) {
                // copied lines
                assert($match[1] - $from_line_no === $match[4] - $to_line_no);
                $edits[] = new Text_Diff_Op_copy(
                    $this->_getLines($from_lines, $from_line_no, $match[1] - 1),
                    $this->_getLines($to_lines, $to_line_no, $match[4] - 1)
                );
            }

            switch ($match[3]) {
                case 'd':
                    // deleted lines
                    $edits[] = new Text_Diff_Op_delete(
                        $this->_getLines($from_lines, $from_line_no, $match[2])
                    );
                    $to_line_no++;
                    break;

                case 'c':
                    // changed lines
                    $edits[] = new Text_Diff_Op_change(
                        $this->_getLines($from_lines, $from_line_no, $match[2]),
                        $this->_getLines($to_lines, $to_line_no, $match[5])
                    );
                    break;

                case 'a':
                    // added lines
                    $edits[] = new Text_Diff_Op_add(
                        $this->_getLines($to_lines, $to_line_no, $match[5])
                    );
                    $from_line_no++;
                    break;
            }
        }

        if (!empty($from_lines)) {
            // Some lines might still be pending. Add them as copied
            $edits[] = new Text_Diff_Op_copy(
                $this->_getLines($from_lines, $from_line_no,
                                 $from_line_no + count($from_lines) - 1),
                $this->_getLines($to_lines, $to_line_no,
                                 $to_line_no + count($to_lines) - 1)
            );
        }

        return $edits;
    }

    /**
     * Get lines from either the old or new text
     *
     * @param array $text_lines Either $from_lines or $to_lines
     * @param int   $line_no    Current line number
     * @param int|false $end    Optional end line, when we want to chop more
     *                          than one line.
     *
     * @return array The chopped lines
     */
    private function _getLines(array &$text_lines, int &$line_no, int|false $end = false): array
    {
        if (!empty($end)) {
            $lines = [];
            // We can shift even more
            while ($line_no <= $end) {
                $lines[] = array_shift($text_lines);
                $line_no++;
            }
        } else {
            $lines = [array_shift($text_lines)];
            $line_no++;
        }

        return $lines;
    }
}
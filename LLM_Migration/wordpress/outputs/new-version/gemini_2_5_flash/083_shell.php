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
class Text_Diff_Engine_shell
{
    /**
     * Path to the diff executable.
     *
     * @var string
     */
    private string $_diffCommand = 'diff';

    /**
     * Returns the array of differences.
     *
     * @param array<string> $from_lines Lines of text from old file.
     * @param array<string> $to_lines   Lines of text from new file.
     *
     * @return array<Text_Diff_Op_copy|Text_Diff_Op_delete|Text_Diff_Op_change|Text_Diff_Op_add> All changes made (array with Text_Diff_Op_* objects).
     */
    public function diff(array $from_lines, array $to_lines): array
    {
        // Assuming Text_Diff is a class with a static method trimNewlines
        array_walk($from_lines, [Text_Diff::class, 'trimNewlines']);
        array_walk($to_lines, [Text_Diff::class, 'trimNewlines']);

        // Assuming Text_Diff is a class with a static method _getTempDir
        $temp_dir = Text_Diff::_getTempDir();

        // Execute gnu diff or similar to get a standard diff file.
        $from_file = tempnam($temp_dir, 'Text_Diff');
        $to_file = tempnam($temp_dir, 'Text_Diff');

        // Handle potential tempnam failures
        if ($from_file === false || $to_file === false) {
            // If tempnam fails, we cannot create diff files.
            // Replicate original behavior: return a copy operation as a fallback.
            return [new Text_Diff_Op_copy($from_lines)];
        }

        $fp_from = fopen($from_file, 'w');
        if ($fp_from === false) {
            // Clean up the other temp file if it was created
            unlink($to_file);
            return [new Text_Diff_Op_copy($from_lines)];
        }
        fwrite($fp_from, implode("\n", $from_lines));
        fclose($fp_from);

        $fp_to = fopen($to_file, 'w');
        if ($fp_to === false) {
            // Clean up the other temp file
            unlink($from_file);
            return [new Text_Diff_Op_copy($from_lines)];
        }
        fwrite($fp_to, implode("\n", $to_lines));
        fclose($fp_to);

        $diff = shell_exec("{$this->_diffCommand} {$from_file} {$to_file}");
        unlink($from_file);
        unlink($to_file);

        if ($diff === null) {
            // No changes were made or shell_exec failed.
            // Original code treated null as "no changes".
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
            // Cast match groups to int for arithmetic operations
            $match1_int = (int)$match[1];
            // Default optional groups to 0 if not set, then cast to int
            $match2_int = (int)($match[2] ?? 0);
            $match4_int = (int)$match[4];
            $match5_int = (int)($match[5] ?? 0);

            if ($match[3] == 'a') {
                $from_line_no--;
            }

            if ($match[3] == 'd') {
                $to_line_no--;
            }

            if ($from_line_no < $match1_int || $to_line_no < $match4_int) {
                // copied lines
                // Assert statement updated for PHP 8.3
                assert($match1_int - $from_line_no == $match4_int - $to_line_no);
                $edits[] = new Text_Diff_Op_copy(
                    $this->_getLines($from_lines, $from_line_no, $match1_int - 1),
                    $this->_getLines($to_lines, $to_line_no, $match4_int - 1)
                );
            }

            switch ($match[3]) {
                case 'd':
                    // deleted lines
                    $edits[] = new Text_Diff_Op_delete(
                        $this->_getLines($from_lines, $from_line_no, $match2_int)
                    );
                    $to_line_no++;
                    break;

                case 'c':
                    // changed lines
                    $edits[] = new Text_Diff_Op_change(
                        $this->_getLines($from_lines, $from_line_no, $match2_int),
                        $this->_getLines($to_lines, $to_line_no, $match5_int)
                    );
                    break;

                case 'a':
                    // added lines
                    $edits[] = new Text_Diff_Op_add(
                        $this->_getLines($to_lines, $to_line_no, $match5_int)
                    );
                    $from_line_no++;
                    break;
            }
        }

        // After the loop, if there are still lines left in either array,
        // it means they are copied lines at the end of the file.
        if (!empty($from_lines) || !empty($to_lines)) {
            $edits[] = new Text_Diff_Op_copy(
                $this->_getLines($from_lines, $from_line_no, $from_line_no + count($from_lines) - 1),
                $this->_getLines($to_lines, $to_line_no, $to_line_no + count($to_lines) - 1)
            );
        }

        return $edits;
    }

    /**
     * Get lines from either the old or new text.
     *
     * @param array<string> &$text_lines Either $from_lines or $to_lines.
     * @param int           &$line_no    Current line number.
     * @param int|false     $end         Optional end line, when we want to chop more
     *                                   than one line.
     *
     * @return array<string> The chopped lines.
     */
    private function _getLines(array &$text_lines, int &$line_no, int|false $end = false): array
    {
        $lines = [];
        if ($end !== false) { // Check for explicit false, as 0 is a valid int.
            // We can shift even more
            // Note: $end is 1-based, $line_no is 1-based.
            while ($line_no <= $end && !empty($text_lines)) {
                $lines[] = array_shift($text_lines);
                $line_no++;
            }
        } elseif (!empty($text_lines)) { // If $end is false, just get one line if available
            $lines[] = array_shift($text_lines);
            $line_no++;
        }

        return $lines;
    }
}
<?php

declare(strict_types=1);

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
     */
    private string $_diffCommand = 'diff';

    /**
     * Returns the array of differences.
     *
     * @param array<string> $from_lines lines of text from old file
     * @param array<string> $to_lines   lines of text from new file
     *
     * @return array<int, \Text_Diff_Op_copy|\Text_Diff_Op_delete|\Text_Diff_Op_change|\Text_Diff_Op_add> all changes made
     */
    public function diff(array $from_lines, array $to_lines): array
    {
        array_walk($from_lines, [Text_Diff::class, 'trimNewlines']);
        array_walk($to_lines, [Text_Diff::class, 'trimNewlines']);

        $temp_dir = Text_Diff::_getTempDir();

        $from_file = tempnam($temp_dir, 'Text_Diff');
        $to_file = tempnam($temp_dir, 'Text_Diff');

        if ($from_file === false || $to_file === false) {
            // Fallback to treating the content as unchanged on temp file creation failure.
            return [new Text_Diff_Op_copy($from_lines)];
        }

        try {
            file_put_contents($from_file, implode("\n", $from_lines));
            file_put_contents($to_file, implode("\n", $to_lines));

            $command = sprintf(
                '%s %s %s',
                $this->_diffCommand,
                escapeshellarg($from_file),
                escapeshellarg($to_file)
            );
            $diff = shell_exec($command);
        } finally {
            unlink($from_file);
            unlink($to_file);
        }

        // shell_exec returns null if an error occurs executing the command.
        if ($diff === null) {
            // Fallback to treating the content as unchanged.
            return [new Text_Diff_Op_copy($from_lines)];
        }

        $from_line_no = 1;
        $to_line_no = 1;
        $edits = [];

        // Get changed lines by parsing something like:
        // 0a1,2
        // 1,2c4,6
        // 1,5d6
        preg_match_all(
            '#^(\d+)(?:,(\d+))?([adc])(\d+)(?:,(\d+))?$#m',
            $diff,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            // This paren is not set every time (see regex).
            $match[5] ??= false;

            if ($match[3] === 'a') {
                $from_line_no--;
            }

            if ($match[3] === 'd') {
                $to_line_no--;
            }

            $m1 = (int)$match[1];
            $m4 = (int)$match[4];

            if ($from_line_no < $m1 || $to_line_no < $m4) {
                // copied lines
                assert($m1 - $from_line_no === $m4 - $to_line_no);
                $edits[] = new Text_Diff_Op_copy(
                    $this->_getLines($from_lines, $from_line_no, $m1 - 1),
                    $this->_getLines($to_lines, $to_line_no, $m4 - 1)
                );
            }

            $m2 = isset($match[2]) && $match[2] !== '' ? (int)$match[2] : false;
            $m5 = $match[5] === false ? false : (int)$match[5];

            switch ($match[3]) {
                case 'd':
                    // deleted lines
                    $edits[] = new Text_Diff_Op_delete(
                        $this->_getLines($from_lines, $from_line_no, $m2)
                    );
                    $to_line_no++;
                    break;

                case 'c':
                    // changed lines
                    $edits[] = new Text_Diff_Op_change(
                        $this->_getLines($from_lines, $from_line_no, $m2),
                        $this->_getLines($to_lines, $to_line_no, $m5)
                    );
                    break;

                case 'a':
                    // added lines
                    $edits[] = new Text_Diff_Op_add(
                        $this->_getLines($to_lines, $to_line_no, $m5)
                    );
                    $from_line_no++;
                    break;
            }
        }

        if (!empty($from_lines)) {
            // Some lines might still be pending. Add them as copied.
            $edits[] = new Text_Diff_Op_copy(
                $this->_getLines(
                    $from_lines,
                    $from_line_no,
                    $from_line_no + count($from_lines) - 1
                ),
                $this->_getLines(
                    $to_lines,
                    $to_line_no,
                    $to_line_no + count($to_lines) - 1
                )
            );
        }

        return $edits;
    }

    /**
     * Get lines from either the old or new text.
     *
     * @param array<string> $text_lines Either $from_lines or $to_lines.
     * @param int           $line_no    Current line number, passed by reference.
     * @param int|false     $end        Optional end line, when we want to chop more
     *                                  than one line.
     *
     * @return array<string> The chopped lines.
     */
    private function _getLines(array &$text_lines, int &$line_no, int|false $end = false): array
    {
        if ($end !== false) {
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
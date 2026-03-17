<?php
/**
 * Parses unified or context diffs output from eg. the diff utility.
 *
 * Example:
 * <code>
 * // Assuming Text\Diff\Diff and Text\Diff\Renderer\InlineRenderer exist
 * $patch = file_get_contents('example.patch');
 * $diff = new Text\Diff\Diff('string', [$patch]);
 * $renderer = new Text\Diff\Renderer\InlineRenderer();
 * echo $renderer->render($diff);
 * </code>
 *
 * Copyright 2005 rjan Persson <o@42mm.org>
 * Copyright 2005-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 */

namespace Text\Diff\Engine;

use InvalidArgumentException;
use Text\Diff\Op\Add;
use Text\Diff\Op\Change;
use Text\Diff\Op\Copy;
use Text\Diff\Op\Delete;

class StringEngine
{
    /**
     * Parses a unified or context diff.
     *
     * First param contains the whole diff and the second can be used to force
     * a specific diff type. If the second parameter is 'autodetect', the
     * diff will be examined to find out which type of diff this is.
     *
     * @param string $diff The diff content.
     * @param string $mode The diff mode of the content in $diff. One of
     *                     'context', 'unified', or 'autodetect'.
     *
     * @return array<int, Add|Change|Copy|Delete> List of all diff operations.
     * @throws InvalidArgumentException If the diff type is unsupported or could not be detected.
     */
    public function diff(string $diff, string $mode = 'autodetect'): array
    {
        // Detect line breaks.
        $lnbr = "\n";
        if (str_contains($diff, "\r\n")) {
            $lnbr = "\r\n";
        } elseif (str_contains($diff, "\r")) {
            $lnbr = "\r";
        }

        // Make sure we have a line break at the EOF.
        if (!str_ends_with($diff, $lnbr)) {
            $diff .= $lnbr;
        }

        if ($mode !== 'autodetect' && $mode !== 'context' && $mode !== 'unified') {
            throw new InvalidArgumentException('Type of diff is unsupported');
        }

        if ($mode === 'autodetect') {
            $context = strpos($diff, '***');
            $unified = strpos($diff, '---');
            if ($context === $unified) {
                throw new InvalidArgumentException('Type of diff could not be detected');
            } elseif ($context === false || $unified === false) {
                $mode = $context !== false ? 'context' : 'unified';
            } else {
                $mode = $context < $unified ? 'context' : 'unified';
            }
        }

        // Split by new line and remove the diff header, if there is one.
        $diffLines = explode($lnbr, $diff);
        if (($mode === 'context' && str_starts_with($diffLines[0] ?? '', '***')) ||
            ($mode === 'unified' && str_starts_with($diffLines[0] ?? '', '---'))) {
            array_shift($diffLines);
            array_shift($diffLines);
        }

        if ($mode === 'context') {
            return $this->parseContextDiff($diffLines);
        } else {
            return $this->parseUnifiedDiff($diffLines);
        }
    }

    /**
     * Parses an array containing the unified diff.
     *
     * @param array<int, string> $diff Array of lines.
     *
     * @return array<int, Add|Change|Copy|Delete> List of all diff operations.
     */
    public function parseUnifiedDiff(array $diff): array
    {
        $edits = [];
        $end = count($diff);
        for ($i = 0; $i < $end;) {
            $diff1 = [];
            $firstChar = $diff[$i][0] ?? ''; // Use null coalescing for safety if line is empty
            switch ($firstChar) {
                case ' ':
                    do {
                        $diff1[] = substr($diff[$i], 1);
                    } while (++$i < $end && ($diff[$i][0] ?? '') === ' ');
                    $edits[] = new Copy($diff1);
                    break;

                case '+':
                    // get all new lines
                    do {
                        $diff1[] = substr($diff[$i], 1);
                    } while (++$i < $end && ($diff[$i][0] ?? '') === '+');
                    $edits[] = new Add($diff1);
                    break;

                case '-':
                    // get changed or removed lines
                    $diff2 = [];
                    do {
                        $diff1[] = substr($diff[$i], 1);
                    } while (++$i < $end && ($diff[$i][0] ?? '') === '-');

                    while ($i < $end && ($diff[$i][0] ?? '') === '+') {
                        $diff2[] = substr($diff[$i++], 1);
                    }
                    if (count($diff2) === 0) {
                        $edits[] = new Delete($diff1);
                    } else {
                        $edits[] = new Change($diff1, $diff2);
                    }
                    break;

                default:
                    $i++; // Skip unknown lines
                    break;
            }
        }

        return $edits;
    }

    /**
     * Parses an array containing the context diff.
     *
     * @param array<int, string> $diff Array of lines.
     *
     * @return array<int, Add|Change|Copy|Delete> List of all diff operations.
     */
    public function parseContextDiff(array $diff): array
    {
        $edits = [];
        $i = $max_i = $j = $max_j = 0;
        $end = count($diff);
        while ($i < $end && $j < $end) {
            while ($i >= $max_i && $j >= $max_j) {
                // Find the boundaries of the diff output of the two files
                for ($i = $j;
                     $i < $end && str_starts_with($diff[$i] ?? '', '***');
                     $i++);
                for ($max_i = $i;
                     $max_i < $end && !str_starts_with($diff[$max_i] ?? '', '---');
                     $max_i++);
                for ($j = $max_i;
                     $j < $end && str_starts_with($diff[$j] ?? '', '---');
                     $j++);
                for ($max_j = $j;
                     $max_j < $end && !str_starts_with($diff[$max_j] ?? '', '***');
                     $max_j++);
            }

            // find what hasn't been changed
            $array = [];
            while ($i < $max_i &&
                   $j < $max_j &&
                   ($diff[$i] ?? '') === ($diff[$j] ?? '')) { // Use === for strict comparison
                $array[] = substr($diff[$i], 2);
                $i++;
                $j++;
            }

            while ($i < $max_i && ($max_j - $j) <= 1) {
                $lineI = $diff[$i] ?? '';
                $firstCharI = $lineI[0] ?? '';
                if ($lineI !== '' && $firstCharI !== ' ') {
                    break;
                }
                $array[] = substr($diff[$i++], 2);
            }

            while ($j < $max_j && ($max_i - $i) <= 1) {
                $lineJ = $diff[$j] ?? '';
                $firstCharJ = $lineJ[0] ?? '';
                if ($lineJ !== '' && $firstCharJ !== ' ') {
                    break;
                }
                $array[] = substr($diff[$j++], 2);
            }
            if (count($array) > 0) {
                $edits[] = new Copy($array);
            }

            if ($i < $max_i) {
                $diff1 = [];
                $firstCharI = $diff[$i][0] ?? '';
                switch ($firstCharI) {
                    case '!':
                        $diff2 = [];
                        do {
                            $diff1[] = substr($diff[$i], 2);
                            if ($j < $max_j && ($diff[$j][0] ?? '') === '!') {
                                $diff2[] = substr($diff[$j++], 2);
                            }
                        } while (++$i < $max_i && ($diff[$i][0] ?? '') === '!');
                        $edits[] = new Change($diff1, $diff2);
                        break;

                    case '+':
                        do {
                            $diff1[] = substr($diff[$i], 2);
                        } while (++$i < $max_i && ($diff[$i][0] ?? '') === '+');
                        $edits[] = new Add($diff1);
                        break;

                    case '-':
                        do {
                            $diff1[] = substr($diff[$i], 2);
                        } while (++$i < $max_i && ($diff[$i][0] ?? '') === '-');
                        $edits[] = new Delete($diff1);
                        break;
                }
            }

            if ($j < $max_j) {
                $diff2 = [];
                $firstCharJ = $diff[$j][0] ?? '';
                switch ($firstCharJ) {
                    case '+':
                        do {
                            $diff2[] = substr($diff[$j++], 2);
                        } while ($j < $max_j && ($diff[$j][0] ?? '') === '+');
                        $edits[] = new Add($diff2);
                        break;

                    case '-':
                        do {
                            $diff2[] = substr($diff[$j++], 2);
                        } while ($j < $max_j && ($diff[$j][0] ?? '') === '-');
                        $edits[] = new Delete($diff2);
                        break;
                }
            }
        }

        return $edits;
    }
}
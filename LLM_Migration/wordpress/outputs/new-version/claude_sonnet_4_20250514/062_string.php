<?php

declare(strict_types=1);

/**
 * Parses unified or context diffs output from eg. the diff utility.
 *
 * Example:
 * <code>
 * $patch = file_get_contents('example.patch');
 * $diff = new Text_Diff('string', array($patch));
 * $renderer = new Text_Diff_Renderer_inline();
 * echo $renderer->render($diff);
 * </code>
 *
 * Copyright 2005 rjan Persson <o@42mm.org>
 * Copyright 2005-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @author  rjan Persson <o@42mm.org>
 * @package Text_Diff
 * @since   0.2.0
 */
class Text_Diff_Engine_string
{
    /**
     * Parses a unified or context diff.
     *
     * First param contains the whole diff and the second can be used to force
     * a specific diff type. If the second parameter is 'autodetect', the
     * diff will be examined to find out which type of diff this is.
     *
     * @param string $diff  The diff content.
     * @param string $mode  The diff mode of the content in $diff. One of
     *                      'context', 'unified', or 'autodetect'.
     *
     * @return array  List of all diff operations.
     * @throws InvalidArgumentException When diff type is unsupported or cannot be detected.
     */
    public function diff(string $diff, string $mode = 'autodetect'): array
    {
        // Detect line breaks.
        $lnbr = match (true) {
            str_contains($diff, "\r\n") => "\r\n",
            str_contains($diff, "\r") => "\r",
            default => "\n"
        };

        // Make sure we have a line break at the EOF.
        if (!str_ends_with($diff, $lnbr)) {
            $diff .= $lnbr;
        }

        if (!in_array($mode, ['autodetect', 'context', 'unified'], true)) {
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
        if (($mode === 'context' && str_starts_with($diffLines[0], '***')) ||
            ($mode === 'unified' && str_starts_with($diffLines[0], '---'))) {
            array_shift($diffLines);
            array_shift($diffLines);
        }

        return match ($mode) {
            'context' => $this->parseContextDiff($diffLines),
            default => $this->parseUnifiedDiff($diffLines)
        };
    }

    /**
     * Parses an array containing the unified diff.
     *
     * @param array $diff  Array of lines.
     *
     * @return array  List of all diff operations.
     */
    public function parseUnifiedDiff(array $diff): array
    {
        $edits = [];
        $end = count($diff) - 1;
        for ($i = 0; $i < $end;) {
            $diff1 = [];
            switch ($diff[$i][0] ?? '') {
                case ' ':
                    do {
                        $diff1[] = substr($diff[$i], 1);
                    } while (++$i < $end && ($diff[$i][0] ?? '') === ' ');
                    $edits[] = new Text_Diff_Op_copy($diff1);
                    break;

                case '+':
                    // get all new lines
                    do {
                        $diff1[] = substr($diff[$i], 1);
                    } while (++$i < $end && ($diff[$i][0] ?? '') === '+');
                    $edits[] = new Text_Diff_Op_add($diff1);
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
                        $edits[] = new Text_Diff_Op_delete($diff1);
                    } else {
                        $edits[] = new Text_Diff_Op_change($diff1, $diff2);
                    }
                    break;

                default:
                    $i++;
                    break;
            }
        }

        return $edits;
    }

    /**
     * Parses an array containing the context diff.
     *
     * @param array $diff  Array of lines.
     *
     * @return array  List of all diff operations.
     */
    public function parseContextDiff(array &$diff): array
    {
        $edits = [];
        $i = $max_i = $j = $max_j = 0;
        $end = count($diff) - 1;
        while ($i < $end && $j < $end) {
            while ($i >= $max_i && $j >= $max_j) {
                // Find the boundaries of the diff output of the two files
                for ($i = $j;
                     $i < $end && str_starts_with($diff[$i], '***');
                     $i++);
                for ($max_i = $i;
                     $max_i < $end && !str_starts_with($diff[$max_i], '---');
                     $max_i++);
                for ($j = $max_i;
                     $j < $end && str_starts_with($diff[$j], '---');
                     $j++);
                for ($max_j = $j;
                     $max_j < $end && !str_starts_with($diff[$max_j], '***');
                     $max_j++);
            }

            // find what hasn't been changed
            $array = [];
            while ($i < $max_i &&
                   $j < $max_j &&
                   strcmp($diff[$i], $diff[$j]) === 0) {
                $array[] = substr($diff[$i], 2);
                $i++;
                $j++;
            }

            while ($i < $max_i && ($max_j - $j) <= 1) {
                if ($diff[$i] !== '' && ($diff[$i][0] ?? '') !== ' ') {
                    break;
                }
                $array[] = substr($diff[$i++], 2);
            }

            while ($j < $max_j && ($max_i - $i) <= 1) {
                if ($diff[$j] !== '' && ($diff[$j][0] ?? '') !== ' ') {
                    break;
                }
                $array[] = substr($diff[$j++], 2);
            }
            if (count($array) > 0) {
                $edits[] = new Text_Diff_Op_copy($array);
            }

            if ($i < $max_i) {
                $diff1 = [];
                switch ($diff[$i][0] ?? '') {
                    case '!':
                        $diff2 = [];
                        do {
                            $diff1[] = substr($diff[$i], 2);
                            if ($j < $max_j && ($diff[$j][0] ?? '') === '!') {
                                $diff2[] = substr($diff[$j++], 2);
                            }
                        } while (++$i < $max_i && ($diff[$i][0] ?? '') === '!');
                        $edits[] = new Text_Diff_Op_change($diff1, $diff2);
                        break;

                    case '+':
                        do {
                            $diff1[] = substr($diff[$i], 2);
                        } while (++$i < $max_i && ($diff[$i][0] ?? '') === '+');
                        $edits[] = new Text_Diff_Op_add($diff1);
                        break;

                    case '-':
                        do {
                            $diff1[] = substr($diff[$i], 2);
                        } while (++$i < $max_i && ($diff[$i][0] ?? '') === '-');
                        $edits[] = new Text_Diff_Op_delete($diff1);
                        break;
                }
            }

            if ($j < $max_j) {
                $diff2 = [];
                switch ($diff[$j][0] ?? '') {
                    case '+':
                        do {
                            $diff2[] = substr($diff[$j++], 2);
                        } while ($j < $max_j && ($diff[$j][0] ?? '') === '+');
                        $edits[] = new Text_Diff_Op_add($diff2);
                        break;

                    case '-':
                        do {
                            $diff2[] = substr($diff[$j++], 2);
                        } while ($j < $max_j && ($diff[$j][0] ?? '') === '-');
                        $edits[] = new Text_Diff_Op_delete($diff2);
                        break;
                }
            }
        }

        return $edits;
    }
}
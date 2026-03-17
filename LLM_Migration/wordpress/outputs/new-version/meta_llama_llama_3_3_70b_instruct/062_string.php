<?php

/**
 * Parses unified or context diffs output from eg. the diff utility.
 *
 * Example:
 * <code>
 * $patch = file_get_contents('example.patch');
 * $diff = new TextDiffEngine('string', [$patch]);
 * $renderer = new TextDiffRendererInline();
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
 * @package TextDiff
 * @since   0.2.0
 */
class TextDiffEngine
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
     */
    public function diff(string $diff, string $mode = 'autodetect'): array
    {
        // Detect line breaks.
        $lineBreak = "\n";
        if (strpos($diff, "\r\n") !== false) {
            $lineBreak = "\r\n";
        } elseif (strpos($diff, "\r") !== false) {
            $lineBreak = "\r";
        }

        // Make sure we have a line break at the EOF.
        if (substr($diff, -strlen($lineBreak)) !== $lineBreak) {
            $diff .= $lineBreak;
        }

        if (!in_array($mode, ['autodetect', 'context', 'unified'])) {
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
        $diffLines = explode($lineBreak, $diff);
        if (($mode === 'context' && strpos($diffLines[0], '***') === 0) ||
            ($mode === 'unified' && strpos($diffLines[0], '---') === 0)) {
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
     * @param array $diffLines  Array of lines.
     *
     * @return array  List of all diff operations.
     */
    private function parseUnifiedDiff(array $diffLines): array
    {
        $edits = [];
        $end = count($diffLines) - 1;
        $i = 0;
        while ($i < $end) {
            $diff1 = [];
            switch (substr($diffLines[$i], 0, 1)) {
                case ' ':
                    do {
                        $diff1[] = substr($diffLines[$i], 1);
                    } while (++$i < $end && substr($diffLines[$i], 0, 1) === ' ');
                    $edits[] = new TextDiffOpCopy($diff1);
                    break;

                case '+':
                    // get all new lines
                    do {
                        $diff1[] = substr($diffLines[$i], 1);
                    } while (++$i < $end && substr($diffLines[$i], 0, 1) === '+');
                    $edits[] = new TextDiffOpAdd($diff1);
                    break;

                case '-':
                    // get changed or removed lines
                    $diff2 = [];
                    do {
                        $diff1[] = substr($diffLines[$i], 1);
                    } while (++$i < $end && substr($diffLines[$i], 0, 1) === '-');

                    while ($i < $end && substr($diffLines[$i], 0, 1) === '+') {
                        $diff2[] = substr($diffLines[$i++], 1);
                    }
                    if (empty($diff2)) {
                        $edits[] = new TextDiffOpDelete($diff1);
                    } else {
                        $edits[] = new TextDiffOpChange($diff1, $diff2);
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
     * @param array $diffLines  Array of lines.
     *
     * @return array  List of all diff operations.
     */
    private function parseContextDiff(array $diffLines): array
    {
        $edits = [];
        $i = $maxI = $j = $maxJ = 0;
        $end = count($diffLines) - 1;
        while ($i < $end && $j < $end) {
            while ($i >= $maxI && $j >= $maxJ) {
                // Find the boundaries of the diff output of the two files
                for ($i = $j;
                     $i < $end && substr($diffLines[$i], 0, 3) === '***';
                     $i++);
                for ($maxI = $i;
                     $maxI < $end && substr($diffLines[$maxI], 0, 3) !== '---';
                     $maxI++);
                for ($j = $maxI;
                     $j < $end && substr($diffLines[$j], 0, 3) === '---';
                     $j++);
                for ($maxJ = $j;
                     $maxJ < $end && substr($diffLines[$maxJ], 0, 3) !== '***';
                     $maxJ++);
            }

            // find what hasn't been changed
            $array = [];
            while ($i < $maxI &&
                   $j < $maxJ &&
                   strcmp($diffLines[$i], $diffLines[$j]) === 0) {
                $array[] = substr($diffLines[$i], 2);
                $i++;
                $j++;
            }

            while ($i < $maxI && ($maxJ - $j) <= 1) {
                if ($diffLines[$i] !== '' && substr($diffLines[$i], 0, 1) !== ' ') {
                    break;
                }
                $array[] = substr($diffLines[$i++], 2);
            }

            while ($j < $maxJ && ($maxI - $i) <= 1) {
                if ($diffLines[$j] !== '' && substr($diffLines[$j], 0, 1) !== ' ') {
                    break;
                }
                $array[] = substr($diffLines[$j++], 2);
            }
            if (!empty($array)) {
                $edits[] = new TextDiffOpCopy($array);
            }

            if ($i < $maxI) {
                $diff1 = [];
                switch (substr($diffLines[$i], 0, 1)) {
                    case '!':
                        $diff2 = [];
                        do {
                            $diff1[] = substr($diffLines[$i], 2);
                            if ($j < $maxJ && substr($diffLines[$j], 0, 1) === '!') {
                                $diff2[] = substr($diffLines[$j++], 2);
                            }
                        } while (++$i < $maxI && substr($diffLines[$i], 0, 1) === '!');
                        $edits[] = new TextDiffOpChange($diff1, $diff2);
                        break;

                    case '+':
                        do {
                            $diff1[] = substr($diffLines[$i], 2);
                        } while (++$i < $maxI && substr($diffLines[$i], 0, 1) === '+');
                        $edits[] = new TextDiffOpAdd($diff1);
                        break;

                    case '-':
                        do {
                            $diff1[] = substr($diffLines[$i], 2);
                        } while (++$i < $maxI && substr($diffLines[$i], 0, 1) === '-');
                        $edits[] = new TextDiffOpDelete($diff1);
                        break;
                }
            }

            if ($j < $maxJ) {
                $diff2 = [];
                switch (substr($diffLines[$j], 0, 1)) {
                    case '+':
                        do {
                            $diff2[] = substr($diffLines[$j++], 2);
                        } while ($j < $maxJ && substr($diffLines[$j], 0, 1) === '+');
                        $edits[] = new TextDiffOpAdd($diff2);
                        break;

                    case '-':
                        do {
                            $diff2[] = substr($diffLines[$j++], 2);
                        } while ($j < $maxJ && substr($diffLines[$j], 0, 1) === '-');
                        $edits[] = new TextDiffOpDelete($diff2);
                        break;
                }
            }
        }

        return $edits;
    }
}

// Assuming the following classes exist:
class TextDiffOpCopy
{
    public function __construct(array $lines)
    {
    }
}

class TextDiffOpAdd
{
    public function __construct(array $lines)
    {
    }
}

class TextDiffOpDelete
{
    public function __construct(array $lines)
    {
    }
}

class TextDiffOpChange
{
    public function __construct(array $lines1, array $lines2)
    {
    }
}

class TextDiffRendererInline
{
    public function render($diff)
    {
    }
}
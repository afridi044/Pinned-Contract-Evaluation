<?php

declare(strict_types=1);

/**
 * Parses unified or context diffs output from eg. the diff utility.
 *
 * Example:
 * <code>
 * // Assuming Text_Diff, Text_Diff_Renderer_inline, and the Text_Diff_Op_*
 * // classes are available and autoloadable.
 * $patch = file_get_contents('example.patch');
 * $diff = new Text_Diff('string', [$patch]);
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
     * @param string $diff The diff content.
     * @param string $mode The diff mode of the content in $diff. One of
     *                     'context', 'unified', or 'autodetect'.
     *
     * @return array<int, \Text_Diff_Op_copy|\Text_Diff_Op_add|\Text_Diff_Op_delete|\Text_Diff_Op_change> List of all diff operations.
     *
     * @throws \InvalidArgumentException If the diff type is unsupported.
     * @throws \RuntimeException If the diff type cannot be detected.
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

        if (!in_array($mode, ['autodetect', 'context', 'unified'], true)) {
            throw new \InvalidArgumentException('Type of diff is unsupported. Must be one of: autodetect, context, unified.');
        }

        if ($mode === 'autodetect') {
            $contextPos = strpos($diff, '***');
            $unifiedPos = strpos($diff, '---');

            if ($contextPos === $unifiedPos) { // Both found at same pos (e.g. 0) or both not found (false)
                throw new \RuntimeException('Type of diff could not be detected.');
            }

            if ($contextPos === false) {
                $mode = 'unified';
            } elseif ($unifiedPos === false) {
                $mode = 'context';
            } else {
                $mode = $contextPos < $unifiedPos ? 'context' : 'unified';
            }
        }

        // Split by new line and remove the diff header, if there is one.
        $diffLines = explode($lnbr, $diff);
        if (
            ($mode === 'context' && str_starts_with($diffLines[0] ?? '', '***')) ||
            ($mode === 'unified' && str_starts_with($diffLines[0] ?? '', '---'))
        ) {
            array_shift($diffLines);
            array_shift($diffLines);
        }

        return match ($mode) {
            'context' => $this->parseContextDiff($diffLines),
            'unified' => $this->parseUnifiedDiff($diffLines),
        };
    }

    /**
     * Parses an array containing the unified diff.
     *
     * @param string[] $diff Array of lines.
     *
     * @return array<int, \Text_Diff_Op_copy|\Text_Diff_Op_add|\Text_Diff_Op_delete|\Text_Diff_Op_change> List of all diff operations.
     */
    public function parseUnifiedDiff(array $diff): array
    {
        $edits = [];
        $end = count($diff) - 1;
        for ($i = 0; $i < $end;) {
            $firstChar = $diff[$i][0] ?? null;

            switch ($firstChar) {
                case ' ':
                    $lines = [];
                    do {
                        $lines[] = substr($diff[$i], 1);
                    } while (++$i < $end && str_starts_with($diff[$i], ' '));
                    $edits[] = new \Text_Diff_Op_copy($lines);
                    break;

                case '+':
                    $lines = [];
                    do {
                        $lines[] = substr($diff[$i], 1);
                    } while (++$i < $end && str_starts_with($diff[$i], '+'));
                    $edits[] = new \Text_Diff_Op_add($lines);
                    break;

                case '-':
                    $deletedLines = [];
                    do {
                        $deletedLines[] = substr($diff[$i], 1);
                    } while (++$i < $end && str_starts_with($diff[$i], '-'));

                    $addedLines = [];
                    while ($i < $end && str_starts_with($diff[$i], '+')) {
                        $addedLines[] = substr($diff[$i++], 1);
                    }

                    if (empty($addedLines)) {
                        $edits[] = new \Text_Diff_Op_delete($deletedLines);
                    } else {
                        $edits[] = new \Text_Diff_Op_change($deletedLines, $addedLines);
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
     * @param string[] $diff Array of lines.
     *
     * @return array<int, \Text_Diff_Op_copy|\Text_Diff_Op_add|\Text_Diff_Op_delete|\Text_Diff_Op_change> List of all diff operations.
     */
    public function parseContextDiff(array &$diff): array
    {
        $edits = [];
        $i = $max_i = $j = $max_j = 0;
        $end = count($diff) - 1;

        while ($i < $end && $j < $end) {
            while ($i >= $max_i && $j >= $max_j) {
                // Find the boundaries of the diff output of the two files
                for ($i = $j; $i < $end && str_starts_with($diff[$i], '***'); $i++);
                for ($max_i = $i; $max_i < $end && !str_starts_with($diff[$max_i], '---'); $max_i++);
                for ($j = $max_i; $j < $end && str_starts_with($diff[$j], '---'); $j++);
                for ($max_j = $j; $max_j < $end && !str_starts_with($diff[$max_j], '***'); $max_j++);
            }

            // find what hasn't been changed
            $copiedLines = [];
            while ($i < $max_i && $j < $max_j && $diff[$i] === $diff[$j]) {
                $copiedLines[] = substr($diff[$i], 2);
                $i++;
                $j++;
            }

            while ($i < $max_i && ($max_j - $j) <= 1) {
                if ($diff[$i] !== '' && ($diff[$i][0] ?? '') !== ' ') {
                    break;
                }
                $copiedLines[] = substr($diff[$i++], 2);
            }

            while ($j < $max_j && ($max_i - $i) <= 1) {
                if ($diff[$j] !== '' && ($diff[$j][0] ?? '') !== ' ') {
                    break;
                }
                $copiedLines[] = substr($diff[$j++], 2);
            }

            if (!empty($copiedLines)) {
                $edits[] = new \Text_Diff_Op_copy($copiedLines);
            }

            if ($i < $max_i) {
                $firstChar = $diff[$i][0] ?? null;
                switch ($firstChar) {
                    case '!':
                        $deletedLines = [];
                        $addedLines = [];
                        do {
                            $deletedLines[] = substr($diff[$i], 2);
                            if ($j < $max_j && str_starts_with($diff[$j], '!')) {
                                $addedLines[] = substr($diff[$j++], 2);
                            }
                        } while (++$i < $max_i && str_starts_with($diff[$i], '!'));
                        $edits[] = new \Text_Diff_Op_change($deletedLines, $addedLines);
                        break;

                    case '+':
                        $lines = [];
                        do {
                            $lines[] = substr($diff[$i], 2);
                        } while (++$i < $max_i && str_starts_with($diff[$i], '+'));
                        $edits[] = new \Text_Diff_Op_add($lines);
                        break;

                    case '-':
                        $lines = [];
                        do {
                            $lines[] = substr($diff[$i], 2);
                        } while (++$i < $max_i && str_starts_with($diff[$i], '-'));
                        $edits[] = new \Text_Diff_Op_delete($lines);
                        break;
                }
            }

            if ($j < $max_j) {
                $firstChar = $diff[$j][0] ?? null;
                switch ($firstChar) {
                    case '+':
                        $lines = [];
                        do {
                            $lines[] = substr($diff[$j++], 2);
                        } while ($j < $max_j && str_starts_with($diff[$j], '+'));
                        $edits[] = new \Text_Diff_Op_add($lines);
                        break;

                    case '-':
                        $lines = [];
                        do {
                            $lines[] = substr($diff[$j++], 2);
                        } while ($j < $max_j && str_starts_with($diff[$j], '-'));
                        $edits[] = new \Text_Diff_Op_delete($lines);
                        break;
                }
            }
        }

        return $edits;
    }
}
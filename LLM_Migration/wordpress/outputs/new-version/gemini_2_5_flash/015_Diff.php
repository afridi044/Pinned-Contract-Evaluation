<?php
/**
 * General API for generating and formatting diffs - the differences between
 * two sequences of strings.
 *
 * The original PHP version of this code was written by Geoffrey T. Dairiki
 * <dairiki@dairiki.org>, and is used/adapted with his permission.
 *
 * Copyright 2004 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * Copyright 2004-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Text_Diff
{
    /**
     * Array of changes.
     *
     * @var array<int, Text_Diff_Op>
     */
    private array $_edits;

    /**
     * Computes diffs between sequences of strings.
     *
     * @param string|array $engine Name of the diffing engine to use. 'auto'
     *                             will automatically select the best. Or, if
     *                             an array, it's treated as the first parameter
     *                             to the engine's diff method, with $params
     *                             being the second.
     * @param array        $params Parameters to pass to the diffing engine.
     *                             Normally an array of two arrays, each
     *                             containing the lines from a file.
     */
    public function __construct(string|array $engine, array $params = [])
    {
        // Backward compatibility workaround.
        if (!is_string($engine)) {
            $params = [$engine, $params]; // $engine becomes first param, $params becomes second
            $engine = 'auto';
        }

        if ($engine === 'auto') {
            $engine = extension_loaded('xdiff') ? 'xdiff' : 'native';
        } else {
            $engine = basename($engine);
        }

        // WP #7391
        require_once __DIR__ . '/Diff/Engine/' . $engine . '.php';
        $class = 'Text_Diff_Engine_' . $engine;
        $diff_engine = new $class();

        $this->_edits = [$diff_engine, 'diff'](...$params);
    }

    /**
     * Returns the array of differences.
     *
     * @return array<int, Text_Diff_Op>
     */
    public function getDiff(): array
    {
        return $this->_edits;
    }

    /**
     * Returns the number of new (added) lines in a given diff.
     *
     * @since Text_Diff 1.1.0
     *
     * @return int The number of new lines
     */
    public function countAddedLines(): int
    {
        $count = 0;
        foreach ($this->_edits as $edit) {
            if ($edit instanceof Text_Diff_Op_add ||
                $edit instanceof Text_Diff_Op_change) {
                $count += $edit->nfinal();
            }
        }
        return $count;
    }

    /**
     * Returns the number of deleted (removed) lines in a given diff.
     *
     * @since Text_Diff 1.1.0
     *
     * @return int The number of deleted lines
     */
    public function countDeletedLines(): int
    {
        $count = 0;
        foreach ($this->_edits as $edit) {
            if ($edit instanceof Text_Diff_Op_delete ||
                $edit instanceof Text_Diff_Op_change) {
                $count += $edit->norig();
            }
        }
        return $count;
    }

    /**
     * Computes a reversed diff.
     *
     * Example:
     * <code>
     * $diff = new Text_Diff($lines1, $lines2);
     * $rev = $diff->reverse();
     * </code>
     *
     * @return Text_Diff A Diff object representing the inverse of the
     *                   original diff. Note that we purposely don't return a
     *                   reference here, since this essentially is a clone()
     *                   method.
     */
    public function reverse(): Text_Diff
    {
        $rev = clone $this;
        $rev->_edits = [];
        foreach ($this->_edits as $edit) {
            $rev->_edits[] = $edit->reverse();
        }
        return $rev;
    }

    /**
     * Checks for an empty diff.
     *
     * @return bool True if two sequences were identical.
     */
    public function isEmpty(): bool
    {
        foreach ($this->_edits as $edit) {
            if (!$edit instanceof Text_Diff_Op_copy) {
                return false;
            }
        }
        return true;
    }

    /**
     * Computes the length of the Longest Common Subsequence (LCS).
     *
     * This is mostly for diagnostic purposes.
     *
     * @return int The length of the LCS.
     */
    public function lcs(): int
    {
        $lcs = 0;
        foreach ($this->_edits as $edit) {
            if ($edit instanceof Text_Diff_Op_copy) {
                $lcs += count($edit->orig);
            }
        }
        return $lcs;
    }

    /**
     * Gets the original set of lines.
     *
     * This reconstructs the $from_lines parameter passed to the constructor.
     *
     * @return array<int, string> The original sequence of strings.
     */
    public function getOriginal(): array
    {
        $lines = [];
        foreach ($this->_edits as $edit) {
            if ($edit->orig) {
                array_splice($lines, count($lines), 0, $edit->orig);
            }
        }
        return $lines;
    }

    /**
     * Gets the final set of lines.
     *
     * This reconstructs the $to_lines parameter passed to the constructor.
     *
     * @return array<int, string> The sequence of strings.
     */
    public function getFinal(): array
    {
        $lines = [];
        foreach ($this->_edits as $edit) {
            if ($edit->final) {
                array_splice($lines, count($lines), 0, $edit->final);
            }
        }
        return $lines;
    }

    /**
     * Removes trailing newlines from a line of text. This is meant to be used
     * with array_walk().
     *
     * @param string  $line The line to trim.
     * @param int     $key  The index of the line in the array. Not used.
     */
    public static function trimNewlines(string &$line, int $key): void
    {
        $line = str_replace(["\n", "\r"], '', $line);
    }

    /**
     * Determines the location of the system temporary directory.
     *
     * @access protected
     *
     * @return string|false A directory name which can be used for temp files.
     *                      Returns false if one could not be found.
     */
    protected function _getTempDir(): string|false
    {
        $tmp_locations = ['/tmp', '/var/tmp', 'c:\WUTemp', 'c:\temp',
                               'c:\windows\temp', 'c:\winnt\temp'];

        /* Try PHP's upload_tmp_dir directive. */
        $tmp = ini_get('upload_tmp_dir');

        /* Otherwise, try to determine the TMPDIR environment variable. */
        if (!is_string($tmp) || !strlen($tmp)) {
            $tmp = getenv('TMPDIR');
        }

        /* If we still cannot determine a value, then cycle through a list of
         * preset possibilities. */
        while ((!is_string($tmp) || !strlen($tmp)) && count($tmp_locations)) {
            $tmp_check = array_shift($tmp_locations);
            if (@is_dir($tmp_check)) {
                $tmp = $tmp_check;
            }
        }

        /* If it is still empty, we have failed, so return false; otherwise
         * return the directory determined. */
        return (is_string($tmp) && strlen($tmp)) ? $tmp : false;
    }

    /**
     * Checks a diff for validity.
     *
     * This is here only for debugging purposes.
     *
     * @param array<int, string> $from_lines
     * @param array<int, string> $to_lines
     * @return bool
     */
    protected function _check(array $from_lines, array $to_lines): bool
    {
        if (serialize($from_lines) !== serialize($this->getOriginal())) {
            trigger_error("Reconstructed original doesn't match", E_USER_ERROR);
        }
        if (serialize($to_lines) !== serialize($this->getFinal())) {
            trigger_error("Reconstructed final doesn't match", E_USER_ERROR);
        }

        $rev = $this->reverse();
        if (serialize($to_lines) !== serialize($rev->getOriginal())) {
            trigger_error("Reversed original doesn't match", E_USER_ERROR);
        }
        if (serialize($from_lines) !== serialize($rev->getFinal())) {
            trigger_error("Reversed final doesn't match", E_USER_ERROR);
        }

        $prevtype = null;
        foreach ($this->_edits as $edit) {
            if ($prevtype === get_class($edit)) {
                trigger_error("Edit sequence is non-optimal", E_USER_ERROR);
            }
            $prevtype = get_class($edit);
        }

        return true;
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 */
class Text_MappedDiff extends Text_Diff
{
    /**
     * Computes a diff between sequences of strings.
     *
     * This can be used to compute things like case-insensitve diffs, or diffs
     * which ignore changes in white-space.
     *
     * @param array<int, string> $from_lines         An array of strings.
     * @param array<int, string> $to_lines           An array of strings.
     * @param array<int, string> $mapped_from_lines  This array should have the same size
     *                                               number of elements as $from_lines. The
     *                                               elements in $mapped_from_lines and
     *                                               $mapped_to_lines are what is actually
     *                                               compared when computing the diff.
     * @param array<int, string> $mapped_to_lines    This array should have the same number
     *                                               of elements as $to_lines.
     */
    public function __construct(
        array $from_lines,
        array $to_lines,
        array $mapped_from_lines,
        array $mapped_to_lines
    ) {
        assert(count($from_lines) === count($mapped_from_lines));
        assert(count($to_lines) === count($mapped_to_lines));

        parent::__construct($mapped_from_lines, $mapped_to_lines);

        $xi = $yi = 0;
        for ($i = 0; $i < count($this->getDiff()); $i++) {
            $edits = $this->getDiff(); // Get a mutable copy of the array
            $edit = $edits[$i];

            if (is_array($edit->orig)) {
                $edit->orig = array_slice($from_lines, $xi, count($edit->orig));
                $xi += count($edit->orig);
            }

            if (is_array($edit->final)) {
                $edit->final = array_slice($to_lines, $yi, count($edit->final));
                $yi += count($edit->final);
            }
            // Re-assign the modified object back to the array if necessary
            // In PHP 7+, objects are passed by reference, so modifying $edit
            // directly modifies the object in $this->_edits.
            // No explicit re-assignment is needed for object properties.
        }
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
abstract class Text_Diff_Op
{
    /** @var array<int, string>|false */
    public array|false $orig;
    /** @var array<int, string>|false */
    public array|false $final;

    /**
     * @return Text_Diff_Op
     */
    abstract public function reverse(): Text_Diff_Op;

    public function norig(): int
    {
        return $this->orig ? count($this->orig) : 0;
    }

    public function nfinal(): int
    {
        return $this->final ? count($this->final) : 0;
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_copy extends Text_Diff_Op
{
    /**
     * @param array<int, string> $orig
     * @param array<int, string>|false $final
     */
    public function __construct(array $orig, array|false $final = false)
    {
        if (!is_array($final)) {
            $final = $orig;
        }
        $this->orig = $orig;
        $this->final = $final;
    }

    public function reverse(): Text_Diff_Op_copy
    {
        return new Text_Diff_Op_copy($this->final, $this->orig);
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_delete extends Text_Diff_Op
{
    /**
     * @param array<int, string> $lines
     */
    public function __construct(array $lines)
    {
        $this->orig = $lines;
        $this->final = false;
    }

    public function reverse(): Text_Diff_Op_add
    {
        return new Text_Diff_Op_add($this->orig);
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_add extends Text_Diff_Op
{
    /**
     * @param array<int, string> $lines
     */
    public function __construct(array $lines)
    {
        $this->final = $lines;
        $this->orig = false;
    }

    public function reverse(): Text_Diff_Op_delete
    {
        return new Text_Diff_Op_delete($this->final);
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @access private
 */
class Text_Diff_Op_change extends Text_Diff_Op
{
    /**
     * @param array<int, string> $orig
     * @param array<int, string> $final
     */
    public function __construct(array $orig, array $final)
    {
        $this->orig = $orig;
        $this->final = $final;
    }

    public function reverse(): Text_Diff_Op_change
    {
        return new Text_Diff_Op_change($this->final, $this->orig);
    }
}
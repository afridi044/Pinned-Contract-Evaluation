<?php
declare(strict_types=1);

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
     * @var array
     */
    protected array $_edits = [];

    /**
     * Computes diffs between sequences of strings.
     *
     * @param string|array $engine     Name of the diffing engine to use.  'auto'
     *                                 will automatically select the best. May also
     *                                 be the first set of lines for backward compatibility.
     * @param mixed        $params      Parameters to pass to the diffing engine.
     *                                  Normally an array of two arrays, each
     *                                  containing the lines from a file.
     */
    public function __construct(mixed $engine, mixed $params = null)
    {
        // Backward compatibility workaround.
        if (!\is_string($engine)) {
            $params = [$engine, $params];
            $engine = 'auto';
        }

        if ($engine === 'auto') {
            $engine = \extension_loaded('xdiff') ? 'xdiff' : 'native';
        } else {
            $engine = \basename($engine);
        }

        if ($params === null) {
            $params = [];
        }

        if (!\is_array($params)) {
            $params = [$params];
        }

        require_once __DIR__ . '/Diff/Engine/' . $engine . '.php';
        $class = 'Text_Diff_Engine_' . $engine;
        $diffEngine = new $class();

        $result = $diffEngine->diff(...$params);
        $this->_edits = \is_array($result) ? $result : [];
    }

    /**
     * Returns the array of differences.
     */
    public function getDiff(): array
    {
        return $this->_edits;
    }

    /**
     * returns the number of new (added) lines in a given diff.
     *
     * @since Text_Diff 1.1.0
     *
     * @return integer The number of new lines
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
     * @return integer The number of deleted lines
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
     * @return static  A Diff object representing the inverse of the
     *                 original diff.
     */
    public function reverse(): static
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
     * @return boolean  True if two sequences were identical.
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
     * @return integer  The length of the LCS.
     */
    public function lcs(): int
    {
        $lcs = 0;
        foreach ($this->_edits as $edit) {
            if ($edit instanceof Text_Diff_Op_copy && \is_array($edit->orig)) {
                $lcs += \count($edit->orig);
            }
        }
        return $lcs;
    }

    /**
     * Gets the original set of lines.
     *
     * This reconstructs the $from_lines parameter passed to the constructor.
     *
     * @return array  The original sequence of strings.
     */
    public function getOriginal(): array
    {
        $lines = [];
        foreach ($this->_edits as $edit) {
            if (\is_array($edit->orig)) {
                \array_splice($lines, \count($lines), 0, $edit->orig);
            }
        }
        return $lines;
    }

    /**
     * Gets the final set of lines.
     *
     * This reconstructs the $to_lines parameter passed to the constructor.
     *
     * @return array  The sequence of strings.
     */
    public function getFinal(): array
    {
        $lines = [];
        foreach ($this->_edits as $edit) {
            if (\is_array($edit->final)) {
                \array_splice($lines, \count($lines), 0, $edit->final);
            }
        }
        return $lines;
    }

    /**
     * Removes trailing newlines from a line of text. This is meant to be used
     * with array_walk().
     *
     * @param string $line  The line to trim.
     * @param integer $key  The index of the line in the array. Not used.
     */
    public static function trimNewlines(string &$line, int $key): void
    {
        $line = \str_replace(["\n", "\r"], '', $line);
    }

    /**
     * Determines the location of the system temporary directory.
     *
     * @return string|false  A directory name which can be used for temp files.
     *                       Returns false if one could not be found.
     */
    protected function _getTempDir(): string|false
    {
        $tmpLocations = ['/tmp', '/var/tmp', 'c:\WUTemp', 'c:\temp',
                         'c:\windows\temp', 'c:\winnt\temp'];

        $tmp = ini_get('upload_tmp_dir');
        if (!\is_string($tmp) || $tmp === '') {
            $envTmp = getenv('TMPDIR');
            $tmp = \is_string($envTmp) ? $envTmp : '';
        }

        if ($tmp === '') {
            foreach ($tmpLocations as $tmpCheck) {
                if (\is_dir($tmpCheck)) {
                    $tmp = $tmpCheck;
                    break;
                }
            }
        }

        return $tmp !== '' ? $tmp : false;
    }

    /**
     * Checks a diff for validity.
     *
     * This is here only for debugging purposes.
     */
    protected function _check(array $from_lines, array $to_lines): bool
    {
        if ($from_lines !== $this->getOriginal()) {
            trigger_error("Reconstructed original doesn't match", E_USER_ERROR);
        }
        if ($to_lines !== $this->getFinal()) {
            trigger_error("Reconstructed final doesn't match", E_USER_ERROR);
        }

        $rev = $this->reverse();
        if ($to_lines !== $rev->getOriginal()) {
            trigger_error("Reversed original doesn't match", E_USER_ERROR);
        }
        if ($from_lines !== $rev->getFinal()) {
            trigger_error("Reversed final doesn't match", E_USER_ERROR);
        }

        $prevtype = null;
        foreach ($this->_edits as $edit) {
            $currentType = \get_class($edit);
            if ($prevtype === $currentType) {
                trigger_error("Edit sequence is non-optimal", E_USER_ERROR);
            }
            $prevtype = $currentType;
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
     * @param array $from_lines         An array of strings.
     * @param array $to_lines           An array of strings.
     * @param array $mapped_from_lines  This array should have the same size
     *                                  number of elements as $from_lines.  The
     *                                  elements in $mapped_from_lines and
     *                                  $mapped_to_lines are what is actually
     *                                  compared when computing the diff.
     * @param array $mapped_to_lines    This array should have the same number
     *                                  of elements as $to_lines.
     */
    public function __construct(
        array $from_lines,
        array $to_lines,
        array $mapped_from_lines,
        array $mapped_to_lines
    ) {
        \assert(\count($from_lines) === \count($mapped_from_lines));
        \assert(\count($to_lines) === \count($mapped_to_lines));

        parent::__construct($mapped_from_lines, $mapped_to_lines);

        $xi = 0;
        $yi = 0;
        $editCount = \count($this->_edits);
        for ($i = 0; $i < $editCount; $i++) {
            $orig = &$this->_edits[$i]->orig;
            if (\is_array($orig)) {
                $orig = \array_slice($from_lines, $xi, \count($orig));
                $xi += \count($orig);
            }

            $final = &$this->_edits[$i]->final;
            if (\is_array($final)) {
                $final = \array_slice($to_lines, $yi, \count($final));
                $yi += \count($final);
            }
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
    public ?array $orig = null;
    public ?array $final = null;

    abstract public function reverse(): Text_Diff_Op;

    public function norig(): int
    {
        return \is_array($this->orig) ? \count($this->orig) : 0;
    }

    public function nfinal(): int
    {
        return \is_array($this->final) ? \count($this->final) : 0;
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
    public function __construct(array $orig, ?array $final = null)
    {
        $this->orig = $orig;
        $this->final = $final ?? $orig;
    }

    public function reverse(): Text_Diff_Op
    {
        return new Text_Diff_Op_copy($this->final ?? [], $this->orig ?? []);
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
    public function __construct(array $lines)
    {
        $this->orig = $lines;
        $this->final = null;
    }

    public function reverse(): Text_Diff_Op
    {
        return new Text_Diff_Op_add($this->orig ?? []);
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
    public function __construct(array $lines)
    {
        $this->final = $lines;
        $this->orig = null;
    }

    public function reverse(): Text_Diff_Op
    {
        return new Text_Diff_Op_delete($this->final ?? []);
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
    public function __construct(array $orig, array $final)
    {
        $this->orig = $orig;
        $this->final = $final;
    }

    public function reverse(): Text_Diff_Op
    {
        return new Text_Diff_Op_change($this->final ?? [], $this->orig ?? []);
    }
}
?>
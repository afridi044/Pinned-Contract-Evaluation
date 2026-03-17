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
     * @var Text_Diff_Op[]
     */
    protected array $_edits;

    /**
     * Computes diffs between sequences of strings.
     *
     * @param string|string[] $engine     Name of the diffing engine to use ('auto', 'native', 'xdiff')
     *                                    or the first array of strings to compare (for backward compatibility).
     * @param string[] $params            Parameters to pass to the diffing engine.
     *                                    Normally an array of two arrays, each
     *                                    containing the lines from a file.
     *                                    Or the second array of strings to compare (for backward compatibility).
     */
    public function __construct(string|array $engine, array $params = [])
    {
        // Backward compatibility workaround for new Text_Diff($lines1, $lines2)
        if (is_array($engine)) {
            $params = [$engine, $params];
            $engine = 'auto';
        }

        if ($engine === 'auto') {
            $engine = extension_loaded('xdiff') ? 'xdiff' : 'native';
        } else {
            $engine = basename($engine);
        }

        // The diff engine is dynamically included.
        // This is a legacy pattern; in a modern app this would be handled by a PSR-4 autoloader.
        require_once __DIR__ . '/Diff/Engine/' . $engine . '.php';
        $class = 'Text_Diff_Engine_' . $engine;
        $diff_engine = new $class();

        $this->_edits = $diff_engine->diff(...$params);
    }

    /**
     * Returns the array of differences.
     *
     * @return Text_Diff_Op[]
     */
    public function getDiff(): array
    {
        return $this->_edits;
    }

    /**
     * Returns the number of new (added) lines in a given diff.
     *
     * @return int The number of new lines.
     */
    public function countAddedLines(): int
    {
        $count = 0;
        foreach ($this->_edits as $edit) {
            if ($edit instanceof Text_Diff_Op_add || $edit instanceof Text_Diff_Op_change) {
                $count += $edit->nfinal();
            }
        }
        return $count;
    }

    /**
     * Returns the number of deleted (removed) lines in a given diff.
     *
     * @return int The number of deleted lines.
     */
    public function countDeletedLines(): int
    {
        $count = 0;
        foreach ($this->_edits as $edit) {
            if ($edit instanceof Text_Diff_Op_delete || $edit instanceof Text_Diff_Op_change) {
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
     * @return self A Diff object representing the inverse of the original diff.
     */
    public function reverse(): self
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
            if (!($edit instanceof Text_Diff_Op_copy)) {
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
     * @return string[] The original sequence of strings.
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
     * @return string[] The sequence of strings.
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
     * @param string $line The line to trim.
     * @param int $key The index of the line in the array. Not used.
     */
    public static function trimNewlines(string &$line, int $key): void
    {
        $line = str_replace(["\n", "\r"], '', $line);
    }

    /**
     * Determines the location of the system temporary directory.
     *
     * @return string|false A directory name which can be used for temp files, or false on failure.
     */
    protected static function getTempDir(): string|false
    {
        return sys_get_temp_dir() ?: false;
    }

    /**
     * Checks a diff for validity.
     *
     * This is here only for debugging purposes.
     *
     * @param string[] $from_lines
     * @param string[] $to_lines
     */
    public function check(array $from_lines, array $to_lines): bool
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
            if ($prevtype === $edit::class) {
                trigger_error("Edit sequence is non-optimal", E_USER_ERROR);
            }
            $prevtype = $edit::class;
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
     * This can be used to compute things like case-insensitive diffs, or diffs
     * which ignore changes in white-space.
     *
     * @param string[] $from_lines         An array of strings.
     * @param string[] $to_lines           An array of strings.
     * @param string[] $mapped_from_lines  This array should have the same size
     *                                     number of elements as $from_lines.  The
     *                                     elements in $mapped_from_lines and
     *                                     $mapped_to_lines are what is actually
     *                                     compared when computing the diff.
     * @param string[] $mapped_to_lines    This array should have the same number
     *                                     of elements as $to_lines.
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
        foreach ($this->_edits as $edit) {
            if (is_array($edit->orig)) {
                $orig_count = count($edit->orig);
                $edit->orig = array_slice($from_lines, $xi, $orig_count);
                $xi += $orig_count;
            }

            if (is_array($edit->final)) {
                $final_count = count($edit->final);
                $edit->final = array_slice($to_lines, $yi, $final_count);
                $yi += $final_count;
            }
        }
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @internal
 */
abstract class Text_Diff_Op
{
    public array|false $orig;
    public array|false $final;

    abstract public function reverse(): self;

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
 * @internal
 */
class Text_Diff_Op_copy extends Text_Diff_Op
{
    public function __construct(array $orig, array|bool $final = false)
    {
        if ($final === false) {
            $final = $orig;
        }
        $this->orig = $orig;
        $this->final = $final;
    }

    public function reverse(): self
    {
        /** @var array $this->final */
        return new self($this->final, $this->orig);
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @internal
 */
class Text_Diff_Op_delete extends Text_Diff_Op
{
    public function __construct(array $lines)
    {
        $this->orig = $lines;
        $this->final = false;
    }

    public function reverse(): Text_Diff_Op_add
    {
        /** @var array $this->orig */
        return new Text_Diff_Op_add($this->orig);
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @internal
 */
class Text_Diff_Op_add extends Text_Diff_Op
{
    public function __construct(array $lines)
    {
        $this->final = $lines;
        $this->orig = false;
    }

    public function reverse(): Text_Diff_Op_delete
    {
        /** @var array $this->final */
        return new Text_Diff_Op_delete($this->final);
    }
}

/**
 * @package Text_Diff
 * @author  Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * @internal
 */
class Text_Diff_Op_change extends Text_Diff_Op
{
    public function __construct(array $orig, array $final)
    {
        $this->orig = $orig;
        $this->final = $final;
    }

    public function reverse(): self
    {
        return new self($this->final, $this->orig);
    }
}
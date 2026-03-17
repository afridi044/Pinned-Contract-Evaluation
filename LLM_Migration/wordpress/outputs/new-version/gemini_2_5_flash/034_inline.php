<?php

declare(strict_types=1);

namespace TextDiff\Renderer;

use TextDiff\Diff\Diff; // Assuming Text_Diff becomes TextDiff\Diff\Diff

/**
 * "Inline" diff renderer.
 *
 * Copyright 2004-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you did
 * not receive this file, see http://opensource.org/licenses/lgpl-license.php.
 *
 * @author  Ciprian Popovici
 * @package Text_Diff
 */
class InlineRenderer extends Renderer // Assuming Text_Diff_Renderer becomes Renderer in the same namespace
{
    /**
     * Number of leading context "lines" to preserve.
     */
    protected int $_leading_context_lines = 10000;

    /**
     * Number of trailing context "lines" to preserve.
     */
    protected int $_trailing_context_lines = 10000;

    /**
     * Prefix for inserted text.
     */
    protected string $_ins_prefix = '<ins>';

    /**
     * Suffix for inserted text.
     */
    protected string $_ins_suffix = '</ins>';

    /**
     * Prefix for deleted text.
     */
    protected string $_del_prefix = '<del>';

    /**
     * Suffix for deleted text.
     */
    protected string $_del_suffix = '</del>';

    /**
     * Header for each change block.
     */
    protected string $_block_header = '';

    /**
     * Whether to split down to character-level.
     */
    protected bool $_split_characters = false;

    /**
     * What are we currently splitting on? Used to recurse to show word-level
     * or character-level changes.
     */
    protected string $_split_level = 'lines';

    protected function _blockHeader(int $xbeg, int $xlen, int $ybeg, int $ylen): string
    {
        return $this->_block_header;
    }

    protected function _startBlock(string $header): string
    {
        return $header;
    }

    protected function _lines(array $lines, string $prefix = ' ', bool $encode = true): string
    {
        if ($encode) {
            // Using a short closure for array_walk is more modern than array(&$this, '_encode')
            array_walk($lines, fn (&$line) => $this->_encode($line));
        }

        if ($this->_split_level === 'lines') {
            return implode("\n", $lines) . "\n";
        }

        return implode('', $lines);
    }

    protected function _added(array $lines): string
    {
        // Ensure array is not empty before accessing elements
        if (empty($lines)) {
            return '';
        }

        array_walk($lines, fn (&$line) => $this->_encode($line));
        $lines[0] = $this->_ins_prefix . $lines[0];
        $lines[array_key_last($lines)] .= $this->_ins_suffix;
        return $this->_lines($lines, ' ', false);
    }

    protected function _deleted(array $lines, bool $words = false): string
    {
        // Ensure array is not empty before accessing elements
        if (empty($lines)) {
            return '';
        }

        array_walk($lines, fn (&$line) => $this->_encode($line));
        $lines[0] = $this->_del_prefix . $lines[0];
        $lines[array_key_last($lines)] .= $this->_del_suffix;
        return $this->_lines($lines, ' ', false);
    }

    protected function _changed(array $orig, array $final): string
    {
        /* If we've already split on characters, just display. */
        if ($this->_split_level === 'characters') {
            return $this->_deleted($orig)
                . $this->_added($final);
        }

        /* If we've already split on words, just display. */
        if ($this->_split_level === 'words') {
            $prefix = '';
            // Check if first elements exist and start with space, using str_starts_with for PHP 8+
            while (isset($orig[0], $final[0]) &&
                   is_string($orig[0]) && is_string($final[0]) && // Defensive check, though type hints imply string
                   str_starts_with($orig[0], ' ') &&
                   str_starts_with($final[0], ' ')) {
                $prefix .= $orig[0][0]; // Access first character directly
                $orig[0] = substr($orig[0], 1);
                $final[0] = substr($final[0], 1);
            }
            return $prefix . $this->_deleted($orig) . $this->_added($final);
        }

        $text1 = implode("\n", $orig);
        $text2 = implode("\n", $final);

        /* Non-printing newline marker. */
        $nl = "\0";

        if ($this->_split_characters) {
            // Use PREG_SPLIT_NO_EMPTY and 'u' modifier for better character splitting with unicode
            $diff = new Diff('native',
                                  [preg_split('//u', $text1, -1, PREG_SPLIT_NO_EMPTY),
                                   preg_split('//u', $text2, -1, PREG_SPLIT_NO_EMPTY)]);
        } else {
            /* We want to split on word boundaries, but we need to preserve
             * whitespace as well. Therefore we split on words, but include
             * all blocks of whitespace in the wordlist. */
            $diff = new Diff('native',
                                  [$this->_splitOnWords($text1, $nl),
                                   $this->_splitOnWords($text2, $nl)]);
        }

        /* Get the diff in inline format. */
        $renderer = new self(array_merge($this->getParams(),
                                         ['split_level' => $this->_split_characters ? 'characters' : 'words']));

        /* Run the diff and get the output. */
        return str_replace($nl, "\n", $renderer->render($diff)) . "\n";
    }

    protected function _splitOnWords(string $string, string $newlineEscape = "\n"): array
    {
        // Ignore \0; otherwise the while loop will never finish.
        $string = str_replace("\0", '', $string);

        $words = []; // Use short array syntax
        $length = strlen($string);
        $pos = 0;

        while ($pos < $length) {
            // Eat a word with any preceding whitespace.
            $spaces = strspn(substr($string, $pos), " \n");
            $nextpos = strcspn(substr($string, $pos + $spaces), " \n");
            $words[] = str_replace("\n", $newlineEscape, substr($string, $pos, $spaces + $nextpos));
            $pos += $spaces + $nextpos;
        }

        return $words;
    }

    protected function _encode(string &$string): void
    {
        // Use modern htmlspecialchars flags for better security and HTML5 compatibility, specify UTF-8 encoding
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
}
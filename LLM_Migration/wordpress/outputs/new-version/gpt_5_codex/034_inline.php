<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Renderer.php';

/**
 * "Inline" diff renderer.
 *
 * This class renders diffs in the Wiki-style "inline" format.
 *
 * @author  Ciprian Popovici
 * @package Text_Diff
 */
class Text_Diff_Renderer_inline extends Text_Diff_Renderer
{
    public int $_leading_context_lines = 10000;
    public int $_trailing_context_lines = 10000;
    public string $_ins_prefix = '<ins>';
    public string $_ins_suffix = '</ins>';
    public string $_del_prefix = '<del>';
    public string $_del_suffix = '</del>';
    public string $_block_header = '';
    public bool $_split_characters = false;
    public string $_split_level = 'lines';

    protected function _blockHeader($xbeg, $xlen, $ybeg, $ylen)
    {
        return $this->_block_header;
    }

    protected function _startBlock($header)
    {
        return $header;
    }

    protected function _lines($lines, $prefix = ' ', $encode = true)
    {
        if ($encode) {
            array_walk($lines, [$this, '_encode']);
        }

        if ($this->_split_level === 'lines') {
            return implode("\n", $lines) . "\n";
        }

        return implode('', $lines);
    }

    protected function _added($lines)
    {
        array_walk($lines, [$this, '_encode']);

        $firstKey = array_key_first($lines);
        $lastKey = array_key_last($lines);

        if ($firstKey === null || $lastKey === null) {
            return '';
        }

        $lines[$firstKey] = $this->_ins_prefix . $lines[$firstKey];
        $lines[$lastKey] .= $this->_ins_suffix;

        return $this->_lines($lines, ' ', false);
    }

    protected function _deleted($lines, $words = false)
    {
        array_walk($lines, [$this, '_encode']);

        $firstKey = array_key_first($lines);
        $lastKey = array_key_last($lines);

        if ($firstKey === null || $lastKey === null) {
            return '';
        }

        $lines[$firstKey] = $this->_del_prefix . $lines[$firstKey];
        $lines[$lastKey] .= $this->_del_suffix;

        return $this->_lines($lines, ' ', false);
    }

    protected function _changed($orig, $final)
    {
        if ($this->_split_level === 'characters') {
            return $this->_deleted($orig) . $this->_added($final);
        }

        if ($this->_split_level === 'words') {
            $prefix = '';

            while (
                isset($orig[0], $final[0]) &&
                $orig[0] !== false &&
                $final[0] !== false &&
                substr((string) $orig[0], 0, 1) === ' ' &&
                substr((string) $final[0], 0, 1) === ' '
            ) {
                $prefix .= substr((string) $orig[0], 0, 1);
                $orig[0] = substr((string) $orig[0], 1);
                $final[0] = substr((string) $final[0], 1);
            }

            return $prefix . $this->_deleted($orig) . $this->_added($final);
    }

        $text1 = implode("\n", $orig);
        $text2 = implode("\n", $final);

        $nl = "\0";

        if ($this->_split_characters) {
            $diff = new Text_Diff('native', [
                preg_split('//', $text1),
                preg_split('//', $text2),
            ]);
        } else {
            $diff = new Text_Diff('native', [
                $this->_splitOnWords($text1, $nl),
                $this->_splitOnWords($text2, $nl),
            ]);
        }

        $renderer = new self(array_merge(
            $this->getParams(),
            ['split_level' => $this->_split_characters ? 'characters' : 'words']
        ));

        return str_replace($nl, "\n", $renderer->render($diff)) . "\n";
    }

    protected function _splitOnWords($string, $newlineEscape = "\n")
    {
        $string = str_replace("\0", '', $string);

        $words = [];
        $length = strlen($string);
        $pos = 0;

        while ($pos < $length) {
            $spaces = strspn($string, " \n", $pos);
            $nextpos = strcspn($string, " \n", $pos + $spaces);
            $words[] = str_replace("\n", $newlineEscape, substr($string, $pos, $spaces + $nextpos));
            $pos += $spaces + $nextpos;
        }

        return $words;
    }

    protected function _encode(&$string)
    {
        $string = htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
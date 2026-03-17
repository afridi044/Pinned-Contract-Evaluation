<?php

class Text_Diff_Engine_shell
{
    public string $_diffCommand = 'diff';

    public function diff(array $from_lines, array $to_lines): array
    {
        array_walk($from_lines, [Text_Diff::class, 'trimNewlines']);
        array_walk($to_lines, [Text_Diff::class, 'trimNewlines']);

        $temp_dir = Text_Diff::_getTempDir();

        $from_file = tempnam($temp_dir, 'Text_Diff');
        $to_file = tempnam($temp_dir, 'Text_Diff');

        if ($from_file === false || $to_file === false) {
            throw new \RuntimeException('Unable to create temporary files for diff computation.');
        }

        $diff = null;

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
            @unlink($from_file);
            @unlink($to_file);
        }

        if ($diff === null) {
            return [new Text_Diff_Op_copy($from_lines)];
        }

        $from_line_no = 1;
        $to_line_no = 1;
        $edits = [];

        preg_match_all(
            '#^(\d+)(?:,(\d+))?([adc])(\d+)(?:,(\d+))?$#m',
            $diff,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $from_start = (int) $match[1];
            $from_end = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : null;
            $operation = $match[3];
            $to_start = (int) $match[4];
            $to_end = isset($match[5]) && $match[5] !== '' ? (int) $match[5] : null;

            if ($operation === 'a') {
                $from_line_no--;
            }

            if ($operation === 'd') {
                $to_line_no--;
            }

            if ($from_line_no < $from_start || $to_line_no < $to_start) {
                assert(($from_start - $from_line_no) === ($to_start - $to_line_no));
                $edits[] = new Text_Diff_Op_copy(
                    $this->_getLines($from_lines, $from_line_no, $from_start - 1),
                    $this->_getLines($to_lines, $to_line_no, $to_start - 1)
                );
            }

            switch ($operation) {
                case 'd':
                    $edits[] = new Text_Diff_Op_delete(
                        $this->_getLines($from_lines, $from_line_no, $from_end)
                    );
                    $to_line_no++;
                    break;

                case 'c':
                    $edits[] = new Text_Diff_Op_change(
                        $this->_getLines($from_lines, $from_line_no, $from_end),
                        $this->_getLines($to_lines, $to_line_no, $to_end)
                    );
                    break;

                case 'a':
                    $edits[] = new Text_Diff_Op_add(
                        $this->_getLines($to_lines, $to_line_no, $to_end)
                    );
                    $from_line_no++;
                    break;
            }
        }

        if (!empty($from_lines)) {
            $edits[] = new Text_Diff_Op_copy(
                $this->_getLines($from_lines, $from_line_no, $from_line_no + count($from_lines) - 1),
                $this->_getLines($to_lines, $to_line_no, $to_line_no + count($to_lines) - 1)
            );
        }

        return $edits;
    }

    public function _getLines(array &$text_lines, int &$line_no, ?int $end = null): array
    {
        if (!empty($end)) {
            $lines = [];
            while ($line_no <= $end) {
                $lines[] = array_shift($text_lines);
                $line_no++;
            }

            return $lines;
        }

        $lines = [array_shift($text_lines)];
        $line_no++;

        return $lines;
    }
}
?>
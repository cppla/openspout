<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * @return list<string>
 */
function values(int $i): array
{
    return ['商品中文 😀', sprintf('%012d', $i), '=SUM(1,2)', '+01234', '-00123', '@CARD', "首行\n第二行", '0000000000000000000000001', '2026-10-22 12:34:56'];
}
$directory = sys_get_temp_dir().'/openspout-stream-'.bin2hex(random_bytes(8));
if (!mkdir($directory, 0o700)) {
    throw new RuntimeException('temporary directory creation failed');
}

try {
    foreach (['XLSX' => 100000, 'ODS' => 2000, 'CSV' => 2000] as $format => $count) {
        memory_reset_peak_usage();
        $base = memory_get_usage(true);
        $file = $directory.'/review.'.strtolower($format);
        $writerClass = 'OpenSpout\Writer\\'.$format.'\Writer';
        $readerClass = 'OpenSpout\Reader\\'.$format.'\Reader';
        $writer = new $writerClass();
        $writer->openToFile($file);
        $headStyle = new Style(fontBold: true, shouldWrapText: true);
        $textStyle = new Style(shouldWrapText: true);
        $writer->addRow(new Row(array_map(static fn (string $value) => new StringCell($value, $headStyle), values(0))));
        for ($i = 1; $i < $count; ++$i) {
            $writer->addRow(new Row(array_map(static fn (string $value) => new StringCell($value, $textStyle), values($i))));
        }
        $writer->close();
        if ('CSV' !== $format) {
            $zip = new ZipArchive();
            if (true !== $zip->open($file)) {
                throw new RuntimeException('zip open failed');
            }
            $member = 'XLSX' === $format ? 'xl/worksheets/sheet1.xml' : 'content.xml';
            // Scan XML in bounded chunks: reading the whole XLSX worksheet would invalidate the streaming memory check.
            $stream = $zip->getStream($member);
            if (!is_resource($stream)) {
                throw new RuntimeException('missing worksheet');
            }
            $tail = '';
            while (!feof($stream)) {
                $chunk = $tail.fread($stream, 65536);
                if (1 === preg_match('/<f(?:\s|>)|table:formula\s*=/', $chunk)) {
                    throw new RuntimeException('unexpected formula');
                }
                $tail = substr($chunk, -64);
            }
            fclose($stream);
            $zip->close();
        }
        $reader = new $readerClass();
        $reader->open($file);
        $seen = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                if ($row->toArray() !== values($seen)) {
                    throw new RuntimeException("{$format} roundtrip failed at {$seen}");
                }
                ++$seen;
            }
        }
        $reader->close();
        if ($seen !== $count) {
            throw new RuntimeException('count mismatch');
        }
        if (memory_get_peak_usage(true) > 32 * 1024 * 1024) {
            throw new RuntimeException('streaming memory budget exceeded');
        }
        echo json_encode(['format' => $format, 'rows' => $seen, 'bytes' => filesize($file), 'php' => PHP_VERSION, 'libxml' => LIBXML_DOTTED_VERSION, 'peak_bytes' => memory_get_peak_usage(true), 'base_bytes' => $base, 'result' => 'PASS'], JSON_UNESCAPED_UNICODE).PHP_EOL;
        unlink($file);
    }
} finally {
    // Only this process's randomly named temporary directory is removed.
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
}

<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 61: PHPUnit metadata lives in attributes, never in doc-comments.
 *
 * PHPUnit 10 still reads `@dataProvider`, `@depends`, `@group` and the rest
 * from doc-comments, PHPUnit 11 reports every one of them as a deprecation,
 * and PHPUnit 12 (Phase 10) stops reading them altogether. A test method
 * whose provider is an annotation then runs WITHOUT its provider and fails
 * on "too few arguments" - or, for `@depends` and `@group`, silently loses
 * what the annotation meant.
 *
 * TODO 40 converted all 36 annotations it found. TODO 49 added a 37th
 * afterwards (`GroupNewsEditTest`), because nothing stopped it; this guard
 * is the thing that does.
 *
 * The scan reads doc-comments with the tokenizer, so a tag inside a string
 * or an ordinary comment does not count, and it asserts a floor of scanned
 * doc-comments, so a scan that finds nothing cannot pass by accident.
 */
class TestMetadataAttributeTest extends TestCase
{
    /**
     * Every doc-comment tag PHPUnit 10/11's annotation parser turns into
     * metadata. `@author` is on the list because PHPUnit treats it as a group.
     */
    private const PHPUNIT_TAGS = [
        'after', 'afterClass', 'author', 'backupGlobals', 'backupStaticAttributes',
        'before', 'beforeClass', 'covers', 'coversClass', 'coversDefaultClass',
        'coversFunction', 'coversNothing', 'dataProvider', 'depends',
        'doesNotPerformAssertions', 'excludeGlobalVariableFromBackup',
        'excludeStaticPropertyFromBackup', 'group', 'large', 'medium',
        'postCondition', 'preCondition', 'preserveGlobalState', 'requires',
        'runClassInSeparateProcess', 'runInSeparateProcess', 'small', 'test',
        'testdox', 'testWith', 'ticket', 'uses',
    ];

    public function test_no_test_file_carries_phpunit_metadata_in_a_doc_comment(): void
    {
        $pattern = '/^\s*\*?\s*@('.implode('|', self::PHPUNIT_TAGS).')\b/m';
        $offenders = [];
        $docComments = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('tests'), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                if (! is_array($token) || $token[0] !== T_DOC_COMMENT) {
                    continue;
                }

                $docComments++;

                if (preg_match_all($pattern, $token[1], $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as [$tag, $offset]) {
                        $line = $token[2] + substr_count(substr($token[1], 0, $offset), "\n");
                        $offenders[] = sprintf(
                            '%s:%d @%s',
                            str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1)),
                            $line,
                            $tag
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(300, $docComments, 'The scan found suspiciously few doc-comments under tests/.');

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'PHPUnit metadata belongs in an attribute (PHPUnit\\Framework\\Attributes\\*), not in a doc-comment.'
        );
    }
}

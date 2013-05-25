<?php
/**
 * Тесты класса TemplateService
 *
 * @version ${product.version}
 *
 * @package TemplateService
 * @subpackage Tests
 */

require_once __DIR__ . '/bootstrap.php';
require_once TESTS_SRC_DIR . '/templateservice.php';
require_once TESTS_SRC_DIR . '/templateservice/classes/Exception.php';
require_once TESTS_SRC_DIR . '/templateservice/classes/InvalidPathException.php';

/**
 * Тесты класса TemplateService
 * @package TemplateService
 * @subpackage Tests
 */
class TemplateServiceTest extends PHPUnit_Framework_TestCase
{
    /**
     * Путь к временной папке теста
     * @var null|string
     */
    private $tempDir = null;

    protected function tearDown()
    {
        if (null !== $this->tempDir)
        {
            // TODO Если папка не пуста, она удалена не будет.
            @rmdir($this->tempDir);
        }
    }

    /**
     * @link https://github.com/Eresus/TemplateService/issues/1
     * @covers TemplateService::uninstallTemplates
     */
    public function testSkipDotsOnUninstall()
    {
        /*
         * Мы не можем использовать vfsStream, т. к. он не поддерживает «.» и «..», см.
         * https://github.com/mikey179/vfsStream/issues/59
         * Мы не можем использовать системную временную папку, т. к. она может располагаться
         * на файловой системе, также не поддерживающей эти спец. директории.
         */
        $root = __DIR__ . '/tmp';
        $folders = array("$root/templates", "$root/templates/foo");
        foreach ($folders as $folder)
        {
            if (!file_exists($folder))
            {
                mkdir($folder);
            }
        }
        $files = array("$root/templates/foo/foo1.html", "$root/templates/foo/foo2.html");
        foreach ($files as $file)
        {
            if (!file_exists($file))
            {
                file_put_contents($file, '');
            }
        }

        $kernel = new stdClass;
        $kernel->froot = $root . '/';

        $CMS = $this->getMock('stdClass', array('getLegacyKernel'));
        $CMS->expects($this->any())->method('getLegacyKernel')->will($this->returnValue($kernel));
        Eresus_CMS::setMock($CMS);

        $plugin = new TemplateService();
        $plugin->uninstallTemplates('foo');
        $this->assertFileNotExists("$root/templates/foo");
    }

    /**
     * Создаёт новую временную папку и возвращает путь к ней
     *
     * @return string
     */
    private function createTempDir()
    {
        $path = tempnam(sys_get_temp_dir(), __CLASS__);
        unlink($path);
        mkdir($path);
        $this->tempDir = $path;
        return $path;
    }
}


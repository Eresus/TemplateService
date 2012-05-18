<?php
/**
 * Служба шаблонов
 *
 * @version ${product.version}
 *
 * @copyright 2012, Михаил Красильников <mihalych@vsepofigu.ru>
 * @license http://www.gnu.org/licenses/gpl.txt	GPL License 3
 * @author Михаил Красильников <mihalych@vsepofigu.ru>
 *
 * Данная программа является свободным программным обеспечением. Вы
 * вправе распространять ее и/или модифицировать в соответствии с
 * условиями версии 3 либо (по вашему выбору) с условиями более поздней
 * версии Стандартной Общественной Лицензии GNU, опубликованной Free
 * Software Foundation.
 *
 * Мы распространяем эту программу в надежде на то, что она будет вам
 * полезной, однако НЕ ПРЕДОСТАВЛЯЕМ НА НЕЕ НИКАКИХ ГАРАНТИЙ, в том
 * числе ГАРАНТИИ ТОВАРНОГО СОСТОЯНИЯ ПРИ ПРОДАЖЕ и ПРИГОДНОСТИ ДЛЯ
 * ИСПОЛЬЗОВАНИЯ В КОНКРЕТНЫХ ЦЕЛЯХ. Для получения более подробной
 * информации ознакомьтесь со Стандартной Общественной Лицензией GNU.
 *
 * Вы должны были получить копию Стандартной Общественной Лицензии
 * GNU с этой программой. Если Вы ее не получили, смотрите документ на
 * <http://www.gnu.org/licenses/>
 *
 * @package TemplateService
 *
 * $Id: myplugin.php 1849 2011-10-03 17:34:22Z mk $
 */

/**
 * Основной класс плагина
 *
 * @package TemplateService
 */
class TemplateService extends Plugin
{
	/**
	 * Версия плагина
	 * @var string
	 */
	public $version = '${product.version}';

	/**
	 * Требуемая версия ядра
	 * @var string
	 */
	public $kernel = '3.00b';

	/**
	 * Название плагина
	 * @var string
	 */
	public $title = 'Служба шаблонов';

	/**
	 * Описание плагина
	 * @var string
	 */
	public $description = 'Может использоваться другими расширениями';

	/**
	 * Путь к корневой директории шаблонов
	 * @var string
	 */
	private $rootDir;

	/**
	 * Конструктор
	 *
	 * @return GoodsCatalogTemplateService
	 */
	public function __construct()
	{
		parent::__construct();
		$this->rootDir = $GLOBALS['Eresus']->froot . 'templates';
	}
	//-----------------------------------------------------------------------------

	/**
	 * Возвращает экземпляр класса
	 *
	 * @return TemplateService
	 */
	public static function &getInstance()
	{
		return $GLOBALS['Eresus']->load('templateservice');
	}
	//-----------------------------------------------------------------------------

	/**
	 * Устанавливает шаблоны в общую директорию шаблонов
	 *
	 * @param string $sourceDir   абсолютный путь к директории с устанавливаемым шаблонами
	 * @param string $targetPath  путь относительно общей директории шаблонов
	 *
	 * @throws TemplateService_InvalidPathException  если указан неправильный путь
	 * @throws TemplateService_PathExistsException  если $path уже существует
	 */
	public function installTemplates($sourceDir, $targetPath)
	{
		if (!is_dir($sourceDir))
		{
			throw new TemplateService_InvalidPathException(
				'Source path not exists or not a directory: ' .	$sourceDir);
		}

		$targetPath = $this->rootDir . '/' . $targetPath;
		if (file_exists($targetPath))
		{
			throw new TemplateService_PathExistsException(
				'Target path already exists: ' .	$targetPath);
		}

		try
		{
			$umask = umask(0000);
			mkdir($targetPath, 0777, true);
			umask($umask);
		}
		catch (Exception $e)
		{
			throw new TemplateService_Exception('Can not create target directory: ' . $targetPath, null,
				$e);
		}

		$templates = new RegexIterator(new DirectoryIterator($sourceDir),
			'/^.*\.html$/', RegexIterator::GET_MATCH);
		/*
		 * Начиная с PHP 5.3.0 можно будет использовать GlobIterator:
		 * GlobIterator($sourceDir . '/*.html', FilesystemIterator::KEY_AS_PATHNAME);
		 */

		foreach ($templates as $template)
		{
			$target = $targetPath . '/' . basename($template[0]);

			copy($sourceDir . '/' . $template[0], $target);
			chmod($target, 0666);
		}
	}
	//-----------------------------------------------------------------------------

	/**
	 * Удаляет установленные ранее шаблоны
	 *
	 * @param string $path  путь к шаблону или директории относительно общей директории шаблонов
	 *
	 * @throws TemplateService_InvalidPathException  если указан неправильный путь
	 */
	public function uninstallTemplates($path)
	{
		$path = $this->rootDir . '/' . $path;
		if (!file_exists($path))
		{
			throw new TemplateService_InvalidPathException(
				'Uninstall path not exists: ' .	$path);
		}

		if (is_file($path))
		{
			unlink($path);
		}
		else
		{
			$branch = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path),
				RecursiveIteratorIterator::SELF_FIRST);

			$files = array();
			$dirs = array();
			foreach ($branch as $file)
			{
				if ($file->isDir())
				{
					$dirs []= $file->getPathname();
				}
				else
				{
					$files []= $file->getPathname();
				}
			}

			/* Вначале удаляем все файлы */
			foreach ($files as $file)
			{
				unlink($file);
			}
			/* Теперь удаляем директории, начиная с самых глубоких */
			for ($i = count($dirs) - 1; $i >= 0; $i--)
			{
				rmdir($dirs[$i]);
			}
			rmdir($path);
		}
	}
	//-----------------------------------------------------------------------------

	/**
	 * Возвращает содержимое шаблона
	 *
	 * @param string $name    имя файла шаблона
	 * @param string $prefix  опциональный префикс (путь относительно корня шаблонов)
	 *
	 * @throws TemplateService_InvalidPathException
	 *
	 * @return string
	 */
	public function getContents($name, $prefix = '')
	{
		$path = $this->rootDir . '/' . $this->getFilename($name, $prefix);

		if (!is_file($path))
		{
			throw new TemplateService_InvalidPathException('Template not exists: ' .	$path);
		}

		$contents = file_get_contents($path);

		return $contents;
	}
	//-----------------------------------------------------------------------------

	/**
	 * Записывает содержимое шаблона
	 *
	 * @param string $contents  содержимое шаблона
	 * @param string $name      имя файла шаблона
	 * @param string $prefix    опциональный префикс (путь относительно корня шаблонов)
	 *
	 * @throws TemplateService_InvalidPathException
	 *
	 * @return void
	 */
	public function setContents($contents, $name, $prefix = '')
	{
		$path = $this->rootDir . '/' . $this->getFilename($name, $prefix);

		if (!is_file($path))
		{
			throw new TemplateService_InvalidPathException('Template not exists: ' .	$path);
		}

		@file_put_contents($path, $contents);
	}
	//-----------------------------------------------------------------------------

	/**
	 * Возвращает путь к файлу шаблона
	 *
	 * @param string $name    имя файла шаблона
	 * @param string $prefix  опциональный префикс (путь относительно корня шаблонов)
	 *
	 * @return string
	 */
	public function getFilename($name, $prefix = '')
	{
		$path = $name;
		if ($prefix != '')
		{
			$path = $prefix . '/' . $path;
		}

		return $path;
	}
	//-----------------------------------------------------------------------------

	/**
	 * Возвращает объект шаблона
	 *
	 * @param string $name    имя файла шаблона
	 * @param string $prefix  опциональный префикс (путь относительно корня шаблонов)
	 *
	 * @throws TemplateService_InvalidPathException
	 *
	 * @return Template
	 */
	public function getTemplate($name, $prefix = '')
	{
		$path = $this->getFilename($name, $prefix);

		if (!is_file($this->rootDir . '/' . $path))
		{
			throw new TemplateService_InvalidPathException('Template not exists: ' .	$path);
		}

		$tmpl = new Template('templates/' . $path);

		return $tmpl;
	}
	//-----------------------------------------------------------------------------

}


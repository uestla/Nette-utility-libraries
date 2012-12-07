<?php

namespace Model\Services;

use Nette;
use Nette\Caching\Cache;
use Nette\Utils\Strings;

use Model\Factories;


/**
 * config.neon:
 * ------------

	parameters:
		webTemp: webtemp

		gallery:
			dir: gallery # photos folder
			tempDir: %webTemp%/gallery-thumbs # folder for generated thumbs
			extensions: # scanned file types
				- *.jpg
				- *.jpeg
				- *.gif
				- *.png
			galleryThumb: # thumbs parameters
				width: 92
				height: 51
				placeX: 13
				placeY: 26
			cache: # thumb caching
				expire: + 1 day

	services:
		galleryService:
			factory: Model\Services\GalleryService()
			setup:
				- setWwwDir( %wwwDir% )
				- setConfig( %gallery% )
				- setImageFactory( @imageFactory )
				- setCacheStorage( ... )

		imageFactory: Model\Factories\ImageFactory()


 * @property-read Cache $cache
 * @property-read string $fullPath
 */
class GalleryService extends Nette\Object
{
	/** @var string */
	protected $wwwDir;

	/** @var array */
	protected $config;

	/** @var string */
	protected $path;

	/** @var string */
	protected $_fullPath = NULL;

	/** @var Factories\ImageFactory */
	protected $imageFactory;

	/** @var \Closure */
	protected $cacheFactory;

	/** @var Cache */
	protected $_cache = NULL;



	const CACHE_THUBMS = 'gallery-thumb-';
	const NAVIGATION = 'navigation';



	/** @return GalleryService */
	function setWwwDir($wwwDir)
	{
		$this->wwwDir = (string) $wwwDir;

		return $this;
	}



	/** @return GalleryService */
	function setConfig(array $config)
	{
		$this->config = $config;

		return $this;
	}



	/** @return GalleryService */
	function setImageFactory(Factories\ImageFactory $factory)
	{
		$this->imageFactory = $factory;

		return $this;
	}



	/** @return GalleryService */
	function setCacheStorage(Nette\Caching\IStorage $storage)
	{
		$this->cacheFactory = function () use ($storage) {
			return new Cache($storage, __CLASS__);
		};

		return $this;
	}



	/** @return Cache */
	function getCache()
	{
		if ($this->_cache === NULL) {
			$this->_cache = callback($this->cacheFactory)->invoke();
		}

		return $this->_cache;
	}



	/** @return string */
	function getFullPath()
	{
		if ($this->_fullPath === NULL) {
			$this->_fullPath = $this->wwwDir . '/' . $this->config['dir'] . ($this->path ? '/' . $this->path : '');
		}

		return $this->_fullPath;
	}



	// === FILE METHODS =============================================================

	/** @return GalleryService */
	function setPath($path)
	{
		if ($path === '.') {
			$this->path = '';

		} else {
			$this->path = rtrim( str_replace('\\', '/', $path), '/' );
		}

		$this->_fullPath = NULL; // cache invalidation

		if ( !is_dir( $this->fullPath ) || Strings::match( $path, '#^(?:\.?/|\.\.(?:$|/))#' ) ) {
			throw new Nette\IOException("Invalid path '$path'.");
		}

		return $this;
	}



	/** @return string */
	function getPath()
	{
		return $this->path;
	}



	/** @return string */
	function getBaseDir()
	{
		return $this->config['dir'];
	}



	/**
	 * @param  string|NULL
	 * @return Finder
	 */
	function getDirs($dir = NULL)
	{
		if ($dir !== NULL) {
			$tmp = $this->path;
			$this->setPath($dir);
		}

		$dirs = Finder::findDirectories('*')
				->exclude('.*')
				->in( $this->fullPath );

		if (isset($tmp)) {
			$this->path = $tmp;
			$this->_fullPath = NULL;
		}

		return $dirs;
	}



	/**
	 * @param  string|NULL
	 * @return Finder
	 */
	function getFiles($dir = NULL, $recursive = FALSE)
	{
		if ($dir !== NULL) {
			$tmp = $this->path;
			$this->setPath($dir);
		}

		$files = Finder::findFiles( $this->config['extensions'] )
				->{$recursive ? 'from' : 'in'}( $this->fullPath );

		if (isset($tmp)) {
			$this->path = $tmp;
			$this->_fullPath = NULL;
		}

		return $files;
	}



	// === TEMPLATE HELPERS =============================================================

	function createGalleryThumb($name, $folderImg = 'images/folder.png')
	{
		$key = static::CACHE_THUBMS . $name;
		$data = $this->cache->load($key);

		if ($data === NULL) {
			$random = NULL;
			foreach ($this->getFiles( $name, TRUE )->orderRandomly() as $file) {
				$random = $file;
				break;
			}

			if ($random === NULL) { // empty folder
				$path = $folderImg;

			} else {
				$image = $this->imageFactory->create( $this->wwwDir . '/' . $folderImg );
				$thumb = $this->imageFactory->create( (string) $random );

				$thumbConf = $this->config['galleryThumb'];

				$thumb->resize( $thumbConf['width'], $thumbConf['height'], Nette\Image::EXACT )->sharpen();
				$image->place( $thumb, $thumbConf['placeX'], $thumbConf['placeY'] );

				$path = $this->config['tempDir'] . '/' . (dirname($name) === '.' ? '' : dirname($name) . '/');
				@mkdir( $this->wwwDir . '/' . $path, TRUE );
				$path .= basename($name) . '.png';
				$image->save( $this->wwwDir . '/' . $path );
			}

			$dpFiles = array(
				'__thumb__' => $this->wwwDir . '/' . $path,
			);

			if (isset($random)) {
				$dpFiles['__parent__'] = dirname((string) $random);
			}

			return $this->cache->save($key, $path, array(
				Cache::FILES => $dpFiles,
				Cache::EXPIRE => isset($this->config['cache']['expire']) ? $this->config['cache']['expire'] : '+ 1 day',
			));
		}

		return $data;
	}
}

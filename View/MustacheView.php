<?php
/**
 * MustacheCake
 *
 * A Mustache implementation for CakePHP.
 *
 * @copyright     Copyright (c) Hanson Wong
 * @link          https://github.com/Dismounted/MustacheCake
 * @package       MustacheCake.View
 * @license       Simplified BSD License (https://github.com/Dismounted/MustacheCake/blob/master/LICENSE)
 */

App::uses('View', 'View');
App::uses('MustachePartialsLoader', 'MustacheCake.View');

/**
 * Mustache view class.
 *
 * This extends the default View class to provide Mustache rendering functionality.
 *
 * @package       MustacheCake.View
 */
class MustacheView extends View {

	/**
	 * File extension. Overrides Cake default. Need to also override in controller!
	 *
	 * @var string
	 */
	public $ext = '.mustache';

	/**
	 * File extension for render classes.
	 *
	 * @var string
	 */
	public $extRenderClass = '.php';

	/**
	 * An instance of the Mustache engine.
	 *
	 * @var Mustache_Engine
	 */
	public $mustache;

	/**
	 * Constructor.
	 *
	 * @param Controller $controller A controller object to pull View::_passedVars from.
	 */
	public function __construct(Controller $controller = null) {
		parent::__construct($controller);

		if (class_exists('Mustache_Autoloader', false) === false) {
			App::import('Vendor', 'MustacheCake.Mustache_Autoloader', array('file' => 'Mustache' . DS . 'src' . DS . 'Mustache' . DS . 'Autoloader.php'));
			Mustache_Autoloader::register();
		}

		$this->mustache = new Mustache_Engine(array(
			'cache' => $this->_getMustacheCachePath(),
			'partials_loader' => new MustachePartialsLoader($this)
		));
	}

	/**
	 * Override to evaluate template through Mustache. Falls back on ".ctp" templates.
	 *
	 * @param string $viewFn Filename of the view.
	 * @param array $dataForView Data to include in rendered view.
	 * @return string Rendered output.
	 */
	protected function _evaluate($viewFile, $dataForView) {
		if ($this->_getViewExt($viewFile) == '.ctp') {
			return parent::_evaluate($viewFile, $dataForView);
		}

		$this->__viewFile = $viewFile;

		$template = $this->_getTemplateAsString($viewFile);
		$renderData = $this->_getRenderData($viewFile, $dataForView);
		$rendered = $this->mustache->render($template, $renderData);

		unset($this->__viewFile);
		return $rendered;
	}

	/**
	 * Grab the view's file extension.
	 *
	 * @param string $viewFn Filename of the view.
	 * @return string File extension.
	 */
	protected function _getViewExt($viewFile) {
		return '.' . pathinfo($viewFile, PATHINFO_EXTENSION);
	}

	/**
	 * Grab the template as a string.
	 *
	 * @param string $viewFn Filename of the view.
	 * @return string Template before output.
	 */
	protected function _getTemplateAsString($viewFile) {
		return file_get_contents($viewFile);
	}

	/**
	 * Grab the render class associated with the view, if it exists.
	 *
	 * @param string $viewFn Filename of the view.
	 * @param array $dataForView Data to include in rendered view.
	 * @return mixed Return a MustacheRender object, or hand back untouched array.
	 */
	protected function _getRenderData($viewFile, $dataForView) {
		$renderClassPath = preg_replace(
			'/' . preg_quote($this->_getViewExt($viewFile)) . '$/i',
			$this->extRenderClass,
			$viewFile
		);

		$renderClassName = $this->_getRenderClassName($renderClassPath);
		if (empty($renderClassName) === true) {
			return $dataForView;
		}

		require_once($renderClassPath);
		return new $renderClassName($this, $dataForView);
	}

	/**
	 * Looks through a file to find the name of the first declared class.
	 *
	 * @param string $file Filename of the class.
	 * @return string Class name, empty if none found.
	 */
	protected function _getRenderClassName($file) {
		if (file_exists($file) === false) {
			return '';
		}

		$fp = fopen($file, 'r');
		$class = $buffer = '';
		$i = 0;

		while (!$class) {
			if (feof($fp)) {
				break;
			}

			$buffer .= fread($fp, 512);
			$tokens = @token_get_all($buffer);

			if (strpos($buffer, '{') === false) {
				continue;
			}

			$tokenCount = count($tokens);
			for (; $i < $tokenCount; ++$i) {
				if ($tokens[$i][0] === T_CLASS) {
					for ($j = ($i + 1); $j < $tokenCount; ++$j) {
						if ($tokens[$j] === '{') {
							$class = $tokens[$i + 2][1];
						}
					}
				}
			}
		}

		return $class;
	}

	/**
	 * Be good and pass on an element filename to the partials loader.
	 *
	 * @param string $name The name of the element to find.
	 * @return mixed Either a string to the element filename or false when one can't be found.
	 */
	public function getPartialFileName($name) {
		return $this->_getElementFilename($name);
	}

	/**
	 * If Cake is using FileEngine, let's hop on!
	 *
	 * @return string The cache path, empty if not applicable.
	 */
	protected function _getMustacheCachePath() {
		$settings = Cache::settings();

		if ($settings['engine'] == 'File') {
			return $settings['path'] . DS . 'mustache';
		}

		return '';
	}

	/**
	 * Get the extensions that view files can use. Override to add ".mustache" into the stack.
	 *
	 * @return array Array of extensions view files use.
	 */
	protected function _getExtensions() {
		$exts = array($this->ext);
		if ($this->ext !== '.mustache') {
			$exts[] = '.mustache';
		}
		if ($this->ext !== '.ctp') {
			$exts[] = '.ctp';
		}
		return $exts;
	}

}
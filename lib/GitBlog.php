<?php

#
# GitBlog
# http://www.monkeynet.ca/GitBlog/
#
# (c) Derek Wueppelmann
# http://www.monkeynet.ca/
#
# For the full license information view the LICENSE file that was ditributed
# with this source code.
#

class GitBlog {
	const version = '0.0.1';

	protected $section;
	protected $error;
	protected $cache;
	protected $renderPage;

	function __construct($sectionDir) {
		$this->error = null;
		$this->cache = array();
		try {
			$this->section = new GBSection($sectionDir);
		}
		catch (Exception $err) {
			$this->error = $err->getMessage();
		}
	}

	function version() {
		return GitBlog::version;
	}

	function basePath() {
		if (!array_key_exists('basePath', $this->cache)) {
			$this->cache['basePath'] = str_replace("/index.php", "", $_SERVER['SCRIPT_NAME']);
		}
		return $this->cache['basePath'];
	}

	function getNavigation($section = null) {
		if (!array_key_exists('navigation', $this->cache)) {
			# Attempt to load a cached version of this data.
			if (file_exists('cache/main_navigation.json')) {
				$data = file_get_contents('cache/main_navigation.json');
				$nav = json_decode($data, true);
				return $nav;
			}

			# Otherwise we'll have to create the data structure from scratch and
			# then attempt to cache it.
			$nav = new GBNavigation(array('path' => '/'));

			# Save it to disk. If possible.
			# TODO

			$this->cache['navigation'] = $nav;
		}

		$nav = $this->cache['navigation'];
		if (!is_null($section)) {
			$nav = $nav->getNavForPath($section->getPath());
		}

		return $nav;
	}

	function renderSectionNavigation($level = 0, $homeLink = null) {
		$nav = $this->getNavigation();
		if (!is_null($homeLink)) {
			$nav->addSubNav(new GBNavigation(
				array('data' => $homeLink)
			));
		}
		return $nav->render($this, 'section', 1, false);
	}

	function renderSectionSubNavigation($level = 0) {
		return $this->getNavigation($this->section)->render($this, 'files', 1, false);
	}

	function link($path) {
		# Need to find our basepath to the index page. Then we can add a base
		# path to the given path.
		$basePath = $this->basePath();

		if (strpos($path, '/') != 0) {
			$basePath .= "/";
		}

		return $basePath . $path;
	}

	function renderPage($page) {
		# Get the include files.
		$headerFile = $this->section->getVar('header', 'header.php');
		$footerFile = $this->section->getVar('footer', 'footer.php');

		# Validate the page we were given.
		if (!$this->error) {
			try {
				$this->renderPage = $this->section->getPage($page);
				$displayContent = $this->renderPage->getFormattedContents();
				$pageVars = $this->renderPage->getPageVars();
			}
			catch (Exception $err) {
				$this->error = $err->getMessage();
			}
		}
		if ($this->error) {
			$displayContent = $this->error;
		}

		include("includes/$headerFile");
		echo $displayContent;
		include("includes/$footerFile");
	}

	function getVar($var, $default = '') {
		if ($this->renderPage && $this->renderPage->getVar($var)) {
			return $this->renderPage->getVar($var);
		}
		if ($this->section && $this->section->getVar($var)) {
			return $this->section->getVar($var);
		}
		return $default;
	}

}

class GBSection {
	const section_info_name = 'section_info.json';

	protected $sectionPath;
	protected $sectionInfo;

	function __construct($section = '') {
		$this->sectionInfo = array();
		$this->setSection($section);
	}

	function getPath() {
		return $this->sectionPath;
	}

	function setSection($sectionDir) {
		# Validate the section. Make sure it's a path that is held under our
		# local directory and contains no loops.
		$localPath = realpath('.');
		$sectionPath = realpath($sectionDir);
		if (strpos($sectionPath, $localPath) != 0) {
			# The path does not fall under this directory skip it.
			throw new Exception("Section given is not valid");
		}
		$sectionPath = substr($sectionPath, strlen($localPath) + 1);

		# Now validate that this directory can be served up.
		if (!$sectionPath) {
			$sectionFile = GBSection::section_info_name;
		}
		else {
			$sectionFile = $sectionPath . '/' . GBSection::section_info_name;
		}

		if (!file_exists($sectionFile)) {
			# We have a valid section_info.json file to read. We should be able
			# to use this directory to serve files.
			throw new Exception("Section given is not valid");
			return;
		}

		$this->sectionPath = $sectionPath;

		$workingPath = "";
		$pathParts = split('/', $sectionPath);
		if ($sectionPath) {
			array_unshift($pathParts, '');
		}

		$sectionInfo = array();
		foreach ($pathParts as $part) {
			$workingPath .= $part;
			if($workingPath) {
				$workingPath .= '/';
			}
			if (file_exists($workingPath . GBSection::section_info_name)) {
				# We can attempt to read the data.
				$json_data = file_get_contents($workingPath. GBSection::section_info_name);
				$info = json_decode($json_data, true);

				# Merge this data with the existing section info.
				$sectionInfo = array_replace($sectionInfo, $info);
			}
		}
		$title_parts = array();
		if (array_key_exists('site_name', $sectionInfo)) {
			array_push($title_parts, $sectionInfo['site_name']);
		}
		if (array_key_exists('section_name', $sectionInfo)) {
			array_push($title_parts, $sectionInfo['section_name']);
		}
		if ($title_parts) {
			$sectionInfo['title'] = join(' - ', $title_parts);
		}

		$this->sectionInfo = $sectionInfo;
	}

	function getPage($page) {
		return new GBPage($this->sectionPath, $page);
	}

	function getVar($var, $default='') {
		if (array_key_exists($var, $this->sectionInfo)) {
			return $this->sectionInfo[$var];
		}
		return $default;
	}
}

class GBPage {
	protected $pagePath;
	protected $rawContent;
	protected $pageVars;

	function __construct($section = '', $page = '') {
		$this->pageVars = null;
		if ($page) {
			if ($section) {
				$displayPage = $section . '/' . $page . '.mkd';
			}
			else {
				$displayPage = $page . '.mkd';
			}

			if (!preg_match("/^[0-9a-z-_\.]+$/i", $page)) {
				throw new Exception("Page $pagePath contains invalid characters.");
			}
			elseif(!file_exists($displayPage)) {
				throw new Exception("Invalid page given for this section.");
			}
			$this->pagePath = $displayPage;
		}
	}

	function getPath() {
		return $this->pagePath;
	}

	function getRawContents() {
		if (!$this->rawContent) {
			$this->rawContent = file_get_contents($this->getPath());	
			# Obtain the list of page vars as well.
			$this->getPageVars();
		}
		return $this->rawContent;
	}

	function getPageVars() {
		if (is_null($this->pageVars)) {
			$this->pageVars = array();
			$content = $this->getRawContents();
			preg_match_all('/<!--([^: >]+): ([^>]+)-->/', $content, $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$content = str_replace($match[0], "", $content);
				$this->pageVars[strtolower($match[1])] = $match[2];
			}
			$this->rawContent = $content;
		}
		return $this->pageVars;
	}

	function findAndValidateLinks($content) {
	}

	function getFormattedContents() {
		# Instantiate the parsedown object.
		$pd = new Parsedown();

		# Read our page contents.
		$displayContent = $this->getRawContents();
		# Do some additional work on the content.
		$this->findAndValidateLinks($displayContent);

		return $pd->text($displayContent);
	}

	function getVar($var, $default='') {
		$pageVars = $this->getPageVars();
		if (array_key_exists($var, $pageVars)) {
			return $pageVars[$var];
		}
		return $default;
	}
}

class GBNavigation {
	protected $path;
	protected $title;
	protected $label;
	protected $sortOrder;
	protected $files;
	protected $subNav;
	protected $parent;

	function  __construct($args = array()) {
		$this->files = array();
		$this->subNav = array();
		if (array_key_exists('path', $args)) {
			$this->path = $args['path'];
			# No leading slashes allowed.
			if (strpos($this->path, '/') == 0) {
				$this->path = str_replace('/', '', $this->path);
			}

			# Do we have a valid section. Throws error on failure.
			$section = new GBSection($this->path);
			$this->title = $section->getVar('section_title');
			$this->label = $section->getVar('section_name');
			$this->sortOrder = $section->getVar('sort_order');
			if ($section->getVar('type') == 'hidden') {
				throw new Exception("Cannot create a navigation entry for a hidden section");
			}

			# Attempt to get the section information.
			$realPath = realpath($this->path);
			$dir = opendir($realPath);
			while ($file = readdir($dir)) {
				# Skip any dot files (including ./ and ../)
				if ($file[0] == '.') continue;

				$subPath = join('/', array($this->path, $file));
				if (is_dir($realPath . '/' . $file)) {
					try {
						$navEntry = new GBNavigation(array('path' => $subPath));
					}
					catch (Exception $err) {
						# Not a valid section, contine onto the next item.
						continue;
					}
					$this->addSubNav($navEntry);
				}
				else {
					try {
						$fileEntry = new GBNavigationFile(array('path' => $this->path, 'fileName' => $file));
					}
					catch (Exception $err) {
						# Not a valid file for inclusion in the navigation. Move
						# onto the next item.
						continue;
					}
					array_push($this->files, $fileEntry);
				}
			}
		}
		elseif (array_key_exists('data', $args)) {
			if (array_key_exists('path', $args['data'])) $this->path = $args['data']['path'];
			if (array_key_exists('title', $args['data'])) $this->tite = $args['data']['title'];
			if (array_key_exists('label', $args['data'])) $this->label = $args['data']['label'];
			if (array_key_exists('sortOrder', $args['data'])) $this->sortOrder = $args['data']['sortOrder'];
			if (array_key_exists('files', $args['data'])) {
				foreach ($args['data']['files'] as $fileData) {
					$fileEntry = new GBNavigationFile(array('data' => $fileData));
					array_push($this->files, $fileEntry);
				}
			}
			if (array_key_exists('subnav', $args['data'])) {
				foreach ($args['data']['subnav'] as $navEntry) {
					$this->addSubNav(new GBNavigation(array('data' => $navEntry)));
				}
			}
		}
	}

	function addSubNav($nav) {
		$nav->parent = $this;
		array_push($this->subNav, $nav);
	}

	function getSortValue() {
		if ($this->sortOrder) {
			return $this->sortOrder;
		}
		return $this->label;
	}

	function getNavForPath($path) {
		if ($path) {
			# Find the first part and then ask for the rest from that item.
			foreach ($this->subNav as $navEntry) {
				if (strpos($navEntry->path, $path) == 0) {
					return $navEntry->getNavForPath($path);
				}
			}
		}
		return $this;
	}

	function isActive() {
		# TODO: 

		return false;
	}

	function render($gb, $type = '', $recurse_level = 1, $render_label = false) {
		$out = '';
		if ($render_label) {
			$active = '';
			$title = '';
			if ($this->isActive()) {
				$active = ' class="active"';
			}
			if ($this->title) {
				$title = ' title="' . htmlentities($this->title) . '"';
			}
			$path = '/' . $this->path;
			if (!preg_match('/\/$/', $path)) $path .= '/';
			$out .= "<li$active><a href=\"" . $gb->link($path) . "\"$title>" . htmlentities($this->label) . "</a>";
		}
		if ($recurse_level != 0) {
			$out = "<ul>";

			# Collect the list of items to render;
			$navItems = array();
			if ($type != 'files') {
				foreach ($this->subNav as $section) {
					array_push($navItems, $section);
				}
			}
			if ($type != 'section') {
				foreach ($this->files as $file) {
					array_push($navItems, $file);
				}
			}

			# Build the soritng function and sort the list of nav items.
			$sortFunction = function ($a, $b) {
				$aVal = $a->getSortValue();
				$bVal = $b->getSortValue();
				if (is_numeric($aVal)) {
					if (is_numeric($bVal)) {
						if ($aVal == $bVal) {
							return 0;
						}
						return (($aVal < $bVal) ? -1 : 1);
					}
					return -1;
				}
				elseif (is_numeric($bVal)) {
					# Second argument is an integer and the first is not. So b must
					# be greater.
					return 1;
				}
				else {
					# String comparison.
					return strcmp($aVal, $bVal);
				}
			};
			usort($navItems, $sortFunction);

			# Render each item.
			foreach ($navItems as $entry) {
				if ($entry instanceof GBNavigation) {
					$out .= $entry->render($gb, $type, $recurse_level - 1, true);
				}
				else {
					$out .= $entry->render($gb);
				}
			}

			$out .= "</ul>";
		}
		if ($render_label) {
			$out .= "</li>";
		}

		return $out;
	}
}

class GBNavigationFile {
	protected $path;
	protected $fileName;
	protected $label;
	protected $title;
	protected $sortOrder;

	function __construct($args = array()) {
		if (array_key_exists('path', $args)) {
			# Load from passed in params.
			$this->path = $args['path'];
			$this->fileName = $args['fileName'];

			if (!preg_match('/\.mkd$/', $this->fileName)) {
				throw new Exception("Only mkd files can be included in the navigation");
			}
			# We only allow .mkd files for the filename. But we are stripping it
			# here because we manually add it when needed. And for navigation
			# it's all chagned to .html anyways.
			$this->fileName = str_replace('.mkd', '', $this->fileName);

			# We need to load the file and obtain the details about it (title and
			# label for the navigation).
			$file = new GBPage($this->path, $this->fileName);
			$this->title = $file->getVar('link_title');
			$this->label = $file->getvar('link_label');
			$this->sortOrder = $file->getVar('sort_order');

			# We need a title or a label to contine.
			if (!$this->title && !$this->label) {
				throw new Exception("Invalid page for navigation");
			}

			# IF we only have a link_title, use it for the label.
			if ($this->title && !$this->label) $this->label = $this->title;
		}
		elseif (array_key_exists('data', $args)) {
			# Load from a datastructure (JSON loading)
			$this->path = $args['data']['path'];
			$this->fileName= $args['data']['filename'];
			$this->label = $args['data']['label'];
			$this->title = $args['data']['title'];
		}
	}

	function getSortValue() {
		if ($this->sortOrder) {
			return $this->sortOrder;
		}
		return $this->label;
	}

	function isActive() {
		# TODO

		return false;
	}

	function render($gb) {
		$active = '';
		$title = '';
		if ($this->isActive()) {
			$active = ' class="active"';
		}
		if ($this->title) {
			$title = ' title="' . htmlentities($this->title) . '"';
		}
		return "<li$active><a href=\"" . $gb->link($this->path . "/" . $this->fileName . '.html') . "\"$title>" . htmlentities($this->label) . "</a></li>";
	}
}
?>

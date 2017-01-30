<?php
	include("lib/Parsedown.php");
	include("lib/GitBlog.php");

	if (array_key_exists('section', $_GET)) {
		$section = $_GET['section'];
	}
	else {
			$section = '';
	}

	$gb = new GitBlog($section);

	# Get the page to display:
	$displayPage = 'index';
	if (array_key_exists('page', $_GET)) {
		$displayPage = $_GET['page'];
	}

	$gb->renderPage($displayPage);
?>

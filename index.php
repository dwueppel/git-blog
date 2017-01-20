<?php
	include("lib/Parsedown.php");

	# Get the page to display:
	# TODO: Need to get this value adn validate we are allowed to display it.
	$displayPage = 'index.mkd';

	# Instantiate the parsedown object.
	$pd = new Parsedown();

	# Load the page contents.
	$displayContent = file_get_contents($displayPage);

	include("includes/header.php");
	echo $pd->text($displayContent);
	include("includes/footer.php");
?>

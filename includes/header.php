<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?= $this->getVar('title') ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	<meta http-equiv="Content-Language" content="en-us" />
	<meta name="description" content="<?= $this->getVar('description') ?>" />
	<meta name="keywords" content="<?= $this->getVar('keywords') ?>" />
	<link type="text/css" rel="stylesheet" href="<?= $this->link("/css/main.css") ?>" title="Standard" />
</head>
<body>
	<div id="Page">
		<div id="Header">
			<a href="<?= $this->link("/") ?>"><img src="<?= $this->link("/images/git-blog-logo.png") ?>" alt="Git-Blog" /></a>
			<p class="subheadline">The barebones blogging software</p>
			<p class="version">V. <?= $this->version() ?></p>
		</div>
		<div id="MainNavigation">
			<?= $this->renderSectionNavigation(0, array('label' => 'Home', 'path' => '', 'sortOrder' => 1)) ?>
		</div>
		<div id="SubNavigation">
			<?= $this->renderSectionSubNavigation(0) ?>
		</div>
		<div id="Content">

<?php
# Index-pagina
# ###############
# Onderdeel van sQuarecoW CMS
# � 2006-2010 sQuarecoW new media
# Versie: 3.0
# Module: cms
# ###############
#
####################################
#
#	Alle acties binnen het CMS gaan via deze indexpagina.
#	Zonder vanuit deze pagina geopend te worden, zijn de andere pagina's niet te benaderen.
#
#	Veel van de php technieken die gebruikt worden zijn ontleend aan SMF >> www.simplemachines.org
#
####################################
# eerst even wat basis dingen
# start een nieuwe sessie
session_start();

# zet een constante om de overige pagina's te beveiligen
define('QWCMS', 1);

# we willen niet overal automatisch slashes
ini_set('magic_quotes_runtime', 0);

# tijdzone + locale
date_default_timezone_set('Europe/Amsterdam');
setlocale(LC_ALL, 'nl_NL', 'nld_nld');

# detecteer de basismap, voor de rechtstreekse includes en file_exists
$base_dir = str_replace('\\', '/', dirname(__FILE__)) . '/';
set_include_path($base_dir);
# de basisdirectories, zo gehouden voor html includes
$media_dir = 'media/';
$sources_dir = 'sources/';
$site_dir = 'site/';
$themes_dir = 'themes/';

# laadt de basis-instellingen
require_once 'settings.php';

# wat belangrijke pagina's includen
require_once $sources_dir . 'load.php';
require_once $sources_dir . 'errors.php';
require_once $sources_dir . 'display.php';
require_once $sources_dir . 'functions.php';
require_once $sources_dir . 'functions-content.php';
require_once $sources_dir . 'functions-display.php';
require_once $sources_dir . 'functions-files.php';
require_once $sources_dir . 'functions-mysql.php';
require_once $sources_dir . 'functions-lang.php';
require_once $sources_dir . 'functions-modules.php';
require_once $sources_dir . 'functions-views.php';

# een eigen error handler
error_reporting(defined('E_STRICT') ? E_ALL ^ E_DEPRECATED | E_STRICT : E_ALL);
set_error_handler('php_error_handler', defined('E_STRICT') ? E_ALL ^ E_DEPRECATED | E_STRICT : E_ALL);
register_shutdown_function('php_shutdown_function');

# probeer te connecten met de database
$db_connection = mysql_initiate_connection($db_host, $db_user, $db_pass, $db);

# we gaan beginnen
# altijd een schone $context;
$context = array();
# schone $account
$account = array();

# start het bufferen van de weergave
ini_set('zlib.output_compression_level', 1);
ob_start();

# laadt de algemene instellingen
load_settings();

# haal de site config
# - daarin de views die beschikbaar zijn voor deze site
# - modules die niet standaard aan staan kunnen in dit bestand worden geactiveerd
require_once 'site/views.php';

# welke modules en functies zijn beschikbaar?
load_modules();

# api's laden
load_apis();

# views laden
load_views();

# de site laden: bepaal wat we willen doen en vang evt results op
load_site();

# en weergeven
ob_exit();

# start de site zelf op
function load_site() {

	global $site_dir, $settings, $context, $site_url;
	global $sources_dir;

	# kijk of de url klopt


	# haal de gegevens van de bezoeker

	# taal laden
	load_language();


	#in_array('mod_rewrite', apache_get_modules())

	# sowieso altijd even opslaan wat we eigenlijk hebben gevraagd
	# alles behalve / komt binnen via een mod_rewrite naar ?r=
	$context['request']['uri'] = !empty($_GET['r']) ? strip_trailing_slash($_GET['r']) : '';

	# alvast even splitsen
	$context['request']['args'] = explode('/', $context['request']['uri']);

	# is er een actie gevraagd?
	if (!empty($context['request']['uri'])) {

		# admin is speciaal ingebakken
		if (strpos($context['request']['uri'], 'admin') === 0) {
            # dan ook alle admingegevens van alle modules laden
			# default theme
			$context['default_theme'] = 'admin';
			$context['default_theme_dir'] = 'admin/';
			# we willen (als het even kan) admin doen
			$context['admin'] = true;
			# we moeten dan sowieso in de admingroup zitten
		}
        $context['request'];

		# haal de gezochte view
        $context['view'] = get_view($context['request']['uri']);

		# als er geen view is gevonden, dan melden we dat
		if (empty($context['view']))
			$context['messages'][] = 'error::onbekende opdracht: "' . $context['request']['uri'] . '"';

		# anders kunnen we verder
		else {
            # doen we admin? dan even de admin dingen laden
            #if (
			# kijk of je deze actie mag uitvoeren
			# eerst maar eens niet
			$can_do = false;
			# ok dat valt dan weer mee
			if (empty($context['view']['restricted']))
				$can_do = true;
			# ah, eens even kijken
			else {
				#if (isAllowedTo($_GET['action'])) {
					$can_do = true;
				#}
			}

			# nope
			if (!$can_do) {
				# een foutmelding
				$context['messages'][] = 'error::je mag deze pagina niet bekijken: "' . $context['request']['uri'] . '"';
				# we vallen terug op de standaardactie
				# WE MOETEN TERUG NAAR DE VORIGE PAGINA
				unset($context['view']);
			}
		}
	}

	# als er nog steeds geen actie is gedefinieerd, dan halen we hier de standaardactie
	# dit is de laatste fallback, als dit niet lukt kunnen we de site niet weergeven
	if (empty($context['view']))
		if (!$context['view'] = get_view($settings['site_default_view']))
			show_fatal_error('Onherstelbare fout', 'Onherstelbare fout', 'default_action_not_loaded', 404);

	# anders kunnen we verder
	# waar bevinden zich de views?
	$context['view']['dir'] = $view_dir = !empty($context['admin']) ? $sources_dir . 'modules/'  : $site_dir;

	# bestaat het aangegeven bestand wel?
	check_file($view_dir . $context['view']['file']);
    
	# anders uitvoeren
	require_once $view_dir . $context['view']['file'];
	return $context['view']['function']();
}
?>
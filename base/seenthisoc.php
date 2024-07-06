<?php

/**
 * Plugin Seenthis_OpenCalais
 */
if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

// lire directement une declaration SQL SHOW CREATE TABLE => SPIP
function seenthisoc_lire_create_table($x) {
	$m = ['field' => [], 'key' => []];

	foreach (explode("\n", $x) as $line) {
		$line = trim(preg_replace('/,$/', '', $line));
		if (preg_match('/^(PRIMARY KEY) \(`(.*?)`\)/', $line, $c)) {
			$m['key'][$c[1]] = $c[2];
		}
		elseif (preg_match('/^(KEY) `(.*?)`\s+\((.*?)\)/', $line, $c)) {
			$m['key'][$c[1] . ' ' . $c[2]] = $c[3];
		}
		elseif (preg_match('/^`(.*?)`\s+(.*?)$/', $line, $c)) {
			$m['field'][$c[1]] = str_replace('`', '', $c[2]);
		}
	}

	return $m;
}

function seenthisoc_declarer_tables_auxiliaires($tables_auxiliaires) {
	return $tables_auxiliaires;
}

function seenthisoc_upgrade($nom_meta_base_version, $version_cible) {
	$current_version = 0.0;


	if (
		(!isset($GLOBALS['meta'][$nom_meta_base_version]) )
		|| (($current_version = $GLOBALS['meta'][$nom_meta_base_version]) != $version_cible)
	) {
		include_spip('base/abstract_sql');
		if (version_compare($current_version, '1.0.2', '<')) {
			include_spip('base/serial');
			include_spip('base/auxiliaires');
			include_spip('base/create');
			creer_base();

			maj_tables([
				'spip_syndic_oc',
			]);

			if (version_compare($current_version, '1.0.2', '<')) {
				seenthisoc_recuperer_tags_102();
				sql_query('ALTER TABLE spip_oc_uri ADD INDEX `tag` (`tag`(60))');
				sql_query('ALTER TABLE spip_oc_uri ADD INDEX `uri` (`uri`(60))');
			}

		/**
		 * pour la version suivante
		 *	if (version_compare($current_version,"1.0.3",'<')) {
		 *		sql_drop_table("spip_syndic_oc");
		 *	}
		 */

			ecrire_meta($nom_meta_base_version, $current_version = $version_cible, 'non');
		}
	}
}

function seenthisoc_vider_tables($nom_meta_base_version) {
	effacer_meta($nom_meta_base_version);
	sql_drop_table('spip_syndic_oc');
}


function seenthisoc_install($action, $prefix, $version_cible) {
	$version_base = $GLOBALS[$prefix . '_base_version'];
	switch ($action) {
		case 'test':
			$ok = (isset($GLOBALS['meta'][$prefix . '_base_version'])
				and version_compare($GLOBALS['meta'][$prefix . '_base_version'], $version_cible, '>='));

			if (!function_exists('curl_init')) {
				$ok = false;
				echo "<p class='error'>" . _L('n&#xE9;cessite @module@', ['module' => 'php-curl']) . '</p>';
			}

			return $ok;
			break;
		case 'install':
			seenthisoc_upgrade($prefix . '_base_version', $version_cible);
			break;
		case 'uninstall':
			seenthisoc_vider_tables($prefix . '_base_version');
			break;
	}
}

# en 1.0.2, reinjecter dans spip_syndic_oc les tags qui sont disperses
# sous forme groupe,mot,id_mot,id_syndic :
function seenthisoc_recuperer_tags_102() {
	sql_query("CREATE TABLE spip_oc_uri SELECT a.relevance, a.off,
		CONCAT( CONCAT(g.titre,':'), m.titre ) as tag, u.url_site AS uri
		FROM spip_syndic_oc AS a
			LEFT JOIN spip_mots AS m ON a.id_mot=m.id_mot
			LEFT JOIN spip_groupes_mots AS g ON m.id_groupe=g.id_groupe
			LEFT JOIN spip_syndic AS u ON u.id_syndic=a.id_syndic
		");
}

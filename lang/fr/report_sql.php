<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * French language strings for the SQL Report plugin.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['addnew'] = 'Nouveau rapport SQL';
$string['createfeaturesnote'] = '« Publier et continuer la modification » pour déverrouiller davantage d\'options — graphiques et filtres par utilisateur et par cours — qui nécessitent les colonnes du rapport publié pour être configurées.';
$string['ai:copied'] = 'Copié';
$string['ai:copy'] = 'Copier';
$string['ai:generate'] = 'Générer le SQL';
$string['ai:generatedname'] = 'Requête générée';
$string['ai:generating'] = 'Génération en cours…';
$string['ai:heading'] = 'Générer du SQL avec l\'IA';
$string['ai:heading_help'] = 'Décrivez en langage naturel les données que vous souhaitez, puis cliquez sur **Générer le SQL**. L\'IA écrit une requête SELECT dans l\'éditeur SQL ci-dessous.

Par exemple : « Afficher tous les étudiants inscrits à plus de 3 cours ».

Vous pouvez aussi faire référence au SQL déjà présent dans l\'éditeur — des instructions comme « ajouter une colonne à ceci », « afficher aussi l\'adresse e-mail » ou « corriger cette erreur » utilisent votre requête actuelle comme point de départ plutôt que d\'en construire une nouvelle à partir de zéro.

En particulier, commencer votre instruction par le mot **also** (également) reprend votre SQL existant et le complète — par exemple « also show the user\'s last login » ajoute à la requête actuelle au lieu de la remplacer.

Vérifiez toujours le SQL généré avant d\'enregistrer — l\'IA peut faire des erreurs.';
$string['ai:history'] = 'Vos questions récentes';
$string['ai:historyempty'] = 'Aucun historique pour le moment. Générez une requête et elle apparaîtra ici.';
$string['ai:historyload'] = 'Charger le SQL';
$string['ai:historywhen'] = 'Quand';
$string['ai:latency'] = 'Généré en {$a} s — vérifiez le SQL avant d\'enregistrer.';
$string['ai:placeholder'] = 'ex. Afficher tous les étudiants inscrits à plus de 3 cours';
$string['ai:prompt'] = 'Instruction envoyée au LLM';
$string['ai:question'] = 'Décrivez les données que vous souhaitez';
$string['ai:sqldescription'] = 'Sélectionne {$a->columns} depuis {$a->tables}.';
$string['ai:sqldescriptionnocols'] = 'Rapport sur {$a}.';
$string['ai:sqlname'] = 'Rapport {$a}';
$string['audienceallusers'] = 'Tous les utilisateurs du site';
$string['audiencecohort'] = 'Membres des cohortes';
$string['audiencecohorts'] = 'Cohortes';
$string['audiencecoursemissing'] = 'un cours supprimé';
$string['audiencecourseparticipant'] = 'Participants au cours';
$string['audiencecourseparticipantdesc'] = 'Utilisateurs ayant une inscription active dans {$a}.';
$string['audiencecourserole'] = 'Utilisateurs ayant un rôle dans le cours';
$string['audiencecourseroledesc'] = 'Utilisateurs détenant l\'un des rôles choisis dans {$a} (ou un contexte ancêtre).';
$string['audiencedefault'] = 'Automatique (selon le cours et la visibilité)';
$string['audiencenone'] = 'Personne (vous et les responsables du site uniquement)';
$string['audienceroles'] = 'Rôles';
$string['audiencesettings'] = 'Qui peut voir le rapport';
$string['audiencetype'] = 'Public';
$string['audiencetype_help'] = 'Détermine qui peut ouvrir le rapport Report Builder publié.

* **Automatique** — déterminé à partir des paramètres ci-dessus : un rapport limité à un cours est visible par les participants de ce cours, un rapport à l\'échelle du site par tous les utilisateurs, et un rapport masqué uniquement par vous et les responsables du site.
* **Participants au cours / Utilisateurs ayant un rôle dans le cours** — nécessitent qu\'une portée de cours soit définie ci-dessus.
* **Tous les utilisateurs du site**, **Membres des cohortes**, **Personne** — s\'appliquent à l\'échelle du site.

Vous pouvez affiner davantage le public dans l\'onglet Publics de Report Builder, mais republier le rapport réinitialise ce choix.';
$string['bulkactions'] = 'Actions groupées';
$string['cachedef_schema'] = 'Schéma de base de données et carte des clés étrangères pour l\'autocomplétion de l\'éditeur';
$string['cacheheader'] = 'Mise en cache';
$string['cachemode'] = 'Mode de cache';
$string['cachemode_help'] = 'Le mode Direct exécute le SQL directement à chaque requête. Le mode En cache l\'exécute selon une planification et sert le dernier instantané actualisé — utilisez ce mode pour une requête lente, afin que les utilisateurs obtiennent des résultats rapides et que la base de données ne soit pas sollicitée à chaque chargement de page.';
$string['cachemodelive'] = 'Direct';
$string['cachemodecached'] = 'En cache';
$string['cachemodecachedbadge'] = 'En cache · toutes les {$a} min';
$string['cacheinterval'] = 'Intervalle d\'actualisation';
$string['cacheinterval_help'] = 'Fréquence d\'actualisation de la table de cache en arrière-plan. Des intervalles plus courts gardent les données plus fraîches mais exécutent la requête (lente) plus souvent.';
$string['cacheintervaloption'] = 'Toutes les {$a} minutes';
$string['cachedataasof'] = 'Données au : {$a}';
$string['cachedataasoflabel'] = 'Données au';
$string['cachenodata'] = 'Pas encore actualisé — la première actualisation s\'exécute peu après la publication.';
$string['cachelasterrorlabel'] = 'Dernière erreur d\'actualisation : {$a}';
$string['errcacheintervalempty'] = 'Saisissez un intervalle d\'actualisation d\'au moins 5 minutes.';
$string['errcreatecachetable'] = '{$a}';
$string['errdropcachetable'] = 'Impossible de supprimer la table de cache : {$a}';
$string['errrefreshcache'] = 'Impossible d\'actualiser la table de cache : {$a}';
$string['cacherefreshed'] = 'Cache actualisé.';
$string['refreshcachenow'] = 'Actualiser le cache maintenant';
$string['task:scanduerefreshes'] = 'Actualiser les caches en attente pour les rapports SQL en mode cache';
$string['chartbar'] = 'Graphique à barres';
$string['chartcolumn'] = 'Graphique';
$string['chartdatalabels'] = 'Afficher les étiquettes de valeur';
$string['chartdatalabels_help'] = 'Affiche chaque valeur tracée sous forme de nombre au-dessus de sa barre ou à côté de son point de ligne, afin que les chiffres exacts puissent être lus directement sur le graphique. S\'applique uniquement aux graphiques à barres et en ligne (les graphiques en secteurs / en anneau affichent les valeurs dans la légende). Supprimé automatiquement lorsqu\'une série comporte trop de points pour être étiquetée sans chevauchement. Désactivé par défaut.';
$string['chartdatalabelslabel'] = 'Afficher chaque valeur sur le graphique à barres / en ligne';
$string['chartdoughnut'] = 'Graphique en anneau';
$string['chartdownloadpng'] = 'Télécharger en PNG';
$string['chartexportcsv'] = 'Exporter en CSV';
$string['chartlabelsize'] = 'Taille du texte des étiquettes';
$string['chartlabelsize_help'] = 'Taille de police (en points) pour les étiquettes de catégorie du graphique — la légende du secteur et les étiquettes de l\'axe X des graphiques à barres / en ligne.';
$string['chartlabelsizeoption'] = '{$a} pt';
$string['chartline'] = 'Graphique en ligne';
$string['chartmulticolour'] = 'Barres multicolores';
$string['chartmulticolour_help'] = 'Attribue à chaque barre sa propre couleur à partir d\'une palette adaptée au daltonisme, au lieu d\'une couleur unique partagée, afin que les catégories soient plus faciles à distinguer. Graphiques à barres uniquement (les graphiques en secteurs et en anneau sont déjà colorés par tranche ; une ligne est une série unique). Désactivé par défaut.';
$string['chartmulticolourlabel'] = 'Colorer chaque barre différemment';
$string['chartnone'] = 'Aucun graphique';
$string['chartpie'] = 'Graphique en secteurs';
$string['chartprint'] = 'Imprimer';
$string['chartpublishrequired'] = 'Pour configurer le graphique, cliquez sur Publier et continuer la modification.';
$string['chartreportname'] = '{$a} (graphique)';
$string['chartrowlimit'] = 'Limite de lignes du graphique';
$string['chartrowlimit_help'] = 'Nombre maximal de lignes à tracer. Gardez ce nombre faible (≤ 200) pour des graphiques lisibles.';
$string['chartsettings'] = 'Paramètres du graphique';
$string['chartshowdata'] = 'Afficher le tableau de données';
$string['chartshowdata_help'] = 'Affiche les paires étiquette/valeur du graphique sous forme de tableau sous l\'image du graphique, dans le rapport graphique. Fournit une alternative textuelle pour les lecteurs d\'écran et permet aux utilisateurs de lire les chiffres exacts. Désactivé par défaut.';
$string['chartshowdatalabel'] = 'Afficher les valeurs tracées sous forme de tableau sous le graphique';
$string['charttype'] = 'Type de graphique';
$string['chartxcol'] = 'Colonne d\'étiquette (axe X / tranches)';
$string['chartxcol_help'] = 'Colonne dont les valeurs étiquettent chaque barre, point ou tranche de secteur.';
$string['chartycol'] = 'Colonne de valeur (axe Y)';
$string['chartycol_help'] = 'Colonne dont les valeurs sont tracées. Doit contenir des données numériques.';
$string['checkallgood'] = 'Aucun problème détecté. La requête semble correcte.';
$string['checkcasecolumnsintro'] = 'Ces colonnes appliquent UPPER()/LOWER() en SQL :';
$string['checkcasecolumnsintroone'] = 'Cette colonne applique UPPER()/LOWER() en SQL :';
$string['checkcasecolumnsmanual'] = 'Impossible de localiser automatiquement l\'expression de cette colonne — basculez-la vers %%CASE()%% manuellement.';
$string['checkcasecolumnsoutro'] = 'Cliquez sur le nom pour le faire basculer vers %%CASE()%% afin que la casse soit appliquée à l\'affichage tandis que la colonne continue de trier et de filtrer sur la valeur d\'origine (et reste portable entre les bases de données).';
$string['checkdatecolumnsintro'] = 'Ces colonnes ressemblent à des dates :';
$string['checkdatecolumnsintroone'] = 'Cette colonne ressemble à une date :';
$string['checkdatecolumnsmanual'] = 'Impossible de localiser automatiquement l\'expression de cette colonne — encadrez-la avec %%TIMESTAMP()%% manuellement.';
$string['checkdatecolumnsoutro'] = 'Cliquez sur le nom pour encadrer son expression avec %%TIMESTAMP()%% afin qu\'elle s\'affiche comme une date formatée et triable.';
$string['checkdistinctlarge'] = 'SELECT DISTINCT sur {$a} lignes doit trier et dédupliquer l\'ensemble du résultat, ce qui est lent à cette taille. Envisagez un GROUP BY sur des colonnes indexées, ou supprimez DISTINCT si les jointures produisent déjà des lignes uniques.';
$string['checkfullscan'] = 'Analyse complète de la table « {$a->table} » (~{$a->rows} lignes), aucun index utilisé. Ce rapport peut être lent — ajoutez un filtre WHERE sur une colonne indexée. Colonnes indexées : {$a->indexed}.';
$string['checkindexedcolumns'] = 'Indexées : {$a}.';
$string['checknotindexedcolumns'] = 'Non indexées : {$a}.';
$string['indexedcolumn'] = 'Colonne indexée';
$string['checklargeresult'] = 'Cette requête renvoie {$a} lignes. Les résultats volumineux s\'affichent lentement — ajoutez un filtre ou une clause LIMIT.';
$string['checkleadingwildcard'] = 'Un motif LIKE commence par un caractère générique (« %… » ou « _… »). Un caractère générique en tête empêche la base de données d\'utiliser un index sur cette colonne, ce qui force une analyse complète. Ancrez le motif (« abc% ») lorsque cela est possible.';
$string['checknonsargable'] = 'Une fonction encapsule une colonne dans la clause WHERE (par exemple DATE(col) ou LOWER(col)). Ceci n\'est pas « sargable » — la base de données ne peut pas utiliser d\'index sur cette colonne. Filtrez plutôt la colonne brute (par exemple une comparaison d\'intervalle, ou comparez un epoch stocké).';
$string['checkquery'] = 'Tester la requête';
$string['checkquery_help'] = 'Exécute votre SQL contre la base de données sans enregistrer ni publier, puis rapporte les résultats. Cela vérifie que la requête est valide et s\'exécute, compte les lignes renvoyées, et signale les problèmes de performance probables — analyses complètes de table, index manquants, filtres non « sargable », résultats volumineux ou DISTINCT — ainsi que les colonnes de date que vous pourriez vouloir encadrer avec %%TIMESTAMP()%%.

Ceci est purement indicatif : cela ne modifie jamais vos données et n\'est pas requis avant d\'enregistrer ou de publier.';
$string['checkrowcount'] = 'Lignes renvoyées : {$a}.';
$string['checkrowcounttimed'] = 'Lignes renvoyées : {$a->rows}. Généré en {$a->ms} ms.';
$string['checkrowcounttimeout'] = 'Le comptage des lignes a expiré après {$a}s — la requête est lente ou le résultat très volumineux. Le rapport pourrait être lent.';
$string['checkselectsubquery'] = 'Une sous-requête dans la liste SELECT est évaluée une fois par ligne renvoyée, ce qui multiplie le travail sur un résultat volumineux. Une JOIN ou un WITH (CTE) est généralement plus rapide.';
$string['checksortindex'] = 'Le rapport trie par {$a->sortcol}, qui n\'est pas indexée, ce qui oblige la base de données à trier l\'ensemble du résultat. Trier par une colonne indexée est plus rapide — colonnes indexées disponibles : {$a->indexed}.';
$string['compiledsql'] = 'SQL compilé (ce qui a réellement été exécuté)';
$string['confirmdeletemany'] = 'Voulez-vous vraiment supprimer ces {$a} source(s) de rapport ? Cela supprime la vue et le rapport sous-jacents de chacune, action irréversible.';
$string['convertaliasspaces'] = 'Remplacer automatiquement les espaces dans l\'alias de colonne par des tirets bas';
$string['convertquestionmark'] = 'Convertir automatiquement les ? entre guillemets en CHAR(63)';
$string['copyof'] = 'Copie de {$a}';
$string['copysuccess'] = 'Source de rapport copiée. Vous modifiez maintenant la copie.';
$string['coursecolumn'] = 'Limiter aux cours enseignés par l\'utilisateur';
$string['coursecolumn_help'] = 'Limite éventuellement la portée de ce rapport afin que chaque utilisateur ne voie que les lignes des cours qu\'il enseigne. Choisissez la colonne de sortie contenant un identifiant de cours ; au moment de la consultation, le rapport n\'affiche que les lignes où cette colonne correspond à l\'un des cours pour lesquels l\'utilisateur a un rôle d\'enseignant ou d\'enseignant sans droit de modification.

Un utilisateur qui n\'enseigne aucun cours ne voit aucune ligne. Cela permet de publier un rapport unique pour un large public (par exemple tout le personnel enseignant) tout en garantissant que chaque enseignant ne voit que ses propres cours. Laissez sur « Choisir une colonne… » pour ne pas appliquer de filtre par cours enseigné.';
$string['coursescope'] = 'Portée du cours';
$string['coursescope_help'] = 'Le cours auquel appartient ce rapport. Laissez vide pour un rapport à l\'échelle du site.

Le cours détermine deux choses lors de la publication du rapport : le contexte dans lequel sa permission « Voir le rapport » est vérifiée, et son public par défaut (participants au cours pour un rapport limité à un cours, tous les utilisateurs pour un rapport à l\'échelle du site).

Modifiez ceci pour redéfinir la portée d\'une requête — par exemple un brouillon importé qui a été défini à l\'échelle du site car son cours d\'origine n\'existait pas sur ce site. Vous ne pouvez choisir que des cours pour lesquels vous êtes autorisé à voir des rapports.';
$string['createrole:aigenerate'] = 'Inclure « Génération SQL par IA »';
$string['createrole:aigenerate_desc'] = 'Accorde également local/sqlchat:use, afin que les titulaires puissent utiliser la zone de question IA pour générer du SQL. Affiché uniquement lorsque le plugin local_sqlchat est installé. Laissez décoché si les auteurs doivent écrire le SQL eux-mêmes.';
$string['createrole:approve'] = 'Inclure « Approuver et publier »';
$string['createrole:approve_desc'] = 'Accorde également report/sql:approve, afin que les titulaires puissent publier et dépublier eux-mêmes les sources de rapport. Laissez décoché si un approbateur distinct doit publier leurs brouillons.';
$string['createrole:author'] = 'Auteur de sources de rapport';
$string['createrole:author_desc'] = 'Toujours inclus : report/sql:author permet aux titulaires d\'écrire et d\'enregistrer des sources de rapport (l\'objet de ce rôle). Sont également toujours accordés moodle/reportbuilder:view, moodle/reportbuilder:viewall et moodle/reportbuilder:editall, afin que les titulaires puissent ouvrir et modifier tout rapport publié via /reportbuilder/view.php, quel que soit son public ou son propriétaire.';
$string['createrole:create'] = 'Créer le rôle';
$string['createrole:done'] = 'Le rôle « Auteur de rapport » a été créé. Attribuez-le à des personnes ci-dessous.';
$string['createrole:exists'] = 'Un rôle « Auteur de rapport » existe déjà. La soumission de ce formulaire mettra à jour ses capacités selon votre sélection ci-dessous.';
$string['createrole:intro'] = 'Ceci crée un rôle système regroupant les capacités liées aux sources de rapport, afin de permettre à des personnes de confiance non administrateurs de créer des rapports sans en faire des responsables de site à part entière. Choisissez les capacités à inclure, puis créez le rôle et attribuez-le à des personnes.';
$string['createrole:linklabel'] = 'Créer le rôle « Auteur de rapport »';
$string['createrole:title'] = 'Créer le rôle « Auteur de rapport »';
$string['createrole:updated'] = 'Les capacités du rôle « Auteur de rapport » ont été mises à jour. Attribuez-le à des personnes ci-dessous.';
$string['createrole:viewall'] = 'Inclure « Voir toutes les sources de rapport »';
$string['createrole:viewall_desc'] = 'Accorde également report/sql:viewall, afin que les titulaires puissent voir et gérer les sources de rapport de tout le monde, pas seulement les leurs.';
$string['createrole:warning'] = 'Créer un rapport signifie écrire une requête SQL SELECT arbitraire, capable de lire presque n\'importe quelle table de la base de données (seule une courte liste d\'exclusion telle que les tables config, sessions et mots de passe est bloquée). Ce rôle constitue donc effectivement un accès en lecture aux données à l\'échelle du site. Ne l\'attribuez qu\'à des personnes en qui vous auriez confiance pour un accès direct en lecture à la base de données, et vérifiez que toute colonne sensible est couverte par la liste d\'exclusion de colonnes dans les paramètres du plugin.';
$string['crimport:colname'] = 'Rapport';
$string['crimport:colnotes'] = 'Modifications appliquées';
$string['crimport:colreason'] = 'Raison';
$string['crimport:coltype'] = 'Type';
$string['crimport:importableheading'] = 'Rapports importables';
$string['crimport:importselected'] = 'Importer la sélection';
$string['crimport:intro'] = 'Voici les rapports SQL trouvés dans le bloc Configurable Reports. Les rapports importables se traduisent proprement et seront créés en tant que brouillons dont vous êtes propriétaire, prêts à publier. Les rapports rejetés utilisent des fonctionnalités qui ne peuvent pas être converties automatiquement — portez-les manuellement.';
$string['crimport:linklabel'] = 'Importer depuis Configurable Reports';
$string['crimport:noneimportable'] = 'Aucun rapport SQL de Configurable Reports n\'a pu être traduit automatiquement. Voir la liste des rejets ci-dessous pour connaître les raisons.';
$string['crimport:noneselected'] = 'Aucun rapport n\'a été sélectionné.';
$string['crimport:noteclean'] = 'Aucune modification nécessaire';
$string['crimport:notedatefn'] = 'Fonction(s) de date MySQL réécrite(s) en jetons portables %%TIMESTAMP%% / %%EPOCH%% / %%NOW%%';
$string['crimport:notenativedate'] = 'Fonction(s) de date MySQL native(s) {$a} conservée(s) — elles s\'exécutent sur cette base MySQL/MariaDB, mais le rapport importé ne sera pas portable vers PostgreSQL';
$string['crimport:noteqmark'] = 'Le ? littéral dans une chaîne a été réécrit en chr(63)';
$string['crimport:notequotes'] = 'Les littéraux de chaîne « entre guillemets doubles » ont été convertis en \'guillemets simples\'';
$string['crimport:notetoken'] = 'Jeton Configurable Reports {$a} substitué';
$string['crimport:reasondatefn'] = 'Utilise la fonction de date MySQL uniquement {$a}, qui n\'a pas d\'équivalent portable';
$string['crimport:reasonfilter'] = 'Utilise un jeton de filtre interactif {$a} ; reconstruisez-le comme filtre Report Builder après l\'import';
$string['crimport:reasonnosql'] = 'Aucun SQL n\'a pu être décodé depuis ce rapport';
$string['crimport:reasonnotsql'] = 'N\'est pas un rapport SQL (type : {$a})';
$string['crimport:reasontoken'] = 'Utilise un jeton non pris en charge {$a}';
$string['crimport:reasonuserid'] = 'Utilise {$a} ; utilisez plutôt le paramètre « Limiter à l\'utilisateur consultant » sur le brouillon importé';
$string['crimport:rejectedheading'] = 'Rapports rejetés';
$string['crimport:title'] = 'Importer depuis Configurable Reports';
$string['crimport:title_help'] = 'Importe les rapports SQL stockés dans le bloc Configurable Reports (block_configurable_reports) en tant que sources de rapport en brouillon.

Chaque rapport est décodé et soumis à une traduction fixe : les fonctions de date MySQL deviennent des jetons portables %%TIMESTAMP%% / %%EPOCH%% / %%NOW%%, les chaînes entre guillemets doubles deviennent des guillemets simples, et un ? littéral dans une chaîne est reconstruit avec chr(63). Les rapports utilisant des fonctionnalités qui ne peuvent pas être converties (comme %%USERID%% ou les jetons interactifs %%FILTER%%) sont listés comme rejetés avec une raison.

Les rapports importés arrivent en tant que brouillons dont vous êtes propriétaire et doivent être publiés avant d\'être actifs. Aucune IA n\'est utilisée — chaque conversion est une règle fixe.';
$string['crimport:unavailable'] = 'Le bloc Configurable Reports (block_configurable_reports) n\'est pas installé, il n\'y a donc rien à importer.';
$string['customisecolumns'] = 'Personnaliser le rapport';
$string['customsqlimport:intro'] = 'Voici les requêtes trouvées dans le rapport Ad-hoc Database Queries (report_customsql). Les requêtes importables se traduisent proprement et seront créées en tant que brouillons dont vous êtes propriétaire, prêts à publier. Les requêtes rejetées utilisent des fonctionnalités qui ne peuvent pas être converties automatiquement — portez-les manuellement.';
$string['customsqlimport:linklabel'] = 'Importer depuis Ad-hoc Database Queries';
$string['customsqlimport:noneimportable'] = 'Aucune requête Ad-hoc Database Queries n\'a pu être traduite automatiquement. Voir la liste des rejets ci-dessous pour connaître les raisons.';
$string['customsqlimport:noteescape'] = 'Jeton(s) d\'échappement customsql (%%Q%% / %%C%% / %%S%%) substitué(s) par leurs caractères littéraux';
$string['customsqlimport:reasonparam'] = 'Utilise le paramètre nommé interactif {$a} ; reconstruisez-le comme filtre Report Builder après l\'import';
$string['customsqlimport:title'] = 'Importer depuis Ad-hoc Database Queries';
$string['customsqlimport:title_help'] = 'Importe les requêtes stockées dans le rapport Ad-hoc Database Queries (report_customsql) en tant que sources de rapport en brouillon.

Chaque requête est soumise à une traduction fixe : les fonctions de date MySQL deviennent des jetons portables %%TIMESTAMP%% / %%EPOCH%% / %%NOW%%, les chaînes entre guillemets doubles deviennent des guillemets simples, les jetons d\'échappement customsql (%%Q%% / %%C%% / %%S%%) deviennent leurs caractères littéraux, et un ? littéral dans une chaîne est reconstruit avec chr(63). Les requêtes utilisant des fonctionnalités qui ne peuvent pas être converties (comme %%USERID%% ou les paramètres nommés interactifs :param) sont listées comme rejetées avec une raison.

Les requêtes importées arrivent en tant que brouillons dont vous êtes propriétaire et doivent être publiées avant d\'être actives. customsql n\'a pas de portée par cours, donc chaque brouillon démarre à l\'échelle du site. Aucune IA n\'est utilisée — chaque conversion est une règle fixe.';
$string['customsqlimport:unavailable'] = 'Le rapport Ad-hoc Database Queries (report_customsql) n\'est pas installé, il n\'y a donc rien à importer.';
$string['delete'] = 'Supprimer';
$string['deleteselected'] = 'Supprimer la sélection';
$string['deleteselecthelp'] = 'Cochez les sources de rapport à supprimer. La suppression supprime la vue de base de données et le rapport sous-jacents de chacune, action irréversible.';
$string['description'] = 'Description';
$string['duplicate'] = 'Dupliquer';
$string['edit'] = 'Modifier';
$string['editreport'] = 'Modifier dans Report Builder';
$string['embedcodecopied'] = 'Code d\'intégration copié';
$string['embedcodecopy'] = 'Copier le code d\'intégration';
$string['entityquery'] = 'Source de rapport';
$string['erraliasspaces'] = 'L\'alias de colonne « {$a} » contient des espaces. Les alias de colonne dans SQL Report ne peuvent pas contenir d\'espaces — utilisez un tiret bas ou la casse chameau à la place, par exemple SELECT firstname AS first_name FROM user. Vous pouvez renommer la colonne avec un espace après publication, via Report Builder.';
$string['erraudiencecohortsempty'] = 'Choisissez au moins une cohorte.';
$string['erraudiencecourse'] = 'Ce public s\'applique à un cours. Choisissez une portée de cours ci-dessus avant de le sélectionner.';
$string['erraudiencerolesempty'] = 'Choisissez au moins un rôle.';
$string['errchartdata'] = 'Les données du rapport pour ce graphique n\'ont pas pu être chargées. Contactez le propriétaire du rapport si le problème persiste.';
$string['errchartnotconfigured'] = 'Aucun graphique n\'est configuré pour cette requête. Modifiez la requête pour ajouter des paramètres de graphique.';
$string['errchartnotpublished'] = 'Cette requête n\'est pas publiée. Publiez-la d\'abord avant de consulter le graphique.';
$string['errcolumnnoalias'] = 'La colonne « {$a} » est une expression sans nom. Donnez un alias à chaque colonne calculée ou agrégée, par exemple SELECT count(*) AS total FROM course.';
$string['errcourseidplaceholder'] = 'Le SQL utilise %%COURSEID%%, ce rapport nécessite donc une portée de cours fixe. Choisissez un cours ci-dessus avant d\'enregistrer — ou, pour afficher les données propres à chaque cours dans un bloc, supprimez le filtre %%COURSEID%% du SQL, affichez la colonne d\'identifiant de cours, et définissez plutôt « Limiter au cours sur lequel se trouve le bloc ».';
$string['errcreateview'] = '{$a}';
$string['errdeniedcolumn'] = 'Colonne non autorisée : {$a}';
$string['errdeniedkeyword'] = 'Mot-clé non autorisé : {$a}';
$string['errdeniedtable'] = 'Table non autorisée : {$a}';
$string['errdropview'] = 'Impossible de supprimer la vue de base de données : {$a}';
$string['errduplicatecolumn'] = 'Les tables jointes partagent des noms de colonne en double (par exemple les deux ont « id »). Remplacez SELECT * par des alias de colonne explicites : SELECT u.id AS userid, fp.id AS postid, ...';
$string['errimportempty'] = 'Le fichier d\'export ne contient aucune source de rapport.';
$string['errimportformat'] = 'Ce fichier n\'est pas un export SQL Report valide.';
$string['errjoinnoon'] = 'Une JOIN n\'a pas de condition ON (ou USING). Chaque JOIN nécessite une condition de jointure, par exemple JOIN {user_enrolments} ue ON ue.userid = u.id';
$string['errmultistatement'] = 'Les instructions multiples ne sont pas autorisées.';
$string['errnodeleteselection'] = 'Sélectionnez au moins une source de rapport à supprimer.';
$string['errnoexportselection'] = 'Sélectionnez au moins une source de rapport à exporter.';
$string['errnoimportselection'] = 'Sélectionnez au moins une source de rapport à importer.';
$string['errnotselect'] = 'Seules les requêtes SELECT sont autorisées.';
$string['errpagecourseambiguous'] = 'Un rapport ne peut porter qu\'un seul jeton %%PAGECOURSE(expr)%%, et son expression doit correspondre à une colonne de sortie nommée. Utilisez un seul %%PAGECOURSE()%% marquant la colonne contenant un identifiant de cours, et donnez-lui un alias, par exemple SELECT %%PAGECOURSE(c.id)%% AS courseid, ... FROM {course} c.';
$string['errpagecourseunresolved'] = 'Le jeton %%PAGECOURSE()%% n\'a pas pu être associé à une colonne de sortie nommée « {$a} ». Donnez à la colonne marquée un alias explicite pour qu\'elle devienne une colonne de vue nommée, par exemple SELECT %%PAGECOURSE(c.id)%% AS courseid, ... — la publication a été arrêtée plutôt que de laisser le filtre par cours de page non appliqué.';
$string['errparse'] = 'Le SQL n\'a pas pu être analysé : {$a}';
$string['errpgsqldatefn'] = 'La fonction {$a}, spécifique à PostgreSQL, n\'est pas prise en charge par MySQL. Utilisez un équivalent multiplateforme.';
$string['errplaceholder'] = 'Le SQL contient un espace réservé non renseigné « {$a} ». Remplacez-le par une valeur réelle avant d\'enregistrer — par exemple remplacez « l.userid = ## » par « l.userid = 2 ».';
$string['errplaceholderuserid'] = 'Le SQL contient « {$a} », qui n\'est pas un espace réservé pris en charge. Il n\'existe pas d\'espace réservé par utilisateur consultant, car le rapport s\'exécute à partir d\'une vue de base de données fixe. Pour limiter le rapport aux lignes de la personne qui l\'ouvre, encadrez la colonne d\'identifiant utilisateur dans le SQL avec %%VIEWER(...)%% — par exemple SELECT %%VIEWER(u.id)%% AS viewerid, ... — ou supprimez « {$a} » et sélectionnez la colonne d\'identifiant utilisateur dans le champ « Limiter à l\'utilisateur consultant » à la fin de ce formulaire. Dans les deux cas, le filtre par utilisateur est appliqué automatiquement à l\'exécution.';
$string['errqualifiedtable'] = 'La référence de table qualifiée par le schéma « {$a} » n\'est pas autorisée. Les rapports ne peuvent lire que les propres tables du site en utilisant la syntaxe Moodle {tablename} ; les références inter-schémas ou inter-bases de données (par exemple information_schema.columns) sont bloquées.';
$string['errquestionmark'] = 'Le SQL contient un caractère ?, que la couche de base de données traite comme un espace réservé de paramètre de requête. Si ? apparaît dans une chaîne d\'URL, remplacez-le par CHAR(63) — par exemple CONCAT(\'…/view.php\', CHAR(63), \'id=\', course.id).';
$string['errteachesambiguous'] = 'Un rapport ne peut porter qu\'un seul jeton %%TEACHES(expr)%%, et son expression doit correspondre à une colonne de sortie nommée. Utilisez un seul %%TEACHES()%% marquant la colonne contenant un identifiant de cours, et donnez-lui un alias, par exemple SELECT %%TEACHES(c.id)%% AS courseid, ... FROM {course} c.';
$string['errteachesunresolved'] = 'Le jeton %%TEACHES()%% n\'a pas pu être associé à une colonne de sortie nommée « {$a} ». Donnez à la colonne marquée un alias explicite pour qu\'elle devienne une colonne de vue nommée, par exemple SELECT %%TEACHES(c.id)%% AS courseid, ... — la publication a été arrêtée plutôt que de laisser le filtre par cours enseigné non appliqué.';
$string['errviewerambiguous'] = 'Un rapport ne peut porter qu\'un seul jeton %%VIEWER(expr)%%, et son expression doit correspondre à une colonne de sortie nommée. Utilisez un seul %%VIEWER()%% marquant la colonne contenant l\'identifiant utilisateur, et donnez-lui un alias, par exemple SELECT %%VIEWER(fp.userid)%% AS viewerid, fp.subject FROM {forum_posts} fp.';
$string['errviewerunresolved'] = 'Le jeton %%VIEWER()%% n\'a pas pu être associé à une colonne de sortie nommée « {$a} ». Donnez à la colonne marquée un alias explicite pour qu\'elle devienne une colonne de vue nommée, par exemple SELECT %%VIEWER(u.id)%% AS viewerid, ... — la publication a été arrêtée plutôt que de laisser le rapport sans portée (ce qui montrerait toutes les lignes à chaque utilisateur consultant).';
$string['event:querycreated'] = 'Requête ad hoc créée';
$string['event:querydeleted'] = 'Requête ad hoc supprimée';
$string['event:querypublished'] = 'Requête ad hoc publiée';
$string['event:queryunpublished'] = 'Requête ad hoc dépubliée';
$string['event:queryupdated'] = 'Requête ad hoc mise à jour';
$string['export'] = 'Exporter';
$string['exportselected'] = 'Exporter la sélection';
$string['exportselecthelp'] = 'Cochez les sources de rapport à inclure dans le fichier d\'export, puis téléchargez le JSON.';
$string['filterpublishrequired'] = 'Pour configurer les filtres par utilisateur et par cours, cliquez sur Publier et continuer la modification.';
$string['copysql'] = 'Copier le SQL';
$string['copysqldone'] = 'SQL copié dans le presse-papiers';
$string['copysqltooltip'] = 'Copier le SQL dans le presse-papiers';
$string['copysqlprefixed'] = 'Copier le SQL avec les noms de table réels';
$string['copysqlprefixeddone'] = 'SQL copié avec les noms de table réels et préfixés';
$string['copysqlmenu'] = 'Options de copie';
$string['errcopyprefixed'] = 'Impossible de copier le SQL préfixé.';
$string['formatsql'] = 'Formater le SQL';
$string['formatsqltooltip'] = 'Reformater le SQL selon une mise en forme standard (Maj+Ctrl+F)';
$string['import'] = 'Importer';
$string['importdemoted'] = 'Défini à l\'échelle du site car son cours n\'a pas été trouvé sur ce site. Modifiez chaque brouillon et définissez sa portée de cours avant de publier : {$a}.';
$string['importdone'] = '{$a} source(s) de rapport importée(s) en tant que brouillons.';
$string['importallhidden'] = 'Rien ne peut être importé : chaque source de rapport de ce fichier nécessite un plugin qui n\'est pas installé ici : {$a}.';
$string['importfile'] = 'Fichier d\'export';
$string['importhidden'] = 'Masqué (un plugin requis n\'est pas installé ici) : {$a}.';
$string['importselected'] = 'Importer la sélection';
$string['importselecthelp'] = 'Cochez les sources de rapport à importer. Chacune est créée comme nouveau brouillon dont vous êtes propriétaire et doit être publiée avant utilisation.';
$string['importskipped'] = 'Ignoré (échec de la validation SQL) : {$a}.';
$string['importupload'] = 'Téléverser et choisir';
$string['importuploadhelp'] = 'Téléversez un fichier JSON produit précédemment par l\'action Exporter. Vous choisirez ensuite quelles sources de rapport importer.';
$string['install:createrole'] = 'Créez éventuellement un rôle « Auteur de rapport » afin que des non-administrateurs puissent créer des rapports. Examinez d\'abord les implications de sécurité : {$a}';
$string['install:loadsamples'] = 'SQL Report est livré avec des exemples de rapports SQL que vous pouvez charger pour démarrer : {$a}';
$string['install:privilegefail'] = 'SQL Report installé, mais l\'utilisateur de base de données ne peut pas créer ni supprimer de vues. La publication des requêtes échouera tant que les privilèges ne seront pas corrigés. Erreur : {$a}';
$string['install:privilegeok'] = 'SQL Report : l\'utilisateur de base de données peut créer et supprimer des vues.';
$string['lastmodified'] = 'Dernière modification';
$string['linkargexpr'] = 'La valeur de colonne affichée dans la cellule.';
$string['linkargkeycol'] = 'Optionnel : une autre colonne de sortie dont la valeur remplit <code>{}</code>, afin que la cellule puisse afficher une chose tout en établissant le lien sur une autre.';
$string['linkargpath'] = "URL relative au site (doit commencer par <code>/</code>, sans schéma) ; <code>{}</code> est remplacé par la valeur encodée en URL, par exemple <code>/user/view.php?id={}</code>.";
$string['linkhelpargs'] = 'Arguments';
$string['linkhelpintro'] = 'Affiche une cellule sous forme de lien : <code>%%LINK(expr, \'path\')%%</code> ou <code>%%LINK(expr, keycol, \'path\')%%</code>.';
$string['linkhelptitle'] = 'Jeton de lien';
$string['name'] = 'Nom';
$string['noqueries'] = 'Aucune source de rapport pour le moment.';
$string['norows'] = 'Aucune donnée à afficher.';
$string['owner'] = 'Propriétaire';
$string['pagecoursecolumn'] = 'Limiter au cours sur lequel se trouve le bloc';
$string['pagecoursecolumn_help'] = 'S\'applique uniquement lorsque ce rapport est affiché via le bloc SQL Report sur une page de cours. Choisissez la colonne de sortie contenant un identifiant de cours ; le bloc n\'affiche alors que les lignes du cours de la page sur laquelle il se trouve, afin qu\'un seul bloc (ou un bloc ajouté à chaque cours) affiche les données propres à chaque cours.

En dehors d\'une page de cours (tableau de bord ou page d\'accueil du site), aucun filtre par cours de page n\'est appliqué. Le visualiseur de rapport autonome ignore également ce paramètre, car il n\'a pas de « cours courant ». Laissez sur « Choisir une colonne… » pour ne pas appliquer de filtre par cours de page.';
$string['plugindisabled'] = 'SQL Report est actuellement désactivé par l\'administrateur du site.';
$string['pluginexplained'] = 'À propos des sources de rapport';
$string['pluginexplained_help'] = 'Ce plugin vous permet d\'écrire une requête SQL SELECT et de la publier sous forme de rapport Report Builder entièrement configurable — sans avoir besoin de PHP.

Lorsque vous publiez une requête, le plugin crée une VUE de base de données à partir de votre SQL, lit ses colonnes, et enregistre une source de données Report Builder pointant vers cette vue. Vous pouvez ensuite construire, filtrer et partager le rapport comme n\'importe quel autre rapport Report Builder.

Seules les requêtes SELECT sont autorisées, et une liste d\'exclusion bloque l\'accès aux tables sensibles. Modifier le SQL d\'une requête publiée reconstruit la vue et le rapport lors de la prochaine publication.';
$string['pluginname'] = 'SQL Report';
$string['preview'] = 'Aperçu des 5 premières lignes';
$string['preview_help'] = 'Affiche votre SQL actuel sous forme d\'un véritable rapport Report Builder, en ligne et sans enregistrer ni publier. Les colonnes sont typées et formatées exactement comme elles le seraient lors de la publication, c\'est donc un moyen rapide de voir à quoi ressemblera le rapport. Seules les 5 premières lignes sont affichées.';
$string['previewheading'] = 'Résultat de l\'aperçu';
$string['previewloading'] = 'Construction de l\'aperçu…';
$string['privacy:metadata:query'] = 'Sources de rapport enregistrées, créées par des utilisateurs.';
$string['privacy:metadata:query:ownerid'] = 'Utilisateur ayant créé la requête.';
$string['privacy:metadata:query:querysql'] = 'Le SQL de la requête.';
$string['privacy:metadata:query:timecreated'] = 'Date de création de la requête.';
$string['privacy:metadata:queryview'] = 'Un journal des sources de rapport publiées que chaque utilisateur a ouvertes.';
$string['privacy:metadata:queryview:timeviewed'] = 'Date d\'ouverture de la source de rapport.';
$string['privacy:metadata:queryview:userid'] = 'Utilisateur ayant ouvert la source de rapport.';
$string['publish'] = 'Publier';
$string['queries'] = 'Rapports SQL enregistrés';
$string['querysql'] = 'SQL (SELECT uniquement)';
$string['querysql_help'] = 'Une seule instruction SELECT ou WITH...SELECT. Utilisez la syntaxe de table Moodle (par exemple {course}). Le plugin crée une VUE de base de données à partir de cette requête et expose ses colonnes comme une source Report Builder.

Aliasez toujours les tables (par exemple FROM {user} u) car {user} se résout en mdl_user à l\'exécution.

Encadrez une colonne texte avec %%CASE(expr, mode)%% pour l\'afficher en majuscules, minuscules, casse de titre ou casse de phrase (par exemple %%CASE(u.lastname, upper)%%). La valeur stockée reste inchangée, la colonne continue donc de trier et de filtrer sur le texte d\'origine, et la transformation fonctionne de la même manière sur MySQL/MariaDB et PostgreSQL.

Pour le schéma de la base de données Moodle, voir <a href="https://www.examulator.com/er/output/index.html" target="_blank">examulator.com/er</a>.

Pour des exemples de requêtes et de l\'inspiration, voir <a href="https://docs.moodle.org/502/en/ad-hoc_contributed_reports" target="_blank">Moodle ad-hoc contributed reports</a>.';
$string['reportsource'] = 'Source de rapport';
$string['reportsourceheader'] = '{$a}';
$string['reportsources'] = 'Rapports SQL';
$string['repository:intro'] = 'Ces sources de rapport partagées proviennent du dépôt distant {$a}. Cochez celles que vous voulez et importez-les. Chacune est créée comme brouillon dont vous êtes propriétaire, que vous devrez publier avant utilisation.';
$string['repository:introsingle'] = 'Ces sources de rapport partagées proviennent du dépôt distant {$a}. Choisissez-en une à importer. Elle est créée comme brouillon dont vous êtes propriétaire, nommé avec le préfixe « Sample: », que vous devrez publier avant utilisation.';
$string['repository:linklabel'] = 'Importer depuis le dépôt partagé';
$string['repository:none'] = 'Aucune source de rapport partagée n\'a pu être lue depuis le dépôt {$a}. Vérifiez l\'URL et que le dépôt contient des fichiers d\'export de sources de rapport.';
$string['repository:noneselected'] = 'Aucune source de rapport n\'a été sélectionnée.';
$string['repository:refresh'] = 'Actualiser depuis le dépôt';
$string['repository:title'] = 'Sources de rapport du dépôt partagé';
$string['repository:titlesingle'] = 'Importer une source de rapport partagée';
$string['repository:unconfigured'] = 'Aucun dépôt partagé n\'est configuré. Définissez-en un dans les paramètres du plugin (Dépôt de sources de rapport partagées) au préalable.';
$string['roledescription'] = 'Créer, modifier et publier des sources de rapport (report_sql) à l\'échelle du site. REMARQUE : la création de rapports permet des requêtes SQL SELECT arbitraires contre la base de données, ce rôle accorde donc effectivement un accès en lecture aux données à l\'échelle du site. À n\'attribuer qu\'à des créateurs de rapports de confiance.';
$string['rolename'] = 'Auteur de rapport';
$string['runreport'] = 'Ouvrir le rapport';
$string['samples:coldesc'] = 'Description';
$string['samples:colname'] = 'Nom';
$string['samples:colselect'] = 'Importer';
$string['samples:duplicates'] = 'Ignoré(s) (déjà présent(s)) : {$a}.';
$string['samples:import'] = 'Importer';
$string['samples:importselected'] = 'Importer la sélection';
$string['samples:intro'] = '{$a} exemples de sources de rapport sont fournis avec ce plugin. Cochez ceux que vous voulez et importez-les. Chacun est créé comme brouillon dont vous êtes propriétaire, que vous devrez publier avant utilisation.';
$string['samples:introsingle'] = '{$a} exemples de sources de rapport sont fournis avec ce plugin. Choisissez-en un à importer. Il est créé comme brouillon dont vous êtes propriétaire, nommé avec le préfixe « Sample: », que vous devrez publier avant utilisation.';
$string['samples:linklabel'] = 'Charger des exemples de rapports SQL';
$string['samples:none'] = 'Aucun exemple de source de rapport fourni n\'a été trouvé.';
$string['samples:noneselected'] = 'Aucun exemple n\'a été sélectionné.';
$string['samples:previewsql'] = 'Afficher le SQL';
$string['samples:requires'] = 'Nécessite {$a}';
$string['samples:requiresmissing'] = 'Ignoré (plugin requis non installé) : {$a}.';
$string['samples:requiresmissingbadge'] = 'Nécessite {$a} (non installé)';
$string['samples:showall'] = 'Afficher {$a} exemple(s) nécessitant un plugin non installé ici';
$string['samples:samplelinklabel'] = 'Charger un exemple de rapport SQL';
$string['samples:sampleprefix'] = 'Exemple : {$a}';
$string['samples:selectall'] = 'Tout sélectionner';
$string['samples:selectnone'] = 'Ne rien sélectionner';
$string['samples:title'] = 'Charger des exemples de rapports SQL';
$string['samples:titlesingle'] = 'Charger un exemple de rapport SQL';
$string['saveandpublish'] = 'Enregistrer et publier';
$string['saveandpublishedit'] = 'Publier et continuer la modification';
$string['savedandpublished'] = 'Modifications enregistrées et rapport publié';
$string['savedpublishfailed'] = 'Modifications enregistrées, mais la publication a échoué : {$a}';
$string['schedule'] = 'Programmer des e-mails';
$string['selectcolumn'] = '(choisir une colonne)';
$string['settings:aigenerate'] = 'Génération SQL par IA';
$string['settings:aigenerate_desc'] = 'Afficher une zone de question IA sur le formulaire de modification de requête. Nécessite que le plugin local_sqlchat soit installé et configuré.';
$string['settings:denycolumns'] = 'Liste d\'exclusion des colonnes sensibles';
$string['settings:denycolumns_desc'] = 'Liste de noms de colonnes, séparés par des virgules, des espaces ou des retours à la ligne, qui seront retirés de tout résultat SELECT introspecté.';
$string['settings:denytables'] = 'Liste d\'exclusion des tables';
$string['settings:denytables_desc'] = 'Liste de noms de tables, séparés par des virgules, des espaces ou des retours à la ligne, qui ne pourront jamais être interrogées. Préremplie avec la liste intégrée du plugin de tables protégées (config, sessions, jetons, historique des mots de passe, et similaires). Cette liste est entièrement modifiable — supprimer une entrée permet d\'interroger cette table, modifiez-la donc avec précaution.';
$string['settings:enumfilterthreshold'] = 'Seuil du filtre déroulant';
$string['settings:enumfilterthreshold_desc'] = 'Lorsqu\'une colonne texte comporte ce nombre de valeurs distinctes ou moins (mesuré au moment de la publication), son filtre de rapport est affiché sous forme de liste déroulante de ces valeurs au lieu d\'un champ de texte libre. Définir à 0 pour désactiver et garder toutes les colonnes texte en filtres de texte libre.';
$string['settings:enumrowceiling'] = 'Plafond de lignes pour le filtre déroulant';
$string['settings:enumrowceiling_desc'] = 'Ignore la détection de filtre déroulant lorsqu\'une vue publiée compte plus de ce nombre de lignes, afin qu\'un rapport volumineux ne subisse pas une analyse distincte par colonne au moment de la publication (toutes ses colonnes texte restent en texte libre). Définir à 0 pour toujours effectuer le test quelle que soit la taille. Pertinent uniquement lorsque le seuil du filtre déroulant est différent de zéro.';
$string['settings:enabled'] = 'Activer SQL Report';
$string['settings:enabled_desc'] = 'Lorsque cette case est décochée, le plugin est désactivé : son entrée est retirée du menu Rapports et ses pages (liste, modification, exécution, graphique) sont bloquées. Les rapports publiés créés via Report Builder ne sont pas affectés.';
$string['settings:sharedrepository'] = 'Dépôt de sources de rapport partagées';
$string['settings:sharedrepository_desc'] = 'URL du dépôt GitHub contenant des fichiers d\'export de sources de rapport partagées. Les auteurs peuvent le parcourir et importer ses sources de rapport en tant que brouillons. Laissez vide pour désactiver le navigateur de dépôt partagé.';
$string['settings:sharedrepositoryenabled'] = 'Activer le dépôt de sources de rapport partagées';
$string['settings:sharedrepositoryenabled_desc'] = 'Autoriser les auteurs à parcourir et importer depuis le dépôt de sources de rapport partagées configuré ci-dessous. Désactivé par défaut ; tant que c\'est désactivé, aucune requête n\'est faite au dépôt distant et sa page de navigation et ses liens sont masqués.';
$string['settings:showbraces'] = 'Afficher les accolades de table dans l\'éditeur';
$string['settings:showbraces_desc'] = 'Affiche les accolades Moodle {table} autour des noms de table dans l\'éditeur SQL. Lorsque désactivé, les tables s\'affichent sans accolades ; dans les deux cas vous n\'avez jamais besoin de les saisir — les accolades sont ajoutées automatiquement à l\'enregistrement.';
$string['settings:showlastmodified'] = 'Afficher la colonne de dernière modification';
$string['settings:showlastmodified_desc'] = 'Afficher une colonne triable « Dernière modification » dans la liste des sources de rapport.';
$string['settings:syntaxhighlight'] = 'Coloration syntaxique SQL et autocomplétion';
$string['settings:syntaxhighlight_desc'] = 'Activer un éditeur SQL CodeMirror 6 sur le formulaire de requête. Suggère les mots-clés SQL ainsi que les noms de table et de colonne Moodle depuis la base de données en direct.';
$string['settings:viewretaindays'] = 'Conservation de l\'historique de consultation (jours)';
$string['settings:viewretaindays_desc'] = 'Nombre de jours de conservation de l\'historique d\'audit des consultations de rapport. Les lignes plus anciennes sont supprimées par une tâche planifiée. Définir à 0 pour conserver l\'historique indéfiniment.';
$string['sql:approve'] = 'Approuver et publier des sources de rapport';
$string['sql:author'] = 'Créer des sources de rapport SQL';
$string['sql:view'] = 'Exécuter des sources de rapport publiées';
$string['sql:viewall'] = 'Voir toutes les sources de rapport quel que soit le public';
$string['sql:viewown'] = 'Exécuter des sources de rapport dans son propre cours';
$string['status'] = 'Statut';
$string['status_draft'] = 'Brouillon';
$string['status_published'] = 'Publié';
$string['strftimeviewdate'] = '%d/%m/%y, %H:%M';
$string['summaryreport'] = 'Rapport de synthèse';
$string['task:purgeviews'] = 'Purger l\'ancien historique de consultation de rapport';
$string['testquery'] = 'Test en cours…';
$string['testview:fail'] = 'L\'utilisateur de base de données ne peut pas créer ni supprimer de vues. Erreur : {$a}';
$string['testview:grantshint'] = 'Accordez à l\'utilisateur de base de données Moodle les privilèges CREATE VIEW et DROP sur le schéma (par exemple sur MySQL/MariaDB : GRANT CREATE VIEW, DROP ON moodle.* TO \'mdluser\'@\'host\';).';
$string['testview:linklabel'] = 'Exécuter le test de privilège de vue de base de données';
$string['testview:ok'] = 'L\'utilisateur de base de données peut créer et supprimer des vues. La publication des requêtes devrait fonctionner.';
$string['testview:run'] = 'Exécuter le test';
$string['testview:intro'] = 'Ce test crée puis supprime immédiatement une vue de base de données jetable afin de confirmer que l\'utilisateur de base de données Moodle détient les privilèges CREATE VIEW et DROP.';
$string['testview:title'] = 'Test de privilège de vue de base de données';
$string['timecreated'] = 'Date de création';
$string['tokenhintcase'] = 'Transformation de casse du texte : upper | lower | title | sentence (affichage uniquement)';
$string['tokenhintcontextblock'] = 'Constante de niveau CONTEXT_BLOCK (80)';
$string['tokenhintcontextcourse'] = 'Constante de niveau CONTEXT_COURSE (50)';
$string['tokenhintcontextcoursecat'] = 'Constante de niveau CONTEXT_COURSECAT (40)';
$string['tokenhintcontextmodule'] = 'Constante de niveau CONTEXT_MODULE (70)';
$string['tokenhintcontextsystem'] = 'Constante de niveau CONTEXT_SYSTEM (10)';
$string['tokenhintcontextuser'] = 'Constante de niveau CONTEXT_USER (30)';
$string['tokenhintcoursecontext'] = "Identifiant de la ligne de contexte du cours lié";
$string['tokenhintcourseid'] = 'Identifiant du cours lié (0 = à l\'échelle du site)';
$string['tokenhintepoch'] = 'Littéral/expression datetime vers un entier epoch Unix';
$string['tokenhintlink'] = "Lie la cellule à un chemin relatif au site : LINK(expr, 'path'), où {} dans path est l'emplacement de la valeur, par exemple '/user/view.php?id={}'. Ajoutez une colonne clé, LINK(expr, keycol, 'path'), pour établir le lien sur une autre colonne de sortie.";
$string['tokenhintnow'] = 'Heure actuelle sous forme d\'entier epoch Unix';
$string['tokenhintpagecourse'] = 'Limite un bloc/une intégration au cours sur lequel il se trouve : PAGECOURSE(expr) marque la colonne d\'identifiant de cours, par exemple PAGECOURSE(c.id) AS courseid. Appliqué depuis le cours de la page (ignoré dans le visualiseur de rapport autonome).';
$string['tokenhintteaches'] = 'Limite le rapport aux cours enseignés par l\'utilisateur consultant : TEACHES(expr) marque la colonne d\'identifiant de cours, par exemple TEACHES(c.id) AS courseid. Appliqué par utilisateur consultant ; la colonne reste visible.';
$string['tokenhinttimestamp'] = 'Colonne epoch vers une date ; format optionnel, par exemple dd/mm/yyyy';
$string['tokenhintviewer'] = 'Limite le rapport à l\'utilisateur consultant : VIEWER(expr) marque la colonne d\'identifiant utilisateur, par exemple VIEWER(u.id) AS viewerid. Appliqué par utilisateur consultant ; la colonne est masquée dans le résultat.';
$string['tokenhintwwwroot'] = 'URL du site (wwwroot)';
$string['tourdesc'] = 'Une courte visite guidée de la page de liste des sources de rapport.';
$string['tourname'] = 'Visite guidée SQL Report';
$string['tourstep1content'] = 'Commencez ici pour créer une source de rapport. Écrivez une requête SQL <em>SELECT</em>, puis publiez-la pour construire un rapport Report Builder entièrement configurable — sans avoir besoin de PHP.';
$string['tourstep1title'] = 'Créer une source de rapport';
$string['tourstep2content'] = 'Chaque source de rapport que vous avez enregistrée est listée ici, avec son propriétaire et son statut. Triez ou filtrez n\'importe quelle colonne pour en retrouver une rapidement.';
$string['tourstep2title'] = 'Vos sources de rapport';
$string['tourstep3content'] = 'Le statut indique si une source de rapport est encore un <strong>Brouillon</strong> ou a été <strong>Publiée</strong> comme rapport actif.';
$string['tourstep3title'] = 'Brouillon ou publié';
$string['tourstep4content'] = 'Modifiez votre requête ici, ou publiez un brouillon pour construire son rapport Report Builder actif. Dépublier remet un rapport actif hors ligne.';
$string['tourstep4title'] = 'Modifier et publier';
$string['tourstep5content'] = 'Ce menu regroupe le reste des actions : modifier dans Report Builder, voir le graphique, programmer l\'envoi par e-mail, copier le code d\'intégration, dupliquer et supprimer.';
$string['tourstep5title'] = 'Autres actions';
$string['tsfmtdd'] = 'Jour, 2 chiffres (05)';
$string['tsfmtddd'] = 'Jour de la semaine, abrégé (lun)';
$string['tsfmtdddd'] = 'Jour de la semaine, complet (lundi)';
$string['tsfmthelpintro'] = 'Ajoutez un format optionnel comme second argument, par exemple <code>%%TIMESTAMP(u.timecreated, dd/mm/yyyy)%%</code>. Sans cela, les dates s\'affichent comme <code>{$a}</code>. Les séparateurs comme / - . : et les espaces sont conservés.';
$string['tsfmthelptitle'] = 'Format d\'affichage de la date';
$string['tsfmthelptokens'] = 'Jetons de format';
$string['tsfmthh'] = 'Heure, format 24h (17)';
$string['tsfmtmi'] = 'Minutes (20)';
$string['tsfmtmm'] = 'Mois, 2 chiffres (06)';
$string['tsfmtmmm'] = 'Nom du mois, abrégé (juin)';
$string['tsfmtmmmm'] = 'Nom du mois, complet (juin)';
$string['tsfmtmon'] = 'Nom du mois, abrégé (juin)';
$string['tsfmtmonth'] = 'Nom du mois, complet (juin)';
$string['tsfmtss'] = 'Secondes (09)';
$string['tsfmtyy'] = 'Année, 2 chiffres (26)';
$string['tsfmtyyyy'] = 'Année, 4 chiffres (2026)';

$string['unpublish'] = 'Dépublier';




$string['usage:detaillabel'] = 'Détail de l\'utilisation';
$string['usage:detailtitle'] = 'Utilisation du rapport : {$a}';
$string['usage:firstviewed'] = 'Première consultation';
$string['usage:intro'] = 'Fréquence à laquelle chaque source de rapport publiée a été ouverte. Chaque ouverture d\'un rapport est enregistrée ; triez, filtrez et exportez la liste selon vos besoins. L\'historique plus ancien que la fenêtre de conservation configurée est supprimé automatiquement.';
$string['usage:lastviewed'] = 'Dernière consultation';
$string['usage:linklabel'] = 'Utilisation du rapport';
$string['usage:nodata'] = 'Cette source de rapport n\'a pas encore été ouverte.';
$string['usage:perreport'] = 'Consultations par rapport';
$string['usage:recent'] = 'Ouvertures récentes';
$string['usage:report'] = 'Rapport';
$string['usage:reportn'] = 'Rapport {$a}';
$string['usage:reportsdeleted'] = 'Rapports supprimés';
$string['usage:title'] = 'Utilisation du rapport';
$string['usage:topviewers'] = 'Meilleurs consultants';
$string['usage:trend'] = 'Consultations au cours des 30 derniers jours';
$string['usage:uniqueviewers'] = 'Consultants uniques';
$string['usage:views'] = 'Consultations';
$string['usage:when'] = 'Quand';
$string['userdocs'] = 'Documentation utilisateur';
$string['useridcolumn'] = 'Limiter à l\'utilisateur consultant';
$string['useridcolumn_help'] = 'Limite éventuellement la portée de ce rapport afin que chaque personne ne voie que les lignes qui lui appartiennent. Choisissez la colonne de sortie contenant un identifiant utilisateur ; au moment de la consultation, le rapport n\'affiche que les lignes où cette colonne est égale à l\'identifiant de l\'utilisateur connecté. Laissez sur « Choisir une colonne… » pour afficher toutes les lignes à tous les membres du public.';
$string['useridfilter'] = 'Filtre par utilisateur';
$string['viewchart'] = 'Voir le graphique';
$string['visible'] = 'Visible';
$string['visible_help'] = 'Détermine si ce rapport publié apparaît dans la page de liste des requêtes. Lorsque décoché, les utilisateurs disposant de la capacité de consultation ne peuvent pas le voir. La vue de base de données et le rapport sous-jacents existent toujours — les administrateurs et les auteurs disposant de la capacité viewall peuvent toujours le voir.

Pour un contrôle d\'accès plus fin, utilisez la fonctionnalité Publics de Report Builder après publication : ouvrez le rapport, allez dans l\'onglet Public, et restreignez par cohorte, rôle ou utilisateur individuel.';
$string['warnlinkoffsite'] = 'Le chemin %%LINK%% \'{$a}\' n\'est pas relatif au site, cette colonne affichera donc du texte brut au lieu d\'un lien. Utilisez un chemin commençant par / (par exemple /user/view.php?id={}) — les liens vers d\'autres sites ne sont pas autorisés.';
$string['warnlinkunnamed'] = 'Le jeton %%LINK%% sur \'{$a}\' n\'a pas de nom de colonne de sortie, cette colonne affichera donc du texte brut au lieu d\'un lien. Donnez-lui un alias, par exemple %%LINK(...)%% AS profile.';
$string['warnmysqldatefn'] = 'La fonction {$a}, spécifique à MySQL, pourrait ne pas fonctionner sur PostgreSQL. Utilisez un équivalent multiplateforme.';

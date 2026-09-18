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
 * German language strings for the SQL Report plugin.
 *
 * @package   report_sql
 * @copyright 2026 Marcus Green
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Aktionen';
$string['addnew'] = 'Neuer SQL-Bericht';
$string['createfeaturesnote'] = '„Veröffentlichen und weiter bearbeiten“, um weitere Optionen freizuschalten – Diagramme sowie benutzer- und kursbezogene Filter –, die die Spalten des veröffentlichten Berichts zur Konfiguration benötigen.';
$string['ai:copied'] = 'Kopiert';
$string['ai:copy'] = 'Kopieren';
$string['ai:generate'] = 'SQL generieren';
$string['ai:generatedname'] = 'Generierte Abfrage';
$string['ai:generating'] = 'Wird generiert…';
$string['ai:heading'] = 'SQL mit KI generieren';
$string['ai:heading_help'] = 'Beschreiben Sie in normalem Deutsch (oder Englisch), welche Daten Sie möchten, und klicken Sie dann auf **SQL generieren**. Die KI schreibt eine SELECT-Abfrage in den SQL-Editor unten.

Beispiel: "Zeige alle Studierenden, die in mehr als 3 Kursen eingeschrieben sind".

Sie können sich auch auf das bereits im Editor vorhandene SQL beziehen – Eingaben wie „füge dazu eine Spalte hinzu“, „zeige auch die E-Mail-Adresse“ oder „behebe diesen Fehler“ verwenden Ihre aktuelle Abfrage als Ausgangspunkt, statt eine neue von Grund auf zu erstellen.

Insbesondere zieht eine Eingabe, die mit dem Wort **auch** beginnt, Ihr vorhandenes SQL heran und baut darauf auf – zum Beispiel fügt „zeige auch den letzten Login des Benutzers“ dies zur aktuellen Abfrage hinzu, statt sie zu ersetzen.

Überprüfen Sie das generierte SQL immer vor dem Speichern – die KI kann Fehler machen.';
$string['ai:history'] = 'Ihre letzten Anfragen';
$string['ai:historyempty'] = 'Noch kein Verlauf. Generieren Sie eine Abfrage, und sie erscheint hier.';
$string['ai:historyload'] = 'SQL laden';
$string['ai:historywhen'] = 'Wann';
$string['ai:latency'] = 'Generiert in {$a} s – prüfen Sie das SQL vor dem Speichern.';
$string['ai:placeholder'] = 'z. B. Zeige alle Studierenden, die in mehr als 3 Kursen eingeschrieben sind';
$string['ai:prompt'] = 'An das LLM gesendete Eingabeaufforderung';
$string['ai:question'] = 'Beschreiben Sie die gewünschten Daten';
$string['ai:sqldescription'] = 'Wählt {$a->columns} aus {$a->tables} aus.';
$string['ai:sqldescriptionnocols'] = 'Bericht über {$a}.';
$string['ai:sqlname'] = '{$a}-Bericht';
$string['audienceallusers'] = 'Alle Website-Nutzer/innen';
$string['audiencecohort'] = 'Mitglieder von Kohorten';
$string['audiencecohorts'] = 'Kohorten';
$string['audiencecoursemissing'] = 'ein gelöschter Kurs';
$string['audiencecourseparticipant'] = 'Kursteilnehmer/innen';
$string['audiencecourseparticipantdesc'] = 'Nutzer/innen mit aktiver Einschreibung in {$a}.';
$string['audiencecourserole'] = 'Nutzer/innen mit einer Rolle im Kurs';
$string['audiencecourseroledesc'] = 'Nutzer/innen mit einer der gewählten Rollen in {$a} (oder einem übergeordneten Kontext).';
$string['audiencedefault'] = 'Automatisch (basierend auf Kurs und Sichtbarkeit)';
$string['audiencenone'] = 'Niemand (nur Sie und Website-Manager/innen)';
$string['audienceroles'] = 'Rollen';
$string['audiencesettings'] = 'Wer den Bericht ansehen kann';
$string['audiencetype'] = 'Zielgruppe';
$string['audiencetype_help'] = 'Legt fest, wer den veröffentlichten Report-Builder-Bericht öffnen kann.

* **Automatisch** – wird aus den obigen Einstellungen abgeleitet: Ein kursbezogener Bericht wird den Teilnehmer/innen dieses Kurses angezeigt, ein websiteweiter Bericht allen Nutzer/innen, und ein verborgener Bericht nur Ihnen und Website-Manager/innen.
* **Kursteilnehmer/innen / Nutzer/innen mit einer Rolle im Kurs** – erfordern, dass oben ein Kursbezug festgelegt ist.
* **Alle Website-Nutzer/innen**, **Mitglieder von Kohorten**, **Niemand** – gelten websiteweit.

Sie können die Zielgruppe im Reiter „Zielgruppen“ im Report Builder weiter verfeinern, aber ein erneutes Veröffentlichen des Berichts setzt sie auf diese Auswahl zurück.';
$string['bulkactions'] = 'Massenaktionen';
$string['cachedef_schema'] = 'Datenbankschema und Fremdschlüssel-Zuordnung für die Autovervollständigung im Editor';
$string['cacheheader'] = 'Zwischenspeicherung';
$string['cachemode'] = 'Cache-Modus';
$string['cachemode_help'] = 'Der Live-Modus führt das SQL bei jeder Anfrage direkt aus. Der Cache-Modus führt es stattdessen nach einem Zeitplan aus und liefert den zuletzt aktualisierten Snapshot – nutzen Sie dies für eine langsame Abfrage, damit Betrachter/innen schnelle Ergebnisse erhalten und die Datenbank nicht bei jedem Seitenaufruf belastet wird.';
$string['cachemodelive'] = 'Live';
$string['cachemodecached'] = 'Zwischengespeichert';
$string['cachemodecachedbadge'] = 'Zwischengespeichert · alle {$a} Min.';
$string['cacheinterval'] = 'Aktualisierungsintervall';
$string['cacheinterval_help'] = 'Wie oft die Cache-Tabelle im Hintergrund aktualisiert wird. Kürzere Intervalle halten die Daten aktueller, führen die (langsame) Abfrage aber häufiger aus.';
$string['cacheintervaloption'] = 'Alle {$a} Minuten';
$string['cachedataasof'] = 'Datenstand: {$a}';
$string['cachedataasoflabel'] = 'Datenstand';
$string['cachenodata'] = 'Noch nicht aktualisiert – die erste Aktualisierung erfolgt kurz nach der Veröffentlichung.';
$string['cachelasterrorlabel'] = 'Letzter Aktualisierungsfehler: {$a}';
$string['errcacheintervalempty'] = 'Geben Sie ein Aktualisierungsintervall von mindestens 5 Minuten ein.';
$string['errcreatecachetable'] = '{$a}';
$string['errdropcachetable'] = 'Cache-Tabelle konnte nicht gelöscht werden: {$a}';
$string['errrefreshcache'] = 'Cache-Tabelle konnte nicht aktualisiert werden: {$a}';
$string['cacherefreshed'] = 'Cache aktualisiert.';
$string['refreshcachenow'] = 'Cache jetzt aktualisieren';
$string['task:scanduerefreshes'] = 'Fällige Caches für SQL-Berichte im Cache-Modus aktualisieren';
$string['chartbar'] = 'Balkendiagramm';
$string['chartcolumn'] = 'Diagramm';
$string['chartdatalabels'] = 'Wertebeschriftungen anzeigen';
$string['chartdatalabels_help'] = 'Zeichnet jeden dargestellten Wert als Zahl über seinem Balken oder neben seinem Linienpunkt, sodass genaue Werte direkt am Diagramm abgelesen werden können. Gilt nur für Balken- und Liniendiagramme (Kreis-/Ringdiagramme zeigen Werte in der Legende). Wird automatisch unterdrückt, wenn eine Reihe zu viele Punkte hat, um ohne Überlappung beschriftet zu werden. Standardmäßig deaktiviert.';
$string['chartdatalabelslabel'] = 'Jeden Wert im Balken-/Liniendiagramm anzeigen';
$string['chartdoughnut'] = 'Ringdiagramm';
$string['chartdownloadpng'] = 'PNG herunterladen';
$string['chartexportcsv'] = 'CSV exportieren';
$string['chartlabelsize'] = 'Textgröße der Beschriftung';
$string['chartlabelsize_help'] = 'Schriftgröße (in Punkt) für die Kategoriebeschriftungen des Diagramms – die Kreisdiagramm-Legende und die x-Achsen-Beschriftungen bei Balken-/Liniendiagrammen.';
$string['chartlabelsizeoption'] = '{$a} pt';
$string['chartline'] = 'Liniendiagramm';
$string['chartmulticolour'] = 'Mehrfarbige Balken';
$string['chartmulticolour_help'] = 'Gibt jedem Balken eine eigene Farbe aus einer für Farbenblinde geeigneten Palette statt einer gemeinsamen Farbe, sodass Kategorien leichter unterscheidbar sind. Nur für Balkendiagramme (Kreis- und Ringdiagramme sind bereits pro Segment eingefärbt; eine Linie ist eine einzelne Reihe). Standardmäßig deaktiviert.';
$string['chartmulticolourlabel'] = 'Jeden Balken unterschiedlich einfärben';
$string['chartnone'] = 'Kein Diagramm';
$string['chartpie'] = 'Kreisdiagramm';
$string['chartprint'] = 'Drucken';
$string['chartpublishrequired'] = 'Um das Diagramm zu konfigurieren, klicken Sie auf „Veröffentlichen und weiter bearbeiten“.';
$string['chartreportname'] = '{$a} (Diagramm)';
$string['chartrowlimit'] = 'Zeilenlimit für das Diagramm';
$string['chartrowlimit_help'] = 'Maximale Anzahl der darzustellenden Zeilen. Für lesbare Diagramme klein halten (≤ 200).';
$string['chartsettings'] = 'Diagrammeinstellungen';
$string['chartshowdata'] = 'Datentabelle anzeigen';
$string['chartshowdata_help'] = 'Stellt die Beschriftungs- und Wertepaare des Diagramms als Tabelle unterhalb des Diagrammbilds im Diagrammbericht dar. Bietet eine Textalternative für Screenreader und ermöglicht es Betrachter/innen, genaue Zahlen abzulesen. Standardmäßig deaktiviert.';
$string['chartshowdatalabel'] = 'Die dargestellten Werte als Tabelle unter dem Diagramm anzeigen';
$string['charttype'] = 'Diagrammtyp';
$string['chartxcol'] = 'Beschriftungsspalte (X-Achse / Segmente)';
$string['chartxcol_help'] = 'Spalte, deren Werte jeden Balken, Punkt oder Kreissegment beschriften.';
$string['chartycol'] = 'Wertespalte (Y-Achse)';
$string['chartycol_help'] = 'Spalte, deren Werte dargestellt werden. Muss numerische Daten enthalten.';
$string['checkallgood'] = 'Keine Probleme gefunden. Die Abfrage sieht gut aus.';
$string['checkcasecolumnsintro'] = 'Diese Spalten wenden UPPER()/LOWER() in SQL an:';
$string['checkcasecolumnsintroone'] = 'Diese Spalte wendet UPPER()/LOWER() in SQL an:';
$string['checkcasecolumnsmanual'] = 'Der Ausdruck dieser Spalte konnte nicht automatisch gefunden werden – stellen Sie sie manuell auf %%CASE()%% um.';
$string['checkcasecolumnsoutro'] = 'Klicken Sie auf den Namen, um sie auf %%CASE()%% umzustellen, sodass die Groß-/Kleinschreibung bei der Anzeige angewendet wird, während die Spalte weiterhin nach dem Originalwert sortiert und filtert (und datenbankübergreifend portabel bleibt).';
$string['checkdatecolumnsintro'] = 'Diese Spalten sehen wie Datumsangaben aus:';
$string['checkdatecolumnsintroone'] = 'Diese Spalte sieht wie eine Datumsangabe aus:';
$string['checkdatecolumnsmanual'] = 'Der Ausdruck dieser Spalte konnte nicht automatisch gefunden werden – umschließen Sie ihn manuell mit %%TIMESTAMP()%%.';
$string['checkdatecolumnsoutro'] = 'Klicken Sie auf den Namen, um dessen Ausdruck mit %%TIMESTAMP()%% zu umschließen, sodass er als formatiertes, sortierbares Datum angezeigt wird.';
$string['checkdistinctlarge'] = 'SELECT DISTINCT über {$a} Zeilen muss das gesamte Ergebnis sortieren und Duplikate entfernen, was bei dieser Größe langsam ist. Erwägen Sie GROUP BY auf indizierten Spalten oder verzichten Sie auf DISTINCT, wenn die Joins bereits eindeutige Zeilen liefern.';
$string['checkfullscan'] = 'Vollständiger Tabellenscan auf „{$a->table}“ (~{$a->rows} Zeilen), kein Index verwendet. Dieser Bericht könnte langsam sein – fügen Sie einen WHERE-Filter auf eine indizierte Spalte hinzu. Indizierte Spalten: {$a->indexed}.';
$string['checkindexedcolumns'] = 'Indiziert: {$a}.';
$string['checknotindexedcolumns'] = 'Nicht indiziert: {$a}.';
$string['indexedcolumn'] = 'Indizierte Spalte';
$string['checklargeresult'] = 'Diese Abfrage liefert {$a} Zeilen zurück. Große Ergebnismengen werden langsam gerendert – fügen Sie einen Filter oder ein LIMIT hinzu.';
$string['checkleadingwildcard'] = 'Ein LIKE-Muster beginnt mit einem Platzhalter ("%…" oder "_…"). Ein führender Platzhalter verhindert, dass die Datenbank einen Index auf dieser Spalte verwendet, und erzwingt einen vollständigen Scan. Verankern Sie das Muster ("abc%"), wo möglich.';
$string['checknonsargable'] = 'Eine Funktion umschließt eine Spalte in der WHERE-Klausel (z. B. DATE(col) oder LOWER(col)). Dies ist nicht „sargable“ – die Datenbank kann keinen Index auf dieser Spalte verwenden. Filtern Sie stattdessen die reine Spalte (z. B. durch einen Bereichsvergleich oder den Vergleich eines gespeicherten Epoch-Werts).';
$string['checkquery'] = 'Abfrage testen';
$string['checkquery_help'] = 'Führt Ihr SQL gegen die Datenbank aus, ohne zu speichern oder zu veröffentlichen, und meldet das Ergebnis zurück. Es prüft, ob die Abfrage gültig ist und ausgeführt werden kann, zählt die zurückgegebenen Zeilen und markiert mögliche Performance-Probleme – vollständige Tabellenscans, fehlende Indizes, nicht „sargable“ Filter, große oder DISTINCT-Ergebnismengen – sowie Datumsspalten, die Sie eventuell mit %%TIMESTAMP()%% umschließen möchten.

Dies ist nur ein Hinweis: Es ändert nie Ihre Daten und ist vor dem Speichern oder Veröffentlichen nicht erforderlich.';
$string['checkrowcount'] = 'Zurückgegebene Zeilen: {$a}.';
$string['checkrowcounttimed'] = 'Zurückgegebene Zeilen: {$a->rows}. Generiert in {$a->ms} ms.';
$string['checkrowcounttimeout'] = 'Zeitüberschreitung bei der Zeilenzählung nach {$a} s – die Abfrage ist langsam oder das Ergebnis sehr groß. Der Bericht könnte langsam sein.';
$string['checkselectsubquery'] = 'Eine Unterabfrage in der SELECT-Liste wird einmal pro zurückgegebener Zeile ausgewertet, was den Aufwand bei einem großen Ergebnis vervielfacht. Ein JOIN oder ein WITH (CTE) ist meist schneller.';
$string['checksortindex'] = 'Der Bericht sortiert nach {$a->sortcol}, was nicht indiziert ist, sodass die Datenbank das gesamte Ergebnis ordnen muss. Sortieren nach einer indizierten Spalte ist schneller – verfügbare indizierte Spalten: {$a->indexed}.';
$string['compiledsql'] = 'Kompiliertes SQL (was tatsächlich ausgeführt wurde)';
$string['confirmdeletemany'] = 'Möchten Sie diese {$a} Berichtsquelle(n) wirklich löschen? Dabei werden die zugehörige Ansicht und der Bericht entfernt, dies kann nicht rückgängig gemacht werden.';
$string['convertaliasspaces'] = 'Leerzeichen im Spaltenalias automatisch durch Unterstriche ersetzen';
$string['convertquestionmark'] = '? innerhalb von Anführungszeichen automatisch in CHAR(63) umwandeln';
$string['copyof'] = 'Kopie von {$a}';
$string['copysuccess'] = 'Berichtsquelle kopiert. Sie bearbeiten jetzt die Kopie.';
$string['coursecolumn'] = 'Auf Kurse beschränken, die die betrachtende Person unterrichtet';
$string['coursecolumn_help'] = 'Optional den Umfang dieses Berichts so festlegen, dass jede betrachtende Person nur Zeilen für Kurse sieht, die sie unterrichtet. Wählen Sie die Ausgabespalte mit einer Kurs-ID; beim Betrachten zeigt der Bericht dann nur Zeilen, bei denen diese Spalte einem der Kurse entspricht, in denen die betrachtende Person die Rolle „Trainer/in“ oder „Trainer/in ohne Bearbeitungsrecht“ hat.

Eine betrachtende Person, die keine Kurse unterrichtet, sieht keine Zeilen. So können Sie einen einzigen Bericht für eine breite Zielgruppe veröffentlichen (z. B. das gesamte Personal), wobei jede Lehrperson dennoch nur ihre eigenen Kurse sieht. Belassen Sie die Auswahl bei „Spalte wählen…“, wenn kein Trainer-Kurs-Filter gewünscht ist.';
$string['coursescope'] = 'Kursbezug';
$string['coursescope_help'] = 'Der Kurs, zu dem dieser Bericht gehört. Für einen websiteweiten Bericht leer lassen.

Der Kurs bestimmt bei der Veröffentlichung des Berichts zwei Dinge: den Kontext, in dem die Berechtigung „Bericht ansehen“ geprüft wird, sowie die Standard-Zielgruppe (Kursteilnehmer/innen bei einem kursbezogenen Bericht, alle Nutzer/innen bei einem websiteweiten).

Ändern Sie dies, um eine Abfrage neu zuzuordnen – etwa einen importierten Entwurf, der websiteweit gesetzt wurde, weil sein ursprünglicher Kurs auf dieser Website nicht existierte. Sie können nur Kurse wählen, in denen Sie Berichte ansehen dürfen.';
$string['createrole:aigenerate'] = '„KI-SQL-Generierung“ einschließen';
$string['createrole:aigenerate_desc'] = 'Gewährt zusätzlich local/sqlchat:use, damit Inhaber/innen das KI-Fragefeld zur SQL-Generierung nutzen können. Wird nur angezeigt, wenn das Plugin local_sqlchat installiert ist. Deaktiviert lassen, wenn Autor/innen SQL selbst schreiben sollen.';
$string['createrole:approve'] = '„Genehmigen und veröffentlichen“ einschließen';
$string['createrole:approve_desc'] = 'Gewährt zusätzlich report/sql:approve, damit Inhaber/innen Berichtsquellen selbst veröffentlichen und zurückziehen können. Deaktiviert lassen, wenn eine separate genehmigende Person die Entwürfe veröffentlichen soll.';
$string['createrole:author'] = 'Berichtsquellen verfassen';
$string['createrole:author_desc'] = 'Immer eingeschlossen: report/sql:author erlaubt Inhaber/innen, Berichtsquellen zu schreiben und zu speichern (der Zweck der Rolle). Ebenfalls immer gewährt werden moodle/reportbuilder:view, moodle/reportbuilder:viewall und moodle/reportbuilder:editall, damit Inhaber/innen jeden veröffentlichten Bericht unter /reportbuilder/view.php öffnen und bearbeiten können, unabhängig von dessen Zielgruppe oder Eigentümer/in.';
$string['createrole:create'] = 'Rolle erstellen';
$string['createrole:done'] = 'Die Rolle „Berichtsautor/in“ wurde erstellt. Weisen Sie unten Personen zu.';
$string['createrole:exists'] = 'Eine Rolle „Berichtsautor/in“ existiert bereits. Das Absenden dieses Formulars aktualisiert ihre Berechtigungen entsprechend Ihrer untenstehenden Auswahl.';
$string['createrole:intro'] = 'Dies erstellt eine systemweite Rolle, die die Berichtsquellen-Berechtigungen bündelt, sodass Sie vertrauenswürdigen Nicht-Administrator/innen das Verfassen von Berichten ermöglichen können, ohne sie zu vollständigen Website-Manager/innen zu machen. Wählen Sie, welche Berechtigungen eingeschlossen werden sollen, und erstellen Sie dann die Rolle und weisen Sie Personen zu.';
$string['createrole:linklabel'] = 'Die Rolle „Berichtsautor/in“ erstellen';
$string['createrole:title'] = 'Die Rolle „Berichtsautor/in“ erstellen';
$string['createrole:updated'] = 'Die Berechtigungen der Rolle „Berichtsautor/in“ wurden aktualisiert. Weisen Sie unten Personen zu.';
$string['createrole:viewall'] = '„Alle Berichtsquellen ansehen“ einschließen';
$string['createrole:viewall_desc'] = 'Gewährt zusätzlich report/sql:viewall, damit Inhaber/innen die Berichtsquellen aller Personen sehen und verwalten können, nicht nur ihre eigenen.';
$string['createrole:warning'] = 'Das Verfassen eines Berichts bedeutet, ein beliebiges SQL-SELECT zu schreiben, das fast jede Tabelle in der Datenbank lesen kann (nur eine kleine Sperrliste wie config-, sessions- und Passworttabellen ist blockiert). Diese Rolle stellt daher faktisch eine websiteweite Leseberechtigung für Daten dar. Weisen Sie sie nur Personen zu, denen Sie direkten Lesezugriff auf die Datenbank zutrauen würden, und stellen Sie sicher, dass sensible Spalten durch die Spalten-Sperrliste in den Plugin-Einstellungen abgedeckt sind.';
$string['crimport:colname'] = 'Bericht';
$string['crimport:colnotes'] = 'Angewendete Änderungen';
$string['crimport:colreason'] = 'Grund';
$string['crimport:coltype'] = 'Typ';
$string['crimport:importableheading'] = 'Importierbare Berichte';
$string['crimport:importselected'] = 'Auswahl importieren';
$string['crimport:intro'] = 'Dies sind die SQL-Berichte, die im Block „Configurable Reports“ gefunden wurden. Importierbare Berichte lassen sich sauber übersetzen und werden als Entwürfe erstellt, die Ihnen gehören und bereit zur Veröffentlichung sind. Abgelehnte Berichte verwenden Funktionen, die nicht automatisch konvertiert werden können – übertragen Sie diese von Hand.';
$string['crimport:linklabel'] = 'Aus Configurable Reports importieren';
$string['crimport:noneimportable'] = 'Es konnten keine SQL-Berichte aus Configurable Reports automatisch übersetzt werden. Der Grund steht in der Liste der abgelehnten Berichte unten.';
$string['crimport:noneselected'] = 'Es wurden keine Berichte ausgewählt.';
$string['crimport:noteclean'] = 'Keine Änderungen nötig';
$string['crimport:notedatefn'] = 'MySQL-Datumsfunktion(en) in portable %%TIMESTAMP%%- / %%EPOCH%%- / %%NOW%%-Token umgeschrieben';
$string['crimport:notenativedate'] = 'Native MySQL-Datumsfunktion(en) {$a} beibehalten – sie funktionieren auf dieser MySQL/MariaDB-Datenbank, aber der importierte Bericht wird nicht nach PostgreSQL portierbar sein';
$string['crimport:noteqmark'] = 'Literales ? in einer Zeichenkette in chr(63) umgeschrieben';
$string['crimport:notequotes'] = '„Doppelt zitierte“ Zeichenkettenliterale in \'einfach zitierte\' umgewandelt';
$string['crimport:notetoken'] = 'Configurable-Reports-Token {$a} ersetzt';
$string['crimport:reasondatefn'] = 'Verwendet die MySQL-exklusive Datumsfunktion {$a}, für die es keine portable Entsprechung gibt';
$string['crimport:reasonfilter'] = 'Verwendet ein interaktives Filtertoken {$a}; erstellen Sie dies nach dem Import als Report-Builder-Filter neu';
$string['crimport:reasonnosql'] = 'Aus diesem Bericht konnte kein SQL dekodiert werden';
$string['crimport:reasonnotsql'] = 'Kein SQL-Bericht (Typ: {$a})';
$string['crimport:reasontoken'] = 'Verwendet ein nicht unterstütztes Token {$a}';
$string['crimport:reasonuserid'] = 'Verwendet {$a}; nutzen Sie stattdessen die Einstellung „Auf betrachtende/n Nutzer/in beschränken“ im importierten Entwurf';
$string['crimport:rejectedheading'] = 'Abgelehnte Berichte';
$string['crimport:title'] = 'Aus Configurable Reports importieren';
$string['crimport:title_help'] = 'Importiert die im Block „Configurable Reports“ (block_configurable_reports) gespeicherten SQL-Berichte als Berichtsquellen-Entwürfe.

Jeder Bericht wird dekodiert und einer festen Übersetzung unterzogen: MySQL-Datumsfunktionen werden zu portablen %%TIMESTAMP%%- / %%EPOCH%%- / %%NOW%%-Token, doppelt zitierte Zeichenketten werden einfach zitiert, und ein literales ? in einer Zeichenkette wird mit chr(63) neu aufgebaut. Berichte, die nicht konvertierbare Funktionen verwenden (wie %%USERID%% oder interaktive %%FILTER%%-Token), werden mit Begründung als abgelehnt aufgeführt.

Importierte Berichte landen als Entwürfe, die Ihnen gehören, und müssen vor dem Livebetrieb veröffentlicht werden. Es wird keine KI verwendet – jede Umwandlung folgt einer festen Regel.';
$string['crimport:unavailable'] = 'Der Block „Configurable Reports“ (block_configurable_reports) ist nicht installiert, daher gibt es nichts zu importieren.';
$string['customisecolumns'] = 'Bericht anpassen';
$string['customsqlimport:intro'] = 'Dies sind die Abfragen, die im Bericht „Ad-hoc Database Queries“ (report_customsql) gefunden wurden. Importierbare Abfragen lassen sich sauber übersetzen und werden als Entwürfe erstellt, die Ihnen gehören und bereit zur Veröffentlichung sind. Abgelehnte Abfragen verwenden Funktionen, die nicht automatisch konvertiert werden können – übertragen Sie diese von Hand.';
$string['customsqlimport:linklabel'] = 'Aus Ad-hoc Database Queries importieren';
$string['customsqlimport:noneimportable'] = 'Es konnten keine Ad-hoc Database Queries automatisch übersetzt werden. Der Grund steht in der Liste der abgelehnten Einträge unten.';
$string['customsqlimport:noteescape'] = 'customsql-Escape-Token (%%Q%% / %%C%% / %%S%%) durch ihre Literalzeichen ersetzt';
$string['customsqlimport:reasonparam'] = 'Verwendet den interaktiven benannten Parameter {$a}; erstellen Sie dies nach dem Import als Report-Builder-Filter neu';
$string['customsqlimport:title'] = 'Aus Ad-hoc Database Queries importieren';
$string['customsqlimport:title_help'] = 'Importiert die im Bericht „Ad-hoc Database Queries“ (report_customsql) gespeicherten Abfragen als Berichtsquellen-Entwürfe.

Jede Abfrage wird einer festen Übersetzung unterzogen: MySQL-Datumsfunktionen werden zu portablen %%TIMESTAMP%%- / %%EPOCH%%- / %%NOW%%-Token, doppelt zitierte Zeichenketten werden einfach zitiert, customsql-Escape-Token (%%Q%% / %%C%% / %%S%%) werden zu ihren Literalzeichen, und ein literales ? in einer Zeichenkette wird mit chr(63) neu aufgebaut. Abfragen, die nicht konvertierbare Funktionen verwenden (wie %%USERID%% oder interaktive :benannte Parameter), werden mit Begründung als abgelehnt aufgeführt.

Importierte Abfragen landen als Entwürfe, die Ihnen gehören, und müssen vor dem Livebetrieb veröffentlicht werden. customsql kennt keinen kursbezogenen Umfang, daher startet jeder Entwurf websiteweit. Es wird keine KI verwendet – jede Umwandlung folgt einer festen Regel.';
$string['customsqlimport:unavailable'] = 'Der Bericht „Ad-hoc Database Queries“ (report_customsql) ist nicht installiert, daher gibt es nichts zu importieren.';
$string['delete'] = 'Löschen';
$string['deleteselected'] = 'Auswahl löschen';
$string['deleteselecthelp'] = 'Markieren Sie die zu löschenden Berichtsquellen. Das Löschen entfernt die zugehörige Datenbankansicht und den Bericht und kann nicht rückgängig gemacht werden.';
$string['description'] = 'Beschreibung';
$string['duplicate'] = 'Duplizieren';
$string['edit'] = 'Bearbeiten';
$string['editreport'] = 'Im Report Builder bearbeiten';
$string['embedcodecopied'] = 'Einbettungscode kopiert';
$string['embedcodecopy'] = 'Einbettungscode kopieren';
$string['entityquery'] = 'Berichtsquelle';
$string['erraliasspaces'] = 'Der Spaltenalias „{$a}“ enthält Leerzeichen. Spaltenaliase in SQL Report dürfen keine Leerzeichen enthalten – verwenden Sie stattdessen einen Unterstrich oder Camel Case, z. B. SELECT firstname AS first_name FROM user. Sie können die Spalte nach der Veröffentlichung mit Report Builder mit Leerzeichen umbenennen.';
$string['erraudiencecohortsempty'] = 'Wählen Sie mindestens eine Kohorte.';
$string['erraudiencecourse'] = 'Diese Zielgruppe bezieht sich auf einen Kurs. Wählen Sie oben zunächst einen Kursbezug, bevor Sie sie auswählen.';
$string['erraudiencerolesempty'] = 'Wählen Sie mindestens eine Rolle.';
$string['errchartdata'] = 'Die Berichtsdaten für dieses Diagramm konnten nicht geladen werden. Wenden Sie sich an den/die Berichtseigentümer/in, falls dies bestehen bleibt.';
$string['errchartnotconfigured'] = 'Für diese Abfrage ist kein Diagramm konfiguriert. Bearbeiten Sie die Abfrage, um Diagrammeinstellungen hinzuzufügen.';
$string['errchartnotpublished'] = 'Diese Abfrage ist nicht veröffentlicht. Veröffentlichen Sie sie zuerst, bevor Sie das Diagramm ansehen.';
$string['errcolumnnoalias'] = 'Die Spalte „{$a}“ ist ein Ausdruck ohne Namen. Geben Sie jeder berechneten oder aggregierten Spalte einen Alias, z. B. SELECT count(*) AS total FROM course.';
$string['errcourseidplaceholder'] = 'Das SQL verwendet %%COURSEID%%, daher benötigt dieser Bericht einen festen Kursbezug. Wählen Sie oben einen Kurs, bevor Sie speichern – oder, um jedem Kurs seine eigenen Daten in einem Block anzuzeigen, entfernen Sie den %%COURSEID%%-Filter aus dem SQL, geben Sie die Kurs-ID-Spalte aus und setzen Sie stattdessen „Auf den Kurs beschränken, in dem sich der Block befindet“.';
$string['errcreateview'] = '{$a}';
$string['errdeniedcolumn'] = 'Nicht erlaubte Spalte: {$a}';
$string['errdeniedkeyword'] = 'Nicht erlaubtes Schlüsselwort: {$a}';
$string['errdeniedtable'] = 'Nicht erlaubte Tabelle: {$a}';
$string['errdropview'] = 'Datenbankansicht konnte nicht gelöscht werden: {$a}';
$string['errduplicatecolumn'] = 'Verknüpfte Tabellen teilen sich doppelte Spaltennamen (z. B. haben beide „id“). Ersetzen Sie SELECT * durch explizite Spaltenaliase: SELECT u.id AS userid, fp.id AS postid, ...';
$string['errimportempty'] = 'Die Exportdatei enthält keine Berichtsquellen.';
$string['errimportformat'] = 'Diese Datei ist kein gültiger SQL-Report-Export.';
$string['errjoinnoon'] = 'Einem JOIN fehlt die ON- (oder USING-)Bedingung. Jeder JOIN benötigt eine Verknüpfungsbedingung, z. B. JOIN {user_enrolments} ue ON ue.userid = u.id';
$string['errmultistatement'] = 'Mehrere Anweisungen sind nicht erlaubt.';
$string['errnodeleteselection'] = 'Wählen Sie mindestens eine Berichtsquelle zum Löschen.';
$string['errnoexportselection'] = 'Wählen Sie mindestens eine Berichtsquelle für den Export.';
$string['errnoimportselection'] = 'Wählen Sie mindestens eine Berichtsquelle für den Import.';
$string['errnotselect'] = 'Nur SELECT-Abfragen sind erlaubt.';
$string['errpagecourseambiguous'] = 'Ein Bericht darf nur ein %%PAGECOURSE(expr)%%-Token enthalten, und dessen Ausdruck muss sich zu einer benannten Ausgabespalte auflösen. Verwenden Sie ein einzelnes %%PAGECOURSE()%%, das die Spalte mit der Kurs-ID markiert, und geben Sie ihr einen Alias, z. B. SELECT %%PAGECOURSE(c.id)%% AS courseid, ... FROM {course} c.';
$string['errpagecourseunresolved'] = 'Das %%PAGECOURSE()%%-Token konnte keiner Ausgabespalte mit dem Namen „{$a}“ zugeordnet werden. Geben Sie der markierten Spalte einen expliziten Alias, damit sie zu einer benannten Ansichtsspalte wird, z. B. SELECT %%PAGECOURSE(c.id)%% AS courseid, ... – die Veröffentlichung wurde gestoppt, statt den Seitenkurs-Filter unangewendet zu lassen.';
$string['errparse'] = 'Das SQL konnte nicht analysiert werden: {$a}';
$string['errpgsqldatefn'] = 'Die PostgreSQL-exklusive Funktion {$a} wird von MySQL nicht unterstützt. Verwenden Sie eine datenbankübergreifende Entsprechung.';
$string['errplaceholder'] = 'Das SQL enthält einen nicht ausgefüllten Platzhalter „{$a}“. Ersetzen Sie ihn vor dem Speichern durch einen echten Wert – z. B. ändern Sie „l.userid = ##“ zu „l.userid = 2“.';
$string['errplaceholderuserid'] = 'Das SQL enthält „{$a}“, was kein unterstützter Platzhalter ist. Es gibt keinen Platzhalter pro Betrachter/in, da der Bericht von einer festen Datenbankansicht ausgeht. Um den Bericht auf die Zeilen der Person zu beschränken, die ihn öffnet, umschließen Sie entweder die Benutzer-ID-Spalte im SQL mit %%VIEWER(...)%% – z. B. SELECT %%VIEWER(u.id)%% AS viewerid, ... – oder entfernen Sie „{$a}“ und wählen Sie die Benutzer-ID-Spalte im Feld „Auf betrachtende/n Nutzer/in beschränken“ am Ende dieses Formulars. So oder so wird der Pro-Nutzer-Filter automatisch zur Laufzeit angewendet.';
$string['errqualifiedtable'] = 'Der schemaqualifizierte Tabellenverweis „{$a}“ ist nicht erlaubt. Berichte dürfen nur die eigenen Tabellen der Website über Moodles {tablename}-Syntax lesen; schema- oder datenbankübergreifende Verweise (z. B. information_schema.columns) sind gesperrt.';
$string['errquestionmark'] = 'SQL enthält ein ?-Zeichen, das die Datenbankschicht als Platzhalter für Abfrageparameter behandelt. Falls ? innerhalb einer URL-Zeichenkette vorkommt, ersetzen Sie es durch CHAR(63) – z. B. CONCAT(\'…/view.php\', CHAR(63), \'id=\', course.id).';
$string['errteachesambiguous'] = 'Ein Bericht darf nur ein %%TEACHES(expr)%%-Token enthalten, und dessen Ausdruck muss sich zu einer benannten Ausgabespalte auflösen. Verwenden Sie ein einzelnes %%TEACHES()%%, das die Spalte mit der Kurs-ID markiert, und geben Sie ihr einen Alias, z. B. SELECT %%TEACHES(c.id)%% AS courseid, ... FROM {course} c.';
$string['errteachesunresolved'] = 'Das %%TEACHES()%%-Token konnte keiner Ausgabespalte mit dem Namen „{$a}“ zugeordnet werden. Geben Sie der markierten Spalte einen expliziten Alias, damit sie zu einer benannten Ansichtsspalte wird, z. B. SELECT %%TEACHES(c.id)%% AS courseid, ... – die Veröffentlichung wurde gestoppt, statt den Trainer-Kurs-Filter unangewendet zu lassen.';
$string['errviewerambiguous'] = 'Ein Bericht darf nur ein %%VIEWER(expr)%%-Token enthalten, und dessen Ausdruck muss sich zu einer benannten Ausgabespalte auflösen. Verwenden Sie ein einzelnes %%VIEWER()%%, das die Spalte mit der Benutzer-ID markiert, und geben Sie ihr einen Alias, z. B. SELECT %%VIEWER(fp.userid)%% AS viewerid, fp.subject FROM {forum_posts} fp.';
$string['errviewerunresolved'] = 'Das %%VIEWER()%%-Token konnte keiner Ausgabespalte mit dem Namen „{$a}“ zugeordnet werden. Geben Sie der markierten Spalte einen expliziten Alias, damit sie zu einer benannten Ansichtsspalte wird, z. B. SELECT %%VIEWER(u.id)%% AS viewerid, ... – die Veröffentlichung wurde gestoppt, statt den Bericht unbeschränkt zu lassen (was jeder betrachtenden Person alle Zeilen zeigen würde).';
$string['event:querycreated'] = 'Ad-hoc-Abfrage erstellt';
$string['event:querydeleted'] = 'Ad-hoc-Abfrage gelöscht';
$string['event:querypublished'] = 'Ad-hoc-Abfrage veröffentlicht';
$string['event:queryunpublished'] = 'Ad-hoc-Abfrage zurückgezogen';
$string['event:queryupdated'] = 'Ad-hoc-Abfrage aktualisiert';
$string['export'] = 'Exportieren';
$string['exportselected'] = 'Auswahl exportieren';
$string['exportselecthelp'] = 'Markieren Sie die Berichtsquellen für die Exportdatei und laden Sie dann das JSON herunter.';
$string['filterpublishrequired'] = 'Um die Pro-Nutzer- und Pro-Kurs-Filter zu konfigurieren, klicken Sie auf „Veröffentlichen und weiter bearbeiten“.';
$string['copysql'] = 'SQL kopieren';
$string['copysqldone'] = 'SQL in die Zwischenablage kopiert';
$string['copysqltooltip'] = 'Das SQL in die Zwischenablage kopieren';
$string['copysqlprefixed'] = 'SQL mit echten Tabellennamen kopieren';
$string['copysqlprefixeddone'] = 'SQL mit echten, präfixierten Tabellennamen kopiert';
$string['copysqlmenu'] = 'Kopieroptionen';
$string['errcopyprefixed'] = 'Das präfixierte SQL konnte nicht kopiert werden.';
$string['formatsql'] = 'SQL formatieren';
$string['formatsqltooltip'] = 'SQL in ein Standardlayout umformatieren (Umschalt+Strg+F)';
$string['import'] = 'Importieren';
$string['importdemoted'] = 'Auf websiteweit gesetzt, da der zugehörige Kurs auf dieser Website nicht gefunden wurde. Bearbeiten Sie jeden Entwurf und legen Sie dessen Kursbezug fest, bevor Sie ihn veröffentlichen: {$a}.';
$string['importdone'] = '{$a} Berichtsquelle(n) als Entwürfe importiert.';
$string['importallhidden'] = 'Nichts kann importiert werden: Jede Berichtsquelle in dieser Datei benötigt ein Plugin, das hier nicht installiert ist: {$a}.';
$string['importfile'] = 'Exportdatei';
$string['importhidden'] = 'Ausgeblendet (ein erforderliches Plugin ist hier nicht installiert): {$a}.';
$string['importselected'] = 'Auswahl importieren';
$string['importselecthelp'] = 'Markieren Sie die zu importierenden Berichtsquellen. Jede wird als neuer Entwurf erstellt, der Ihnen gehört, und muss vor der Nutzung veröffentlicht werden.';
$string['importskipped'] = 'Übersprungen (SQL-Validierung fehlgeschlagen): {$a}.';
$string['importupload'] = 'Hochladen und auswählen';
$string['importuploadhelp'] = 'Laden Sie eine zuvor durch die Exportfunktion erzeugte JSON-Datei hoch. Anschließend wählen Sie aus, welche Berichtsquellen importiert werden sollen.';
$string['install:createrole'] = 'Optional eine Rolle „Berichtsautor/in“ erstellen, damit Nicht-Administrator/innen Berichte verfassen können. Prüfen Sie zunächst die sicherheitsrelevanten Auswirkungen: {$a}';
$string['install:loadsamples'] = 'SQL Report liefert Beispiel-SQL-Berichte mit, die Sie zum Einstieg laden können: {$a}';
$string['install:privilegefail'] = 'SQL Report wurde installiert, aber der Datenbankbenutzer kann keine Ansichten erstellen oder löschen. Das Veröffentlichen von Abfragen schlägt fehl, bis die Berechtigungen korrigiert sind. Fehler: {$a}';
$string['install:privilegeok'] = 'SQL Report: Der Datenbankbenutzer kann Ansichten erstellen und löschen.';
$string['lastmodified'] = 'Zuletzt geändert';
$string['linkargexpr'] = 'Der in der Zelle angezeigte Spaltenwert.';
$string['linkargkeycol'] = 'Optional: eine weitere Ausgabespalte, deren Wert <code>{}</code> füllt, sodass die Zelle eine Sache anzeigen kann, während der Link sich auf eine andere bezieht.';
$string['linkargpath'] = "Websiteinterne URL (muss mit <code>/</code> beginnen, kein Schema); <code>{}</code> wird durch den URL-codierten Wert ersetzt, z. B. <code>/user/view.php?id={}</code>.";
$string['linkhelpargs'] = 'Argumente';
$string['linkhelpintro'] = 'Eine Zelle als Link darstellen: <code>%%LINK(expr, \'path\')%%</code> oder <code>%%LINK(expr, keycol, \'path\')%%</code>.';
$string['linkhelptitle'] = 'Link-Token';
$string['name'] = 'Name';
$string['noqueries'] = 'Noch keine Berichtsquellen.';
$string['norows'] = 'Keine Daten zum Anzeigen.';
$string['owner'] = 'Eigentümer/in';
$string['pagecoursecolumn'] = 'Auf den Kurs beschränken, in dem sich der Block befindet';
$string['pagecoursecolumn_help'] = 'Gilt nur, wenn dieser Bericht über den SQL-Report-Block auf einer Kursseite angezeigt wird. Wählen Sie die Ausgabespalte mit einer Kurs-ID; der Block zeigt dann nur Zeilen für den Kurs der Seite, auf der er sich befindet, sodass ein Block (oder ein Block, der jedem Kurs hinzugefügt wird) jedem Kurs seine eigenen Daten zeigt.

Außerhalb einer Kursseite (Dashboard oder die Startseite der Website) wird kein Seitenkurs-Filter angewendet. Der eigenständige Berichtsbetrachter ignoriert dies ebenfalls, da er keinen „aktuellen Kurs“ hat. Belassen Sie die Auswahl bei „Spalte wählen…“, wenn kein Seitenkurs-Filter gewünscht ist.';
$string['plugindisabled'] = 'SQL Report ist derzeit von der Website-Administration deaktiviert.';
$string['pluginexplained'] = 'Über Berichtsquellen';
$string['pluginexplained_help'] = 'Mit diesem Plugin können Sie eine SQL-SELECT-Abfrage schreiben und als vollständig konfigurierbaren Report-Builder-Bericht veröffentlichen – ohne PHP-Kenntnisse.

Wenn Sie eine Abfrage veröffentlichen, erstellt das Plugin eine Datenbankansicht (VIEW) aus Ihrem SQL, liest deren Spalten aus und registriert eine Report-Builder-Datenquelle, die auf diese Ansicht verweist. Anschließend können Sie den Bericht wie jeden anderen Report-Builder-Bericht erstellen, filtern und teilen.

Nur SELECT-Abfragen sind erlaubt, und eine Sperrliste blockiert den Zugriff auf sensible Tabellen. Das Bearbeiten des SQL einer veröffentlichten Abfrage baut die Ansicht und den Bericht bei der nächsten Veröffentlichung neu auf.';
$string['pluginname'] = 'SQL Report';
$string['preview'] = 'Vorschau der ersten 5 Zeilen';
$string['preview_help'] = 'Stellt Ihr aktuelles SQL als echten Report-Builder-Bericht dar, direkt eingebettet und ohne zu speichern oder zu veröffentlichen. Spalten werden genau so typisiert und formatiert wie bei der Veröffentlichung, sodass dies eine schnelle Möglichkeit ist, zu sehen, wie der Bericht aussehen wird. Nur die ersten 5 Zeilen werden angezeigt.';
$string['previewheading'] = 'Vorschauergebnis';
$string['previewloading'] = 'Vorschau wird erstellt…';
$string['privacy:metadata:query'] = 'Von Nutzer/innen verfasste gespeicherte Berichtsquellen.';
$string['privacy:metadata:query:ownerid'] = 'Nutzer/in, die/der die Abfrage verfasst hat.';
$string['privacy:metadata:query:querysql'] = 'Das SQL der Abfrage.';
$string['privacy:metadata:query:timecreated'] = 'Wann die Abfrage erstellt wurde.';
$string['privacy:metadata:queryview'] = 'Ein Protokoll, welche veröffentlichten Berichtsquellen von welcher Person geöffnet wurden.';
$string['privacy:metadata:queryview:timeviewed'] = 'Wann die Berichtsquelle geöffnet wurde.';
$string['privacy:metadata:queryview:userid'] = 'Nutzer/in, die/der die Berichtsquelle geöffnet hat.';
$string['publish'] = 'Veröffentlichen';
$string['queries'] = 'Gespeicherte SQL-Berichte';
$string['querysql'] = 'SQL (nur SELECT)';
$string['querysql_help'] = 'Eine einzelne SELECT- oder WITH...SELECT-Anweisung. Verwenden Sie die Moodle-Tabellensyntax (z. B. {course}). Das Plugin erstellt aus dieser Abfrage eine Datenbankansicht (VIEW) und stellt deren Spalten als Reportbuilder-Quelle bereit.

Versehen Sie Tabellen immer mit einem Alias (z. B. FROM {user} u), da {user} zur Laufzeit zu mdl_user aufgelöst wird.

Umschließen Sie eine Textspalte mit %%CASE(expr, mode)%%, um sie in Groß-, Klein-, Titel- oder Satzschreibweise anzuzeigen (z. B. %%CASE(u.lastname, upper)%%). Der gespeicherte Wert bleibt unverändert, sodass die Spalte weiterhin nach dem Originaltext sortiert und filtert, und die Umwandlung funktioniert gleich auf MySQL/MariaDB und PostgreSQL.

Das Moodle-Datenbankschema finden Sie unter <a href="https://www.examulator.com/er/output/index.html" target="_blank">examulator.com/er</a>.

Beispielabfragen und Anregungen finden Sie unter <a href="https://docs.moodle.org/502/en/ad-hoc_contributed_reports" target="_blank">Moodle ad-hoc contributed reports</a>.';
$string['reportsource'] = 'Berichtsquelle';
$string['reportsourceheader'] = '{$a}';
$string['reportsources'] = 'SQL-Berichte';
$string['repository:intro'] = 'Diese geteilten Berichtsquellen stammen aus dem entfernten Repository {$a}. Markieren Sie die gewünschten und importieren Sie sie. Jede wird als Entwurf erstellt, der Ihnen gehört und den Sie vor der Nutzung veröffentlichen müssen.';
$string['repository:introsingle'] = 'Diese geteilten Berichtsquellen stammen aus dem entfernten Repository {$a}. Wählen Sie eine zum Importieren aus. Sie wird als Entwurf erstellt, der Ihnen gehört, mit dem Präfix „Sample:“ benannt, und Sie müssen sie vor der Nutzung veröffentlichen.';
$string['repository:linklabel'] = 'Aus geteiltem Repository importieren';
$string['repository:none'] = 'Es konnten keine geteilten Berichtsquellen aus dem Repository {$a} gelesen werden. Überprüfen Sie die URL und ob das Repository Exportdateien für Berichtsquellen enthält.';
$string['repository:noneselected'] = 'Es wurden keine Berichtsquellen ausgewählt.';
$string['repository:refresh'] = 'Vom Repository aktualisieren';
$string['repository:title'] = 'Berichtsquellen aus geteiltem Repository';
$string['repository:titlesingle'] = 'Eine geteilte Berichtsquelle importieren';
$string['repository:unconfigured'] = 'Es ist kein geteiltes Repository konfiguriert. Legen Sie zuerst eines in den Plugin-Einstellungen fest (Geteiltes Repository für Berichtsquellen).';
$string['roledescription'] = 'Berichtsquellen (report_sql) websiteweit erstellen, bearbeiten und veröffentlichen. HINWEIS: Das Verfassen erlaubt beliebiges SQL-SELECT gegen die Datenbank, diese Rolle gewährt daher faktisch websiteweiten Lesezugriff auf Daten. Nur vertrauenswürdigen Berichtserstellenden zuweisen.';
$string['rolename'] = 'Berichtsautor/in';
$string['runreport'] = 'Bericht öffnen';
$string['samples:coldesc'] = 'Beschreibung';
$string['samples:colname'] = 'Name';
$string['samples:colselect'] = 'Importieren';
$string['samples:duplicates'] = 'Übersprungen (bereits vorhanden): {$a}.';
$string['samples:import'] = 'Importieren';
$string['samples:importselected'] = 'Auswahl importieren';
$string['samples:intro'] = 'Diesem Plugin liegen {$a} Beispiel-Berichtsquellen bei. Markieren Sie die gewünschten und importieren Sie sie. Jede wird als Entwurf erstellt, der Ihnen gehört und den Sie vor der Nutzung veröffentlichen müssen.';
$string['samples:introsingle'] = 'Diesem Plugin liegen {$a} Beispiel-Berichtsquellen bei. Wählen Sie eine zum Importieren aus. Sie wird als Entwurf erstellt, der Ihnen gehört, mit dem Präfix „Sample:“ benannt, und Sie müssen sie vor der Nutzung veröffentlichen.';
$string['samples:linklabel'] = 'Beispiel-SQL-Berichte laden';
$string['samples:none'] = 'Es wurden keine mitgelieferten Beispiel-Berichtsquellen gefunden.';
$string['samples:noneselected'] = 'Es wurden keine Beispiele ausgewählt.';
$string['samples:previewsql'] = 'SQL anzeigen';
$string['samples:requires'] = 'Erfordert {$a}';
$string['samples:requiresmissing'] = 'Übersprungen (erforderliches Plugin nicht installiert): {$a}.';
$string['samples:requiresmissingbadge'] = 'Erfordert {$a} (nicht installiert)';
$string['samples:showall'] = '{$a} Beispiel(e) anzeigen, die ein hier nicht installiertes Plugin benötigen';
$string['samples:samplelinklabel'] = 'Beispiel-SQL-Bericht laden';
$string['samples:sampleprefix'] = 'Beispiel: {$a}';
$string['samples:selectall'] = 'Alle auswählen';
$string['samples:selectnone'] = 'Keine auswählen';
$string['samples:title'] = 'Beispiel-SQL-Berichte laden';
$string['samples:titlesingle'] = 'Beispiel-SQL-Bericht laden';
$string['saveandpublish'] = 'Speichern und veröffentlichen';
$string['saveandpublishedit'] = 'Veröffentlichen und weiter bearbeiten';
$string['savedandpublished'] = 'Änderungen gespeichert und Bericht veröffentlicht';
$string['savedpublishfailed'] = 'Änderungen gespeichert, aber die Veröffentlichung ist fehlgeschlagen: {$a}';
$string['schedule'] = 'E-Mail-Versand planen';
$string['selectcolumn'] = '(Spalte wählen)';
$string['settings:aigenerate'] = 'KI-SQL-Generierung';
$string['settings:aigenerate_desc'] = 'Ein KI-Fragefeld im Bearbeitungsformular der Abfrage anzeigen. Erfordert die Installation und Konfiguration des Plugins local_sqlchat.';
$string['settings:denycolumns'] = 'Sperrliste sensibler Spalten';
$string['settings:denycolumns_desc'] = 'Durch Komma, Leerzeichen oder Zeilenumbruch getrennte Liste von Spaltennamen, die aus jedem introspektierten SELECT-Ergebnis entfernt werden.';
$string['settings:denytables'] = 'Tabellen-Sperrliste';
$string['settings:denytables_desc'] = 'Durch Komma, Leerzeichen oder Zeilenumbruch getrennte Liste von Tabellennamen, die niemals abgefragt werden dürfen. Vorbelegt mit der eingebauten Liste geschützter Tabellen des Plugins (config, sessions, Token, Passwortverlauf und Ähnliches). Diese Liste ist vollständig bearbeitbar – das Entfernen eines Eintrags erlaubt Abfragen auf diese Tabelle, also mit Vorsicht bearbeiten.';
$string['settings:enumfilterthreshold'] = 'Schwellenwert für Dropdown-Filter';
$string['settings:enumfilterthreshold_desc'] = 'Wenn eine Textspalte so viele oder weniger verschiedene Werte hat (zum Zeitpunkt der Veröffentlichung gemessen), wird ihr Berichtsfilter als Dropdown dieser Werte statt als Freitextfeld dargestellt. Auf 0 setzen, um dies zu deaktivieren und alle Textspalten als Freitextfilter zu belassen.';
$string['settings:enumrowceiling'] = 'Zeilenobergrenze für Dropdown-Filter';
$string['settings:enumrowceiling_desc'] = 'Die Erkennung von Dropdown-Filtern überspringen, wenn eine veröffentlichte Ansicht mehr als diese Anzahl an Zeilen hat, damit ein großer Bericht bei der Veröffentlichung keinen Distinct-Scan pro Spalte durchführen muss (alle seine Textspalten bleiben Freitext). Auf 0 setzen, um unabhängig von der Größe immer zu prüfen. Nur relevant, wenn der Schwellenwert für Dropdown-Filter ungleich 0 ist.';
$string['settings:enabled'] = 'SQL Report aktivieren';
$string['settings:enabled_desc'] = 'Wenn deaktiviert, ist das Plugin ausgeschaltet: Sein Eintrag wird aus dem Berichte-Menü entfernt, und seine Seiten (Liste, Bearbeiten, Ausführen, Diagramm) sind gesperrt. Über Report Builder erstellte veröffentlichte Berichte sind davon nicht betroffen.';
$string['settings:sharedrepository'] = 'Geteiltes Repository für Berichtsquellen';
$string['settings:sharedrepository_desc'] = 'GitHub-Repository-URL mit geteilten Exportdateien für Berichtsquellen. Autor/innen können darin stöbern und dessen Berichtsquellen als Entwürfe importieren. Leer lassen, um den geteilten Repository-Browser zu deaktivieren.';
$string['settings:sharedrepositoryenabled'] = 'Geteiltes Repository für Berichtsquellen aktivieren';
$string['settings:sharedrepositoryenabled_desc'] = 'Autor/innen erlauben, im unten konfigurierten geteilten Repository für Berichtsquellen zu stöbern und daraus zu importieren. Standardmäßig deaktiviert; solange dies ausgeschaltet ist, wird keine Anfrage an das entfernte Repository gestellt und dessen Browse-Seite sowie Links sind ausgeblendet.';
$string['settings:showbraces'] = 'Tabellenklammern im Editor anzeigen';
$string['settings:showbraces_desc'] = 'Moodle-{table}-Klammern um Tabellennamen im SQL-Editor anzeigen. Wenn deaktiviert, erscheinen Tabellen ohne Klammern; so oder so müssen Sie diese nie selbst eingeben – Klammern werden beim Speichern automatisch hinzugefügt.';
$string['settings:showlastmodified'] = 'Spalte „Zuletzt geändert“ anzeigen';
$string['settings:showlastmodified_desc'] = 'Eine sortierbare Spalte „Zuletzt geändert“ in der Liste der Berichtsquellen anzeigen.';
$string['settings:syntaxhighlight'] = 'SQL-Syntaxhervorhebung und Autovervollständigung';
$string['settings:syntaxhighlight_desc'] = 'Einen CodeMirror-6-SQL-Editor im Abfrageformular aktivieren. Schlägt SQL-Schlüsselwörter sowie Moodle-Tabellen- und Spaltennamen aus der Live-Datenbank vor.';
$string['settings:viewretaindays'] = 'Aufbewahrung des Ansichtsverlaufs (Tage)';
$string['settings:viewretaindays_desc'] = 'Wie viele Tage der Prüfverlauf zu Berichtsansichten aufbewahrt wird. Ältere Zeilen werden durch eine geplante Aufgabe entfernt. Auf 0 setzen, um den Verlauf dauerhaft aufzubewahren.';
$string['sql:approve'] = 'Berichtsquellen genehmigen und veröffentlichen';
$string['sql:author'] = 'SQL-Berichtsquellen verfassen';
$string['sql:view'] = 'Veröffentlichte Berichtsquellen ausführen';
$string['sql:viewall'] = 'Alle Berichtsquellen unabhängig von der Zielgruppe ansehen';
$string['sql:viewown'] = 'Berichtsquellen im eigenen Kurs ausführen';
$string['status'] = 'Status';
$string['status_draft'] = 'Entwurf';
$string['status_published'] = 'Veröffentlicht';
$string['strftimeviewdate'] = '%d.%m.%y, %H:%M';
$string['summaryreport'] = 'Zusammenfassungsbericht';
$string['task:purgeviews'] = 'Alten Ansichtsverlauf für Berichte bereinigen';
$string['testquery'] = 'Wird getestet…';
$string['testview:fail'] = 'Der Datenbankbenutzer kann keine Ansichten erstellen oder löschen. Fehler: {$a}';
$string['testview:grantshint'] = 'Gewähren Sie dem Moodle-Datenbankbenutzer die Berechtigungen CREATE VIEW und DROP für das Schema (z. B. unter MySQL/MariaDB: GRANT CREATE VIEW, DROP ON moodle.* TO \'mdluser\'@\'host\';).';
$string['testview:linklabel'] = 'Datenbank-Ansichtsberechtigungstest ausführen';
$string['testview:ok'] = 'Der Datenbankbenutzer kann Ansichten erstellen und löschen. Das Veröffentlichen von Abfragen sollte funktionieren.';
$string['testview:run'] = 'Test ausführen';
$string['testview:intro'] = 'Dieser Test erstellt und löscht sofort wieder eine Wegwerf-Datenbankansicht, um zu bestätigen, dass der Moodle-Datenbankbenutzer die Berechtigungen CREATE VIEW und DROP besitzt.';
$string['testview:title'] = 'Datenbank-Ansichtsberechtigungstest';
$string['timecreated'] = 'Erstellungszeit';
$string['tokenhintcase'] = 'Textumwandlung Groß-/Kleinschreibung: upper | lower | title | sentence (nur Anzeige)';
$string['tokenhintcontextblock'] = 'Kontextebenen-Konstante CONTEXT_BLOCK (80)';
$string['tokenhintcontextcourse'] = 'Kontextebenen-Konstante CONTEXT_COURSE (50)';
$string['tokenhintcontextcoursecat'] = 'Kontextebenen-Konstante CONTEXT_COURSECAT (40)';
$string['tokenhintcontextmodule'] = 'Kontextebenen-Konstante CONTEXT_MODULE (70)';
$string['tokenhintcontextsystem'] = 'Kontextebenen-Konstante CONTEXT_SYSTEM (10)';
$string['tokenhintcontextuser'] = 'Kontextebenen-Konstante CONTEXT_USER (30)';
$string['tokenhintcoursecontext'] = 'Kontext-Zeilen-ID des gebundenen Kurses';
$string['tokenhintcourseid'] = 'Gebundene Kurs-ID (0 = websiteweit)';
$string['tokenhintepoch'] = 'Datumsliteral/-ausdruck in Unix-Epoch-Ganzzahl';
$string['tokenhintlink'] = "Die Zelle mit einem websiteinternen Pfad verlinken: LINK(expr, 'path'), wobei {} im Pfad die Wertstelle ist, z. B. '/user/view.php?id={}'. Fügen Sie eine Schlüsselspalte hinzu, LINK(expr, keycol, 'path'), um den Link auf eine andere Ausgabespalte zu beziehen.";
$string['tokenhintnow'] = 'Aktuelle Zeit als Unix-Epoch-Ganzzahl';
$string['tokenhintpagecourse'] = 'Einen Block/eine Einbettung auf den Kurs beschränken, in dem er/sie sich befindet: PAGECOURSE(expr) markiert die Kurs-ID-Spalte, z. B. PAGECOURSE(c.id) AS courseid. Wird vom Seitenkurs übernommen (wird im eigenständigen Berichtsbetrachter ignoriert).';
$string['tokenhintteaches'] = 'Den Bericht auf Kurse beschränken, die die betrachtende Person unterrichtet: TEACHES(expr) markiert die Kurs-ID-Spalte, z. B. TEACHES(c.id) AS courseid. Wird pro Betrachter/in angewendet; die Spalte bleibt sichtbar.';
$string['tokenhinttimestamp'] = 'Epoch-Spalte in Datum; optionales Format, z. B. dd/mm/yyyy';
$string['tokenhintviewer'] = 'Den Bericht auf die betrachtende Person beschränken: VIEWER(expr) markiert die Benutzer-ID-Spalte, z. B. VIEWER(u.id) AS viewerid. Wird pro Betrachter/in angewendet; die Spalte wird aus der Ausgabe ausgeblendet.';
$string['tokenhintwwwroot'] = 'Website-URL (wwwroot)';
$string['tourdesc'] = 'Eine kurze geführte Tour über die Seite mit der Liste der Berichtsquellen.';
$string['tourname'] = 'SQL-Report-Tour';
$string['tourstep1content'] = 'Beginnen Sie hier, um eine Berichtsquelle zu erstellen. Schreiben Sie eine SQL-<em>SELECT</em>-Abfrage und veröffentlichen Sie sie dann, um einen vollständig konfigurierbaren Report-Builder-Bericht zu erstellen – ohne PHP-Kenntnisse.';
$string['tourstep1title'] = 'Eine Berichtsquelle erstellen';
$string['tourstep2content'] = 'Jede von Ihnen gespeicherte Berichtsquelle wird hier mit Eigentümer/in und Status aufgeführt. Sortieren oder filtern Sie jede Spalte, um schnell eine zu finden.';
$string['tourstep2title'] = 'Ihre Berichtsquellen';
$string['tourstep3content'] = 'Der Status zeigt, ob eine Berichtsquelle noch ein <strong>Entwurf</strong> ist oder als Live-Bericht <strong>veröffentlicht</strong> wurde.';
$string['tourstep3title'] = 'Entwurf oder veröffentlicht';
$string['tourstep4content'] = 'Bearbeiten Sie Ihre Abfrage hier oder veröffentlichen Sie einen Entwurf, um dessen Live-Report-Builder-Bericht zu erstellen. Das Zurückziehen nimmt einen Live-Bericht wieder offline.';
$string['tourstep4title'] = 'Bearbeiten und veröffentlichen';
$string['tourstep5content'] = 'Dieses Menü enthält die übrigen Aktionen: im Report Builder bearbeiten, das Diagramm ansehen, den E-Mail-Versand planen, den Einbettungscode kopieren, duplizieren und löschen.';
$string['tourstep5title'] = 'Weitere Aktionen';
$string['tsfmtdd'] = 'Tag, 2-stellig (05)';
$string['tsfmtddd'] = 'Wochentag, kurz (Mo)';
$string['tsfmtdddd'] = 'Wochentag, vollständig (Montag)';
$string['tsfmthelpintro'] = 'Fügen Sie optional ein Format als zweites Argument hinzu, z. B. <code>%%TIMESTAMP(u.timecreated, dd/mm/yyyy)%%</code>. Ohne dieses werden Datumsangaben als <code>{$a}</code> angezeigt. Trennzeichen wie / - . : und Leerzeichen werden übernommen.';
$string['tsfmthelptitle'] = 'Datumsanzeigeformat';
$string['tsfmthelptokens'] = 'Formattoken';
$string['tsfmthh'] = 'Stunde, 24-Stunden-Format (17)';
$string['tsfmtmi'] = 'Minuten (20)';
$string['tsfmtmm'] = 'Monat, 2-stellig (06)';
$string['tsfmtmmm'] = 'Monatsname, kurz (Jun)';
$string['tsfmtmmmm'] = 'Monatsname, vollständig (Juni)';
$string['tsfmtmon'] = 'Monatsname, kurz (Jun)';
$string['tsfmtmonth'] = 'Monatsname, vollständig (Juni)';
$string['tsfmtss'] = 'Sekunden (09)';
$string['tsfmtyy'] = 'Jahr, 2-stellig (26)';
$string['tsfmtyyyy'] = 'Jahr, 4-stellig (2026)';

$string['unpublish'] = 'Zurückziehen';




$string['usage:detaillabel'] = 'Nutzungsdetail';
$string['usage:detailtitle'] = 'Berichtsnutzung: {$a}';
$string['usage:firstviewed'] = 'Erstmals angesehen';
$string['usage:intro'] = 'Wie oft jede veröffentlichte Berichtsquelle geöffnet wurde. Jedes Öffnen eines Berichts wird protokolliert; sortieren, filtern und exportieren Sie die Liste nach Bedarf. Verlauf, der älter als das konfigurierte Aufbewahrungsfenster ist, wird automatisch entfernt.';
$string['usage:lastviewed'] = 'Zuletzt angesehen';
$string['usage:linklabel'] = 'Berichtsnutzung';
$string['usage:nodata'] = 'Diese Berichtsquelle wurde noch nicht geöffnet.';
$string['usage:perreport'] = 'Aufrufe nach Bericht';
$string['usage:recent'] = 'Kürzliche Aufrufe';
$string['usage:report'] = 'Bericht';
$string['usage:reportn'] = 'Bericht {$a}';
$string['usage:reportsdeleted'] = 'Gelöschte Berichte';
$string['usage:title'] = 'Berichtsnutzung';
$string['usage:topviewers'] = 'Top-Betrachter/innen';
$string['usage:trend'] = 'Aufrufe der letzten 30 Tage';
$string['usage:uniqueviewers'] = 'Eindeutige Betrachter/innen';
$string['usage:views'] = 'Aufrufe';
$string['usage:when'] = 'Wann';
$string['userdocs'] = 'Benutzerdokumentation';
$string['useridcolumn'] = 'Auf betrachtende/n Nutzer/in beschränken';
$string['useridcolumn_help'] = 'Optional den Umfang dieses Berichts so festlegen, dass jede Person nur Zeilen sieht, die zu ihr gehören. Wählen Sie die Ausgabespalte mit einer Benutzer-ID; beim Betrachten zeigt der Bericht dann nur Zeilen, bei denen diese Spalte der ID der angemeldeten Person entspricht. Belassen Sie die Auswahl bei „Spalte wählen…“, um allen Personen der Zielgruppe alle Zeilen anzuzeigen.';
$string['useridfilter'] = 'Pro-Nutzer-Filter';
$string['viewchart'] = 'Diagramm ansehen';
$string['visible'] = 'Sichtbar';
$string['visible_help'] = 'Legt fest, ob dieser veröffentlichte Bericht in der Abfragen-Listenseite erscheint. Wenn nicht markiert, können Nutzer/innen mit der Berechtigung „Ansehen“ ihn nicht sehen. Die zugrunde liegende Datenbankansicht und der Bericht bestehen weiterhin – Administrator/innen und Autor/innen mit der Berechtigung „viewall“ können ihn weiterhin sehen.

Für eine feinere Zugriffskontrolle nutzen Sie nach der Veröffentlichung die Zielgruppen-Funktion im Report Builder: Öffnen Sie den Bericht, gehen Sie zum Reiter „Zielgruppe“ und schränken Sie nach Kohorte, Rolle oder einzelner Person ein.';
$string['warnlinkoffsite'] = 'Der %%LINK%%-Pfad \'{$a}\' ist nicht websiteintern, daher zeigt diese Spalte einfachen Text statt eines Links an. Verwenden Sie einen Pfad, der mit / beginnt (zum Beispiel /user/view.php?id={}) – Links zu anderen Websites sind nicht erlaubt.';
$string['warnlinkunnamed'] = 'Das %%LINK%%-Token für \'{$a}\' hat keinen Ausgabespaltennamen, daher zeigt diese Spalte einfachen Text statt eines Links an. Geben Sie ihm einen Alias, zum Beispiel %%LINK(...)%% AS profile.';
$string['warnmysqldatefn'] = 'Die MySQL-exklusive Funktion {$a} funktioniert möglicherweise nicht unter PostgreSQL. Verwenden Sie eine datenbankübergreifende Entsprechung.';
